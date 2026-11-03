<?php

declare(strict_types=1);

$title = is_string($data['title'] ?? null) ? $data['title'] : 'RemindEDU';
$content = is_string($data['content'] ?? null) ? $data['content'] : '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="RemindEDU, manajemen tugas sederhana.">
    <title><?= e($title) ?> — RemindEDU</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/navbar.php'; ?>
    <main class="container">
        <?php require __DIR__ . '/../partials/flash.php'; ?>
        <?= $content ?>
    </main>
</body>
</html>
