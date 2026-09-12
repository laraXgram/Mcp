<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use Generator;
use LaraGram\Mcp\Enums\MetaKey;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class Listen implements Method
{
    /**
     * @return Generator<int, JsonRpcResponse>
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator
    {
        yield JsonRpcResponse::notification('notifications/subscriptions/acknowledged', [
            '_meta' => [MetaKey::SUBSCRIPTION_ID->value => $request->id],
            'notifications' => (object) [],
        ]);

        yield JsonRpcResponse::result($request->id, [
            '_meta' => [MetaKey::SUBSCRIPTION_ID->value => $request->id],
        ]);
    }
}
