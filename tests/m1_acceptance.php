#!/usr/bin/env php
<?php

declare(strict_types=1);

$base = 'http://127.0.0.1:8099';
$cookie = tempnam(sys_get_temp_dir(), 'm1-cookie-');
if ($cookie === false) {
    throw new RuntimeException('Cannot create cookie jar.');
}

register_shutdown_function(static function () use ($cookie): void {
    if (is_file($cookie)) {
        unlink($cookie);
    }
});

/** @return array{status: int, headers: string, body: string} */
function request(string $method, string $path, array $fields = [], ?string $jar = null): array
{
    global $base, $cookie;
    $jar ??= $cookie;
    $handle = curl_init($base . $path);
    if ($handle === false) {
        throw new RuntimeException('Cannot initialize cURL.');
    }

    $headers = [];
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
            $headers[] = rtrim($line, "\r\n");

            return strlen($line);
        },
        CURLOPT_POSTFIELDS => $method === 'POST' ? http_build_query($fields) : null,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    $body = curl_exec($handle);
    if ($body === false) {
        throw new RuntimeException(curl_error($handle));
    }
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return ['status' => $status, 'headers' => implode("\n", $headers), 'body' => $body];
}

function csrf(array $response): string
{
    if (preg_match('/name="_csrf" value="([^"]+)"/', $response['body'], $matches) !== 1) {
        throw new RuntimeException('CSRF token not found; status=' . $response['status'] . ' body=' . substr($response['body'], 0, 120));
    }

    return $matches[1];
}

function check(string $label, bool $condition): void
{
    echo ($condition ? 'PASS' : 'FAIL') . ' - ' . $label . PHP_EOL;
}

$login = request('GET', '/login');
$register = request('GET', '/register');
$registered = request('POST', '/register', [
    '_csrf' => csrf($register),
    'name' => 'XSS <script>alert(1)</script>',
    'email' => 'm1user@example.com',
    'password' => 'rahasia123',
    'password_confirm' => 'rahasia123',
]);
check('1 register valid redirects', $registered['status'] === 302);

$users = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/users.json'), true, 512, JSON_THROW_ON_ERROR);
$categories = json_decode((string) file_get_contents(__DIR__ . '/../storage/data/categories.json'), true, 512, JSON_THROW_ON_ERROR);
$user = array_values(array_filter($users['items'], static fn (array $item): bool => $item['email'] === 'm1user@example.com'))[0];
check('1 password stored as hash only', preg_match('/^\$(argon2id|2y)\$/', $user['password_hash']) === 1 && !array_key_exists('password', $user));
check('1 three default categories seeded', count(array_filter($categories['items'], static fn (array $item): bool => $item['user_id'] === $user['id'])) === 3);

$duplicate = request('POST', '/register', [
    '_csrf' => csrf(request('GET', '/register')),
    'name' => 'Other',
    'email' => 'M1USER@EXAMPLE.COM',
    'password' => 'rahasia123',
    'password_confirm' => 'rahasia123',
]);
check('2 duplicate email rejected case-insensitive', $duplicate['status'] === 200 && str_contains($duplicate['body'], 'Email sudah terdaftar'));

$short = request('POST', '/register', [
    '_csrf' => csrf(request('GET', '/register')),
    'name' => 'A',
    'email' => 'bad@example.com',
    'password' => 'short',
    'password_confirm' => 'short',
]);
$mismatch = request('POST', '/register', [
    '_csrf' => csrf(request('GET', '/register')),
    'name' => 'Valid Name',
    'email' => 'bad@example.com',
    'password' => 'long-enough',
    'password_confirm' => 'different',
]);
check('3 short/mismatched password rejected inline', $short['status'] === 200 && str_contains($short['body'], 'minimal 8 karakter') && $mismatch['status'] === 200 && str_contains($mismatch['body'], 'Konfirmasi password tidak cocok'));

$loginPage = request('GET', '/login');
$loginCsrf = csrf($loginPage);
$cookieBeforeLogin = (string) $loginPage['headers'];
$loginSuccess = request('POST', '/login', [
    '_csrf' => $loginCsrf,
    'email' => 'm1user@example.com',
    'password' => 'rahasia123',
]);
check('4 correct login redirects to tasks', $loginSuccess['status'] === 302 && str_contains($loginSuccess['headers'], 'Location: /tasks'));
check('4 session cookie is issued with HttpOnly and SameSite', preg_match('/(?i)HttpOnly/', $loginSuccess['headers']) === 1 && preg_match('/(?i)SameSite=Lax/', $loginSuccess['headers']) === 1);
check('4 session id changes after login', $cookieBeforeLogin !== $loginSuccess['headers']);

$wrongSession = tempnam(sys_get_temp_dir(), 'm1-wrong-');
$unknownSession = tempnam(sys_get_temp_dir(), 'm1-unknown-');
if ($wrongSession === false || $unknownSession === false) {
    throw new RuntimeException('Cannot create comparison cookie jars.');
}
try {
    $wrongPage = request('GET', '/login', [], $wrongSession);
    $wrong = request('POST', '/login', ['_csrf' => csrf($wrongPage), 'email' => 'm1user@example.com', 'password' => 'wrong-password'], $wrongSession);
    $unknownPage = request('GET', '/login', [], $unknownSession);
    $unknown = request('POST', '/login', ['_csrf' => csrf($unknownPage), 'email' => 'unknown@example.com', 'password' => 'wrong-password'], $unknownSession);
    preg_match('/Email atau password salah\./', $wrong['body'], $wrongMessage);
    preg_match('/Email atau password salah\./', $unknown['body'], $unknownMessage);
    check('5 unknown and wrong password messages are byte-identical', $wrongMessage[0] === $unknownMessage[0]);
} finally {
    unlink($wrongSession);
    unlink($unknownSession);
}

check('9 logged-in user opening login redirects tasks', request('GET', '/login')['status'] === 302);
$loggedInTasks = request('GET', '/tasks');
check('11 XSS name is escaped in rendered page', str_contains($loggedInTasks['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($loggedInTasks['body'], '<script>alert(1)</script>'));

$logout = request('POST', '/logout', ['_csrf' => $loginCsrf]);
check('6 logout POST succeeds', $logout['status'] === 302);

for ($attempt = 1; $attempt <= 6; $attempt++) {
    $page = request('GET', '/login');
    $lastFailure = request('POST', '/login', ['_csrf' => csrf($page), 'email' => 'm1user@example.com', 'password' => 'wrong-password']);
}
check('6 sixth failed login is rate limited', $lastFailure['status'] === 429);

$withoutCsrf = request('POST', '/login', ['email' => 'm1user@example.com', 'password' => 'wrong-password']);
check('7 POST without CSRF returns 403', $withoutCsrf['status'] === 403);

$guestCookie = tempnam(sys_get_temp_dir(), 'm1-guest-');
if ($guestCookie === false) {
    throw new RuntimeException('Cannot create guest cookie jar.');
}
$oldCookie = $cookie;
$cookie = $guestCookie;
try {
    $private = request('GET', '/tasks');
    check('8 private tasks redirects guest to login', $private['status'] === 302 && str_contains($private['headers'], 'Location: /login'));
} finally {
    unlink($guestCookie);
    $cookie = $oldCookie;
}

check('10 GET logout rejected', request('GET', '/logout')['status'] === 404);
$securityLog = (string) file_get_contents(__DIR__ . '/../storage/logs/security.log');
check('12 security log contains auth and CSRF events without password', str_contains($securityLog, 'login_failed') && str_contains($securityLog, 'login_success') && str_contains($securityLog, 'csrf_failed') && !str_contains($securityLog, 'wrong-password'));
