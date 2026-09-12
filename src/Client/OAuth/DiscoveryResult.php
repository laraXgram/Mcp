<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\OAuth;

class DiscoveryResult
{
    /**
     * @param  array<int, string>  $scopesSupported
     */
    public function __construct(
        public AuthServerMetadata $server,
        public array $scopesSupported = [],
    ) {}
}
