<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use Exception;
use LaraGram\Container\Container;
use LaraGram\Support\Arr;
use InvalidArgumentException;
use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Server;
use LaraGram\Mcp\Server\Completions\CompletionResponse;
use LaraGram\Mcp\Server\Contracts\Completable;
use LaraGram\Mcp\Server\Contracts\HasUriTemplate;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\Methods\Concerns\ResolvesPrompts;
use LaraGram\Mcp\Server\Methods\Concerns\ResolvesResources;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class CompletionComplete implements Method
{
    use ResolvesPrompts;
    use ResolvesResources;

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        if (! $context->hasCapability(Server::CAPABILITY_COMPLETIONS)) {
            throw new JsonRpcException(
                'Server does not support completions capability.',
                -32601,
                $request->id,
            );
        }

        $ref = $request->get('ref');
        $argument = $request->get('argument');

        if (is_null($ref) || is_null($argument)) {
            throw new JsonRpcException(
                'Missing required parameters: ref and argument',
                -32602,
                $request->id,
            );
        }

        try {
            $primitive = $this->resolvePrimitive($ref, $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32602, $request->id);
        }

        if (! $primitive instanceof Completable) {
            $result = CompletionResponse::empty();

            return JsonRpcResponse::result($request->id, [
                'completion' => $result->toArray(),
            ]);
        }

        $argumentName = Arr::get($argument, 'name');
        $argumentValue = Arr::get($argument, 'value', '');

        if (is_null($argumentName)) {
            throw new JsonRpcException(
                'Missing argument name.',
                -32602,
                $request->id,
            );
        }

        $contextArguments = Arr::get($request->get('context'), 'arguments', []);

        try {
            $result = $this->invokeCompletion($primitive, $argumentName, $argumentValue, $contextArguments);
        } catch (Exception) {
            $result = CompletionResponse::empty();
        }

        return JsonRpcResponse::result($request->id, [
            'completion' => $result->toArray(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $ref
     */
    protected function resolvePrimitive(array $ref, ServerContext $context): Prompt|Resource|HasUriTemplate
    {
        return match (Arr::get($ref, 'type')) {
            'ref/prompt' => $this->resolvePrompt(Arr::get($ref, 'name'), $context),
            'ref/resource' => $this->resolveResource(Arr::get($ref, 'uri'), $context),
            default => throw new InvalidArgumentException('Invalid reference type. Expected ref/prompt or ref/resource.'),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function invokeCompletion(
        Completable $primitive,
        string $argumentName,
        string $argumentValue,
        array $context
    ): mixed {
        $container = Container::getInstance();

        $result = $container->call($primitive->complete(...), [
            'argument' => $argumentName,
            'value' => $argumentValue,
            'context' => $context,
        ]);

        return $result->resolve($argumentValue);
    }
}
