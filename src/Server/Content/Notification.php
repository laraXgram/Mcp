<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Content;

use LaraGram\Mcp\Server\Concerns\HasMeta;
use LaraGram\Mcp\Server\Contracts\Content;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\Tool;

class Notification implements Content
{
    use HasMeta;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(protected string $method, protected array $params)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function toTool(Tool $tool): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toPrompt(Prompt $prompt): array
    {
        return $this->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toResource(Resource $resource): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->method;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $params = $this->params;

        if ($this->meta !== null && $this->meta !== [] && ! isset($params['_meta'])) {
            $params['_meta'] = $this->meta;
        }

        return [
            'method' => $this->method,
            'params' => $params,
        ];
    }
}
