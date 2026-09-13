<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram;

use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Telegram\MTProto\Resources\ChatUpdatesResource;
use LaraGram\Mcp\Telegram\MTProto\Resources\SessionUpdatesResource;
use LaraGram\Mcp\Telegram\MTProto\Toolset;

class MTProto
{
    /**
     * Start building tools for an MTProto (user or bot account) session.
     *
     *     $this->tools = [
     *         ...MTProto::tools()->session('support')->withoutDestructive()->all(),
     *         ToolSearch::class => MTProto::tools()->only('messages.*', 'channels.*')->generated(),
     *     ];
     *
     * Calls run through ClientManager::invoker(), so under Surge they are forwarded
     * to the pump process instead of opening a second connection on the session.
     * Login, account security, payment and secret chat methods are never exposed.
     */
    public static function tools(): Toolset
    {
        return new Toolset;
    }

    /**
     * Resources serving the updates recorded by MTProto\RecordUpdates.
     *
     * @return array<int, Resource>
     */
    public static function resources(): array
    {
        return [new SessionUpdatesResource, new ChatUpdatesResource];
    }
}
