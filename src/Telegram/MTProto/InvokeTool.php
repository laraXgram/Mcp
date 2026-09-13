<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Container\Container;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[IsOpenWorld]
class InvokeTool extends Tool
{
    public function __construct(protected Toolset $toolset)
    {
        //
    }

    public function name(): string
    {
        return $this->toolset->prefixValue().'invoke';
    }

    public function title(): string
    {
        return 'Invoke MTProto Method';
    }

    public function description(): string
    {
        return 'Invoke any permitted Telegram API (TL) method on the MTProto session, e.g. "messages.getHistory" with {"peer": "@durov", "limit": 10}. Peers may be given as @username or id; common offsets, hashes and random ids are filled in. Use the describe tool first to see the parameters of a method. Account security, login and payment methods are not available.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $properties = [
            'method' => $schema->string()->description('The full TL method name, e.g. "messages.sendMessage".')->required(),
            'params' => $schema->object()->description('The method parameters. "parse_mode" ("html" or "markdown") is accepted for methods with a message or caption.'),
        ];

        if ($this->toolset->selectsSession()) {
            $properties['session'] = $schema->string()->enum($this->toolset->sessions())->description('The MTProto session to use.');
        }

        return $properties;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'method' => ['required', 'string'],
            'params' => ['nullable', 'array'],
        ]);

        $name = (string) $request->get('method');
        $method = Schema::method($name);

        if ($method === null) {
            return Response::error("Unknown Telegram API method [{$name}].");
        }

        if (! $this->toolset->permits($name)) {
            return Response::error("The method [{$name}] is not allowed on this server.");
        }

        foreach ($this->toolset->abilitiesFor($name) as $ability) {
            $user = Container::getInstance()->make(Request::class)->user();

            if ($user === null || ! method_exists($user, 'tokenCan') || ! $user->tokenCan($ability)) {
                return Response::error("The access token does not grant the [{$ability}] ability.");
            }
        }

        return MethodTool::call($this->toolset, $method, (array) $request->get('params', []), $request->get('session'), $request);
    }
}
