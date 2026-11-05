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
    <script src="/assets/js/dropdown.js" defer></script>
</head>
<body>
    <?php require __DIR__ . '/../partials/navbar.php'; ?>
    <main class="container">
        <?php
        /** @var \App\Core\Session|null $flashSession */
        $flashSession = $data['session'] ?? null;
        if ($flashSession instanceof \App\Core\Session) {
            $flashSuccess = $flashSession->pullFlash('success');
            if (is_string($flashSuccess) && $flashSuccess !== '') {
                echo '<div class="flash flash--success" role="status">' . e($flashSuccess) . '</div>';
            }
            $flashError = $flashSession->pullFlash('error');
            if (is_string($flashError) && $flashError !== '') {
                echo '<div class="flash flash--error" role="alert">' . e($flashError) . '</div>';
            }
            $flashInfo = $flashSession->pullFlash('info');
            if (is_string($flashInfo) && $flashInfo !== '') {
                echo '<div class="flash flash--info" role="status">' . e($flashInfo) . '</div>';
            }
        }
        ?>
        <?= $content ?>
    </main>
</body>
</html>
