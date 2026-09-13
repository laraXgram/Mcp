<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Luna;

use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Server\AppResource;
use RuntimeException;

/**
 * An MCP App rendered from a Luna page (Vue, React or Svelte), with optional SSR.
 *
 * The page's JavaScript and CSS are inlined from a single-file build, because MCP
 * hosts render apps in a sandboxed frame that cannot load assets from the app:
 *
 *     // vite.mcp-app.config.js, built with: vite build --config vite.mcp-app.config.js
 *     plugins: [vue(), luna(), lunaMcpApp({ entry: 'resources/js/mcp-app.ts' })]
 *
 * The entry creates the Luna app and calls connectMcpApp() from @laraxgram/luna.
 */
abstract class LunaAppResource extends AppResource
{
    /**
     * The root view the page is rendered into.
     */
    protected string $rootView = 'mcp::luna-app';

    /**
     * The directory of the single-file build, relative to the base path.
     */
    protected string $bundle = 'bootstrap/mcp-app';

    /**
     * The Luna page component to render, e.g. "Orders/Dashboard".
     */
    abstract protected function component(): string;

    /**
     * The page props.
     *
     * @return array<string, mixed>
     */
    protected function props(Request $request): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        $html = LunaPage::html($this->component(), $this->props($request), $this->rootView, [
            'title' => $this->title(),
            'bundle' => $this->assets(),
        ]);

        return Response::text($html);
    }

    /**
     * @return array{js: string, css: string}
     */
    protected function assets(): array
    {
        $directory = base_path($this->bundle);
        $js = $directory.'/app.js';

        if (! is_file($js)) {
            throw new RuntimeException("The MCP App bundle [{$js}] does not exist. Build it with the lunaMcpApp() Vite plugin.");
        }

        return [
            'js' => (string) file_get_contents($js),
            'css' => is_file($directory.'/app.css') ? (string) file_get_contents($directory.'/app.css') : '',
        ];
    }
}
