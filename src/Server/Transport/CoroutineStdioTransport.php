<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Transport;

use Closure;
use LaraGram\Mcp\Server\Cancellation;
use RuntimeException;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

/**
 * A STDIO transport for Swoole coroutines ("php laragram mcp:start {handle} --coroutine").
 *
 * Requests are still handled one at a time, but subscription streams run in their
 * own coroutines, so the server keeps reading requests (including their cancellation)
 * while notifications are delivered. Coroutine-based packages such as MTProto can be
 * used from tools without leaving the coroutine context.
 */
class CoroutineStdioTransport extends StdioTransport
{
    protected ?Channel $writeLock = null;

    protected bool $detachNextStream = false;

    /** @var array<string, int|string> */
    protected array $detached = [];

    public function send(string $message): void
    {
        $this->writeLock?->pop();

        try {
            parent::send($message);
        } finally {
            $this->writeLock?->push(true);
        }
    }

    public function run(): void
    {
        if (! class_exists(Coroutine::class) || Coroutine::getCid() < 0) {
            throw new RuntimeException('The coroutine STDIO transport must run inside a Swoole coroutine.');
        }

        $this->writeLock = new Channel(1);
        $this->writeLock->push(true);

        while (($line = fgets(STDIN)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            $subscriptionId = $this->subscriptionId($line);
            $this->detachNextStream = $subscriptionId !== null;

            if ($subscriptionId !== null) {
                $this->detached[get_debug_type($subscriptionId).':'.$subscriptionId] = $subscriptionId;
            }

            try {
                if (is_callable($this->handler)) {
                    ($this->handler)($line);
                }
            } finally {
                $this->detachNextStream = false;
            }
        }

        // STDIN closed: end the open subscriptions so the process can exit.
        foreach ($this->detached as $subscriptionId) {
            Cancellation::cancel($subscriptionId);
        }
    }

    public function stream(Closure $stream): void
    {
        if ($this->detachNextStream) {
            Coroutine::create($stream);

            return;
        }

        $stream();
    }

    protected function subscriptionId(string $line): int|string|null
    {
        $message = json_decode($line, true);

        return is_array($message)
            && ($message['method'] ?? null) === 'subscriptions/listen'
            && (is_int($message['id'] ?? null) || is_string($message['id'] ?? null))
                ? $message['id']
                : null;
    }
}
