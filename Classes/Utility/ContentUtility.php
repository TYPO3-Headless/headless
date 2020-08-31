<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 * (c) 2020
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Utility;

/***
 *
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 *
 *  (c) 2019
 *
 ***/

/**
 * ContentUtility
 *
 * This class group elements by column position, for easier frontend rendering.
 */
class ContentUtility
{
    /**
     * This method takes whole content as JSON string, breaks it per element, and pass to groupContentElementByColPos method to group content by colPos.
     *
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
     * Groups content by colPos.
     *
     * @param array $contentElements
     * @return array
     */
    protected function groupContentElementsByColPos(array $contentElements): array
    {
        $data = [];

        foreach ($contentElements as $key => $element) {
            // wrap all INT_SCRIPT occurences for later json enocding
            $element = preg_replace(
                '/(' . preg_quote('<!--INT_SCRIPT.', '/') . '[0-9a-z]{32}' . preg_quote('-->', '/') . ')/',
                'HEADLESS_JSON_START<<\1>>HEADLESS_JSON_END',
                $element
            );

            $element = json_decode($element);
            if ($element->colPos >= 0) {
                $data['colPos' . $element->colPos][] = $element;
            }
        }

        return $data;
    }
}
