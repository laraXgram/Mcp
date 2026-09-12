<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Concerns;

use LaraGram\Mcp\Schema\Icon;
use LaraGram\Mcp\Server\Attributes\Icon as IconAttribute;

trait HasIcons
{
    use ReadsAttributes;

    /**
     * @return list<Icon>
     */
    public function resolvedIcons(): array
    {
        $attributeIcons = array_map(
            fn (IconAttribute $icon): Icon => $icon->toIcon(),
            $this->resolveAttributes(IconAttribute::class),
        );

        return [...$attributeIcons, ...$this->icons()];
    }
}
