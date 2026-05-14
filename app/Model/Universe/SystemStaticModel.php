<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 18.05.2018
 * Time: 17:50
 */

namespace Exodus4D\Pathfinder\Model\Universe;

use DB\SQL\Schema;

class SystemStaticModel extends AbstractUniverseModel {

    /**
     * @var string
     */
    protected $table = 'system_static';

    /**
     * @var array<string, mixed>
     */
    protected $fieldConf = [
        'systemId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Universe\SystemModel::class,
            'constraint' => [
                [
                    'table' => 'system',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'validate' => 'notDry'
        ],
        'typeId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Universe\TypeModel::class,
            'constraint' => [
                [
                    'table' => 'type',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'validate' => 'notDry'
        ]
    ];

    /**
     * No static columns added
     * @var bool
     */
    protected $addStaticFields = false;

    /**
     * get static data
     * @return null|string
     */
    public function getData(){
        return $this->typeId ? $this->typeId->getWormholeName() : null;
    }

    /**
     * @param int $id
     * @param string $accessToken
     * @param array<string, mixed> $additionalOptions
     */
    protected function loadData(int $id, string $accessToken = '', array $additionalOptions = []): void {}

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
            $status = parent::setMultiColumnIndex(['systemId', 'typeId'], true);
        }
        return $status;
    }
}