<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class RateLimiter
{
    private const DIRECTORY = __DIR__ . '/../../storage/ratelimit';

    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $windowSeconds
    ) {
        if (!is_dir(self::DIRECTORY)) {
            mkdir(self::DIRECTORY, 0750, true);
        }
    }

    public function hit(string $key): int
    {
        $path = $this->path($key);
        $now = time();
        $handle = $this->open($path);

        try {
            $data = $this->lockedRead($handle);
            $data = array_values(array_filter(
                $data,
                fn (int $ts): bool => $ts > $now - $this->windowSeconds
            ));
            $data[] = $now;
            $this->lockedWrite($handle, $data);

            return count($data);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function attempts(string $key): int
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return 0;
        }

        $now = time();
        $handle = $this->open($path);

        try {
            $data = $this->lockedRead($handle);

            return count(array_filter(
                $data,
                fn (int $ts): bool => $ts > $now - $this->windowSeconds
            ));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function tooManyAttempts(string $key): bool
    {
        return $this->attempts($key) >= $this->maxAttempts;
    }

    public function clear(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function keyFromIpAndEmail(string $ip, string $email): string
    {
        return hash('xxh128', $ip . '|' . strtolower(trim($email)));
    }

    private function path(string $key): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9]/', '', $key);

        return self::DIRECTORY . '/' . $sanitized . '.json';
    }

    /** @return resource */
    private function open(string $path)
    {
        $handle = fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Cannot lock rate limit file.');
        }

        return $handle;
    }

    /** @return list<int> */
    private function lockedRead($handle): array
    {
        rewind($handle);
        $contents = stream_get_contents($handle);
        if ($contents === false || $contents === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }

            return array_values(array_filter($decoded, 'is_int'));
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param list<int> $data */
    private function lockedWrite($handle, array $data): void
    {
        $encoded = json_encode($data, JSON_THROW_ON_ERROR);
        rewind($handle);
        ftruncate($handle, 0);
        $written = fwrite($handle, $encoded);
        if ($written === false || $written !== strlen($encoded)) {
            throw new RuntimeException('Cannot write rate limit file.');
        }
        fflush($handle);
    }
}
