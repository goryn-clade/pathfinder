<?php /** @noinspection PhpUndefinedMethodInspection */

/**
 * Created by PhpStorm.
 * User: exodus4d
 * Date: 16.01.16
 * Time: 03:34
 */

namespace Exodus4D\Pathfinder\Controller\Api;


use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Controller;

/**
* Github controller
* Class Route
* @package Controller\Api
*/
class GitHub extends Controller\Controller {

    /**
     * cache key for formatted release data
     */
    const CACHE_KEY_RELEASES = 'CACHE_GITHUB_RELEASES';

    /**
     * cache time for release data (s)
     * -> GitHub allows 60 unauthenticated requests per hour per IP, each uncached call makes 1 + RELEASE_COUNT
     */
    const CACHE_TTL_RELEASES = 60 * 60;

    /**
     * cache time when GitHub returned no releases (s), e.g. rate limited
     */
    const CACHE_TTL_RELEASES_EMPTY = 60 * 5;

    /**
     * number of releases to show
     */
    const RELEASE_COUNT = 4;

    /**
     * get release information from  GitHub
     * @param \Base $f3
     */
    public function releases(\Base $f3): void{
        if(!$f3->exists(self::CACHE_KEY_RELEASES, $releases)){
            $releases = $this->getReleases($f3);
            $f3->set(self::CACHE_KEY_RELEASES, $releases, empty($releases) ? self::CACHE_TTL_RELEASES_EMPTY : self::CACHE_TTL_RELEASES);
        }

        $return = (object) [];
        $return->releasesData = $releases;
        $return->version = (object) [];
        $return->version->current =  Config::getPathfinderData('version');
        $return->version->last =  '';
        $return->version->delta = null;
        $return->version->dev = false;
        $return->version->unavailable = empty($releases);

        foreach($releases as $key => $release){
            if($key === 0){
                $return->version->last = $release['name'] ?? '';
                if(version_compare( $return->version->current, $return->version->last, '>')){
                    $return->version->dev = true;
                }
            }

            if(
                !$return->version->dev &&
                version_compare($release['name'] ?? '', $return->version->current, '>=')
            ){
                $return->version->delta = ($key === count($releases) - 1) ? '>= ' . $key : $key;
            }
        }

        echo json_encode($return);
    }

    /**
     * get releases from GitHub, release body converted to HTML
     * @param \Base $f3
     * @return array
     */
    protected function getReleases(\Base $f3) : array {
        try {
            $releases = $f3->gitHubClient()->send('getProjectReleases', 'goryn-clade/pathfinder', self::RELEASE_COUNT);
        } catch(\Exception) {
            $releases = [];
        }

        if(!is_array($releases) || isset($releases['error'])){
            return [];
        }

        foreach($releases as &$release){
            $body = $release['body'] ?? '';

            // remove "update information" from release text
            // -> keep everything until first "***" -> horizontal line
            if( ($pos = strpos((string) $body, '***')) !== false){
                $body = substr((string) $body, 0, $pos);
            }

            // convert list style
            $body = str_replace(' - ', '* ', $body);

            // convert Markdown to HTML -> use either gitHub API (in oder to create abs, issue links)
            // -> or F3´s markdown as fallback
            try {
                $html = $f3->gitHubClient()->send('markdownToHtml', 'goryn-clade/pathfinder', $body);
            } catch(\Exception) {
                $html = '';
            }
            if(!is_string($html)){
                $html = '';
            }

            if(!empty($html)){
                $body = $html;
            }else{
                $body = \Markdown::instance()->convert(trim($body));
            }

            $release['body'] = $body;
        }

        return $releases;
    }
}
