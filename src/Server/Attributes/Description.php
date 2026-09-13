<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PARAMETER)]
class Description extends ServerAttribute {}
