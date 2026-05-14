<?php
/**
 * Created by PhpStorm.
 * User: Exodus
 * Date: 26.11.2016
 * Time: 17:32
 */

namespace Exodus4D\Pathfinder\Lib;


class Util {

    /**
     * convert array keys to upper/lowercase -> recursive
     * @param array<string, mixed> $arr
     * @param int $case
     * @return array<string, mixed>
     */
    static function arrayChangeKeyCaseRecursive(array $arr, int $case = CASE_LOWER){
        if(is_array($arr)){
            $arr = array_map( function($item){
                if( is_array($item) )
                    $item = self::arrayChangeKeyCaseRecursive($item);
                return $item;
            }, array_change_key_case((array)$arr, $case));
        }

        return $arr;
    }

    /**
     * flatten multidimensional array ignore keys
     * @param  $array
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    static function arrayFlattenByValue(array $array) : array {
        $return = [];
        array_walk_recursive($array, function($value) use (&$return): void { $return[] = $value; });
        return $return;
    }

    /**
     * flatten multidimensional array merge keys
     * -> overwrites duplicate keys!
     * @param  $array
     * @param array<string, mixed> $array
     * @return array<string, mixed>
     */
    static function arrayFlattenByKey(array $array) : array {
        $return = [];
        array_walk_recursive($array, function($value, $key) use (&$return): void { $return[$key] = $value; });
        return $return;
    }

    /**
     * transforms array with assoc. arrays as values
     * into assoc. array where $key column data is used for its key
     * @param  $array
     * @param string $key
     * @param bool $unsetKey
     * @param array<string, mixed> $array
     * @return mixed[][]
     */
    static function arrayGetBy(array $array, string $key, bool $unsetKey = true) : array {
        // we can remove $key from nested arrays
        return array_map(function($val) use ($key, $unsetKey) : array {
            if($unsetKey){
                unset($val[$key]);
            }
            return $val;
        }, array_column($array, null, $key));
    }

    /**
     * checks whether an array is associative or not (sequential)
     * @param mixed $array
     * @return bool
     */
    static function is_assoc($array) : bool {
        $isAssoc = false;
        if(
            is_array($array) &&
            array_keys($array) !== range(0, count($array) - 1)
        ){
            $isAssoc = true;
        }

        return $isAssoc;
    }

    /**
     * convert array keys by a custom callback
     * @param array<string, mixed> $arr
     * @param callable $callback
     * @return array<string, mixed>
     */
    static function arrayChangeKeys(array $arr, callable $callback){
        return array_combine(
            array_map(fn($key) => $callback($key), array_keys($arr)), $arr
        );
    }

    /**
     * convert a string with multiple scopes into an array
     * @param string $scopes
     * @return array<string, mixed>|null
     */
    static function convertScopesString($scopes){
        $scopes = array_filter(
            array_map(strtolower(...),
                (array)explode(' ', $scopes)
            )
        );

        if($scopes){
            sort($scopes);
        }else{
            $scopes = null;
        }

        return $scopes;
    }

    /**
     * obsucre string e.g. password (hide last characters)
     * @param string $string
     * @param int $maxHideChars
     * @return string
     */
    static function obscureString(string $string, int $maxHideChars = 10) : string {
        $formatted = '';
        $length = mb_strlen((string)$string);
        if($length > 0){
            $hideChars = ($length < $maxHideChars) ? $length : $maxHideChars;
            $formatted = substr_replace($string, str_repeat('_', min(3, $length)), -$hideChars) .
                ' [' . $length . ']';
        }
        return $formatted;
    }

    /**
     * get hash from an array of ESI scopes
     * @param  $scopes
     * @param array<string, mixed> $scopes
     * @return string
     */
    static function getHashFromScopes(array $scopes) : string {
        $scopes = (array)$scopes;
        sort($scopes);
        return md5(serialize($scopes));
    }

    /**
     * get some information about a $source file/dir
     * @param string|null $source
     * @return array<string, lowercase-string|bool>
     */
    static function filesystemInfo(?string $source) : array {
        $info = [];
        if(is_dir($source)){
            $info['isDir'] = true;
        }elseif(is_file($source)){
            $info['isFile'] = true;
        }
        if(!empty($info)){
            $info['chmod'] = substr(sprintf('%o', fileperms($source)), -4);
        }
        return $info;
    }

    /**
     * round DateTime to interval
     * @param \DateTime $dateTime
     * @param string $type
     * @param int $interval
     * @param string $round
     */
    static function roundToInterval(\DateTime &$dateTime, string $type = 'sec', int $interval = 5, string $round = 'floor'): void{
        $hours = $minutes = $seconds = 0;

        $roundInterval = (fn(string $format, int $interval, string $round): int => call_user_func($round, $format / $interval) * $interval);

        switch($type){
            case 'hour':
                $hours = $roundInterval($dateTime->format('H'), $interval, $round);
                break;
            case 'min':
                $hours = $dateTime->format('H');
                $minutes = $roundInterval($dateTime->format('i'), $interval, $round);
                break;
            case 'sec':
                $hours = $dateTime->format('H');
                $minutes = $dateTime->format('i');
                $seconds = $roundInterval($dateTime->format('s'), $interval, $round);
                break;
        }

        $dateTime->setTime($hours, $minutes, $seconds);
    }
}