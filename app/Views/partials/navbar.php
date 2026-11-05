<?php

declare(strict_types=1);

use App\Core\Csrf;

$navbarUserName = null;
if (is_string($_SESSION['user_id'] ?? null) && $_SESSION['user_id'] !== '') {
    $navbarRepository = new \App\Repositories\UserRepository(__DIR__ . '/../../../storage/data');
    $navbarUser = $navbarRepository->findById($_SESSION['user_id']);
    $navbarUserName = is_array($navbarUser) ? (string) ($navbarUser['name'] ?? '') : null;
    unset($navbarRepository, $navbarUser);
}
?>
<header class="navbar">
    <div class="navbar__inner container">
        <a class="navbar__brand" href="/">RemindEDU</a>

        <?php if ($navbarUserName !== null): ?>
            <nav class="navbar__links" aria-label="Navigasi utama">
                <a href="/tasks">Tugas</a>
                <a href="/categories">Kategori</a>
                <a href="/import">Import</a>
                <a href="/export/xlsx">Export</a>
            </nav>
            <div class="navbar__user">
                <span class="navbar__username"><?= e($navbarUserName) ?></span>
                <form method="POST" action="/logout" class="navbar__logout-form">
                    <?= (new Csrf())->field() ?>
                    <button type="submit" class="btn btn--logout">Logout</button>
                </form>
            </div>
        <?php else: ?>
            <nav class="navbar__links" aria-label="Navigasi utama">
                <a href="/login">Masuk</a>
                <a href="/register">Daftar</a>
            </nav>
        <?php endif; ?>
    </div>
</header>
