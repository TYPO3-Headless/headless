<?php

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

final class JsonDecoder implements JsonDecoderInterface
{
    /**
     * @param array $data
     * @return array
     */
    public function decode(array $data): array
    {
        $json = [];

        foreach ($data as $key => $singleData) {
            if (is_string($singleData)) {
                if ($this->isJson($singleData)) {
                    $json[$key] = json_decode($singleData);
                } else {
                    $json[$key] = $singleData;
                }
            } elseif (is_array($singleData)) {
                $json[$key] = $this->decode($singleData);
            } else {
                $json[$key] = $singleData;
            }
        }
        return $json;
    }

    /**
     * @param mixed $possibleJson
     * @return bool
     */
    public function isJson($possibleJson): bool
    {
        if (is_numeric($possibleJson)) {
            return false;
        }

        $possibleJson = trim((string) $possibleJson);

        if ($possibleJson === '') {
            return false;
        }

        $data = \json_decode($possibleJson);

        if (!is_object($data) && !is_array($data)) {
            return false;
        }

        return $data !== null;
    }
}
