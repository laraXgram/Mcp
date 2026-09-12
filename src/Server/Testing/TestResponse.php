<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Testing;

use LaraGram\Support\Traits\Conditionable;
use LaraGram\Support\Traits\Macroable;
use LaraGram\Mcp\Server\Primitive;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Transport\JsonRpcResponse;
use RuntimeException;

class TestResponse
{
    use Conditionable;
    use Macroable;

    protected JsonRpcResponse $response;

    /**
     * @var array<int, JsonRpcResponse>
     */
    protected array $notifications = [];

    /**
     * @param  iterable<int, JsonRpcResponse>|JsonRpcResponse  $response
     */
    public function __construct(
        protected Primitive $primitive,
        iterable|JsonRpcResponse $response,
    ) {
        $responses = is_iterable($response)
            ? iterator_to_array($response)
            : [$response];

        foreach ($responses as $response) {
            $content = $response->toArray();

            if (isset($content['id'])) {
                $this->response = $response;
            } else {
                $this->notifications[] = $response;
            }
        }
    }

    public function dd(): void
    {
        dd($this->response->toArray());
    }

    public function dump(): static
    {
        dump($this->response->toArray());

        return $this;
    }

    /**
     * @return array<int, string>
     */
    protected function content(): array
    {
        return (match (true) {
            // @phpstan-ignore-next-line
            $this->primitive instanceof Tool => collect($this->response->toArray()['result']['content'] ?? [])
                ->map(fn (array $message): string => $message['text'] ?? $message['data'] ?? ''),
            // @phpstan-ignore-next-line
            $this->primitive instanceof Prompt => collect($this->response->toArray()['result']['messages'] ?? [])
                ->map(fn (array $message): array => $message['content'])
                ->map(fn (array $content): string => $content['text'] ?? $content['data'] ?? ''),
            // @phpstan-ignore-next-line
            $this->primitive instanceof Resource => collect($this->response->toArray()['result']['contents'] ?? [])
                ->map(fn (array $item): string => $item['text'] ?? $item['blob'] ?? ''),
            default => throw new RuntimeException('This primitive type is not supported.'),
        })->filter()->unique()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    protected function errors(): array
    {
        $response = $this->response->toArray();

        if (data_get($response, 'result.isError', false)) {
            return $this->content();
        }

        if (array_key_exists('error', $response)) {
            return [$response['error']['message']];
        }

        return [];
    }
}
