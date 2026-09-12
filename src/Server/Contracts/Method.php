<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Contracts;

use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

interface Method
{
    /**
     * @return iterable<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): iterable|JsonRpcResponse;
}
