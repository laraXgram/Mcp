<?php

declare(strict_types=1);

namespace LaraGram\Mcp;

use LaraGram\Container\Container;
use LaraGram\Support\Arr;
use LaraGram\Support\Collection;
use LaraGram\Mcp\Client\ClientManager;
use LaraGram\Mcp\Client\Contracts\Transport;
use LaraGram\Mcp\Client\Exceptions\AuthorizationRequiredException;
use LaraGram\Mcp\Client\Methods\Ping;
use LaraGram\Mcp\Client\Methods\Prompts\GetPrompt;
use LaraGram\Mcp\Client\Methods\Prompts\ListPrompts;
use LaraGram\Mcp\Client\Methods\Resources\ListResources;
use LaraGram\Mcp\Client\Methods\Resources\ReadResource;
use LaraGram\Mcp\Client\Methods\Tools\CallTool;
use LaraGram\Mcp\Client\Methods\Tools\ListTools;
use LaraGram\Mcp\Client\Primitives\Prompt;
use LaraGram\Mcp\Client\Primitives\Resource;
use LaraGram\Mcp\Client\Primitives\Tool;
use LaraGram\Mcp\Client\Protocol;
use LaraGram\Mcp\Client\ResponseCache;
use LaraGram\Mcp\Client\Schema\DiscoverResult;
use LaraGram\Mcp\Client\Schema\InitializeResult;
use LaraGram\Mcp\Client\Schema\PromptResult;
use LaraGram\Mcp\Client\Schema\ResourceReadResult;
use LaraGram\Mcp\Client\Schema\ToolResult;
use LaraGram\Mcp\Client\Transport\HttpTransport;
use LaraGram\Mcp\Client\Transport\StdioTransport;
use LaraGram\Mcp\Client\Transport\TransportFactory;
use LaraGram\Mcp\Enums\ErrorCode;
use LaraGram\Mcp\Enums\ProtocolVersion;
use LaraGram\Mcp\Exceptions\ClientException;
use LaraGram\Mcp\Exceptions\JsonRpcException;
use LaraGram\Mcp\Schema\Implementation;
use LaraGram\Mcp\Support\MirroredParameters;

class Client
{
    protected Protocol $protocol;

    protected ?string $name = null;

    public function __construct(
        protected Transport $transport,
        public ?Implementation $clientInfo = null,
    ) {
        $this->clientInfo = $clientInfo ?? $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo);
    }

    protected function defaultClientInfo(): Implementation
    {
        return new Implementation(
            name: config('app.name', 'LaraGram MCP Client'),
            version: '0.0.1',
        );
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param  array<int, string>  $args
     */
    public static function local(string $command, array $args = []): static
    {
        return new static(new StdioTransport($command, $args));
    }

    public static function web(string $url): WebClient
    {
        return new WebClient(new HttpTransport($url));
    }

    public function withCache(?string $store = null, ?string $for = null): static
    {
        $this->protocol->useCache(new ResponseCache($store, $for));

        return $this;
    }

    /**
     * Declare client capabilities sent with every request, e.g. ['elicitation' => new stdClass].
     *
     * @param  array<string, mixed>  $capabilities
     */
    public function withCapabilities(array $capabilities): static
    {
        $this->protocol->useCapabilities($capabilities);

        return $this;
    }

    public function withoutCache(): static
    {
        $this->protocol->useCache(null);

        return $this;
    }

    public function withTimeout(float $seconds): static
    {
        $this->transport->setTimeoutSeconds($seconds);

        return $this;
    }

    public function connect(): static
    {
        $this->protocol->connect();

        return $this;
    }

    public function disconnect(): void
    {
        $this->protocol->disconnect();
    }

    public function connected(): bool
    {
        return $this->protocol->connected();
    }

    public function initializeResult(): ?InitializeResult
    {
        return $this->protocol->initializeResult();
    }

    public function discoverResult(): ?DiscoverResult
    {
        return $this->protocol->discoverResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $this->protocol->connect();

        return $this->protocol->capabilities();
    }

    public function serverInfo(): ?Implementation
    {
        $this->protocol->connect();

        return $this->protocol->serverInfo();
    }

    public function instructions(): ?string
    {
        $this->protocol->connect();

        return $this->protocol->instructions();
    }

    public function withProtocolVersion(?ProtocolVersion $version): static
    {
        if ($version instanceof ProtocolVersion && ! in_array($version->value, ProtocolVersion::clientSupported(), true)) {
            throw new ClientException(sprintf(
                'This client does not support protocol version [%s]. It supports [%s].',
                $version->value,
                implode(', ', ProtocolVersion::clientSupported()),
            ));
        }

        $this->protocol->pinProtocolVersion($version);

        return $this;
    }

    public function protocolVersion(): ProtocolVersion
    {
        $this->protocol->connect();

        return $this->protocol->connectionProtocol();
    }

    public function ping(): void
    {
        (new Ping)->handle($this->protocol);
    }

    /**
     * @param  iterable<string, Tool>|null  $default
     * @return Collection<string, Tool>
     */
    public function tools(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListTools(client: $this, limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return Collection::make($default);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $inputResponses  Responses to the input requests of a previous "input_required" result.
     */
    public function callTool(Tool|string $tool, array $arguments = [], array $inputResponses = [], ?string $requestState = null): ToolResult
    {
        $name = $tool instanceof Tool ? $tool->name : $tool;
        $mirroredParameters = $tool instanceof Tool ? $tool->mirroredParameters() : null;

        try {
            return (new CallTool($name, $arguments, $mirroredParameters, $inputResponses, $requestState))->handle($this->protocol);
        } catch (JsonRpcException $jsonRpcException) {
            if ($jsonRpcException->getCode() !== ErrorCode::HEADER_MISMATCH->value) {
                throw $jsonRpcException;
            }

            $refreshed = $this->tools()->get($name)?->mirroredParameters();

            if (! $refreshed instanceof MirroredParameters
                || $refreshed->headers($arguments) === ($mirroredParameters?->headers($arguments) ?? [])) {
                throw $jsonRpcException;
            }

            return (new CallTool($name, $arguments, $refreshed, $inputResponses, $requestState))->handle($this->protocol);
        }
    }

    /**
     * @param  iterable<string, Prompt>|null  $default
     * @return Collection<string, Prompt>
     */
    public function prompts(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListPrompts(limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return Collection::make($default);
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function getPrompt(string $name, array $arguments = []): PromptResult
    {
        return (new GetPrompt($name, $arguments))->handle($this->protocol);
    }

    /**
     * @param  iterable<string, Resource>|null  $default
     * @return Collection<string, Resource>
     */
    public function resources(?int $limit = null, ?iterable $default = null): Collection
    {
        try {
            return (new ListResources(limit: $limit))->handle($this->protocol);
        } catch (AuthorizationRequiredException $authorizationRequiredException) {
            if ($default === null) {
                throw $authorizationRequiredException;
            }

            return Collection::make($default);
        }
    }

    public function readResource(string $uri): ResourceReadResult
    {
        return (new ReadResource($uri))->handle($this->protocol);
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        if ($this->name !== null) {
            return ['name' => $this->name];
        }

        return [
            'name' => null,
            'clientInfo' => $this->clientInfo,
            'transport' => $this->transport->recipe(),
            'protocolVersion' => $this->protocol->pinnedProtocolVersion()?->value,
            'cache' => $this->protocol->cache(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->name = Arr::get($data, 'name');

        if ($this->name !== null) {
            $resolved = Container::getInstance()->make(ClientManager::class)->build($this->name);

            $this->transport = $resolved->transport;
            $this->clientInfo = $resolved->clientInfo;
            $pinned = $resolved->protocol->pinnedProtocolVersion();
            $cache = $resolved->protocol->cache();
        } else {
            $this->clientInfo = Arr::get($data, 'clientInfo');
            $this->transport = TransportFactory::fromRecipe(Arr::get($data, 'transport'));
            $pinned = ProtocolVersion::tryFrom((string) Arr::get($data, 'protocolVersion'));
            $cache = Arr::get($data, 'cache');
        }

        $this->clientInfo ??= $this->defaultClientInfo();

        $this->protocol = new Protocol($this->transport, $this->clientInfo, $pinned);
        $this->protocol->useCache($cache instanceof ResponseCache ? $cache : null);
    }

    public function __destruct()
    {
        if ($this->connected()) {
            $this->disconnect();
        }
    }
}
