<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Auth;
use App\Repositories\UserRepository;

$sidebarUserName = '';
if (isset($_SESSION['user_name']) && is_string($_SESSION['user_name']) && $_SESSION['user_name'] !== '') {
    $sidebarUserName = $_SESSION['user_name'];
}

$sidebarUserId = Auth::id();
if ($sidebarUserName === '' && is_string($sidebarUserId) && $sidebarUserId !== '') {
    try {
        $dataPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'data';
        $sidebarRepository = new UserRepository($dataPath);
        $sidebarUser = $sidebarRepository->findById($sidebarUserId);
        if (is_array($sidebarUser) && !empty($sidebarUser['name'])) {
            $sidebarUserName = (string) $sidebarUser['name'];
            $_SESSION['user_name'] = $sidebarUserName;
        }
    } catch (\Throwable) {
        // Fallback gracefully jika file storage sedang terkunci atau gagal dibaca
    }
}

if ($sidebarUserName === '' && isset($data['user']['name']) && is_string($data['user']['name']) && $data['user']['name'] !== '') {
    $sidebarUserName = $data['user']['name'];
}

if ($sidebarUserName === '' && is_string($sidebarUserId) && $sidebarUserId !== '') {
    $sidebarUserName = 'Pengguna';
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
