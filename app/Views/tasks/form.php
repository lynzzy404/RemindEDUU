<?php

declare(strict_types=1);

require_once __DIR__ . '/../partials/category_dropdown.php';

$old = is_array($data['old'] ?? null) ? $data['old'] : [];
$errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
$categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
$statuses = ['todo' => 'Belum', 'in_progress' => 'Proses', 'done' => 'Selesai'];
$priorities = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];
?>
<section class="form-page">
    <div class="page-header">
        <div>
            <p class="eyebrow">TUGAS</p>
            <h1 class="page-title"><?= e((string) ($data['heading'] ?? 'Tugas')) ?></h1>
        </div>
        <a class="btn" href="/tasks">Kembali</a>
    </div>
    <form method="POST" action="<?= e((string) ($data['action'] ?? '/tasks')) ?>" class="card task-form">
        <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
        <div class="form-group">
            <label for="task-title">Judul</label>
            <input class="input" id="task-title" name="title" maxlength="120" value="<?= e((string) ($old['title'] ?? '')) ?>" required>
            <?php if (isset($errors['title'])): ?><span class="field-error" role="alert"><?= e($errors['title']) ?></span><?php endif; ?>
        </div>
        <div class="form-group">
            <label for="task-description">Deskripsi</label>
            <textarea class="input" id="task-description" name="description" maxlength="1000" rows="6"><?= e((string) ($old['description'] ?? '')) ?></textarea>
            <?php if (isset($errors['description'])): ?><span class="field-error" role="alert"><?= e($errors['description']) ?></span><?php endif; ?>
        </div>
        <div class="task-form__grid">
            <?php category_dropdown($categories, 'category_id', (string) ($old['category_id'] ?? '')); ?>
            <div class="form-group">
                <label for="task-status">Status</label>
                <select class="input" id="task-status" name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($old['status'] ?? 'todo') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['status'])): ?><span class="field-error" role="alert"><?= e($errors['status']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="task-priority">Prioritas</label>
                <select class="input" id="task-priority" name="priority">
                    <?php foreach ($priorities as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($old['priority'] ?? 'medium') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['priority'])): ?><span class="field-error" role="alert"><?= e($errors['priority']) ?></span><?php endif; ?>
            </div>
            <div class="form-group">
                <label for="task-due-date">Tenggat</label>
                <input class="input" id="task-due-date" name="due_date" type="date" value="<?= e((string) ($old['due_date'] ?? '')) ?>">
                <?php if (isset($errors['due_date'])): ?><span class="field-error" role="alert"><?= e($errors['due_date']) ?></span><?php endif; ?>
            </div>
        </div>
        <?php if (isset($errors['category_id'])): ?><span class="field-error" role="alert"><?= e($errors['category_id']) ?></span><?php endif; ?>
        <button type="submit" class="btn btn--primary"><?= e((string) ($data['submit_label'] ?? 'Simpan')) ?></button>
    </form>
</section>
