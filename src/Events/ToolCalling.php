<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Events;

use LaraGram\Mcp\Server\Tool;

class ToolCalling
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly Tool $tool,
        public readonly array $arguments,
    ) {
        //
    }
}
