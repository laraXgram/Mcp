<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\OAuth;

use SensitiveParameter;

class OAuthConfig
{
    public function __construct(
        public ?string $clientId = null,
        #[SensitiveParameter]
        public ?string $clientSecret = null,
        public ?string $scope = null,
        public ?string $redirectUri = null,
    ) {}
}
