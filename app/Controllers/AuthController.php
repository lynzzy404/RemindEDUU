<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CategoryRepository;
use App\Repositories\UserRepository;

final class AuthController
{
    private readonly View $view;

    public function __construct(
        private readonly Request $request,
        private readonly UserRepository $userRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly RateLimiter $rateLimiter,
        private readonly Logger $logger,
        private readonly Session $session
    ) {
        $this->view = new View(__DIR__ . '/../Views');
    }

    public function showLogin(): Response
    {
        if (Auth::id() !== null) {
            return Response::redirect('/tasks');
        }

        return Response::html($this->renderView('auth/login', [
            'title' => 'Masuk',
            'csrf_token' => $this->csrfToken(),
            'next' => $this->safeNext($this->request->string('next')),
        ]));
    }

    public function handleLogin(): Response
    {
        if (!Csrf::verifyRequest()) {
            $this->logger->security('csrf_failed', Auth::id(), $this->request->ip());

            return Response::forbidden();
        }

        if (Auth::id() !== null) {
            return Response::redirect('/tasks');
        }

        $email = Validator::clean($this->request->string('email'));
        $password = $this->request->string('password');
        $key = $this->rateLimiterKey($email);

        if ($this->rateLimiter->tooManyAttempts($key)) {
            $this->logger->security('login_rate_limited', null, $this->request->ip());

            return Response::html(
                $this->renderView('auth/login', [
                    'title' => 'Masuk',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => ['general' => 'Terlalu banyak percobaan. Silakan coba lagi dalam 15 menit.'],
                    'old' => ['email' => $email],
                    'next' => $this->safeNext($this->request->string('next')),
                ]),
                429
            );
        }

        $validator = new Validator();
        $validator->validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required']
        );

        if ($validator->fails()) {
            $this->rateLimiter->hit($key);

            return Response::html(
                $this->renderView('auth/login', [
                    'title' => 'Masuk',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => $validator->errors(),
                    'old' => ['email' => $email],
                    'next' => $this->safeNext($this->request->string('next')),
                ])
            );
        }

        $user = $this->userRepository->verifyPassword($email, $password);

        if ($user === null) {
            $this->rateLimiter->hit($key);
            $this->logger->security('login_failed', null, $this->request->ip());

            return Response::html(
                $this->renderView('auth/login', [
                    'title' => 'Masuk',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => ['general' => 'Email atau password salah.'],
                    'old' => ['email' => $email],
                    'next' => $this->safeNext($this->request->string('next')),
                ])
            );
        }

        $this->rateLimiter->clear($key);
        $this->userRepository->updateLastLogin($user['id']);

        // SEC-10/14: rehash otomatis bila parameter hash sudah usang.
        if (password_needs_rehash((string) $user['password_hash'], $this->passwordAlgorithm())) {
            $this->userRepository->updatePasswordHash(
                (string) $user['id'],
                $this->hashPassword($password)
            );
        }

        $this->logger->security('login_success', $user['id'], $this->request->ip(), []);

        Auth::login((string) $user['id'], (string) ($user['name'] ?? ''));

        return Response::redirect($this->redirectAfterLogin());
    }

    public function showRegister(): Response
    {
        if (Auth::id() !== null) {
            return Response::redirect('/tasks');
        }

        return Response::html($this->renderView('auth/register', [
            'title' => 'Daftar',
            'csrf_token' => $this->csrfToken(),
        ]));
    }

    public function handleRegister(): Response
    {
        if (!Csrf::verifyRequest()) {
            $this->logger->security('csrf_failed', Auth::id(), $this->request->ip());

            return Response::forbidden();
        }

        if (Auth::id() !== null) {
            return Response::redirect('/tasks');
        }

        // SEC-13: rate limit juga berlaku untuk register (per IP+email).
        $name = Validator::clean($this->request->string('name'));
        $email = Validator::clean($this->request->string('email'));
        $password = $this->request->string('password');
        $passwordConfirm = $this->request->string('password_confirm');
        $key = $this->rateLimiterKey($email);

        if ($this->rateLimiter->tooManyAttempts($key)) {
            $this->logger->security('register_rate_limited', null, $this->request->ip());

            return Response::html(
                $this->renderView('auth/register', [
                    'title' => 'Daftar',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => ['general' => 'Terlalu banyak percobaan. Silakan coba lagi dalam 15 menit.'],
                    'old' => ['name' => $name, 'email' => $email],
                ]),
                429
            );
        }

        $validator = new Validator();
        $validator->validate(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirm' => $passwordConfirm],
            ['name' => 'required|min:2|max:60', 'email' => 'required|email|max:254', 'password' => 'required|min:8|max:128']
        );

        if ($validator->fails()) {
            $this->rateLimiter->hit($key);

            return Response::html(
                $this->renderView('auth/register', [
                    'title' => 'Daftar',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => $validator->errors(),
                    'old' => ['name' => $name, 'email' => $email],
                ])
            );
        }

        if ($password !== $passwordConfirm) {
            $this->rateLimiter->hit($key);

            return Response::html(
                $this->renderView('auth/register', [
                    'title' => 'Daftar',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => ['password_confirm' => 'Konfirmasi password tidak cocok.'],
                    'old' => ['name' => $name, 'email' => $email],
                ])
            );
        }

        if (!Validator::isValidUtf8($name) || !Validator::isValidUtf8($email)) {
            $this->rateLimiter->hit($key);

            return Response::html(
                $this->renderView('auth/register', [
                    'title' => 'Daftar',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => ['general' => 'Data yang dimasukkan tidak valid.'],
                    'old' => ['name' => $name, 'email' => $email],
                ])
            );
        }

        try {
            $user = $this->userRepository->create([
                'name' => $name,
                'email' => strtolower($email),
                'password_hash' => $this->hashPassword($password),
            ]);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() !== 'Email already exists.') {
                throw $exception;
            }

            $this->rateLimiter->hit($key);

            return Response::html(
                $this->renderView('auth/register', [
                    'title' => 'Daftar',
                    'csrf_token' => $this->csrfToken(),
                    'errors' => ['email' => 'Email sudah terdaftar. Gunakan email lain.'],
                    'old' => ['name' => $name, 'email' => $email],
                ])
            );
        }

        $this->rateLimiter->clear($key);
        $this->categoryRepository->seedDefaults($user['id']);
        $this->logger->security('register_success', $user['id'], $this->request->ip());

        $this->session->flash('success', 'Akun berhasil dibuat. Silakan masuk.');

        return Response::redirect('/login');
    }

    public function logout(): Response
    {
        if ($this->request->method() !== 'POST') {
            return Response::html($this->renderView('errors/404'), 404);
        }

        if (!Csrf::verifyRequest()) {
            $this->logger->security('csrf_failed', Auth::id(), $this->request->ip());

            return Response::forbidden();
        }

        $userId = Auth::id();
        $this->logger->security('logout', $userId, $this->request->ip(), []);
        Auth::logout();

        return Response::redirect('/login');
    }

    private function renderView(string $template, array $data = []): string
    {
        $data['session'] = $this->session;

        return $this->view->render($template, $data);
    }

    private function csrfToken(): string
    {
        $csrf = new Csrf();

        return $csrf->token();
    }

    private function rateLimiterKey(string $email): string
    {
        return RateLimiter::keyFromIpAndEmail($this->request->ip(), $email);
    }

    private function redirectAfterLogin(): string
    {
        $next = $this->safeNext($this->request->string('next'));
        if ($next !== null) {
            return $next;
        }

        return '/tasks';
    }

    private function safeNext(string $next): ?string
    {
        if ($next !== '' && !str_starts_with($next, '//') && preg_match('#^/[A-Za-z0-9/_?&=.%+-]*$#', $next) === 1) {
            return $next;
        }

        return null;
    }

    private function passwordAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private function hashPassword(string $password): string
    {
        $algorithm = $this->passwordAlgorithm();
        $options = $algorithm === PASSWORD_BCRYPT ? ['cost' => 12] : [];
        $hash = password_hash($password, $algorithm, $options);
        if ($hash === false) {
            throw new \RuntimeException('Password hashing failed.');
        }

        return $hash;
    }
}
