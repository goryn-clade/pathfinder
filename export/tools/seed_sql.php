<?php
/**
 * Helpers for the export/tools scripts: read and rewrite data blocks in a mysqldump file
 * (export/sql/eve_universe.sql), one row per line.
 */

declare(strict_types=1);

function fail(string $message) : never {
    fwrite(STDERR, "ERROR: $message\n");
    exit(1);
}

function sqlString(?string $value) : string {
    if($value === null){
        return 'NULL';
    }
    // same escaping as mysqldump
    $value = strtr($value, ["\\" => "\\\\", "'" => "\\'", '"' => '\\"', "\0" => "\\0", "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]);
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
 * Find the data section of a table, as written by mysqldump:
 *   ALTER TABLE `x` DISABLE KEYS;
 *   INSERT INTO `x` VALUES <rows...>;   (large tables have several INSERT statements)
 *   ALTER TABLE `x` ENABLE KEYS;
 * @return array{0:int, 1:int} index of the first and last line between DISABLE KEYS and ENABLE KEYS
 */
function findDataSection(array $lines, string $table) : array {
    $start = array_search("/*!40000 ALTER TABLE `$table` DISABLE KEYS */;", $lines, true);
    if($start === false){
        fail("Data section for table `$table` not found");
    }
    for($i = $start + 1; $i < count($lines); $i++){
        if($lines[$i] === "/*!40000 ALTER TABLE `$table` ENABLE KEYS */;"){
            return [$start + 1, $i - 1];
        }
        if($lines[$i] !== "INSERT INTO `$table` VALUES" && !str_starts_with($lines[$i], '(')){
            fail("Unexpected line in `$table` data section at line " . ($i + 1));
        }
    }
    fail("End of data section for table `$table` not found");
}

/**
 * @return array<int, string> row id => row text without the trailing , or ;
 */
function readRows(array $lines, string $table) : array {
    [$first, $last] = findDataSection($lines, $table);
    $rows = [];
    for($i = $first; $i <= $last; $i++){
        if($lines[$i] === "INSERT INTO `$table` VALUES"){
            continue;
        }
        if(!preg_match('/^\((-?\d+),/', $lines[$i], $m)){
            fail('Cannot read row id at line ' . ($i + 1));
        }
        $rows[(int)$m[1]] = substr($lines[$i], 0, -1);
    }
    return $rows;
}

/**
 * replace the data section of a table with $rows, sorted by id;
 * a new INSERT statement starts after ~1 MB, like mysqldump
 * @param array<int, string> $rows row id => row text without the trailing , or ;
 */
function replaceBlock(array &$lines, string $table, array $rows) : void {
    [$first, $last] = findDataSection($lines, $table);
    ksort($rows);
    $out = [];
    $bytes = 0;
    foreach(array_values($rows) as $row){
        if(!$out || $bytes > 1000000){
            if($out){
                $out[count($out) - 1] = substr($out[count($out) - 1], 0, -1) . ';';
            }
            $out[] = "INSERT INTO `$table` VALUES";
            $bytes = 0;
        }
        $out[] = $row . ',';
        $bytes += strlen($row) + 2;
    }
    $out[count($out) - 1] = substr($out[count($out) - 1], 0, -1) . ';';
    array_splice($lines, $first, $last - $first + 1, $out);
}

/**
 * set AUTO_INCREMENT of a table; with $onlyRaise it is never lowered
 */
function setAutoIncrement(array &$lines, string $table, int $value, bool $onlyRaise = false) : void {
    $inTable = false;
    foreach($lines as $i => $line){
        if($line === "CREATE TABLE `$table` ("){
            $inTable = true;
        }elseif($inTable && preg_match('/^\) ENGINE=.* AUTO_INCREMENT=(\d+)/', $line, $m)){
            if($onlyRaise ? $value > (int)$m[1] : $value !== (int)$m[1]){
                $lines[$i] = preg_replace('/AUTO_INCREMENT=\d+/', "AUTO_INCREMENT=$value", $line);
            }
            return;
        }
    }
    fail("CREATE TABLE `$table` not found");
}

/**
 * split the values of one row line into SQL tokens (strings keep their quotes)
 * @return string[]
 */
function rowValues(string $row) : array {
    if(!preg_match_all("/'(?:[^'\\\\]|\\\\.)*'|[^,()]+/", trim($row, '(),;'), $m)){
        fail("Cannot read row: $row");
    }
    return $m[0];
}

function sqlUnquote(string $value) : ?string {
    if($value === 'NULL'){
        return null;
    }
    return stripcslashes(substr($value, 1, -1));
}
