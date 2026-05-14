<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 26.02.15
 * Time: 21:12
 */

namespace Exodus4D\Pathfinder\Model\Pathfinder;

use DB\SQL\Schema;
use Exodus4D\Pathfinder\Controller\Api\Rest\Route;
use Exodus4D\Pathfinder\Enum\ConnectionType;
use Exodus4D\Pathfinder\Lib\Logging;
use Exodus4D\Pathfinder\Exception;
use Exodus4D\Pathfinder\Model\Universe;

class ConnectionModel extends AbstractMapTrackingModel {

    /**
     * @var string
     */
    protected $table = 'connection';

    /**
     * @var array<string, mixed>
     */
    protected $fieldConf = [
        'active' => [
            'type' => Schema::DT_BOOL,
            'nullable' => false,
            'default' => 1,
            'index' => true
        ],
        'mapId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Pathfinder\MapModel::class,
            'constraint' => [
                [
                    'table' => 'map',
                    'on-delete' => 'CASCADE'
                ]
            ]
        ],
        'source' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Pathfinder\SystemModel::class,
            'constraint' => [
                [
                    'table' => 'system',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'activity-log' => true
        ],
        'target' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Pathfinder\SystemModel::class,
            'constraint' => [
                [
                    'table' => 'system',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'activity-log' =>  true
        ],
        'scope' => [
            'type' => Schema::DT_VARCHAR128,
            'nullable' => false,
            'default' => '',
            'activity-log' => true
        ],
        'type' => [
            'type' => self::DT_JSON,
            'activity-log' => true
        ],
        'sourceEndpointType' => [
            'type' => self::DT_JSON,
            'activity-log' => true
        ],
        'targetEndpointType' => [
            'type' => self::DT_JSON,
            'activity-log' => true
        ],
        'eolUpdated' => [
            'type' => Schema::DT_TIMESTAMP,
            'default' => null
        ],
        'nominalLifespan' => [
            'type' => Schema::DT_INT,
            'default' => null
        ],
        'signatures' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Pathfinder\SystemSignatureModel::class, 'connectionId']
        ],
        'connectionLog' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Pathfinder\ConnectionLogModel::class, 'connectionId']
        ]
    ];

    // Jump-mass type strings (sourced from enum for single source of truth)
    private const JUMP_MASS_TYPES = [
        ConnectionType::WhJumpMassS->value,
        ConnectionType::WhJumpMassM->value,
        ConnectionType::WhJumpMassL->value,
        ConnectionType::WhJumpMassXl->value,
    ];

    // Thresholds match Init.wormholeSizes in init.js (descending order)
    private const JUMP_MASS_BUCKETS = [
        1_000_000_000 => ConnectionType::WhJumpMassXl->value,
        375_000_000   => ConnectionType::WhJumpMassL->value,
        62_000_000    => ConnectionType::WhJumpMassM->value,
        5_000         => ConnectionType::WhJumpMassS->value,
    ];

    /**
     * @return string[]
     */
    public static function getConnectionTypeWhitelist() : array {
        return ConnectionType::whitelist();
    }

    /**
     * get connection data
     * @param bool $addSignatureData
     * @param bool $addLogData
     * @return \stdClass
     */
    public function getData($addSignatureData = false, $addLogData = false){
        $connectionData = (object) [];
        $connectionData->id             = $this->id;
        $connectionData->source         = $this->source->id;
        $connectionData->target         = $this->target->id;
        $connectionData->scope          = $this->scope;
        $type = (array)json_decode($this->get('type', true) ?? 'null');
        // backward compat: legacy wh_eol -> wh_eol1
        $type = array_map(fn($t) => $t === 'wh_eol' ? ConnectionType::WhEol1->value : $t, $type);
        $connectionData->type           = array_values(array_unique($type));
        $connectionData->updated        = strtotime($this->updated);
        $connectionData->created        = strtotime($this->created);
        $connectionData->eolUpdated     = $this->eolUpdated ? strtotime($this->eolUpdated) : false;

        if( !empty($endpointsData = $this->getEndpointsData()) ){
            $connectionData->endpoints = $endpointsData;
        }

        if($addSignatureData){
            if( !empty($signaturesData = $this->getSignaturesData()) ){
                $connectionData->signatures = $signaturesData;
            }
        }

        if($addLogData){
            if( !empty($logsData = $this->getLogsData()) ){
                $connectionData->logs = $logsData;
            }
        }

        return $connectionData;
    }

    /**
     * setter for connection type
     * @param  $type
     * @return array<string, mixed>
     */
    public function set_type( $type){
        // normalise: map legacy 'wh_eol' to 'wh_eol1', then validate via enum
        // (unknown strings return null from tryFrom and are filtered out)
        // -> reset keys! otherwise JSON format results in object and not in array
        $type = array_values(array_unique(array_filter(array_map(
            function(mixed $t): ?string {
                $s = (string)$t;
                if ($s === 'wh_eol') return ConnectionType::WhEol1->value;
                return ConnectionType::tryFrom($s)?->value;
            },
            (array)$type
        ))));

        // set EOL timestamp per phase transition
        $eolValues = array_map(fn(ConnectionType $c) => $c->value, ConnectionType::eolCases());
        $newEolType = array_values(array_intersect($type, $eolValues));
        $currentEolType = array_values(array_intersect((array)$this->type, $eolValues));
        if(empty($newEolType)){
            $this->eolUpdated = null;
        }elseif($newEolType !== $currentEolType){
            // phase added or changed -> reset per-phase timer
            $this->touch('eolUpdated');
        }

        return $type;
    }

    /**
     * setter for endpoints data (data for source/target endpoint)
     * @param  $endpointsData
     */
    public function set_endpoints( $endpointsData){
        if(!empty($endpointData = (array)($endpointsData['source'] ?? []))){
            $this->setEndpointData('source', $endpointData);
        }
        if(!empty($endpointData = (array)($endpointsData['target'] ?? []))){
            $this->setEndpointData('target', $endpointData);
        }
    }

    /**
     * set connection endpoint related data
     * @param string $label (source||target)
     * @param  $endpointData
     */
    public function setEndpointData(string $label,  $endpointData = []){
        if($this->exists($field = $label . 'EndpointType')){
            $types = empty($types = (array)($endpointData['types'] ?? [])) ? null : $types;
            if($this->$field != $types){
                $this->$field = $types;
            }
        }
    }

    /**
     * check object for model access
     * @param CharacterModel $characterModel
     * @return bool
     */
    public function hasAccess(CharacterModel $characterModel) : bool {
        $access = false;
        if( !$this->dry() ){
            $access = $this->mapId->hasAccess($characterModel);
        }
        return $access;
    }

    /**
     * set default connection scope + type by search route between endpoints
     * @throws \Exception
     */
    public function setAutoScopeAndType(){
        if(
            is_object($this->source) &&
            is_object($this->target)
        ){
            if(
                $this->source->isAbyss() ||
                $this->target->isAbyss()
            ){
                $this->scope = ConnectionType::Abyssal->value;
                $this->type = [ConnectionType::Abyssal->value];
            }elseif(
                $this->source->isKspace() &&
                $this->target->isKspace() &&
                $this->source->systemId !== null &&
                $this->target->systemId !== null &&
                (new Route())->searchRoute($this->source->systemId, $this->target->systemId, 1)['routePossible']
            ){
                $this->scope = ConnectionType::Stargate->value;
                $this->type = [ConnectionType::Stargate->value];
            }else{
                $this->scope = ConnectionType::Wh->value;
                $type = [ConnectionType::WhFresh->value];
                if($defaultMass = $this->defaultMassFromEndpoints()){
                    $type[] = $defaultMass;
                }
                $this->type = $type;
            }
        }
    }

    /**
     * map massIndividual (kg) to a wh_jump_mass_* type using the same thresholds as Init.wormholeSizes
     */
    public static function jumpMassTypeFromMass(?int $massIndividual) : ?string {
        $result = null;
        if($massIndividual !== null){
            foreach(self::JUMP_MASS_BUCKETS as $threshold => $type){
                if($massIndividual >= $threshold){
                    $result = $type;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * replace any existing wh_jump_mass_* with $massType (mutual exclusion)
     * returns the new type array on change, or null if unchanged
     * @return array<string, mixed>
     */
    public function setJumpMassType(string $massType) : ?array {
        if(!in_array($massType, self::JUMP_MASS_TYPES, true)) return null;
        $current = (array)$this->type;
        if(in_array($massType, $current, true)) return null;
        $next = array_values(array_diff($current, self::JUMP_MASS_TYPES));
        $next[] = $massType;
        $this->type = $next;
        return $next;
    }

    /**
     * look up a Universe wormhole typeId and apply the matching mass class to this connection.
     * uses a direct SQL update to avoid AbstractMapTrackingModel::save() requiring a CharacterModel.
     */
    public function applyMassFromWormholeTypeId(int $typeId) : bool {
        if($typeId <= 0 || $this->dry()) return false;
        /** @var Universe\TypeModel $typeModel */
        $typeModel = Universe\AbstractUniverseModel::getNew('TypeModel');
        $typeModel->loadById($typeId);
        if($typeModel->dry()) return false;
        $whData = $typeModel->getWormholeData();
        $massIndividual = isset($whData->massIndividual) ? (int)$whData->massIndividual : null;
        $massType = self::jumpMassTypeFromMass($massIndividual);
        if($massType === null) return false;
        $newType = $this->setJumpMassType($massType);
        if($newType === null) return false;
        // bypass AbstractMapTrackingModel::save() — it requires a CharacterModel and would
        // fail with not-null validation on updatedCharacterId when called without one
        $this->db->exec(
            'UPDATE `' . $this->getTable() . '` SET `type`=? WHERE `id`=?',
            [json_encode($newType), $this->_id]
        );
        return true;
    }

    /**
     * infer default jump-mass class from endpoint system security classes
     * most restrictive endpoint wins: C13 → small, C1 → medium
     */
    private function defaultMassFromEndpoints() : ?string {
        $securities = [];
        if(is_object($this->source)){
            $securities[] = (string)$this->source->security;
        }
        if(is_object($this->target)){
            $securities[] = (string)$this->target->security;
        }
        if(in_array('C13', $securities, true)){
            return ConnectionType::WhJumpMassS->value;
        }
        if(in_array('C1',  $securities, true)){
            return ConnectionType::WhJumpMassM->value;
        }
        return null;
    }

    /**
     * check whether this connection is a wormhole or not
     * @return bool
     */
    public function isWormhole() : bool {
        return ($this->scope === ConnectionType::Wh->value);
    }

    /**
     * check whether this model is valid or not
     * @return bool
     * @throws Exception\DatabaseException
     */
    public function isValid() : bool {
        if($valid = parent::isValid()){
            // check if source/target system are not equal
            // check if source/target belong to same map
            if(
                is_object($this->source) &&
                is_object($this->target) &&
                $this->get('source', true) === $this->get('target', true) ||
                $this->source->get('mapId', true) !== $this->target->get('mapId', true)
            ){
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Event "Hook" function
     * can be overwritten
     * return false will stop any further action
     * @param \Exodus4D\Pathfinder\Model\AbstractModel $self
     * @param array<string, mixed> $pkeys
     * @return bool
     * @throws Exception\DatabaseException
     * @throws \Exception
     */
    public function beforeInsertEvent($self, $pkeys) : bool {
        // check for "default" connection type and add them if missing
        // -> get() with "true" returns RAW data! important for JSON table column check!
        $types = (array)json_decode($this->get('type', true) ?? 'null');
        if(
            !$this->scope ||
            empty($types)
        ){
            $this->setAutoScopeAndType();
        }

        return $this->isValid() ? parent::beforeInsertEvent($self, $pkeys) : false;
    }

    /**
     * Event "Hook" function
     * return false will stop any further action
     * @param self $self
     * @param array<string, mixed> $pkeys
     */
    public function afterInsertEvent($self, $pkeys){
        $self->clearCacheData();
        $self->logActivity('connectionCreate');
    }

    /**
     * Event "Hook" function
     * return false will stop any further action
     * @param self $self
     * @param array<string, mixed> $pkeys
     */
    public function afterUpdateEvent($self, $pkeys){
        $self->clearCacheData();
        $self->logActivity('connectionUpdate');
    }

    /**
     * Event "Hook" function
     * can be overwritten
     * @param self $self
     * @param array<string, mixed> $pkeys
     */
    public function afterEraseEvent($self, $pkeys){
        $self->clearCacheData();
        $self->logActivity('connectionDelete');
    }

    /**
     * @param string $action
     * @return Logging\LogInterface
     * @throws Exception\ConfigException
     */
    public function newLog(string $action = '') : Logging\LogInterface {
        return $this->getMap()->newLog($action)->setTempData($this->getLogObjectData());
    }

    /**
     * @return MapModel
     */
    public function getMap() : MapModel {
        return $this->get('mapId');
    }

    /**
     * delete a connection
     * @param CharacterModel $characterModel
     * @return bool
     */
    public function delete(CharacterModel $characterModel) : bool {
        return ($this->valid() && $this->hasAccess($characterModel)) ? $this->erase() : false;
    }

    /**
     * get object relevant data for model log
     * @return array<string, mixed>
     */
    public function getLogObjectData() : array {
        return [
            'objId' => $this->_id,
            'objName' => $this->scope
        ];
    }

    /**
     * see parent
     */
    public function clearCacheData(): void {
        $this->mapId->clearCacheData();
    }

    /**
     * get all signatures that are connected with this connection
     * @return array|mixed
     */
    public function getSignatures(){
        $signatures = [];
        $this->filter('signatures', [
            'active = :active',
            ':active' => 1
        ]);

        if($this->signatures){
            $signatures = $this->signatures;
        }

        return $signatures;
    }

    /**
     * get all jump logs that are connected with this connection
     * @return array|mixed
     */
    public function getLogs(){
        $logs = [];

        if($this->connectionLog){
            $logs = $this->connectionLog;
        }

        return $logs;
    }

    /**
     * get endpoint data for $type (source || target)
     * @param string $type
     * @return array<string, mixed>
     */
    protected function getEndpointData(string $type) : array {
        $endpointData = [];

        if($this->exists($field = $type . 'EndpointType') && !empty($types = (array)$this->$field)){
            $endpointData['types'] = $types;
        }

        return $endpointData;
    }

    /**
     * get all endpoint data for this connection
     * @return array<string, mixed>
     */
    protected function getEndpointsData() : array {
        $endpointsData = [];

        if(!empty($endpointData = $this->getEndpointData('source'))){
            $endpointsData['source'] = $endpointData;
        }
        if(!empty($endpointData = $this->getEndpointData('target'))){
            $endpointsData['target'] = $endpointData;
        }

        return $endpointsData;
    }

    /**
     * get all signature data linked to this connection
     * @return array<string, mixed>
     */
    public function getSignaturesData() : array {
        $signaturesData = [];
        $signatures = $this->getSignatures();

        foreach($signatures as $signature){
            $signaturesData[] = $signature->getData();
        }

        return $signaturesData;
    }

    /**
     * get all connection log data linked to this connection
     * @return array<string, mixed>
     */
    public function getLogsData() : array {
        $logsData = [];
        $logs = $this->getLogs();

        foreach($logs as $log){
            $logsData[] = $log->getData();
        }

        return $logsData;
    }

    /**
     * get blank connectionLog model
     * @return ConnectionLogModel
     * @throws \Exception
     */
    public function getNewLog() : ConnectionLogModel {
        /**
         * @var ConnectionLogModel $log
         */
        $log = self::getNew('ConnectionLogModel');
        $log->connectionId = $this;
        return $log;
    }

    /**
     * log new mass for this connection
     * @param CharacterLogModel $characterLog
     * @return ConnectionModel
     * @throws \Exception
     */
    public function logMass(CharacterLogModel $characterLog) : self {
        if( !$characterLog->dry() ){
            $log = $this->getNewLog();
            $log->shipTypeId = $characterLog->shipTypeId;
            $log->shipTypeName = $characterLog->shipTypeName;
            $log->shipMass = $characterLog->shipMass;
            $log->characterId = $characterLog->characterId->_id;
            $log->characterName = $characterLog->characterId->name;
            $log->save();
        }

        return $this;
    }

    /**
     * overwrites parent
     * @param null $db
     * @param null $table
     * @param null $fields
     * @return bool
     * @throws \Exception
     */
    public static function setup($db = null, $table = null, $fields = null){
        if($status = parent::setup($db, $table, $fields)){
            $status = parent::setMultiColumnIndex(['source', 'target', 'scope']);
        }
        return $status;
    }
} 