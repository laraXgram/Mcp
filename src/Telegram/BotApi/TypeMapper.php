<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;

class TypeMapper
{
    /**
     * @param  array<string, array{description: string, fields: array<int, array{name: string, type: string, required: bool, description: string}>}>  $types
     */
    public function __construct(
        protected JsonSchema $schema,
        protected array $types,
        protected int $maxDepth = 2,
    ) {
        //
    }

    /**
     * Map a Telegram type expression (e.g. "Integer or String", "Array of MessageEntity") to a JSON Schema type.
     */
    public function map(string $expression, int $depth = 0): Type
    {
        $expression = trim($expression);

        if (preg_match('/^Array of (.+)$/', $expression, $matches) === 1) {
            return $this->schema->array()->items($this->mapAlternatives($this->splitList($matches[1]), $depth));
        }

        return $this->mapAlternatives(preg_split('/\s+or\s+/', $expression) ?: [$expression], $depth);
    }

    /**
     * @param  array<int, string>  $alternatives
     */
    protected function mapAlternatives(array $alternatives, int $depth): Type
    {
        $alternatives = array_values(array_unique(array_map('trim', $alternatives)));

        // A file may be referenced by file_id or URL, which is always a string here.
        if (in_array('InputFile', $alternatives, true)) {
            $alternatives = array_values(array_unique(array_map(
                fn (string $alternative): string => $alternative === 'InputFile' ? 'String' : $alternative,
                $alternatives,
            )));
        }

        if (count($alternatives) === 1) {
            return $this->mapSingle($alternatives[0], $depth);
        }

        $primitives = array_map(fn (string $alternative): ?string => $this->primitiveName($alternative), $alternatives);

        if (! in_array(null, $primitives, true)) {
            return $this->schema->union(array_values(array_unique($primitives)));
        }

        return $this->schema->anyOf(array_map(
            fn (string $alternative): Type => $this->mapSingle($alternative, $depth),
            $alternatives,
        ));
    }

    protected function mapSingle(string $name, int $depth): Type
    {
        if (str_starts_with($name, 'Array of ')) {
            return $this->map($name, $depth);
        }

        return match ($this->primitiveName($name)) {
            'integer' => $this->schema->integer(),
            'number' => $this->schema->number(),
            'boolean' => $this->schema->boolean(),
            'string' => $name === 'InputFile'
                ? $this->schema->string()->description('A file_id of a file on the Telegram servers or an HTTP URL.')
                : $this->schema->string(),
            default => $this->mapObject($name, $depth),
        };
    }

    protected function mapObject(string $name, int $depth): Type
    {
        $definition = $this->types[$name] ?? null;

        if ($definition === null || $depth >= $this->maxDepth) {
            return $this->schema->object()->description("A Telegram {$name} object.");
        }

        $fields = $definition['fields'];

        if (count($fields) === 1 && $fields[0]['name'] === '__union__') {
            return $this->mapAlternatives(explode('|', $fields[0]['type']), $depth)
                ->description($this->describe($definition['description']));
        }

        $properties = [];

        foreach ($fields as $field) {
            $property = $this->map($field['type'], $depth + 1)
                ->description($this->describe($field['description']));

            $properties[$field['name']] = $field['required'] ? $property->required() : $property;
        }

        return $this->schema->object($properties)->description($this->describe($definition['description']));
    }

    protected function primitiveName(string $name): ?string
    {
        return match ($name) {
            'Integer', 'Int' => 'integer',
            'Float', 'Float number' => 'number',
            'Boolean', 'True', 'False' => 'boolean',
            'String', 'InputFile' => 'string',
            default => null,
        };
    }

    /**
     * Split a list like "InputMediaAudio, InputMediaDocument and InputMediaVideo".
     *
     * @return array<int, string>
     */
    protected function splitList(string $list): array
    {
        return preg_split('/\s*,\s*|\s+and\s+|\s+or\s+/', $list) ?: [$list];
    }

    protected function describe(string $description): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $description));
    }
}
