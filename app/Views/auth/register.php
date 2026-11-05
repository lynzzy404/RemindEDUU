<?php

declare(strict_types=1);

$errors = $data['errors'] ?? [];
$old = $data['old'] ?? [];
?>
<section class="auth-page">
    <div class="auth-card card">
        <h1>Daftar Akun</h1>
        <p class="auth-card__lead">Buat akun baru untuk mulai mengelola tugas.</p>

        <form method="POST" action="/register" class="auth-form">
            <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">

            <div class="form-group">
                <label for="name">Nama</label>
                <input
                    class="input"
                    type="text"
                    id="name"
                    name="name"
                    value="<?= e($old['name'] ?? '') ?>"
                    autocomplete="name"
                    required
                >
                <?php if (isset($errors['name'])): ?>
                    <span class="field-error" role="alert"><?= e($errors['name']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input
                    class="input"
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e($old['email'] ?? '') ?>"
                    autocomplete="email"
                    required
                >
                <?php if (isset($errors['email'])): ?>
                    <span class="field-error" role="alert"><?= e($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    class="input"
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    required
                >
                <small class="form-hint">Minimal 8 karakter.</small>
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error" role="alert"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="password_confirm">Konfirmasi Password</label>
                <input
                    class="input"
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    autocomplete="new-password"
                    required
                >
                <?php if (isset($errors['password_confirm'])): ?>
                    <span class="field-error" role="alert"><?= e($errors['password_confirm']) ?></span>
                <?php endif; ?>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="flash flash--error"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <button type="submit" class="btn btn--primary auth-submit">Daftar</button>
        </form>

        <p class="auth-card__footer">
            Sudah punya akun? <a href="/login">Masuk di sini</a>
        </p>
    </div>
</section>
