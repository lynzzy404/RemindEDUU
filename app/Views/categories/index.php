<?php

declare(strict_types=1);

require_once __DIR__ . '/../partials/category_dropdown.php';

$errors = $data['errors'] ?? [];
$old = $data['old'] ?? [];
$categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
$taskCounts = is_array($data['task_counts'] ?? null) ? $data['task_counts'] : [];
$colors = is_array($data['colors'] ?? null) ? $data['colors'] : [];
$colorNames = [
    '#FFE14D' => 'Kuning',
    '#FF6B9D' => 'Pink',
    '#4D9DFF' => 'Biru',
    '#5EEAA0' => 'Hijau',
    '#FF9F43' => 'Oranye',
    '#B794F6' => 'Ungu',
];
?>
<section class="page-header">
    <div>
        <p class="eyebrow">PERSONALISASI</p>
        <h1 class="page-title">Kategori</h1>
        <p class="page-lead">Atur warna untuk membantu tugasmu mudah dipindai.</p>
    </div>
</section>

<section class="category-layout" aria-label="Kelola kategori">
    <div class="card category-form-card">
        <h2>Tambah kategori</h2>
        <form method="POST" action="/categories" class="auth-form">
            <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
            <div class="form-group">
                <label for="category-name">Nama</label>
                <input class="input" id="category-name" name="name" value="<?= e($old['name'] ?? '') ?>" maxlength="30" required>
                <?php if (isset($errors['name'])): ?><span class="field-error" role="alert"><?= e($errors['name']) ?></span><?php endif; ?>
            </div>
            <fieldset class="color-picker">
                <legend>Warna</legend>
                <div class="color-picker__options">
                    <?php foreach ($colors as $color): ?>
                        <label class="color-swatch" data-color="<?= e($color) ?>">
                            <input type="radio" name="color" value="<?= e($color) ?>" <?= ($old['color'] ?? $colors[0] ?? '') === $color ? 'checked' : '' ?>>
                            <span class="category-chip" data-color="<?= e($color) ?>"></span>
                            <span><?= e($colorNames[$color] ?? 'Warna') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['color'])): ?><span class="field-error" role="alert"><?= e($errors['color']) ?></span><?php endif; ?>
            </fieldset>
            <button type="submit" class="btn btn--primary">Tambah</button>
        </form>
    </div>

    <div class="category-list">
        <div class="section-heading">
            <h2>Kategori kamu</h2>
            <span class="badge"><?= count($categories) ?>/50</span>
        </div>
        <?php if ($categories === []): ?>
            <div class="card empty-state">
                <h3>Belum ada kategori</h3>
                <p>Tambahkan kategori pertama untuk mengelompokkan tugas.</p>
            </div>
        <?php else: ?>
            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <?php $categoryId = (string) ($category['id'] ?? ''); ?>
                    <article class="card category-card">
                        <div class="category-card__title">
                            <span class="category-chip" data-color="<?= e((string) ($category['color'] ?? '')) ?>"></span>
                            <h3><?= e((string) ($category['name'] ?? '')) ?></h3>
                        </div>
                        <p><?= (int) ($taskCounts[$categoryId] ?? 0) ?> tugas</p>
                        <details class="category-card__edit">
                            <summary class="btn btn--small">Edit</summary>
                            <form method="POST" action="/categories/<?= e($categoryId) ?>/update" class="auth-form">
                                <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
                                <label for="category-<?= e($categoryId) ?>">Nama</label>
                                <input class="input" id="category-<?= e($categoryId) ?>" name="name" value="<?= e((string) ($category['name'] ?? '')) ?>" maxlength="30" required>
                                <label for="color-<?= e($categoryId) ?>">Warna</label>
                                <select class="input" id="color-<?= e($categoryId) ?>" name="color">
                                    <?php foreach ($colors as $color): ?>
                                        <option value="<?= e($color) ?>" <?= ($category['color'] ?? '') === $color ? 'selected' : '' ?>><?= e($colorNames[$color] ?? 'Warna') ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn--secondary">Simpan</button>
                            </form>
                        </details>
                        <button type="button" class="btn btn--danger" data-delete-dialog="delete-<?= e($categoryId) ?>">Hapus</button>
                        <dialog id="delete-<?= e($categoryId) ?>" class="confirm-dialog">
                            <form method="POST" action="/categories/<?= e($categoryId) ?>/delete">
                                <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
                                <h2>Hapus kategori?</h2>
                                <p>Tugas tetap aman, tetapi akan menjadi tanpa kategori.</p>
                                <button type="submit" class="btn btn--danger">Ya, hapus</button>
                                <button type="button" class="btn" data-close-dialog>Batal</button>
                            </form>
                        </dialog>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
