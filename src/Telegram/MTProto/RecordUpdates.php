<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Mcp\Facades\Mcp;
use LaraGram\Mcp\Telegram\UpdateBuffer;
use LaraGram\MTProto\Events\UpdateReceived;
use LaraGram\Support\Facades\Event;
use Throwable;

/**
 * Records every update an MTProto session receives and notifies subscribed MCP
 * clients: "mtproto://sessions/{session}/updates" and ".../chats/{peer}/updates",
 * where the peer id uses the Bot API convention (users positive, groups negative,
 * channels -100...).
 *
 *     RecordUpdates::listen(); // in the app booted by the pump (e.g. AppServiceProvider::boot())
 */
class RecordUpdates
{
    public static function listen(): void
    {
        Event::listen(UpdateReceived::class, static::class);
    }

    public function handle(UpdateReceived $event): void
    {
        try {
            $update = Normalizer::normalize($event->update);
            $session = $event->session;

            UpdateBuffer::push("mtproto:{$session}", ['type' => $event->type, 'update' => $update]);
            Mcp::resourceUpdated("mtproto://sessions/{$session}/updates");

            if (($peer = static::peerId($update)) !== null) {
                UpdateBuffer::push("mtproto:{$session}:chat:{$peer}", ['type' => $event->type, 'update' => $update]);
                Mcp::resourceUpdated("mtproto://sessions/{$session}/chats/{$peer}/updates");
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public static function peerId(array $update): ?string
    {
        $peer = $update['message']['peer_id'] ?? $update['peer'] ?? $update['peer_id'] ?? null;

        if (! is_array($peer)) {
            return isset($update['user_id']) && is_int($update['user_id']) ? (string) $update['user_id'] : null;
        }

        return match ($peer['_'] ?? null) {
            'peerUser' => isset($peer['user_id']) ? (string) $peer['user_id'] : null,
            'peerChat' => isset($peer['chat_id']) ? '-'.$peer['chat_id'] : null,
            'peerChannel' => isset($peer['channel_id']) ? '-100'.$peer['channel_id'] : null,
            default => null,
        };
    }
}
