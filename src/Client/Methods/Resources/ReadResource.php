<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\Methods\Resources;

use LaraGram\Mcp\Client\Contracts\Method;
use LaraGram\Mcp\Client\Protocol;
use LaraGram\Mcp\Client\Schema\ResourceReadResult;

/**
 * @implements Method<ResourceReadResult>
 */
class ReadResource implements Method
{
    public function __construct(
        protected string $uri,
    ) {
        //
    }

    public function method(): string
    {
        return 'resources/read';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return ['uri' => $this->uri];
    }

    public function handle(Protocol $protocol): ResourceReadResult
    {
        return ResourceReadResult::from($protocol->dispatch($this));
    }
}
