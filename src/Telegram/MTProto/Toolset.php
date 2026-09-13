<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use Closure;
use LaraGram\Container\Container;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\ResponseFactory;
use LaraGram\Mcp\Server\Tool;
use LaraGram\MTProto\Contracts\Invoker;
use LaraGram\MTProto\TL\TLMethod;
use LaraGram\Support\Str;
use RuntimeException;
use Throwable;

class Toolset
{
    /** @var array<int, string> */
    protected array $only = [];

    /** @var array<int, string> */
    protected array $except = [];

    protected bool $readOnly = false;

    protected bool $withoutDestructive = false;

    protected string $prefix = 'mtproto_';

    protected ?string $session = null;

    /** @var array<int, string>|null */
    protected ?array $sessions = null;

    /** @var (Closure(): array<int, int|string>)|array<int, int|string>|null */
    protected Closure|array|null $allowedPeers = null;

    /** @var array<int, string>|null */
    protected ?array $fileDirectories = null;

    protected ?string $abilityFormat = null;

    protected bool $confirmDestructive = false;

    /**
     * Only include TL methods matching the given patterns, e.g. "messages.*" or "channels.get*".
     */
    public function only(string ...$patterns): static
    {
        $this->only = [...$this->only, ...$patterns];

        return $this;
    }

    /**
     * Exclude TL methods matching the given patterns.
     */
    public function except(string ...$patterns): static
    {
        $this->except = [...$this->except, ...$patterns];

        return $this;
    }

    /**
     * Only allow reading methods (get*, search*, check*, resolve*).
     */
    public function readOnly(): static
    {
        $this->readOnly = true;

        return $this;
    }

    /**
     * Exclude methods that delete, ban, leave, report or otherwise irreversibly change state.
     */
    public function withoutDestructive(): static
    {
        $this->withoutDestructive = true;

        return $this;
    }

    /**
     * Run every call on the given session.
     */
    public function session(string $session): static
    {
        $this->session = $session;

        return $this;
    }

    /**
     * Let the client pick one of the given sessions (all authorized sessions by default).
     *
     * @param  array<int, string>|null  $sessions
     */
    public function selectableSessions(?array $sessions = null): static
    {
        $this->sessions = $sessions ?? $this->manager()->authorizedSessions();

        return $this;
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Restrict peer arguments to the given usernames or ids.
     *
     * @param  (Closure(): array<int, int|string>)|array<int, int|string>  $peers
     */
    public function allowPeers(Closure|array $peers): static
    {
        $this->allowedPeers = $peers;

        return $this;
    }

    /**
     * Directories local files may be sent from (storage/app by default).
     */
    public function allowFilesFrom(string ...$directories): static
    {
        $this->fileDirectories = $directories;

        return $this;
    }

    /**
     * Require the access token to grant an ability per method, e.g. "mtproto:{method}".
     */
    public function requireAbilities(string $format = 'mtproto:{method}'): static
    {
        $this->abilityFormat = $format;

        return $this;
    }

    /**
     * Ask the user to confirm (through elicitation) before a destructive method runs.
     * Clients without elicitation support cannot run destructive methods.
     */
    public function confirmDestructive(): static
    {
        $this->confirmDestructive = true;

        return $this;
    }

    /**
     * Get the confirmation response to return before running a destructive call, if one is needed.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function confirmation(Request $request, string $method, array $arguments, bool $destructive): Response|ResponseFactory|null
    {
        if (! $this->confirmDestructive || ! $destructive || $request->confirmed()) {
            return null;
        }

        return $request->declined()
            ? Response::error("The user declined the {$method} call.")
            : Response::confirm("Allow the MTProto session to call {$method} with ".json_encode(Normalizer::normalize($arguments), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'?');
    }

    /**
     * The curated high-level tools plus the generic invoke and describe tools.
     *
     * @return array<int, Tool>
     */
    public function all(): array
    {
        return [...$this->highLevel(), new InvokeTool($this), new DescribeTool($this)];
    }

    /**
     * Curated tools for common tasks built on the Client's own methods.
     *
     * @return array<int, Tool>
     */
    public function highLevel(): array
    {
        $tools = [];

        if ($this->permits('messages.sendMessage') && ($sendMessage = Schema::method('messages.sendMessage')) !== null) {
            $tools[] = new MethodTool($this, $sendMessage);
        }

        foreach (ClientMethodTool::METHODS as $method => $definition) {
            if ($this->permitsClientMethod($method, $definition)) {
                $tools[] = new ClientMethodTool($this, $method, $definition);
            }
        }

        return $tools;
    }

    /**
     * One tool per permitted TL method (hundreds; use them behind ToolSearch), plus the describe tool.
     *
     * @return array<int, Tool>
     */
    public function generated(): array
    {
        $tools = array_map(
            fn (TLMethod $method): MethodTool => new MethodTool($this, $method),
            array_values(array_filter(Schema::methods(), fn (TLMethod $method): bool => $this->permits($method->getFullName()))),
        );

        return [...$tools, new DescribeTool($this)];
    }

    /**
     * Determine if a TL method may be called.
     */
    public function permits(string $method): bool
    {
        if (Guard::blocked($method)) {
            return false;
        }

        if ($this->only !== [] && ! Str::is($this->only, $method)) {
            return false;
        }

        if ($this->except !== [] && Str::is($this->except, $method)) {
            return false;
        }

        if ($this->readOnly && ! Guard::isReadOnly($method)) {
            return false;
        }

        return ! ($this->withoutDestructive && Guard::isDestructive($method));
    }

    /**
     * @param  array{description: string, methods: array<int, string>, readOnly?: bool, destructive?: bool}  $definition
     */
    protected function permitsClientMethod(string $method, array $definition): bool
    {
        foreach ($definition['methods'] as $tlMethod) {
            if (! $this->permits($tlMethod)) {
                return false;
            }
        }

        if ($this->readOnly && ! ($definition['readOnly'] ?? false)) {
            return false;
        }

        return ! ($this->withoutDestructive && ($definition['destructive'] ?? false));
    }

    /**
     * Run a call on the session's invoker and turn the outcome into a tool response.
     *
     * @param  Closure(Invoker): mixed  $callback
     */
    public function run(?string $session, Closure $callback): Response|ResponseFactory
    {
        try {
            $result = $callback($this->manager()->invoker($this->resolveSession($session)));
        } catch (Throwable $e) {
            return Response::error('MTProto error: '.$e->getMessage());
        }

        return Response::structured(['result' => Normalizer::normalize($result)]);
    }

    public function resolveSession(?string $session): string
    {
        if ($this->session !== null) {
            return $this->session;
        }

        if ($session !== null && $this->sessions !== null && ! in_array($session, $this->sessions, true)) {
            throw new RuntimeException("Session [{$session}] is not available.");
        }

        return $session ?? (string) (config('mtproto.session.name') ?: 'default');
    }

    /**
     * Find the first peer argument that is not allowed, if any.
     *
     * @param  array<string, mixed>  $arguments
     * @param  array<int, string>  $peerParameters
     */
    public function disallowedPeer(array $arguments, array $peerParameters): int|string|null
    {
        if ($this->allowedPeers === null) {
            return null;
        }

        $allowed = array_map(
            fn (int|string $peer): string => strtolower(ltrim((string) $peer, '@')),
            $this->allowedPeers instanceof Closure ? ($this->allowedPeers)() : $this->allowedPeers,
        );

        foreach ($peerParameters as $name) {
            if (! array_key_exists($name, $arguments)) {
                continue;
            }

            foreach ((array) $arguments[$name] as $peer) {
                if (! is_scalar($peer) || ! in_array(strtolower(ltrim((string) $peer, '@')), $allowed, true)) {
                    return is_scalar($peer) ? $peer : 'object';
                }
            }
        }

        return null;
    }

    public function fileAllowed(string $path): bool
    {
        $real = realpath($path);

        if ($real === false || ! is_file($real)) {
            return false;
        }

        foreach ($this->fileDirectories ?? [storage_path('app')] as $directory) {
            $root = realpath($directory);

            if ($root !== false && str_starts_with($real, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function abilitiesFor(string $method): array
    {
        return $this->abilityFormat === null ? [] : [str_replace('{method}', $method, $this->abilityFormat)];
    }

    public function prefixValue(): string
    {
        return $this->prefix;
    }

    public function selectsSession(): bool
    {
        return $this->session === null && $this->sessions !== null && $this->sessions !== [];
    }

    /**
     * @return array<int, string>
     */
    public function sessions(): array
    {
        return $this->sessions ?? [];
    }

    /**
     * @return \LaraGram\MTProto\Foundation\ClientManager
     */
    protected function manager(): object
    {
        $container = Container::getInstance();

        if (! $container->bound('mtproto.manager')) {
            throw new RuntimeException('The laraxgram/mtproto package is not installed.');
        }

        return $container->make('mtproto.manager');
    }
}
