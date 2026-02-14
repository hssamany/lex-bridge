<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Utils;

final class UuidUtil
{
    private function __construct() {}

    public static function generateUuid(): string
    {
        // Use ramsey/uuid or ext/uuid if available
        if (function_exists('uuid_create')) {
            return uuid_create(UUID_TYPE_RANDOM);
        }
        // Fallback: random 32 hex chars with dashes (UUID v4 format)
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
