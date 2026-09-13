<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Concerns;

use LaraGram\Container\Container;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Server\Attributes\RequiresAbility;

trait HasAbilities
{
    /**
     * The token abilities required to list and use this primitive.
     *
     * @return array<int, string>
     */
    public function abilities(): array
    {
        return array_map(
            fn (RequiresAbility $attribute): string => $attribute->value,
            $this->resolveAttributes(RequiresAbility::class),
        );
    }

    /**
     * Determine if the current access token grants every required ability.
     *
     * Works with user models exposing Citadel's "tokenCan" (the HasApiTokens trait)
     * and fails closed: without a user or token abilities, access is denied.
     */
    public function hasRequiredAbilities(): bool
    {
        $abilities = $this->abilities();

        if ($abilities === []) {
            return true;
        }

        $user = Container::getInstance()->make(Request::class)->user();

        if ($user === null || ! method_exists($user, 'tokenCan')) {
            return false;
        }

        foreach ($abilities as $ability) {
            if (! $user->tokenCan($ability)) {
                return false;
            }
        }

        return true;
    }
}
