<?php

declare(strict_types=1);

require_once __DIR__ . '/../partials/category_dropdown.php';

$categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
$categoryMap = is_array($data['category_map'] ?? null) ? $data['category_map'] : [];
$tasks = is_array($data['tasks'] ?? null) ? $data['tasks'] : [];
$filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
$summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
$pagination = is_array($data['pagination'] ?? null) ? $data['pagination'] : [];
$queryUrl = static function (array $changes = []) use ($filters, $pagination): string {
    $query = array_merge($filters, [
        'page' => $pagination['page'] ?? 1,
        'per_page' => $pagination['per_page'] ?? 10,
    ], $changes);
    $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

    return '/tasks?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
};
$statusLabels = ['todo' => 'Belum', 'in_progress' => 'Proses', 'done' => 'Selesai'];
$priorityLabels = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];
$today = gmdate('Y-m-d');
?>
<section class="dashboard-header">
    <div>
        <p class="eyebrow">DASHBOARD</p>
        <h1 class="page-title">Tugas kamu</h1>
        <p class="page-lead">Satu tempat untuk semua hal yang perlu kamu selesaikan.</p>
    </div>
    <div class="task-header__actions">
        <a class="btn" href="/export/pdf<?= e($queryUrl() === '/tasks?' ? '' : '?' . (parse_url($queryUrl(), PHP_URL_QUERY) ?? '')) ?>">Export PDF</a>
        <a class="btn" href="/export/xlsx<?= e($queryUrl() === '/tasks?' ? '' : '?' . (parse_url($queryUrl(), PHP_URL_QUERY) ?? '')) ?>">Export XLSX</a>
        <a class="btn btn--primary" href="/tasks/create">Tambah tugas</a>
    </div>
</section>

<section class="summary-grid" aria-label="Ringkasan tugas">
    <article class="card summary-card"><span>Total</span><strong><?= (int) ($summary['total'] ?? 0) ?></strong><small>semua tugas</small></article>
    <article class="card summary-card summary-card--green"><span>Selesai</span><strong><?= (int) ($summary['completed'] ?? 0) ?></strong><small>sudah beres</small></article>
    <article class="card summary-card summary-card--pink"><span>Terlambat</span><strong><?= (int) ($summary['overdue'] ?? 0) ?></strong><small>perlu perhatian</small></article>
</section>

<section class="filter-bar card" aria-label="Filter tugas">
    <form method="GET" action="/tasks" class="filter-bar__form">
        <div class="form-group">
            <label for="task-search">Cari tugas</label>
            <input class="input" id="task-search" name="q" type="search" value="<?= e((string) ($filters['q'] ?? '')) ?>" placeholder="Cari judul atau deskripsi...">
        </div>
        <?php category_dropdown($categories, 'category', (string) ($filters['category'] ?? '')); ?>
        <div class="form-group">
            <label for="task-status">Status</label>
            <select class="input" id="task-status" name="status">
                <option value="">Semua</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="task-priority">Prioritas</label>
            <select class="input" id="task-priority" name="priority">
                <option value="">Semua</option>
                <?php foreach ($priorityLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['priority'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="task-sort">Urutkan</label>
            <select class="input" id="task-sort" name="sort">
                <?php foreach (['created_at' => 'Terbaru', 'due_date' => 'Tenggat', 'priority' => 'Prioritas', 'title' => 'Judul'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= ($filters['sort'] ?? 'created_at') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <select class="input filter-direction" name="direction" aria-label="Arah urutan">
            <option value="desc" <?= ($filters['direction'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>↓</option>
            <option value="asc" <?= ($filters['direction'] ?? '') === 'asc' ? 'selected' : '' ?>>↑</option>
        </select>
        <button type="submit" class="btn btn--secondary">Terapkan</button>
        <a class="btn" href="/tasks">Reset</a>
    </form>
</section>

<?php if ($tasks === []): ?>
    <section class="empty-state card" aria-label="Daftar tugas kosong">
        <div class="empty-state__mark">0</div>
        <h2>Belum ada tugas</h2>
        <p>Mulai dari satu langkah kecil hari ini.</p>
        <a class="btn btn--primary" href="/tasks/create">Tambah tugas pertama</a>
    </section>
<?php else: ?>
    <section class="task-grid" aria-label="Daftar tugas">
        <?php foreach ($tasks as $task): ?>
            <?php
            $taskId = (string) ($task['id'] ?? '');
            $categoryId = (string) ($task['category_id'] ?? '');
            $category = $categoryMap[$categoryId] ?? null;
            $isOverdue = ($task['status'] ?? '') !== 'done'
                && is_string($task['due_date'] ?? null)
                && $task['due_date'] < $today;
            ?>
            <article class="card task-card <?= ($task['status'] ?? '') === 'done' ? 'task-card--done' : '' ?>">
                <span class="task-card__strip" data-color="<?= e((string) ($category['color'] ?? '')) ?>"></span>
                <div class="task-card__body">
                    <div class="task-card__topline">
                        <span class="badge badge--<?= e((string) ($task['status'] ?? 'todo')) ?>"><?= e($statusLabels[$task['status']] ?? 'Belum') ?></span>
                        <span class="badge badge--priority-<?= e((string) ($task['priority'] ?? 'medium')) ?>"><?= e($priorityLabels[$task['priority']] ?? 'Sedang') ?></span>
                    </div>
                    <h2><?= e((string) ($task['title'] ?? '')) ?></h2>
                    <?php if ((string) ($task['description'] ?? '') !== ''): ?>
                        <p class="task-card__description"><?= nl2br(e((string) $task['description'])) ?></p>
                    <?php endif; ?>
                    <div class="task-card__meta">
                        <span class="category-label"><span class="category-chip" data-color="<?= e((string) ($category['color'] ?? '')) ?>"></span><?= e((string) ($category['name'] ?? 'Tanpa Kategori')) ?></span>
                        <?php if (is_string($task['due_date'] ?? null) && $task['due_date'] !== ''): ?>
                            <span class="<?= $isOverdue ? 'task-card__overdue' : '' ?>">Tenggat: <?= e($task['due_date']) ?><?= $isOverdue ? ' · Terlambat' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="task-card__actions">
                        <a class="btn btn--small" href="/tasks/<?= e($taskId) ?>/edit">Edit</a>
                        <form method="POST" action="/tasks/<?= e($taskId) ?>/toggle">
                            <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
                            <button class="btn btn--small btn--secondary" type="submit"><?= ($task['status'] ?? '') === 'done' ? 'Buka kembali' : 'Selesaikan' ?></button>
                        </form>
                        <button class="btn btn--small btn--danger" type="button" data-delete-dialog="delete-task-<?= e($taskId) ?>">Hapus</button>
                    </div>
                </div>
                <dialog id="delete-task-<?= e($taskId) ?>" class="confirm-dialog">
                    <form method="POST" action="/tasks/<?= e($taskId) ?>/delete">
                        <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
                        <h2>Hapus tugas?</h2>
                        <p>Tugas ini akan dihapus permanen.</p>
                        <button type="submit" class="btn btn--danger">Ya, hapus</button>
                        <button type="button" class="btn" data-close-dialog>Batal</button>
                    </form>
                </dialog>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="pagination" aria-label="Pagination">
        <?php for ($page = 1; $page <= (int) $pagination['total_pages']; $page++): ?>
            <a class="btn btn--small <?= $page === (int) $pagination['page'] ? 'pagination__current' : '' ?>" href="<?= e($queryUrl(['page' => $page])) ?>"><?= $page ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
