<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram;

use LaraGram\Container\Container;
use LaraGram\Contracts\Cache\LockProvider;
use LaraGram\Contracts\Cache\Repository;
use Throwable;

/**
 * Keeps the most recent updates per scope (a bot connection, a chat, an MTProto
 * session...) in the cache, so MCP resources can serve them to any process.
 */
class UpdateBuffer
{
    /**
     * The number of updates kept per scope.
     */
    public static int $size = 50;

    /**
     * How long (seconds) a scope is kept after its last update.
     */
    public static int $ttl = 86400;

    /**
     * @param  array<string, mixed>  $update
     */
    public static function push(string $scope, array $update): void
    {
        $cache = static::cache();
        $key = static::key($scope);

        $write = function () use ($cache, $key, $update): void {
            $updates = (array) $cache->get($key, []);
            $updates[] = ['received_at' => time(), 'update' => $update];

            $cache->put($key, array_slice($updates, -static::$size), static::$ttl);
        };

        $store = $cache->getStore();

        if (! $store instanceof LockProvider) {
            $write();

            return;
        }

        try {
            $store->lock($key.':lock', 5)->block(2, $write);
        } catch (Throwable) {
            $write();
        }
    }

    /**
     * Get the most recent updates of a scope, oldest first.
     *
     * @return array<int, array{received_at: int, update: array<string, mixed>}>
     */
    public static function get(string $scope, int $limit = 50): array
    {
        return array_slice((array) static::cache()->get(static::key($scope), []), -max(1, $limit));
    }

    protected static function key(string $scope): string
    {
        return 'mcp:telegram:updates:'.$scope;
    }

    protected static function cache(): Repository
    {
        $container = Container::getInstance();

        return $container->make('cache')->store($container->make('config')->get('mcp.subscriptions.store'));
    }
}
