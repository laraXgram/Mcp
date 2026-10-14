<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Auth;

use LaraGram\Citadel\Telegram\IssueAccessToken as CitadelIssueAccessToken;

/**
 * A bot listener that issues and revokes Citadel access tokens for MCP clients
 * from a private chat, so users authenticate with their Telegram identity.
 *
 *     IssueAccessToken::using(abilities: ['telegram:sendMessage'], expiresInMinutes: 60 * 24 * 30);
 *
 *     Bot::onCommand('mcp {action?}', IssueAccessToken::class);
 *
 * "/mcp" issues a new token and "/mcp revoke" deletes every token issued this way.
 * Requires laraxgram/citadel; the user model must use Citadel's HasApiTokens trait.
 */
class IssueAccessToken extends CitadelIssueAccessToken
{
    protected static string $tokenName = 'mcp';

    /** @var array<int, string> */
    protected static array $abilities = ['*'];

    protected static ?int $expiresInMinutes = null;

    protected static string $guard = 'bot';

    /**
     * Configure the issued tokens.
     *
     * @param  array<int, string>  $abilities
     */
    public static function using(string $name = 'mcp', array $abilities = ['*'], ?int $expiresInMinutes = null, string $guard = 'bot'): void
    {
        parent::using($name, $abilities, $expiresInMinutes, $guard);
    }
}
