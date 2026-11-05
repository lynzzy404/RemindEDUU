<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\JsonStore;
use RuntimeException;

final class CategoryRepository
{
    private JsonStore $store;
    private JsonStore $taskStore;

    /** @var list<string> */
    public const ALLOWED_COLORS = [
        '#FFE14D',
        '#FF6B9D',
        '#4D9DFF',
        '#5EEAA0',
        '#FF9F43',
        '#B794F6',
    ];

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(string $dataPath)
    {
        $this->store = new JsonStore($dataPath . '/categories.json');
        $this->taskStore = new JsonStore($dataPath . '/tasks.json');
    }

    /** @return list<array<string, mixed>> */
    public function allByUser(string $userId): array
    {
        $data = $this->store->read();
        $result = [];

        foreach ($data['items'] as $item) {
            if (is_array($item) && ($item['user_id'] ?? null) === $userId) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    public function findOwned(string $id, string $userId): ?array
    {
        $cacheKey = $userId;
        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = [];
            foreach ($this->allByUser($userId) as $cat) {
                $this->cache[$cacheKey][$cat['id']] = $cat;
            }
        }

        return $this->cache[$cacheKey][$id] ?? null;
    }

    public function belongsToUser(string $categoryId, string $userId): bool
    {
        return $this->findOwned($categoryId, $userId) !== null;
    }

    public function nameExistsForUser(string $userId, string $name, ?string $excludeId = null): bool
    {
        $categories = $this->allByUser($userId);
        $normalizedName = mb_strtolower(trim($name));

        foreach ($categories as $category) {
            if ($excludeId !== null && ($category['id'] ?? null) === $excludeId) {
                continue;
            }
            if (mb_strtolower(trim($category['name'] ?? '')) === $normalizedName) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $category */
    public function create(string $userId, array $category): array
    {
        $name = trim((string) ($category['name'] ?? ''));
        $color = strtoupper(trim((string) ($category['color'] ?? '')));
        if ($name === '' || !in_array($color, self::ALLOWED_COLORS, true)) {
            throw new RuntimeException('Invalid category data.');
        }

        $categoryId = JsonStore::generateId('cat');
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $created = [
            'id' => $categoryId,
            'user_id' => $userId,
            'name' => $name,
            'color' => $color,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->store->transaction(static function (array &$document) use ($created): void {
            $userCategories = array_filter(
                $document['items'],
                static fn ($item): bool => is_array($item) && ($item['user_id'] ?? null) === $created['user_id']
            );
            if (count($userCategories) >= 50) {
                throw new RuntimeException('Category limit reached.');
            }
            $normalizedName = mb_strtolower($created['name']);
            foreach ($userCategories as $item) {
                if (mb_strtolower(trim((string) ($item['name'] ?? ''))) === $normalizedName) {
                    throw new RuntimeException('Category name already exists.');
                }
            }
            $document['items'][] = $created;
        });

        $this->cache = [];

        return $created;
    }

    /** @param array<string, mixed> $updates */
    public function update(string $id, string $userId, array $updates): void
    {
        $name = trim((string) ($updates['name'] ?? ''));
        $color = strtoupper(trim((string) ($updates['color'] ?? '')));
        if ($name === '' || !in_array($color, self::ALLOWED_COLORS, true)) {
            throw new RuntimeException('Invalid category data.');
        }

        $this->store->transaction(static function (array &$document) use ($id, $userId, $name, $color): void {
            $found = false;
            foreach ($document['items'] as $candidate) {
                if (is_array($candidate) && ($candidate['user_id'] ?? null) === $userId
                    && ($candidate['id'] ?? null) !== $id
                    && mb_strtolower(trim((string) ($candidate['name'] ?? ''))) === mb_strtolower($name)) {
                    throw new RuntimeException('Category name already exists.');
                }
            }
            foreach ($document['items'] as &$item) {
                if (is_array($item) && ($item['id'] ?? null) === $id && ($item['user_id'] ?? null) === $userId) {
                    $item = array_merge($item, ['name' => $name, 'color' => $color, 'updated_at' => gmdate('Y-m-d\TH:i:s\Z')]);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new RuntimeException('Category not found.');
            }
        });
        $this->cache = [];
    }

    public function delete(string $id, string $userId): void
    {
        $this->store->transaction(function (array &$document) use ($id, $userId): void {
            $found = false;
            foreach ($document['items'] as $item) {
                if (is_array($item) && ($item['id'] ?? null) === $id && ($item['user_id'] ?? null) === $userId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new RuntimeException('Category not found.');
            }

            $this->taskStore->transaction(static function (array &$tasks) use ($id, $userId): void {
                foreach ($tasks['items'] as &$task) {
                    if (is_array($task) && ($task['user_id'] ?? null) === $userId && ($task['category_id'] ?? null) === $id) {
                        $task['category_id'] = null;
                        $task['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
                    }
                }
            });

            $document['items'] = array_values(array_filter(
                $document['items'],
                static fn ($item): bool => !is_array($item) || ($item['id'] ?? null) !== $id || ($item['user_id'] ?? null) !== $userId
            ));
        });
        $this->cache = [];
    }

    /** @return array<string, int> */
    public function taskCountsByCategory(string $userId): array
    {
        $data = $this->taskStore->read();
        $counts = [];
        foreach ($data['items'] as $task) {
            if (is_array($task) && ($task['user_id'] ?? null) === $userId && is_string($task['category_id'] ?? null)) {
                $categoryId = $task['category_id'];
                $counts[$categoryId] = ($counts[$categoryId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function seedDefaults(string $userId): void
    {
        $defaults = [
            ['name' => 'Pribadi', 'color' => '#FF6B9D'],
            ['name' => 'Pekerjaan', 'color' => '#4D9DFF'],
            ['name' => 'Belajar', 'color' => '#FFE14D'],
        ];

        foreach ($defaults as $category) {
            $this->create($userId, $category);
        }
    }
}
