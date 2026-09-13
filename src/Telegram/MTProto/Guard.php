<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\MTProto;

use LaraGram\Support\Str;

/**
 * TL methods that are never exposed: they log the account in or out, hand over
 * credentials or sessions, spend money, or bypass the client's own protocol layer.
 * This list is enforced in code and cannot be relaxed through configuration.
 */
final class Guard
{
    public const BLOCKED = [
        'auth.*',
        'account.deleteAccount',
        'account.resetAuthorization',
        'account.getAuthorizations',
        'account.changeAuthorizationSettings',
        'account.getAuthorizationForm',
        'account.acceptAuthorization',
        'account.*Password*',
        'account.*Passkey*',
        'account.*WebAuthorization*',
        'account.*Takeout*',
        'account.sendChangePhoneCode',
        'account.changePhone',
        'account.sendConfirmPhoneCode',
        'account.confirmPhone',
        'account.sendVerifyEmailCode',
        'account.verifyEmail',
        'account.registerDevice',
        'account.unregisterDevice',
        'account.updateDeviceLocked',
        'account.getSecureValue',
        'account.getAllSecureValues',
        'account.saveSecureValue',
        'account.deleteSecureValue',
        'payments.*',
        'messages.requestEncryption',
        'messages.acceptEncryption',
        'messages.discardEncryption',
        'messages.*Encrypted*',
        'upload.saveFilePart',
        'upload.saveBigFilePart',
        'test.*',
    ];

    public const DESTRUCTIVE = [
        'delete*', 'ban*', 'leave*', 'kick*', 'revoke*', 'report*', 'discard*',
        'block*', 'reset*', 'clear*', 'remove*', 'decline*', 'hide*', 'toggleSlowMode', 'editBanned',
    ];

    public const READ_ONLY = ['get*', 'search*', 'check*', 'resolve*', 'fetch*'];

    public static function blocked(string $method): bool
    {
        // Top-level methods (invokeWithLayer, initConnection, ...) are protocol wrappers.
        return ! str_contains($method, '.') || Str::is(self::BLOCKED, $method);
    }

    public static function isDestructive(string $method): bool
    {
        return Str::is(self::DESTRUCTIVE, Str::afterLast($method, '.'));
    }

    public static function isReadOnly(string $method): bool
    {
        return Str::is(self::READ_ONLY, Str::afterLast($method, '.'));
    }
}
