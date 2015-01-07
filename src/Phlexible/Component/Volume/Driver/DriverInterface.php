<?php
/**
 * phlexible
 *
 * @copyright 2007-2013 brainbits GmbH (http://www.brainbits.net)
 * @license   proprietary
 */

namespace Phlexible\Component\Volume\Driver;

use Phlexible\Component\Volume\Exception\AlreadyExistsException;
use Phlexible\Component\Volume\FileSource\FileSourceInterface;
use Phlexible\Component\Volume\HashCalculator\HashCalculatorInterface;
use Phlexible\Component\Volume\Model\FileInterface;
use Phlexible\Component\Volume\Model\FolderInterface;

/**
 * Driver interface
 *
 * @author Stephan Wentz <sw@brainbits.net>
 */
interface DriverInterface
{
    const FEATURE_VERSIONS = 'versions';

    /**
     * @param string $feature
     *
     * @return bool
     */
    public function hasFeature($feature);

    /**
     * @return string
     */
    public function getFileClass();

    /**
     * @return string
     */
    public function getFolderClass();

    /**
     * @return HashCalculatorInterface
     */
    public function getHashCalculator();

    /**
     * @return FolderInterface
     */
    public function findRootFolder();

    /**
     * @param string $id
     *
     * @return FolderInterface
     */
    public function findFolder($id);

    /**
     * @param int $fileId
     *
     * @return FolderInterface
     */
    public function findFolderByFileId($fileId);

    /**
     * @param string $path
     *
     * @return FolderInterface
     */
    public function findFolderByPath($path);

    /**
     * @param FolderInterface $parentFolder
     *
     * @return FolderInterface[]
     */
    public function findFoldersByParentFolder(FolderInterface $parentFolder);

    /**
     * @param FolderInterface $parentFolder
     *
     * @return int
     */
    public function countFoldersByParentFolder(FolderInterface $parentFolder);

    /**
     * @param int $id
     * @param int $version
     *
     * @return FileInterface
     */
    public function findFile($id, $version = 1);

    /**
     * @param array      $criteria
     * @param array|null $order
     * @param int|null   $limit
     * @param int|null   $start
     *
     * @return FileInterface[]
     */
    public function findFiles(array $criteria, $order = null, $limit = null, $start = null);

    /**
     * @param array $criteria
     *
     * @return int
     */
    public function countFiles(array $criteria);

    /**
     * @param string $path
     * @param int    $version
     *
     * @return FileInterface
     */
    public function findFileByPath($path, $version = 1);

    /**
     * @param int $id
     *
     * @return FileInterface[]
     */
    public function findFileVersions($id);

    /**
     * @param FolderInterface $folder
     * @param string          $order
     * @param int             $limit
     * @param int             $start
     * @param bool            $includeHidden
     *
     * @return FileInterface[]
     */
    public function findFilesByFolder(
        FolderInterface $folder,
        $order = null,
        $limit = null,
        $start = null,
        $includeHidden = false);

    /**
     * @param FolderInterface $folder
     *
     * @return int
     */
    public function countFilesByFolder(FolderInterface $folder);

    /**
     * @param int $limit
     *
     * @return FileInterface[]
     */
    public function findLatestFiles($limit = 20);

    /**
     * @param string $query
     *
     * @return FileInterface[]
     */
    public function search($query);

    /**
     * @param FolderInterface $folder
     */
    public function updateFolder(FolderInterface $folder);

    /**
     * @param FolderInterface $folder
     * @param string          $oldPath
     */
    public function renameFolder(FolderInterface $folder, $oldPath);

    /**
     * @param FolderInterface $folder
     * @param string          $oldPath
     */
    public function moveFolder(FolderInterface $folder, $oldPath);

    /**
     * @param FolderInterface $folder
     */
    public function deleteFolder(FolderInterface $folder);

    /**
     * @param FileInterface $file
     */
    public function updateFile(FileInterface $file);

    /**
     * @param FileInterface       $file
     * @param FileSourceInterface $fileSource
     */
    public function createFile(FileInterface $file, FileSourceInterface $fileSource);

    /**
     * @param FileInterface       $file
     * @param FileSourceInterface $fileSource
     */
    public function replaceFile(FileInterface $file, FileSourceInterface $fileSource);

    /**
     * @param FileInterface $file
     *
     * @return FileInterface
     */
    public function deleteFile(FileInterface $file);

    /**
     * @param FolderInterface $folder
     *
     * @throws AlreadyExistsException
     */
    public function validateCreateFolder(FolderInterface $folder);

    /**
     * @param FolderInterface $folder
     *
     * @throws AlreadyExistsException
     */
    public function validateRenameFolder(FolderInterface $folder);

    /**
     * @param FolderInterface $folder
     *
     * @throws AlreadyExistsException
     */
    public function validateMoveFolder(FolderInterface $folder);

    /**
     * @param FolderInterface $folder
     * @param FolderInterface $targetFolder
     *
     * @throws AlreadyExistsException
     */
    public function validateCopyFolder(FolderInterface $folder, FolderInterface $targetFolder);

    /**
     * @param FileInterface $file
     *
     * @throws AlreadyExistsException
     */
    public function validateCreateFile(FileInterface $file);

    /**
     * @param FileInterface $file
     *
     * @throws AlreadyExistsException
     */
    public function validateRenameFile(FileInterface $file);

    /**
     * @param FileInterface $file
     *
     * @throws AlreadyExistsException
     */
    public function validateMoveFile(FileInterface $file);

    /**
     * @param FileInterface $file
     *
     * @throws AlreadyExistsException
     */
    public function validateCopyFile(FileInterface $file);
}