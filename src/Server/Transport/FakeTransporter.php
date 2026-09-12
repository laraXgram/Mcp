<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Transport;

use Closure;
use LaraGram\Http\Response;
use LaraGram\Mcp\Server\Contracts\Transport;
use LogicException;
use LaraGram\Http\StreamedResponse;

class FakeTransporter implements Transport
{
    public function onReceive(Closure $handler): void
    {
        //
    }

    public function send(string $message): void
    {
        //
    }

    public function run(): Response|StreamedResponse
    {
        throw new LogicException('Not implemented.');
    }

    public function stream(Closure $stream): void
    {
        //
    }
}
