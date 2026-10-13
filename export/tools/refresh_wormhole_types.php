<?php
/**
 * Refresh wormhole types in export/sql/eve_universe.sql from CCP's ESI.
 *
 * ESI is the source of truth. For every type in the wormhole group (988) this script:
 *  - adds or updates its row in the `type` table
 *  - regenerates all of its rows in the `type_attribute` table
 *  - adds any dogma attribute that ESI uses but `dogma_attribute` does not have yet
 *
 * Signature strength (attribute 1908) is not in ESI's wormhole data. It comes from
 * export/csv/wormhole.csv, the same way TypeModel adds it on import. If ESI ever
 * provides 1908 for a type, the ESI value wins.
 *
 * Types that are in the seed but no longer in the ESI group are reported, not deleted:
 * system statics may still reference them.
 *
 * Run from the pathfinder repo root (no local PHP needed):
 *   docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app php:8.3-cli \
 *     php export/tools/refresh_wormhole_types.php [--dry-run]
 *
 * Then rebuild the zip that the compose stacks mount:
 *   (cd export/sql && rm -f eve_universe.sql.zip && zip -X eve_universe.sql.zip eve_universe.sql)
 */

declare(strict_types=1);

const ESI_BASE = 'https://esi.evetech.net/latest';
const WORMHOLE_GROUP_ID = 988;
const SCAN_STRENGTH_ATTRIBUTE_ID = 1908;
const USER_AGENT = 'Pathfinder export/tools/refresh_wormhole_types.php (github.com/goryn-clade/pathfinder)';

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

function fail(string $message) : never {
    fwrite(STDERR, "ERROR: $message\n");
    exit(1);
}

function esiGet(string $path) : array {
    $context = stream_context_create(['http' => [
        'timeout' => 20,
        'ignore_errors' => true,
        'header' => "Accept: application/json\r\nUser-Agent: " . USER_AGENT . "\r\n"
    ]]);
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

// ---- SQL helpers ----------------------------------------------------------------------------

function sqlString(?string $value) : string {
    if($value === null){
        return 'NULL';
    }
    // same escaping as mysqldump
    $value = strtr($value, ["\\" => "\\\\", "'" => "\\'", "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]);
    return "'" . $value . "'";
}

function sqlNumber(int|float|string|null $value) : string {
    if($value === null){
        return 'NULL';
    }
    if(is_string($value)){
        return $value;
    }
    if(is_float($value) && floor($value) == $value && abs($value) < 1e18){
        return sprintf('%.0f', $value);
    }
    return (string)$value;
}

function sqlBool(?bool $value) : string {
    return $value === null ? 'NULL' : ($value ? '1' : '0');
}

/**
 * Find the data block of a table, as written by mysqldump:
 *   ALTER TABLE `x` DISABLE KEYS; INSERT INTO `x` VALUES <rows...>;
 * @return array{0:int, 1:int} index of the first and last row line
 */
function findInsertBlock(array $lines, string $table) : array {
    foreach($lines as $i => $line){
        if(
            $line === "INSERT INTO `$table` VALUES" &&
            ($lines[$i - 1] ?? '') === "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;"
        ){
            for($j = $i + 1; $j < count($lines); $j++){
                if(!str_starts_with($lines[$j], '(')){
                    fail("Unexpected line in `$table` data block at line " . ($j + 1));
                }
                if(str_ends_with($lines[$j], ');')){
                    return [$i + 1, $j];
                }
            }
        }
    }
    fail("Data block for table `$table` not found");
}

/**
 * @return array<int, string> row id => row text without the trailing , or ;
 */
function readRows(array $lines, array $block) : array {
    $rows = [];
    for($i = $block[0]; $i <= $block[1]; $i++){
        if(!preg_match('/^\((-?\d+),/', $lines[$i], $m)){
            fail('Cannot read row id at line ' . ($i + 1));
        }
        $rows[(int)$m[1]] = substr($lines[$i], 0, -1);
    }
    return $rows;
}

/**
 * @param array<int, string> $rows
 * @return string[]
 */
function writeRows(array $rows) : array {
    ksort($rows);
    $out = array_values($rows);
    $last = count($out) - 1;
    foreach($out as $i => &$row){
        $row .= $i === $last ? ';' : ',';
    }
    return $out;
}

function replaceBlock(array &$lines, array $block, array $newRows) : void {
    array_splice($lines, $block[0], $block[1] - $block[0] + 1, $newRows);
}

function setAutoIncrement(array &$lines, string $table, int $min) : void {
    $inTable = false;
    foreach($lines as $i => $line){
        if($line === "CREATE TABLE `$table` ("){
            $inTable = true;
        }elseif($inTable && preg_match('/^\) ENGINE=.* AUTO_INCREMENT=(\d+)/', $line, $m)){
            if((int)$m[1] !== $min){
                $lines[$i] = preg_replace('/AUTO_INCREMENT=\d+/', "AUTO_INCREMENT=$min", $line);
            }
            return;
        }
    }
    fail("CREATE TABLE `$table` not found");
}

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

fwrite(STDERR, "Fetching ESI group " . WORMHOLE_GROUP_ID . "...\n");
$group = esiGet('/universe/groups/' . WORMHOLE_GROUP_ID . '/');
$typeIds = $group['types'];
sort($typeIds);

$esiTypes = [];
foreach($typeIds as $n => $typeId){
    fwrite(STDERR, sprintf("\rFetching types %d/%d", $n + 1, count($typeIds)));
    $esiTypes[$typeId] = esiGet("/universe/types/$typeId/");
}
fwrite(STDERR, "\n");

// ---- type -----------------------------------------------------------------------------------

$typeBlock = findInsertBlock($lines, 'type');
$typeRows = readRows($lines, $typeBlock);

// group of every seed type, to find wormhole types that left the ESI group
$seedWormholeIds = [];
foreach($typeRows as $id => $row){
    if(preg_match('/,(\d+|NULL),\d+,[^,]+,\d+,\d+\)$/', $row, $m) && $m[1] === (string)WORMHOLE_GROUP_ID){
        $seedWormholeIds[] = $id;
    }
}

$report = ['typesAdded' => [], 'typesChanged' => [], 'attributeChanges' => [], 'dogmaAdded' => [], 'warnings' => []];

$typeRow = fn(int $id, array $t, string $created, string $updated) => '(' . implode(',', [
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

foreach($esiTypes as $id => $t){
    if(isset($typeRows[$id])){
        preg_match("/^\(\d+,'([^']*)','([^']*)'/", $typeRows[$id], $m);
        $unchanged = $typeRow($id, $t, $m[1], $m[2]);
        if($unchanged !== $typeRows[$id]){
            $typeRows[$id] = $typeRow($id, $t, $m[1], $now);
            $report['typesChanged'][] = "$id {$t['name']}";
        }
    }else{
        $typeRows[$id] = $typeRow($id, $t, $now, $now);
        $report['typesAdded'][] = "$id {$t['name']}";
    }
}

foreach(array_diff($seedWormholeIds, $typeIds) as $id){
    $report['warnings'][] = "type $id is in the seed's wormhole group but not in ESI's (left in place)";
}

replaceBlock($lines, $typeBlock, writeRows($typeRows));
$maxTypeId = max(array_keys($typeRows));

// ---- dogma_attribute ------------------------------------------------------------------------

$dogmaBlock = findInsertBlock($lines, 'dogma_attribute');
$dogmaRows = readRows($lines, $dogmaBlock);

$usedAttributeIds = [SCAN_STRENGTH_ATTRIBUTE_ID];
foreach($esiTypes as $t){
    foreach($t['dogma_attributes'] ?? [] as $a){
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
replaceBlock($lines, $dogmaBlock, writeRows($dogmaRows));
setAutoIncrement($lines, 'dogma_attribute', max(array_keys($dogmaRows)) + 1);

// ---- type_attribute -------------------------------------------------------------------------

$attrBlock = findInsertBlock($lines, 'type_attribute');
$oldAttributes = [];   // typeId => [attributeId => value]
foreach(readRows($lines, $attrBlock) as $row){
    if(!preg_match('/^\(\d+,(\d+),(\d+),([^)]+)\)$/', $row, $m)){
        fail("Cannot read type_attribute row: $row");
    }
    $oldAttributes[(int)$m[1]][(int)$m[2]] = $m[3];
}

$newAttributes = [];   // typeId => [attributeId => value]
foreach($esiTypes as $typeId => $t){
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
    if(!isset($esiTypes[$typeId])){
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
replaceBlock($lines, $attrBlock, writeRows($attrRows));
setAutoIncrement($lines, 'type_attribute', $rowId + 1);

// raise `type` AUTO_INCREMENT if a new type id needs it, never lower it
$typeCreate = array_search('CREATE TABLE `type` (', $lines, true);
for($i = $typeCreate; $i < count($lines); $i++){
    if(preg_match('/^\) ENGINE=.* AUTO_INCREMENT=(\d+)/', $lines[$i], $m)){
        if($maxTypeId + 1 > (int)$m[1]){
            setAutoIncrement($lines, 'type', $maxTypeId + 1);
        }
        break;
    }
}

// ---- report and write -----------------------------------------------------------------------

$sections = [
    'typesAdded' => 'Types added',
    'typesChanged' => 'Type rows changed',
    'dogmaAdded' => 'Dogma attributes added',
    'attributeChanges' => 'Attribute changes (attributeId:old->new)',
    'warnings' => 'Warnings'
];
foreach($sections as $key => $title){
    echo "== $title: " . count($report[$key]) . "\n";
    foreach($report[$key] as $entry){
        echo "   $entry\n";
    }
}
echo "== ESI wormhole types: " . count($esiTypes) . ", type_attribute rows: $rowId\n";

if($dryRun){
    echo "Dry run: $sqlFile not changed.\n";
}else{
    file_put_contents($sqlFile, implode("\n", $lines));
    echo "Wrote $sqlFile. Now rebuild eve_universe.sql.zip (see the header of this script).\n";
}
