<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

final class Auth
{
    public function __construct(private readonly ?UserRepository $userRepository = null)
    {
    }

    public static function id(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = self::id();
        if ($id === null || $this->userRepository === null) {
            return null;
        }

        $user = $this->userRepository->findById($id);

        if ($user === null) {
            self::logout();

            return null;
        }

        unset($user['password_hash']);

        return $user;
    }

    public function check(): bool
    {
        return self::id() !== null;
    }

    public function guest(): bool
    {
        return !self::check();
    }

    public static function login(string $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }
}
