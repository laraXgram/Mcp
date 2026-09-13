<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsIdempotent;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class DescribeTypeTool extends Tool
{
    public function __construct(protected Toolset $toolset)
    {
        //
    }

    public function name(): string
    {
        return $this->toolset->prefixValue().'describe_type';
    }

    public function title(): string
    {
        return 'Describe Telegram Type';
    }

    public function description(): string
    {
        return 'Get the fields of a Telegram Bot API object type (e.g. InlineKeyboardButton, ReplyParameters, InputMediaPhoto). Use it when a tool input describes a value only as "A Telegram <Type> object".';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('The exact Telegram type name.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate(['type' => ['required', 'string']]);

        $name = (string) $request->get('type');
        $definition = $this->toolset->types()[$name] ?? null;

        if ($definition === null) {
            return Response::error("Unknown Telegram type [{$name}].");
        }

        $fields = $definition['fields'];

        if (count($fields) === 1 && $fields[0]['name'] === '__union__') {
            return Response::structured([
                'type' => $name,
                'description' => $definition['description'],
                'oneOf' => explode('|', $fields[0]['type']),
            ]);
        }

        return Response::structured([
            'type' => $name,
            'description' => $definition['description'],
            'fields' => $fields === [] ? (object) [] : $fields,
        ]);
    }
}
