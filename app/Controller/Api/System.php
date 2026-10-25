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
     * default max rally pokes per account (user) within RALLY_POKE_PERIOD_DEFAULT
     * -> used if RALLY_POKE_LIMIT is missing in pathfinder.ini
     */
    const RALLY_POKE_LIMIT_DEFAULT              = 5;

    /**
     * default rally poke limit window (seconds)
     * -> used if RALLY_POKE_PERIOD is missing in pathfinder.ini
     */
    const RALLY_POKE_PERIOD_DEFAULT             = 300;

    /**
     * Redis key prefix for rally poke counters (+ userId)
     * -> per account, so all characters of one user share one counter
     */
    const RALLY_POKE_KEY_PREFIX                 = 'pathfinder:rally_poke:user:';

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

                // only pokes that would be sent count against the limit -> a rally point without poke is not limited
                $hasPoke = !empty($system->getRallyPokeHandlers($rallyData));

                if($hasPoke && ($retryIn = $this->checkRallyPokeLimit($f3, (int)$activeCharacter?->getUser()?->_id))){
                    $error = (object) [];
                    $error->type = 'warning';
                    $error->title = 'Rally poke limit';
                    $error->text = 'Rally poke limit reached, try again in ' . self::formatRetryIn($retryIn) .
                        '. The rally point is set, but no Slack or Discord poke was sent.';
                    $return->error[] = $error;
                }else{
                    $system->sendRallyPoke($rallyData, $activeCharacter);
                }
            }
        }

        echo json_encode($return);
    }

    /**
     * count a rally poke for an account (user) against RALLY_POKE_LIMIT per RALLY_POKE_PERIOD
     * -> fixed window counter in Redis (CACHE backend), keyed by userId
     * -> no limit if CACHE is not Redis
     * -> fails open: pokes are allowed (warning logged) if Redis is not available
     * @param \Base $f3
     * @param int $userId
     * @return int seconds until the next poke is allowed, 0 if this poke is allowed
     */
    protected function checkRallyPokeLimit(\Base $f3, int $userId) : int {
        $limit = Config::getPathfinderData('notification.rally_poke_limit');
        $limit = is_numeric($limit) ? (int)$limit : self::RALLY_POKE_LIMIT_DEFAULT;
        $period = Config::getPathfinderData('notification.rally_poke_period');
        $period = is_numeric($period) ? (int)$period : self::RALLY_POKE_PERIOD_DEFAULT;

        if($limit <= 0 || $period <= 0 || $userId <= 0){
            return 0;
        }

        // no Redis CACHE backend -> no limit (see pathfinder.ini)
        if(
            !Config::parseDSN((string)$f3->get('CACHE'), $conf) ||
            ($conf['type'] ?? null) !== 'redis' ||
            !extension_loaded('redis')
        ){
            return 0;
        }

        try{
            $client = new \Redis();
            if(!$client->pconnect(
                (string)$conf['host'],
                (int)$conf['port'],
                Config::REDIS_OPT_TIMEOUT,
                null,
                Config::REDIS_OPT_RETRY_INTERVAL,
                Config::REDIS_OPT_READ_TIMEOUT
            )){
                throw new \RedisException('connect failed');
            }
            if(!empty($conf['auth'])){
                $client->auth($conf['auth']);
            }
            if(isset($conf['db'])){
                $client->select((int)$conf['db']);
            }

            $key = self::RALLY_POKE_KEY_PREFIX . $userId;
            $count = (int)$client->incr($key);
            $ttl = (int)$client->ttl($key);
            if($ttl < 0){
                // new window (or key without expiry) -> start window
                $client->expire($key, $period);
                $ttl = $period;
            }

            return $count > $limit ? max(1, $ttl) : 0;
        }catch(\RedisException $e){
            self::getLogger('ERROR')->write(sprintf(
                'Rally poke limit not checked, poke allowed (userId: %d): Redis unavailable: %s',
                $userId,
                $e->getMessage()
            ));
        }

        return 0;
    }

    /**
     * format seconds as "N minutes" (or "N seconds" below 1 minute)
     * @param int $seconds
     * @return string
     */
    protected static function formatRetryIn(int $seconds) : string {
        if($seconds < 60){
            return $seconds . ($seconds === 1 ? ' second' : ' seconds');
        }
        $minutes = (int)ceil($seconds / 60);
        return $minutes . ($minutes === 1 ? ' minute' : ' minutes');
    }

}
