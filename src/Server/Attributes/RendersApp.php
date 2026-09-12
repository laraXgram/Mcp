<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Attributes;

use Attribute;
use LaraGram\Mcp\Server\Ui\Enums\Visibility;

#[Attribute(Attribute::TARGET_CLASS)]
class RendersApp
{
    /**
     * @param  class-string  $resource
     * @param  array<int, Visibility>  $visibility
     */
    public function __construct(
        public string $resource,
        public array $visibility = [Visibility::Model, Visibility::App],
    ) {
        //
    }
}
