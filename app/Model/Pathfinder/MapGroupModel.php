<?php

namespace Exodus4D\Pathfinder\Model\Pathfinder;

use DB\SQL\Schema;
use Exodus4D\Pathfinder\Lib\Logging;
use Exodus4D\Pathfinder\Exception;

class MapGroupModel extends AbstractMapTrackingModel {

    protected $table = 'map_group';

    protected $fieldConf = [
        'active' => [
            'type'     => Schema::DT_BOOL,
            'nullable' => false,
            'default'  => 1,
            'index'    => true,
            'activity-log' => true
        ],
        'mapId' => [
            'type'         => Schema::DT_INT,
            'index'        => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Pathfinder\MapModel::class,
            'constraint'   => [['table' => 'map', 'on-delete' => 'CASCADE']],
            'validate'     => 'notDry'
        ],
        'label' => [
            'type'     => Schema::DT_VARCHAR128,
            'nullable' => false,
            'default'  => '',
            'activity-log' => true,
            'validate' => true
        ],
        'posX' => [
            'type'     => Schema::DT_INT,
            'nullable' => false,
            'default'  => 0
        ],
        'posY' => [
            'type'     => Schema::DT_INT,
            'nullable' => false,
            'default'  => 0
        ],
        'width' => [
            'type'     => Schema::DT_INT,
            'nullable' => false,
            'default'  => 300
        ],
        'height' => [
            'type'     => Schema::DT_INT,
            'nullable' => false,
            'default'  => 200
        ],
        'isCollapsed' => [
            'type'     => Schema::DT_BOOL,
            'nullable' => false,
            'default'  => 0,
            'activity-log' => true
        ],
        'constrain' => [
            'type'     => Schema::DT_BOOL,
            'nullable' => false,
            'default'  => 0,
            'activity-log' => true
        ],
        'groupSystems' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Pathfinder\SystemModel::class, 'groupId']
        ]
    ];

    protected function validate_label(string $key, string $val) : bool {
        $valid = true;
        if(mb_strlen(trim($val)) < 1){
            $valid = false;
            $this->throwValidationException($key);
        }
        return $valid;
    }

    public function getData() : \stdClass {
        $data = (object)[];
        $data->id          = $this->_id;
        $data->label       = $this->label;
        $data->posX        = (int)$this->posX;
        $data->posY        = (int)$this->posY;
        $data->width       = (int)$this->width;
        $data->height      = (int)$this->height;
        $data->isCollapsed = (bool)$this->isCollapsed;
        $data->constrain   = (bool)$this->constrain;
        $data->updated     = (object)[];
        $data->updated->updated = strtotime($this->updated);
        return $data;
    }

    #[\Override]
    public function hasAccess(CharacterModel $characterModel) : bool {
        return $this->mapId ? $this->mapId->hasAccess($characterModel) : false;
    }

    /**
     * @param array<string, mixed> $pkeys
     */
    public function afterInsertEvent($self, $pkeys){
        $self->clearCacheData();
        $self->logActivity('groupCreate');
    }

    /**
     * @param array<string, mixed> $pkeys
     */
    public function afterUpdateEvent($self, $pkeys){
        $self->clearCacheData();
        $self->logActivity('groupUpdate');
    }

    #[\Override]
    /**
     * @param array<string, mixed> $pkeys
     */
    public function beforeEraseEvent($self, $pkeys) : bool {
        // nullify groupId on all child systems (replaces DB-level ON DELETE SET NULL)
        if($systems = $self->groupSystems){
            $character = $self->updatedCharacterId;
            foreach($systems as $system){
                $system->groupId = null;
                $system->save($character);
            }
        }
        return parent::beforeEraseEvent($self, $pkeys);
    }

    /**
     * @param array<string, mixed> $pkeys
     */
    public function afterEraseEvent($self, $pkeys){
        $self->clearCacheData();
        $self->logActivity('groupDelete');
    }

    #[\Override]
    public function newLog(string $action = '') : Logging\LogInterface {
        return $this->getMap()->newLog($action);
    }

    public function getMap() : MapModel {
        return $this->get('mapId');
    }

    #[\Override]
    public function clearCacheData(): void {
        parent::clearCacheData();
        if($this->mapId){
            $this->mapId->clearCacheData();
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogObjectData(): array {
        return [
            'objId'   => $this->_id,
            'objName' => $this->label
        ];
    }

    #[\Override]
    public function getLogData(): array {
        return [];
    }
}
