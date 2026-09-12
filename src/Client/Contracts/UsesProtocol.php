<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\Contracts;

use LaraGram\Mcp\Enums\ProtocolVersion;

interface UsesProtocol
{
    public function useProtocol(ProtocolVersion $protocolVersion): void;
}
