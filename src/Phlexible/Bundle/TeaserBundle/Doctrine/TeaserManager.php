<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Bundle\TeaserBundle\Doctrine;

use Doctrine\ORM\EntityManager;
use Phlexible\Bundle\ElementtypeBundle\Entity\ElementtypeVersion;
use Phlexible\Bundle\TeaserBundle\Entity\Teaser;
use Phlexible\Bundle\TeaserBundle\Model\TeaserManagerInterface;
use Phlexible\Bundle\TreeBundle\Model\TreeNodeInterface;

/**
 * Teaser manager
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
class TeaserManager implements TeaserManagerInterface
{
    const TYPE_TEASER = 'teaser';
    const TYPE_CATCH = 'catch';
    const TYPE_INHERITED = 'inherited';
    const TYPE_STOP = 'stop';
    const TYPE_HIDE = 'hide';

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
     * {@inheritdoc}
     */
    public function findTeaser($id)
    {
        return $this->entityManager->getRepository('PhlexibleTeaserBundle:Teaser')->find($id);
    }

    /**
     * {@inheritdoc}
     */
    public function findForLayoutAreaAndTreeNodePath($layoutarea, array $treeNodePath)
    {
        $teasers = array();
        $forTreeId = end($treeNodePath)->getId();

        foreach ($treeNodePath as $treeNode) {
            $localTeasers = $this->findForLayoutAreaAndTreeNode($layoutarea, $treeNode);

            foreach ($localTeasers as $index => $localTeaser) {
                if ($localTeaser->getType() === 'stop') {
                    unset($localTeasers[$index]);
                } elseif ($localTeaser->getType() === 'hide' && $treeNode->getId() === $forTreeId) {
                    unset($localTeasers[$index]);
                }
            }

            $teasers = array_merge($teasers, $localTeasers);
        }

        return $teasers;
    }

    /**
     * {@inheritdoc}
     */
    public function findForLayoutAreaAndTreeNode($layoutarea, TreeNodeInterface $treeNode)
    {
        $teasers = $this->entityManager->getRepository('PhlexibleTeaserBundle:Teaser')->findBy(
            array(
                'layoutareaId' => $layoutarea->getId(),
                'treeId'       => $treeNode->getId()
            )
        );

        return $teasers;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllByTIDPathFlat(
        $treePath,
        ElementtypeVersion $layoutArea,
        $language = null,
        array $availableLanguages = array(),
        $isPreview = false
    )
    {
        $teaserData = $this->getAllByTIDPath($treePath, $layoutArea, $language, $availableLanguages, $isPreview);

        $result = array();
        foreach ($teaserData['children'] as $teaserItem) {
            if ($teaserItem['type'] !== self::TYPE_INHERITED) {
                $result[] = $teaserItem;
            } else {
                foreach ($teaserItem['children'] as $inheritedTeaserItem) {
                    $result[] = $inheritedTeaserItem;
                }
            }
        }

        $teaserData['children'] = $result;

        return $teaserData;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllByTIDPath(
        $treePath,
        ElementtypeVersion $layoutArea,
        $language = null,
        array $availableLanguages = array(),
        $isPreview = false
    )
    {
        $container = MWF_Registry::getContainer();

        $teaserManager = $container->teasersManager;
        $elementManager = $container->elementsManager;
        $elementVersionManager = $container->elementsVersionManager;

        if (!count($availableLanguages)) {
            $availableLanguages = array($language);
        }

        $areaRoot = array(
            'id'                 => 'area_' . $layoutArea->getId(),
            'type'               => 'area',
            'layoutareaId'       => $layoutArea->getId(),
            'text'               => $layoutArea->getTitle(),
            'icon'               => $layoutArea->getIconUrl(),
            'elementTypeVersion' => $layoutArea,
            'children'           => array(),
        );

        $inheritUsed = false;
        $dummyInherit = array(
            'id'           => -1, //layoutArea->getId() . '_inherit',
            'type'         => self::TYPE_INHERITED,
            'layoutareaId' => $layoutArea->getId(),
            'text'         => 'inherited_teasers',
            'icon'         => '/resources/asset/elementtypes/elementtypes/_up.gif',
            'children'     => array(),
        );

        $hideEids = array();
        $inheritedStopEids = array();
        $localStopEids = array();
        $inheritedEids = array();

        $localTreeId = end($treePath)->getId();

        foreach ($treePath as $currentNode) {
            $currentTreeId = $currentNode->getId();

            $element = $elementManager->getByEID($currentNode->getEid());
            $elementMasterLanguage = $element->getMasterLanguage();

            $teasers = $teaserManager->getAllByTID(
                $currentTreeId,
                $layoutArea->getId(),
                $language,
                null,
                $availableLanguages,
                true
            );

            // first loop - only flags
            foreach ($teasers as $teaserArray) {
                $isInherited = $currentTreeId != $localTreeId;

                switch ($teaserArray['type']) {
                    case self::TYPE_HIDE:
                        // only necessary for local teasers
                        if ($isInherited) {
                            continue;
                        }

                        if ((bool) $teaserArray['no_display'] && !$isInherited) {
                            $hideEids[] = $teaserArray['teaser_eid'];
                        }

                        break;

                    case self::TYPE_STOP:
                        if ($isInherited) {
                            $inheritedStopEids[] = $teaserArray['teaser_eid'];
                        } else {
                            $localStopEids[] = $teaserArray['teaser_eid'];
                        }

                        break;
                }
            }

            // second loop - only teasers, catches and inherited
            foreach ($teasers as $teaserArray) {
                switch ($teaserArray['type']) {
                    case self::TYPE_INHERITED:
                        // only necessary for local teasers
                        if ($isInherited) {
                            continue;
                        }

                        $dummyInherit['id'] = $teaserArray['id'];
                        $dummyInherit['sort'] = $teaserArray['sort'];
                        $areaRoot['children'][] =& $dummyInherit;

                        $inheritUsed = count($areaRoot['children']);

                        break;


                    case self::TYPE_CATCH:
                        // only necessary for local teasers
                        if ($isInherited) {
                            continue;
                        }

                        $catchConfig = unserialize($teaserArray['configuration']);
                        $catchConfig = is_array($catchConfig) ? $catchConfig : array();

                        $availableLanguages = array(
                            $language,
                        );

                        $catch = new Makeweb_Teasers_Catch(
                            $teaserArray['id'],
                            $catchConfig,
                            $availableLanguages,
                            $isPreview,
                            0
                        );

                        $dummyCatch = array(
                            'id'           => $teaserArray['id'],
                            'type'         => self::TYPE_CATCH,
                            'layoutareaId' => $layoutArea->getID(),
                            'text'         => 'Catched',
                            'icon'         => '/resources/asset/elementtypes/elementtypes/_left.gif',
                            'sort'         => $teaserArray['sort'],
                            'catch'        => $catch,
                        );

                        $areaRoot['children'][] = $dummyCatch;

                        break;

                    case self::TYPE_TEASER:
                    default:
                        if ($isInherited && $teaserArray['stop_inherit']) {
                            continue;
                        }

                        if (in_array($teaserArray['teaser_eid'], $inheritedStopEids)) {
                            continue;
                        }

                        if (in_array($teaserArray['teaser_eid'], $localStopEids)) {
                            continue;
                        }

                        if (!empty($inheritedEids[$teaserArray['teaser_eid']])) {
                            continue;
                        }

                        $teaserNode = new Makeweb_Teasers_Node($teaserArray['id']);

                        if ($isPreview) {
                            $teaserLanguage = $language;
                            $teaser = $elementVersionManager->getLatest($teaserArray['teaser_eid']);
                        } else {
                            $onlineVersion = null;
                            foreach ($availableLanguages as $availableLanguage) {
                                if ($teaserNode->isPublished($availableLanguage)) {
                                    $teaserLanguage = $availableLanguage;
                                    $onlineVersion = $teaserNode->getOnlineVersion($teaserLanguage);
                                    break;
                                }
                            }

                            if (null === $onlineVersion) {
                                continue;
                            }

                            $teaser = $elementVersionManager->get($teaserArray['teaser_eid'], $onlineVersion);
                        }

                        $stopInherit = false;
                        if ($teaserArray['stop_inherit'] || in_array($teaserArray['teaser_eid'], $localStopEids)) {
                            $stopInherit = true;
                        }

                        $noDisplay = false;
                        if (!$isInherited && ($teaserArray['no_display'] || in_array(
                                    $teaserArray['teaser_eid'],
                                    $hideEids
                                ))
                        ) {
                            $noDisplay = true;
                        }

                        $dummyTeaser = array(
                            'id'             => $teaserArray['id'],
                            'eid'            => $teaserArray['teaser_eid'],
                            'type'           => self::TYPE_TEASER,
                            'layoutareaId'   => $layoutArea->getID(),
                            'language'       => $teaserLanguage,
                            'text'           => $teaser->getBackendTitle($language, $elementMasterLanguage),
                            'icon'           => $teaser->getIconUrl($teaserNode->getIconParams($language)),
                            'sort'           => $teaserArray['sort'],
                            'templateId'     => $teaserArray['template_id'],
                            'node'           => $teaserNode,
                            'elementVersion' => $teaser,
                            'inherited'      => $isInherited,
                            'stopInherit'    => $stopInherit,
                            'noDisplay'      => $noDisplay
                        );

                        if ($isInherited) {
                            $dummyInherit['children'][] = $dummyTeaser;

                            $inheritedEids[$teaserArray['teaser_eid']] = true;

                        } else {
                            $areaRoot['children'][] = $dummyTeaser;
                        }

                        break;
                }
            }
        }

        if (count($inheritedStopEids) || count($localStopEids)) {
            foreach ($dummyInherit['children'] as $teaserIdx => $teaser) {
                if (in_array($teaser['eid'], $inheritedStopEids)) {
                    unset($dummyInherit['children'][$teaserIdx]);
                    continue;
                }
                if (in_array($teaser['eid'], $localStopEids)) {
                    $dummyInherit['children'][$teaserIdx]['stopInherit'] = true;
                }
            }
        }

        if (false === $inheritUsed && count($dummyInherit['children'])) {
            $inheritUsed = count($areaRoot['children']);

            $dummyInherit['sort'] = 9999;
            array_push($areaRoot['children'], $dummyInherit);
        } elseif (false !== $inheritUsed && !count($dummyInherit['children'])) {
            unset($areaRoot['children'][$inheritUsed]);
        }

        foreach ($hideEids as $hideEid) {
            foreach ($areaRoot['children'] as $teaserIdx => $teaser) {
                if (isset($teaser['eid']) && $teaser['eid'] == $hideEid) {
                    $areaRoot['children'][$teaserIdx]['noDisplay'] = true;
                }

                if (isset($teaser['children'])) {
                    foreach ($teaser['children'] as $inheritedTeaserIdx => $inheritedTeaser) {
                        if (isset($inheritedTeaser['eid']) && $inheritedTeaser['eid'] == $hideEid) {
                            $areaRoot['children'][$teaserIdx]['children'][$inheritedTeaserIdx]['noDisplay'] = true;
                        }
                    }
                }
            }
        }

        /*
        if (count($localStopEids) && null !== $inheritUsed)
        {
            foreach($areaRoot['children'][$inheritUsed]['children'] as $key => $inheritedTeaser)
            {
                if (in_array($inheritedTeaser['eid'], $localStopEids))
                {
                    #$areaRoot['children'][$inheritUsed]['children'][$key]['cls'] = trim(str_replace('inherit', '', $areaRoot['children'][$inheritUsed]['children'][$key]['cls']));
                    $areaRoot['children'][$inheritUsed]['children'][$key]['stop_inherit'] = true;
                }
            }
        }
        */

        return $areaRoot;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllByTID(
        $tid,
        $areaId = null,
        $language = null,
        $includeInherit = false,
        array $availableLanguages = array(),
        $isPreview = false
    )
    {
        $db = MWF_Registry::getContainer()->dbPool->default;

        $select = $db->select()
            ->from(
                array('ett' => $db->prefix . 'element_tree_teasers'),
                array(
                    'tree_id',
                    'eid',
                    'layoutarea_id',
                    'teaser_eid',
                    'type',
                    'sort',
                    'modify_uid',
                    'modify_time',
                    'configuration',
                    'stop_inherit',
                    'id',
                    'template_id',
                    'no_display',
                )
            )
            ->where('tree_id = ?', (int) $tid)
            ->order('sort ASC');

        if ($isPreview) {
            $select
                ->joinLeft(
                    array('e' => $db->prefix . 'element'),
                    'e.eid = ett.teaser_eid',
                    array(
                        'latest_version',
                    )
                )
                ->joinLeft(
                    array('eh' => $db->prefix . 'element_history'),
                    'eh.eid = ett.teaser_eid AND NOT ISNULL(eh.language)',
                    'language'
                )
                ->joinLeft(
                    array('etto' => $db->prefix . 'element_tree_teasers_online'),
                    'etto.teaser_id = ett.id AND eh.language = etto.language',
                    array(
                        'online_version' => 'version'
                    )
                )
                ->group(array('ett.teaser_eid', 'ett.type', 'eh.language', 'ett.configuration'));
        } else {
            $select
                ->joinLeft(
                    array('e' => $db->prefix . 'element'),
                    'e.eid = ett.teaser_eid',
                    'latest_version'
                )
                ->joinLeft(
                    array('etto' => $db->prefix . 'element_tree_teasers_online'),
                    'etto.teaser_id = ett.id',
                    array(
                        'online_version' => 'version',
                        'language'
                    )
                );
        }

        if (!is_null($areaId)) {
            $select->where('layoutarea_id = ?', $areaId);
        }

        if ($language === null) {
            $language = MWF_Env::getContentLanguage();
        }

        if (!count($availableLanguages)) {
            $availableLanguages = array($language);
        }

        $results = $db->fetchAll($select);

        $groupedResults = Brainbits_Util_Array::groupBy(
            $results,
            array('sort', 'teaser_eid', 'language')
        );

        $hasInherit = false;

        $teasers = array();
        foreach ($groupedResults as $sortValue => $teaserEidArray) {
            foreach ($teaserEidArray as $teaserEid => $languageArray) {
                if (!count($languageArray)) {
                    continue;
                }

                // Is this a catch or a virtual teaser.
                if (!key($languageArray)) {
                    $teasers = array_merge($teasers, $languageArray['']);
                    continue;
                }

                $found = false;
                foreach ($availableLanguages as $language) {
                    if (array_key_exists($language, $languageArray)) {
                        $teasers = array_merge($teasers, $languageArray[$language]);
                        $found = true;
                        break;
                    }
                }

                if (!$found && $isPreview) {
                    $teasers = array_merge($teasers, current($languageArray));
                    continue;
                }
            }
        }

        /*
        if (0 && $includeInherit && !$hasInherit)
        {
            $treeManager = Makeweb_Elements_Tree_Manager::getInstance();
            $node        = $treeManager->getNodeByNodeId($tid);
            $path        = $node->getPath();
            array_pop($path);

            $inheritIds = array();

            foreach ($path as $pathTid)
            {
                $results = $db->fetchAll($select, array('tid' => $pathTid));

                foreach ($results as $row)
                {
                    if ($row['type'] === self::TYPE_TEASER && !empty($row['inherit']))
                    {
                        $inheritIds[$row['teaser_eid']] = 1;
                    }
                    if ($row['type'] === self::TYPE_STOP && array_key_exists($row['teaser_eid'], $inheritIds))
                    {
                        unset($inheritIds[$row['teaser_eid']]);
                    }
                }
            }

            if (count($inheritIds))
            {
                $teasers[] = array(
                    'id'   => 'newinheritsort',
                    'type' => 'inherit',
                    'sort' => 999
                );
            }
        }
        */

        return $teasers;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllByEID($eid, $areaId = null, $inheritSiterootID = null)
    {
        $db = MWF_Registry::getContainer()->dbPool->default;

        if (0 && !is_null($inheritSiterootID)) {
            $treeManager = Makeweb_Elements_Tree_Manager::getInstance();
            $tree = $treeManager->getBySiteRootID($inheritSiterootID);
            $node = $tree->getNodeByEid($eid);
            $path = $node->getEidPath();
        } else {
            $path = array($eid);
        }

        $teasers = array();
        foreach ($path as $pathEid) {
            $select = $db->select()
                ->from($db->prefix . 'element_tree_teasers')
                ->where('eid = ?', $pathEid)
                ->order('sort ASC');

            if (!is_null($areaId)) {
                $select->where('layoutarea_id = ?', $areaId);
            }

            $teasers = $db->fetchAll($select);

            return $teasers;

            foreach ($teasers as $teaserRow) {
                $teaserEid = $teaserRow['teaser_eid'];
                $type = $teaserRow['type'];

                if ($type == self::TYPE_TEASER) {
                    $teaser = self::getByEID($teaserEid);

                    $teasers[$teaserEid] = array(
                        'elementVersion' => $teaser,
                        'inherit'        => $teaserRow['inherit'],
                        'stop_inherit'   => $teaserRow['stop_inherit'],
                    );
                } else {
                    if ($type == 'inherit') {
                        $teasers['inherit'] = null;
                    } else {
                        if ($type == self::TYPE_CATCH) {
                            $teaserId = $teaserRow['id'];
                            $teasers['catch_' . $teaserId] = unserialize($teaserRow['configuration']);
                        }
                    }
                }
            }
        }

        return $teasers;
    }

    /**
     * {@inheritdoc}
     */
    public function getByEID($eid, $version = null)
    {
        $manager = Makeweb_Elements_Element_Version_Manager::getInstance();

        if ($version !== null) {
            return $manager->get($eid, $version);
        } else {
            return $manager->getLatest($eid);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createTeaser(
        $treeId,
        $eid,
        $layoutAreaId,
        $newElementTypeID,
        $prevId = 0,
        $inherit = true,
        $noDisplay = false,
        $masterLanguage = 'en'
    )
    {
        $db = MWF_Registry::getContainer()->dbPool->default;
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        try {
            $beforeEvent = new Makeweb_Teasers_Event_BeforeCreateTeaser($treeId, $eid, $layoutAreaId, $newElementTypeID);
            if (false === $dispatcher->dispatch($beforeEvent)) {
                return null;
            }

            $db->beginTransaction();

            $elementManager = Makeweb_Elements_Element_Manager::getInstance();
            $newElement = $elementManager->create($newElementTypeID, true, $masterLanguage);

            // fetch new eid
            $newEid = $newElement->getEid();

            $sort = 0;
            if ($prevId) {
                $select = $db->select()
                    ->from($db->prefix . 'element_tree_teasers', new Zend_Db_Expr('sort + 1'))
                    ->where('id = ?', $prevId);

                $sort = $db->fetchOne($select);
            }

            $now = date('Y-m-d H:i:s');
            $uid = MWF_Env::getUid();

            $db->update(
                $db->prefix . 'element_tree_teasers',
                array('sort' => new Zend_Db_Expr('sort + 1')),
                array('tree_id = ?' => $treeId, 'sort >= ?' => $sort)
            );

            // place new teaser in element_tree_teasers
            $insertData = array(
                'tree_id'       => $treeId,
                'eid'           => $eid,
                'teaser_eid'    => $newEid,
                'layoutarea_id' => $layoutAreaId,
                'type'          => self::TYPE_TEASER,
                'sort'          => $sort,
                'stop_inherit'  => !$inherit ? 1 : 0,
                'no_display'    => $noDisplay ? 1 : 0,
                'modify_time'   => $now,
                'modify_uid'    => $uid,
            );

            $db->insert($db->prefix . 'element_tree_teasers', $insertData);

            $newTeaserId = $db->lastInsertId($db->prefix . 'element_tree_teasers');

            $db->commit();

            $node = new Makeweb_Teasers_Node($newTeaserId);

            $event = new Makeweb_Teasers_Event_CreateTeaser($node);
            $dispatcher->dispatch($event);
        } catch (Exception $e) {
            $db->rollBack();

            throw new Makeweb_Elements_Element_Manager_Exception($e->getMessage());
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function createTeaserInstance($treeId, $teaserId, $layoutAreaId)
    {
        $db = MWF_Registry::getContainer()->dbPool->default;
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        try {
            $beforeEvent = new Makeweb_Teasers_Event_BeforeCreateTeaserInstance($treeId, $teaserId, $layoutAreaId);
            if (false === $dispatcher->dispatch($beforeEvent)) {
                return null;
            }

            $select = $db->select()
                ->from($db->prefix . 'element_tree_teasers')
                ->where('id = ?', $teaserId)
                ->limit(1);

            $row = $db->fetchRow($select);

            $row['id'] = null;
            $row['tree_id'] = $treeId;
            $row['layoutarea_id'] = $layoutAreaId;
            $row['modify_uid'] = MWF_Env::getUid();
            $row['modify_time'] = $db->fn->now();

            $db->insert($db->prefix . 'element_tree_teasers', $row);
            $newTeaserId = $db->lastInsertId($db->prefix . 'element_tree_teasers');

            Makeweb_Teasers_History::insert(
                Makeweb_Teasers_History::ACTION_CREATE_INSTANCE,
                $teaserId,
                $row['teaser_eid']
            );

            $node = new Makeweb_Teasers_Node($newTeaserId);

            $event = new Makeweb_Teasers_Event_CreateTeaserInstance($node);
            $dispatcher->dispatch($event);
        } catch (Exception $e) {
            $db->rollBack();

            throw new Makeweb_Elements_Element_Manager_Exception($e->getMessage());
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function createCatch(
        $treeId,
        $eid,
        $layoutAreaId
    )
    {
        // get writable db connection
        $db = MWF_Registry::getContainer()->dbPool->default;
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        try {
            $beforeEvent = new Makeweb_Teasers_Event_BeforeCreateCatch($treeId, $eid, $layoutAreaId);
            if (false === $dispatcher->dispatch($beforeEvent)) {
                return null;
            }
            #
            $now = date('Y-m-d H:i:s');
            $uid = MWF_Env::getUid();

            // place new teaser in element_tree_teasers
            $insertData = array(
                'tree_id'       => $treeId,
                'eid'           => $eid,
                'layoutarea_id' => $layoutAreaId,
                'type'          => self::TYPE_CATCH,
                'configuration' => serialize(
                    array(
                        'forTreeId' => $treeId,
                    )
                ),
                'modify_time'   => $now,
                'modify_uid'    => $uid,
            );

            $db->insert($db->prefix . 'element_tree_teasers', $insertData);

            $teaserId = $db->lastInsertId($db->prefix . 'element_tree_teasers');

            $event = new Makeweb_Teasers_Event_CreateCatch($treeId, $eid, $layoutAreaId, $teaserId);
            $dispatcher->dispatch($event);
        } catch (Exception $e) {
            throw new Makeweb_Elements_Element_Manager_Exception($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveCatch(
        $teaserId,
        $forTreeId,
        array $catchElementTypeId,
        $catchInNavigation,
        $catchMaxDepth,
        $catchSortField,
        $catchSortOrder,
        $catchFilter,
        $catchPaginator,
        $catchMaxElements,
        $catchRotation,
        $catchPoolSize,
        $catchElementsPerPage,
        $catchTemplate,
        array $catchMetaSearch
    )
    {
        // get writable db connection
        $db = MWF_Registry::getContainer()->dbPool->default;
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        // ignore pool size if catchMaxElements == catchPoolSize
        // to avoid unwanted data administration errors
        if (!$catchRotation || !$catchMaxElements || ($catchPoolSize && $catchMaxElements >= $catchPoolSize)) {
            $catchPoolSize = '';
            $catchRotation = false;
        }

        try {
            $beforeEvent = new Makeweb_Teasers_Event_BeforeUpdateCatch($teaserId);
            if (false === $dispatcher->dispatch($beforeEvent)) {
                return null;
            }

            $now = date('Y-m-d H:i:s');
            $uid = MWF_Env::getUid();

            // place new teaser in element_tree_teasers
            $updateData = array(
                'configuration' => serialize(
                    array(
                        'forTreeId'            => $forTreeId,
                        'catchElementTypeId'   => $catchElementTypeId,
                        'catchInNavigation'    => $catchInNavigation,
                        'catchMaxDepth'        => $catchMaxDepth,
                        'catchSortField'       => $catchSortField,
                        'catchSortOrder'       => $catchSortOrder,
                        'catchFilter'          => $catchFilter,
                        'catchRotation'        => $catchRotation,
                        'catchPaginator'       => $catchPaginator,
                        'catchMaxElements'     => $catchMaxElements,
                        'catchPoolSize'        => $catchPoolSize,
                        'catchElementsPerPage' => $catchElementsPerPage,
                        'catchTemplate'        => $catchTemplate,
                        'catchMetaSearch'      => $catchMetaSearch,
                    )
                ),
                'modify_time'   => $now,
                'modify_uid'    => $uid,
            );

            $where = array('id = ?' => $teaserId);

            $db->update($db->prefix . 'element_tree_teasers', $updateData, $where);

            $event = new Makeweb_Teasers_Event_UpdateCatch($teaserId);
            $dispatcher->dispatch($event);
        } catch (Exception $e) {
            throw new Makeweb_Elements_Element_Manager_Exception($e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTeaser($teaserId)
    {
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        $node = new Makeweb_Teasers_Node($teaserId);

        $beforeEvent = new Makeweb_Teasers_Event_BeforeDeleteTeaser($node);
        if (false === $dispatcher->dispatch($beforeEvent)) {
            return;
        }

        $db = MWF_Registry::getContainer()->dbPool->default;

        $select = $db->select()
            ->from($db->prefix . 'element_tree_teasers', 'teaser_eid')
            ->where('id = ?', $teaserId)
            ->limit(1);

        $eid = $db->fetchOne($select);

        $db->delete(
            $db->prefix . 'element_tree_teasers',
            array(
                'id = ?' => $teaserId
            )
        );

        Makeweb_Teasers_History::insert(
            Makeweb_Teasers_History::ACTION_DELETE_TEASER,
            $teaserId,
            $eid
        );

        $event = new Makeweb_Teasers_Event_DeleteTeaser($node);
        $dispatcher->dispatch($event);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteCatch($catchId)
    {
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        $beforeEvent = new Makeweb_Teasers_Event_BeforeDeleteCatch($catchId);
        if (false === $dispatcher->dispatch($beforeEvent)) {
            return;
        }

        $db = MWF_Registry::getContainer()->dbPool->default;

        $db->delete(
            $db->prefix . 'element_tree_teasers',
            array(
                'id = ?' => $catchId
            )
        );

        $event = new Makeweb_Teasers_Event_DeleteCatch($catchId);
        $dispatcher->dispatch($event);
    }

    /**
     * {@inheritdoc}
     */
    public function getTeaserEidById($id)
    {
        // get writable db connection
        $db = MWF_Registry::getContainer()->dbPool->default;

        $select = $db->select()
            ->from($db->prefix . 'element_tree_teasers', 'teaser_eid')
            ->where('id = :id');

        $result = (int) $db->fetchOne($select, array(':id' => $id));

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getLayoutAreaIdById($id)
    {
        // get writable db connection
        $db = MWF_Registry::getContainer()->dbPool->default;

        $select = $db->select()
            ->from($db->prefix . 'element_tree_teasers', 'layoutarea_id')
            ->where('id = :id');

        $result = (int) $db->fetchOne($select, array(':id' => $id));

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function publish($teaserId, $version, $language, $comment, $tid)
    {
        $db = MWF_Registry::getContainer()->dbPool->default;
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        $node = new Makeweb_Teasers_Node($teaserId);

        $beforeEvent = new Makeweb_Teasers_Event_BeforePublishTeaser($node, $language, $version);
        if (!$dispatcher->dispatch($beforeEvent)) {
            return null;
        }

        $eid = $node->getEid();

        if ($version === null) {
            $version = $node->getLatestVersion();
        }

        $db->delete(
            $db->prefix . 'element_tree_teasers_online',
            array(
                'teaser_id = ?' => $teaserId,
                'language = ?'  => $language,
            )
        );

        $insertData = array(
            'teaser_id'    => $teaserId,
            'eid'          => $eid,
            'language'     => $language,
            'version'      => $version,
            'publish_uid'  => MWF_Env::getUid(),
            'publish_time' => $db->fn->now(),
        );

        $db->insert($db->prefix . 'element_tree_teasers_online', $insertData);

        Makeweb_Teasers_History::insert(
            Makeweb_Teasers_History::ACTION_PUBLISH,
            $teaserId,
            $eid,
            $version,
            $language,
            $comment
        );

        $node = new Makeweb_Teasers_Node($teaserId);

        $event = new Makeweb_Teasers_Event_PublishTeaser($node, $language, $version);
        $dispatcher->dispatch($event);

        return $eid;
    }

    /**
     * {@inheritdoc}
     */
    public function setOffline($teaserId, $language)
    {
        $db = MWF_Registry::getContainer()->dbPool->default;
        $dispatcher = Brainbits_Event_Dispatcher::getInstance();

        $node = new Makeweb_Teasers_Node($teaserId);

        $beforeEvent = new Makeweb_Teasers_Event_BeforeSetTeaserOffline($node, $language);
        if (!$dispatcher->dispatch($beforeEvent)) {
            return null;
        }

        $db->delete(
            $db->prefix . 'element_tree_teasers_online',
            array(
                'teaser_id = ?' => $teaserId,
                'language = ?'  => $language,
            )
        );


        Makeweb_Teasers_History::insert(
            Makeweb_Teasers_History::ACTION_PUBLISH,
            $teaserId,
            $node->getEid(),
            null,
            $language
        );

        $node = new Makeweb_Teasers_Node($teaserId);

        $event = new Makeweb_Teasers_Event_SetTeaserOffline($node, $language);
        $dispatcher->dispatch($event);

        return $node->getEid();
    }

    /**
     * {@inheritdoc}
     */
    public function isPublished($eid, $language)
    {
        try {
            $db = MWF_Registry::getContainer()->dbPool->default;

            $select = $db->select()
                ->from($db->prefix . 'element_tree_teasers_online', new Zend_Db_Expr('1'))
                ->where('eid = ?', $eid)
                ->where('language = ?', $language)
                ->limit(1);

            $result = $db->fetchOne($select);

            return (bool) $result;
        } catch (Exception $e) {
            MWF_Log::exception($e);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isInherited($teaserId, $tid)
    {
        try {
            $db = MWF_Registry::getContainer()->dbPool->default;

            $select = $db->select()
                ->from($db->prefix . 'element_tree_teasers', 'tree_id')
                ->where('id = ?', $teaserId)
                ->limit(1);

            $result = $db->fetchOne($select);

            return $result != $tid;
        } catch (Exception $e) {
            MWF_Log::exception($e);
        }

        return false;
    }
}