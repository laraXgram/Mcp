<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Support\Str;

class MethodTool extends Tool
{
    /**
     * Tool definitions memoized per method and toolset options, for long-running processes.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $definitions = [];

    public function __construct(
        protected Method $method,
        protected Toolset $toolset,
    ) {
        //
    }

    public function method(): Method
    {
        return $this->method;
    }

    public function name(): string
    {
        return $this->toolset->prefixValue().Str::snake($this->method->name);
    }

    public function title(): string
    {
        return Str::headline($this->method->name);
    }

    public function description(): string
    {
        $description = trim((string) preg_replace('/\s+/', ' ', $this->method->description));

        if ($this->method->returns !== null) {
            $description .= " Returns: {$this->method->returns}.";
        }

        return $description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $key = $this->method->name.'|'.$this->toolset->fingerprint();

        return static::$definitions[$key] ??= parent::toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $mapper = new TypeMapper($schema, $this->toolset->types(), $this->toolset->depthValue());
        $properties = [];

        foreach ($this->method->parameters as $parameter) {
            $property = $mapper->map($parameter['type'])
                ->description(trim((string) preg_replace('/\s+/', ' ', $parameter['description'])));

            $properties[$parameter['name']] = $parameter['required'] ? $property->required() : $property;
        }

        if ($this->toolset->selectsConnection()) {
            $properties['connection'] = $schema->string()
                ->enum($this->toolset->connections())
                ->description('The bot connection to send the request with. Defaults to the default connection.');
        }

        return $properties;
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return [
            'readOnlyHint' => $this->method->isReadOnly(),
            'destructiveHint' => $this->method->isDestructive(),
            'idempotentHint' => $this->method->isIdempotent(),
            'openWorldHint' => true,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return $this->toolset->abilitiesFor($this->method);
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate($this->rules());

        $arguments = $request->all();

        $connection = $this->toolset->fixedConnection() ?? $arguments['connection'] ?? null;

        unset($arguments['connection']);

        $parameters = array_intersect_key($arguments, array_flip(array_column($this->method->parameters, 'name')));

        if (array_key_exists('chat_id', $parameters) && ! $this->toolset->chatAllowed($parameters['chat_id'])) {
            return Response::error("Chat [{$parameters['chat_id']}] is not allowed for this server.");
        }

        if ($this->toolset->confirmsDestructive() && $this->method->isDestructive() && ! $request->confirmed()) {
            return $request->declined()
                ? Response::error("The user declined the {$this->method->name} call.")
                : Response::confirm("Allow the bot to call {$this->method->name} with ".json_encode($parameters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'?');
        }

        $result = (new Client(is_string($connection) ? $connection : null))->call($this->method->name, $parameters);

        if (! $result->ok) {
            return Response::error($result->errorMessage());
        }

        return Response::structured(array_filter([
            'ok' => true,
            'result' => $result->result,
            'note' => $result->description,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        $rules = [];

        foreach ($this->method->parameters as $parameter) {
            if ($parameter['required']) {
                $rules[$parameter['name']] = ['required'];
            }
        }

        if ($this->toolset->selectsConnection()) {
            $rules['connection'] = ['nullable', 'string', 'in:'.implode(',', $this->toolset->connections())];
        }

        return $rules;
    }
}
