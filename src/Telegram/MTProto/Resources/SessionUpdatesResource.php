<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto\Resources;

use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Contracts\HasUriTemplate;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Support\UriTemplate;
use LaraGram\Mcp\Telegram\UpdateBuffer;

class SessionUpdatesResource extends Resource implements HasUriTemplate
{
    protected string $name = 'mtproto-session-updates';

    protected string $title = 'MTProto Session Updates';

    protected string $description = 'The most recent updates received by an MTProto session, oldest first. Subscribe to be notified of new updates.';

    protected string $mimeType = 'application/json';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('mtproto://sessions/{session}/updates');
    }

    public function handle(Request $request): Response
    {
        return Response::text((string) json_encode(
            UpdateBuffer::get('mtproto:'.$request->get('session')),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
