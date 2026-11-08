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
    <script src="/assets/js/sidebar.js" defer></script>
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
    <?php
    $isErrorPage = str_contains($content, 'error-page');
    $hasAuthenticatedUser = is_string($_SESSION['user_id'] ?? null) && $_SESSION['user_id'] !== '';
    $showSidebar = $hasAuthenticatedUser && !$isErrorPage;
    ?>
    <?php if ($showSidebar): ?>
        <div class="app-shell" data-sidebar-shell>
            <?php require __DIR__ . '/../partials/sidebar.php'; ?>
            <div class="sidebar-overlay" data-sidebar-overlay hidden></div>
            <div class="app-main">
                <header class="mobile-topbar">
                    <a class="mobile-topbar__brand" href="/tasks">RemindEDU</a>
                    <button
                        class="mobile-topbar__toggle"
                        type="button"
                        aria-label="Buka menu navigasi"
                        aria-expanded="false"
                        aria-controls="app-sidebar"
                        data-sidebar-toggle
                    >Menu</button>
                </header>
                <main class="container">
    <?php else: ?>
        <?php require __DIR__ . '/../partials/navbar.php'; ?>
        <main class="container">
    <?php endif; ?>
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
    <?php if ($showSidebar): ?>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
