<?php
/**
 * Created by PhpStorm.
 * User: Exodus
 * Date: 25.06.2016
 * Time: 16:58
 */

namespace Exodus4D\Pathfinder\Data\Filesystem;


class Search {

    /**
     * max file count that can be returned
     */
    const DEFAULT_FILE_LIMIT                = 1000;

    /**
     * recursive file filter by mTime
     * @param string $dir
     * @param null $mTime
     * @param int $limit
     * @return \Traversable<mixed, \SplFileInfo>
     */
    static function getFilesByMTime(string $dir, ?int $mTime = null, int $limit = self::DEFAULT_FILE_LIMIT)  : \Traversable {
        $mTime = is_null($mTime) ? time() : (int)$mTime;

        $filterCallback = function(\SplFileInfo $current) use ($mTime) {
            /**
             * @var \SplFileInfo $current
             */
            if (
                !$current->isFile() || // allow recursion
                (
                    !str_starts_with($current->getFilename(), '.') && // skip e.g. ".gitignore"
                    $current->getMTime() < $mTime // filter last modification date
                )
            ){
                return true;
            }
            return false;
        };

        return self::getFilesByCallback($dir, $filterCallback, $limit);
    }

    /**
     * recursive file filter by size
     * @param string $dir
     * @param int $size
     * @param int $limit
     * @return \Traversable<mixed, \SplFileInfo>
     */
    static function getFilesBySize(string $dir, int $size = 0, int $limit = self::DEFAULT_FILE_LIMIT)  : \Traversable {

        $filterCallback = function(\SplFileInfo $current) use ($size) {
            /**
             * @var \SplFileInfo $current
             */
            if (
                !$current->isFile() || // allow recursion
                (
                    !str_starts_with($current->getFilename(), '.') && // skip e.g. ".gitignore"
                    $current->getSize() > $size // filter file size
                )
            ){
                return true;
            }
            return false;
        };

        return self::getFilesByCallback($dir, $filterCallback, $limit);
    }

    /**
     * @param string $dir
     * @param \Closure $filterCallback
     * @param int $limit
     * @return \Traversable<mixed, \SplFileInfo>
     */
    private static function getFilesByCallback(string $dir, \Closure $filterCallback, int $limit = self::DEFAULT_FILE_LIMIT) : \Traversable {
        $files = new \ArrayIterator();
        if(is_dir($dir)){
            $directory = new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS );
            $files = new \RecursiveCallbackFilterIterator($directory, $filterCallback);
        }
        return new \LimitIterator($files, 0, $limit);
    }
}