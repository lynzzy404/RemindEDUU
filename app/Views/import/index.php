<?php

declare(strict_types=1);

$result = is_array($data['result'] ?? null) ? $data['result'] : null;
?>
<section class="page-header">
    <div>
        <p class="eyebrow">DATA</p>
        <h1 class="page-title">Import tugas</h1>
        <p class="page-lead">Unggah XLS/XLSX sesuai template. Maksimal 2 MB dan 1000 baris data.</p>
    </div>
    <div class="task-header__actions">
        <a class="btn" href="/import/template">Unduh template</a>
        <a class="btn" href="/export/xlsx">Export XLSX</a>
        <a class="btn" href="/export/pdf">Export PDF</a>
    </div>
</section>
<?php if (isset($data['error'])): ?><div class="import-result import-result--error" role="alert"><?= e((string) $data['error']) ?></div><?php endif; ?>
<?php if ($result !== null): ?>
    <div class="import-result import-result--success" role="status">
        <strong>Import selesai</strong>
        <span>Diimpor: <?= (int) ($result['imported'] ?? 0) ?> · Dilewati: <?= (int) ($result['skipped'] ?? 0) ?> · Kategori baru: <?= (int) ($result['categories'] ?? 0) ?></span>
        <?php if (!empty($result['errors'])): ?><ul><?php foreach ($result['errors'] as $error): ?><li>Baris <?= (int) ($error['row'] ?? 0) ?>: <?= e((string) ($error['message'] ?? '')) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
<?php endif; ?>
<form method="POST" action="/import" enctype="multipart/form-data" class="card import-form" data-import-form>
    <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
    <label for="import-file">File XLS/XLSX</label>
    <input class="input" id="import-file" name="file" type="file" accept=".xls,.xlsx" required>
    <p>Header harus persis: Judul, Deskripsi, Kategori, Prioritas, Status, Tenggat.</p>
    <button class="btn btn--primary" type="submit">Import tugas</button>
</form>
