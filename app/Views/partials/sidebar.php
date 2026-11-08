<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Auth;
use App\Repositories\UserRepository;

$sidebarUserName = '';
$sidebarUserId = Auth::id();
if (is_string($sidebarUserId) && $sidebarUserId !== '') {
    $sidebarRepository = new UserRepository(__DIR__ . '/../../../storage/data');
    $sidebarUser = $sidebarRepository->findById($sidebarUserId);
    $sidebarUserName = is_array($sidebarUser) ? (string) ($sidebarUser['name'] ?? '') : '';
}

$sidebarPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$sidebarPath = is_string($sidebarPath) && $sidebarPath !== '' ? $sidebarPath : '/';
$sidebarItems = [
    ['/tasks', 'Daftar Tugas'],
    ['/categories', 'Kategori'],
    ['/import', 'Import'],
    ['/export/xlsx', 'Export Excel'],
    ['/export/pdf', 'Export PDF'],
];
?>
<aside class="sidebar" id="app-sidebar" data-sidebar-drawer aria-label="Navigasi utama">
    <div class="sidebar__header">
        <a class="sidebar__brand" href="/tasks">RemindEDU</a>
        <button class="sidebar__close" type="button" aria-label="Tutup menu" data-sidebar-close>X</button>
    </div>
    <nav class="sidebar__nav" aria-label="Menu aplikasi">
        <?php foreach ($sidebarItems as [$path, $label]): ?>
            <?php $isActive = $sidebarPath === $path; ?>
            <a
                class="sidebar__link<?= $isActive ? ' is-active' : '' ?>"
                href="<?= e($path) ?>"
                data-sidebar-link
                <?= $isActive ? 'aria-current="page"' : '' ?>
            ><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar__footer">
        <span class="sidebar__username" title="<?= e($sidebarUserName) ?>"><?= e($sidebarUserName) ?></span>
        <form method="POST" action="/logout" class="sidebar__logout-form">
            <?= (new Csrf())->field() ?>
            <button type="submit" class="btn btn--danger btn--logout">Keluar</button>
        </form>
    </div>
</aside>
