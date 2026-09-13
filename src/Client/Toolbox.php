<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client;

use Closure;
use LaraGram\Container\Container;
use LaraGram\Mcp\Client;
use LaraGram\Mcp\Client\Primitives\Tool;
use LaraGram\Mcp\Client\Schema\ToolResult;
use LaraGram\Mcp\Exceptions\ClientException;

/**
 * Exposes the tools of one or more MCP servers to an LLM, independent of the LLM provider:
 * hand definitions() to the model, and run the tool calls it makes with call().
 *
 *     $toolbox = Toolbox::for('telegram', 'orders');
 *     $definitions = $toolbox->definitions();          // name, description, input_schema
 *     $result = $toolbox->call('telegram__send_message', ['chat_id' => 1, 'text' => 'Hi']);
 */
class Toolbox
{
    public const SEPARATOR = '__';

    /**
     * @param  array<string, Client>  $clients
     */
    public function __construct(protected array $clients)
    {
        //
    }

    /**
     * Create a toolbox from clients registered with Mcp::registerClient() or client instances.
     */
    public static function for(string|Client ...$clients): static
    {
        $resolved = [];

        foreach ($clients as $index => $client) {
            if (is_string($client)) {
                $resolved[$client] = Container::getInstance()->make(ClientManager::class)->client($client);
            } else {
                $resolved['mcp'.$index] = $client;
            }
        }

        return new static($resolved);
    }

    /**
     * Tool definitions for the model, named "{client}__{tool}".
     *
     * @return array<int, array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->clients as $clientName => $client) {
            /** @var Tool $tool */
            foreach ($client->tools() as $tool) {
                $definitions[] = [
                    'name' => $clientName.self::SEPARATOR.$tool->name,
                    'description' => (string) ($tool->description ?? $tool->title ?? $tool->name),
                    'input_schema' => $tool->inputSchema,
                ];
            }
        }

        return $definitions;
    }

    /**
     * Call a tool by its toolbox name.
     *
     * When the server asks for more input (e.g. a confirmation), the resolver receives the input
     * requests and returns the input responses; the call is then retried. Without a resolver, or
     * when it returns null, the "input_required" result is returned (see ToolResult::requiresInput()),
     * and the call can be resumed later with resume().
     *
     * @param  array<string, mixed>  $arguments
     * @param  (Closure(array<string, array<string, mixed>>, string): (array<string, mixed>|null))|null  $resolveInput
     */
    public function call(string $name, array $arguments = [], ?Closure $resolveInput = null, int $maxRounds = 5): ToolResult
    {
        [$client, $tool] = $this->resolve($name);

        $result = $client->callTool($tool, $arguments);

        for ($round = 0; $result->requiresInput() && $resolveInput !== null && $round < $maxRounds; $round++) {
            $responses = $resolveInput($result->inputRequests, $name);

            if ($responses === null) {
                break;
            }

            $result = $client->callTool($tool, $arguments, $responses, $result->requestState);
        }

        return $result;
    }

    /**
     * Retry a call that returned "input_required", with the collected input responses.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>  $inputResponses
     */
    public function resume(string $name, array $arguments, array $inputResponses, ?string $requestState): ToolResult
    {
        [$client, $tool] = $this->resolve($name);

        return $client->callTool($tool, $arguments, $inputResponses, $requestState);
    }

    /**
     * @return array{0: Client, 1: string}
     */
    protected function resolve(string $name): array
    {
        [$clientName, $tool] = array_pad(explode(self::SEPARATOR, $name, 2), 2, null);

        if ($tool === null || ! isset($this->clients[$clientName])) {
            throw new ClientException("Unknown toolbox tool [{$name}].");
        }

        return [$this->clients[$clientName], $tool];
    }
}
