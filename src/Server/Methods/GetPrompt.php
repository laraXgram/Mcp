<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods;

use Generator;
use LaraGram\Container\Container;
use InvalidArgumentException;
use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Contracts\Method;
use LaraGram\Mcp\Server\Methods\Concerns\InteractsWithResponses;
use LaraGram\Mcp\Server\Methods\Concerns\ResolvesPrompts;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\ServerContext;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;

class GetPrompt implements Method
{
    use InteractsWithResponses;
    use ResolvesPrompts;

    /**
     * @return Generator<JsonRpcResponse>|JsonRpcResponse
     */
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        try {
            $prompt = $this->resolvePrompt($request->get('name'), $context);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new JsonRpcException($invalidArgumentException->getMessage(), -32602, $request->id);
        }

        // @phpstan-ignore-next-line
        $response = $this->callHandler(fn (): mixed => Container::getInstance()->call([$prompt, 'handle']), $request);

        return is_iterable($response)
            ? $this->toJsonRpcStreamedResponse($request, $response, $this->serializable($prompt))
            : $this->toJsonRpcResponse($request, $response, $this->serializable($prompt));
    }

    /**
     * @return callable(ResponseFactory): array<string, mixed>
     */
    protected function serializable(Prompt $prompt): callable
    {
        return fn (ResponseFactory $factory): array => $factory->mergeMeta([
            'description' => $prompt->description(),
            'messages' => $factory->responses()->map(fn (Response $response): array => [
                'role' => $response->role()->value,
                'content' => $response->content()->toPrompt($prompt),
            ])->all(),
        ]);
    }
}
