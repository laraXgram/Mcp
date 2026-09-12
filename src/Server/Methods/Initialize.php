<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use LaraGram\Mcp\Enums\ProtocolVersion;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class Initialize implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $requested = $request->params['protocolVersion'] ?? null;

        return JsonRpcResponse::result($request->id, [
            'protocolVersion' => in_array($requested, ProtocolVersion::initializeSupported(), true)
                ? $requested
                : ProtocolVersion::initializeSupported()[0],
            'capabilities' => $context->serverCapabilities ?: (object) [],
            'serverInfo' => $context->implementation->toArray(),
            'instructions' => $context->instructions,
        ]);
    }
}
