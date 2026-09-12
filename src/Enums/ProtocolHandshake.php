<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Enums;

enum ProtocolHandshake
{
    case Initialize;
    case Discovery;
}
