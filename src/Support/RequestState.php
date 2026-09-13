<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Support;

use LaraGram\Container\Container;
use LaraGram\Support\Str;
use RuntimeException;

/**
 * Integrity-protected "requestState" for Multi Round-Trip Requests.
 *
 * The state travels through the client, so it is signed with the application key
 * and bound to the authenticated principal, the originating request (method, name
 * and arguments) and a short expiry, as the specification requires.
 */
final class RequestState
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function seal(array $data, string $fingerprint, ?string $subject, int $ttl = 600): string
    {
        $payload = self::encode((string) json_encode([
            'data' => $data,
            'fp' => $fingerprint,
            'sub' => $subject,
            'exp' => time() + $ttl,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $payload.'.'.self::encode(hash_hmac('sha256', $payload, self::key(), true));
    }

    /**
     * Verify the state and get its data, or null when it was tampered with, expired,
     * or issued for another request or principal.
     *
     * @return array<string, mixed>|null
     */
    public static function open(?string $state, string $fingerprint, ?string $subject): ?array
    {
        if ($state === null || substr_count($state, '.') !== 1) {
            return null;
        }

        [$payload, $signature] = explode('.', $state);

        if (! hash_equals(self::encode(hash_hmac('sha256', $payload, self::key(), true)), $signature)) {
            return null;
        }

        $decoded = json_decode((string) self::decode($payload), true);

        if (! is_array($decoded)
            || ($decoded['exp'] ?? 0) < time()
            || ! hash_equals((string) ($decoded['fp'] ?? ''), $fingerprint)
            || ($decoded['sub'] ?? null) !== $subject) {
            return null;
        }

        return is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
    }

    /**
     * Identify a request by its method, name or URI and arguments.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function fingerprint(string $method, ?string $name, array $arguments): string
    {
        ksort($arguments);

        return hash('sha256', $method."\0".$name."\0".json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function key(): string
    {
        $key = (string) Container::getInstance()->make('config')->get('app.key');

        if ($key === '') {
            throw new RuntimeException('An application key is required to sign MCP request state.');
        }

        return Str::startsWith($key, 'base64:') ? (string) base64_decode(substr($key, 7)) : $key;
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): string|false
    {
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
