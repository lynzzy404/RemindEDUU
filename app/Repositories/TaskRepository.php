<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\JsonStore;
use RuntimeException;

final class TaskRepository
{
    /** @var list<string> */
    public const STATUSES = ['todo', 'in_progress', 'done'];

    /** @var list<string> */
    public const PRIORITIES = ['low', 'medium', 'high'];

    private JsonStore $store;

    public function __construct(string $dataPath)
    {
        $this->store = new JsonStore($dataPath . '/tasks.json');
    }

    /**
     * @param array<string, string> $filters
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginateByUser(string $userId, array $filters, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, min(100, $page));
        $perPage = max(1, min(100, $perPage));
        $items = $this->filteredByUser($userId, $filters);

        $sort = (string) ($filters['sort'] ?? 'created_at');
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        $sortFields = ['created_at', 'due_date', 'priority', 'title'];
        if (!in_array($sort, $sortFields, true)) {
            $sort = 'created_at';
        }
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        usort($items, static function (array $left, array $right) use ($sort, $direction): int {
            $a = $left[$sort] ?? null;
            $b = $right[$sort] ?? null;
            if ($a === $b) {
                return 0;
            }
            if ($a === null) {
                return 1;
            }
            if ($b === null) {
                return -1;
            }
            $result = is_string($a) && is_string($b)
                ? strcasecmp($a, $b)
                : ($a <=> $b);

            return $direction === 'asc' ? $result : -$result;
        });

        $total = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        return [
            'items' => array_values(array_slice($items, ($page - 1) * $perPage, $perPage)),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findOwned(string $id, string $userId): ?array
    {
        $document = $this->store->read();
        foreach ($document['items'] as $item) {
            if (is_array($item) && ($item['id'] ?? null) === $id && ($item['user_id'] ?? null) === $userId) {
                return $item;
            }
        }

        return null;
    }

    /** @return array{total: int, completed: int, overdue: int} */
    public function summaryByUser(string $userId): array
    {
        $items = $this->filteredByUser($userId, []);
        $today = gmdate('Y-m-d');
        $completed = 0;
        $overdue = 0;
        foreach ($items as $item) {
            if (($item['status'] ?? null) === 'done') {
                $completed++;
            }
            if (($item['status'] ?? null) !== 'done'
                && is_string($item['due_date'] ?? null)
                && $item['due_date'] < $today) {
                $overdue++;
            }
        }

        return ['total' => count($items), 'completed' => $completed, 'overdue' => $overdue];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function create(string $userId, array $input): array
    {
        $task = $this->normalizedTask($userId, $input);
        $task['id'] = JsonStore::generateId('tsk');
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $task['created_at'] = $now;
        $task['updated_at'] = $now;
        $task['completed_at'] = $task['status'] === 'done' ? $now : null;

        $this->store->transaction(static function (array &$document) use ($task): void {
            $document['items'][] = $task;
        });

        return $task;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function update(string $id, string $userId, array $input): array
    {
        $task = $this->normalizedTask($userId, $input);
        $updated = null;
        $this->store->transaction(static function (array &$document) use ($id, $userId, $task, &$updated): void {
            foreach ($document['items'] as &$item) {
                if (!is_array($item) || ($item['id'] ?? null) !== $id || ($item['user_id'] ?? null) !== $userId) {
                    continue;
                }
                $now = gmdate('Y-m-d\TH:i:s\Z');
                $item = array_merge($item, $task, [
                    'updated_at' => $now,
                    'completed_at' => $task['status'] === 'done'
                        ? (($item['completed_at'] ?? null) ?: $now)
                        : null,
                ]);
                $updated = $item;
                break;
            }
        });

        if ($updated === null) {
            throw new RuntimeException('Task not found.');
        }

        return $updated;
    }

    public function delete(string $id, string $userId): void
    {
        $deleted = false;
        $this->store->transaction(static function (array &$document) use ($id, $userId, &$deleted): void {
            foreach ($document['items'] as $item) {
                if (is_array($item) && ($item['id'] ?? null) === $id && ($item['user_id'] ?? null) === $userId) {
                    $deleted = true;
                    break;
                }
            }
            if ($deleted) {
                $document['items'] = array_values(array_filter(
                    $document['items'],
                    static fn ($item): bool => !is_array($item)
                        || ($item['id'] ?? null) !== $id
                        || ($item['user_id'] ?? null) !== $userId
                ));
            }
        });

        if (!$deleted) {
            throw new RuntimeException('Task not found.');
        }
    }

    /** @return array<string, mixed> */
    public function toggle(string $id, string $userId): array
    {
        $updated = null;
        $this->store->transaction(static function (array &$document) use ($id, $userId, &$updated): void {
            foreach ($document['items'] as &$item) {
                if (!is_array($item) || ($item['id'] ?? null) !== $id || ($item['user_id'] ?? null) !== $userId) {
                    continue;
                }
                $now = gmdate('Y-m-d\TH:i:s\Z');
                $item['status'] = ($item['status'] ?? 'todo') === 'done' ? 'todo' : 'done';
                $item['completed_at'] = $item['status'] === 'done' ? $now : null;
                $item['updated_at'] = $now;
                $updated = $item;
                break;
            }
        });

        if ($updated === null) {
            throw new RuntimeException('Task not found.');
        }

        return $updated;
    }

    /** @return list<array<string, mixed>> */
    private function filteredByUser(string $userId, array $filters): array
    {
        $document = $this->store->read();
        $query = trim((string) ($filters['q'] ?? ''));
        $status = (string) ($filters['status'] ?? '');
        $category = (string) ($filters['category'] ?? '');
        $priority = (string) ($filters['priority'] ?? '');
        $items = [];

        foreach ($document['items'] as $item) {
            if (!is_array($item) || ($item['user_id'] ?? null) !== $userId) {
                continue;
            }
            $haystack = (string) ($item['title'] ?? '') . ' ' . (string) ($item['description'] ?? '');
            if ($query !== '' && mb_stripos($haystack, $query) === false) {
                continue;
            }
            if ($status !== '' && ($item['status'] ?? null) !== $status) {
                continue;
            }
            if ($priority !== '' && ($item['priority'] ?? null) !== $priority) {
                continue;
            }
            if ($category === 'tanpa kategori' || $category === 'none') {
                if (($item['category_id'] ?? null) !== null) {
                    continue;
                }
            } elseif ($category !== '' && ($item['category_id'] ?? null) !== $category) {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function normalizedTask(string $userId, array $input): array
    {
        $status = (string) ($input['status'] ?? 'todo');
        $priority = (string) ($input['priority'] ?? 'medium');
        if (!in_array($status, self::STATUSES, true) || !in_array($priority, self::PRIORITIES, true)) {
            throw new RuntimeException('Invalid task enum.');
        }

        return [
            'user_id' => $userId,
            'category_id' => (($input['category_id'] ?? null) === '' || ($input['category_id'] ?? null) === null)
                ? null
                : (string) $input['category_id'],
            'title' => (string) ($input['title'] ?? ''),
            'description' => (string) ($input['description'] ?? ''),
            'status' => $status,
            'priority' => $priority,
            'due_date' => (($input['due_date'] ?? null) === '' || ($input['due_date'] ?? null) === null)
                ? null
                : (string) $input['due_date'],
        ];
    }
}
