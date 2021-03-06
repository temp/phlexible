<?php

/*
 * This file is part of the phlexible package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Bundle\ElementBundle\Change;

use Phlexible\Bundle\ElementBundle\ElementService;
use Phlexible\Bundle\ElementBundle\Model\ElementSourceManagerInterface;
use Phlexible\Bundle\ElementtypeBundle\ElementtypeService;

/**
 * Elementtype change checker
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class Checker
{
    /**
     * @var ElementtypeService
     */
    private $elementtypeService;

    /**
     * @var ElementService
     */
    private $elementService;

    /**
     * @var ElementSourceManagerInterface
     */
    private $elementSourceManager;

    /**
     * @param ElementtypeService            $elementtypeService
     * @param ElementService                $elementService
     * @param ElementSourceManagerInterface $elementSourceManager
     */
    public function __construct(
        ElementtypeService $elementtypeService,
        ElementService $elementService,
        ElementSourceManagerInterface $elementSourceManager)
    {
        $this->elementtypeService = $elementtypeService;
        $this->elementService = $elementService;
        $this->elementSourceManager = $elementSourceManager;
    }

    /**
     * @return Change[]
     */
    public function check()
    {
        $changes = [];

        $allElementtypes = $this->elementtypeService->findAllElementtypes();

        $referenceElementtypeIds = [];
        foreach ($allElementtypes as $elementtype) {
            if ($elementtype->getType() !== 'reference') {
                continue;
            }
            $reason = '';
            $needImport = false;
            $outdatedElementSources = [];
            $oldElementSources = $this->elementSourceManager->findByElementtype($elementtype);
            if (!$oldElementSources) {
                $needImport = true;
                $reason = 'New Elementtype';
            } else {
                foreach ($oldElementSources as $oldElementSource) {
                    if ($oldElementSource->getElementtypeRevision() < $elementtype->getRevision()) {
                        $needImport = true;
                        $outdatedElementSources[] = $oldElementSource;
                        $reason = 'Higher revision';
                    }
                }
            }
            if ($needImport) {
                $referenceElementtypeIds[] = $elementtype->getId();
            }
            $changes[] = new Change($elementtype, $needImport, $reason, $outdatedElementSources);
        }

        foreach ($allElementtypes as $elementtype) {
            if ($elementtype->getType() === 'reference') {
                continue;
            }
            $reason = '';
            $needImport = false;
            $outdatedElementSources = [];
            $oldElementSources = $this->elementSourceManager->findByElementtype($elementtype);
            if (!$oldElementSources) {
                $needImport = true;
                $reason = 'New Elementtype';
            } else {
                foreach ($oldElementSources as $oldElementSource) {
                    if ($oldElementSource->getElementtypeRevision() < $elementtype->getRevision()) {
                        $needImport = true;
                        $outdatedElementSources[] = $oldElementSource;
                        $reason = 'Higher revision';
                    #} else {
                    #    throw new \Exception("Version mismatch, to-be-commited version is lower than existing version.");
                    }
                }

                if (array_intersect($elementtype->getStructure()->getReferenceIds(), $referenceElementtypeIds)) {
                    foreach ($oldElementSources as $oldElementSource) {
                        $needImport = true;
                        $outdatedElementSources[] = $oldElementSource;
                    }
                    $reason = 'Reference outdated';
                }
            }
            $changes[] = new Change($elementtype, $needImport, $reason, $outdatedElementSources);
        }

        return $changes;
    }
}
