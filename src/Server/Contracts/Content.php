<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Contracts;

use LaraGram\Contracts\Support\Arrayable;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\Tool;
use Stringable;

/**
 * @extends Arrayable<string, mixed>
 */
interface Content extends Arrayable, Stringable
{
    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array;

    /**
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array;

    /**
     * @return array<string, mixed>
     */
    public function toResource(Resource $resource): array;

    /**
     * @param  array<string, mixed>|string  $meta
     */
    public function setMeta(array|string $meta, mixed $value = null): void;

    public function __toString(): string;
}
