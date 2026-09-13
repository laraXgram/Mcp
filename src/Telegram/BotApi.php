<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram;

use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Telegram\BotApi\Resources\BotResource;
use LaraGram\Mcp\Telegram\BotApi\Resources\ChatResource;
use LaraGram\Mcp\Telegram\BotApi\Resources\ChatUpdatesResource;
use LaraGram\Mcp\Telegram\BotApi\Resources\CommandsResource;
use LaraGram\Mcp\Telegram\BotApi\Resources\FileResource;
use LaraGram\Mcp\Telegram\BotApi\Resources\UpdatesResource;
use LaraGram\Mcp\Telegram\BotApi\Resources\WebhookResource;
use LaraGram\Mcp\Telegram\BotApi\Toolset;

class BotApi
{
    /**
     * Start building tools for the Telegram Bot API methods.
     *
     *     protected function boot(): void
     *     {
     *         $this->tools = [
     *             ToolSearch::class => BotApi::tools()->preset('messaging')->withoutDestructive()->all(),
     *         ];
     *     }
     */
    public static function tools(): Toolset
    {
        return new Toolset;
    }

    /**
     * Get the Bot API resource templates (bot info, webhook, commands, chats, files and recorded updates).
     *
     * @return array<int, Resource>
     */
    public static function resources(): array
    {
        return [
            new BotResource,
            new WebhookResource,
            new CommandsResource,
            new ChatResource,
            new FileResource,
            new UpdatesResource,
            new ChatUpdatesResource,
        ];
    }
}
