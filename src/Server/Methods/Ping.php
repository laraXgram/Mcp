<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class Ping implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        return JsonRpcResponse::result($request->id, []);
    }
}
