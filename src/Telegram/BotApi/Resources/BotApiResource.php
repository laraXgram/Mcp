<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi\Resources;

use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\Contracts\HasUriTemplate;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Support\UriTemplate;
use LaraGram\Mcp\Telegram\BotApi\Client;

abstract class BotApiResource extends Resource implements HasUriTemplate
{
    protected string $mimeType = 'application/json';

    /**
     * The URI template, relative to "telegram://bots/{connection}/".
     */
    abstract protected function path(): string;

    /**
     * The Bot API method to call.
     */
    abstract protected function method(): string;

    /**
     * @return array<string, mixed>
     */
    protected function parameters(Request $request): array
    {
        return [];
    }

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('telegram://bots/{connection}/'.$this->path());
    }

    public function handle(Request $request): Response
    {
        $connection = (string) $request->get('connection');

        if (! in_array($connection, Client::connections(), true)) {
            return Response::error("Bot connection [{$connection}] is not configured.");
        }

        $result = (new Client($connection))->call($this->method(), $this->parameters($request));

        if (! $result->ok) {
            return Response::error($result->errorMessage());
        }

        return Response::text((string) json_encode(
            $result->result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
