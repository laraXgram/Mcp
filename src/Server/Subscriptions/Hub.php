<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Subscriptions;

use LaraGram\Contracts\Cache\Repository;

/**
 * A change feed for "subscriptions/listen" streams, kept in the cache so events
 * published in any process (HTTP workers, bot listeners, queue workers, the MTProto
 * pump) reach every open stream.
 */
class Hub
{
    public const TOOLS_LIST_CHANGED = 'notifications/tools/list_changed';

    public const PROMPTS_LIST_CHANGED = 'notifications/prompts/list_changed';

    public const RESOURCES_LIST_CHANGED = 'notifications/resources/list_changed';

    public const RESOURCE_UPDATED = 'notifications/resources/updated';

    public function __construct(
        protected Repository $cache,
        protected string $prefix = 'mcp:subscriptions',
        protected int $ttl = 600,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function publish(string $method, array $params = []): void
    {
        // Some stores (e.g. database) only increment existing keys.
        $this->cache->add($this->prefix.':sequence', 0);

        $sequence = (int) $this->cache->increment($this->prefix.':sequence');

        $this->cache->put($this->prefix.':event:'.$sequence, ['method' => $method, 'params' => $params], $this->ttl);
    }

    /**
     * The sequence number of the latest event.
     */
    public function cursor(): int
    {
        return (int) $this->cache->get($this->prefix.':sequence', 0);
    }

    /**
     * Get the events published after the given cursor and advance it.
     *
     * @return array<int, array{method: string, params: array<string, mixed>}>
     */
    public function since(int &$cursor): array
    {
        $latest = $this->cursor();
        $events = [];

        // Only look back a bounded window, e.g. after the cache was flushed.
        for ($sequence = max($cursor + 1, $latest - 1000); $sequence <= $latest; $sequence++) {
            $event = $this->cache->get($this->prefix.':event:'.$sequence);

            if (is_array($event)) {
                $events[] = $event;
            }
        }

        $cursor = max($cursor, $latest);

        return $events;
    }
}
