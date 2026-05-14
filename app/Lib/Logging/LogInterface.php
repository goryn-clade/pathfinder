<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 05.08.2017
 * Time: 14:10
 */

namespace Exodus4D\Pathfinder\Lib\Logging;


interface LogInterface {

    public function setMessage(string $message);

    public function setLevel(string $level);

    public function setTag(string $tag);

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data) : LogInterface;

    /**
     * @param array<string, mixed> $data
     */
    public function setTempData(array $data) : LogInterface;

    public function addHandler(string $handlerKey, ?string $formatterKey = null, ?\stdClass $handlerParams = null) : LogInterface;

    public function addHandlerGroup(string $handlerKey) : LogInterface;

    /**
     * @return array<string, mixed>
     */
    public function getHandlerConfig() : array;

    /**
     * @return array<string, mixed>
     */
    public function getHandlerParamsConfig() : array;

    /**
     * @return array<string, mixed>
     */
    public function getProcessorConfig() : array;

    /**
     * @return array<string, mixed>
     */
    public function getProcessorParams(string $processorKey) : array;

    /**
     * @return array<string, mixed>
     */
    public function getHandlerParams(string $handlerKey) : array;

    public function getMessage() : string;

    public function getAction() : string;

    public function getChannelType() : string;

    public function getChannelName() : string;

    public function getLevel() : string;

    /**
     * @return array<string, mixed>
     */
    public function getData() : array;

    /**
     * @return array<string, mixed>
     */
    public function getContext() : array;

    /**
     * @return array<string, mixed>
     */
    public function getHandlerGroups() : array;

    public function getGroupHash() : string;

    public function hasHandlerKey(string $handlerKey) : bool;

    public function hasHandlerGroupKey(string $handlerKey) : bool;

    public function hasProcessorKey(string $processorKey) : bool;

    public function hasBuffer() : bool;

    public function isGrouped() : bool;

    public function removeHandlerGroups();

    public function removeHandlerGroup(string $handlerKey);

    public function buffer();
}