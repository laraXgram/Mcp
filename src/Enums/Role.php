<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Enums;

enum Role: string
{
    case Assistant = 'assistant';
    case User = 'user';
}
