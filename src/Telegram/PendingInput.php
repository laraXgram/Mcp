<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram;

use LaraGram\Container\Container;
use LaraGram\Contracts\Cache\Repository;
use LaraGram\Mcp\Client\Schema\ToolResult;
use LaraGram\Mcp\Client\Toolbox;
use LaraGram\Support\Str;

/**
 * Parks a tool call that needs user input (e.g. a confirmation) until the Telegram
 * user answers in a later update, such as an inline keyboard tap.
 *
 *     $result = $toolbox->call($name, $arguments);
 *
 *     if ($result->requiresInput()) {
 *         $id = PendingInput::put(chat()->id, $name, $arguments, $result);
 *         // send the elicitation message with buttons carrying "mcp:{$id}:accept" / "mcp:{$id}:decline"
 *     }
 *
 *     // in the callback query listener:
 *     $result = PendingInput::resume($toolbox, chat()->id, $id, ['confirm' => ['action' => 'accept', 'content' => ['confirm' => true]]]);
 */
class PendingInput
{
    public static int $ttl = 600;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function put(int|string $chatId, string $tool, array $arguments, ToolResult $result): string
    {
        $id = Str::random(16);

        static::cache()->put(static::key($chatId, $id), [
            'tool' => $tool,
            'arguments' => $arguments,
            'inputRequests' => $result->inputRequests,
            'requestState' => $result->requestState,
        ], static::$ttl);

        return $id;
    }

    /**
     * @return array{tool: string, arguments: array<string, mixed>, inputRequests: array<string, mixed>, requestState: string|null}|null
     */
    public static function get(int|string $chatId, string $id): ?array
    {
        $pending = static::cache()->get(static::key($chatId, $id));

        return is_array($pending) ? $pending : null;
    }

    /**
     * Resume the parked call with the user's input responses. Pending calls are single-use
     * and bound to the chat they were created for.
     *
     * @param  array<string, mixed>  $inputResponses
     */
    public static function resume(Toolbox $toolbox, int|string $chatId, string $id, array $inputResponses): ?ToolResult
    {
        $pending = static::cache()->pull(static::key($chatId, $id));

        if (! is_array($pending)) {
            return null;
        }

        return $toolbox->resume($pending['tool'], $pending['arguments'], $inputResponses, $pending['requestState']);
    }

    protected static function key(int|string $chatId, string $id): string
    {
        return "mcp:telegram:pending:{$chatId}:{$id}";
    }

    protected static function cache(): Repository
    {
        $container = Container::getInstance();

        return $container->make('cache')->store($container->make('config')->get('mcp.subscriptions.store'));
    }
}
