<?php

declare(strict_types=1);

namespace LaraGram\Mcp;

use LaraGram\Container\Container;
use LaraGram\Contracts\Auth\Authenticatable;
use LaraGram\Contracts\Support\Arrayable;
use LaraGram\Support\Facades\Validator;
use LaraGram\Support\Traits\Conditionable;
use LaraGram\Support\Traits\InteractsWithData;
use LaraGram\Support\Traits\Macroable;
use LaraGram\Validation\ValidationException;
use LaraGram\Mcp\Support\RequestState;

/**
 * @implements Arrayable<string, mixed>
 */
class Request implements Arrayable
{
    use Conditionable;
    use InteractsWithData;
    use Macroable;

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<string, mixed>|null  $meta
     */
    /**
     * The client's responses to input requests of a previous attempt, keyed by request key.
     *
     * @var array<string, mixed>
     */
    protected array $inputResponses = [];

    protected ?string $requestState = null;

    protected string $fingerprint = '';

    public function __construct(
        protected array $arguments = [],
        protected ?array $meta = null,
        protected ?string $uri = null,
    ) {
        //
    }

    /**
     * @param  array<array-key, string>|array-key|null  $keys
     * @return array<string, mixed>
     */
    public function all(mixed $keys = null): array
    {
        if (is_null($keys)) {
            return $this->data();
        }

        return array_intersect_key($this->data(), array_flip(is_array($keys) ? $keys : func_get_args()));
    }

    protected function data(mixed $key = null, mixed $default = null): mixed
    {
        return data_get($this->arguments, $key, $default);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data($key, $default);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function merge(array $data): static
    {
        $this->arguments = array_merge($this->arguments, $data);

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->arguments;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $messages
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $rules, array $messages = [], array $attributes = []): array
    {
        return Validator::validate($this->all(), $rules, $messages, $attributes);
    }

    public function user(?string $guard = null): ?Authenticatable
    {
        $auth = Container::getInstance()->make('auth');

        return call_user_func($auth->userResolver(), $guard);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        return $this->meta;
    }

    public function uri(): ?string
    {
        return $this->uri;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function setMeta(?array $meta): void
    {
        $this->meta = $meta;
    }

    public function setUri(?string $uri): void
    {
        $this->uri = $uri;
    }

    /**
     * @param  array<string, mixed>  $inputResponses
     */
    public function setInput(array $inputResponses, ?string $requestState, string $fingerprint): void
    {
        $this->inputResponses = $inputResponses;
        $this->requestState = $requestState;
        $this->fingerprint = $fingerprint;
    }

    /**
     * @return array<string, mixed>
     */
    public function inputResponses(): array
    {
        return $this->inputResponses;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function rawRequestState(): ?string
    {
        return $this->requestState;
    }

    /**
     * The verified data stored with Response::inputRequired(), or an empty array.
     *
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return (array) ($this->verifiedState()['state'] ?? []);
    }

    /**
     * Get the client's response to an input request issued for this very request.
     *
     * Responses are only returned when the signed request state proves the input
     * was requested by this server for the same principal and request.
     *
     * @return array<string, mixed>|null
     */
    public function inputResponse(string $key): ?array
    {
        if (! in_array($key, (array) ($this->verifiedState()['inputs'] ?? []), true)) {
            return null;
        }

        return is_array($this->inputResponses[$key] ?? null) ? $this->inputResponses[$key] : null;
    }

    /**
     * Get the submitted content of an accepted elicitation, or null when it was declined, cancelled or not answered.
     *
     * @return array<string, mixed>|null
     */
    public function elicited(string $key): ?array
    {
        $response = $this->inputResponse($key);

        return ($response['action'] ?? null) === 'accept' ? (array) ($response['content'] ?? []) : null;
    }

    /**
     * Determine if the user accepted the confirmation requested with Response::confirm().
     */
    public function confirmed(string $key = 'confirm'): bool
    {
        return ($this->elicited($key)['confirm'] ?? false) === true;
    }

    /**
     * Determine if the user explicitly declined or cancelled an elicitation.
     */
    public function declined(string $key = 'confirm'): bool
    {
        return in_array($this->inputResponse($key)['action'] ?? null, ['decline', 'cancel'], true);
    }

    /**
     * Determine if the client declared the given capability (e.g. "elicitation") on this request.
     */
    public function clientSupports(string $capability): bool
    {
        $capabilities = $this->meta['io.modelcontextprotocol/clientCapabilities'] ?? null;

        return is_array($capabilities) && array_key_exists($capability, $capabilities);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function verifiedState(): ?array
    {
        return RequestState::open($this->requestState, $this->fingerprint, $this->subject());
    }

    /**
     * @internal
     */
    public function subject(): ?string
    {
        try {
            $identifier = $this->user()?->getAuthIdentifier();
        } catch (\Throwable) {
            $identifier = null;
        }

        return $identifier === null ? null : (string) $identifier;
    }
}
