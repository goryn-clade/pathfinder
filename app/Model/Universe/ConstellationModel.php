<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 29.07.2017
 * Time: 16:49
 */

namespace Exodus4D\Pathfinder\Model\Universe;

use DB\SQL\Schema;

class ConstellationModel extends AbstractUniverseModel {

    /**
     * @var string
     */
    protected $table = 'constellation';

    /**
     * @var array
     */
    protected $fieldConf = [
        'name' => [
            'type' => Schema::DT_VARCHAR128,
            'nullable' => false,
            'default' => ''
        ],
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
        'x' => [
            'type' => Schema::DT_BIGINT,
            'nullable' => false,
            'default' => 0
        ],
        'y' => [
            'type' => Schema::DT_BIGINT,
            'nullable' => false,
            'default' => 0
        ],
        'z' => [
            'type' => Schema::DT_BIGINT,
            'nullable' => false,
            'default' => 0
        ],
        'systems' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Universe\SystemModel::class, 'constellationId']
        ],
        'systemNeighbours' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Universe\SystemNeighbourModel::class, 'constellationId']
        ]
    ];

    /**
     * get data
     * @return \stdClass
     */
    public function getData(){
        $constellationData          = (object) [];
        $constellationData->id      = $this->_id;
        $constellationData->name    = $this->name;
        $constellationData->region  = $this->regionId->getData();

        return $constellationData;
    }

    /**
     * @param int $id
     * @param string $accessToken
     * @param array $additionalOptions
     */
    protected function loadData(int $id, string $accessToken = '', array $additionalOptions = []){
        $data = self::getF3()->ccpClient()->send('getUniverseConstellation', $id);
        if(!empty($data)){
            /**
             * @var RegionModel $region
             */
            $region = $this->rel('regionId');
            $region->loadById($data['regionId'], $accessToken, $additionalOptions);
            $data['regionId'] = $region;

            $this->copyfrom($data, ['id', 'name', 'regionId', 'position']);
            $this->save();
        }
    }

    /**
     * load systems data for this constellation
     */
    public function loadSystemsData(): void{
        if( !$this->dry() ){
            $data = self::getF3()->ccpClient()->send('getUniverseConstellation', $this->_id);
            if(!empty($data)){
                foreach((array)$data['systems'] as $systemId){
                    /**
                     * @var SystemModel $system
                     */
                    $system = $this->rel('systems');
                    $system->loadById($systemId);
                    $system->reset();
                }
            }
        }
    }

}