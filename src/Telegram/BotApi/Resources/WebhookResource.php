<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi\Resources;

class WebhookResource extends BotApiResource
{
    protected string $name = 'telegram-bot-webhook';

    protected string $title = 'Telegram Bot Webhook';

    protected string $description = 'The current webhook status of the bot (getWebhookInfo).';

    protected function path(): string
    {
        return 'webhook';
    }

    protected function method(): string
    {
        return 'getWebhookInfo';
    }
}
