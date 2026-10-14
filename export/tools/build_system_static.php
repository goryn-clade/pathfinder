<?php
/**
 * Rebuild wormhole statics (export/csv/system_static.csv and the `system_static` rows in
 * export/sql/eve_universe.sql) from Tripwire's statics.json.
 *
 * CCP publishes no statics: they are in neither ESI nor the SDE. Every list is compiled from
 * player reports. Tripwire maintains its list in the open, with a commit for each change:
 *   https://bitbucket.org/daimian/tripwire/src/production/tools/statics.json
 * Tripwire is MIT licensed (README.md and package.json of that repository).
 *
 * statics.json maps a system name to wormhole codes ("J134637": ["J244"]). This script maps
 * names and codes to ids with the seed's `system` and `type` tables, so run
 * refresh_universe_types.php first if CCP added wormhole types. A code with more than one type id
 * (CCP has two "Wormhole J244") uses the id the CSV already uses for it, else the lowest.
 *
 * Rows keep their id when the (system, type) pair is unchanged, and new pairs get new ids: the
 * /setup import updates rows by id and deletes rows that are no longer in the CSV.
 *
 * Run from the pathfinder repo root (no local PHP needed):
 *   docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.3-cli \
 *     php export/tools/build_system_static.php [--dry-run] [--source=<file or URL>]
 *
 * Then rebuild the zip that the compose stacks mount:
 *   (cd export/sql && rm -f eve_universe.sql.zip && zip -X eve_universe.sql.zip eve_universe.sql)
 */

declare(strict_types=1);

require __DIR__ . '/seed_sql.php';

const TRIPWIRE_STATICS_URL = 'https://bitbucket.org/daimian/tripwire/raw/production/tools/statics.json';
const WORMHOLE_GROUP_ID = 988;
const USER_AGENT = 'Pathfinder export/tools/build_system_static.php (github.com/goryn-clade/pathfinder)';

$options = getopt('', ['dry-run', 'source:', 'sql:', 'csv:']);
$dryRun = isset($options['dry-run']);
$source = $options['source'] ?? TRIPWIRE_STATICS_URL;
$sqlFile = $options['sql'] ?? 'export/sql/eve_universe.sql';
$csvFile = $options['csv'] ?? 'export/csv/system_static.csv';

foreach([$sqlFile, $csvFile] as $file){
    if(!is_file($file)){
        fail("File not found: $file (run from the pathfinder repo root)");
    }
}

// ---- load statics.json ----------------------------------------------------------------------

$context = stream_context_create(['http' => ['timeout' => 30, 'header' => 'User-Agent: ' . USER_AGENT . "\r\n"]]);
if(($json = @file_get_contents($source, false, $context)) === false){
    fail("Cannot read $source");
}
$statics = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
if(!is_array($statics) || !$statics){
    fail("$source holds no statics");
}

// ---- systems and wormhole types from the seed -----------------------------------------------

$lines = explode("\n", file_get_contents($sqlFile));

$systemIds = [];        // name => id
$systemNames = [];      // id => name
$systemClasses = [];    // id => security/class (e.g. C1, L)
foreach(readRows($lines, 'system') as $id => $row){
    // (id, created, updated, name, constellationId, starId, security, ...)
    $values = rowValues($row);
    $name = sqlUnquote($values[3]);
    $systemIds[$name] = $id;
    $systemNames[$id] = $name;
    $systemClasses[$id] = sqlUnquote($values[6]);
}

$typeIdsByCode = [];    // wormhole code => [type ids]
$codesByTypeId = [];    // type id => wormhole code
foreach(readRows($lines, 'type') as $id => $row){
    // (id, created, updated, name, description, published, radius, volume, capacity, mass, groupId, ...)
    $values = rowValues($row);
    $name = (string)sqlUnquote($values[3]);
    if((int)$values[10] === WORMHOLE_GROUP_ID && str_starts_with($name, 'Wormhole ')){
        $code = substr($name, strlen('Wormhole '));
        $typeIdsByCode[$code][] = $id;
        $codesByTypeId[$id] = $code;
    }
}

// ---- current CSV ----------------------------------------------------------------------------

$csvLines = array_values(array_filter(explode("\n", file_get_contents($csvFile)), 'strlen'));
$header = array_shift($csvLines);
if(trim($header) !== 'id;systemId;typeId'){
    fail("Unexpected header in $csvFile: $header");
}
$oldIds = [];           // "systemId:typeId" => id
$usedTypeIds = [];      // type ids the CSV already uses
foreach($csvLines as $line){
    [$id, $systemId, $typeId] = array_map('intval', explode(';', trim($line)));
    $oldIds["$systemId:$typeId"] = $id;
    $usedTypeIds[$typeId] = true;
}

// ---- build the new pairs --------------------------------------------------------------------

$warnings = [];
$pairs = [];            // "systemId:typeId" => [systemId, typeId]
foreach($statics as $systemName => $codes){
    if(!isset($systemIds[$systemName])){
        $warnings[] = "system '$systemName' is not in the seed (skipped)";
        continue;
    }
    $systemId = $systemIds[$systemName];
    foreach((array)$codes as $code){
        $candidates = $typeIdsByCode[$code] ?? [];
        if(!$candidates){
            $warnings[] = "wormhole '$code' ($systemName) is not in the seed (skipped)";
            continue;
        }
        sort($candidates);
        $typeId = null;
        foreach($candidates as $candidate){
            if(isset($oldIds["$systemId:$candidate"])){
                $typeId = $candidate;
                break;
            }
        }
        $typeId ??= current(array_filter($candidates, fn($c) => isset($usedTypeIds[$c]))) ?: $candidates[0];
        $pairs["$systemId:$typeId"] = [$systemId, $typeId];
    }
}

// keep ids of unchanged pairs; new pairs get ids after the highest id ever used in the CSV
$rows = [];             // id => [systemId, typeId]
$nextId = max($oldIds ?: [0]) + 1;
$added = [];
uksort($pairs, fn($a, $b) => $pairs[$a] <=> $pairs[$b]);
foreach($pairs as $key => $pair){
    if(isset($oldIds[$key])){
        $rows[$oldIds[$key]] = $pair;
    }else{
        $rows[$nextId++] = $pair;
        $added[] = $pair;
    }
}
ksort($rows);
$removed = [];
foreach($oldIds as $key => $id){
    if(!isset($pairs[$key])){
        $removed[] = array_map('intval', explode(':', $key));
    }
}

// ---- report ---------------------------------------------------------------------------------

$label = fn(array $pair) => sprintf('%-20s %-4s %s', $systemNames[$pair[0]] ?? $pair[0], $systemClasses[$pair[0]] ?? '?',
    $codesByTypeId[$pair[1]] ?? $pair[1]);
echo "== Source: $source (" . count($statics) . " systems)\n";
echo "== Statics added: " . count($added) . "\n";
foreach($added as $pair){
    echo '   + ' . $label($pair) . "\n";
}
echo "== Statics removed: " . count($removed) . "\n";
foreach($removed as $pair){
    echo '   - ' . $label($pair) . "\n";
}
echo "== Warnings: " . count($warnings) . "\n";
foreach($warnings as $warning){
    echo "   $warning\n";
}
$systemCount = count(array_unique(array_column($rows, 0)));
echo "== Result: " . count($rows) . " statics in $systemCount systems\n";

// ---- write ----------------------------------------------------------------------------------

if($dryRun){
    echo "Dry run: nothing written.\n";
    exit(0);
}

$csv = ['id;systemId;typeId'];
$seedRows = [];
foreach($rows as $id => [$systemId, $typeId]){
    $csv[] = "$id;$systemId;$typeId";
    $seedRows[$id] = "($id,$systemId,$typeId)";
}
file_put_contents($csvFile, implode("\n", $csv));

replaceBlock($lines, 'system_static', $seedRows);
setAutoIncrement($lines, 'system_static', max(array_keys($rows)) + 1);
file_put_contents($sqlFile, implode("\n", $lines));

echo "Wrote $csvFile and $sqlFile. Now rebuild eve_universe.sql.zip (see the header of this script).\n";
