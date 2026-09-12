<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Contracts;

use LaraGram\Mcp\Support\UriTemplate;

interface HasUriTemplate
{
    /**
     * Get the URI pattern for the resource template.
     */
    public function uriTemplate(): UriTemplate;
}
