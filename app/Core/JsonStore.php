<?php

declare(strict_types=1);

namespace App\Core;

use JsonException;
use RuntimeException;

final class JsonStore
{
    public function __construct(private readonly string $path)
    {
        if (!is_file($this->path)) {
            throw new RuntimeException('Data file is unavailable.');
        }
    }

    /** @return array{schema_version: int, items: array<int, mixed>} */
    public function read(): array
    {
        $lock = $this->openLock();
        if (!flock($lock, LOCK_SH)) {
            fclose($lock);
            throw new RuntimeException('Data lock is unavailable.');
        }

        try {
            return $this->decodeDocument();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return mixed */
    public function transaction(callable $mutator): mixed
    {
        $lock = $this->openLock();
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('Data lock is unavailable.');
        }

        try {
            $document = $this->decodeDocument();
            $result = $mutator($document);
            $this->writeDocument($document);

            return $result;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function generateId(string $prefix): string
    {
        if (!in_array($prefix, ['usr', 'cat', 'tsk'], true)) {
            throw new RuntimeException('Invalid identifier prefix.');
        }

        return $prefix . '_' . bin2hex(random_bytes(8));
    }

    /** @return array{schema_version: int, items: array<int, mixed>} */
    private function decodeDocument(): array
    {
        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Data file cannot be read.');
        }

        try {
            $document = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            error_log('Invalid JSON data file: ' . $this->safeLogMessage($exception->getMessage()));
            throw new RuntimeException('Data file is invalid.', 0, $exception);
        }

        if (
            !is_array($document)
            || ($document['schema_version'] ?? null) !== 1
            || !is_array($document['items'] ?? null)
        ) {
            error_log('Invalid JSON data envelope.');
            throw new RuntimeException('Data file is invalid.');
        }

        return $document;
    }

    /** @param array{schema_version: int, items: array<int, mixed>} $document */
    private function writeDocument(array $document): void
    {
        $encoded = json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $directory = dirname($this->path);
        $temporaryPath = $directory . DIRECTORY_SEPARATOR . '.'
            . basename($this->path) . '.' . bin2hex(random_bytes(8)) . '.tmp';

        $handle = fopen($temporaryPath, 'x');
        if ($handle === false) {
            throw new RuntimeException('Temporary data file cannot be created.');
        }

        try {
            $written = fwrite($handle, $encoded);
            if ($written === false || $written !== strlen($encoded)) {
                throw new RuntimeException('Temporary data file cannot be written.');
            }
            fflush($handle);
            fclose($handle);
            chmod($temporaryPath, 0640);

            if (!rename($temporaryPath, $this->path)) {
                throw new RuntimeException('Data file cannot be replaced.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @return resource */
    private function openLock()
    {
        $lockPath = $this->path . '.lock';
        $lock = fopen($lockPath, 'c+');
        if ($lock === false) {
            throw new RuntimeException('Data lock cannot be opened.');
        }
        chmod($lockPath, 0640);

        return $lock;
    }

    private function safeLogMessage(string $message): string
    {
        return str_replace(["\r", "\n"], ' ', $message);
    }
}
