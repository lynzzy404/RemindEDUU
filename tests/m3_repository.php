#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\JsonStore;
use App\Repositories\TaskRepository;

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'remindedu-m3-' . bin2hex(random_bytes(4));
mkdir($directory, 0700, true);
file_put_contents($directory . '/tasks.json', '{"schema_version":1,"items":[]}');
register_shutdown_function(static function () use ($directory): void {
    foreach (glob($directory . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($directory);
});

function assertTrue(string $label, bool $condition): void
{
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
    if (!$condition) {
        exit(1);
    }
}

$repository = new TaskRepository($directory);
$task = $repository->create('usr_a', [
    'category_id' => null,
    'title' => 'Laporan',
    'description' => 'Bab satu',
    'status' => 'todo',
    'priority' => 'high',
    'due_date' => '2026-11-10',
]);
$other = $repository->create('usr_b', [
    'category_id' => null,
    'title' => 'Rahasia',
    'description' => '',
    'status' => 'done',
    'priority' => 'low',
    'due_date' => null,
]);

assertTrue('created task has tsk id and owner', preg_match('/^tsk_[a-f0-9]{16}$/', $task['id']) === 1 && $task['user_id'] === 'usr_a');
assertTrue('completed_at is set for done task', is_string($other['completed_at']));
assertTrue('pagination is isolated by user', $repository->paginateByUser('usr_a', [], 1, 10)['total'] === 1);
assertTrue('search matches title and description', $repository->paginateByUser('usr_a', ['q' => 'bab'], 1, 10)['total'] === 1);
assertTrue('invalid sort falls back without error', $repository->paginateByUser('usr_a', ['sort' => ';DROP', 'direction' => 'nope'], -1, 99999)['per_page'] === 100);
assertTrue('findOwned refuses another user', $repository->findOwned($task['id'], 'usr_b') === null);

$toggled = $repository->toggle($task['id'], 'usr_a');
assertTrue('toggle marks task done with completed_at', $toggled['status'] === 'done' && is_string($toggled['completed_at']));
$untoggled = $repository->toggle($task['id'], 'usr_a');
assertTrue('toggle clears completed_at when reopened', $untoggled['status'] === 'todo' && $untoggled['completed_at'] === null);

$repository->delete($task['id'], 'usr_a');
assertTrue('delete removes only owned task', $repository->findOwned($task['id'], 'usr_a') === null && $repository->findOwned($other['id'], 'usr_b') !== null);
