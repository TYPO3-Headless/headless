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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Video tag renderer class
 *
 * @codeCoverageIgnore
 */
class VideoTagRenderer extends \TYPO3\CMS\Core\Resource\Rendering\VideoTagRenderer
{
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
        if (($options['returnUrl'] ?? false) === true) {
            return htmlspecialchars(GeneralUtility::makeInstance(FileUtility::class)->getAbsoluteUrl($file->getPublicUrl($usedPathsRelativeToCurrentScript)), ENT_QUOTES | ENT_HTML5);
        }
        return parent::render(...func_get_args());
    }
}
