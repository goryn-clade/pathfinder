<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 09.02.15
 * Time: 23:30
 */

namespace Exodus4D\Pathfinder\Controller;


use Exodus4D\Pathfinder\Model\Pathfinder;

class AccessController extends Controller {

    /**
     * event handler
     * @param \Base $f3
     * @param $params
     * @return bool
     * @throws \Exception
     */
    #[\Override]
    function beforeroute(\Base $f3,  array $params) : bool {
        if($return = parent::beforeroute($f3, $params)){
            // Any route/endpoint of a child class of this one,
            // requires a valid logged in user!
            if($this->isLoggedIn($f3) !== 'OK'){
                // no character found or login timer expired
                $this->logoutCharacter($f3);
                // skip route handler and afterroute()
                $return = false;
            }
        }

        return $return;
    }

    /**
     * broadcast MapModel to clients
     * @param Pathfinder\MapModel $map
     * @param bool                $noCache
     */
    protected function broadcastMap(Pathfinder\MapModel $map, bool $noCache = false) : void {
        $this->broadcastMapData($this->getFormattedMapData($map, $noCache));
    }


    /**
     * broadcast map data to clients
     * -> send over TCP Socket
     * @param array<string, mixed>|null $mapData
     */
    protected function broadcastMapData(?array $mapData) : void {
        if(!empty($mapData)){
            $this->getF3()->webSocket()->write('mapUpdate', $mapData);
        }
    }

    /**
     * get formatted Map Data
     * @param Pathfinder\MapModel $map
     * @param bool                $noCache
     * @return array<string, mixed>|null
     */
    protected function getFormattedMapData(Pathfinder\MapModel $map, bool $noCache = false) : ?array {
        $data = null;
        try{
            $mapData = $map->getData($noCache);
            $data = [
                'config' => $mapData->mapData,
                'data' => [
                    'systems' => $mapData->systems,
                    'connections' => $mapData->connections,
                    'groups' => $mapData->groups ?? [],
                ]
            ];
        }catch(\Exception){

        }

        return $data;
    }

}