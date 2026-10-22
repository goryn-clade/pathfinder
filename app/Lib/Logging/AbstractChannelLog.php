<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 04.08.2017
 * Time: 22:13
 */

namespace Exodus4D\Pathfinder\Lib\Logging;


abstract class AbstractChannelLog extends AbstractLog {

    /**
     * @var array<string, mixed>
     */
    protected $channelData          = [];

    /**
     * AbstractChannelLog constructor.
     * @param string $action
     * @param array<string, mixed> $channelData
     */
    public function __construct(string $action, array $channelData){
        parent::__construct($action);
        $this->setChannelData($channelData);

        // add log processor -> remove $channelData from log
        $processorClearChannelData = function($record){
            $record['context'] = array_diff_key($record['context'], $this->getChannelData());
            return $record;
        };

        // init processorConfig. IMPORTANT: first processor gets executed at the end!
        $this->processorConfig = ['clearChannelData' => $processorClearChannelData] + $this->processorConfig;
    }

    /**
     * @param array<string, mixed> $channelData
     */
    protected function setChannelData(array $channelData): void {
        $this->channelData = $channelData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getChannelData() : array{
        return $this->channelData;
    }

    /**
     * @return int
     */
    public function getChannelId() : int{
        return (int)$this->getChannelData()['channelId'];
    }

    /**
     * @return string
     */
    #[\Override]
    public function getChannelName() : string{
        return (string)$this->getChannelData()['channelName'];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function getData() : array{
        $data['main'] = parent::getData();

        if(!empty($channelLogData = $this->getChannelData())){
            $channelData['channel'] = $channelLogData;
            $data = $channelData + $data;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function getContext(): array{
        $context = parent::getContext();

        // add temp data (e.g. used for $message placeholder replacement
        $context += $this->getChannelData();

        return $context;
    }
}