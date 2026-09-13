<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Luna;

use LaraGram\Container\Container;
use LaraGram\Http\Request as HttpRequest;
use LaraGram\Luna\ResponseFactory;
use LaraGram\Mcp\ResponseFactory as McpResponseFactory;
use LaraGram\Mcp\Response;
use RuntimeException;

class LunaPage
{
    /**
     * Render a Luna page as a standalone HTML document.
     *
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $viewData
     */
    public static function html(string $component, array $props = [], string $rootView = 'mcp::luna-app', array $viewData = []): string
    {
        return static::factory()->renderToHtml($component, $props, $rootView, $viewData);
    }

    /**
     * A tool response that makes an open Luna MCP App navigate to the given page.
     *
     * The connectMcpApp() bridge of @laraxgram/luna swaps to the page in the
     * "luna" member of the structured content; other clients still see the props.
     *
     * @param  array<string, mixed>  $props
     */
    public static function response(string $component, array $props = []): McpResponseFactory
    {
        $container = Container::getInstance();

        $request = $container->bound('http.request')
            ? $container->make('http.request')
            : HttpRequest::create((string) config('app.url', '/'));

        $page = static::factory()->render($component, $props)->toPage($request);

        return Response::structured(['luna' => $page]);
    }

    protected static function factory(): ResponseFactory
    {
        if (! class_exists(ResponseFactory::class)) {
            throw new RuntimeException('The laraxgram/luna package is not installed.');
        }

        return Container::getInstance()->make(ResponseFactory::class);
    }
}
