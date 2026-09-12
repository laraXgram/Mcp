<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\OAuth\Concerns;

use LaraGram\Http\Client\PendingRequest;
use LaraGram\Support\Facades\Http;

trait InteractsWithOAuthEndpoints
{
    protected function oAuthRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(5)
            ->connectTimeout(2)
            ->withOptions(['allow_redirects' => false]);
    }
}
