<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server;

/**
 * Tracks requests cancelled with "notifications/cancelled" within the current process,
 * so long-running handlers (such as subscription streams) can stop early.
 */
final class Cancellation
{
    /** @var array<string, true> */
    private static array $cancelled = [];

    public static function cancel(int|string $requestId): void
    {
        self::$cancelled[self::key($requestId)] = true;
    }

    public static function isCancelled(int|string $requestId): bool
    {
        return isset(self::$cancelled[self::key($requestId)]);
    }

    public static function forget(int|string $requestId): void
    {
        unset(self::$cancelled[self::key($requestId)]);
    }

    private static function key(int|string $requestId): string
    {
        return get_debug_type($requestId).':'.$requestId;
    }
}
