<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 17.11.2018
 * Time: 10:13
 */

namespace Exodus4D\Pathfinder\Lib\Logging\Handler;

use Exodus4D\Pathfinder\Lib\Util;

class DiscordRallyWebhookHandler extends AbstractRallyWebhookHandler {

    /**
     * Build a native Discord embed payload for rally point notifications.
     * @return array<string, mixed>
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
                $characterData = (array)$attachmentData['character'];

                if (!empty($attachmentData['formatted'])) {
                    $content = $attachmentData['formatted'];
                }

                $embed = [
                    'title'  => "\u{26A0}\u{FE0F} Rally Point",
                    'color'  => $this->getAttachmentColorInt($tag ?: 'warning'),
                    'fields' => [],
                ];

                if (!empty($attachmentData['main']['message'])) {
                    $embed['description'] = $attachmentData['main']['message'];
                }

                if (!empty($characterData['id']) && !empty($characterData['name'])) {
                    $embed['author'] = [
                        'name'     => $characterData['name'] . ' #' . $characterData['id'],
                        'icon_url' => \Exodus4D\Pathfinder\Lib\Config::getPathfinderData('api.ccp_image_server') . '/Character/' . $characterData['id'] . '_32.jpg',
                    ];
                }

                if (!empty($thumbData['url'])) {
                    $embed['thumbnail'] = ['url' => $thumbData['url']];
                }

                if ($this->includeContext && !empty($objectData = $attachmentData['object'])) {
                    if (!empty($objectData['objName'])) {
                        $embed['fields'][] = ['name' => 'System', 'value' => $objectData['objName'], 'inline' => true];
                    }
                    if (!empty($objectData['objRegion'])) {
                        $embed['fields'][] = ['name' => 'Region', 'value' => $objectData['objRegion'], 'inline' => true];
                    }
                    if (!empty($objectData['objSecurity'])) {
                        $embed['fields'][] = ['name' => 'Security', 'value' => $objectData['objSecurity'], 'inline' => true];
                    }
                    if (isset($objectData['objIsWormhole'])) {
                        $embed['fields'][] = ['name' => 'Wormhole', 'value' => $objectData['objIsWormhole'] ? 'Yes' : 'No', 'inline' => true];
                    }
                    if (!empty($objectData['objEffect'])) {
                        $embed['fields'][] = ['name' => 'Effect', 'value' => $objectData['objEffect'], 'inline' => true];
                    }
                    if (!empty($objectData['objTrueSec'])) {
                        $embed['fields'][] = ['name' => 'TrueSec', 'value' => (string)$objectData['objTrueSec'], 'inline' => true];
                    }
                    if (!empty($objectData['objAlias'])) {
                        $embed['fields'][] = ['name' => 'Alias', 'value' => $objectData['objAlias'], 'inline' => true];
                    }
                    if (!empty($objectData['objDescription'])) {
                        $description = $this->htmlToMarkdown($objectData['objDescription']);
                        $embed['fields'][] = ['name' => 'Description', 'value' => sprintf('```%s```', $description), 'inline' => false];
                    }
                    if (!empty($objectData['objUrl'])) {
                        $embed['fields'][] = ['name' => 'Map Link', 'value' => $objectData['objUrl'], 'inline' => false];
                    }
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
