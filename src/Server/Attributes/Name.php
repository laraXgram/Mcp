<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Name extends ServerAttribute {}
