<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 21.03.15
 * Time: 14:34
 */

namespace Exodus4D\Pathfinder\Model\Pathfinder;

use DB\SQL\Schema;
use Exodus4D\Pathfinder\Lib\Logging;
use Exodus4D\Pathfinder\Exception;

class SystemSignatureModel extends AbstractMapTrackingModel {

    /**
     * @var string
     */
    protected $table = 'system_signature';

    /**
     * @var array
     */
    protected $fieldConf = [
        'active' => [
            'type' => Schema::DT_BOOL,
            'nullable' => false,
            'default' => 1,
            'index' => true
        ],
        'systemId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Pathfinder\SystemModel::class,
            'constraint' => [
                [
                    'table' => 'system',
                    'on-delete' => 'CASCADE'
                ]
            ]
        ],
        'groupId' => [
            'type' => Schema::DT_INT,
            'nullable' => false,
            'default' => 0,
            'index' => true,
            'activity-log' => true
        ],
        'typeId' => [
            'type' => Schema::DT_INT,
            'nullable' => false,
            'default' => 0,
            'index' => true,
            'activity-log' => true
        ],
        'connectionId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Pathfinder\ConnectionModel::class,
            'constraint' => [
                [
                    'table' => 'connection',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'activity-log' => true
        ],
        'name' => [
            'type' => Schema::DT_VARCHAR128,
            'nullable' => false,
            'default' => '',
            'activity-log' => true,
            'validate' => true
        ],
        'description' => [
            'type' => Schema::DT_VARCHAR512,
            'nullable' => false,
            'default' => '',
            'activity-log' => true
        ]
    ];

    /**
     * set data by associative array
     * @param  $data
     */
    public function setData( $data): void{
        $this->copyfrom($data, ['name', 'groupId', 'typeId', 'description', 'connectionId']);
    }

    /**
     * get signature data
     * @return \stdClass
     */
    public function getData(){
        $signatureData                              = (object) [];
        $signatureData->id                          = $this->id;

        $signatureData->system                      = (object) [];
        $signatureData->system->id                  = $this->get('systemId', true);

        $signatureData->groupId                     = $this->groupId;
        $signatureData->typeId                      = $this->typeId;
        $signatureData->name                        = $this->name;
        $signatureData->description                 = $this->description;

        if($connection = $this->getConnection()){
            $signatureData->connection              = (object) [];
            $signatureData->connection->id          = $connection->_id;
        }

        $signatureData->created                     = (object) [];
        $signatureData->created->created            = strtotime($this->created);
        if( is_object($this->createdCharacterId) ){
            $signatureData->created->character      = $this->createdCharacterId->getBasicData();
        }

        $signatureData->updated                     = (object) [];
        $signatureData->updated->updated            = strtotime($this->updated);
        if( is_object($this->updatedCharacterId) ){
            $signatureData->updated->character      = $this->updatedCharacterId->getBasicData();
        }

        return $signatureData;
    }

    /**
     * setter for connectionId
     * @param ConnectionModel|int $connectionId
     * @return int|null
     */
    public function set_connectionId(ConnectionModel|int|null $connectionId){
        $connectionId = (int)$connectionId;
        $validConnectionId = null;

        if($connectionId > 0){
            // check if connectionId is valid
            $systemId = (int) $this->get('systemId', true);

            /**
             * @var ConnectionModel $connection
             */
            $connection = $this->rel('connectionId');
            $connection->getById($connectionId);

            if(
                !$connection->dry() &&
                (
                    $connection->get('source', true) === $systemId||
                    $connection->get('target', true) === $systemId
                )
            ){
                // connectionId belongs to same system as $this signature -> is valid
                $validConnectionId = $connectionId;
            }
        }

        return $validConnectionId;
    }

    /**
     * validate name column
     * @param string $key
     * @param string $val
     * @return bool
     * @throws Exception\ValidationException
     */
    protected function validate_name(string $key, string $val): bool {
        $valid = true;
        if(!mb_ereg('^[a-zA-Z]{3}-\d{3}$', $val)){
            $valid = false;
            $this->throwValidationException($key);
        }
        return $valid;
    }

    /**
     * @param string $action
     * @return Logging\LogInterface
     * @throws Exception\ConfigException
     */
    #[\Override]
    public function newLog(string $action = ''): Logging\LogInterface{
        return $this->getMap()->newLog($action)->setTempData($this->getLogObjectData());
    }

    /**
     * @return MapModel
     */
    public function getMap(): MapModel{
        return $this->get('systemId')->getMap();
    }

    /**
     * get the connection (if attached)
     * @return ConnectionModel|null
     */
    public function getConnection(){
        return $this->connectionId;
    }

    /**
     * compares a new data set (array) with the current values
     * and checks if something has changed
     * @param  $signatureData
     * @return bool
     */
    public function hasChanged( $signatureData) : bool {
        $hasChanged = false;

        foreach((array)$signatureData as $key => $value){
            if($this->exists($key)){
                if($this->$key instanceof ConnectionModel){
                    $currentValue = $this->get($key, true);
                }else{
                    $currentValue = $this->$key;
                }

                $hasChanged = $currentValue !== $value;
                break;
            }
        }

        return $hasChanged;
    }

    /**
     * check object for model access
     * @param CharacterModel $characterModel
     * @return bool
     */
    #[\Override]
    public function hasAccess(CharacterModel $characterModel) : bool {
        return $this->systemId ? $this->systemId->hasAccess($characterModel) : false;
    }

    /**
     * delete signature
     * @return bool
     */
    public function delete() : bool {
        return $this->valid() ? $this->erase() : false;
    }

    /**
     * Event "Hook" function
     * return false will stop any further action
     * @param self $self
     * @param array $pkeys
     */
    public function afterInsertEvent($self, $pkeys): void{
        $self->logActivity('signatureCreate');
        $self->syncConnectionMass();
    }

    /**
     * Event "Hook" function
     * can be overwritten
     * return false will stop any further action
     * @param self $self
     * @param array $pkeys
     * @return bool
     */
    #[\Override]
    public function beforeUpdateEvent($self, $pkeys) : bool {
        // "updated" column should always be updated if no changes made this signature
        // -> makes it easier to see what signatures have not been updated
        $this->touch('updated');

        // Capture effective typeId now, while the mapper's 'initial' values (pre-save DB state)
        // are still available. After mapper->update() fires, initial is reset to the new value.
        // This handles the common case where the client sets connId and clears typeId to 0
        // in the same request (e.g. linking a connection resets the type dropdown).
        $connId  = (int)$this->get('connectionId', true);
        $typeId  = (int)$this->typeId;
        if($connId > 0 && $typeId === 0){
            $schema = $this->getMapper()->schema();
            $typeId = isset($schema['typeId']) ? (int)$schema['typeId']['initial'] : 0;
        }
        $this->virtual('_syncTypeId', $typeId);

        return parent::beforeUpdateEvent($self, $pkeys);
    }

    /**
     * Event "Hook" function
     * return false will stop any further action
     * @param self $self
     * @param array $pkeys
     */
    public function afterUpdateEvent($self, $pkeys): void{
        $self->logActivity('signatureUpdate');
        $self->syncConnectionMass();
    }

    /**
     * when a wormhole signature with a linked connection gets/changes its typeId,
     * push the corresponding jump-mass class onto the connection
     */
    private function syncConnectionMass() : void {
        $connId  = (int)$this->get('connectionId', true);
        // use effective typeId captured in beforeUpdateEvent (handles client clearing typeId to 0
        // when linking a connection in the same request). get() checks vFields; $this->_syncTypeId
        // cannot be used because __isset() returns false for virtual fields (exists() checks DB only)
        $typeId  = (int)$this->get('_syncTypeId') ?: (int)$this->typeId;
        $groupId = (int)$this->groupId;
        if($connId <= 0 || $typeId <= 0){
            return;
        }
        if($groupId !== 5){
            return;
        }
        $connection = $this->getConnection();
        if($connection && !$connection->dry() && $connection->isWormhole()){
            $connection->applyMassFromWormholeTypeId($typeId);
        }
    }

    /**
     * Event "Hook" function
     * can be overwritten
     * @param self $self
     * @param array $pkeys
     */
    public function afterEraseEvent($self, $pkeys): void{
        $self->logActivity('signatureDelete');

        if(
            $self->connectionIdDeleteCascade === true &&
            ($connection = $self->getConnection())
        ){
            $connection->erase();
        }
    }

    /**
     * get object relevant data for model log
     * @return array
     */
    public function getLogObjectData() : array{
        return [
            'objId' => $this->_id,
            'objName' => $this->name
        ];
    }

    /**
     * overwrites parent
     * @param null $db
     * @param null $table
     * @param null $fields
     * @return bool
     * @throws \Exception
     */
    #[\Override]
    public static function setup($db = null, $table = null, $fields = null){
        if($status = parent::setup($db, $table, $fields)){
            $status = parent::setMultiColumnIndex(['systemId', 'typeId', 'groupId']);
        }
        return $status;
    }
}
