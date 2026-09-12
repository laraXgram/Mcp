<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Exceptions;

use Exception;

class NotImplementedException extends Exception
{
    public static function forMethod(string $class, string $method): static
    {
        return new static("The method [{$class}@{$method}] is not implemented yet.");
    }
}
