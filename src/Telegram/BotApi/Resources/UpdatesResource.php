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
 * The most recent updates received by the bot (recorded by RecordUpdates).
 * Subscribe to the URI to be notified of new updates.
 */
class UpdatesResource extends Resource implements HasUriTemplate
{
    protected string $name = 'telegram-bot-updates';

    protected string $title = 'Telegram Bot Updates';

    protected string $description = 'The most recent updates received by the bot, oldest first. Subscribe to be notified of new updates.';

    protected string $mimeType = 'application/json';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('telegram://bots/{connection}/updates');
    }

    public function handle(Request $request): Response
    {
        return Response::text((string) json_encode(
            UpdateBuffer::get('bot:'.$request->get('connection')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
