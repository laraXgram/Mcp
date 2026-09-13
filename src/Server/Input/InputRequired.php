<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Input;

use LaraGram\Contracts\Support\Arrayable;

/**
 * An interim "input_required" result of the Multi Round-Trip Requests pattern:
 * the client fulfills the input requests and retries the original request with
 * the "inputResponses" and the echoed "requestState".
 *
 * @implements Arrayable<string, mixed>
 */
class InputRequired implements Arrayable
{
    /**
     * @param  array<string, array{method: string, params: array<string, mixed>}>  $inputRequests
     */
    public function __construct(
        public readonly array $inputRequests = [],
        public readonly ?string $requestState = null,
    ) {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'resultType' => 'input_required',
            'inputRequests' => $this->inputRequests === [] ? null : $this->inputRequests,
            'requestState' => $this->requestState,
        ], fn (mixed $value): bool => $value !== null);
    }
}
