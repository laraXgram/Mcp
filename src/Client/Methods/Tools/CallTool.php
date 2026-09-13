<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Client\Methods\Tools;

use LaraGram\Mcp\Client\Contracts\Method;
use LaraGram\Mcp\Client\Contracts\MirrorsParameters;
use LaraGram\Mcp\Client\Protocol;
use LaraGram\Mcp\Client\Schema\ToolResult;
use LaraGram\Mcp\Support\MirroredParameters;

/**
 * @implements Method<ToolResult>
 */
class CallTool implements Method, MirrorsParameters
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        protected string $name,
        protected array $arguments = [],
        protected ?MirroredParameters $mirroredParameters = null,
        protected array $inputResponses = [],
        protected ?string $requestState = null,
    ) {
        //
    }

    /**
     * @return array<string, string>
     */
    public function requestHeaders(): array
    {
        return $this->mirroredParameters?->headers($this->arguments) ?? [];
    }

    public function method(): string
    {
        return 'tools/call';
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        return array_filter([
            'name' => $this->name,
            'arguments' => (object) $this->arguments,
            'inputResponses' => $this->inputResponses === [] ? null : $this->inputResponses,
            'requestState' => $this->requestState,
        ], fn (mixed $value): bool => $value !== null);
    }

    public function handle(Protocol $protocol): ToolResult
    {
        return ToolResult::from($protocol->dispatch($this));
    }
}
