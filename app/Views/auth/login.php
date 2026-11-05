<?php

declare(strict_types=1);

$errors = $data['errors'] ?? [];
$old = $data['old'] ?? [];
?>
<section class="auth-page">
    <div class="auth-card card">
        <h1>Masuk</h1>
        <p class="auth-card__lead">Masukkan email dan password untuk masuk ke akun kamu.</p>

        <form method="POST" action="/login" class="auth-form">
            <input type="hidden" name="_csrf" value="<?= e($data['csrf_token'] ?? '') ?>">
            <?php if (($data['next'] ?? null) !== null): ?>
                <input type="hidden" name="next" value="<?= e($data['next']) ?>">
            <?php endif; ?>

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
                    autocomplete="current-password"
                    required
                >
                <?php if (isset($errors['password'])): ?>
                    <span class="field-error" role="alert"><?= e($errors['password']) ?></span>
                <?php endif; ?>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="flash flash--error"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <button type="submit" class="btn btn--primary auth-submit">Masuk</button>
        </form>

        <p class="auth-card__footer">
            Belum punya akun? <a href="/register">Daftar di sini</a>
        </p>
    </div>
</section>
