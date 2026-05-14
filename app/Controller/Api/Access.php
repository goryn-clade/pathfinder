<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 24.05.2015
 * Time: 17:42
 */

namespace Exodus4D\Pathfinder\Controller\Api;

use Exodus4D\Pathfinder\Controller;
use Exodus4D\Pathfinder\Model\Pathfinder;

class Access extends Controller\AccessController {


    /**
     * search character/corporation or alliance by name
     * @param \Base $f3
     * @param $params
     * @throws \Exception
     */
    public function search(\Base $f3,  $params): void{
        $accessData = [];

        if(
            array_key_exists('arg1', $params) &&
            array_key_exists('arg2', $params)
        ){
            $searchType = strtolower((string) $params['arg1']);
            $searchToken = strtolower((string) $params['arg2']);
            // strip LIKE wildcards so the user cannot widen the match arbitrarily
            $searchToken = str_replace(['%', '_'], ['\\%', '\\_'], $searchToken);

            $accessModel = null;
            switch($searchType){
                case 'character':
                    $accessModel = Pathfinder\AbstractPathfinderModel::getNew('CharacterModel');
                    break;
                case 'corporation':
                    $accessModel = Pathfinder\AbstractPathfinderModel::getNew('CorporationModel');
                    break;
                case 'alliance':
                    $accessModel = Pathfinder\AbstractPathfinderModel::getNew('AllianceModel');
                    break;
            }

            if( is_object($accessModel) ){

                // find "active" entries that have their "sharing" option activated
                $accessList = $accessModel->find( [
                    "LOWER(name) LIKE :token AND " .
                    "active = 1 AND " .
                    "shared = 1 ",
                    ':token' => '%' . $searchToken . '%'
                ]);

                if($accessList){
                    foreach($accessList as $accessObject){
                        $accessData[] = $accessObject->getData();
                    }
                }
            }
        }

        echo json_encode($accessData);
    }

}