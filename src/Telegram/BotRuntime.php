<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram;

use LaraGram\Mcp\Telegram\Runtime\Toolset;

class BotRuntime
{
    /**
     * Tools for developing the bot itself: listing listeners, simulating updates,
     * rendering templates and inspecting conversations. Nothing is sent to Telegram.
     * Only available in the "local" environment unless configured otherwise.
     */
    public static function tools(): Toolset
    {
        return new Toolset;
    }
}
