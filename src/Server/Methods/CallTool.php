<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use Generator;
use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Server\Contracts\Errable;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Server\ToolInvoker;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class CallTool implements Errable, Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context
            ->tools()
            ->first(
                fn ($tool): bool => $tool->name() === $request->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$request->params['name']}] not found.",
                    -32602,
                    $request->id,
                ));

        return (new ToolInvoker)->invoke($tool, $request);
    }
}
