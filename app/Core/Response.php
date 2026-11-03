<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        array $headers = []
    ) {
        $this->headers = $headers;
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        if (!preg_match('#^/[A-Za-z0-9/_?&=.%+-]*$#', $location)) {
            throw new \InvalidArgumentException('Invalid redirect location.');
        }

        return new self('', $status, ['Location' => $location]);
    }

    public static function notFound(string $body = 'Halaman tidak ditemukan.'): self
    {
        return new self($body, 404, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function forbidden(string $body = 'Akses ditolak.'): self
    {
        return new self($body, 403, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function download(
        string $body,
        string $contentType,
        string $filename
    ): self {
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            throw new \InvalidArgumentException('Invalid download filename.');
        }

        return new self($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
    }
}
