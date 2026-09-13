<?php

declare(strict_types=1);

namespace LaraGram\Mcp;

use Closure;
use LaraGram\Container\Container;
use LaraGram\Contracts\JsonSchema\JsonSchema;
use LaraGram\JsonSchema\JsonSchema as JsonSchemaFactory;
use LaraGram\JsonSchema\Types\Type;
use LaraGram\Filesystem\FilesystemAdapter;
use LaraGram\Support\Facades\Storage;
use LaraGram\Support\Traits\Conditionable;
use LaraGram\Support\Traits\Macroable;
use InvalidArgumentException;
use JsonException;
use LaraGram\Mcp\Enums\Role;
use LaraGram\Mcp\Schema\Icon;
use LaraGram\Mcp\Server\Content\Audio;
use LaraGram\Mcp\Server\Content\Blob;
use LaraGram\Mcp\Server\Content\Image;
use LaraGram\Mcp\Server\Content\Notification;
use LaraGram\Mcp\Server\Content\ResourceLink;
use LaraGram\Mcp\Server\Content\Text;
use LaraGram\Mcp\Server\Input\InputRequired;
use LaraGram\Mcp\Support\RequestState;
use LaraGram\Mcp\Server\Contracts\Content;
use LaraGram\Mcp\Server\Resource;
use LaraGram\Filesystem\Exception\UnableToReadFile;

class Response
{
    use Conditionable;
    use Macroable;

    protected function __construct(
        protected Content $content,
        protected Role $role = Role::User,
        protected bool $isError = false,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function notification(string $method, array $params = []): static
    {
        return new static(new Notification($method, $params));
    }

    public static function text(string $text): static
    {
        return new static(new Text($text));
    }

    public static function html(string $path): static
    {
        $path = str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $path) ? $path : resource_path($path);

        if (! file_exists($path)) {
            throw new InvalidArgumentException("File not found at path [{$path}].");
        }

        return static::text((string) file_get_contents($path));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    public static function view(string $view, array $data = [], array $mergeData = []): static
    {
        return static::text(view($view, $data, $mergeData)->render());
    }

    /**
     * @internal
     *
     * @throws JsonException
     */
    public static function json(mixed $content): static
    {
        return static::text(json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function blob(string $content): static
    {
        return new static(new Blob($content));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function structured(array $response): ResponseFactory
    {
        if ($response === []) {
            throw new InvalidArgumentException('Structured content cannot be empty.');
        }

        try {
            $json = json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException("Invalid structured content: {$jsonException->getMessage()}", 0, $jsonException);
        }

        $content = Response::text($json);

        return (new ResponseFactory($content))->withStructuredContent($response);
    }

    public static function error(string $text): static
    {
        return new static(new Text($text), isError: true);
    }

    /**
     * Ask the client for more input before the request can complete (Multi Round-Trip Requests).
     *
     * The client fulfills the input requests and retries the request; read the answers with
     * $request->inputResponse($key) and the stored data with $request->state().
     *
     * @param  array<string, array{method: string, params: array<string, mixed>}>  $inputRequests
     * @param  array<string, mixed>  $state
     */
    public static function inputRequired(array $inputRequests = [], array $state = [], int $ttl = 600): ResponseFactory
    {
        $request = Container::getInstance()->make(Request::class);

        $sealed = RequestState::seal(
            ['inputs' => array_keys($inputRequests), 'state' => $state],
            $request->fingerprint(),
            $request->subject(),
            $ttl,
        );

        return (new ResponseFactory([]))->withInputRequired(new InputRequired($inputRequests, $sealed));
    }

    /**
     * Ask the user to fill in a form through the client (elicitation), then read it with $request->elicited($key).
     *
     * @param  (Closure(JsonSchema): array<string, Type>)|array<string, Type>  $schema
     * @param  array<string, mixed>  $state
     */
    public static function elicit(string $key, string $message, Closure|array $schema, array $state = []): ResponseFactory
    {
        $request = Container::getInstance()->make(Request::class);

        if (! $request->clientSupports('elicitation')) {
            return new ResponseFactory(static::error('This action needs confirmation from the user, but the client does not support elicitation.'));
        }

        $requestedSchema = JsonSchemaFactory::object($schema)->toArray();
        $requestedSchema['properties'] ??= (object) [];

        return static::inputRequired([
            $key => [
                'method' => 'elicitation/create',
                'params' => [
                    'mode' => 'form',
                    'message' => $message,
                    'requestedSchema' => $requestedSchema,
                ],
            ],
        ], $state);
    }

    /**
     * Ask the user to confirm an action, then check it with $request->confirmed($key).
     */
    public static function confirm(string $message, string $key = 'confirm'): ResponseFactory
    {
        return static::elicit($key, $message, fn (JsonSchema $schema): array => [
            'confirm' => $schema->boolean()->description('Allow this action.')->required(),
        ]);
    }

    public function content(): Content
    {
        return $this->content;
    }

    /**
     * @param  Response|array<int, Response>  $responses
     */
    public static function make(Response|array $responses): ResponseFactory
    {
        return new ResponseFactory($responses);
    }

    /**
     * @param  array<string, mixed>|string  $meta
     */
    public function withMeta(array|string $meta, mixed $value = null): static
    {
        $this->content->setMeta($meta, $value);

        return $this;
    }

    public static function audio(string $data, string $mimeType = 'audio/wav'): static
    {
        return new static(new Audio($data, $mimeType));
    }

    public static function image(string $data, string $mimeType = 'image/png'): static
    {
        return new static(new Image($data, $mimeType));
    }

    /**
     * @param  string|class-string<Resource>|Resource|ResourceLink  $uri
     * @param  array<string, mixed>  $annotations
     * @param  list<Icon>  $icons
     */
    public static function resourceLink(
        string|Resource|ResourceLink $uri,
        ?string $name = null,
        ?string $mimeType = null,
        ?string $title = null,
        ?string $description = null,
        ?int $size = null,
        array $annotations = [],
        array $icons = [],
    ): static {
        if (is_string($uri) && is_subclass_of($uri, Resource::class)) {
            $uri = Container::getInstance()->make($uri);
        }

        $link = match (true) {
            $uri instanceof ResourceLink => $uri,
            $uri instanceof Resource => (new ResourceLink(
                uri: $uri->uri(),
                name: $name ?? $uri->name(),
                mimeType: $mimeType ?? $uri->mimeType(),
                title: $title ?? $uri->title(),
                description: $description ?? $uri->description(),
                size: $size,
                annotations: array_merge($uri->annotations(), $annotations),
                icons: $icons === [] ? $uri->resolvedIcons() : $icons,
            )),
            default => new ResourceLink(
                uri: $uri,
                name: $name ?? throw new InvalidArgumentException('Resource link name is required when using a URI string.'),
                mimeType: $mimeType,
                title: $title,
                description: $description,
                size: $size,
                annotations: $annotations,
                icons: $icons,
            ),
        };

        return new static($link);
    }

    public static function fromStorage(string $path, ?string $disk = null, ?string $mimeType = null): static
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        try {
            $data = $storage->get($path);
        } catch (UnableToReadFile $unableToReadFile) {
            throw new InvalidArgumentException("File not found at path [{$path}].", 0, $unableToReadFile);
        }

        if ($data === null) {
            throw new InvalidArgumentException("File not found at path [{$path}].");
        }

        $mimeType ??= $storage->mimeType($path) ?: throw new InvalidArgumentException(
            "Unable to determine MIME type for [{$path}].",
        );

        return match (true) {
            str_starts_with($mimeType, 'image/') => static::image($data, $mimeType),
            str_starts_with($mimeType, 'audio/') => static::audio($data, $mimeType),
            default => throw new InvalidArgumentException("Unsupported MIME type [{$mimeType}] for [{$path}]."),
        };
    }

    public function asAssistant(): static
    {
        return new static($this->content, Role::Assistant, $this->isError);
    }

    public function isNotification(): bool
    {
        return $this->content instanceof Notification;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
