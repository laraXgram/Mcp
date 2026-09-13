<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\Auth;

use LaraGram\Container\Container;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Support\Tempora;

/**
 * A bot listener that issues and revokes Citadel access tokens for MCP clients
 * from a private chat, so users authenticate with their Telegram identity.
 *
 *     IssueAccessToken::using(abilities: ['telegram:sendMessage'], expiresInMinutes: 60 * 24 * 30);
 *
 *     Bot::onCommand('mcp {action?}', IssueAccessToken::class);
 *
 * "/mcp" issues a new token and "/mcp revoke" deletes every token issued this way.
 * The user model must use Citadel's HasApiTokens trait.
 */
class IssueAccessToken
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
        static::$tokenName = $name;
        static::$abilities = $abilities;
        static::$expiresInMinutes = $expiresInMinutes;
        static::$guard = $guard;
    }

    public function __invoke(BotRequest $request): void
    {
        $chat = $request->message->chat ?? null;

        if ($chat === null) {
            return;
        }

        if (($chat->type ?? null) !== 'private') {
            $this->reply($request, $chat->id, 'For your security, access tokens are only issued in a private chat with the bot.');

            return;
        }

        $user = Container::getInstance()->make('auth')->guard(static::$guard)->user();

        if ($user === null || ! method_exists($user, 'createToken')) {
            $this->reply($request, $chat->id, 'Your account is not registered, so an access token cannot be issued.');

            return;
        }

        if ($this->action($request) === 'revoke') {
            $revoked = $user->tokens()->where('name', static::$tokenName)->delete();

            $this->reply($request, $chat->id, "Revoked {$revoked} access token(s).");

            return;
        }

        $expiresAt = static::$expiresInMinutes !== null ? Tempora::now()->addMinutes(static::$expiresInMinutes) : null;

        $token = $user->createToken(static::$tokenName, static::$abilities, $expiresAt)->plainTextToken;

        $lines = [
            'Your MCP access token:',
            '',
            '<code>'.htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE).'</code>',
            '',
            'Use it as a Bearer token in your MCP client. It is shown only once; delete this message after copying it.',
        ];

        if ($expiresAt !== null) {
            $lines[] = 'Expires at: '.$expiresAt->toDateTimeString().' UTC.';
        }

        $this->reply($request, $chat->id, implode("\n", $lines), ['parse_mode' => 'HTML', 'protect_content' => true]);
    }

    protected function action(BotRequest $request): string
    {
        $parts = preg_split('/\s+/', trim((string) ($request->message->text ?? '')), 2) ?: [];

        return strtolower(trim($parts[1] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    protected function reply(BotRequest $request, int|string $chatId, string $text, array $parameters = []): void
    {
        $request->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, ...$parameters]);
    }
}
