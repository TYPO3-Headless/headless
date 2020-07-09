<?php
declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;

interface JsonDecoderInterface
{
    /**
     * @param array $data
     * @return array
     */
    public function decode(array $data): array;

    /**
     * @param mixed $possibleJson
     * @return bool
     */
    public function isJson($possibleJson): bool;
}
