<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

use LaraGram\Support\Str;

class Method
{
    /**
     * Methods that break a running bot, belong to the update-delivery pipeline,
     * or expose credentials. They are never exposed unless explicitly allowed.
     *
     * @var array<int, string>
     */
    public const DANGEROUS = [
        'getUpdates',
        'setWebhook',
        'deleteWebhook',
        'logOut',
        'close',
        'getManagedBotToken',
        'replaceManagedBotToken',
    ];

    /**
     * Name patterns of methods that irreversibly remove or revoke something.
     *
     * @var array<int, string>
     */
    public const DESTRUCTIVE = [
        'delete*',
        'ban*',
        'leave*',
        'revoke*',
        'decline*',
        'remove*',
        'refund*',
        'transfer*',
        'convertGiftToStars',
        'unpinAll*',
        'close*',
        'logOut',
        'replaceManagedBotToken',
    ];

    /**
     * @param  array<int, array{name: string, type: string, required: bool, description: string}>  $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $returns,
        public readonly array $parameters,
    ) {
        //
    }

    /**
     * @param  array{description: string, returns: string|null, parameters: array<int, array{name: string, type: string, required: bool, description: string}>}  $definition
     */
    public static function fromSchema(string $name, array $definition): static
    {
        return new static($name, $definition['description'], $definition['returns'] ?? null, $definition['parameters']);
    }

    /**
     * Determine if the method name matches any of the given patterns.
     *
     * @param  array<int, string>|string  $patterns
     */
    public function is(array|string $patterns): bool
    {
        return Str::is($patterns, $this->name);
    }

    public function isReadOnly(): bool
    {
        return $this->is('get*');
    }

    public function isDestructive(): bool
    {
        return $this->is(self::DESTRUCTIVE);
    }

    public function isIdempotent(): bool
    {
        return $this->is(['get*', 'set*']);
    }

    public function isDangerous(): bool
    {
        return in_array($this->name, self::DANGEROUS, true);
    }

    /**
     * Determine if the method targets a chat through a "chat_id" parameter.
     */
    public function hasChatParameter(): bool
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter['name'] === 'chat_id') {
                return true;
            }
        }

        return false;
    }
}
