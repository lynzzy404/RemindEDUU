#!/usr/bin/env php
<?php

declare(strict_types=1);

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
    $response = ['status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
    curl_close($handle);

    return $response;
}

function token(array $response): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $response['body'], $match);

    return $match[1] ?? '';
}

function check(string $label, bool $condition): void
{
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
    if (!$condition) {
        exit(1);
    }
}

function registerUser(string $email, string $jar): void
{
    $page = request('GET', '/register', [], $jar);
    $response = request('POST', '/register', [
        '_csrf' => token($page),
        'name' => 'M3 Test User',
        'email' => $email,
        'password' => 'rahasia123',
        'password_confirm' => 'rahasia123',
    ], $jar);
    if ($response['status'] !== 302) {
        throw new RuntimeException('Registration failed.');
    }
    $login = request('GET', '/login', [], $jar);
    $response = request('POST', '/login', [
        '_csrf' => token($login),
        'email' => $email,
        'password' => 'rahasia123',
    ], $jar);
    if ($response['status'] !== 302) {
        throw new RuntimeException('Login failed.');
    }
}

function latestUser(string $email): array
{
    $users = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/users.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach (array_reverse($users['items']) as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }
    throw new RuntimeException('User not found.');
}

function findTask(string $id): array
{
    $tasks = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/tasks.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($tasks['items'] as $task) {
        if ($task['id'] === $id) {
            return $task;
        }
    }
    return [];
}

$aEmail = 'm3-a-' . bin2hex(random_bytes(3)) . '@example.com';
$bEmail = 'm3-b-' . bin2hex(random_bytes(3)) . '@example.com';
$aJar = tempnam(sys_get_temp_dir(), 'm3-a-');
$bJar = tempnam(sys_get_temp_dir(), 'm3-b-');
if ($aJar === false || $bJar === false) {
    throw new RuntimeException('Cannot create cookie jars.');
}
register_shutdown_function(static function () use ($aJar, $bJar): void {
    @unlink($aJar);
    @unlink($bJar);
});

registerUser($aEmail, $aJar);
$userA = latestUser($aEmail);
$aPage = request('GET', '/tasks/create', [], $aJar);
$aCategories = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/categories.json'), true, 512, JSON_THROW_ON_ERROR);
$aCategory = array_values(array_filter($aCategories['items'], static fn (array $category): bool => $category['user_id'] === $userA['id']))[0];
$csrf = token($aPage);
$title = 'XSS <img src=x onerror=alert(1)>';
$created = request('POST', '/tasks', [
    '_csrf' => $csrf,
    'user_id' => 'usr_forged',
    'category_id' => $aCategory['id'],
    'title' => $title,
    'description' => '<script>alert(1)</script> detail',
    'status' => 'in_progress',
    'priority' => 'high',
    'due_date' => '2026-11-10',
], $aJar);
check('1 create stores owned task with generated ID', $created['status'] === 302);
$aTasksPage = request('GET', '/tasks', [], $aJar);
$taskData = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/tasks.json'), true, 512, JSON_THROW_ON_ERROR);
$aTask = array_values(array_filter($taskData['items'], static fn (array $task): bool => $task['user_id'] === $userA['id']))[0];
$taskId = $aTask['id'];
check('1 task schema has owner, UTC timestamps and category', preg_match('/^tsk_[a-f0-9]{16}$/', $taskId) === 1 && $aTask['user_id'] === $userA['id'] && str_ends_with($aTask['created_at'], 'Z') && $aTask['category_id'] === $aCategory['id']);
check('11 XSS title and description are escaped', str_contains($aTasksPage['body'], '&lt;img src=x onerror=alert(1)&gt;') && str_contains($aTasksPage['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($aTasksPage['body'], '<script>alert(1)</script>'));

$emptyTitle = request('POST', '/tasks', ['_csrf' => token($aPage), 'title' => '', 'description' => '', 'status' => 'todo', 'priority' => 'medium'], $aJar);
check('2 empty title rejected inline', $emptyTitle['status'] === 422 && str_contains($emptyTitle['body'], 'wajib diisi'));
$longTitle = request('POST', '/tasks', ['_csrf' => token($aPage), 'title' => str_repeat('x', 121), 'description' => '', 'status' => 'todo', 'priority' => 'medium'], $aJar);
check('2 title over 120 rejected', $longTitle['status'] === 422);
$invalidDate = request('POST', '/tasks', ['_csrf' => token($aPage), 'title' => 'Bad date', 'description' => '', 'status' => 'todo', 'priority' => 'medium', 'due_date' => '2026-02-31'], $aJar);
check('3 invalid calendar date rejected', $invalidDate['status'] === 422);
$invalidEnum = request('POST', '/tasks', ['_csrf' => token($aPage), 'title' => 'Bad enum', 'description' => '', 'status' => 'hacked', 'priority' => 'urgent'], $aJar);
check('4 invalid status and priority rejected', $invalidEnum['status'] === 422);

registerUser($bEmail, $bJar);
$userB = latestUser($bEmail);
$bPage = request('GET', '/tasks/create', [], $bJar);
$foreignCategory = request('POST', '/tasks', ['_csrf' => token($bPage), 'title' => 'Foreign category', 'description' => '', 'status' => 'todo', 'priority' => 'low', 'category_id' => $aCategory['id']], $bJar);
check('5 category owned by another user rejected', $foreignCategory['status'] === 422 && str_contains($foreignCategory['body'], 'Kategori tidak valid'));
$foreignEdit = request('GET', '/tasks/' . $taskId . '/edit', [], $bJar);
$foreignUpdate = request('POST', '/tasks/' . $taskId . '/update', ['_csrf' => token($bPage), 'title' => 'Hijack', 'description' => '', 'status' => 'todo', 'priority' => 'low'], $bJar);
$foreignToggle = request('POST', '/tasks/' . $taskId . '/toggle', ['_csrf' => token($bPage)], $bJar);
$foreignDelete = request('POST', '/tasks/' . $taskId . '/delete', ['_csrf' => token($bPage)], $bJar);
check('6 IDOR edit/update/toggle/delete return 404', $foreignEdit['status'] === 404 && $foreignUpdate['status'] === 404 && $foreignToggle['status'] === 404 && $foreignDelete['status'] === 404);
check('14 user B cannot see user A task', !str_contains(request('GET', '/tasks', [], $bJar)['body'], 'XSS &lt;img'));

$toggle = request('POST', '/tasks/' . $taskId . '/toggle', ['_csrf' => token($aTasksPage)], $aJar);
$toggledTask = findTask($taskId);
check('7 toggle sets done and completed_at', $toggle['status'] === 302 && $toggledTask['status'] === 'done' && is_string($toggledTask['completed_at']));
$toggleBack = request('POST', '/tasks/' . $taskId . '/toggle', ['_csrf' => token(request('GET', '/tasks', [], $aJar))], $aJar);
check('7 second toggle clears completed_at', $toggleBack['status'] === 302 && findTask($taskId)['completed_at'] === null);

$query = request('GET', '/tasks?page=-1&per_page=99999&sort=%3BDROP&direction=wat', [], $aJar);
check('10 unsafe query parameters normalize without error', $query['status'] === 200 && !str_contains($query['body'], '500'));
$csrfFail = request('POST', '/tasks/' . $taskId . '/toggle', [], $aJar);
check('13 POST without CSRF returns 403', $csrfFail['status'] === 403);
check('14 GET cannot mutate task routes', request('GET', '/tasks/' . $taskId . '/toggle', [], $aJar)['status'] === 404 && request('GET', '/tasks/' . $taskId . '/delete', [], $aJar)['status'] === 404);

$deleted = request('POST', '/tasks/' . $taskId . '/delete', ['_csrf' => token(request('GET', '/tasks', [], $aJar))], $aJar);
check('8 delete removes task', $deleted['status'] === 302 && findTask($taskId) === []);
check('9 filter bar and summary render real data', str_contains($aTasksPage['body'], 'Ringkasan') && str_contains($aTasksPage['body'], 'Filter tugas'));
