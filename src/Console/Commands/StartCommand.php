<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Console\Commands;

use LaraGram\Console\Command;
use LaraGram\Mcp\Server\Registrar;
use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Input\InputArgument;
use LaraGram\Console\Input\InputOption;
use LaraGram\Mcp\Server\Transport\CoroutineStdioTransport;

#[AsCommand(
    name: 'mcp:start',
    description: 'Start the MCP Server for a given handle'
)]
class StartCommand extends Command
{
    public function handle(Registrar $registrar): int
    {
        $handle = $this->argument('handle');

        assert(is_string($handle));

        $server = $registrar->getLocalServer($handle);

        if ($server === null) {
            $this->components->error("MCP Server with name [{$handle}] not found. Did you register it using [Mcp::local()]?");

            return static::FAILURE;
        }

        if (! $this->option('coroutine')) {
            $server();

            return static::SUCCESS;
        }

        if (! function_exists('Swoole\Coroutine\run')) {
            $this->components->error('The [--coroutine] option requires the Swoole extension.');

            return static::FAILURE;
        }

        \Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);

        \Swoole\Coroutine\run(fn (): mixed => $server(fn (): CoroutineStdioTransport => new CoroutineStdioTransport));

        return static::SUCCESS;
    }

    /**
     * @return array<int, array<int, string|int>>
     */
    protected function getArguments(): array
    {
        return [
            ['handle', InputArgument::REQUIRED, 'The handle of the MCP server to start.'],
        ];
    }

    /**
     * @return array<int, array<int, string|int|null>>
     */
    protected function getOptions(): array
    {
        return [
            ['coroutine', null, InputOption::VALUE_NONE, 'Run the server inside a Swoole coroutine (required for subscriptions and coroutine-based packages).'],
        ];
    }
}
