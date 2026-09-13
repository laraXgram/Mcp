<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use Generator;
use LaraGram\Container\Container;
use LaraGram\Contracts\Config\Repository;
use LaraGram\Mcp\Enums\MetaKey;
use LaraGram\Mcp\Server\Cancellation;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Server\Subscriptions\Hub;
use LaraGram\Mcp\Server\Transport\CoroutineStdioTransport;
use LaraGram\Mcp\Server\Transport\HttpTransport;
use LaraGram\Mcp\Server\Transport\StdioTransport;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class Listen implements Method
{
    /**
     * @return Generator<int, JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator
    {
        $container = Container::getInstance();
        $meta = [MetaKey::SUBSCRIPTION_ID->value => $request->id];

        // A plain STDIO server handles one message at a time, so it cannot hold a stream open
        // and honors no notification types (use "mcp:start --coroutine" for subscriptions).
        $transport = $container->bound('mcp.transport') ? $container->make('mcp.transport') : null;
        $canStream = ! ($transport instanceof StdioTransport) || $transport instanceof CoroutineStdioTransport;

        $accepted = $canStream ? $this->accepted((array) $request->get('notifications', []), $context) : [];

        $config = $container->make('config');
        $timeout = $this->timeout($config);
        $interval = max(0.05, (float) $config->get('mcp.subscriptions.poll_interval', 1.0));
        $keepAlive = (float) $config->get('mcp.subscriptions.keep_alive', 15);
        $hub = $container->make(Hub::class);

        return (function () use ($request, $accepted, $meta, $timeout, $interval, $keepAlive, $hub, $container, $transport): Generator {
            yield JsonRpcResponse::notification('notifications/subscriptions/acknowledged', [
                '_meta' => $meta,
                'notifications' => $accepted === [] ? (object) [] : $accepted,
            ]);

            if ($accepted !== []) {
                $cursor = $hub->cursor();
                $deadline = $timeout > 0 ? microtime(true) + $timeout : null;
                $lastWrite = microtime(true);

                while (! Cancellation::isCancelled($request->id) && ($deadline === null || microtime(true) < $deadline)) {
                    if ($this->disconnected($container)) {
                        break;
                    }

                    foreach ($hub->since($cursor) as $event) {
                        if ($this->matches($event, $accepted)) {
                            $lastWrite = microtime(true);

                            yield JsonRpcResponse::notification($event['method'], ['_meta' => $meta, ...$event['params']]);
                        }
                    }

                    if ($keepAlive > 0 && $transport instanceof HttpTransport && microtime(true) - $lastWrite >= $keepAlive) {
                        $lastWrite = microtime(true);

                        $transport->keepAlive();
                    }

                    usleep((int) ($interval * 1_000_000));
                }
            }

            Cancellation::forget($request->id);

            yield JsonRpcResponse::result($request->id, ['_meta' => $meta]);
        })();
    }

    /**
     * The stream timeout, kept below Surge's max execution time so the stream closes
     * gracefully (and the client listens again) instead of the worker being killed.
     */
    protected function timeout(Repository $config): float
    {
        $timeout = (float) $config->get('mcp.subscriptions.timeout', 300);
        $limit = isset($_SERVER['LARAGRAM_SURGE']) ? (float) $config->get('surge.max_execution_time', 0) : 0.0;

        if ($limit <= 0) {
            return $timeout;
        }

        $cap = max(1.0, $limit - min(5.0, $limit / 5));

        return $timeout > 0 ? min($timeout, $cap) : $cap;
    }

    /**
     * Determine if the client of the stream has gone away.
     *
     * connection_aborted() is only updated after a failed write under PHP-FPM and is
     * always 0 under Surge (Swoole), which binds its own check for streamed responses.
     */
    protected function disconnected(Container $container): bool
    {
        if (connection_aborted() === 1) {
            return true;
        }

        return $container->bound('surge.disconnected') && ($container->make('surge.disconnected'))() === true;
    }

    /**
     * The subset of the requested notifications the server declared support for.
     *
     * @param  array<string, mixed>  $requested
     * @return array<string, mixed>
     */
    protected function accepted(array $requested, ServerContext $context): array
    {
        $capabilities = $context->serverCapabilities;
        $accepted = [];

        foreach (['toolsListChanged' => 'tools', 'promptsListChanged' => 'prompts', 'resourcesListChanged' => 'resources'] as $filter => $capability) {
            if (($requested[$filter] ?? false) === true && ($capabilities[$capability]['listChanged'] ?? false) === true) {
                $accepted[$filter] = true;
            }
        }

        $uris = array_values(array_filter((array) ($requested['resourceSubscriptions'] ?? []), 'is_string'));

        if ($uris !== [] && ($capabilities['resources']['subscribe'] ?? false) === true) {
            $accepted['resourceSubscriptions'] = $uris;
        }

        return $accepted;
    }

    /**
     * @param  array{method: string, params: array<string, mixed>}  $event
     * @param  array<string, mixed>  $accepted
     */
    protected function matches(array $event, array $accepted): bool
    {
        return match ($event['method']) {
            Hub::TOOLS_LIST_CHANGED => isset($accepted['toolsListChanged']),
            Hub::PROMPTS_LIST_CHANGED => isset($accepted['promptsListChanged']),
            Hub::RESOURCES_LIST_CHANGED => isset($accepted['resourcesListChanged']),
            Hub::RESOURCE_UPDATED => in_array($event['params']['uri'] ?? null, $accepted['resourceSubscriptions'] ?? [], true),
            default => false,
        };
    }
}
