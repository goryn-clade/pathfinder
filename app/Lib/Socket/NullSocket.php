<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 24.03.2019
 * Time: 23:22
 */

namespace Exodus4D\Pathfinder\Lib\Socket;


use React\Socket;
use React\Promise;

class NullSocket extends AbstractSocket {

    /**
     * name of Socket
     */
    const SOCKET_NAME = 'webSocket';

    /**
     * @return Socket\ConnectorInterface
     */
    protected function getConnector(): Socket\ConnectorInterface {
        return new Socket\Connector($this->options, $this->getLoop());
    }

    /**
     * write to NullSocket can not be performed -> resolve with an 'error' payload
     * @param string $task
     * @param null $load
     * @return Promise\PromiseInterface
     */
    #[\Override]
    public function write(string $task, $load = null) : Promise\PromiseInterface {
        return Promise\resolve($this->newPayload('error'));
    }
}