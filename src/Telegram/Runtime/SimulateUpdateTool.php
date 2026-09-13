<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Runtime;

use LaraGram\Container\Container;
use LaraGram\Contracts\Bot\Kernel;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Listening\Listen;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Request\Request as BotRequest;

class SimulateUpdateTool extends Tool
{
    protected string $name = 'bot_simulate_update';

    protected string $title = 'Simulate Bot Update';

    public function __construct(protected Toolset $toolset)
    {
        //
    }

    public function description(): string
    {
        return 'Run a Telegram update through the bot\'s listeners exactly like a real webhook update, without sending anything to Telegram. Returns the matched listener and every Bot API call the handlers made. A "bot_command" entity is added automatically when a message text starts with "/" and has no entities.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'update' => $schema->object()
                ->description('A Telegram Update object, e.g. {"update_id": 1, "message": {"message_id": 1, "date": 0, "chat": {"id": 1, "type": "private"}, "from": {"id": 1, "is_bot": false, "first_name": "Test"}, "text": "/start"}}.')
                ->required(),
            'connection' => $schema->string()->description('The bot connection the update is delivered to.'),
            'results' => $schema->object()->description('Fake Bot API results keyed by method name, returned to the handlers instead of the defaults.'),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false];
    }

    public function shouldRegister(): bool
    {
        return $this->toolset->enabled();
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'update' => ['required', 'array'],
            'update.update_id' => ['required', 'integer'],
            'connection' => ['nullable', 'string'],
            'results' => ['nullable', 'array'],
        ]);

        $sandbox = new Sandbox((array) $request->get('results', []));
        $connection = $request->get('connection');

        $outcome = $sandbox->run($this->withCommandEntities((array) $request->get('update')), function (BotRequest $update): array {
            $response = Container::getInstance()->make(Kernel::class)->handle($update);
            $listen = $update->listen();

            return [
                'listen' => $listen instanceof Listen ? [
                    'methods' => $listen->methods(),
                    'pattern' => $listen->pattern(),
                    'name' => $listen->getName(),
                    'action' => ltrim($listen->getActionName(), '\\'),
                ] : null,
                'response' => $response->getContent() ?: null,
            ];
        }, is_string($connection) ? $connection : null);

        return Response::structured([
            'matched' => $outcome['listen'] !== null,
            'listen' => $outcome['listen'],
            'calls' => $sandbox->calls(),
            'response' => $outcome['response'],
        ]);
    }

    /**
     * Add the "bot_command" entity Telegram attaches to messages starting with a command.
     *
     * @param  array<string, mixed>  $update
     * @return array<string, mixed>
     */
    protected function withCommandEntities(array $update): array
    {
        foreach (['message', 'edited_message', 'channel_post', 'edited_channel_post', 'business_message'] as $type) {
            $text = $update[$type]['text'] ?? null;

            if (! is_string($text) || ! str_starts_with($text, '/') || isset($update[$type]['entities'])) {
                continue;
            }

            $update[$type]['entities'] = [[
                'type' => 'bot_command',
                'offset' => 0,
                // Entity offsets and lengths are measured in UTF-16 code units.
                'length' => intdiv(strlen(mb_convert_encoding(explode(' ', $text, 2)[0], 'UTF-16LE', 'UTF-8')), 2),
            ]];
        }

        return $update;
    }
}
