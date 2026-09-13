<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server;

use Generator;
use LaraGram\Container\Container;
use LaraGram\Mcp\Events\ToolCalled;
use LaraGram\Mcp\Events\ToolCalling;
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
        $arguments = (array) ($request->params['arguments'] ?? []);

        $this->dispatch(new ToolCalling($tool, $arguments));

        // @phpstan-ignore-next-line
        $response = $this->callHandler(fn (): mixed => Container::getInstance()->call([$tool, 'handle']), $request);

        if (! is_iterable($response)) {
            return tap(
                $this->toJsonRpcResponse($request, $response, $this->serializable($tool)),
                fn (JsonRpcResponse $response) => $this->dispatch(new ToolCalled($tool, $arguments, $response->toArray()['result'] ?? null)),
            );
        }

        return (function () use ($tool, $request, $response, $arguments): Generator {
            $last = null;

            foreach ($this->toJsonRpcStreamedResponse($request, $response, $this->serializable($tool)) as $message) {
                yield $last = $message;
            }

            $this->dispatch(new ToolCalled($tool, $arguments, $last?->toArray()['result'] ?? null));
        })();
    }

    protected function dispatch(object $event): void
    {
        $container = Container::getInstance();

        if ($container->bound('events')) {
            $container->make('events')->dispatch($event);
        }
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
