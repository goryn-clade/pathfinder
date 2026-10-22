<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 19.05.2015
 * Time: 20:14
 */

namespace Exodus4D\Pathfinder\Model\Pathfinder;

use DB\SQL\Schema;

class AllianceMapModel extends AbstractPathfinderModel {

    /**
     * @var string
     */
    protected $table = 'alliance_map';

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
        'allianceId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Pathfinder\AllianceModel::class,
            'constraint' => [
                [
                    'table' => 'alliance',
                    'on-delete' => 'CASCADE'
                ]
            ]
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
        ]
    ];

    /**
     * see parent
     */
    #[\Override]
    public function clearCacheData(): void{
        // clear map cache
        $this->mapId->clearCacheData();
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
            $status = parent::setMultiColumnIndex(['allianceId', 'mapId'], true);
        }
        return $status;
    }
}