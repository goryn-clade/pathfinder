<?php


namespace Exodus4D\Pathfinder\Controller\Api\Rest;

use Exodus4D\Pathfinder\Controller\Ccp\Universe;
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
    public function get(\Base $f3){
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
            $type = ['wh_fresh'];

            $estimatedEol = $wormholeData['estimatedEol'] ?? null;
            if($estimatedEol !== null && $estimatedEol <= 4){
                if($estimatedEol > 1){
                    $type[] = 'wh_eol1';    // Phase 1 (aging): 1–4h remaining
                }elseif($estimatedEol > 0){
                    $type[] = 'wh_eol2';    // Phase 2 (expiring): 0–1h remaining
                }else{
                    $type[] = 'wh_eol3';    // Phase 3 (zombie): past natural end
                }
            }
            switch($wormholeData['jumpMass'] ?? '') {
                case 'capital':
                case 'xlarge':
                    $type[] = 'wh_jump_mass_xl';
                    break;
                case 'large':
                    $type[] = 'wh_jump_mass_l';
                    break;
                case 'medium':
                    $type[] = 'wh_jump_mass_m';
                    break;
                case 'small':
                    $type[] = 'wh_jump_mass_s';
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
                            'scope' => 'wh',
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
