<?php

declare(strict_types=1);

$path = __DIR__ . '/../storage/tmp/concurrency.json';
$document = [
    'schema_version' => 1,
    'items' => [['count' => 0]],
];

file_put_contents(
    $path,
    json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
    LOCK_EX
);
