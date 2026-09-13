<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Runtime;

use LaraGram\Container\Container;
use LaraGram\Mcp\Server\Tool;

class Toolset
{
    /** @var array<int, string>|null */
    protected ?array $environments = ['local'];

    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListListensTool::class,
        SimulateUpdateTool::class,
        RenderTemplateTool::class,
        ConversationStateTool::class,
    ];

    /**
     * Only expose the tools in the given environments ("local" by default).
     */
    public function inEnvironments(string ...$environments): static
    {
        $this->environments = $environments;

        return $this;
    }

    /**
     * Expose the tools in every environment.
     */
    public function inAllEnvironments(): static
    {
        $this->environments = null;

        return $this;
    }

    /**
     * Only include the given tools.
     *
     * @param  class-string<Tool>  ...$tools
     */
    public function only(string ...$tools): static
    {
        $this->tools = array_values(array_intersect($this->tools, $tools));

        return $this;
    }

    /**
     * @return array<int, Tool>
     */
    public function all(): array
    {
        return array_map(fn (string $tool): Tool => new $tool($this), $this->tools);
    }

    /**
     * @internal
     */
    public function enabled(): bool
    {
        return $this->environments === null
            || Container::getInstance()->make('app')->environment($this->environments);
    }
}
