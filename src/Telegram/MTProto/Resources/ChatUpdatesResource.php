<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto\Resources;

use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Contracts\HasUriTemplate;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Support\UriTemplate;
use LaraGram\Mcp\Telegram\UpdateBuffer;

class ChatUpdatesResource extends Resource implements HasUriTemplate
{
    protected string $name = 'mtproto-chat-updates';

    protected string $title = 'MTProto Chat Updates';

    protected string $description = 'The most recent updates of one chat of an MTProto session (peer id in Bot API format), oldest first. Subscribe to be notified of new updates.';

    protected string $mimeType = 'application/json';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('mtproto://sessions/{session}/chats/{peer}/updates');
    }

    public function handle(Request $request): Response
    {
        return Response::text((string) json_encode(
            UpdateBuffer::get('mtproto:'.$request->get('session').':chat:'.$request->get('peer')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
