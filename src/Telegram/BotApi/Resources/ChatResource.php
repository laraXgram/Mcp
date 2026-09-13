<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi\Resources;

use LaraGram\Mcp\Request;

class ChatResource extends BotApiResource
{
    protected string $name = 'telegram-chat';

    protected string $title = 'Telegram Chat';

    protected string $description = 'Full information about a chat the bot has access to (getChat).';

    protected function path(): string
    {
        return 'chats/{chat_id}';
    }

    protected function method(): string
    {
        return 'getChat';
    }

    /**
     * @return array<string, mixed>
     */
    protected function parameters(Request $request): array
    {
        return ['chat_id' => $request->get('chat_id')];
    }
}
