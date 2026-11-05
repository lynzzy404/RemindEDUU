<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\JsonStore;
use RuntimeException;

final class UserRepository
{
    private JsonStore $store;

    public function __construct(string $dataPath)
    {
        $this->store = new JsonStore($dataPath . '/users.json');
    }

    /** @return array<string, mixed>|null */
    public function findById(string $id): ?array
    {
        $data = $this->store->read();
        foreach ($data['items'] as $user) {
            if (is_array($user) && ($user['id'] ?? null) === $id) {
                return $user;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $data = $this->store->read();
        $normalized = strtolower(trim($email));
        foreach ($data['items'] as $user) {
            if (is_array($user) && strtolower(trim($user['email'] ?? '')) === $normalized) {
                return $user;
            }
        }

        return null;
    }

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): array
    {
        $userId = JsonStore::generateId('usr');
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $this->store->transaction(static function (array &$document) use ($userId, $data, $now): void {
            $normalizedEmail = strtolower(trim((string) $data['email']));
            foreach ($document['items'] as $existingUser) {
                if (is_array($existingUser) && strtolower(trim((string) ($existingUser['email'] ?? ''))) === $normalizedEmail) {
                    throw new RuntimeException('Email already exists.');
                }
            }

            $document['items'][] = [
                'id' => $userId,
                'name' => $data['name'],
                'email' => $normalizedEmail,
                'password_hash' => $data['password_hash'],
                'created_at' => $now,
                'updated_at' => $now,
                'last_login_at' => null,
            ];
        });

        return [
            'id' => $userId,
            'name' => $data['name'],
            'email' => $data['email'],
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => null,
        ];
    }

    public function updateLastLogin(string $id): void
    {
        $this->store->transaction(static function (array &$document) use ($id): void {
            foreach ($document['items'] as &$user) {
                if (is_array($user) && ($user['id'] ?? null) === $id) {
                    $user['last_login_at'] = gmdate('Y-m-d\TH:i:s\Z');
                    break;
                }
            }
        });
    }

    public function updatePasswordHash(string $id, string $hash): void
    {
        $this->store->transaction(static function (array &$document) use ($id, $hash): void {
            foreach ($document['items'] as &$user) {
                if (is_array($user) && ($user['id'] ?? null) === $id) {
                    $user['password_hash'] = $hash;
                    $user['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
                    break;
                }
            }
        });
    }

    public function verifyPassword(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            // SEC-12: tetap habiskan waktu hashing dengan hash dummy,
            // agar waktu respons tidak membocorkan keberadaan email.
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$dHV0dXR1dHV0dXR0dXQ$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA');

            return null;
        }

        if (!is_string($user['password_hash'] ?? null) || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }
}
