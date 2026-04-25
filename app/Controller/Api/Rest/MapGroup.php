<?php

namespace Exodus4D\Pathfinder\Controller\Api\Rest;

use Exodus4D\Pathfinder\Model\Pathfinder;

class MapGroup extends AbstractRestController {

    public function get(\Base $f3, $params) : void {
        $requestData = $this->getRequestData($f3);
        $groupData = null;

        if($groupId = (int)$params['id']){
            $activeCharacter = $this->getCharacter();
            if(!$activeCharacter) return;

            $group = Pathfinder\AbstractPathfinderModel::getNew('MapGroupModel');
            $group->getById($groupId);
            if(!$group->dry() && $group->hasAccess($activeCharacter)){
                $groupData = $group->getData();
            }
        }

        $this->out($groupData);
    }

    public function put(\Base $f3) : void {
        $requestData = $this->getRequestData($f3);
        $groupData = null;

        if($mapId = (int)($requestData['mapId'] ?? 0)){
            $activeCharacter = $this->getCharacter();
            if(!$activeCharacter){
                $this->out($groupData);
                return;
            }

            $map = Pathfinder\AbstractPathfinderModel::getNew('MapModel');
            $map->getById($mapId);
            if($map->hasAccess($activeCharacter)){
                $group = Pathfinder\AbstractPathfinderModel::getNew('MapGroupModel');
                $group->mapId   = $map;
                $group->label   = (string)($requestData['label'] ?? 'Group');
                $group->posX    = (int)($requestData['posX'] ?? 0);
                $group->posY    = (int)($requestData['posY'] ?? 0);
                $group->width   = (int)($requestData['width'] ?? 300);
                $group->height  = (int)($requestData['height'] ?? 200);
                $group->constrain = (bool)($requestData['constrain'] ?? false);
                if($group->save($activeCharacter)){
                    $groupData = $group->getData();
                }
            }
        }

        $this->out($groupData);
    }

    public function patch(\Base $f3, $params) : void {
        $requestData = $this->getRequestData($f3);
        $groupData = null;

        if($groupId = (int)$params['id']){
            $activeCharacter = $this->getCharacter();
            if(!$activeCharacter){
                $this->out($groupData);
                return;
            }

            $group = Pathfinder\AbstractPathfinderModel::getNew('MapGroupModel');
            $group->getById($groupId);

            if(!$group->dry() && $group->hasAccess($activeCharacter)){
                if(isset($requestData['label']))       $group->label       = (string)$requestData['label'];
                if(isset($requestData['posX']))        $group->posX        = (int)$requestData['posX'];
                if(isset($requestData['posY']))        $group->posY        = (int)$requestData['posY'];
                if(isset($requestData['width']))       $group->width       = (int)$requestData['width'];
                if(isset($requestData['height']))      $group->height      = (int)$requestData['height'];
                if(isset($requestData['isCollapsed'])) $group->isCollapsed = (bool)$requestData['isCollapsed'];
                if(isset($requestData['constrain']))   $group->constrain   = (bool)$requestData['constrain'];
                if($group->save($activeCharacter)){
                    $groupData = $group->getData();
                }
            }
        }

        $this->out($groupData);
    }

    public function delete(\Base $f3, $params) : void {
        $deletedGroupIds = [];

        if($groupId = (int)$params['id']){
            $activeCharacter = $this->getCharacter();
            $group = Pathfinder\AbstractPathfinderModel::getNew('MapGroupModel');
            $group->getById($groupId);
            if(!$group->dry() && $group->hasAccess($activeCharacter)){
                $group->updatedCharacterId = $activeCharacter;
                if($group->erase()){
                    $deletedGroupIds[] = $groupId;
                }
            }
        }

        $this->out($deletedGroupIds);
    }
}
