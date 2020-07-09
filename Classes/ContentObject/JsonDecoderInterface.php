<?php
declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\ContentObject;


interface JsonDecoderInterface
{
    public function decode(array $data): array;
    public function isJson($possibleJson): bool;
}
