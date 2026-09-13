<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Container\Container;
use LaraGram\MTProto\TL\TLMethod;
use LaraGram\MTProto\TL\TLParser;
use ReflectionClass;
use RuntimeException;

/**
 * The Telegram API (TL) schema shipped with the MTProto package.
 */
final class Schema
{
    private static ?TLParser $parser = null;

    /** @var array<string, array<int, string>>|null */
    private static ?array $constructorsByType = null;

    public static function available(): bool
    {
        return class_exists(TLParser::class);
    }

    public static function parser(): TLParser
    {
        if (self::$parser !== null) {
            return self::$parser;
        }

        if (! self::available()) {
            throw new RuntimeException('The laraxgram/mtproto package is not installed.');
        }

        $container = Container::getInstance();
        $parser = new TLParser($container->bound('files') ? $container->make('files') : null);
        $parser->parseFile(dirname((string) (new ReflectionClass(TLParser::class))->getFileName()).'/schemas/telegram_api.tl');

        return self::$parser = $parser;
    }

    public static function method(string $name): ?TLMethod
    {
        return self::parser()->getMethod($name);
    }

    /**
     * @return array<int, TLMethod>
     */
    public static function methods(): array
    {
        return array_values(self::parser()->getMethods());
    }

    /**
     * Get the constructor names of a TL type, e.g. "InputReplyTo" => ["inputReplyToMessage", ...].
     *
     * @return array<int, string>
     */
    public static function constructorsOf(string $type): array
    {
        if (self::$constructorsByType === null) {
            self::$constructorsByType = [];

            foreach (self::parser()->getConstructors() as $constructor) {
                self::$constructorsByType[$constructor->getType()][] = $constructor->getFullName();
            }
        }

        return self::$constructorsByType[$type] ?? [];
    }
}
