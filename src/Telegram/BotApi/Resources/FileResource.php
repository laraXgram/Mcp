<?php

declare(strict_types=1);

namespace LaraGram\Mcp\Telegram\BotApi\Resources;

use finfo;
use LaraGram\Mcp\Request;
use LaraGram\Mcp\Response;
use LaraGram\Mcp\Telegram\BotApi\Client;
use LaraGram\Mcp\Telegram\BotApi\Redactor;
use LaraGram\Support\Facades\Http;
use Throwable;

class FileResource extends BotApiResource
{
    protected string $name = 'telegram-file';

    protected string $title = 'Telegram File';

    protected string $description = 'The contents of a file stored on the Telegram servers, by file_id (getFile). Bots can download files of up to 20 MB.';

    protected string $mimeType = 'application/octet-stream';

    /**
     * The maximum number of bytes to download.
     */
    public static int $maxBytes = 20 * 1024 * 1024;

    protected function path(): string
    {
        return 'files/{file_id}';
    }

    protected function method(): string
    {
        return 'getFile';
    }

    public function handle(Request $request): Response
    {
        $connection = (string) $request->get('connection');

        if (! in_array($connection, Client::connections(), true)) {
            return Response::error("Bot connection [{$connection}] is not configured.");
        }

        $result = (new Client($connection))->call('getFile', ['file_id' => (string) $request->get('file_id')]);

        if (! $result->ok) {
            return Response::error($result->errorMessage());
        }

        $filePath = $result->result['file_path'] ?? null;
        $fileSize = $result->result['file_size'] ?? null;

        if (! is_string($filePath) || $filePath === '') {
            return Response::error('The file is not available for download.');
        }

        if (is_int($fileSize) && $fileSize > static::$maxBytes) {
            return Response::error("The file is larger than the allowed {$this->megabytes(static::$maxBytes)} MB.");
        }

        try {
            $contents = $this->download($connection, $filePath);
        } catch (Throwable $e) {
            return Response::error('Unable to download the file: '.Redactor::redact($e->getMessage()));
        }

        if ($contents === null) {
            return Response::error('Unable to download the file.');
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        return match (true) {
            str_starts_with($mimeType, 'image/') => Response::image($contents, $mimeType),
            str_starts_with($mimeType, 'audio/') => Response::audio($contents, $mimeType),
            default => $this->blob($contents, $mimeType),
        };
    }

    /**
     * Download the file. The URL contains the bot token, so it never leaves the server.
     */
    protected function download(string $connection, string $filePath): ?string
    {
        // A local Bot API server returns absolute paths on its own disk.
        // Only paths inside the configured server directory are read.
        if (str_starts_with($filePath, '/')) {
            $root = realpath((string) config('bot.api_server.dir'));
            $real = realpath($filePath);

            if ($root === false || $real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ! is_readable($real)) {
                return null;
            }

            return (string) file_get_contents($real);
        }

        $token = (string) config("bot.connections.{$connection}.token");
        $server = rtrim((string) config('bot.api_server.endpoint', 'https://api.telegram.org'), '/');

        $response = Http::timeout(30)->get("{$server}/file/bot{$token}/{$filePath}");

        return $response->successful() ? $response->body() : null;
    }

    protected function blob(string $contents, string $mimeType): Response
    {
        $this->mimeType = $mimeType;

        return Response::blob($contents);
    }

    protected function megabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.');
    }
}
