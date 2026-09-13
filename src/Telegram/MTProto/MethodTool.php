<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\MTProto\Contracts\Invoker;
use LaraGram\MTProto\TL\TLMethod;
use LaraGram\MTProto\TL\TLParameter;
use LaraGram\Support\Str;

/**
 * A tool for a single TL method, generated from the MTProto package's schema.
 */
class MethodTool extends Tool
{
    /**
     * Parameters that are required by the schema but have an obvious default
     * (mirrors the defaults of MTProto's generated method shortcuts).
     *
     * @var array<string, array<string, mixed>>
     */
    public const DEFAULTS = [
        'hash' => ['int' => 0, 'long' => 0],
        'limit' => ['int' => 100],
        'offset_id' => ['int' => 0, 'long' => 0],
        'offset_date' => ['int' => 0],
        'add_offset' => ['int' => 0],
        'max_id' => ['int' => 0, 'long' => 0],
        'min_id' => ['int' => 0, 'long' => 0],
        'offset' => ['int' => 0, 'long' => 0, 'string' => ''],
        'offset_peer' => ['InputPeer' => ['_' => 'inputPeerEmpty']],
        'offset_rate' => ['int' => 0],
        'min_date' => ['int' => 0],
        'max_date' => ['int' => 0],
        'offset_topic' => ['int' => 0],
        'filter' => ['MessagesFilter' => ['_' => 'inputMessagesFilterEmpty']],
    ];

    /** Methods where "hash" is not a cache hash and must be given explicitly. */
    public const HASH_EXCLUSIONS = [
        'account.resetAuthorization',
        'account.resetWebAuthorization',
        'account.changeAuthorizationSettings',
        'messages.checkChatInvite',
        'messages.importChatInvite',
        'account.sendConfirmPhoneCode',
    ];

    public function __construct(protected Toolset $toolset, protected TLMethod $method)
    {
        //
    }

    public function name(): string
    {
        return $this->toolset->prefixValue().str_replace('.', '_', Str::snake($this->method->getFullName()));
    }

    public function title(): string
    {
        return $this->method->getFullName();
    }

    public function description(): string
    {
        return "Invoke the Telegram API method {$this->method->getFullName()} on the MTProto session. Returns {$this->method->getType()}.";
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $mapper = new TypeMapper($schema);
        $properties = [];

        foreach ($this->parameters() as $parameter) {
            $property = $mapper->map($parameter);

            $properties[$parameter->getName()] = $this->isRequired($parameter) ? $property->required() : $property;
        }

        if ($this->supportsParseMode()) {
            $properties['parse_mode'] = $schema->string()->enum(['html', 'markdown'])
                ->description('Parse the message text into entities with this format.');
        }

        if ($this->toolset->selectsSession()) {
            $properties['session'] = $schema->string()->enum($this->toolset->sessions())->description('The MTProto session to use.');
        }

        return $properties;
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        $name = $this->method->getFullName();

        return [
            'readOnlyHint' => Guard::isReadOnly($name),
            'destructiveHint' => Guard::isDestructive($name),
            'openWorldHint' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return $this->toolset->abilitiesFor($this->method->getFullName());
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $rules = [];

        foreach ($this->parameters() as $parameter) {
            if ($this->isRequired($parameter)) {
                $rules[$parameter->getName()] = ['required'];
            }
        }

        $request->validate($rules);

        return static::call($this->toolset, $this->method, $request->all(), $request->get('session'), $request);
    }

    /**
     * Call a TL method with the given arguments, applying defaults and the peer allowlist.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function call(Toolset $toolset, TLMethod $method, array $arguments, mixed $session, ?Request $request = null): Response|ResponseFactory
    {
        $parameters = [];
        $peers = [];

        foreach ($method->getParams() as $parameter) {
            $name = $parameter->getName();

            if (in_array($parameter->getInnerType(), TypeMapper::PEER_TYPES, true)) {
                $peers[] = $name;
            }

            if (array_key_exists($name, $arguments)) {
                $parameters[$name] = $arguments[$name];
            } elseif (($default = static::defaultFor($method, $parameter)) !== null) {
                $parameters[$name] = $default;
            }
        }

        if (isset($arguments['parse_mode']) && is_string($arguments['parse_mode'])) {
            $parameters['parse_mode'] = $arguments['parse_mode'];
        }

        if (($peer = $toolset->disallowedPeer($parameters, $peers)) !== null) {
            return Response::error("Peer [{$peer}] is not allowed for this server.");
        }

        if ($request !== null && ($confirmation = $toolset->confirmation($request, $method->getFullName(), $parameters, Guard::isDestructive($method->getFullName()))) !== null) {
            return $confirmation;
        }

        return $toolset->run(
            is_string($session) ? $session : null,
            fn (Invoker $invoker): mixed => $invoker->invoke($method->getFullName(), $parameters),
        );
    }

    public static function defaultFor(TLMethod $method, TLParameter $parameter): mixed
    {
        if ($parameter->isOptional() || $parameter->isVector()) {
            return null;
        }

        if ($parameter->getName() === 'hash' && in_array($method->getFullName(), self::HASH_EXCLUSIONS, true)) {
            return null;
        }

        return self::DEFAULTS[$parameter->getName()][$parameter->getInnerType()] ?? null;
    }

    /**
     * @return array<int, TLParameter>
     */
    protected function parameters(): array
    {
        return array_values(array_filter(
            $this->method->getParams(),
            fn (TLParameter $parameter): bool => ! $parameter->isFlags(),
        ));
    }

    protected function isRequired(TLParameter $parameter): bool
    {
        return ! $parameter->isOptional()
            && $parameter->getName() !== 'random_id'
            && static::defaultFor($this->method, $parameter) === null;
    }

    protected function supportsParseMode(): bool
    {
        return $this->method->hasParam('entities') && ($this->method->hasParam('message') || $this->method->hasParam('caption'));
    }
}
