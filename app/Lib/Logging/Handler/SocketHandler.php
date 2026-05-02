<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 23.02.2019
 * Time: 19:11
 */

namespace Exodus4D\Pathfinder\Lib\Logging\Handler;


use Exodus4D\Pathfinder\Lib\Config;
use Monolog\Logger;

class SocketHandler extends \Monolog\Handler\SocketHandler {

    /**
     * SocketHandler constructor.
     * @param $connectionString
     * @param int $level
     * @param bool $bubble
     * @param array $metaData
     */
    public function __construct($connectionString, $level = Logger::DEBUG, $bubble = true, /**
     * some meta data (additional processing information)
     */
    protected $metaData = []){
        parent::__construct($connectionString, $level, $bubble);

        $this->setConnectionTimeout(2);
        $this->setTimeout(2);
    }

    /**
     * overwrite default handle()
     * -> change data structure after processor() calls and before formatter() calls
     * @param array $record
     * @return bool
     */
    public function handle(array $record) : bool {
        if (!$this->isHandling($record)) {
            return false;
        }

        $record = $this->processRecord($record);

        $record = [
            'task' => 'logData',
            'load' => [
                'meta' => $this->metaData,
                'log' => $record
            ]
        ];

        $record['formatted'] = $this->getFormatter()->format($record);

        try {
            $this->write($record);
        } catch (\RuntimeException $e) {
            // Mark socket as unavailable so subsequent writes in this request
            // (and for the remainder of the cache TTL) skip the socket handler.
            \Base::instance()->set(Config::CACHE_KEY_SOCKET_VALID, false, Config::CACHE_TTL_SOCKET_VALID);
            return false;
        }

        return false === $this->bubble;
    }
}