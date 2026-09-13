<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

use LaraGram\Request\Request as BotRequest;
use Throwable;

class Client
{
    public function __construct(protected ?string $connection = null)
    {
        //
    }

    /**
     * Call a Bot API method through the framework's request pipeline, so the
     * per-call connection, anti-flood, proxy pool and interception all apply.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function call(string $method, array $parameters = []): Result
    {
        $request = new BotRequest;

        if ($this->connection !== null) {
            $request->connection($this->connection);
        }

        try {
            return Result::from($request->call($method, $parameters));
        } catch (Throwable $e) {
            return new Result(false, errorCode: 0, description: Redactor::redact($e->getMessage()));
        }
    }

    /**
     * Get the names of the configured bot connections.
     *
     * @return array<int, string>
     */
    public static function connections(): array
    {
        return array_map('strval', array_keys((array) config('bot.connections', [])));
    }
}
