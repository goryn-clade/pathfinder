<?php


namespace Exodus4D\Pathfinder\Model\Universe;

use DB\SQL\Schema;

class SystemNeighbourModel extends AbstractUniverseModel {

    /**
     * @var string
     */
    protected $table                = 'system_neighbour';

    /**
     * allow table truncate
     * -> used on /setup page or index build
     * @var bool
     */
    protected $allowTruncate        = true;

    /**
     * @var array<string, mixed>
     */
    protected $fieldConf = [
        'regionId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Universe\RegionModel::class,
            'constraint' => [
                [
                    'table' => 'region',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'validate' => 'notDry'
        ],
        'constellationId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Universe\ConstellationModel::class,
            'constraint' => [
                [
                    'table' => 'constellation',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'validate' => 'notDry'
        ],
        'systemId' => [
            'type' => Schema::DT_INT,
            'index' => true,
            'unique' => true,
            'belongs-to-one' => \Exodus4D\Pathfinder\Model\Universe\SystemModel::class,
            'constraint' => [
                [
                    'table' => 'system',
                    'on-delete' => 'CASCADE'
                ]
            ],
            'validate' => 'notDry'
        ],
        'systemName' => [
            'type' => Schema::DT_VARCHAR128,
            'nullable' => false,
            'default' => ''
        ],
        'jumpNodes' => [
            'type' => Schema::DT_VARCHAR512,
            'nullable' => false,
            'default' => ''
        ],
        'trueSec' => [
            'type' => Schema::DT_DECIMAL,
            'nullable' => false,
            'default' => 0
        ]
    ];

    /**
     * No static columns added
     * @var bool
     */
    protected $addStaticFields = false;

    /**
     * @param int $id
     * @param string $accessToken
     * @param array<string, mixed> $additionalOptions
     */
    protected function loadData(int $id, string $accessToken = '', array $additionalOptions = []){}
}