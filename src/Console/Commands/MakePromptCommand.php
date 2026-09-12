<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Console\Commands;

use LaraGram\Console\GeneratorCommand;
use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-prompt',
    description: 'Create a new MCP prompt class'
)]
class MakePromptCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Prompt';

    protected function getStub(): string
    {
        return file_exists($customPath = $this->laragram->basePath('stubs/mcp-prompt.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/mcp-prompt.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Prompts";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the prompt already exists'],
        ];
    }
}
