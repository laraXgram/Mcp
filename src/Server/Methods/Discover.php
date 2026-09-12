<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class Discover implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, [
            'supportedVersions' => $context->supportedProtocolVersions,
            'capabilities' => $context->serverCapabilities ?: (object) [],
            'instructions' => $context->instructions,
        ]);
    }
}
