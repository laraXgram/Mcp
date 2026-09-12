<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Console\Commands;

use LaraGram\Console\GeneratorCommand;
use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-resource',
    description: 'Create a new MCP resource class'
)]
class MakeResourceCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Resource';

    protected function getStub(): string
    {
        return file_exists($customPath = $this->laragram->basePath('stubs/mcp-resource.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/mcp-resource.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Resources";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the resource already exists'],
        ];
    }
}
