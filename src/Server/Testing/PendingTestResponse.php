<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Testing;

use LaraGram\Container\Container;
use LaraGram\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Server;
use LaraGram\Mcp\Server\Contracts\HasUriTemplate;
use LaraGram\Mcp\Server\Primitive;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Transport\FakeTransporter;
use LaraGram\Mcp\Support\UriTemplate;
use LaraGram\Mcp\Transport\JsonRpcRequest;
use LaraGram\Mcp\Transport\JsonRpcResponse;
use Stringable;

class PendingTestResponse
{
    /**
     * @param  class-string<Server>  $serverClass
     */
    public function __construct(
        protected Container $app,
        protected string $serverClass
    ) {
        //
    }

    public function tools(): TestListResponse
    {
        $server = $this->initializeServer();
        $tools = [];
        $cursor = null;

        do {
            $request = new JsonRpcRequest(
                uniqid(),
                'tools/list',
                $cursor !== null ? ['cursor' => $cursor] : [],
            );

            $response = $this->executeRequest($server, $request);

            if (! $response instanceof JsonRpcResponse) {
                throw new InvalidArgumentException('Expected a JsonRpcResponse for [tools/list].');
            }

            $result = $response->toArray()['result'] ?? null;

            if (! is_array($result) || ! is_array($result['tools'] ?? null)) {
                throw new InvalidArgumentException('Invalid tools/list response from server.');
            }

            $tools = [...$tools, ...$result['tools']];
            $cursor = is_string($result['nextCursor'] ?? null) ? $result['nextCursor'] : null;
        } while ($cursor !== null);

        return new TestListResponse($tools);
    }

    public function resources(): TestListResponse
    {
        $server = $this->initializeServer();
        $resources = [];
        $cursor = null;

        do {
            $request = new JsonRpcRequest(
                uniqid(),
                'resources/list',
                $cursor !== null ? ['cursor' => $cursor] : [],
            );

            $response = $this->executeRequest($server, $request);

            if (! $response instanceof JsonRpcResponse) {
                throw new InvalidArgumentException('Expected a JsonRpcResponse for [resources/list].');
            }

            $result = $response->toArray()['result'] ?? null;

            if (! is_array($result) || ! is_array($result['resources'] ?? null)) {
                throw new InvalidArgumentException('Invalid resources/list response from server.');
            }

            $resources = [...$resources, ...$result['resources']];
            $cursor = is_string($result['nextCursor'] ?? null) ? $result['nextCursor'] : null;
        } while ($cursor !== null);

        return new TestListResponse($resources);
    }

    public function prompts(): TestListResponse
    {
        $server = $this->initializeServer();
        $prompts = [];
        $cursor = null;

        do {
            $request = new JsonRpcRequest(
                uniqid(),
                'prompts/list',
                $cursor !== null ? ['cursor' => $cursor] : [],
            );

            $response = $this->executeRequest($server, $request);

            if (! $response instanceof JsonRpcResponse) {
                throw new InvalidArgumentException('Expected a JsonRpcResponse for [prompts/list].');
            }

            $result = $response->toArray()['result'] ?? null;

            if (! is_array($result) || ! is_array($result['prompts'] ?? null)) {
                throw new InvalidArgumentException('Invalid prompts/list response from server.');
            }

            $prompts = [...$prompts, ...$result['prompts']];
            $cursor = is_string($result['nextCursor'] ?? null) ? $result['nextCursor'] : null;
        } while ($cursor !== null);

        return new TestListResponse($prompts);
    }

    /**
     * @param  class-string<Tool>|Tool  $tool
     * @param  array<string, mixed>  $arguments
     */
    public function tool(Tool|string $tool, array $arguments = []): TestResponse
    {
        return $this->run('tools/call', $tool, $arguments);
    }

    /**
     * @param  class-string<Prompt>|Prompt  $prompt
     * @param  array<string, mixed>  $arguments
     */
    public function prompt(Prompt|string $prompt, array $arguments = []): TestResponse
    {
        return $this->run('prompts/get', $prompt, $arguments);
    }

    /**
     * @param  class-string<Resource>|Resource  $resource
     * @param  array<string, mixed>  $arguments
     */
    public function resource(Resource|string $resource, array $arguments = []): TestResponse
    {
        return $this->run('resources/read', $resource, $arguments);
    }

    /**
     * @param  class-string<Primitive>|Primitive  $primitive
     * @param  array<string, mixed>  $currentArgs
     */
    public function completion(
        Primitive|string $primitive,
        string $argumentName,
        string $argumentValue = '',
        array $currentArgs = []
    ): TestResponse {
        $primitive = $this->resolvePrimitive($primitive);
        $server = $this->initializeServer();

        $request = new JsonRpcRequest(
            uniqid(),
            'completion/complete',
            [
                'ref' => $this->buildCompletionRef($primitive),
                'argument' => [
                    'name' => $argumentName,
                    'value' => $argumentValue,
                ],
                'context' => [
                    'arguments' => $currentArgs,
                ],
            ],
        );

        $response = $this->executeRequest($server, $request);

        return new TestResponse($primitive, $response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCompletionRef(Primitive $primitive): array
    {
        return match (true) {
            $primitive instanceof Prompt => [
                'type' => 'ref/prompt',
                'name' => $primitive->name(),
            ],
            $primitive instanceof Resource => [
                'type' => 'ref/resource',
                'uri' => $primitive->uri(),
            ],
            default => throw new InvalidArgumentException('Unsupported primitive type for completion.'),
        };
    }

    protected function resolvePrimitive(Primitive|string $primitive): Primitive
    {
        return is_string($primitive)
            ? Container::getInstance()->make($primitive)
            : $primitive;
    }

    protected function initializeServer(): Server
    {
        $server = Container::getInstance()->make(
            $this->serverClass,
            ['transport' => new FakeTransporter]
        );

        $server->start();

        return $server;
    }

    protected function executeRequest(Server $server, JsonRpcRequest $request): mixed
    {
        try {
            return (fn (): iterable|JsonRpcResponse => $this->runMethodHandle($request, $this->createContext()))->call($server);
        } catch (JsonRpcException $jsonRpcException) {
            return $jsonRpcException->toJsonRpcResponse();
        }
    }

    public function actingAs(Authenticatable $user, ?string $guard = null): static
    {
        if (property_exists($user, 'wasRecentlyCreated')) {
            $user->wasRecentlyCreated = false;
        }

        $this->app['auth']->guard($guard)->setUser($user);

        $this->app['auth']->shouldUse($guard);

        return $this;
    }

    /**
     * @param  class-string<Primitive>|Primitive  $primitive
     * @param  array<string, mixed>  $arguments
     *
     * @throws JsonRpcException
     */
    protected function run(string $method, Primitive|string $primitive, array $arguments = []): TestResponse
    {
        $primitive = $this->resolvePrimitive($primitive);
        $server = $this->initializeServer();

        $params = [
            ...$primitive->toMethodCall(),
            'arguments' => $arguments,
        ];

        if ($method === 'resources/read' && $primitive instanceof HasUriTemplate) {
            $params['uri'] = $this->expandUriTemplate($primitive->uriTemplate(), $arguments);
        }

        $request = new JsonRpcRequest(uniqid(), $method, $params);

        $response = $this->executeRequest($server, $request);

        return new TestResponse($primitive, $response);
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    protected function expandUriTemplate(UriTemplate $template, array $variables): string
    {
        $expanded = (string) $template;

        foreach ($template->variableNames() as $name) {
            if (! array_key_exists($name, $variables)) {
                throw new InvalidArgumentException("Missing value for URI template variable [{$name}].");
            }

            $value = $variables[$name];

            if (! is_scalar($value) && ! $value instanceof Stringable) {
                throw new InvalidArgumentException("URI template variable [{$name}] must be a scalar or Stringable value.");
            }

            $value = (string) $value;

            if (str_contains($value, '/')) {
                throw new InvalidArgumentException("URI template variable [{$name}] value must not contain '/'.");
            }

            $expanded = str_replace('{'.$name.'}', $value, $expanded);
        }

        return $expanded;
    }
}
