<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\Mcp\Server\Tools\Annotations\IsIdempotent;
use LaraGram\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaraGram\MTProto\TL\TLParameter;

#[IsReadOnly]
#[IsIdempotent]
class DescribeTool extends Tool
{
    public function __construct(protected Toolset $toolset)
    {
        //
    }

    public function name(): string
    {
        return $this->toolset->prefixValue().'describe';
    }

    public function title(): string
    {
        return 'Describe MTProto Method or Type';
    }

    public function description(): string
    {
        return 'Describe a Telegram API (TL) method (its parameters and result type) or a TL type (its constructors and their fields). Pass either "method" (e.g. "messages.sendMessage") or "type" (e.g. "InputReplyTo").';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'method' => $schema->string()->description('A full TL method name.'),
            'type' => $schema->string()->description('A TL type name.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $request->validate([
            'method' => ['nullable', 'string', 'required_without:type'],
            'type' => ['nullable', 'string', 'required_without:method'],
        ]);

        if (is_string($name = $request->get('method'))) {
            $method = Schema::method($name);

            if ($method === null || ! $this->toolset->permits($name)) {
                return Response::error("Unknown or unavailable Telegram API method [{$name}].");
            }

            return Response::structured([
                'method' => $method->getFullName(),
                'returns' => $method->getType(),
                'parameters' => $this->parameters($method->getParams()),
            ]);
        }

        $type = (string) $request->get('type');
        $constructors = [];

        foreach (Schema::parser()->getConstructors() as $constructor) {
            if ($constructor->getType() === $type) {
                $constructors[] = [
                    '_' => $constructor->getFullName(),
                    'fields' => $this->parameters($constructor->getParams()),
                ];
            }
        }

        if ($constructors === []) {
            return Response::error("Unknown TL type [{$type}].");
        }

        return Response::structured(['type' => $type, 'constructors' => $constructors]);
    }

    /**
     * @param  array<int, TLParameter>  $parameters
     * @return array<int, array<string, mixed>>
     */
    protected function parameters(array $parameters): array
    {
        $described = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isFlags()) {
                continue;
            }

            $described[] = [
                'name' => $parameter->getName(),
                'type' => ($parameter->isVector() ? 'Vector<'.$parameter->getInnerType().'>' : $parameter->getInnerType()),
                'optional' => $parameter->isOptional(),
            ];
        }

        return $described;
    }
}
