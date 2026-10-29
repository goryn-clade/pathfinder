<?php

namespace Exodus4D\Pathfinder\Lib\Logging\Formatter;

use Monolog\Formatter\JsonFormatter;

/**
 * JSON formatter for the WebSocket payload sent by SocketHandler
 * -> the payload is an envelope ({task, load: {meta, log}}), not a LogRecord
 */
class SocketPayloadFormatter extends JsonFormatter {

    /**
     * @param array<string, mixed> $payload
     * @return string
     */
    public function formatPayload(array $payload) : string {
        return $this->toJson($this->normalize($payload), true) . ($this->appendNewline ? "\n" : '');
    }
}
