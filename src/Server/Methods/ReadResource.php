<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use Generator;
use LaraGram\Container\Container;
use LaraGram\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use LaraGram\Mcp\Enums\ErrorCode;
use LaraGram\Mcp\Events\ResourceRead;
use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\AppResource;
use LaraGram\Mcp\Server\Contracts\HasUriTemplate;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use LaraGram\Mcp\Server\Methods\Concerns\ResolvesResources;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class ReadResource implements Method
{
    use InteractsWithResponses;
    use ResolvesResources;

    /**
     * @return Generator<JsonRpcResponse>|JsonRpcResponse
     *
     * @throws BindingResolutionException
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        $uri = $request->get('uri');

        try {
            $resource = $this->resolveResource($uri, $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException(
                $invalidArgumentException->getMessage(),
                ErrorCode::INVALID_PARAMS->value,
                $request->id,
            );
        }

        $response = $this->callHandler(fn (): mixed => $this->invokeResource($resource, $uri), $request);

        if (! is_iterable($response)) {
            return tap(
                $this->toJsonRpcResponse($request, $response, $this->serializable($resource, $uri)),
                fn (JsonRpcResponse $response) => $this->dispatchRead($resource, $uri, $response),
            );
        }

        return (function () use ($request, $response, $resource, $uri): Generator {
            $last = null;

            foreach ($this->toJsonRpcStreamedResponse($request, $response, $this->serializable($resource, $uri)) as $message) {
                yield $last = $message;
            }

            $this->dispatchRead($resource, $uri, $last);
        })();
    }

    protected function dispatchRead(Resource $resource, string $uri, ?JsonRpcResponse $response): void
    {
        $container = Container::getInstance();

        if ($container->bound('events')) {
            $container->make('events')->dispatch(new ResourceRead($resource, $uri, $response?->toArray()['result'] ?? null));
        }
    }

    /**
     * @throws BindingResolutionException
     */
    protected function invokeResource(Resource $resource, string $uri): mixed
    {
        $container = Container::getInstance();

        $request = $container->make(Request::class);
        $request->setUri($uri);

        if ($resource instanceof HasUriTemplate) {
            $variables = $resource->uriTemplate()->match($uri) ?? [];
            $request->merge($variables);
        }

        $container->instance(Request::class, $request);

        if ($resource instanceof AppResource) {
            $container->instance('mcp.library_scripts', $resource->libraryScripts());
        }

        try {
            // @phpstan-ignore-next-line
            return $container->call([$resource, 'handle']);
        } finally {
            $container->forgetInstance(Request::class);
            $container->forgetInstance('mcp.library_scripts');
        }
    }

    protected function serializable(Resource $resource, string $uri): callable
    {
        $appMeta = $resource instanceof AppResource ? $resource->resolvedAppMeta() : null;

        return fn (ResponseFactory $factory): array => $factory->mergeMeta([
            'contents' => $factory->responses()->map(function (Response $response) use ($resource, $uri, $appMeta): array {
                $content = [
                    ...$response->content()->toResource($resource),
                    'uri' => $uri,
                ];

                if ($appMeta !== null && $appMeta !== []) {
                    $content['_meta'] = array_merge($content['_meta'] ?? [], [
                        'ui' => $appMeta,
                    ]);
                }

                return $content;
            })->all(),
        ]);
    }
}
