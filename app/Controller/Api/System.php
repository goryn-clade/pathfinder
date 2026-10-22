<?php
/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 08.02.15
 * Time: 20:23
 */

namespace Exodus4D\Pathfinder\Controller\Api;

use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Controller;
use Exodus4D\Pathfinder\Model\Pathfinder;

class System extends Controller\AccessController {

    /**
     * set destination for system, station or structure
     * @param \Base $f3
     * @throws \Exception
     */
    public function setDestination(\Base $f3): void{
        $postData = (array)$f3->get('POST');

        $return = (object) [];
        $return->error = [];
        $return->destData = [];

        if(!empty($destData = (array)($postData['destData'] ?? []))){
            $activeCharacter = $this->getCharacter();

            if($activeCharacter && ($accessToken = $activeCharacter->getAccessToken())){
                $return->clearOtherWaypoints = (bool)($postData['clearOtherWaypoints'] ?? false);
                $return->first = (bool)($postData['first'] ?? false);
                $options = [
                    'clearOtherWaypoints' => $return->clearOtherWaypoints,
                    'addToBeginning' => $return->first,
                ];

                foreach($destData as $data){
                    $response =  $f3->ccpClient()->send('setWaypoint', (int)$data['id'], $accessToken, $options);

                    if(empty($response)){
                        $return->destData[] = $data;
                    }else{
                        $error = (object) [];
                        $error->type = 'error';
                        $error->text = $response['error'] ?? '';
                        $return->error[] = $error;
                    }
                }

            }
        }

        echo json_encode($return);
    }

    /**
     * send Rally Point poke
     * @param \Base $f3
     * @throws \Exception
     */
    public function pokeRally(\Base $f3): void{
        $rallyData = (array)$f3->get('POST');
        $systemId = (int)($rallyData['systemId'] ?? 0);
        $return = (object) [];

        if($systemId){
            $activeCharacter = $this->getCharacter();

            /**
             * @var Pathfinder\SystemModel $system
             */
            $system = Pathfinder\AbstractPathfinderModel::getNew('SystemModel');
            $system->getById($systemId);

            if($system->hasAccess($activeCharacter)){
                $rallyData['pokeDesktop']   = ($rallyData['pokeDesktop'] ?? '0') === '1';
                $rallyData['pokeMail']      = ($rallyData['pokeMail'] ?? '0') === '1';
                $rallyData['pokeSlack']     = ($rallyData['pokeSlack'] ?? '0') === '1';
                $rallyData['pokeDiscord']   = ($rallyData['pokeDiscord'] ?? '0') === '1';
                $rallyData['message']       = trim((string)($rallyData['message'] ?? ''));

                $system->sendRallyPoke($rallyData, $activeCharacter);
            }
        }

        echo json_encode($return);
    }

}

