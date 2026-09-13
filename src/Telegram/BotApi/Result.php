<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi;

class Result
{
    public function __construct(
        public readonly bool $ok,
        public readonly mixed $result = null,
        public readonly ?int $errorCode = null,
        public readonly ?string $description = null,
        public readonly ?float $retryAfter = null,
    ) {
        //
    }

    /**
     * Normalize a raw Bot API response.
     *
     * Handles Telegram's own shape ({ok, result} / {ok, error_code, description, parameters}),
     * the legacy transport failure shape ({ok, code, message}) and the empty body returned
     * by the fire-and-forget "no_response_curl" mode.
     */
    public static function from(mixed $response): static
    {
        if ($response === '' || $response === null) {
            return new static(true, null, description: 'The request was sent without waiting for a response.');
        }

        if (is_object($response)) {
            $response = json_decode((string) json_encode($response), true);
        }

        if (! is_array($response)) {
            return new static(false, errorCode: 0, description: 'Unexpected response from the Telegram Bot API.');
        }

        if (($response['ok'] ?? false) === true) {
            return new static(true, $response['result'] ?? null);
        }

        $retryAfter = $response['parameters']['retry_after'] ?? null;

        return new static(
            false,
            errorCode: (int) ($response['error_code'] ?? $response['code'] ?? 0),
            description: Redactor::redact((string) ($response['description'] ?? $response['message'] ?? 'Unknown error.')),
            retryAfter: is_numeric($retryAfter) ? (float) $retryAfter : null,
        );
    }

    public function errorMessage(): string
    {
        $message = "Telegram Bot API error [{$this->errorCode}]: {$this->description}";

        if ($this->retryAfter !== null) {
            $message .= " Retry after {$this->retryAfter} seconds.";
        }

        return $message;
    }
}
