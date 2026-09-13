<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\MTProto\TL\TLParameter;

final class TypeMapper
{
    public const PEER_TYPES = ['InputPeer', 'InputUser', 'InputChannel'];

    public function __construct(private readonly JsonSchema $schema)
    {
    }

    public function map(TLParameter $parameter): Type
    {
        $type = $this->mapInner($parameter->getInnerType());

        return $parameter->isVector() ? $this->schema->array()->items($type) : $type;
    }

    private function mapInner(string $type): Type
    {
        return match ($type) {
            'int', 'int53' => $this->schema->integer(),
            'long', 'int128', 'int256' => $this->schema->union(['integer', 'string'])
                ->description('A 64-bit integer, as a number or a numeric string.'),
            'double' => $this->schema->number(),
            'string' => $this->schema->string(),
            'bytes' => $this->schema->string()->description('Binary data as a string, or {"_bytes": "<base64>"} as returned by other tools.'),
            'Bool', 'true' => $this->schema->boolean(),
            'X', '!X', 'Object' => $this->schema->object(),
            default => in_array($type, self::PEER_TYPES, true)
                ? $this->schema->union(['integer', 'string'])->description('A peer: @username, phone number, t.me link or numeric id.')
                : $this->mapConstructorType($type),
        };
    }

    private function mapConstructorType(string $type): Type
    {
        $constructors = Schema::constructorsOf($type);
        $listed = implode(', ', array_slice($constructors, 0, 12)).(count($constructors) > 12 ? ', ...' : '');

        return $this->schema->object()->description(
            "A TL {$type} object with its constructor in \"_\"".($listed !== '' ? " (one of: {$listed})" : '').'. Use the describe tool for its fields.'
        );
    }
}
