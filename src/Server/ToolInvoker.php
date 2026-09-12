<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server;

use Generator;
use LaraGram\Container\Container;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Contracts\Errable;
use LaraGram\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class ToolInvoker implements Errable
{
    use InteractsWithResponses;

    public function invoke(Tool $tool, JsonRpcRequest $request): Generator|JsonRpcResponse
    {
        // @phpstan-ignore-next-line
        $response = $this->callHandler(fn (): mixed => Container::getInstance()->call([$tool, 'handle']), $request);

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($tool));
    }

    protected function serializable(Tool $tool): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeStructuredContent(
            $factory->mergeMeta([
                'content' => $factory->responses()->map(fn (Response $response): array => $response->content()->toTool($tool))->all(),
                'isError' => $factory->responses()->contains(fn (Response $response): bool => $response->isError()),
            ])
        );
    }
}
