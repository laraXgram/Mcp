<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Attributes;

use Attribute;

/**
 * Expose a public method as an MCP tool (see CallableTool::fromClass()).
 */
#[Attribute(Attribute::TARGET_METHOD)]
class AsTool {}
