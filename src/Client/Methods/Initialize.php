<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\Methods;

use LaraGram\Mcp\Client\Contracts\Method;
use LaraGram\Mcp\Client\Protocol;
use LaraGram\Mcp\Client\Schema\InitializeResult;
use LaraGram\Mcp\Enums\ProtocolVersion;
use LaraGram\Mcp\Schema\Implementation;

/**
 * @implements Method<InitializeResult>
 */
class Initialize implements Method
{
    public function __construct(
        protected Implementation $clientInfo,
        protected ProtocolVersion $protocolVersion = ProtocolVersion::V2025_11_25,
    ) {
        //
    }

    public function method(): string
    {
        return 'initialize';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return [
            'protocolVersion' => $this->protocolVersion->value,
            'capabilities' => (object) [],
            'clientInfo' => $this->clientInfo->toArray(),
        ];
    }

    public function handle(Protocol $protocol): InitializeResult
    {
        return InitializeResult::from($protocol->dispatch($this));
    }
}
