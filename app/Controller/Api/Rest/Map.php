<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 12.03.2020
 * Time: 19:30
 */

namespace Exodus4D\Pathfinder\Controller\Api\Rest;


use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Model\Pathfinder;

class Map extends AbstractRestController {

    /**
     * error message missing character right for map delete
     */
    const ERROR_MAP_DELETE = 'Character %s does not have sufficient rights for map delete';

    /**
     * error message missing character right (e.g. map_update)
     */
    const ERROR_MAP_RIGHT = 'Character %s does not have sufficient rights for %s';

    /**
     * request keys for map sharing (map_share right)
     */
    const SHARE_KEYS = ['mapCharacters', 'mapCorporations', 'mapAlliances'];

    /**
     * get map settings that are not part of the map data (webhook URLs)
     * -> map settings dialog, map_update right only
     * @param \Base $f3
     * @param       $params
     * @throws \Exception
     */
    public function get(\Base $f3,  array $params) : void {
        $webhookData = [];

        if($mapId = (int)($params['id'] ?? 0)){
            $activeCharacter = $this->getCharacter();

            /**
             * @var Pathfinder\MapModel $map
             */
            $map = Pathfinder\AbstractPathfinderModel::getNew('MapModel');
            $map->getById($mapId);
            if($map->hasAccess($activeCharacter)){
                if(!$activeCharacter->hasMapRight((int)$map->get('typeId', true), 'map_update')){
                    $this->errorRight($f3, 'map_update');
                    return;
                }
                $webhookData = $map->getWebhookData();
            }
        }

        $this->out($webhookData);
    }

    /**
     * @param \Base $f3
     * @param       $test
     * @throws \Exception
     */
    public function put(\Base $f3,  array $test) : void {
        $requestData = $this->getRequestData($f3);
        $activeCharacter = $this->getCharacter();

        if(!$activeCharacter->hasMapRight(self::getRequestTypeId($requestData), 'map_create')){
            $this->errorRight($f3, 'map_create');
            return;
        }

        /**
         * @var Pathfinder\MapModel $map
         */
        $map = Pathfinder\AbstractPathfinderModel::getNew('MapModel');
        $mapData = $this->update($map, $requestData, true)->getData();

        $this->out($mapData);
    }

    /**
     * @param \Base $f3
     * @param       $params
     * @throws \Exception
     */
    public function patch(\Base $f3,  array $params) : void {
        $requestData = $this->getRequestData($f3);
        $mapData = [];

        if($mapId = (int)$params['id']){
            $activeCharacter = $this->getCharacter();

            /**
             * @var Pathfinder\MapModel $map
             */
            $map = Pathfinder\AbstractPathfinderModel::getNew('MapModel');
            $map->getById($mapId);
            if($map->hasAccess($activeCharacter)){
                $typeId = (int)$map->get('typeId', true);
                $canUpdate = $activeCharacter->hasMapRight($typeId, 'map_update');
                $canShare = $activeCharacter->hasMapRight($typeId, 'map_share');

                if(!$canUpdate && !$canShare){
                    $this->errorRight($f3, 'map_update');
                    return;
                }

                if(!$canUpdate){
                    // share right only -> drop map settings
                    $requestData = array_intersect_key($requestData, array_flip(self::SHARE_KEYS));
                }

                // type change removes the old corp/alliance access -> needs delete right
                $newTypeId = self::getRequestTypeId($requestData);
                if(
                    $newTypeId && $newTypeId !== $typeId &&
                    (
                        !$activeCharacter->hasMapRight($typeId, 'map_delete') ||
                        !$activeCharacter->hasMapRight($newTypeId, 'map_update')
                    )
                ){
                    $this->errorRight($f3, 'map type change');
                    return;
                }

                $mapData = $this->update($map, $requestData, $canShare)->getData(true);
            }
        }

        $this->out($mapData);
    }

    /**
     * @param \Base $f3
     * @param       $params
     * @throws \Exception
     */
    public function delete(\Base $f3,  array $params) : void {
        $deletedMapIds = [];

        if($mapId = (int)$params['id']){
            $activeCharacter = $this->getCharacter();

            /**
             * @var Pathfinder\MapModel $map
             */
            $map = Pathfinder\AbstractPathfinderModel::getNew('MapModel');
            $map->getById($mapId);

            if($map->hasAccess($activeCharacter)){
                // check if character has delete right for map type
                if($activeCharacter->hasMapRight((int)$map->get('typeId', true), 'map_delete')){
                    $map->setActive(false);
                    $map->save($activeCharacter);
                    $deletedMapIds[] = $mapId;
                    // broadcast map delete
                    $this->broadcastMapDeleted($mapId);
                }else{
                    $f3->set('HALT', true);
                    $f3->error(401, sprintf(self::ERROR_MAP_DELETE, $activeCharacter->name));
                }
            }
        }

        $this->out($deletedMapIds);
    }

    /**
     * @param Pathfinder\MapModel $map
     * @param array<string, mixed>               $mapData
     * @return Pathfinder\MapModel
     * @throws \Exception
     */
    private function update(Pathfinder\MapModel $map,  $mapData, bool $canShare) : Pathfinder\MapModel {
        $activeCharacter = $this->getCharacter();

        $isNew = $map->dry();
        $map->setData($mapData);
        $typeChange = $map->changed('typeId');
        $map->save($activeCharacter);

        // access ids for $setMapAccess(): new map or type change -> owner only,
        // no share right or no share data sent -> null (access unchanged)
        $getAccessIds = function(Pathfinder\AbstractPathfinderModel $primaryModel, string $key) use ($isNew, $typeChange, $canShare, $mapData) : ?array {
            if($isNew || $typeChange){
                return [$primaryModel->_id];
            }
            return $canShare ? ($mapData[$key] ?? null) : null;
        };

        // save global map access. Depends on map "type" --------------------------------------------------------------
        /**
         * @param Pathfinder\AbstractPathfinderModel $primaryModel
         * @param array|null                         $modelIds
         * @param int                                $maxShared
         * @return int
         */
        $setMapAccess = function(Pathfinder\AbstractPathfinderModel &$primaryModel, ?array $modelIds = [], int $maxShared = 3) use (&$map) : int {
            $added = 0;
            $deleted = 0;
            if(is_array($modelIds)){
                // remove primaryModel id (-> re-add later)
                $modelIds = array_diff(array_map(intval(...), $modelIds), [$primaryModel->_id]);

                // avoid abuse -> respect share limits (-1 is because the primaryModel has also access)
                $modelIds = array_slice($modelIds, 0, max($maxShared - 1, 0));

                // add the primaryModel id back (again)
                $modelIds[] = $primaryModel->_id;

                // clear map access for entities that do not match the map "mapType"
                $deleted += $map->clearAccessByType();

                $compare = $map->compareAccess($modelIds);

                foreach((array)($compare['old'] ?? []) as $modelId) {
                    $deleted += $map->removeFromAccess($modelId);
                }

                $modelClass = (new \ReflectionClass($primaryModel))->getShortName();
                $tempModel = Pathfinder\AbstractPathfinderModel::getNew($modelClass);
                foreach((array)($compare['new'] ?? []) as $modelId) {
                    $tempModel->getById($modelId);
                    if(
                        $tempModel->valid() &&
                        (
                            $modelId == $primaryModel->_id ||   // primary model has always access (regardless of "shared" value)
                            $tempModel->shared == 1             // check if map shared is enabled
                        )
                    ){
                        $added += (int)$map->setAccess($tempModel);
                    }

                    $tempModel->reset();
                }
            }
            return $added + $deleted;
        };

        $accessChangeCount = 0;
        $mapDefaultConf = Config::getMapsDefaultConfig();
        if($map->isPrivate()){
            $accessChangeCount = $setMapAccess(
                $activeCharacter,
                $getAccessIds($activeCharacter, 'mapCharacters'),
                (int)$mapDefaultConf['private']['max_shared']
            );
        }elseif($map->isCorporation()){
            if($corporation = $activeCharacter->getCorporation()){
                $accessChangeCount = $setMapAccess(
                    $corporation,
                    $getAccessIds($corporation, 'mapCorporations'),
                    (int)$mapDefaultConf['corporation']['max_shared']
                );
            }
        }elseif($map->isAlliance()){
            if($alliance = $activeCharacter->getAlliance()){
                $accessChangeCount = $setMapAccess(
                    $alliance,
                    $getAccessIds($alliance, 'mapAlliances'),
                    (int)$mapDefaultConf['alliance']['max_shared']
                );
            }
        }

        if($accessChangeCount){
            $map->touch('updated');
            $map->save($activeCharacter);
        }

        // reload the same map model (refresh)
        // this makes sure all data is up2date
        $map->getById($map->_id, 0);

        // broadcast map Access -> and send map Data
        $this->broadcastMapAccess($map);

        return $map;
    }

    /**
     * broadcast characters with map access rights to WebSocket server
     * -> if characters with map access found -> broadcast mapData to them
     * @param Pathfinder\MapModel $map
     * @throws \Exception
     */
    protected function broadcastMapAccess(Pathfinder\MapModel $map): void {
        $mapAccess =  [
            'id' => $map->_id,
            'name' => $map->name,
            'characterIds' => array_map(fn($data) => $data->id, $map->getCharactersData())
        ];

        $this->getF3()->webSocket()->write('mapAccess', $mapAccess);

        // map has (probably) active connections that should receive map Data
        $this->broadcastMap($map, true);
    }

    /**
     * broadcast map delete information to clients
     * @param int $mapId
     */
    private function broadcastMapDeleted(int $mapId): void{
        $this->getF3()->webSocket()->write('mapDeleted', $mapId);
    }

    /**
     * send 401 for a missing map right
     * @param \Base  $f3
     * @param string $right
     */
    protected function errorRight(\Base $f3, string $right) : void {
        $f3->set('HALT', true);
        $f3->error(401, sprintf(self::ERROR_MAP_RIGHT, $this->getCharacter()->name, $right));
    }

    /**
     * get map type id from request data ('typeId' from form, or 'type' => ['id' => …])
     * @param array<string, mixed> $requestData
     * @return int
     */
    protected static function getRequestTypeId(array $requestData) : int {
        if(isset($requestData['type']['id'])){
            return (int)$requestData['type']['id'];
        }
        return is_array($requestData['typeId'] ?? null) ? 0 : (int)($requestData['typeId'] ?? 0);
    }
}
