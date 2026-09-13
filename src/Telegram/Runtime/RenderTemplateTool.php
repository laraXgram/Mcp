<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Runtime;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsIdempotent;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class RenderTemplateTool extends Tool
{
    protected string $name = 'bot_render_template';

    protected string $title = 'Render Bot Template';

    protected string $description = 'Render a bot template (Temple8, including rich messages and keyboards) for a chat without sending it. Returns the Bot API calls the template would make, with their exact parameters.';

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
            'template' => $schema->string()->description('The template name, e.g. "welcome" or "orders.receipt".')->required(),
            'data' => $schema->object()->description('The data passed to the template.'),
            'chat_id' => $schema->integer()->description('The chat and user id the template is rendered for.')->required(),
            'connection' => $schema->string()->description('The bot connection to render for.'),
        ];
    }

    public function shouldRegister(): bool
    {
        return $this->toolset->enabled();
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'template' => ['required', 'string'],
            'data' => ['nullable', 'array'],
            'chat_id' => ['required', 'integer'],
            'connection' => ['nullable', 'string'],
        ]);

        $name = (string) $request->get('template');

        if (! template()->exists($name)) {
            return Response::error("Template [{$name}] does not exist.");
        }

        $chatId = (int) $request->get('chat_id');
        $connection = $request->get('connection');
        $sandbox = new Sandbox;

        $update = [
            'update_id' => 0,
            'message' => [
                'message_id' => 0,
                'date' => time(),
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $chatId, 'is_bot' => false, 'first_name' => 'MCP'],
                'text' => '',
            ],
        ];

        $sandbox->run(
            $update,
            fn (): string => template($name, (array) $request->get('data', [])),
            is_string($connection) ? $connection : null,
        );

        return Response::structured([
            'template' => $name,
            'calls' => $sandbox->calls(),
        ]);
    }
}
