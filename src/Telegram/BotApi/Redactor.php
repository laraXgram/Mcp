<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

class Redactor
{
    /**
     * Remove bot tokens (as found in Bot API and file download URLs) from the given text.
     */
    public static function redact(string $text): string
    {
        return (string) preg_replace('/\d{5,}:[A-Za-z0-9_-]{30,}/', '[redacted-bot-token]', $text);
    }
}
