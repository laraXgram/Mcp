<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi\Resources;

class BotResource extends BotApiResource
{
    protected string $name = 'telegram-bot';

    protected string $title = 'Telegram Bot';

    protected string $description = 'Basic information about the bot (getMe).';

    protected function path(): string
    {
        return 'me';
    }

    protected function method(): string
    {
        return 'getMe';
    }
}
