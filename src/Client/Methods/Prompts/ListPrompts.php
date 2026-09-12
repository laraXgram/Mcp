<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\Methods\Prompts;

use LaraGram\Support\Collection;
use LaraGram\Mcp\Client\Contracts\Method;
use LaraGram\Mcp\Client\Methods\Concerns\PaginatesList;
use LaraGram\Mcp\Client\Primitives\Prompt;

/**
 * @implements Method<Collection<string, Prompt>>
 */
class ListPrompts implements Method
{
    use PaginatesList;

    public function __construct(?string $cursor = null, ?int $limit = null)
    {
        $this->cursor = $cursor;
        $this->limit = $limit;
    }

    protected function listType(): string
    {
        return 'prompts';
    }

    protected function nextPage(?string $cursor): static
    {
        return new static($cursor, $this->limit);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     * @return Collection<string, Prompt>
     */
    protected function hydrate(array $payloads): Collection
    {
        return collect($payloads)->mapWithKeys(function (array $payload): array {
            $prompt = Prompt::from($payload);

            return [$prompt->name => $prompt];
        });
    }
}
