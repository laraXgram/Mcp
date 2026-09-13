<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Events;

use LaraGram\Mcp\Server\Resource;

class ResourceRead
{
    /**
     * @param  array<string, mixed>|null  $result  The JSON-RPC result, or null when the read failed with a JSON-RPC error.
     */
    public function __construct(
        public readonly Resource $resource,
        public readonly string $uri,
        public readonly ?array $result,
    ) {
        //
    }

    public function isError(): bool
    {
        return $this->result === null;
    }
}
