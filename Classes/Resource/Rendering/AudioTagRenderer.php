<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Resource\Rendering;

use FriendsOfTYPO3\Headless\Utility\FileUtility;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Audio tag renderer class
 *
 * @codeCoverageIgnore
 */
class AudioTagRenderer extends \TYPO3\CMS\Core\Resource\Rendering\AudioTagRenderer
{
    /**
     * @return int
     */
    public function getPriority(): int
    {
        return 2;
    }

    /**
     * Render for given File(Reference) html output
     *
     * @param FileInterface $file
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     * @param array $options
     * @param bool $usedPathsRelativeToCurrentScript See $file->getPublicUrl()
     * @return string
     */
    public function render(FileInterface $file, $width, $height, array $options = [], $usedPathsRelativeToCurrentScript = false): string
    {
        if ($options['returnUrl'] === true) {

            // If autoplay isn't set manually check if $file is a FileReference take autoplay from there
            if (!isset($options['autoplay']) && $file instanceof FileReference) {
                $autoplay = $file->getProperty('autoplay');
                if ($autoplay !== null) {
                    $options['autoplay'] = $autoplay;
                }
            }

            $additionalAttributes = [];
            if (isset($options['additionalAttributes']) && is_array($options['additionalAttributes'])) {
                $additionalAttributes[] = GeneralUtility::implodeAttributes($options['additionalAttributes'], true, true);
            }
            if (isset($options['data']) && is_array($options['data'])) {
                array_walk($options['data'], function (&$value, $key) {
                    $value = 'data-' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                });
                $additionalAttributes[] = implode(' ', $options['data']);
            }
            if (!isset($options['controls']) || !empty($options['controls'])) {
                $additionalAttributes[] = 'controls';
            }
            if (!empty($options['autoplay'])) {
                $additionalAttributes[] = 'autoplay';
            }
            if (!empty($options['muted'])) {
                $additionalAttributes[] = 'muted';
            }
            if (!empty($options['loop'])) {
                $additionalAttributes[] = 'loop';
            }
            foreach (['class', 'dir', 'id', 'lang', 'style', 'title', 'accesskey', 'tabindex', 'onclick', 'preload', 'controlsList'] as $key) {
                if (!empty($options[$key])) {
                    $additionalAttributes[] = $key . '="' . htmlspecialchars($options[$key]) . '"';
                }
            }

            return htmlspecialchars(GeneralUtility::makeInstance(FileUtility::class)->getAbsoluteUrl($file->getPublicUrl($usedPathsRelativeToCurrentScript)), ENT_QUOTES | ENT_HTML5);
        }
        return parent::render(...func_get_args());
    }
}
