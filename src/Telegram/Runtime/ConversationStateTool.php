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
            'user_id' => $schema->integer()->description('The Telegram user id.')->required(),
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
        ]);

        $container = Container::getInstance();
        $config = $container->make('config');

        $store = $container->make('cache')->store($config->get('conversation.store'));
        $prefix = $config->get('conversation.prefix', 'conversation');
        $userId = (int) $request->get('user_id');

        $stateKey = "{$prefix}:{$userId}";
        $answersKey = "{$prefix}:answers:{$userId}";

        $state = [
            'user_id' => $userId,
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
