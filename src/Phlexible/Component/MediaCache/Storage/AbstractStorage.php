<?php

/*
 * This file is part of the phlexible package.
 *
 * (c) Stephan Wentz <sw@brainbits.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phlexible\Component\MediaCache\Storage;

use Phlexible\Component\MediaManager\Volume\ExtendedFileInterface;

/**
 * Abstract storage
 *
 * @author Peter Fahsel <pfahsel@brainbits.net>
 */
abstract class AbstractStorage implements  StorageInterface
{
    /**
     * download path
     */
    const MEDIA_PATH_DOWNLOAD = 'download';

    /**
     * inline path
     */
    const MEDIA_PATH_INLINE = 'inline';

    /**
     * media path
     */
    const MEDIA_PATH_MEDIA = 'media';

    /**
     * icon path
     */
    const MEDIA_PATH_ICON = 'icon';

    /**
     * image path index
     * @todo remove, only in here for frontentmediamanager field image template compatibility reasons
     */
    const MEDIA_PATH_IMAGE = 'image';

    /**
     * {@inheritdoc}
     */
    public function getUrls(ExtendedFileInterface $file, $baseUrl)
    {
        $fileId        = $file->getId();
        $fileName      = rawurlencode($file->getName());
        $iconFileName  = rawurlencode($this->replaceExtension($file->getName(), '.gif'));

        $urls     = [
            self::MEDIA_PATH_DOWNLOAD => $baseUrl . '/' . self::MEDIA_PATH_DOWNLOAD . '/' . $fileId . '/' . $fileName,
            self::MEDIA_PATH_INLINE   => $baseUrl . '/' . self::MEDIA_PATH_INLINE . '/' . $fileId . '/' . $fileName,
            self::MEDIA_PATH_ICON     => $baseUrl . '/' . self::MEDIA_PATH_ICON . '/' . $fileId . '/16/' . $iconFileName,
        ];

        return $urls;
    }

    /**
     * Remove a filename extension
     *
     * @param string $filename
     *
     * @return string
     */
    protected function removeExtension($filename)
    {
        if (strrpos($filename, '.') !== false) {
            $filename = substr($filename, 0, strrpos($filename, '.'));
        }

        return $filename;
    }

    /**
     * Remove a filename extension
     *
     * @param string $filename
     * @param string $ext
     *
     * @return string
     */
    protected function replaceExtension($filename, $ext)
    {
        $ext      = str_replace('.', '', $ext);
        $filename = $this->removeExtension($filename);

        return $filename . '.' . $ext;
    }
}
