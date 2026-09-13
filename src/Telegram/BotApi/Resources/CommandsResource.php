<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi\Resources;

class CommandsResource extends BotApiResource
{
    protected string $name = 'telegram-bot-commands';

    protected string $title = 'Telegram Bot Commands';

    protected string $description = 'The bot commands for the default scope and all languages (getMyCommands).';

    protected function path(): string
    {
        return 'commands';
    }

    protected function method(): string
    {
        return 'getMyCommands';
    }
}
