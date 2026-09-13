<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Runtime;

use Closure;
use LaraGram\Container\Container;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Listening\Listen;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsIdempotent;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class ListListensTool extends Tool
{
    protected string $name = 'bot_listens';

    protected string $title = 'List Bot Listeners';

    protected string $description = 'List the registered bot listeners (update type, pattern, name, handler, middleware and bot connections), like "php laragram listen:list".';

    public function __construct(protected Toolset $toolset)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()->description('Only include listeners whose pattern, name or handler contains this text.'),
        ];
    }

    public function shouldRegister(): bool
    {
        return $this->toolset->enabled();
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $container = Container::getInstance();
        $filter = (string) $request->get('filter', '');
        $listens = [];

        foreach (['listener' => 'bot', 'client.listener' => 'client'] as $binding => $kind) {
            if (! $container->bound($binding)) {
                continue;
            }

            $listener = $container->make($binding);

            foreach ($listener->getListens() as $listen) {
                $information = $this->information($listener, $listen, $kind);

                if ($filter === '' || str_contains(strtolower(implode(' ', [$information['pattern'], $information['name'], $information['action']])), strtolower($filter))) {
                    $listens[] = $information;
                }
            }
        }

        return Response::structured(['count' => count($listens), 'listens' => $listens]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function information(object $listener, Listen $listen, string $kind): array
    {
        $middleware = method_exists($listener, 'gatherListenMiddleware')
            ? array_map(fn (mixed $middleware): string => $middleware instanceof Closure ? 'Closure' : (string) $middleware, $listener->gatherListenMiddleware($listen))
            : [];

        return [
            'listener' => $kind,
            'methods' => $listen->methods(),
            'pattern' => (string) $listen->pattern(),
            'name' => (string) $listen->getName(),
            'action' => ltrim($listen->getActionName(), '\\'),
            'middleware' => array_values($middleware),
            'connections' => method_exists($listen, 'getForConnections') ? $listen->getForConnections() : [],
        ];
    }
}
