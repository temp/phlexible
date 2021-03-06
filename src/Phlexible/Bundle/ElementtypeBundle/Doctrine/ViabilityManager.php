<?php

/*
 * This file is part of the phlexible package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\ElementtypeBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Phlexible\Bundle\ElementtypeBundle\Entity\ElementtypeApply;
use Phlexible\Bundle\ElementtypeBundle\Model\Elementtype;
use Phlexible\Bundle\ElementtypeBundle\Model\ViabilityManagerInterface;

/**
 * Viability manager
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class ViabilityManager implements ViabilityManagerInterface
{
    /**
     * @var EntityManager
     */
    private $entityManager;

    /**
     * @param EntityManager $entityManager
     */
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param Elementtype $elementtype
     *
     * @return ElementtypeApply[]
     */
    public function findAllowedParents(Elementtype $elementtype)
    {
        $viabilityRepository = $this->entityManager->getRepository('PhlexibleElementtypeBundle:ElementtypeApply');

        return $viabilityRepository->findBy(['elementtypeId' => $elementtype->getId()]);
    }

    /**
     * @param Elementtype $elementtype
     *
     * @return array
     */
    public function findAllowedChildren(Elementtype $elementtype)
    {
        $viabilityRepository = $this->entityManager->getRepository('PhlexibleElementtypeBundle:ElementtypeApply');

        return $viabilityRepository->findBy(['underElementtypeId' => $elementtype->getId()]);
    }

    /**
     * Update viability
     *
     * @param Elementtype $elementtype
     * @param array       $parentIds
     *
     * @return $this
     */
    public function updateViability(Elementtype $elementtype, array $parentIds)
    {
        $viabilityRepository = $this->entityManager->getRepository('PhlexibleElementtypeBundle:ElementtypeApply');

        $viabilities = $viabilityRepository->findBy(['elementtypeId' => $elementtype->getId()]);

        foreach ($viabilities as $index => $viability) {
            if (in_array($viability->getUnderElementtypeId(), $parentIds)) {
                unset($parentIds[array_search($viability->getUnderElementtypeId(), $parentIds)]);
                unset($viabilities[$index]);
            }
        }

        foreach ($parentIds as $parentId) {
            $viability = new ElementtypeApply();
            $viability
                ->setElementtypeId($elementtype->getId())
                ->setUnderElementtypeId($parentId);

            $this->entityManager->persist($viability);
        }

        foreach ($viabilities as $viability) {
            $this->entityManager->remove($viability);
        }

        $this->entityManager->flush();
    }
}
