<?php
declare(strict_types = 1);

namespace FriendsOfTYPO3\Headless\Utility;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

/**
 * ContentUtility
 */
class ContentUtility
{
    /**
     * @param $content
     * @param array $configuration
     * @return string|null
     */
    public function groupContent($content, array $configuration): string
    {
        $contents = $this->cObj->cObjGetSingle($configuration['10'], $configuration['10.']);
        $contentData = array_map('trim', (array_slice(explode('###BREAK###', $contents), 0, -1)));
        return json_encode($this->groupContentElementsByColPos($contentData));
    }

    /**
     * @param array $contentElements
     * @return array
     */
    protected function groupContentElementsByColPos(array $contentElements): array
    {
        $data = [];

        foreach ($contentElements as $key => $element) {
            $element = json_decode($element);
            $data['colPos' . $element->colPos][] = $element;
        }

        return $data;
    }
}
