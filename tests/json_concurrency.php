<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\JsonStore;

$store = new JsonStore(__DIR__ . '/../storage/tmp/concurrency.json');
$store->transaction(static function (array &$document): void {
    $document['items'][0]['count']++;
    usleep(5000);
});
