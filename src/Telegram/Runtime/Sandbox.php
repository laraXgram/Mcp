<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Runtime;

use Closure;
use LaraGram\Container\Container;
use LaraGram\Laraquest\ConnectionRegistry;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Support\Facades\Facade;
use LaraGram\Support\Str;
use ReflectionProperty;
use RuntimeException;

/**
 * Runs code against a synthetic Telegram update while every Bot API call is
 * captured instead of sent. The container's bot request and redirector, the
 * default auth guard and the default bot connection are restored afterwards, so
 * the sandbox can run inside an HTTP (MCP) request without leaking bot state into it.
 */
class Sandbox
{
    /** @var array<int, array{method: string, parameters: array<string, mixed>, connection: string|null}> */
    protected array $calls = [];

    /**
     * @param  array<string, mixed>  $results  Fake results keyed by Bot API method name.
     */
    public function __construct(protected array $results = [])
    {
        //
    }

    /**
     * @template TReturn
     *
     * @param  array<string, mixed>  $update
     * @param  Closure(BotRequest): TReturn  $callback
     * @return TReturn
     */
    public function run(array $update, Closure $callback, ?string $connection = null): mixed
    {
        if (BotRequest::isIntercepting()) {
            throw new RuntimeException('Telegram API calls are already being intercepted.');
        }

        $app = Container::getInstance();
        $request = BotRequest::createFromBase([null, json_encode($update), json_encode($this->server($connection))]);

        $previousRequest = $app->bound('request') ? $app->make('request') : null;
        // The bot kernel forgets the "redirect" instance, which the HTTP kernel binds to its own redirector.
        $previousRedirect = $app->resolved('redirect') ? $app->make('redirect') : null;
        $auth = $app->bound('auth') ? $app->make('auth') : null;
        $previousGuard = $auth?->getDefaultDriver();
        $previousConnection = ConnectionRegistry::getDefaultConnection();

        $this->calls = [];

        BotRequest::interceptUsing(fn (string $method, array $parameters, ?string $resolved): mixed => $this->capture($method, $parameters, $resolved));

        if ($connection !== null) {
            ConnectionRegistry::setDefaultConnection($connection);
        }

        $app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        try {
            return $callback($request);
        } finally {
            BotRequest::interceptUsing(null);

            if ($connection !== null) {
                // ConnectionRegistry has no public way to clear the default connection.
                $previousConnection === null
                    ? (new ReflectionProperty(ConnectionRegistry::class, 'defaultConnection'))->setValue(null, null)
                    : ConnectionRegistry::setDefaultConnection($previousConnection);
            }

            $previousRequest !== null
                ? $app->instance('request', $previousRequest)
                : $app->forgetInstance('request');

            Facade::clearResolvedInstance('request');

            if ($previousRedirect !== null) {
                $app->instance('redirect', $previousRedirect);

                Facade::clearResolvedInstance('redirect');
            }

            if ($auth !== null && $previousGuard !== null) {
                $auth->shouldUse($previousGuard);
            }
        }
    }

    /**
     * Get the Bot API calls captured by the last run.
     *
     * @return array<int, array{method: string, parameters: array<string, mixed>, connection: string|null}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function capture(string $method, array $parameters, ?string $connection): array
    {
        $this->calls[] = ['method' => $method, 'parameters' => $parameters, 'connection' => $connection];

        return ['ok' => true, 'result' => $this->fakeResult($method, $parameters)];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function fakeResult(string $method, array $parameters): mixed
    {
        if (array_key_exists($method, $this->results)) {
            return $this->results[$method];
        }

        if (Str::is(['send*', 'copyMessage', 'forwardMessage', 'edit*', 'stopMessageLiveLocation', 'setGameScore'], $method)) {
            return [
                'message_id' => count($this->calls),
                'date' => time(),
                'chat' => ['id' => $parameters['chat_id'] ?? 0, 'type' => 'private'],
                'text' => $parameters['text'] ?? null,
            ];
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function server(?string $connection): array
    {
        $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_TIME' => time()];

        $secret = $connection !== null ? config("bot.connections.{$connection}.secret_token") : null;

        if (is_string($secret) && $secret !== '') {
            $server['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = $secret;
        }

        return $server;
    }
}
