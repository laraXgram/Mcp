<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Console\Commands;

use LaraGram\Console\GeneratorCommand;
use LaraGram\Contracts\Filesystem\FileNotFoundException;
use LaraGram\Support\Str;
use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Input\InputOption;

#[AsCommand(
    name: 'make:mcp-tool',
    description: 'Create a new MCP tool class'
)]
class MakeToolCommand extends GeneratorCommand
{
    /**
     * @var string
     */
    protected $type = 'Tool';

    protected function getStub(): string
    {
        return file_exists($customPath = $this->laragram->basePath('stubs/mcp-tool.stub'))
            ? $customPath
            : __DIR__.'/../../../stubs/mcp-tool.stub';
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return "{$rootNamespace}\\Mcp\\Tools";
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the tool already exists'],
        ];
    }

    /**
     * @param  string  $name
     *
     * @throws FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $className = class_basename($name);
        $title = Str::headline($className);

        return str_replace(
            '{{ title }}',
            $title,
            $stub,
        );
    }
}
