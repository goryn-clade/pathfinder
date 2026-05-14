<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 30.07.2015
 * Time: 17:54
 */

namespace Exodus4D\Pathfinder\Cron;


use Exodus4D\Pathfinder\Enum\ConnectionType;
use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Model\Pathfinder;

class MapUpdate extends AbstractCron {

    /**
     * log text
     */
    const LOG_TEXT_MAPS_DELETED = ', %3s maps deleted';

    /**
     * disabled maps will be fully deleted after (x) days
     */
    const DAYS_UNTIL_MAP_DELETION = 30;

    /**
     * deactivate all "private" maps whose lifetime is over
     * >> php index.php "/cron/deactivateMapData"
     * @param \Base $f3
     */
    function deactivateMapData(\Base $f3): void{
        $this->logStart(__FUNCTION__, false);
        $privateMapLifetime = (int)Config::getMapsDefaultConfig('private.lifetime');

        if($privateMapLifetime > 0){
            if($pfDB = $f3->DB->getDB('PF')){
                $sqlDeactivateExpiredMaps = "UPDATE map SET
                        active = 0
                    WHERE
                        map.active = 1 AND
                        map.typeId = 2 AND
                        TIMESTAMPDIFF(DAY, map.updated, NOW() ) > :lifetime";

                $pfDB->exec($sqlDeactivateExpiredMaps, ['lifetime' => $privateMapLifetime]);
            }
        }

        $this->logEnd(__FUNCTION__);
    }

    /**
     * delete all deactivated maps
     * >> php index.php "/cron/deleteMapData"
     * @param \Base $f3
     * @throws \Exception
     */
    function deleteMapData(\Base $f3): void{
        $this->logStart(__FUNCTION__);
        $total = 0;

        if($pfDB = $f3->DB->getDB('PF')){
            $sqlDeleteDisabledMaps = "SELECT
                id 
            FROM
                map
            WHERE
                map.active = 0 AND
                TIMESTAMPDIFF(DAY, map.updated, NOW() ) > :deletion_time";

            $disabledMaps = $pfDB->exec($sqlDeleteDisabledMaps, ['deletion_time' => self::DAYS_UNTIL_MAP_DELETION]);

            if($total = $pfDB->count()){
                $mapModel =  Pathfinder\AbstractPathfinderModel::getNew('MapModel');
                foreach($disabledMaps as $data){
                    $mapModel->getById( (int)$data['id'], 3, false );
                    if($mapModel->valid()){
                         $mapModel->erase();
                    }
                    $mapModel->reset();
                }
            }
        }

        $count = $importCount = $total;

        // Log --------------------------------------------------------------------------------------------------------
        $text = sprintf(self::LOG_TEXT_MAPS_DELETED, $total);
        $this->logEnd(__FUNCTION__, $total, $count, $importCount, 0, $text);
    }

    /**
     * delete expired EOL connections
     * >> php index.php "/cron/deleteEolConnections"
     * @param \Base $f3
     * @throws \Exception
     */
    function deleteEolConnections(\Base $f3): void{
        $this->logStart(__FUNCTION__, false);
        $nominalDefault = (int)($f3->get('PATHFINDER.CACHE.EXPIRE_CONNECTIONS_NOMINAL_DEFAULT') ?: 86400);
        $pfDB = $f3->DB->getDB('PF');

        $total = 0;
        $count = 0;
        if(!$pfDB){
            $this->logEnd(__FUNCTION__, $total, $count, $total);
            return;
        }

        $sql = "SELECT
            `con`.`id`,
            `con`.`type`,
            TIMESTAMPDIFF(SECOND, `con`.`eolUpdated`, NOW()) AS eolAge,
            COALESCE(`con`.`nominalLifespan`, :nominalDefault) AS nominalLifespan
        FROM
          `connection` `con` INNER JOIN
          `map` ON
            `map`.`id` = `con`.`mapId`
        WHERE
          `map`.`deleteEolConnections` = :deleteEolConnections AND
          `con`.`eolUpdated` IS NOT NULL
        ";

        $connectionsData = $pfDB->exec($sql, [
            'deleteEolConnections' => 1,
            'nominalDefault' => $nominalDefault
        ]);

        if($connectionsData){
            $total = count($connectionsData);
            /** @var Pathfinder\ConnectionModel $connection */
            $connection = Pathfinder\AbstractPathfinderModel::getNew('ConnectionModel');
            $count = $this->eraseExpiredEolConnections($connection, $connectionsData);
        }

        $this->logEnd(__FUNCTION__, $total, $count, $total);
    }

    /**
     * @param Pathfinder\ConnectionModel $connection reusable model instance
     * @param array<string, mixed> $connectionsData rows from deleteEolConnections query
     * @return int number of connections erased
     */
    private function eraseExpiredEolConnections($connection, array $connectionsData) : int {
        $count = 0;
        foreach($connectionsData as $data){
            $expire = $this->getEolExpireSeconds($data);
            if($expire === null || (int)$data['eolAge'] <= $expire){
                continue;
            }
            $connection->getById((int)$data['id']);
            if($connection->valid()){
                $connection->erase();
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<string, mixed> $data row with keys: type (JSON), nominalLifespan (seconds)
     * @return int|null expiry window in seconds for the connection's EOL phase, null if no EOL type
     */
    private function getEolExpireSeconds(array $data) : ?int {
        $types = (array)json_decode($data['type'] ?? 'null');
        $buffer = (int)((int)$data['nominalLifespan'] * 0.2);
        foreach(ConnectionType::eolCases() as $eolCase){
            if(in_array($eolCase->value, $types)){
                return $eolCase->eolBaseSeconds() + $buffer;
            }
        }
        return null;
    }

    /**
     * delete expired WH connections after max lifetime for wormholes is reached
     * >> php index.php "/cron/deleteExpiredConnections"
     * @param \Base $f3
     * @throws \Exception
     */
    function deleteExpiredConnections(\Base $f3): void{
        $this->logStart(__FUNCTION__, false);
        $nominalDefault = (int)($f3->get('PATHFINDER.CACHE.EXPIRE_CONNECTIONS_NOMINAL_DEFAULT') ?: 86400);
        $pfDB = $f3->DB->getDB('PF');

        $total = 0;
        $count = 0;
        if(!$pfDB){
            $this->logEnd(__FUNCTION__, $total, $count, $total);
            return;
        }

        // Expire healthy WH connections at 120% of their nominal lifespan
        $sql = "SELECT
            `con`.`id`,
            TIMESTAMPDIFF(SECOND, `con`.`created`, NOW()) AS age,
            COALESCE(`con`.`nominalLifespan`, :nominalDefault) AS nominalLifespan
        FROM
          `connection` `con` INNER JOIN
          `map` ON
            `map`.`id` = `con`.`mapId`
        WHERE
          `map`.`deleteExpiredConnections` = :deleteExpiredConnections AND
          `con`.`scope` = :scope
        ";

        $connectionsData = $pfDB->exec($sql, [
            'deleteExpiredConnections' => 1,
            'scope' => ConnectionType::Wh->value,
            'nominalDefault' => $nominalDefault
        ]);

        if($connectionsData){
            $total = count($connectionsData);
            /** @var Pathfinder\ConnectionModel $connection */
            $connection = Pathfinder\AbstractPathfinderModel::getNew('ConnectionModel');
            foreach($connectionsData as $data){
                if((int)$data['age'] > (int)$data['nominalLifespan'] * 1.2){
                    $connection->getById((int)$data['id']);
                    if($connection->valid()){
                        $connection->erase();
                        $count++;
                    }
                }
            }
        }

        $this->logEnd(__FUNCTION__, $total, $count, $total);
    }

    /**
     * delete all expired signatures on "inactive" systems
     * >> php index.php "/cron/deleteSignatures"
     * @param \Base $f3
     */
    function deleteSignatures(\Base $f3): void{
        $this->logStart(__FUNCTION__, false);
        $signatureExpire = (int)$f3->get('PATHFINDER.CACHE.EXPIRE_SIGNATURES');

        $count = 0;
        if($signatureExpire > 0){
            if($pfDB = $f3->DB->getDB('PF')){
                $sqlDeleteExpiredSignatures = "DELETE `sigs` FROM
                    `system_signature` `sigs` INNER JOIN
                    `system` ON 
                      `system`.`id` = `sigs`.`systemId`
                  WHERE
                    `system`.`active` = 0 AND
                    TIMESTAMPDIFF(SECOND, `sigs`.`updated`, NOW() ) > :lifetime
                ";

                $count = $pfDB->exec($sqlDeleteExpiredSignatures, ['lifetime' => $signatureExpire]);
            }
        }

        $importCount = $total = $count;

        $this->logEnd(__FUNCTION__, $total, $count, $importCount);
    }

}