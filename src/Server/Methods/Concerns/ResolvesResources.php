<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Methods\Concerns;

use InvalidArgumentException;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\ServerContext;

trait ResolvesResources
{
    protected function resolveResource(?string $uri, ServerContext $context): Resource
    {
        if (! $uri) {
            throw new InvalidArgumentException('Missing [uri] parameter.');
        }

        $resource = $context->resources()->first(fn ($resource): bool => $resource->uri() === $uri)
            ?? $context->resourceTemplates()->first(fn ($template): bool => (string) $template->uriTemplate() === $uri
                || $template->uriTemplate()->match($uri) !== null);

        if (! $resource) {
            throw new InvalidArgumentException("Resource [{$uri}] not found.");
        }

        return $resource;
    }
}
