<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public function token(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    public function verify(string $token): bool
    {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e($this->token()) . '">';
    }

    public static function verifyRequest(): bool
    {
        $instance = new self();
        $token = $_POST['_csrf'] ?? '';

        return is_string($token) && $instance->verify($token);
    }
}
