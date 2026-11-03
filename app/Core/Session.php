<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public function __construct(private readonly array $config)
    {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name((string) $this->config['session_name']);
        $isHttps = parse_url((string) $this->config['app_url'], PHP_URL_SCHEME) === 'https';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $now = time();
        $createdAt = (int) ($_SESSION['_created_at'] ?? $now);
        $lastActivity = (int) ($_SESSION['_last_activity'] ?? $now);
        $lifetime = (int) $this->config['session_lifetime'];
        if ($now - $lastActivity > $lifetime || $now - $createdAt > $lifetime) {
            $_SESSION = [];
            session_regenerate_id(true);
            $createdAt = $now;
        }

        $_SESSION['_created_at'] = $createdAt;
        $_SESSION['_last_activity'] = $now;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public function pullFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return is_string($message) ? $message : null;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
