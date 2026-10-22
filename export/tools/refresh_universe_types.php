<?php
/**
 * Refresh the types that /setup imports from CCP's ESI in export/sql/eve_universe.sql.
 *
 * ESI is the source of truth. The script covers the same data as the /setup import buttons:
 *  - Ships (category 6) and Structures (category 65): category, group and type rows
 *  - Wormholes (group 988): group and type rows, and all `type_attribute` rows
 * Rows are added or updated; any dogma attribute that wormholes use but `dogma_attribute`
 * does not have yet is added too.
 *
 * Signature strength (attribute 1908) is not in ESI's wormhole data. It comes from
 * export/csv/wormhole.csv, the same way TypeModel adds it on import. If ESI ever
 * provides 1908 for a type, the ESI value wins.
 *
 * Groups and types that are in the seed but no longer in ESI are reported, not deleted:
 * other tables (e.g. system statics) may still reference them.
 *
 * Run from the pathfinder repo root (no local PHP needed):
 *   docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.3-cli \
 *     php export/tools/refresh_universe_types.php [--dry-run]
 *
 * Then rebuild the zip that the compose stacks mount:
 *   (cd export/sql && rm -f eve_universe.sql.zip && zip -X eve_universe.sql.zip eve_universe.sql)
 */

declare(strict_types=1);

require __DIR__ . '/seed_sql.php';

const ESI_BASE = 'https://esi.evetech.net/latest';
const CATEGORY_IDS = [6, 65];           // Ships, Structures
const WORMHOLE_GROUP_ID = 988;
const SCAN_STRENGTH_ATTRIBUTE_ID = 1908;
const USER_AGENT = 'Pathfinder export/tools/refresh_universe_types.php (github.com/goryn-clade/pathfinder)';

$options = getopt('', ['dry-run', 'sql:', 'csv:']);
$dryRun = isset($options['dry-run']);
$sqlFile = $options['sql'] ?? 'export/sql/eve_universe.sql';
$csvFile = $options['csv'] ?? 'export/csv/wormhole.csv';
$now = gmdate('Y-m-d H:i:s');

foreach([$sqlFile, $csvFile] as $file){
    if(!is_file($file)){
        fail("File not found: $file (run from the pathfinder repo root)");
    }
}

// ---- ESI ------------------------------------------------------------------------------------

function esiGet(string $path) : array {
    $context = stream_context_create(['http' => [
        'timeout' => 20,
        'ignore_errors' => true,
        'header' => "Accept: application/json\r\nUser-Agent: " . USER_AGENT . "\r\n"
    ]]);
    $status = 0;
    for($attempt = 1; $attempt <= 3; $attempt++){
        $body = @file_get_contents(ESI_BASE . $path, false, $context);
        $status = (int)(explode(' ', $http_response_header[0] ?? '')[1] ?? 0);
        if($body !== false && $status === 200){
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        }
        sleep($attempt * 2);
    }
    fail("ESI request failed after 3 attempts: $path (last HTTP status $status)");
}

function progress(string $label, int $done, int $total) : void {
    fwrite(STDERR, sprintf("\r%s %d/%d", $label, $done, $total) . ($done === $total ? "\n" : ''));
}

/**
 * GET many ESI paths, $parallel at a time; failed requests are retried up to 3 times
 * @param array<int, string> $paths key => path
 * @return array<int, array> key => decoded response
 */
function esiGetMany(array $paths, string $label, int $parallel = 10) : array {
    $results = [];
    $attempts = [];
    $queue = array_keys($paths);
    $multi = curl_multi_init();
    $running = [];
    $start = function($key) use ($paths, $multi, &$running) : void {
        $handle = curl_init(ESI_BASE . $paths[$key]);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => USER_AGENT
        ]);
        curl_multi_add_handle($multi, $handle);
        $running[(int)$handle] = [$key, $handle];
    };
    while($queue || $running){
        while($queue && count($running) < $parallel){
            $start(array_shift($queue));
        }
        curl_multi_exec($multi, $active);
        curl_multi_select($multi, 1.0);
        while($info = curl_multi_info_read($multi)){
            [$key, $handle] = $running[(int)$info['handle']];
            unset($running[(int)$info['handle']]);
            $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $body = curl_multi_getcontent($handle);
            curl_multi_remove_handle($multi, $handle);
            if($info['result'] === CURLE_OK && $status === 200){
                $results[$key] = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                progress($label, count($results), count($paths));
            }elseif(($attempts[$key] = ($attempts[$key] ?? 0) + 1) < 3){
                sleep($attempts[$key] * 2);
                $queue[] = $key;
            }else{
                fail("ESI request failed after 3 attempts: {$paths[$key]} (last HTTP status $status)");
            }
        }
    }
    curl_multi_close($multi);
    return $results;
}

// ---- helpers ------------------------------------------------------------------------------

/**
 * Add or update rows of a table that start with (id, created, updated, ...).
 * An existing row keeps its timestamps when nothing else changed, otherwise `updated` is set to now.
 * @param array<int, string> $rows existing rows, changed in place
 * @param array<int, callable(string $created, string $updated): string> $newRows id => row builder
 */
function upsertRows(array &$rows, array $newRows, string $now, array &$added, array &$changed, callable $label) : void {
    foreach($newRows as $id => $build){
        if(isset($rows[$id])){
            if(!preg_match("/^\(-?\d+,'([^']*)','([^']*)'/", $rows[$id], $m)){
                fail("Cannot read timestamps of row: {$rows[$id]}");
            }
            if($build($m[1], $m[2]) !== $rows[$id]){
                $rows[$id] = $build($m[1], $now);
                $changed[] = $label($id);
            }
        }else{
            $rows[$id] = $build($now, $now);
            $added[] = $label($id);
        }
    }
}

// ---- fetch from ESI -------------------------------------------------------------------------

$esiCategories = [];
$groupIds = [WORMHOLE_GROUP_ID];
foreach(CATEGORY_IDS as $categoryId){
    $esiCategories[$categoryId] = esiGet("/universe/categories/$categoryId/");
    array_push($groupIds, ...$esiCategories[$categoryId]['groups']);
}
$groupIds = array_values(array_unique($groupIds));
sort($groupIds);

$esiGroups = esiGetMany(array_combine($groupIds, array_map(fn($id) => "/universe/groups/$id/", $groupIds)), 'Fetching groups');
ksort($esiGroups);
// categories of all refreshed groups (e.g. 'Celestial' for wormholes)
foreach($esiGroups as $g){
    $esiCategories[$g['category_id']] ??= esiGet("/universe/categories/{$g['category_id']}/");
}

$typeIds = [];
foreach($esiGroups as $g){
    array_push($typeIds, ...$g['types']);
}
$typeIds = array_values(array_unique($typeIds));
sort($typeIds);

$esiTypes = esiGetMany(array_combine($typeIds, array_map(fn($id) => "/universe/types/$id/", $typeIds)), 'Fetching types');
ksort($esiTypes);

$wormholeTypeIds = $esiGroups[WORMHOLE_GROUP_ID]['types'];
sort($wormholeTypeIds);

// ---- load inputs ----------------------------------------------------------------------------

$lines = explode("\n", file_get_contents($sqlFile));

$scanStrength = [];
$csv = array_map(fn($l) => str_getcsv($l, ';', '"', ''), array_filter(explode("\n", file_get_contents($csvFile))));
$header = array_shift($csv);
$nameCol = array_search('Name', $header);
$strengthCol = array_search('scanWormholeStrength', $header);
foreach($csv as $row){
    if(isset($row[$nameCol]) && (float)($row[$strengthCol] ?? 0)){
        $scanStrength[trim($row[$nameCol])] = trim($row[$strengthCol]);
    }
}

$report = [];
foreach(['categoriesAdded', 'categoriesChanged', 'groupsAdded', 'groupsChanged', 'typesAdded', 'typesChanged',
    'dogmaAdded', 'attributeChanges', 'warnings'] as $key){
    $report[$key] = [];
}

// ---- category -------------------------------------------------------------------------------

$categoryRows = readRows($lines, 'category');
$builders = [];
foreach($esiCategories as $id => $c){
    $builders[$id] = fn(string $created, string $updated) => '(' . implode(',', [
        $id, sqlString($created), sqlString($updated), sqlString($c['name']), sqlBool($c['published'] ?? true)
    ]) . ')';
}
upsertRows($categoryRows, $builders, $now, $report['categoriesAdded'], $report['categoriesChanged'],
    fn($id) => "$id {$esiCategories[$id]['name']}");
replaceBlock($lines, 'category', $categoryRows);
setAutoIncrement($lines, 'category', max(array_keys($categoryRows)) + 1, true);

// ---- group ----------------------------------------------------------------------------------

$groupRows = readRows($lines, 'group');
$builders = [];
foreach($esiGroups as $id => $g){
    $builders[$id] = fn(string $created, string $updated) => '(' . implode(',', [
        $id, sqlString($created), sqlString($updated), sqlString($g['name']), sqlBool($g['published'] ?? true),
        (int)$g['category_id']
    ]) . ')';
}
upsertRows($groupRows, $builders, $now, $report['groupsAdded'], $report['groupsChanged'],
    fn($id) => "$id {$esiGroups[$id]['name']}");
foreach($groupRows as $id => $row){
    if(
        !isset($esiGroups[$id]) &&
        preg_match('/,(\d+)\)$/', $row, $m) && in_array((int)$m[1], CATEGORY_IDS, true)
    ){
        $report['warnings'][] = "group $id is in the seed (category {$m[1]}) but not in ESI (left in place)";
    }
}
replaceBlock($lines, 'group', $groupRows);
setAutoIncrement($lines, 'group', max(array_keys($groupRows)) + 1, true);

// ---- type -----------------------------------------------------------------------------------

$typeRows = readRows($lines, 'type');
$builders = [];
foreach($esiTypes as $id => $t){
    $builders[$id] = fn(string $created, string $updated) => '(' . implode(',', [
        $id,
        sqlString($created),
        sqlString($updated),
        sqlString($t['name']),
        sqlString($t['description'] ?? null),
        sqlBool($t['published'] ?? true),
        sqlNumber((float)($t['radius'] ?? 0)),
        sqlNumber((float)($t['volume'] ?? 0)),
        sqlNumber((float)($t['capacity'] ?? 0)),
        sqlNumber((float)($t['mass'] ?? 0)),
        (int)$t['group_id'],
        (int)($t['market_group_id'] ?? 0),
        sqlNumber((float)($t['packaged_volume'] ?? 0)),
        (int)($t['portion_size'] ?? 0),
        (int)($t['graphic_id'] ?? 0)
    ]) . ')';
}
upsertRows($typeRows, $builders, $now, $report['typesAdded'], $report['typesChanged'],
    fn($id) => "$id {$esiTypes[$id]['name']}");
foreach($typeRows as $id => $row){
    if(
        !isset($esiTypes[$id]) &&
        preg_match('/,(\d+|NULL),\d+,[^,]+,\d+,\d+\)$/', $row, $m) && isset($esiGroups[(int)$m[1]])
    ){
        $report['warnings'][] = "type $id is in the seed (group {$m[1]}) but not in ESI (left in place)";
    }
}
replaceBlock($lines, 'type', $typeRows);
setAutoIncrement($lines, 'type', max(array_keys($typeRows)) + 1, true);

// ---- dogma_attribute ------------------------------------------------------------------------

$dogmaRows = readRows($lines, 'dogma_attribute');
$usedAttributeIds = [SCAN_STRENGTH_ATTRIBUTE_ID];
foreach($wormholeTypeIds as $typeId){
    foreach($esiTypes[$typeId]['dogma_attributes'] ?? [] as $a){
        $usedAttributeIds[] = (int)$a['attribute_id'];
    }
}
foreach(array_unique($usedAttributeIds) as $attributeId){
    if(isset($dogmaRows[$attributeId])){
        continue;
    }
    $a = esiGet("/dogma/attributes/$attributeId/");
    $dogmaRows[$attributeId] = '(' . implode(',', [
        $attributeId,
        sqlString($now),
        sqlString($now),
        sqlString($a['name'] ?? ''),
        sqlString($a['display_name'] ?? null),
        sqlString($a['description'] ?? null),
        sqlBool($a['published'] ?? null),
        sqlBool($a['stackable'] ?? null),
        sqlBool($a['high_is_good'] ?? null),
        sqlNumber((float)($a['default_value'] ?? 0)),
        sqlNumber($a['icon_id'] ?? null),
        sqlNumber($a['unit_id'] ?? null)
    ]) . ')';
    $report['dogmaAdded'][] = "$attributeId " . ($a['name'] ?? '');
}
replaceBlock($lines, 'dogma_attribute', $dogmaRows);
setAutoIncrement($lines, 'dogma_attribute', max(array_keys($dogmaRows)) + 1, true);

// ---- type_attribute (wormholes) -------------------------------------------------------------

$oldAttributes = [];   // typeId => [attributeId => value]
foreach(readRows($lines, 'type_attribute') as $row){
    if(!preg_match('/^\(\d+,(\d+),(\d+),([^)]+)\)$/', $row, $m)){
        fail("Cannot read type_attribute row: $row");
    }
    $oldAttributes[(int)$m[1]][(int)$m[2]] = $m[3];
}

$newAttributes = [];   // typeId => [attributeId => value]
foreach($wormholeTypeIds as $typeId){
    $t = $esiTypes[$typeId];
    $values = [];
    foreach($t['dogma_attributes'] ?? [] as $a){
        $values[(int)$a['attribute_id']] = sqlNumber((float)$a['value']);
    }
    $wormholeName = substr($t['name'], strrpos($t['name'], ' ') + 1);
    if(!isset($values[SCAN_STRENGTH_ATTRIBUTE_ID]) && isset($scanStrength[$wormholeName])){
        $values[SCAN_STRENGTH_ATTRIBUTE_ID] = $scanStrength[$wormholeName];
    }
    ksort($values);
    $newAttributes[$typeId] = $values;
}

// keep rows of types outside the wormhole group untouched
foreach($oldAttributes as $typeId => $values){
    if(!in_array($typeId, $wormholeTypeIds, true)){
        $newAttributes[$typeId] = $values;
    }
}
ksort($newAttributes);

foreach($newAttributes as $typeId => $values){
    $old = $oldAttributes[$typeId] ?? [];
    $changes = [];
    foreach(array_unique(array_merge(array_keys($old), array_keys($values))) as $attributeId){
        $before = $old[$attributeId] ?? null;
        $after = $values[$attributeId] ?? null;
        if($before === null || $after === null || (float)$before != (float)$after){
            $changes[] = "$attributeId:" . ($before ?? '-') . '->' . ($after ?? '-');
        }
    }
    if($changes){
        $name = $esiTypes[$typeId]['name'] ?? '?';
        $report['attributeChanges'][] = "$typeId $name  " . implode(' ', $changes);
    }
}

$attrRows = [];
$rowId = 0;
foreach($newAttributes as $typeId => $values){
    foreach($values as $attributeId => $value){
        $rowId++;
        $attrRows[$rowId] = "($rowId,$typeId,$attributeId,$value)";
    }
}
replaceBlock($lines, 'type_attribute', $attrRows);
setAutoIncrement($lines, 'type_attribute', $rowId + 1);

// ---- report and write -----------------------------------------------------------------------

$sections = [
    'categoriesAdded' => 'Categories added',
    'categoriesChanged' => 'Categories changed',
    'groupsAdded' => 'Groups added',
    'groupsChanged' => 'Groups changed',
    'typesAdded' => 'Types added',
    'typesChanged' => 'Types changed',
    'dogmaAdded' => 'Dogma attributes added',
    'attributeChanges' => 'Wormhole attribute changes (attributeId:old->new)',
    'warnings' => 'Warnings'
];
foreach($sections as $key => $title){
    echo "== $title: " . count($report[$key]) . "\n";
    foreach($report[$key] as $entry){
        echo "   $entry\n";
    }
}
echo "== ESI: " . count($esiGroups) . " groups, " . count($esiTypes) . " types (" . count($wormholeTypeIds) .
    " wormholes), type_attribute rows: $rowId\n";

if($dryRun){
    echo "Dry run: $sqlFile not changed.\n";
}else{
    file_put_contents($sqlFile, implode("\n", $lines));
    echo "Wrote $sqlFile. Now rebuild eve_universe.sql.zip (see the header of this script).\n";
}
