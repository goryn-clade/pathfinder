<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 17.11.2018
 * Time: 10:23
 */

namespace Exodus4D\Pathfinder\Lib\Logging\Handler;

use Exodus4D\Pathfinder\Lib\Util;

class DiscordMapWebhookHandler extends AbstractMapWebhookHandler {

    /**
     * Build a native Discord embed payload for map change events.
     * @return array<string, string|list<array<string, (array<mixed> | int | string)>>>
     */
    #[\Override]
    /**
     * @param array<string, mixed> $record
     */
    protected function getPostData(array $record): array {
        $tag     = (string)$record['context']['tag'];
        $content = '';
        $embeds    = [];

        if (
            $this->useAttachment &&
            !empty($attachmentsData = $record['context']['data'])
        ) {
            $attachmentsData = Util::is_assoc($attachmentsData) ? [$attachmentsData] : $attachmentsData;
            $thumbData       = (array)$record['extra']['thumb'];

            foreach ($attachmentsData as $attachmentData) {
                $channelData   = (array)$attachmentData['channel'];
                $characterData = (array)$attachmentData['character'];
                $formatted     = (string)$attachmentData['formatted'];
                $msgParts      = explode('|', $formatted, 2);

                if (!empty($channelData) && empty($content)) {
                    $content = "**Map '" . $channelData['channelName'] . "' #" . $channelData['channelId'] . " changed**";
                }

                $embed = [
                    'title'       => !empty($msgParts[0]) ? $msgParts[0] : 'Map update',
                    'description' => !empty($msgParts[1]) ? sprintf('```%s```', trim($msgParts[1])) : '',
                    'color'       => $this->getAttachmentColorInt($tag),
                    'fields'      => [],
                ];

                if (!empty($characterData['id']) && !empty($characterData['name'])) {
                    $embed['author'] = [
                        'name'     => $characterData['name'] . ' #' . $characterData['id'],
                        'icon_url' => \Exodus4D\Pathfinder\Lib\Config::getPathfinderData('api.ccp_image_server') . '/Character/' . $characterData['id'] . '_32.jpg',
                    ];
                }

                if (!empty($thumbData['url'])) {
                    $embed['thumbnail'] = ['url' => $thumbData['url']];
                }

                if ($this->includeExtra) {
                    if (!empty($record['extra']['path'])) {
                        $embed['fields'][] = ['name' => 'Path', 'value' => sprintf('`%s`', $record['extra']['path']), 'inline' => true];
                    }
                    if (!empty($tag)) {
                        $embed['fields'][] = ['name' => 'Tag', 'value' => sprintf('`%s`', $tag), 'inline' => true];
                    }
                    if (!empty($record['level_name'])) {
                        $embed['fields'][] = ['name' => 'Level', 'value' => sprintf('`%s`', $record['level_name']), 'inline' => true];
                    }
                    if (!empty($record['extra']['ip'])) {
                        $embed['fields'][] = ['name' => 'IP', 'value' => sprintf('`%s`', $record['extra']['ip']), 'inline' => true];
                    }
                }

                $embeds[] = $embed;
            }
        }

        $payload = ['embeds' => $embeds];
        if ($content) {
            $payload['content'] = $content;
        }

        return $payload;
    }
}
