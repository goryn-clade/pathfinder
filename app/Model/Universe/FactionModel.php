<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 30.03.2019
 * Time: 09:51
 */

namespace Exodus4D\Pathfinder\Model\Universe;


use DB\SQL\Schema;


class FactionModel extends AbstractUniverseModel {

    /**
     * @var string
     */
    protected $table = 'faction';

    /**
     * @var array<string, mixed>
     */
    protected $fieldConf = [
        'name' => [
            'type' => Schema::DT_VARCHAR128,
            'nullable' => false,
            'default' => ''
        ],
        'description' => [
            'type' => Schema::DT_TEXT
        ],
        'sizeFactor' => [
            'type' => Schema::DT_INT,
            'nullable' => false,
            'default' => 0
        ],
        'stationCount' => [
            'type' => Schema::DT_INT,
            'nullable' => false,
            'default' => 0
        ],
        'stationSystemCount' => [
            'type' => Schema::DT_INT,
            'nullable' => false,
            'default' => 0
        ],
        'race' => [ // faction API endpoint dont have "raceId" data, but race API endpoint has
            'has-one' => [\Exodus4D\Pathfinder\Model\Universe\RaceModel::class, 'factionId']
        ],
        'alliances' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Universe\AllianceModel::class, 'factionId']
        ],
        'corporations' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Universe\CorporationModel::class, 'factionId']
        ],
        'sovereigntySystems' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Universe\SovereigntyMapModel::class, 'factionId']
        ],
        'factionWarSystemOwners' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Universe\FactionWarSystemModel::class, 'ownerFactionId']
        ],
        'factionWarSystemOccupiers' => [
            'has-many' => [\Exodus4D\Pathfinder\Model\Universe\FactionWarSystemModel::class, 'occupierFactionId']
        ]
    ];

    /**
     * get data
     * @return \stdClass
     */
    public function getData(){
        $factionData                = (object) [];
        $factionData->id            = $this->_id;
        $factionData->name          = $this->name;

        return $factionData;
    }

    /**
     * @param int $id
     * @param string $accessToken
     * @param array<string, mixed> $additionalOptions
     */
    protected function loadData(int $id, string $accessToken = '', array $additionalOptions = []): void {
        $data = self::getF3()->ccpClient()->send('getUniverseFaction', $id);
        if(!empty($data) && !isset($data['error'])){
            $this->copyfrom($data, ['id', 'name', 'description', 'sizeFactor', 'stationCount', 'stationSystemCount']);
            $this->save();
        }
    }
}