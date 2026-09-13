<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

use LaraGram\Foundation\Bot\Events\RequestHandled;
use LaraGram\Mcp\Facades\Mcp;
use LaraGram\Mcp\Telegram\UpdateBuffer;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Support\Facades\Event;
use Throwable;

/**
 * Records every update the bot receives and notifies subscribed MCP clients:
 * "telegram://bots/{connection}/updates" and ".../chats/{chat_id}/updates".
 *
 *     RecordUpdates::listen(); // e.g. in AppServiceProvider::boot()
 */
class RecordUpdates
{
    public static function listen(): void
    {
        Event::listen(RequestHandled::class, static::class);
    }

    public function handle(RequestHandled $event): void
    {
        $request = $event->request;

        if (! $request instanceof BotRequest) {
            return;
        }

        try {
            $update = $request->toArray();

            if ($update === []) {
                return;
            }

            $connection = $request->getConnection();

            UpdateBuffer::push("bot:{$connection}", $update);
            Mcp::resourceUpdated("telegram://bots/{$connection}/updates");

            if (($chatId = static::chatId($update)) !== null) {
                UpdateBuffer::push("bot:{$connection}:chat:{$chatId}", $update);
                Mcp::resourceUpdated("telegram://bots/{$connection}/chats/{$chatId}/updates");
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function chatId(array $update): int|string|null
    {
        foreach ($update as $value) {
            if (! is_array($value)) {
                continue;
            }

            $chatId = $value['chat']['id'] ?? $value['message']['chat']['id'] ?? null;

            if (is_int($chatId) || is_string($chatId)) {
                return $chatId;
            }
        }

        return null;
    }
}
