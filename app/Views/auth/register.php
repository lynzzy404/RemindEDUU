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
                <div class="password-input-wrapper">
                    <input
                        class="input"
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                    >
                    <button
                        type="button"
                        class="password-toggle-btn"
                        aria-label="Tampilkan password"
                        aria-controls="password password_confirm"
                        data-target="password password_confirm"
                        aria-pressed="false"
                        data-password-toggle
                        hidden
                    >
                        <svg class="password-toggle-icon password-toggle-icon--eye" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <svg class="password-toggle-icon password-toggle-icon--eye-off" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>
                </div>
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
