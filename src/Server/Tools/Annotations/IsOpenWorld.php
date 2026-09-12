<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Tools\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class IsOpenWorld extends ToolAnnotation
{
    public function __construct(public bool $value = true)
    {
        //
    }

    public function key(): string
    {
        return 'openWorldHint';
    }
}
