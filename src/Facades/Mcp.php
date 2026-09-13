<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Facades;

use LaraGram\Support\Facades\Facade;
use LaraGram\Mcp\Server\Registrar;

/**
 * @method static \LaraGram\Routing\Route web(string $route, string $serverClass)
 * @method static void local(string $handle, string $serverClass)
 * @method static void registerClient(string $name, \Closure $factory)
 * @method static \LaraGram\Mcp\Client client(string $name)
 * @method static void oAuthRoutesFor(string $client, \Closure|array $handler, array|string $middleware = 'web', string|null $connectUri = null, string|null $callbackUri = null, string|null $clientMetadataUri = null, array $clientMetadata = [])
 * @method static void toolsListChanged()
 * @method static void promptsListChanged()
 * @method static void resourcesListChanged()
 * @method static void resourceUpdated(string $uri)
 * @method static callable|null getLocalServer(string $handle)
 * @method static \LaraGram\Routing\Route|null getWebServer(string $route)
 * @method static array servers()
 * @method static void oauthRoutes(string $oauthPrefix = 'oauth')
 * @method static array ensureMcpScope()
 * @method static void macro(string $name, object|callable $macro)
 * @method static void mixin(object $mixin, bool $replace = true)
 * @method static bool hasMacro(string $name)
 * @method static void flushMacros()
 *
 * @see Registrar
 */
class Mcp extends Facade
{
    /**
     * @return class-string<Registrar>
     */
    protected static function getFacadeAccessor(): string
    {
        return Registrar::class;
    }
}
