<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 13.10.2018
 * Time: 15:28
 */

namespace Exodus4D\Pathfinder\Controller\Api\Rest;


use Exodus4D\Pathfinder\Model\Pathfinder;

class Log extends AbstractRestController {

    /**
     * put (insert) log data
     * @param \Base $f3
     * @throws \Exception
     */
    public function put(\Base $f3){
        $requestData = $this->getRequestData($f3);
        $connectionData = [];

        if($connectionId = (int)($requestData['connectionId'] ?? 0)){
            $activeCharacter = $this->getCharacter();

            /**
             * @var Pathfinder\ConnectionModel $connection
             */
            $connection = Pathfinder\AbstractPathfinderModel::getNew('ConnectionModel');
            $connection->getById($connectionId);

            if($connection->hasAccess($activeCharacter)){
                $log = $connection->getNewLog();
                $log->setData($requestData);
                $log->record = false; // log not recorded by ESI
                $log->save();

                $connectionData[] = $log->getConnection()->getData(true, true);
            }
        }

        $this->out($connectionData);
    }

    /**
     * delete (deactivate) log data
     * @param \Base $f3
     * @param $params
     * @throws \Exception
     */
    public function delete(\Base $f3,  $params){
        $logId = (int)$params['id'];
        $connectionData = [];

        if($log = $this->update($logId, ['active' => false])){
            if($connection = $log->getConnection()){
                $connectionData[] =  $connection->getData(true, true);
            }
        }

        $this->out($connectionData);
    }

    /**
     * update log data
     * @param \Base $f3
     * @param $params
     * @throws \Exception
     */
    public function patch(\Base $f3,  $params){
        $logId = (int)$params['id'];
        $requestData = $this->getRequestData($f3);
        $connectionData = [];

        if($log = $this->update($logId, $requestData)){
            if($connection = $log->getConnection()){
                $connectionData[] =  $connection->getData(true, true);
            }
        }

        $this->out($connectionData);
    }

    // ----------------------------------------------------------------------------------------------------------------

    /**
     * update existing connectionLog with new data
     * @param int $logId
     * @param array $logData
     * @return bool|Pathfinder\ConnectionLogModel
     * @throws \Exception
     */
    private function update(int $logId,  $logData){
        $log = false;
        if($logId){
            $activeCharacter = $this->getCharacter();
            /**
             * @var Pathfinder\ConnectionLogModel $log
             */
            $log = Pathfinder\AbstractPathfinderModel::getNew('ConnectionLogModel');
            $log->getById($logId, 0, false);

            if($log->hasAccess($activeCharacter)){
                $log->setData($logData);

                if(isset($logData['active'])){
                    $log->setActive((bool)$logData['active']);
                }
                $log->save();
            }
        }
        return $log;
    }
}