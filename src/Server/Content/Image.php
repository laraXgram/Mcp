<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Server\Content;

use LaraGram\Mcp\Server\Concerns\HasMeta;
use LaraGram\Mcp\Server\Contracts\Content;
use LaraGram\Mcp\Server\Prompt;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Mcp\Server\Tool;

class Image implements Content
{
    use HasMeta;

    public function __construct(protected string $data, protected string $mimeType = 'image/png')
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
        return $this->mergeMeta([
            'blob' => base64_encode($this->data),
            'uri' => $resource->uri(),
            'mimeType' => $this->mimeType,
        ]);
    }

    public function __toString(): string
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->mergeMeta([
            'type' => 'image',
            'data' => base64_encode($this->data),
            'mimeType' => $this->mimeType,
        ]);
    }
}
