<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server;

use Closure;
use LaraGram\Container\Container;
use LaraGram\Http\Response;
use LaraGram\Routing\Route;
use LaraGram\Support\Facades\Route as Router;
use LaraGram\Support\Traits\Macroable;
use LaraGram\Mcp\Client;
use LaraGram\Mcp\Client\ClientManager;
use LaraGram\Mcp\Client\OAuth\OAuthRouteRegistrar;
use LaraGram\Mcp\Client\OAuth\TokenSet;
use LaraGram\Mcp\Server;
use LaraGram\Mcp\Server\Contracts\Transport;
use LaraGram\Mcp\Server\Http\Controllers\OAuthRegisterController;
use LaraGram\Mcp\Server\Middleware\AddWwwAuthenticateHeader;
use LaraGram\Mcp\Server\Middleware\ReorderJsonAccept;
use LaraGram\Mcp\Server\Middleware\ValidateMcpHeaders;
use LaraGram\Mcp\Server\Subscriptions\Hub;
use LaraGram\Mcp\Server\Transport\HttpTransport;
use LaraGram\Mcp\Server\Transport\StdioTransport;

class Registrar
{
    use Macroable;

    public const OAUTH_SCOPE = 'mcp:use';

    /** @var array<string, callable> */
    protected array $localServers = [];

    /** @var array<string, Route> */
    protected array $httpServers = [];

    /**
     * @param  class-string<Server>  $serverClass
     */
    public function web(string $route, string $serverClass): Route
    {
        // https://modelcontextprotocol.io/specification/2025-11-25/basic/transports#listening-for-messages-from-the-server
        Router::get($route, fn (): Response => response('', 405)->header('Allow', 'POST'));

        Router::delete($route, fn (): Response => response('', 405)->header('Allow', 'POST'));

        $route = Router::post($route, static fn (): mixed => static::startServer(
            $serverClass,
            static fn (): HttpTransport => new HttpTransport(request()),
        ))->middleware([
            ReorderJsonAccept::class,
            ValidateMcpHeaders::class,
            AddWwwAuthenticateHeader::class,
        ]);

        assert($route instanceof Route);

        $this->httpServers[$route->uri()] = $route;

        return $route;
    }

    /**
     * @param  class-string<Server>  $serverClass
     */
    public function local(string $handle, string $serverClass): void
    {
        $this->localServers[$handle] = fn (?Closure $transport = null): mixed => static::startServer($serverClass, $transport ?? fn (): StdioTransport => new StdioTransport);
    }

    /**
     * @param  Closure(): Client  $factory
     */
    public function registerClient(string $name, Closure $factory): void
    {
        $this->clientManager()->registerClient($name, $factory);
    }

    public function client(string $name): Client
    {
        return $this->clientManager()->client($name);
    }

    /**
     * @param  Closure(string, TokenSet): mixed|array{0: class-string, 1: string}  $handler
     * @param  array<int, string>|string  $middleware
     * @param  array<string, mixed>  $clientMetadata
     */
    public function oAuthRoutesFor(
        string $client,
        Closure|array $handler,
        array|string $middleware = 'web',
        ?string $connectUri = null,
        ?string $callbackUri = null,
        ?string $clientMetadataUri = null,
        array $clientMetadata = [],
    ): void {
        (new OAuthRouteRegistrar)->register($client, $handler, $middleware, $connectUri, $callbackUri, $clientMetadataUri, $clientMetadata);
    }

    /**
     * Notify "subscriptions/listen" streams that the list of tools changed.
     */
    public function toolsListChanged(): void
    {
        $this->hub()->publish(Hub::TOOLS_LIST_CHANGED);
    }

    /**
     * Notify "subscriptions/listen" streams that the list of prompts changed.
     */
    public function promptsListChanged(): void
    {
        $this->hub()->publish(Hub::PROMPTS_LIST_CHANGED);
    }

    /**
     * Notify "subscriptions/listen" streams that the list of resources changed.
     */
    public function resourcesListChanged(): void
    {
        $this->hub()->publish(Hub::RESOURCES_LIST_CHANGED);
    }

    /**
     * Notify streams subscribed to the given resource URI that it was updated.
     */
    public function resourceUpdated(string $uri): void
    {
        $this->hub()->publish(Hub::RESOURCE_UPDATED, ['uri' => $uri]);
    }

    public function getLocalServer(string $handle): ?callable
    {
        return $this->localServers[$handle] ?? null;
    }

    public function getWebServer(string $route): ?Route
    {
        return $this->httpServers[$route] ?? null;
    }

    /**
     * @return array<string, callable|Route>
     */
    public function servers(): array
    {
        return array_merge(
            $this->localServers,
            $this->httpServers,
        );
    }

    public function oauthRoutes(string $oauthPrefix = 'oauth'): void
    {
        static::ensureMcpScope();
        $hasExactProtectedResourceRoute = $this->hasGetRoute('.well-known/oauth-protected-resource');
        $hasExactAuthorizationServerRoute = $this->hasGetRoute('.well-known/oauth-authorization-server');

        if (! $hasExactProtectedResourceRoute) {
            Router::get('/.well-known/oauth-protected-resource', static fn () => response()->json(static::protectedResourceMetadata('')))
                ->name('mcp.oauth.protected-resource');
        }

        if (! $hasExactAuthorizationServerRoute) {
            Router::get('/.well-known/oauth-authorization-server', static fn () => response()->json(static::authorizationServerMetadata($oauthPrefix)))
                ->name('mcp.oauth.authorization-server');
        }

        Router::get('/.well-known/oauth-protected-resource/{path}', static function (Route $route) {
            $path = $route->parameter('path');

            return response()->json(static::protectedResourceMetadata(is_string($path) ? $path : ''));
        })
            ->where('path', '.*')
            ->name('mcp.oauth.protected-resource.nested');

        Router::get('/.well-known/oauth-authorization-server/{path}', static fn (string $path) => response()->json(static::authorizationServerMetadata($oauthPrefix)))
            ->where('path', '.*')
            ->name('mcp.oauth.authorization-server.nested');

        Router::post($oauthPrefix.'/register', OAuthRegisterController::class);
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    protected static function authorizationServerMetadata(string $oauthPrefix): array
    {
        return [
            'issuer' => config('mcp.authorization_server') ?? url('/'),
            'authorization_endpoint' => route('passport.authorizations.authorize'),
            'token_endpoint' => route('passport.token'),
            'registration_endpoint' => url($oauthPrefix.'/register'),
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [self::OAUTH_SCOPE],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
        ];
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    protected static function protectedResourceMetadata(string $path): array
    {
        return [
            'resource' => url('/'.$path),
            'authorization_servers' => [config('mcp.authorization_server') ?? url('/')],
            'scopes_supported' => [self::OAUTH_SCOPE],
        ];
    }

    protected function hasGetRoute(string $uri): bool
    {
        foreach (Router::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === $uri && in_array('GET', $route->methods(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function ensureMcpScope(): array
    {
        if (class_exists('LaraGram\Passport\Passport') === false) {
            return [];
        }

        /** @var array<string, string> $current */
        $current = \LaraGram\Passport\Passport::scopes()->pluck('description', 'id')->toArray();

        if (! array_key_exists(self::OAUTH_SCOPE, $current)) {
            $current[self::OAUTH_SCOPE] = 'Use MCP server';

            \LaraGram\Passport\Passport::tokensCan($current);
        }

        return $current;
    }

    protected function hub(): Hub
    {
        return Container::getInstance()->make(Hub::class);
    }

    protected function clientManager(): ClientManager
    {
        return Container::getInstance()->make(ClientManager::class);
    }

    /**
     * @param  class-string<Server>  $serverClass
     * @param  callable(): Transport  $transportFactory
     */
    protected static function startServer(string $serverClass, callable $transportFactory): mixed
    {
        $transport = $transportFactory();

        $server = Container::getInstance()->make($serverClass, [
            'transport' => $transport,
        ]);

        $server->start();

        return $transport->run();
    }
}
