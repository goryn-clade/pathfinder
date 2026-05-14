<?php


namespace Exodus4D\Pathfinder\Controller\Api\Rest;


use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Controller\Ccp\Universe;
use Exodus4D\Pathfinder\Enum\ConnectionType;
use Exodus4D\Pathfinder\Model\Pathfinder;

class Route extends AbstractRestController {


    /**
     * cache key for current Thera connections from eve-scout.com
     */
    const CACHE_KEY_THERA_JUMP_DATA = 'CACHED_THERA_JUMP_DATA';

    /**
     * cache key for current Turnur connections from eve-scout.com
     */
    const CACHE_KEY_TURNUR_JUMP_DATA = 'CACHED_TURNUR_JUMP_DATA';

    /**
     * EVE system ID for Thera
     */
    const THERA_SYSTEM_ID = 31000005;

    /**
     * EVE system ID for Turnur
     */
    const TURNUR_SYSTEM_ID = 30002086;

    /**
     * route search depth
     */
    const ROUTE_SEARCH_DEPTH_DEFAULT = 1;

    /**
     * ESI route search can handle max 100 custom connections
     * -> each connection has a A->B and B->A entry. So we have 50 "real connections"
     */
    const MAX_CONNECTION_COUNT  = 100;

    /**
     * cache time for static jump data (e.g. K-Space stargates)
     * @var int
     */
    private $staticJumpDataCacheTime = 86400;

    /**
     * cache time for dynamic jump data (e.g. W-Space systems, Jumpbridges. ...)
     * @var int
     */
    private $dynamicJumpDataCacheTime = 10;

    /**
     * cache time for Thera connections from eve-scout.com
     * @var int
     */
    private $theraJumpDataCacheTime = 60;

    /**
     * array system information grouped by systemId
     * @var array<string, mixed>
     */
    private $nameArray = [];

    /**
     * array neighbour systems grouped by systemName
     * @var array<string, mixed>
     */
    private $jumpArray = [];

    /**
     * array with systemName => systemId matching
     * @var array<string, mixed>
     */
    private $idArray = [];

    /**
     * template for routeData payload
     * @var array<string, mixed>
     */
    private $defaultRouteData = [
        'routePossible' => false,
        'routeJumps' => 0,
        'maxDepth' => self::ROUTE_SEARCH_DEPTH_DEFAULT,
        'depthSearched' => 0,
        'searchType' => '',
        'route' => [],
        'error' => ''
    ];

    /**
     * reset all jump data
     */
    protected function resetJumpData(){
        $this->nameArray = [];
        $this->jumpArray = [];
        $this->idArray = [];
    }

    /**
     * set static system jump data for this instance
     * the data is fixed and should not change
     * -> jump data includes JUST "static" connections (Stargates)
     * -> this data is equal for EACH route search (does not depend on map data)
     */
    private function setStaticJumpData(): void{
        if($universeDB = $this->getDB('UNIVERSE')){
            $query = "SELECT * FROM system_neighbour";
            $rows = $universeDB->exec($query, null, $this->staticJumpDataCacheTime);

            if(count($rows) > 0){
                array_walk($rows, function(&$row): void{
                    $row['jumpNodes'] = array_map(intval(...), explode(':', (string) $row['jumpNodes']));
                });
                $this->updateJumpData($rows);
            }
        }
    }

    /**
     * filter an array of connection-type values against the model whitelist
     * @param array<string, mixed> $values
     * @return string[] safe values, in original order, deduplicated, ready to inline into SQL
     */
    private function filterConnectionTypes(array $values) : array {
        return array_values(array_intersect(
            array_unique(array_map(strval(...), $values)),
            Pathfinder\ConnectionModel::getConnectionTypeWhitelist()
        ));
    }

    /**
     * set/add dynamic system jump data for specific "mapId"´s
     * -> this data is dynamic and could change on any map change
     * -> (e.g. new system added, connection added/updated, ...)
     * @param  $mapIds
     * @param  $filterData
     * @throws \Exception
     */
    private function setDynamicJumpData( $mapIds = [],  $filterData = []): void{
        // make sure, mapIds are integers (protect against SQL injections)
        $mapIds = array_unique( array_map(intval(...), $mapIds), SORT_NUMERIC);

        if( !empty($mapIds) ){
            // map filter ---------------------------------------------------------------------------------------------
            $whereMapIdsQuery = (count($mapIds) == 1) ? " = " . reset($mapIds) : " IN (" . implode(', ', $mapIds) . ")";

            // connection filter --------------------------------------------------------------------------------------
            $whereQuery = "";
            $includeScopes = [];
            $includeTypes = [];
            $excludeTypes = [];

            $excludeEndpointTypes = [];

            if( ($filterData['stargates'] ?? false) === true){
                // include "stargates" for search
                $includeScopes[] = ConnectionType::Stargate->value;
                $includeTypes[] = ConnectionType::Stargate->value;

            }

            if( ($filterData['jumpbridges'] ?? false) === true ){
                // add jumpbridge connections for search
                $includeScopes[] = ConnectionType::Jumpbridge->value;
                $includeTypes[] = ConnectionType::Jumpbridge->value;
            }

            if( ($filterData['wormholes'] ?? false) === true ){
                // add wormhole connections for search
                $includeScopes[] = ConnectionType::Wh->value;
                $includeTypes[] = ConnectionType::WhFresh->value;


                if( ($filterData['wormholesReduced'] ?? false) === true ){
                    $includeTypes[] = ConnectionType::WhReduced->value;
                }

                if( ($filterData['wormholesCritical'] ?? false) === true ){
                    $includeTypes[] = ConnectionType::WhCritical->value;
                }

                foreach([
                    'wormholesEOL1' => ConnectionType::WhEol1->value,
                    'wormholesEOL2' => ConnectionType::WhEol2->value,
                    'wormholesEOL3' => ConnectionType::WhEol3->value,
                ] as $key => $type){
                    if(($filterData[$key] ?? true) === false){
                        $excludeTypes[] = $type;
                    }
                }

                if(!empty($filterData['excludeTypes'])){
                    $excludeTypes = array_values(array_intersect(
                        array_map(strval(...), (array)$filterData['excludeTypes']),
                        Pathfinder\ConnectionModel::getConnectionTypeWhitelist()
                    ));
                }
            }

            if( ($filterData['endpointsBubble'] ?? false) !== true ){
                $excludeEndpointTypes[] = 'bubble';
            }

            // search connections -------------------------------------------------------------------------------------

            // run every type/scope list through the whitelist helper before inlining into SQL
            $includeScopes        = $this->filterConnectionTypes($includeScopes);
            $includeTypes         = $this->filterConnectionTypes($includeTypes);
            $excludeTypes         = $this->filterConnectionTypes($excludeTypes);
            $excludeEndpointTypes = array_values(array_intersect($excludeEndpointTypes, ['bubble']));

            if( !empty($includeScopes) ){
                $whereQuery .= " `connection`.`scope` IN ('" . implode("', '", $includeScopes) . "') AND ";

                if( !empty($excludeTypes) ){
                    $whereQuery .= " `connection`.`type` NOT REGEXP '" . implode("|", $excludeTypes) . "' AND ";
                }

                if( !empty($includeTypes) ){
                    $whereQuery .= " `connection`.`type` REGEXP '" . implode("|", $includeTypes) . "' AND ";
                }

                if( !empty($excludeEndpointTypes) ){
                    $whereQuery .= " CONCAT_WS(' ', `connection`.`sourceEndpointType`, `connection`.`targetEndpointType`) ";
                    $whereQuery .= " NOT REGEXP '" . implode("|", $excludeEndpointTypes) . "' AND ";
                }

                $query = "SELECT 
                            `system_src`.`systemId` systemSourceId,
                            `system_tar`.`systemId` systemTargetId
                          FROM
                            `connection` INNER JOIN
                            `map` ON
                              `map`.`id` = `connection`.`mapId` AND 
                              `map`.`active` = 1 INNER JOIN
                            `system` `system_src` ON 
                              `system_src`.`id` = `connection`.`source` AND
                              `system_src`.`active` = 1 INNER JOIN
                            `system` `system_tar` ON 
                              `system_tar`.`id` = `connection`.`target` AND
                              `system_tar`.`active` = 1
                          WHERE
                              " . $whereQuery . "
                              `connection`.`active` = 1 AND 
                              `connection`.`mapId` " . $whereMapIdsQuery . "
                              ";

                if($db = $this->getDB()){
                    $rows = $db->exec($query,  null, $this->dynamicJumpDataCacheTime);
                }else{
                    $rows = [];
                }

                if(count($rows) > 0){
                    $jumpData = [];
                    $universe = new Universe();

                    /**
                     * enrich dynamic jump data with static system data (from universe DB)
                     * @param array $row
                     * @param string $systemSourceKey
                     * @param string $systemTargetKey
                     */
                    $enrichJumpData = function(array &$row, string $systemSourceKey, string $systemTargetKey) use (&$jumpData, &$universe): void {
                        if(
                            !array_key_exists($row[$systemSourceKey], $jumpData) &&
                            !is_null($staticData = $universe->getSystemData($row[$systemSourceKey]))
                        ){
                            $jumpData[$row[$systemSourceKey]] = [
                                'systemId'          => (int)$row[$systemSourceKey],
                                'systemName'        => $staticData->name,
                                'constellationId'   => $staticData->constellation->id,
                                'regionId'          => $staticData->constellation->region->id,
                                'trueSec'           => $staticData->trueSec,
                                'jumpNodes'         => [],
                            ];
                        }

                        if( !in_array($row[$systemTargetKey], (array)$jumpData[$row[$systemSourceKey]]['jumpNodes']) ){
                            $jumpData[$row[$systemSourceKey]]['jumpNodes'][] = (int)$row[$systemTargetKey];
                        }
                    };

                    for($i = 0; $i < count($rows); $i++){
                        // skip connections involving unknown systems (null systemId)
                        if(is_null($rows[$i]['systemSourceId']) || is_null($rows[$i]['systemTargetId'])){
                            continue;
                        }
                        $enrichJumpData($rows[$i],  'systemSourceId', 'systemTargetId');
                        $enrichJumpData($rows[$i],  'systemTargetId', 'systemSourceId');
                    }

                    $this->updateJumpData($jumpData);
                }
            }
        }
    }

    /**
     * build jump data from EVE Scout connections, filtered to a specific hub system
     * @param int $hubSystemId only include connections where source OR target matches this system ID
     * @param string $cacheKey
     * @return array<string, mixed>
     */
    private function buildEveScoutJumpData(int $hubSystemId, string $cacheKey) : array {
        if(!$this->getF3()->exists($cacheKey, $jumpData)){
            $jumpData = [];
            $connectionsData = $this->getF3()->eveScoutClient()->send('getTheraConnections');

            if(!empty($connectionsData) && !isset($connectionsData['error'])){
                $enrichJumpData = function(array &$row, string $systemSourceKey, string $systemTargetKey) use (&$jumpData): void {
                    if(
                        is_array($systemSource = $row[$systemSourceKey]) && !empty($systemSource) &&
                        is_array($systemTarget = $row[$systemTargetKey]) && !empty($systemTarget)
                    ){
                        $srcId = $systemSource['id'];
                        $targetId = $systemTarget['id'];
                        if(!array_key_exists($srcId, $jumpData)){
                            $jumpData[$srcId] = [
                                'systemId'   => $srcId,
                                'systemName' => $systemSource['name'],
                                'jumpNodes'  => [],
                            ];
                        }
                        if(!in_array($targetId, $jumpData[$srcId]['jumpNodes'])){
                            $jumpData[$srcId]['jumpNodes'][] = $targetId;
                        }
                    }
                };

                foreach((array)$connectionsData['connections'] as $connectionData){
                    // only include connections involving the specified hub system
                    $sourceId = (int)(is_array($connectionData['source'] ?? null) ? $connectionData['source']['id'] ?? 0 : 0);
                    $targetId = (int)(is_array($connectionData['target'] ?? null) ? $connectionData['target']['id'] ?? 0 : 0);
                    if($sourceId !== $hubSystemId && $targetId !== $hubSystemId){
                        continue;
                    }
                    $enrichJumpData($connectionData, 'source', 'target');
                    $enrichJumpData($connectionData, 'target', 'source');
                }

                if(!empty($jumpData)){
                    $this->getF3()->set($cacheKey, $jumpData, $this->theraJumpDataCacheTime);
                }
            }
        }

        return $jumpData;
    }

    /**
     * set current Thera connections jump data for this instance
     * -> Connected wormholes pulled from eve-scout.com, filtered to Thera (31000005)
     */
    private function setTheraJumpData() : void {
        $jumpData = $this->buildEveScoutJumpData(self::THERA_SYSTEM_ID, self::CACHE_KEY_THERA_JUMP_DATA);
        $this->updateJumpData($jumpData);
    }

    /**
     * set current Turnur connections jump data for this instance
     * -> Connected wormholes pulled from eve-scout.com, filtered to Turnur (30002086)
     */
    private function setTurnurJumpData() : void {
        $jumpData = $this->buildEveScoutJumpData(self::TURNUR_SYSTEM_ID, self::CACHE_KEY_TURNUR_JUMP_DATA);
        $this->updateJumpData($jumpData);
    }

    /**
     * update jump data for this instance
     * -> data is either coming from CCPs [SDE] OR from map specific data
     * @param  $rows
     */
    private function updateJumpData( &$rows = []): void{
        foreach($rows as &$row){
            $regionId       = (int)($row['regionId'] ?? 0);
            $constId        = (int)($row['constellationId'] ?? 0);
            $systemName     = (string)($row['systemName']);
            $systemId       = (int)$row['systemId'];
            $secStatus      = (float)($row['trueSec'] ?? 0.0);

            // fill "nameArray" data ----------------------------------------------------------------------------------
            if( !isset($this->nameArray[$systemId]) ){
                $this->nameArray[$systemId][0] = $systemName;
                $this->nameArray[$systemId][1] = $regionId;
                $this->nameArray[$systemId][2] = $constId;
                $this->nameArray[$systemId][3] = $secStatus;
            }

            // fill "idArray" data ------------------------------------------------------------------------------------
            if( !isset($this->idArray[$systemId]) ){
                $this->idArray[$systemId] = $systemName;
            }

            // fill "jumpArray" data ----------------------------------------------------------------------------------
            if( !is_array($this->jumpArray[$systemId] ?? null) ){
                $this->jumpArray[$systemId] = [];
            }
            $this->jumpArray[$systemId] = array_merge((array)$row['jumpNodes'], $this->jumpArray[$systemId]);

            // add systemName to end (if not already there)
            if(end($this->jumpArray[$systemId]) != $systemName){
                array_push($this->jumpArray[$systemId], $systemName);
            }
        }
    }

    /**
     * filter systems (remove some systems) e.g. WH,LS,0.0 for "secure search"
     * @param  $filterData
     * @param  $keepSystems
     */
    private function filterJumpData( $filterData = [],  $keepSystems = []): void{
        if(($filterData['flag'] ?? '') == 'secure'){
            // remove all systems (TrueSec < 0.5) from search arrays
            $this->jumpArray = array_filter($this->jumpArray, function($systemId) use ($keepSystems) {
                $systemNameData = $this->nameArray[$systemId];
                $systemSec = $systemNameData[3];

                if(
                    $systemSec < 0.45 &&
                    !in_array($systemId, $keepSystems) &&
                    !preg_match('/^j\d+$/i', (string) $this->idArray[$systemId]) // WHs are supposed to be "secure"
                ){
                    // remove system from nameArray and idArray
                    unset($this->nameArray[$systemId]);
                    unset($this->idArray[$systemId]);
                    return false;
                }else{
                    return true;
                }
            }, ARRAY_FILTER_USE_KEY );
        }
    }

    /**
     * get system data by systemId and dataName
     * @param int $systemId
     * @param string $option
     * @return null
     */
    private function getSystemInfoBySystemId(int $systemId, string $option){
        $row = $this->nameArray[$systemId] ?? null;
        if(!is_array($row)){
            return null;
        }
        return match ($option) {
            'systemName' => $row[0],
            'regionId' => $row[1],
            'constellationId' => $row[2],
            'trueSec' => $row[3],
            default => null,
        };
    }

    /**
     * recursive search function within a undirected graph
     * @param  $G
     * @param string $A
     * @param string $B
     * @param int $M
     * @return array<string, mixed>
     * @param array<string, mixed> $G
     */
    private function graph_find_path(array &$G, string $A, string $B, int $M = 50000): array{
        $maxDepth = $M;

        // $P will hold the result path at the end.
        // Remains empty if no path was found.
        $P = [];

        // For each Node ID create a "visit information",
        // initially set as 0 (meaning not yet visited)
        // as soon as we visit a node we will tag it with the "source"
        // so we can track the path when we reach the search target

        $V = [];

        // We are going to keep a list of nodes that are "within reach",
        // initially this list will only contain the start node,
        // then gradually expand (almost like a flood fill)
        $R = [trim($A)];

        $A = trim($A);
        $B = trim($B);

        while(count($R) > 0 && $M > 0){
            $M--;

            $X = trim(array_shift($R));

            if(array_key_exists($X, $G)){
                foreach($G[$X] as $Y){
                    $Y = trim((string) $Y);
                    // See if we got a solution
                    if($Y == $B){
                        // We did? Construct a result path then
                        array_push($P, $B);
                        array_push($P, $X);
                        while($V[$X] != $A){
                            array_push($P, trim($V[$X]));
                            $X = $V[$X];
                        }
                        array_push($P, $A);
                        //return array_reverse($P);
                        return [
                            'path'=> array_reverse($P),
                            'depth' => ($maxDepth - $M)
                        ];
                    }
                    // First time we visit this node?
                    if(!array_key_exists($Y, $V)){
                        // Store the path so we can track it back,
                        $V[$Y] = $X;
                        // and add it to the "within reach" list
                        array_push($R, $Y);
                    }
                }
            }
        }

        return [
            'path'=> $P,
            'depth' => ($maxDepth - $M)
        ];
    }

    /**
     * get formatted jump node data
     * @param int $systemId
     * @return array<string, mixed>
     */
    protected function getJumpNodeData(int $systemId) : array {
        return [
            'system' => $this->getSystemInfoBySystemId($systemId, 'systemName'),
            'security' => $this->getSystemInfoBySystemId($systemId, 'trueSec')
        ];
    }

    /**
     * search root between two systemIds
     * -> function searches over ESI API, as fallback a custom search algorithm is used (no ESI)
     * @param int $systemFromId
     * @param int $systemToId
     * @param int $searchDepth
     * @param  $mapIds
     * @param  $filterData
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function searchRoute(int $systemFromId, int $systemToId, int $searchDepth = 0,  $mapIds = [],  $filterData = []) : array {
        // search root by ESI API
        $routeData = $this->searchRouteESI($systemFromId, $systemToId, $searchDepth, $mapIds, $filterData);

        // Endpoint return http:404 in case no route find (e.g. from inside a wh)
        // we thread that error "no route found" as a valid response! -> no fallback to custom search
        if(!empty($routeData['error']) && strtolower((string) $routeData['error']) !== 'no route found'){
            // ESI route search has errors -> fallback to custom search implementation
            $routeData = $this->searchRouteCustom($systemFromId, $systemToId, $searchDepth, $mapIds, $filterData);
        }

        return $routeData;
    }

    /**
     * uses a custom search algorithm to fine a route
     * @param int $systemFromId
     * @param int $systemToId
     * @param int $searchDepth
     * @param  $mapIds
     * @param  $filterData
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function searchRouteCustom(int $systemFromId, int $systemToId, int $searchDepth = 0,  $mapIds = [],  $filterData = []) : array {
        // reset all previous set jump data
        $this->resetJumpData();

        $searchDepth = $searchDepth ?: Config::getPathfinderData('route.search_depth');

        $routeData = $this->defaultRouteData;
        $routeData['maxDepth'] = $searchDepth;
        $routeData['searchType'] = 'custom';

        if($systemFromId && $systemToId){
            // prepare search data ------------------------------------------------------------------------------------
            // add static data (e.g. K-Space stargates,..)
            $this->setStaticJumpData();

            // add map specific data
            $this->setDynamicJumpData($mapIds, $filterData);

            // add current Thera connections data
            if($filterData['wormholesThera'] ?? false){
                $this->setTheraJumpData();
            }

            // add current Turnur connections data
            if($filterData['wormholesTurnur'] ?? false){
                $this->setTurnurJumpData();
            }

            // filter jump data (e.g. remove some systems (0.0, LS)
            // --> don´t filter some systems (e.g. systemFrom, systemTo) even if they are are WH,LS,0.0
            $this->filterJumpData($filterData, [$systemFromId, $systemToId]);

            // search route -------------------------------------------------------------------------------------------

            // jump counter
            $jumpNum = 0;
            $depthSearched = 0;

            if(isset($this->jumpArray[$systemFromId])){
                // check if the system we are looking for is a direct neighbour
                foreach($this->jumpArray[$systemFromId] as $n){
                    if($n == $systemToId){
                        $jumpNum = 2;
                        $routeData['route'][] = $this->getJumpNodeData($n);
                        break;
                    }
                }

                // system is not a direct neighbour -> search recursive its neighbours
                if($jumpNum == 0){
                    $searchResult = $this->graph_find_path( $this->jumpArray, $systemFromId, $systemToId, $searchDepth );
                    $depthSearched = $searchResult['depth'];
                    foreach($searchResult['path'] as $systemId){
                        if($jumpNum > 0){
                            $routeData['route'][] = $this->getJumpNodeData($systemId);
                        }
                        $jumpNum++;
                    }
                }

                if($jumpNum > 0){
                    // route found
                    $routeData['routePossible'] = true;
                    // insert "from" system on top
                    array_unshift($routeData['route'], $this->getJumpNodeData($systemFromId));
                }else{
                    // route not found
                    $routeData['routePossible'] = false;
                }
            }

            // route jumps
            $routeData['routeJumps'] = $jumpNum - 1;
            $routeData['depthSearched'] = $depthSearched;
        }

        return $routeData;
    }

    /**
     * uses ESI route search endpoint to fine a route
     * @param int $systemFromId
     * @param int $systemToId
     * @param int $searchDepth
     * @param  $mapIds
     * @param  $filterData
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function searchRouteESI(int $systemFromId, int $systemToId, int $searchDepth = 0,  $mapIds = [],  $filterData = []) : array {
        // reset all previous set jump data
        $this->resetJumpData();

        $searchDepth = $searchDepth ?: Config::getPathfinderData('route.search_depth');

        $routeData = $this->defaultRouteData;
        $routeData['maxDepth'] = $searchDepth;
        $routeData['searchType'] = 'esi';

        if($systemFromId && $systemToId){
            // ESI route search can only handle 50 $connections (100 entries)
            // we  want to add NON stargate connections ONLY for ESI route search
            // because ESI will use them anyways!
            $filterData['stargates'] = false;

            // prepare search data ------------------------------------------------------------------------------------

            // add map specific data
            $this->setDynamicJumpData($mapIds, $filterData);

            // add current Thera connections data
            if($filterData['wormholesThera'] ?? false){
                $this->setTheraJumpData();
            }

            // add current Turnur connections data
            if($filterData['wormholesTurnur'] ?? false){
                $this->setTurnurJumpData();
            }

            // filter jump data (e.g. remove some systems (0.0, LS)
            // --> don´t filter some systems (e.g. systemFrom, systemTo) even if they are are WH,LS,0.0
            $this->filterJumpData($filterData, [$systemFromId, $systemToId]);

            // pre-populate connections array with new connected_pairs from TrailBlazer expansion because 
            // they have not been added to ESI routes. Can be removed when ESI is updated
            $connections = [
                [30001721,30001957], // Saminer => F7-ICZ
                [30001957,30001721], // F7-ICZ => Saminer
                [30003605,30003823], // Kennink => Eggheron
                [30003823,30003605], // Eggheron => Kennink
                [30003452,30005198], // Pakhshi => Irgrus
                [30005198,30003452], // Irgrus => Pakhshi
                [30000134,30005196], // Hykkota => Ahbazon
                [30005196,30000134], // Ahbazon => Hykkota
            ];
            foreach($this->jumpArray as $systemSourceId => $jumpData){
                $count = count($jumpData);
                if($count > 1){
                    // ... should always > 1
                    // loop all connections for current source system
                    foreach($jumpData as $systemTargetId){
                        // skip last entry
                        if(--$count <= 0){
                            break;
                        }

                        // systemIds exist and wer not removed before in filterJumpData()
                        if($systemSourceId && $systemTargetId){
                            $jumpNode = [$systemSourceId, $systemTargetId];
                            // jumpNode must be unique for ESI,
                            // ... there can be multiple connections between same systems in Pathfinder
                            if(!in_array($jumpNode, $connections)){
                                $connections[] = [$systemSourceId, $systemTargetId];
                                // check if connections limit is reached
                                if(count($connections) >= self::MAX_CONNECTION_COUNT){
                                    // ESI API limit for custom "connections"
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            // search route -------------------------------------------------------------------------------------------
            $options = [
                'flag' => ($filterData['flag'] ?? ''),
                'connections' => $connections
            ];

            $result = $this->getF3()->ccpClient()->send('getRoute', $systemFromId, $systemToId, $options);

            // format result ------------------------------------------------------------------------------------------

            // jump counter
            $jumpNum = 0;
            $depthSearched = 0;
            if( !empty($result['error']) ){
                $routeData['error'] = $result['error'];
            }elseif( !empty($result['route']) ){
                $jumpNum = count($result['route']) - 1;

                // check max search depth
                if($jumpNum <= $routeData['maxDepth']){
                    $depthSearched = $jumpNum;

                    $routeData['routePossible'] = true;

                    // Now (after search) we have to "add" static jump data information
                    $this->setStaticJumpData();

                    foreach($result['route'] as $systemId){
                        $routeData['route'][] = $this->getJumpNodeData($systemId);
                    }
                }else{
                    $depthSearched = $routeData['maxDepth'];
                }
            }

            // route jumps
            $routeData['routeJumps'] = $jumpNum;
            $routeData['depthSearched'] = $depthSearched;
        }

        return $routeData;
    }

    /**
     * get key for route cache
     * @param $mapIds
     * @param $systemFrom
     * @param $systemTo
     * @param array<string, mixed> $filterData
     * @return string
     */
    private function getRouteCacheKey( $mapIds, int $systemFrom, int $systemTo,  $filterData = []){

        $keyParts = [
            implode('_', $mapIds),
            self::formatHiveKey($systemFrom),
            self::formatHiveKey($systemTo)
        ];

        $keyParts += $filterData;
        $keyStrings = array_map(fn($v) => is_array($v) ? implode(',', $v) : (string)$v, $keyParts);
        return 'route_' . hash('md5', implode('_', $keyStrings));
    }

    /**
     * search multiple route between two systems
     * @param \Base $f3
     * @throws \Exception
     */
    public function post(\Base $f3): void{
        $requestData = $this->getRequestData($f3);

        $activeCharacter = $this->getCharacter();

        $return = (object) [];
        $return->error = [];
        $return->routesData = [];

        if( !empty($requestData['routeData']) ){
            $routesData = (array)$requestData['routeData'];

            //  map data where access was already checked -> cached data
            $validMaps = [];

            /**
             * @var Pathfinder\MapModel $map
             */
            $map = Pathfinder\AbstractPathfinderModel::getNew('MapModel');

            // limit max search routes to max limit
            array_splice($routesData, Config::getPathfinderData('route.limit'));

            foreach($routesData as $key => $routeData){
                // mapIds are optional. If mapIds is empty or not set
                // route search is limited to CCPs static data
                $mapData = (array)$routeData['mapIds'];
                $mapData = array_flip( array_map(intval(...), $mapData) );

                // check map access (filter requested mapIDs and format) ----------------------------------------------
                array_walk($mapData, function(&$item, $key, $data): void{
                    /**
                     * @var Pathfinder\MapModel $data[0]
                     */
                    if( isset($data[1][$key]) ){
                        // character has map access -> do not check again
                        $item = $data[1][$key];
                    }else{
                        // check map access for current character
                        $data[0]->getById($key);

                        if( $data[0]->hasAccess($data[2]) ){
                            $item = ['id' => $key, 'name' => $data[0]->name];
                        }else{
                            $item = false;
                        }
                        $data[0]->reset();
                    }

                }, [$map, $validMaps, $activeCharacter]);

                // filter maps with NO access right
                $mapData = array_filter($mapData);
                $mapIds = array_column($mapData, 'id');

                // add map data to cache array
                $validMaps += $mapData;

                // search route with filter options
                $filterData = [
                    'stargates'             => (bool) ($routeData['stargates'] ?? false),
                    'jumpbridges'           => (bool) ($routeData['jumpbridges'] ?? false),
                    'wormholes'             => (bool) ($routeData['wormholes'] ?? false),
                    'wormholesReduced'      => (bool) ($routeData['wormholesReduced'] ?? false),
                    'wormholesCritical'     => (bool) ($routeData['wormholesCritical'] ?? false),
                    'wormholesEOL1'         => (bool) ($routeData['wormholesEOL1'] ?? true),
                    'wormholesEOL2'         => (bool) ($routeData['wormholesEOL2'] ?? true),
                    'wormholesEOL3'         => (bool) ($routeData['wormholesEOL3'] ?? true),
                    'wormholesThera'        => (bool) ($routeData['wormholesThera'] ?? false),
                    'wormholesTurnur'       => (bool) ($routeData['wormholesTurnur'] ?? false),
                    'wormholesSizeMin'      => (string) ($routeData['wormholesSizeMin'] ?? ''),
                    'excludeTypes'          => (array) ($routeData['excludeTypes'] ?? []),
                    'endpointsBubble'       => (bool) ($routeData['endpointsBubble'] ?? false),
                    'flag'                  => ($routeData['flag'] ?? '')
                ];

                $returnRoutData = [
                    'systemFromData'        => ($routeData['systemFromData'] ?? []),
                    'systemToData'          => ($routeData['systemToData'] ?? []),
                    'skipSearch'            => (bool) ($routeData['skipSearch'] ?? false),
                    'maps'                  => $mapData,
                    'mapIds'                => $mapIds
                ];

                // add filter options for each route as well
                $returnRoutData += $filterData;

                if(
                    !$returnRoutData['skipSearch'] &&
                    count($mapIds) > 0
                ){
                    $systemFromId   = (int)$routeData['systemFromData']['systemId'];
                    $systemToId     = (int)$routeData['systemToData']['systemId'];

                    $cacheKey = $this->getRouteCacheKey(
                        $mapIds,
                        $systemFromId,
                        $systemToId,
                        $filterData
                    );

                    if($f3->exists($cacheKey, $cachedData)){
                        // get data from cache
                        $returnRoutData = $cachedData;
                    }else{
                        $foundRoutData = $this->searchRoute($systemFromId, $systemToId, 0, $mapIds, $filterData);

                        $returnRoutData = array_merge($returnRoutData, $foundRoutData);

                        // cache if route was found
                        if(
                            isset($returnRoutData['routePossible']) &&
                            $returnRoutData['routePossible'] === true
                        ){
                            $f3->set($cacheKey, $returnRoutData, $this->dynamicJumpDataCacheTime);
                        }
                    }
                }

                $return->routesData[] = $returnRoutData;
            }
        }

        $this->out($return);
    }
}
