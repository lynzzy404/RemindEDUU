<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\JsonStore;

$store = new JsonStore(__DIR__ . '/../storage/tmp/concurrency.json');
$document = $store->read();
echo (string) ($document['items'][0]['count'] ?? -1) . PHP_EOL;
