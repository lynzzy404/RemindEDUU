<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Logger
{
    private const DIRECTORY = __DIR__ . '/../../storage/logs';

    public function __construct(private readonly string $filename = 'app.log')
    {
        if (!is_dir(self::DIRECTORY)) {
            mkdir(self::DIRECTORY, 0750, true);
        }
    }

    public function log(string $message, string $type = 'INFO'): void
    {
        $path = self::DIRECTORY . '/' . $this->filename;
        $entry = sprintf(
            '[%s] %s: %s %s%s',
            gmdate('Y-m-d\TH:i:s\Z'),
            $type,
            $this->sanitize($message),
            PHP_EOL,
            PHP_EOL
        );

        $handle = fopen($path, 'c');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Cannot lock log file.');
            }

            fseek($handle, 0, SEEK_END);
            fwrite($handle, $entry);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function info(string $message): void
    {
        $this->log($message, 'INFO');
    }

    public function warning(string $message): void
    {
        $this->log($message, 'WARNING');
    }

    public function error(string $message): void
    {
        $this->log($message, 'ERROR');
    }

    /** @param array<string, mixed> $context */
    public function security(string $event, ?string $userId, string $ip, array $context = []): void
    {
        $message = sprintf(
            '%s | user=%s | ip=%s',
            $event,
            $userId ?? 'anonymous',
            $this->sanitize($ip)
        );

        foreach ($context as $key => $value) {
            if (!in_array($key, ['password', 'hash', 'token', 'session', 'secret', 'key'], true)) {
                $message .= ' | ' . $key . '=' . $this->sanitize((string) $value);
            }
        }

        $this->log($message, 'SECURITY');
    }

    private function sanitize(string $value): string
    {
        return str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $value);
    }
}
