<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\Schema;

use LaraGram\Support\Arr;
use LaraGram\Mcp\Exceptions\ClientException;
use Stringable;

class ToolResult implements Stringable
{
    /**
     * @param  array<int, array<string, mixed>>  $content
     * @param  array<string, mixed>|null  $structuredContent
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public array $content,
        public bool $isError,
        public ?array $structuredContent = null,
        public ?array $meta = null,
        public string $resultType = 'complete',
        public array $inputRequests = [],
        public ?string $requestState = null,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public static function from(array $result): self
    {
        $content = Arr::get($result, 'content', []);
        $isError = Arr::get($result, 'isError', false);
        $structuredContent = Arr::get($result, 'structuredContent');
        $meta = Arr::get($result, '_meta');
        $resultType = Arr::get($result, 'resultType', 'complete');

        if ($resultType === 'input_required') {
            $inputRequests = Arr::get($result, 'inputRequests', []);
            $requestState = Arr::get($result, 'requestState');

            return new self(
                content: [],
                isError: false,
                meta: is_array($meta) ? $meta : null,
                resultType: 'input_required',
                inputRequests: is_array($inputRequests) ? $inputRequests : [],
                requestState: is_string($requestState) ? $requestState : null,
            );
        }

        if (! is_array($content) || ! is_bool($isError)) {
            throw new ClientException('Invalid tools/call result from server.');
        }

        return new self(
            content: array_values(array_filter($content, is_array(...))),
            isError: $isError,
            structuredContent: is_array($structuredContent) ? $structuredContent : null,
            meta: is_array($meta) ? $meta : null,
        );
    }

    /**
     * Determine if the server needs more input: fulfill the input requests and call the tool again.
     */
    public function requiresInput(): bool
    {
        return $this->resultType === 'input_required';
    }

    public function text(): string
    {
        $parts = [];

        foreach ($this->content as $item) {
            $text = Arr::get($item, 'text');

            if (Arr::get($item, 'type') === 'text' && is_string($text)) {
                $parts[] = $text;
            }
        }

        return implode('', $parts);
    }

    public function __toString(): string
    {
        return $this->text();
    }
}
