<?php
/**
 * Created by PhpStorm.
 * User: Exodus 4D
 * Date: 16.03.2019
 * Time: 23:29
 */

namespace Exodus4D\Pathfinder\Controller\Api\Rest;


use Exodus4D\Pathfinder\Model\Pathfinder;

class Structure extends AbstractRestController {

    /**
     * @param \Base $f3
     * @throws \Exception
     */
    public function post(\Base $f3): void{
        $requestData = $this->getRequestData($f3);
        $structuresData = $requestData ? $this->update($requestData) : [];
        $this->out($structuresData);
    }

    /**
     * @param \Base $f3
     * @throws \Exception
     */
    public function put(\Base $f3): void{
        $requestData = $this->getRequestData($f3);
        $structuresData = $requestData ? $this->update([$requestData]) : [];
        $this->out($structuresData);
    }

    /**
     * @param \Base $f3
     * @param $params
     * @throws \Exception
     */
    public function patch(\Base $f3,  array $params): void{
        $requestData = $this->getRequestData($f3);
        $structuresData = (($structureId = (int)$params['id']) && ($structureId == (int)($requestData['id'] ?? 0))) ? $this->update([$requestData]) : [];
        $this->out($structuresData);
    }

    /**
     * @param \Base $f3
     * @param $params
     * @throws \Exception
     */
    public function delete(\Base $f3,  array $params): void{
        $deletedStructureIds = [];

        if($structureId = (int)$params['id']){
            $activeCharacter = $this->getCharacter();
            /**
             * @var Pathfinder\StructureModel $structure
             */
            $structure = Pathfinder\AbstractPathfinderModel::getNew('StructureModel');
            $structure->getById($structureId);
            if($structure->hasAccess($activeCharacter) && $structure->erase()){
                $deletedStructureIds[] = $structureId;
            }
        }
        $this->out($deletedStructureIds);
    }

    /**
     * @param array<string, mixed> $structuresData
     * @return array<string, mixed>
     * @throws \Exception
     */
    private function update( $structuresData) : array {
        $data = [];

        $activeCharacter = $this->getCharacter();
        if(!$activeCharacter || !($corporation = $activeCharacter->getCorporation())){
            $this->out($data);
            return $data;
        }

        // structures always belong to a corporation
        /**
         * @var Pathfinder\StructureModel $structure
         */
        $structure = Pathfinder\AbstractPathfinderModel::getNew('StructureModel');
        foreach($structuresData as $structureData){
            // reset on loop start because of potential "continue"
            $structure->reset();

            if(!empty($structureData['id']) && $structureId = (int)$structureData['id']){
                // update specific structure
                $structure->getById($structureId);
                if(!$structure->hasAccess($activeCharacter)){
                    continue;
                }
            }elseif(!isset($structureData['id'])){
                // from clipboard -> search by structure by name
                $structure->getByName($corporation, (string)($structureData['name'] ?? ''), (int)($structureData['systemId'] ?? 0));
            }

            $isNew = $structure->dry();

            $structure->setData($structureData);
            $structure->save();

            if($isNew){
                $corporation->saveStructure($structure);
            }

            // group all updated structures by corporation -> just for return
            $corporationsStructureData = $structure->getDataByCorporations();
            foreach($corporationsStructureData as $corporationId => $corporationStructureData){
                if(isset($data[$corporationId])){
                    $data[$corporationId]['structures'] = array_merge(
                        $data[$corporationId]['structures'],
                        $corporationStructureData['structures']
                    );
                }else{
                    $data[$corporationId] = $corporationStructureData;
                }
            }
        }

        return $data;
    }
}