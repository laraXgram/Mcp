<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi\Resources;

use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Contracts\HasUriTemplate;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Support\UriTemplate;
use LaraGram\Mcp\Telegram\UpdateBuffer;

/**
 * The most recent updates of one chat (recorded by RecordUpdates).
 */
class ChatUpdatesResource extends Resource implements HasUriTemplate
{
    protected string $name = 'telegram-chat-updates';

    protected string $title = 'Telegram Chat Updates';

    protected string $description = 'The most recent updates the bot received from a chat, oldest first. Subscribe to be notified of new updates.';

    protected string $mimeType = 'application/json';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('telegram://bots/{connection}/chats/{chat_id}/updates');
    }

    public function handle(Request $request): Response
    {
        return Response::text((string) json_encode(
            UpdateBuffer::get('bot:'.$request->get('connection').':chat:'.$request->get('chat_id')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
