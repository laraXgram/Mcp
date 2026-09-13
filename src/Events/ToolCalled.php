<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Events;

use LaraGram\Mcp\Server\Tool;

class ToolCalled
{
    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $result  The JSON-RPC result, or null when the call failed with a JSON-RPC error.
     */
    public function __construct(
        public readonly Tool $tool,
        public readonly array $arguments,
        public readonly ?array $result,
    ) {
        //
    }

    public function isError(): bool
    {
        return $this->result === null || ($this->result['isError'] ?? false) === true;
    }
}
