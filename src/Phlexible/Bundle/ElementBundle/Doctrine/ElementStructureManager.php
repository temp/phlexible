<?php

/*
 * This file is part of the phlexible package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\ElementBundle\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Phlexible\Bundle\ElementBundle\ElementEvents;
use Phlexible\Bundle\ElementBundle\ElementStructure\LinkExtractor\LinkExtractor;
use Phlexible\Bundle\ElementBundle\Entity\ElementLink;
use Phlexible\Bundle\ElementBundle\Entity\ElementStructure as StructureEntity;
use Phlexible\Bundle\ElementBundle\Entity\ElementStructureValue as ValueEntity;
use Phlexible\Bundle\ElementBundle\Entity\ElementVersion;
use Phlexible\Bundle\ElementBundle\Event\ElementStructureEvent;
use Phlexible\Bundle\ElementBundle\Model\ElementStructure;
use Phlexible\Bundle\ElementBundle\Model\ElementStructureManagerInterface;
use Phlexible\Bundle\ElementtypeBundle\Field\FieldRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Element structure manager
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ElementStructureManager implements ElementStructureManagerInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @var ElementStructureLoader
     */
    private $elementStructureLoader;

    /**
     * @var FieldRegistry
     */
    private $fieldRegistry;

    /**
     * @var LinkExtractor
     */
    private $linkExtractor;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param EntityManager            $entityManager
     * @param ElementStructureLoader   $elementStructureLoader
     * @param FieldRegistry            $fieldRegistry
     * @param LinkExtractor            $linkExtractor
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        EntityManager $entityManager,
        ElementStructureLoader $elementStructureLoader,
        FieldRegistry $fieldRegistry,
        LinkExtractor $linkExtractor,
        EventDispatcherInterface $eventDispatcher)
    {
        $this->entityManager = $entityManager;
        $this->elementStructureLoader = $elementStructureLoader;
        $this->fieldRegistry = $fieldRegistry;
        $this->linkExtractor = $linkExtractor;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ElementVersion $elementVersion
     * @param string         $defaultLanguage
     *
     * @return ElementStructure
     */
    public function find(ElementVersion $elementVersion, $defaultLanguage = null)
    {
        return $this->elementStructureLoader->load($elementVersion, $defaultLanguage);
    }

    /**
     * {@inheritdoc}
     */
    public function updateElementStructure(ElementStructure $elementStructure, $flush = true)
    {
        $conn = $this->entityManager->getConnection();

        $event = new ElementStructureEvent($elementStructure);
        $this->eventDispatcher->dispatch(ElementEvents::BEFORE_CREATE_ELEMENT_STRUCTURE, $event);

        $this->applyStructureSort($elementStructure);
        $this->insertStructure($elementStructure, $conn, true);
        $this->insertLinks($elementStructure);

        $event = new ElementStructureEvent($elementStructure);
        $this->eventDispatcher->dispatch(ElementEvents::CREATE_ELEMENT_STRUCTURE, $event);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param ElementStructure $elementStructure
     */
    private function applyStructureSort(ElementStructure $elementStructure)
    {
        $sort = 1;

        $elementStructure->setSort($sort++);

        $rii = new \RecursiveIteratorIterator($elementStructure->getIterator(), \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($rii as $structure) {
            $structure->setSort($sort++);
        }
    }

    /**
     * @param ElementStructure $elementStructure
     * @param Connection       $conn
     * @param bool             $isRoot
     */
    private function insertStructure(ElementStructure $elementStructure, Connection $conn, $isRoot = false, array $entities = [])
    {
        $structureRepository = $this->entityManager->getRepository('PhlexibleElementBundle:ElementStructure');

        /*
        $structureEntity = $structureRepository->findOneBy(
            array(
                'dataId'         => $elementStructure->getDataId(),
                'elementVersion' => $elementStructure->getElementVersion(),
            )
        );
        */
        $structureEntity = null;
        if ($elementStructure->getId()) {
            $structureEntity = $structureRepository->find($elementStructure->getId());
        }
        if (!$structureEntity) {
            $structureEntity = new StructureEntity();
        }
        $parentStructureEntity = $elementStructure->getParentStructure() ? $entities[spl_object_hash($elementStructure->getParentStructure())] : null;
        $structureEntity
            ->setDataId($elementStructure->getDataId())
            ->setElementVersion($elementStructure->getElementVersion())
            ->setDsId($elementStructure->getDsId())
            ->setType($isRoot ? 'root' : 'group')
            ->setName($elementStructure->getName())
            ->setParentStructure($parentStructureEntity)
            ->setParentDsId($elementStructure->getParentDsId())
            ->setSort($elementStructure->getSort());

        $this->entityManager->persist($structureEntity);

        $entities[spl_object_hash($elementStructure)] = $structureEntity;

        $valueRepository = $this->entityManager->getRepository('PhlexibleElementBundle:ElementStructureValue');

        foreach ($elementStructure->getLanguages() as $language) {
            $valueEntities = $valueRepository->findBy(
                [
                    'structure' => $structureEntity,
                ]
            );
            foreach ($valueEntities as $valueEntity) {
                $this->entityManager->remove($valueEntity);
            }
        }

        foreach ($elementStructure->getLanguages() as $language) {
            foreach ($elementStructure->getValues($language) as $elementStructureValue) {
                if ($elementStructureValue->getValue()) {
                    $value = $elementStructureValue->getValue();
                    $field = $this->fieldRegistry->getField($elementStructureValue->getType());
                    $value = $field->serialize($value);

                    $valueEntity = new ValueEntity();
                    $valueEntity
                        ->setElement($elementStructure->getElementVersion()->getElement())
                        ->setVersion($elementStructure->getElementVersion()->getVersion())
                        ->setLanguage($elementStructureValue->getLanguage())
                        ->setDsId($elementStructureValue->getDsId())
                        ->setType($elementStructureValue->getType())
                        ->setName($elementStructureValue->getName())
                        ->setStructure($structureEntity)
                        ->setContent($value)
                        ->setOptions($elementStructureValue->getOptions() ? $elementStructureValue->getOptions() : null);

                    $this->entityManager->persist($valueEntity);
                    /*
                    $conn->insert(
                        'element_structure_value',
                        array(
                            'data_id'          => $elementStructureValue->getId(),
                            'eid'              => $elementStructure->getElementVersion()->getElement()->getEid(),
                            'version'          => $elementStructure->getElementVersion()->getVersion(),
                            'language'         => $elementStructureValue->getLanguage(),
                            'ds_id'            => $elementStructureValue->getDsId(),
                            'type'             => $elementStructureValue->getType(),
                            'name'             => $elementStructureValue->getName(),
                            'repeatable_id'    => $elementStructure->getId() ?: null,
                            'repeatable_ds_id' => $elementStructure->getDsId() ?: null,
                            'content'          => $value,
                            'options'          => !empty($elementStructureValue->getOptions()) ? $elementStructureValue->getOptions() : null,
                        )
                    );
                    */
                }
            }
        }

        foreach ($elementStructure->getStructures() as $childStructure) {
            $this->insertStructure($childStructure, $conn, false, $entities);
        }
    }

    /**
     * @param ElementStructure $elementStructure
     */
    private function insertLinks(ElementStructure $elementStructure)
    {
        $links = $this->extractLinks($elementStructure);

        foreach ($links as $link) {
            $link->setElementVersion($elementStructure->getElementVersion());

            $this->entityManager->persist($link);
        }
    }

    /**
     * @param ElementStructure $elementStructure
     *
     * @return ElementLink[]
     */
    private function extractLinks(ElementStructure $elementStructure)
    {
        $links = [];

        foreach ($elementStructure->getLanguages() as $language) {
            foreach ($elementStructure->getValues($language) as $elementStructureValue) {
                $links = array_merge($links, $this->linkExtractor->extract($elementStructureValue));
            }
        }

        foreach ($elementStructure->getStructures() as $childStructure) {
            $links = array_merge($links, $this->extractLinks($childStructure));
        }

        return $links;
    }
}
