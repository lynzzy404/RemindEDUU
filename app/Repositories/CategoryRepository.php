<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\JsonStore;

final class CategoryRepository
{
    private JsonStore $store;

    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    public function __construct(string $dataPath)
    {
        $this->store = new JsonStore($dataPath . '/categories.json');
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
        $category['id'] = JsonStore::generateId('cat');
        $category['user_id'] = $userId;
        $category['created_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $category['updated_at'] = $category['created_at'];

        $this->store->transaction(static function (array &$document) use ($category): void {
            $document['items'][] = $category;
        });

        return $category;
    }

    /** @param array<string, mixed> $updates */
    public function update(string $id, string $userId, array $updates): void
    {
        $this->store->transaction(static function (array &$document) use ($id, $userId, $updates): void {
            foreach ($document['items'] as &$item) {
                if (is_array($item) && ($item['id'] ?? null) === $id && ($item['user_id'] ?? null) === $userId) {
                    $item = array_merge($item, $updates, ['updated_at' => gmdate('Y-m-d\TH:i:s\Z')]);
                    break;
                }
            }
        });
    }

    public function delete(string $id, string $userId): void
    {
        $this->store->transaction(static function (array &$document) use ($id, $userId): void {
            $document['items'] = array_values(array_filter(
                $document['items'],
                static fn ($item): bool => !is_array($item) || ($item['id'] ?? null) !== $id || ($item['user_id'] ?? null) !== $userId
            ));
        });
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
