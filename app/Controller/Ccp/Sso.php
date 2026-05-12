<?php /** @noinspection PhpUndefinedMethodInspection */

/**
 * Created by PhpStorm.
 * User: Exodus
 * Date: 23.01.2016
 * Time: 17:18
 *
 * Handles access to EVE-Online "ESI API" and "SSO" oAuth 2.0 functions
 * - Add your API credentials in "environment.ini"
 * - Check "PATHFINDER.API" in "pathfinder.ini" for correct API URLs
 */

namespace Exodus4D\Pathfinder\Controller\Ccp;

use Exodus4D\Pathfinder\Controller;
use Exodus4D\Pathfinder\Controller\Api as Api;
use Exodus4D\Pathfinder\Model\Pathfinder;
use Exodus4D\Pathfinder\Lib;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class Sso extends Api\User{

    /**
     * @var int timeout (seconds) for API calls
     */
    const SSO_TIMEOUT                               = 4;

    /**
     * SSO endpoints are pre-authentication by definition — skip the Api\User auth guard.
     */
    public function beforeroute(\Base $f3, $params): bool {
        return Controller\Controller::beforeroute($f3, $params);
    }

    // SSO specific session keys
    const SESSION_KEY_SSO                           = 'SESSION.SSO';
    const SESSION_KEY_SSO_ERROR                     = 'SESSION.SSO.ERROR';
    const SESSION_KEY_SSO_STATE                     = 'SESSION.SSO.STATE';
    const SESSION_KEY_SSO_FROM                      = 'SESSION.SSO.FROM';

    // F3 cache key for CCP JWKS — avoids fetching on every login callback
    const JWKS_CACHE_KEY                            = 'sso_jwks_keyset';
    const JWKS_CACHE_TTL                            = 3600;

    // error messages
    const ERROR_CCP_SSO_URL                         = 'Invalid "ENVIRONMENT.[ENVIRONMENT].CCP_SSO_URL" url. %s';
    const ERROR_CCP_CLIENT_ID                       = 'Missing "ENVIRONMENT.[ENVIRONMENT].CCP_SSO_CLIENT_ID".';
    const ERROR_ACCESS_TOKEN                        = 'Unable to get a valid "access_token. %s';
    const ERROR_VERIFY_CHARACTER                    = 'Unable to verify character data. %s';
    const ERROR_LOGIN_FAILED                        = 'Failed authentication due to technical problems: %s';
    const ERROR_CHARACTER_VERIFICATION              = 'Character verification failed by SSP SSO';
    const ERROR_CHARACTER_DATA                      = 'Failed to load characterData from ESI';
    const ERROR_CHARACTER_FORBIDDEN                 = 'Character "%s" is not authorized to log in. Reason: %s';
    const ERROR_SERVICE_TIMEOUT                     = 'CCP SSO service timeout (%ss). Try again later';
    const ERROR_COOKIE_LOGIN                        = 'Login from Cookie failed (data not found). Please retry by CCP SSO';
    const ERROR_CCP_JWK_CLAIM                       = 'Invalid "ENVIRONMENT.[ENVIRONMENT].CCP_SSO_JWK_CLAIM" url. %s';
    const ERROR_TOKEN_VERIFICATION                  = 'Could not validate the authenticity of the Access Token';

    /**
     * redirect user to CCP SSO page and request authorization
     * -> cf. Controller->getCookieCharacters() ( equivalent cookie based login)
     * @param \Base $f3
     */
    public function requestAdminAuthorization(\Base $f3) : void {
        // store browser tabId to be "targeted" after login
        $f3->set(self::SESSION_KEY_SSO_FROM, 'admin');

        $scopes = self::getScopesByAuthType('admin');
        $this->rerouteAuthorization($f3, $scopes, 'admin');
    }

    /**
     * redirect user to CCP SSO page and request authorization
     * -> cf. Controller->getCookieCharacters() ( equivalent cookie based login)
     * @param \Base $f3
     * @throws \Exception
     */
    public function requestAuthorization(\Base $f3) : void {
        $params = $f3->get('GET');

        if(
            isset($params['characterId']) &&
            ( $activeCharacter = $this->getCharacter() )
        ){
            // authentication restricted to a characterId -------------------------------------------------------------
            // restrict login to this characterId e.g. for character switch on map page
            $characterId = (int)trim((string)$params['characterId']);

            /**
             * @var Pathfinder\CharacterModel $character
             */
            $character = Pathfinder\AbstractPathfinderModel::getNew('CharacterModel');
            $character->getById($characterId, 0);

            // check if character is valid and exists
            if(
                $character->valid() &&
                $character->hasUserCharacter() &&
                ($activeCharacter->getUser()->_id === $character->getUser()->_id)
            ){
                // requested character belongs to current user
                // -> update character vom ESI (e.g. corp changed,..)
                $updateStatus = $character->updateFromESI();

                if( empty($updateStatus) ){

                    // make sure character data is up2date!
                    // -> this is not the case if e.g. userCharacters was removed "ownerHash" changed...
                    $character->getById($character->_id);

                    $character->updateAffiliation();

                    if(
                        $character->hasUserCharacter() &&
                        ($character->isAuthorized() === 'OK')
                    ){
                        if($this->loginByCharacter($character)){
                            // set "login" cookie
                            $this->setLoginCookie($character);

                            // route to "map"
                            $f3->reroute(['map', ['*' => '']]);
                        }
                    }
                }
            }

            // redirect to map map page on successful login
            $f3->set(self::SESSION_KEY_SSO_FROM, 'map');
        }

        // redirect to CCP SSO ----------------------------------------------------------------------------------------
        $scopes = self::getScopesByAuthType();
        $this->rerouteAuthorization($f3, $scopes);
    }

    /**
     * redirect user to CCPs SSO page
     * @param \Base $f3
     * @param array $scopes
     * @param string $rootAlias
     */
    private function rerouteAuthorization(\Base $f3,  $scopes = [], string $rootAlias = 'login'){
        if( !empty( Controller\Controller::getEnvironmentData('CCP_SSO_CLIENT_ID') ) ){
            // used for "state" check between request and callback
            $state = bin2hex( random_bytes(32) );
            $f3->set(self::SESSION_KEY_SSO_STATE, $state);

            $urlParams = [
                'response_type' => 'code',
                'redirect_uri' => Controller\Controller::getEnvironmentData('URL') . Controller\Controller::getEnvironmentData('BASE') . $f3->build('/sso/callbackAuthorization'),
                'client_id' => Controller\Controller::getEnvironmentData('CCP_SSO_CLIENT_ID'),
                'scope' => implode(' ', $scopes),
                'state' => $state
            ];

            $ssoAuthUrl = $f3->ssoClient()->getUrl();
            $ssoAuthUrl .= $f3->ssoClient()->getAuthorizationEndpointURI();
            $ssoAuthUrl .= '?' . http_build_query($urlParams, '', '&', PHP_QUERY_RFC3986 );

            $f3->status(302);
            $f3->reroute($ssoAuthUrl);
        }else{
            // SSO clientId missing
            $f3->set(self::SESSION_KEY_SSO_ERROR, self::ERROR_CCP_CLIENT_ID);
            self::getSSOLogger()->write(self::ERROR_CCP_CLIENT_ID);
            $f3->reroute([$rootAlias, ['*' => '']]);
        }
    }

    /**
     * callback handler for CCP SSO user Auth
     * -> see requestAuthorization()
     * @param \Base $f3
     * @throws \Exception
     */
    public function callbackAuthorization(\Base $f3) : void {
        $getParams = (array)$f3->get('GET');

        // users can log in either from @login (new user) or @map (existing user) root alias
        // -> or from /admin page
        // -> in case login fails, users should be redirected differently
        $rootAlias = 'login';
        if( !empty($f3->get(self::SESSION_KEY_SSO_FROM)) ){
            $rootAlias = $f3->get(self::SESSION_KEY_SSO_FROM);
        }

        if($f3->exists(self::SESSION_KEY_SSO_STATE)){
            // check response and validate 'state'
            if(
                isset($getParams['code']) &&
                isset($getParams['state']) &&
                !empty($getParams['code']) &&
                !empty($getParams['state']) &&
                $f3->get(self::SESSION_KEY_SSO_STATE) === $getParams['state']
            ){
                // clear 'state' for new next login request
                $f3->clear(self::SESSION_KEY_SSO_STATE);
                $f3->clear(self::SESSION_KEY_SSO_FROM);

                $accessData = $this->getSsoAccessData($getParams['code']);

                if(isset($accessData->accessToken, $accessData->esiAccessTokenExpires, $accessData->refreshToken)){
                    // login succeeded -> get basic character data for current login

                    $verificationCharacterData = $this->verifyCharacterData($accessData->accessToken);

                    if( !empty($verificationCharacterData) ){

                        // check if login is restricted to a characterID

                        // verification available data. Data is needed for "ownerHash" check

                        // get character data from ESI
                        $characterData = $this->getCharacterData((int)$verificationCharacterData->characterId);

                        if( isset($characterData->character) ){
                            // add "ownerHash" and SSO tokens
                            $characterData->character['ownerHash']              = $verificationCharacterData->owner;
                            $characterData->character['esiAccessToken']         = $accessData->accessToken;
                            $characterData->character['esiAccessTokenExpires']  = $accessData->esiAccessTokenExpires;
                            $characterData->character['esiRefreshToken']        = $accessData->refreshToken;
                            $characterData->character['esiScopes']              = $verificationCharacterData->scp;

                            // add/update static character data
                            $characterModel = $this->updateCharacter($characterData);

                            if( !is_null($characterModel) ){
                                // refresh corp/alliance affiliation from ESI
                                $characterModel->updateAffiliation();

                                // check if character is authorized to log in
                                if( ($authStatus = $characterModel->isAuthorized()) === 'OK'){
                                    // character is authorized to log in
                                    // -> update character log (current location,...)
                                    $characterModel = $characterModel->updateLog();

                                    // connect character with current user
                                    if(is_null($user = $this->getUser())){
                                        // connect character with existing user (no changes)
                                        if(is_null($user = $characterModel->getUser())){
                                            // no user found (new character) -> create new user and connect to character
                                            /**
                                             * @var Pathfinder\UserModel $user
                                             */
                                            $user = Pathfinder\AbstractPathfinderModel::getNew('UserModel');
                                            $user->name = $characterModel->name;
                                            $user->save();
                                        }
                                    }

                                    /**
                                     * @var Pathfinder\UserCharacterModel $userCharactersModel
                                     */
                                    if( is_null($userCharactersModel = $characterModel->userCharacter) ){
                                        $userCharactersModel = $characterModel->rel('userCharacter');
                                        $userCharactersModel->characterId = $characterModel;
                                    }

                                    // user might have changed
                                    $userCharactersModel->userId = $user;
                                    $userCharactersModel->save();

                                    // get updated character model
                                    $characterModel = $userCharactersModel->getCharacter();

                                    // login by character
                                    if($this->loginByCharacter($characterModel)){
                                        // set "login" cookie
                                        $this->setLoginCookie($characterModel);

                                        // -> pass current character data to target page
                                        $this->setTempCharacterData($characterModel->_id);

                                        // route to "map"
                                        if($rootAlias == 'admin'){
                                            $f3->reroute([$rootAlias, ['*' => '']]);
                                        }else{
                                            $f3->reroute(['map', ['*' => '']]);
                                        }
                                    }else{
                                        $f3->set(self::SESSION_KEY_SSO_ERROR, sprintf(self::ERROR_LOGIN_FAILED, $characterModel->name));
                                    }
                                }else{
                                    // character is not authorized to log in
                                    $f3->set(self::SESSION_KEY_SSO_ERROR,
                                        sprintf(self::ERROR_CHARACTER_FORBIDDEN, $characterModel->name, Pathfinder\CharacterModel::AUTHORIZATION_STATUS[$authStatus])
                                    );
                                }
                            }
                        }else{
                            // failed to load characterData from API
                            $f3->set(self::SESSION_KEY_SSO_ERROR, self::ERROR_CHARACTER_DATA);
                        }
                    }else{
                        // failed to verify character by CCP SSO
                        $f3->set(self::SESSION_KEY_SSO_ERROR, self::ERROR_CHARACTER_VERIFICATION);
                    }
                }else{
                    // SSO "accessData" missing (e.g. timeout)
                    $f3->set(self::SESSION_KEY_SSO_ERROR, sprintf(self::ERROR_SERVICE_TIMEOUT, self::SSO_TIMEOUT));
                }
            }else{
                // invalid SSO response
                $f3->set(self::SESSION_KEY_SSO_ERROR, sprintf(self::ERROR_LOGIN_FAILED, 'Invalid response'));
            }
        }

        $f3->reroute([$rootAlias, ['*' => '']]);
    }

    /**
     * login by cookie name
     * @param \Base $f3
     * @throws \Exception
     */
    public function login(\Base $f3) : void {
        $data = (array)$f3->get('GET');
        $cookieName = (string)$data['cookie'];
        $character = null;

        if( !empty($cookieName) ){
            if( !empty($cookieData = $this->getCookieByName($cookieName) )){
                // cookie data is valid -> validate data against DB (security check!)
                if( !empty($characters = $this->getCookieCharacters(array_slice($cookieData, 0, 1, true))) ){
                    // character is valid and allowed to login
                    $character = $characters[$cookieName];
                }
            }
        }

        if(is_object($character)){
            // login by character
            if($this->loginByCharacter($character)){
                // route to "map"
                $f3->reroute(['map', ['*' => '']]);
            }else{
                $f3->set(self::SESSION_KEY_SSO_ERROR, sprintf(self::ERROR_LOGIN_FAILED, $character->name));
            }
        }else{
            $f3->set(self::SESSION_KEY_SSO_ERROR, self::ERROR_COOKIE_LOGIN);
        }

        // on error -> route back to login form
        $f3->reroute(['login']);
    }

    /**
     * get a valid "access_token" for oAuth 2.0 verification
     * @param string $authCode
     * @return null|\stdClass
     */
    protected function getSsoAccessData(string $authCode) : ?\stdClass {
        return $this->verifyAuthorizationCode($authCode);
    }

    /**
     * verify authorization code, and get an "access_token" data
     * @param string $authCode
     * @return \stdClass
     */
    protected function verifyAuthorizationCode(string $authCode) : \stdClass {
        $requestParams = [
            'grant_type' => 'authorization_code',
            'code' => $authCode
        ];

        return $this->requestAccessData($requestParams);
    }

    /**
     * get new "access_token" by an existing "refresh_token"
     * -> if "access_token" is expired, this function gets a fresh one
     * @param string $refreshToken
     * @return \stdClass
     */
    public function refreshAccessToken(string $refreshToken) : \stdClass {
        $requestParams = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken
        ];

        return $this->requestAccessData($requestParams);
    }

    /**
     * request an "access_token" AND "refresh_token" data
     * -> this can either be done by sending a valid "authorization code"
     * OR by providing a valid "refresh_token"
     * @param array $requestParams
     * @return \stdClass
     */
    protected function requestAccessData( $requestParams) : \stdClass {
        $accessData = (object) [];
        $accessData->accessToken = null;
        $accessData->refreshToken = null;
        $accessData->esiAccessTokenExpires = 0;

        $authCodeRequestData = $this->getF3()->ssoClient()->send('getAccess', $this->getAuthorizationData(), $requestParams);

        if( !empty($authCodeRequestData) ){
            if( !empty($authCodeRequestData['accessToken']) ){
                // accessToken is required for endpoints that require Auth
                $accessData->accessToken =  $authCodeRequestData['accessToken'];
            }

            if( !empty($authCodeRequestData['expiresIn']) ){
                // expire time for accessToken
                try{
                    $accessTokenExpires = $this->getF3()->get('getDateTime')();
                    $accessTokenExpires->add(new \DateInterval('PT' . (int)$authCodeRequestData['expiresIn'] . 'S'));

                    $accessData->esiAccessTokenExpires = $accessTokenExpires->format('Y-m-d H:i:s');
                }catch(\Exception $e){
                    $this->getF3()->error(500, $e->getMessage(), $e->getTrace());
                }
            }

            if( !empty($authCodeRequestData['refreshToken']) ){
                // this token is used to refresh/get a new access_token when expires
                $accessData->refreshToken =  $authCodeRequestData['refreshToken'];
            }
        }else{
            $grantType = $requestParams['grant_type'] ?? 'unknown';
            self::getSSOLogger()->write(sprintf(self::ERROR_ACCESS_TOKEN . ' grant_type=[%s]',
                print_r(self::redactSecrets($requestParams), true),
                $grantType
            ));
        }

        return $accessData;
    }

    /**
     * redact sensitive keys from a parameter array before logging
     * @param array $params
     * @return array
     */
    private static function redactSecrets(array $params) : array {
        foreach (['refresh_token', 'code', 'client_secret'] as $key) {
            if (isset($params[$key])) {
                $params[$key] = '[REDACTED]';
            }
        }
        return $params;
    }

    /**
     * verify character data by decoding JWT "access_token"
     * -> verify against CCP JWK
     * -> get some basic information (like character id)
     * @param string $accessToken
     * @return object|null null on any verification failure
     */
    public function verifyCharacterData(string $accessToken) : ?object {
        try {
            $characterData = $this->verifyJwtAccessToken($accessToken);
            $characterData->characterId = (int)explode(':', $characterData->sub)[2];
            return $characterData;
        } catch (\Exception $e) {
            self::getSSOLogger()->write(sprintf(self::ERROR_VERIFY_CHARACTER, $e->getMessage()));
            return null;
        }
    }

    /**
     * verify JWT by comparing to CCP public JWK
     * -> get Ccp JWKs
     * -> decode accessToken using JWKs
     * -> Verify token claim is correct
     * @param string $accessToken
     * @return object
     * @throws \UnexpectedValueException on issuer or audience mismatch
    */
    public function verifyJwtAccessToken(string $accessToken) : object {
        JWT::$leeway = 10;
        $ccpJwks = $this->getCcpJwkData();

        // parse JWKS; on structural failure assume cached blob is corrupt and refetch once
        try {
            $keySet = JWK::parseKeySet($ccpJwks);
        } catch (\UnexpectedValueException | \InvalidArgumentException $e) {
            $this->getF3()->clear(self::JWKS_CACHE_KEY);
            $ccpJwks = $this->getCcpJwkData();
            $keySet = JWK::parseKeySet($ccpJwks);
        }

        try {
            // firebase/php-jwt v6.4+: algs are embedded in Key objects returned by parseKeySet; no separate alg array needed
            $decodedJwt = JWT::decode($accessToken, $keySet);
        } catch (\UnexpectedValueException $e) {
            // "kid" invalid = CCP rotated keys while our JWKS was cached — bust cache and retry once
            if (str_contains($e->getMessage(), '"kid" invalid')) {
                $this->getF3()->clear(self::JWKS_CACHE_KEY);
                $ccpJwks = $this->getCcpJwkData();
                $decodedJwt = JWT::decode($accessToken, JWK::parseKeySet($ccpJwks));
            } else {
                throw $e;
            }
        }

        // F1: issuer must match configured claim (previous strpos !== true was always true — never actually blocked)
        if (!hash_equals(static::getSsoJwkClaim(), (string)$decodedJwt->iss)) {
            throw new \UnexpectedValueException('JWT issuer mismatch');
        }

        // F2: audience must include our client ID (firebase/php-jwt does not verify aud automatically)
        $expectedClientId = (string)Controller\Controller::getEnvironmentData('CCP_SSO_CLIENT_ID');
        $aud = $decodedJwt->aud ?? null;
        $audList = is_array($aud) ? $aud : (is_string($aud) ? [$aud] : []);
        if (!in_array($expectedClientId, $audList, true)) {
            throw new \UnexpectedValueException('JWT audience mismatch');
        }

        // azp (authorized party) must match client ID if present
        if (isset($decodedJwt->azp) && (string)$decodedJwt->azp !== $expectedClientId) {
            throw new \UnexpectedValueException('JWT authorized party mismatch');
        }

        return $decodedJwt;
    }

    /**
     * get JWK from CCP and return decoded json object
     * Results are cached in F3 for JWKS_CACHE_TTL seconds to avoid a round-trip on every login.
     * @return array
    */
    protected function getCcpJwkData() : array {
        $f3 = $this->getF3();

        if ($cached = $f3->get(self::JWKS_CACHE_KEY)) {
            return $cached;
        }

        $jwkJson = $f3->ssoClient()->send('getJWKS');

        if( !empty($jwkJson) ){
            // ensure items in 'keys' are arrays and not objects
            array_walk($jwkJson['keys'], function(&$item): void{$item = (array) $item;});
            $f3->set(self::JWKS_CACHE_KEY, $jwkJson, self::JWKS_CACHE_TTL);
            return $jwkJson;
        }

        self::getSSOLogger()->write(sprintf(self::ERROR_LOGIN_FAILED, __METHOD__));
        return [];
    }

    /**
     * get character data
     * @param int $characterId
     * @return \stdClass
     * @throws \Exception
     */
    public function getCharacterData(int $characterId) : \stdClass{
        $characterData = (object) [];

        if($characterId){
            $characterDataBasic = $this->getF3()->ccpClient()->send('getCharacter', $characterId);
            if( !empty($characterDataBasic) ){
                // remove some "unwanted" data -> not relevant for Pathfinder
                $characterData->character = array_filter($characterDataBasic, fn($key) => in_array($key, ['id', 'name', 'securityStatus']), ARRAY_FILTER_USE_KEY);

            }
        }

        return $characterData;
    }

    /**
     * update character
     * @param \stdClass $characterData
     * @return Pathfinder\CharacterModel|null
     * @throws \Exception
     */
    protected function updateCharacter(\stdClass $characterData) : ?Pathfinder\CharacterModel {
        $character = null;

        if(!empty($characterData->character)){
            /**
             * @var Pathfinder\CharacterModel $character
             */
            $character = Pathfinder\AbstractPathfinderModel::getNew('CharacterModel');
            $character->getById((int)$characterData->character['id'], 0);
            $character->copyfrom($characterData->character, [
                'id', 'name', 'ownerHash', 'esiAccessToken', 'esiAccessTokenExpires', 'esiRefreshToken', 'esiScopes', 'securityStatus'
            ]);

            $character->save();
        }

        return $character;
    }

    /**
     * get data for HTTP "Authorization:" Header
     * -> This header is required for any Auth-required endpoints!
     * @return array
     */
    protected function getAuthorizationData() : array {
        return [
            Controller\Controller::getEnvironmentData('CCP_SSO_CLIENT_ID'),
            Controller\Controller::getEnvironmentData('CCP_SSO_SECRET_KEY'),
            'basic'
        ];
    }

    /**
     * get CCP SSO url from configuration file
     * -> throw error if url is broken/missing
     * @return string
     */
    static function getSsoUrlRoot() : string {
        $url = '';
        if( \Audit::instance()->url(self::getEnvironmentData('CCP_SSO_URL')) ){
            $url = self::getEnvironmentData('CCP_SSO_URL');
        }else{
            $error = sprintf(self::ERROR_CCP_SSO_URL, __METHOD__);
            self::getSSOLogger()->write($error);
            \Base::instance()->error(502, $error);
        }

        return $url;
    }

    /**
     * get CCP SSO JWK CLAIM from configuration file
     * -> throw error if string is missing
     * @return string
     */
    static function getSsoJwkClaim() : string {
        $str = self::getEnvironmentData('CCP_SSO_JWK_CLAIM');
        
        if( empty($str)){
            $error = sprintf(self::ERROR_CCP_JWK_CLAIM, __METHOD__);
            self::getSSOLogger()->write($error);
            \Base::instance()->error(502, $error);
        }

        return $str;
    }

    /**
     * get logger for SSO logging
     * @return \Log
     */
    static function getSSOLogger() : \Log {
        return parent::getLogger('SSO');
    }
}
