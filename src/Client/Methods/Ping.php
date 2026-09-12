<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\Methods;

use LaraGram\Mcp\Client\Contracts\Method;
use LaraGram\Mcp\Client\Protocol;

/**
 * @implements Method<void>
 */
class Ping implements Method
{
    public function method(): string
    {
        return 'ping';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [];
    }

    public function handle(Protocol $protocol): void
    {
        $protocol->dispatch($this);
    }
}
