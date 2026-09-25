<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Runtime;

use LaraGram\Container\Container;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;

class ConversationStateTool extends Tool
{
    protected string $name = 'bot_conversation_state';

    protected string $title = 'Bot Conversation State';

    protected string $description = 'Inspect the active conversation state and the retained answers of a Telegram user, or forget them to reset the user\'s conversation.';

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
            'user_id' => $schema->integer()->description('The Telegram user id, or the sender chat id for messages sent on behalf of a chat.')->required(),
            'connection' => $schema->string()->description('The bot connection the conversation belongs to. Needed when [bot.default] is "auto", where conversations are stored per bot.'),
            'action' => $schema->string()->enum(['get', 'forget'])->description('Read the state (default) or forget it.'),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false];
    }

    public function shouldRegister(): bool
    {
        return $this->toolset->enabled();
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'user_id' => ['required', 'integer'],
            'action' => ['nullable', 'in:get,forget'],
            'connection' => ['nullable', 'string'],
        ]);

        $container = Container::getInstance();
        $config = $container->make('config');

        $store = $container->make('cache')->store($config->get('conversation.store'));
        $prefix = $config->get('conversation.prefix', 'conversation');
        $userId = (int) $request->get('user_id');

        $connection = null;
        $owner = (string) $userId;

        // With the 'auto' connection, conversations are stored per bot.
        if ($config->get('bot.default') === 'auto') {
            $connections = array_keys((array) $config->get('bot.connections', []));
            $connection = $request->get('connection') ?? (count($connections) === 1 ? $connections[0] : null);

            if (! is_string($connection) || ! in_array($connection, $connections, true)) {
                return Response::error('Pass the bot [connection] the conversation belongs to: '.implode(', ', $connections).'.');
            }

            $owner = "{$connection}:{$userId}";
        }

        $stateKey = "{$prefix}:{$owner}";
        $answersKey = "{$prefix}:answers:{$owner}";

        $state = [
            'user_id' => $userId,
            'connection' => $connection,
            'active' => $store->get($stateKey),
            'retained_answers' => $store->get($answersKey),
        ];

        if ($request->get('action') === 'forget') {
            $store->forget($stateKey);
            $store->forget($answersKey);

            $state['forgotten'] = true;
        }

        return Response::structured($state);
    }
}
