<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Repositories\TaskRepository;

$repository = new TaskRepository($argv[1]);
$repository->create($argv[2], [
    'category_id' => null,
    'title' => $argv[3],
    'description' => '',
    'status' => 'todo',
    'priority' => 'medium',
    'due_date' => null,
]);
