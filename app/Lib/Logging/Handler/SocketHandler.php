<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 23.02.2019
 * Time: 19:11
 */

namespace Exodus4D\Pathfinder\Lib\Logging\Handler;


use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Lib\Logging\Formatter\SocketPayloadFormatter;
use Monolog\Level;
use Monolog\LogRecord;

class SocketHandler extends \Monolog\Handler\SocketHandler {

    /**
     * SocketHandler constructor.
     * @param $connectionString
     * @param int|string|Level $level
     * @param bool $bubble
     * @param array<string, mixed> $metaData
     */
    public function __construct($connectionString, $level = Level::Debug, $bubble = true, /**
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
     * -> the envelope is not a LogRecord, so SocketPayloadFormatter builds the JSON
     *    (the formatter set by Monolog::log() is not used for the socket payload)
     * @param LogRecord $record
     * @return bool
     */
    #[\Override]
    public function handle(LogRecord $record) : bool {
        if (!$this->isHandling($record)) {
            return false;
        }

        $record = $this->processRecord($record);

        $record->formatted = (new SocketPayloadFormatter())->formatPayload([
            'task' => 'logData',
            'load' => [
                'meta' => $this->metaData,
                'log' => $record->toArray()
            ]
        ]);

        try {
            $this->write($record);
        } catch (\RuntimeException) {
            // Mark socket as unavailable so subsequent writes in this request
            // (and for the remainder of the cache TTL) skip the socket handler.
            \Base::instance()->set(Config::CACHE_KEY_SOCKET_VALID, false, Config::CACHE_TTL_SOCKET_VALID);
            return false;
        }

        return false === $this->bubble;
    }
}