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
 * Video tag renderer class
 *
 * @codeCoverageIgnore
 */
class VideoTagRenderer extends \TYPO3\CMS\Core\Resource\Rendering\VideoTagRenderer
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

            $attributes = [];
            if (isset($options['additionalAttributes']) && is_array($options['additionalAttributes'])) {
                $attributes[] = GeneralUtility::implodeAttributes($options['additionalAttributes'], true, true);
            }
            if (isset($options['data']) && is_array($options['data'])) {
                array_walk($options['data'], function (&$value, $key) {
                    $value = 'data-' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
                });
                $attributes[] = implode(' ', $options['data']);
            }
            if ((int)$width > 0) {
                $attributes[] = 'width="' . (int)$width . '"';
            }
            if ((int)$height > 0) {
                $attributes[] = 'height="' . (int)$height . '"';
            }
            if (!isset($options['controls']) || !empty($options['controls'])) {
                $attributes[] = 'controls';
            }
            if (!empty($options['autoplay'])) {
                $attributes[] = 'autoplay';
            }
            if (!empty($options['muted'])) {
                $attributes[] = 'muted';
            }
            if (!empty($options['loop'])) {
                $attributes[] = 'loop';
            }
            if (isset($options['additionalConfig']) && is_array($options['additionalConfig'])) {
                foreach ($options['additionalConfig'] as $key => $value) {
                    if ((bool)$value) {
                        $attributes[] = htmlspecialchars($key);
                    }
                }
            }

            return htmlspecialchars(GeneralUtility::makeInstance(FileUtility::class)->getAbsoluteUrl($file->getPublicUrl($usedPathsRelativeToCurrentScript)), ENT_QUOTES | ENT_HTML5);
        }
        return parent::render(...func_get_args());
    }
}
