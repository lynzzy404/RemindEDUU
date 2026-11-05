#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Core\JsonStore;

$base = 'http://127.0.0.1:8099';

function request(string $method, string $path, array $fields, string $jar): array
{
    global $base;
    $handle = curl_init($base . $path);
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_POSTFIELDS => $method === 'POST' ? http_build_query($fields) : null,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException(curl_error($handle));
    }
    $result = ['status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
    curl_close($handle);
    return $result;
}

function token(array $response): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $response['body'], $match);
    return $match[1] ?? '';
}

function check(string $label, bool $condition): void
{
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
}

function login(string $email, string $password, string $jar): void
{
    $page = request('GET', '/login', [], $jar);
    $result = request('POST', '/login', [
        '_csrf' => token($page),
        'email' => $email,
        'password' => $password,
    ], $jar);
    if ($result['status'] !== 302) {
        throw new RuntimeException('Login failed for ' . $email);
    }
}

function registerUser(string $email, string $jar): void
{
    $page = request('GET', '/register', [], $jar);
    $result = request('POST', '/register', [
        '_csrf' => token($page),
        'name' => 'M2 Test User',
        'email' => $email,
        'password' => 'rahasia123',
        'password_confirm' => 'rahasia123',
    ], $jar);
    if ($result['status'] !== 302) {
        throw new RuntimeException('Registration failed for ' . $email);
    }
}

$userJar = tempnam(sys_get_temp_dir(), 'm2-user-');
$otherJar = tempnam(sys_get_temp_dir(), 'm2-other-');
if ($userJar === false || $otherJar === false) {
    throw new RuntimeException('Cannot create cookie jars.');
}
register_shutdown_function(static function () use ($userJar, $otherJar): void {
    @unlink($userJar);
    @unlink($otherJar);
});

registerUser('m2user-' . bin2hex(random_bytes(3)) . '@example.com', $userJar);
$users = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/users.json'), true, 512, JSON_THROW_ON_ERROR);
$user = end($users['items']);
$userEmail = $user['email'];
login($userEmail, 'rahasia123', $userJar);
$categoriesPage = request('GET', '/categories', [], $userJar);
$csrf = token($categoriesPage);
$categories = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/categories.json'), true, 512, JSON_THROW_ON_ERROR);
$owned = array_values(array_filter($categories['items'], static fn (array $item): bool => $item['user_id'] === $user['id']));
$categoryId = $owned[0]['id'];

$created = request('POST', '/categories', ['_csrf' => $csrf, 'name' => 'XSS <b>kategori</b>', 'color' => '#B794F6'], $userJar);
check('1 create category succeeds', $created['status'] === 302);
$list = request('GET', '/categories', [], $userJar);
check('2 category name is escaped in list and dropdown', str_contains($list['body'], 'XSS &lt;b&gt;kategori&lt;/b&gt;') && !str_contains($list['body'], 'XSS <b>kategori</b>'));

$duplicate = request('POST', '/categories', ['_csrf' => token($list), 'name' => 'xss <B>KATEGORI</B>', 'color' => '#FFE14D'], $userJar);
check('3 duplicate category name rejected case-insensitively', $duplicate['status'] === 422 && str_contains($duplicate['body'], 'Nama kategori sudah digunakan'));
$invalidColor = request('POST', '/categories', ['_csrf' => token($list), 'name' => 'Invalid color', 'color' => '#000000'], $userJar);
check('4 invalid color rejected server-side', $invalidColor['status'] === 422);
$withoutCsrf = request('POST', '/categories', ['name' => 'No CSRF', 'color' => '#FFE14D'], $userJar);
check('5 category POST without CSRF returns 403', $withoutCsrf['status'] === 403);

registerUser('m2other-' . bin2hex(random_bytes(3)) . '@example.com', $otherJar);
$otherUsers = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/users.json'), true, 512, JSON_THROW_ON_ERROR);
$otherUser = end($otherUsers['items']);
login($otherUser['email'], 'rahasia123', $otherJar);
$otherPage = request('GET', '/categories', [], $otherJar);
$forbiddenUpdate = request('POST', '/categories/' . $categoryId . '/update', ['_csrf' => token($otherPage), 'name' => 'Hijack', 'color' => '#FFE14D'], $otherJar);
check('6 editing another user category returns 404', $forbiddenUpdate['status'] === 404);

$taskStore = new JsonStore(__DIR__ . '/../storage/data/tasks.json');
$taskId = JsonStore::generateId('tsk');
$taskStore->transaction(static function (array &$document) use ($taskId, $user, $categoryId): void {
    $document['items'][] = [
        'id' => $taskId,
        'user_id' => $user['id'],
        'category_id' => $categoryId,
        'title' => 'Temporary M2 task',
        'description' => '',
        'due_at' => null,
        'completed_at' => null,
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];
});
$deletePage = request('GET', '/categories', [], $userJar);
$delete = request('POST', '/categories/' . $categoryId . '/delete', ['_csrf' => token($deletePage)], $userJar);
$tasks = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/tasks.json'), true, 512, JSON_THROW_ON_ERROR);
$task = array_values(array_filter($tasks['items'], static fn (array $item): bool => $item['id'] === $taskId))[0] ?? [];
check('7 deleting category succeeds and clears task category_id', $delete['status'] === 302 && array_key_exists('category_id', $task) && $task['category_id'] === null);
$taskStore->transaction(static function (array &$document) use ($taskId): void {
    $document['items'] = array_values(array_filter($document['items'], static fn (array $item): bool => $item['id'] !== $taskId));
});

$tasksPage = request('GET', '/tasks', [], $userJar);
check('8 dashboard layout renders summary, filter, empty state and CTA', str_contains($tasksPage['body'], 'Ringkasan') && str_contains($tasksPage['body'], 'Filter tugas') && str_contains($tasksPage['body'], 'Belum ada tugas'));
check('9 dropdown markup includes listbox and noscript fallback', str_contains($tasksPage['body'], 'role="listbox"') && str_contains($tasksPage['body'], '<noscript>'));
$js = (string) file_get_contents(__DIR__ . '/../public/assets/js/dropdown.js');
check('10 dropdown JS has keyboard handling, textContent and no innerHTML', str_contains($js, 'ArrowDown') && str_contains($js, 'Home') && str_contains($js, 'textContent') && !str_contains($js, 'innerHTML'));
