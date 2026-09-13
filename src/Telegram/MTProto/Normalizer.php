<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use JsonSerializable;

/**
 * Makes MTProto results JSON-safe: TL objects become arrays and binary strings
 * (bytes fields such as file references) become {"_bytes": "<base64>"}.
 */
final class Normalizer
{
    public static function normalize(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = match (true) {
                method_exists($value, 'toArray') => $value->toArray(),
                $value instanceof JsonSerializable => $value->jsonSerialize(),
                default => get_object_vars($value),
            };
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
            return ['_bytes' => base64_encode($value)];
        }

        return $value;
    }
}
