<?php

namespace Exodus4D\Pathfinder\Controller\Api;

use Exodus4D\Pathfinder\Lib\Config;
use Exodus4D\Pathfinder\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Proxy controller for the zKillboard R2Z2 killstream API.
 * Browser fetch() is blocked by CORS on r2z2.zkillboard.com, so we proxy through our own domain.
 */
class Killboard extends Controller\Controller {

    /**
     * Proxy GET https://r2z2.zkillboard.com/ephemeral/sequence.json
     * Cached for 5s to absorb concurrent requests from multiple browser clients.
     * Route: GET /api/Killboard/sequence
     * @param \Base $f3
     */
    public function sequence(\Base $f3) : void {
        $ttl = 5;
        $cacheKey = 'r2z2_sequence';

        if(!$f3->exists($cacheKey, $body)){
            $r2z2Base = Config::getPathfinderData('api.zkillboard_r2z2');
            $client = new Client(['timeout' => 10, 'connect_timeout' => 3]);

            try {
                $response = $client->get($r2z2Base . '/sequence.json', ['http_errors' => false]);
                if($response->getStatusCode() !== 200){
                    $f3->status(502);
                    echo json_encode(['error' => 'R2Z2 sequence unavailable']);
                    return;
                }
                $body = (string)$response->getBody();
                $f3->set($cacheKey, $body, $ttl);
            } catch(\Throwable $e){
                error_log(sprintf('Killboard::sequence %s: %s', $e::class, $e->getMessage()));
                $f3->status(502);
                echo json_encode(['error' => 'R2Z2 request failed']);
                return;
            }
        }

        header('Content-Type: application/json');
        echo $body;
    }

    /**
     * Proxy GET https://r2z2.zkillboard.com/ephemeral/{sequenceId}.json
     * Returns the killmail JSON on 200, or 404 when the sequence is not yet available.
     * Route: GET /api/Killboard/r2z2/@arg1
     * @param \Base $f3
     * @param array $params
     */
    public function r2z2(\Base $f3, array $params) : void {
        $sequenceId = (int)($params['arg1'] ?? 0);

        if(!$sequenceId){
            $f3->status(400);
            echo json_encode(['error' => 'Invalid sequence ID']);
            return;
        }

        $r2z2Base = Config::getPathfinderData('api.zkillboard_r2z2');
        $client = new Client(['timeout' => 15, 'connect_timeout' => 3]);

        try {
            $response = $client->get($r2z2Base . '/' . $sequenceId . '.json', ['http_errors' => false]);
            $statusCode = $response->getStatusCode();

            if($statusCode === 404){
                $f3->status(204);
                return;
            }

            if($statusCode !== 200){
                $f3->status(502);
                echo json_encode(['error' => 'R2Z2 error ' . $statusCode]);
                return;
            }

            header('Content-Type: application/json');
            echo (string)$response->getBody();
        } catch(\Throwable $e){
            error_log(sprintf('Killboard::r2z2 seq=%d %s: %s', $sequenceId, $e::class, $e->getMessage()));
            $f3->status(204);
        }
    }
}
