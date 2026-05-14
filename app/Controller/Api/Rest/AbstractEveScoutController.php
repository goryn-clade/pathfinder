<?php


namespace Exodus4D\Pathfinder\Controller\Api\Rest;

use Exodus4D\Pathfinder\Controller\Ccp\Universe;
use Exodus4D\Pathfinder\Enum\ConnectionType;
use Exodus4D\Pathfinder\Lib\Config;

abstract class AbstractEveScoutController extends AbstractRestController {

    /**
     * EVE system ID for the source system (e.g. Thera, Turnur)
     */
    const SOURCE_SYSTEM_ID = 0;

    /**
     * Cache key for HTTP response
     */
    const CACHE_KEY = '';

    /**
     * @param \Base $f3
     */
    public function get(\Base $f3): void{
        $ttl = 60 * 3;
        if(!$exists = $f3->exists(static::CACHE_KEY, $connectionsData)){
            $connectionsData = $this->getEveScoutConnections();
            $f3->set(static::CACHE_KEY, $connectionsData, $ttl);
        }

        $f3->expire(Config::ttlLeft($exists, $ttl));

        $this->out($connectionsData);
    }

    /**
     * @return array
     */
    protected function getEveScoutConnections() : array {
        $connectionsData = [];

        $enrichWithSystemData = function(string $key,  $eveScoutConnection, array &$connectionData) : void {
            $eveScoutSystem = (array)$eveScoutConnection[$key];
            $universe = new Universe();
            $staticData = $universe->getSystemData($eveScoutSystem['id']);
            if($staticData === null){
                throw new \RuntimeException('System data not found for id: ' . $eveScoutSystem['id']);
            }

            $connectionData[$key] = [
                'id' => (int)$staticData->id,
                'name' => (string)$staticData->name,
                'system_class' => round((float)$staticData->trueSec, 4),
                'constellation' => ['id' => (int)$staticData->constellation->id],
                'region' => [
                    'id' => (int)$staticData->constellation->region->id,
                    'name' => (string)$staticData->constellation->region->name
                ]
            ];
        };

        $enrichWithSignatureData = function(string $key,  $eveScoutConnection, array &$connectionData) : void {
            $eveScoutSignature = (array)$eveScoutConnection[$key];
            $signatureData = [
                'name' => ($eveScoutSignature['name'] ?? null) ?: null,
                'short_name' => str_split((string)($eveScoutSignature['name'] ?? ''), 3)[0] ?: null
            ];
            if($key == 'sourceSignature' && ($eveScoutConnection['wh_exits_outward'] ?? false)) {
                $signatureData['type'] = ['name' => strtoupper((string)($eveScoutConnection['wh_type'] ?? ''))];
            }
            if($key == 'targetSignature' && !($eveScoutConnection['wh_exits_outward'] ?? false)) {
                $signatureData['type'] = ['name' => strtoupper((string)($eveScoutConnection['wh_type'] ?? ''))];
            }
            $connectionData[$key] = $signatureData;
        };

        $enrichWithWormholeData = function( $wormholeData, array &$connectionsData) : void {
            $type = [ConnectionType::WhFresh->value];

            $estimatedEol = $wormholeData['estimatedEol'] ?? null;
            if($estimatedEol !== null && $estimatedEol <= 4){
                if($estimatedEol > 1){
                    $type[] = ConnectionType::WhEol1->value;
                }elseif($estimatedEol > 0){
                    $type[] = ConnectionType::WhEol2->value;
                }else{
                    $type[] = ConnectionType::WhEol3->value;
                }
            }
            switch($wormholeData['jumpMass'] ?? '') {
                case 'capital':
                case 'xlarge':
                    $type[] = ConnectionType::WhJumpMassXl->value;
                    break;
                case 'large':
                    $type[] = ConnectionType::WhJumpMassL->value;
                    break;
                case 'medium':
                    $type[] = ConnectionType::WhJumpMassM->value;
                    break;
                case 'small':
                    $type[] = ConnectionType::WhJumpMassS->value;
                    break;
            }

            $connectionsData['type'] = $type;
            $connectionsData['estimatedEol'] = $wormholeData['estimatedEol'];
        };

        $eveScoutResponse = $this->getF3()->eveScoutClient()->send('getTheraConnections');
        if(!empty($eveScoutResponse) && !isset($eveScoutResponse['error'])){
            foreach((array)$eveScoutResponse['connections'] as $eveScoutConnection){
                if(
                    $eveScoutConnection['type'] === 'wormhole' &&
                    isset($eveScoutConnection['source']) && isset($eveScoutConnection['target']) &&
                    $eveScoutConnection['source']['id'] === static::SOURCE_SYSTEM_ID
                ){
                    try{
                        $data = [
                            'id' => (int)$eveScoutConnection['id'],
                            'scope' => ConnectionType::Wh->value,
                            'created' => [
                                'created' => (new \DateTime($eveScoutConnection['created']))->getTimestamp(),
                                'character' => (array)$eveScoutConnection['character']
                            ],
                            'updated' => (new \DateTime($eveScoutConnection['updated']))->getTimestamp()
                        ];
                        $enrichWithWormholeData((array)$eveScoutConnection['wormhole'], $data);
                        $enrichWithSystemData('source', $eveScoutConnection, $data);
                        $enrichWithSystemData('target', $eveScoutConnection, $data);
                        $enrichWithSignatureData('sourceSignature', $eveScoutConnection, $data);
                        $enrichWithSignatureData('targetSignature', $eveScoutConnection, $data);
                        $connectionsData[] = $data;
                    }catch(\Exception){
                        // DateTime parse failure -> skip connection
                    }
                }
            }
        }

        return $connectionsData;
    }
}
