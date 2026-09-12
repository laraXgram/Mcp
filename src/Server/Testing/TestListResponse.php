<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Testing;

use LaraGram\Support\Traits\Conditionable;
use LaraGram\Support\Traits\Macroable;

class TestListResponse
{
    use Conditionable;
    use Macroable;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        protected array $items,
    ) {}

    public function dd(): void
    {
        dd($this->items);
    }

    public function dump(): static
    {
        dump($this->items);

        return $this;
    }
}
