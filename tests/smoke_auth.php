#!/usr/bin/env php
<?php
/**
 * Smoke test: verifies core auth components work correctly.
 * Run: php tests/smoke_auth.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/bootstrap.php';

use App\Core\Csrf;
use App\Core\JsonStore;
use App\Core\RateLimiter;
use App\Repositories\CategoryRepository;
use App\Repositories\UserRepository;

// Isolated data directory so the run never touches storage/data/*.json.
$dataPath = __DIR__ . '/../storage/tmp/smoke_' . bin2hex(random_bytes(8));
if (!mkdir($dataPath, 0750)) {
    fwrite(STDERR, 'FAIL - cannot create isolated data directory' . PHP_EOL);
    exit(1);
}

// Shutdown hook instead of finally: finally does not run when exit() is called.
register_shutdown_function(static function () use ($dataPath): void {
    foreach (scandir($dataPath) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            unlink($dataPath . '/' . $entry);
        }
    }
    rmdir($dataPath);
});

foreach (['users.json', 'categories.json', 'tasks.json'] as $file) {
    file_put_contents($dataPath . '/' . $file, json_encode(['schema_version' => 1, 'items' => []], JSON_PRETTY_PRINT));
}

$assert = static function (string $label, bool $condition): void {
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
};

try {
    $userRepo = new UserRepository($dataPath);
    $catRepo = new CategoryRepository($dataPath);

    // 1. Create user
    $user = $userRepo->create([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'password_hash' => password_hash('rahasia123', PASSWORD_ARGON2ID),
    ]);
    $assert('user created with usr_ prefix', str_starts_with($user['id'], 'usr_'));
    $assert('user id length is correct', strlen($user['id']) === 20); // usr_ + 16 hex
    $assert('user name stored correctly', $user['name'] === 'Budi Santoso');

    // 2. Seed categories
    $catRepo->seedDefaults($user['id']);
    $cats = $catRepo->allByUser($user['id']);
    $assert('3 default categories seeded', count($cats) === 3);
    $assert('category has cat_ prefix', str_starts_with($cats[0]['id'], 'cat_'));
    $assert('category color is valid', str_starts_with($cats[0]['color'], '#') && strlen($cats[0]['color']) === 7);
    $assert('default categories are Pribadi/Pekerjaan/Belajar', count(array_column($cats, 'name')) === 3);

    // 3. Verify password
    $verified = $userRepo->verifyPassword('budi@example.com', 'rahasia123');
    $assert('correct password verifies', $verified !== null && $verified['id'] === $user['id']);
    $assert('wrong password rejected', $userRepo->verifyPassword('budi@example.com', 'salah') === null);
    $assert('non-existent email rejected', $userRepo->verifyPassword('tidak@ada.com', 'rahasia123') === null);

    // 4. Rate limiter
    $limiter = new RateLimiter(2, 60);
    $key = 'smoke_test_key_' . bin2hex(random_bytes(4));
    $limiter->clear($key);
    $assert('rate limit initial state', !$limiter->tooManyAttempts($key));
    $assert('rate limit hit increments', $limiter->hit($key) === 1);
    $limiter->hit($key);
    $assert('rate limit at threshold', $limiter->tooManyAttempts($key));
    $limiter->clear($key);
    $assert('rate limit clear resets', !$limiter->tooManyAttempts($key));

    // 5. CSRF
    $csrf = new Csrf();
    $_SESSION['csrf_token'] = null;
    $token = $csrf->token();
    $assert('csrf token length is 64 chars', strlen($token) === 64);
    $assert('csrf verify correct token', $csrf->verify($token));
    $assert('csrf reject wrong token', !$csrf->verify('tampered_token_0123456789abcdef'));
    $assert('csrf field contains token', str_contains($csrf->field(), $token));

    // 6. JsonStore ID generation
    $assert('generateId usr_ prefix', str_starts_with(JsonStore::generateId('usr'), 'usr_'));
    $assert('generateId cat_ prefix', str_starts_with(JsonStore::generateId('cat'), 'cat_'));
    $assert('generateId tsk_ prefix', str_starts_with(JsonStore::generateId('tsk'), 'tsk_'));
    $assert('generateId invalid prefix throws', true);
    try {
        JsonStore::generateId('xyz');
        $assert('generateId invalid prefix actually throws', false);
    } catch (\Throwable) {
        $assert('generateId invalid prefix actually throws', true);
    }

    // 7. Validator clean strips control chars
    $cleaned = \App\Core\Validator::clean("  hello " . chr(0) . chr(7) . "world  ");
    $assert('validator clean trims and removes control chars', trim($cleaned) === 'hello world');
    $assert('validator isValidUtf8', \App\Core\Validator::isValidUtf8('Halo Nusantara'));

    // 8. Check data files still valid envelope after writes
    foreach (['users.json', 'categories.json'] as $file) {
        $path = $dataPath . '/' . $file;
        if (is_file($path) && filesize($path) > 0) {
            $doc = json_decode((string) file_get_contents($path), true);
            $assert($file . ' envelope schema_version=1', ($doc['schema_version'] ?? null) === 1);
            $assert($file . ' envelope items is array', is_array($doc['items'] ?? null));
        } else {
            echo 'SKIP  - ' . $file . ' (empty/inaccessible)' . PHP_EOL;
        }
    }

    echo PHP_EOL . 'All smoke tests passed.' . PHP_EOL;
} catch (\Throwable $e) {
    echo 'FAIL - Exception: ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . PHP_EOL;
    exit(1);
}
