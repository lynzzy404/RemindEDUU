<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\CategoryRepository;
use RuntimeException;

final class CategoryController
{
    private readonly View $view;

    public function __construct(
        private readonly Request $request,
        private readonly CategoryRepository $categoryRepository,
        private readonly Logger $logger,
        private readonly Session $session
    ) {
        $this->view = new View(__DIR__ . '/../Views');
    }

    public function index(): Response
    {
        $userId = Auth::id();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        return Response::html($this->render('categories/index', [
            'title' => 'Kategori',
            'categories' => $this->categoryRepository->allByUser($userId),
            'task_counts' => $this->categoryRepository->taskCountsByCategory($userId),
            'csrf_token' => (new Csrf())->token(),
            'colors' => CategoryRepository::ALLOWED_COLORS,
        ]));
    }

    public function create(): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::forbidden();
        }

        $userId = Auth::id();
        if ($userId === null) {
            return Response::redirect('/login');
        }

        $name = Validator::clean($this->request->string('name'));
        $color = strtoupper(Validator::clean($this->request->string('color')));
        $validator = new Validator();
        $validator->validate(
            ['name' => $name, 'color' => $color],
            ['name' => 'required|min:1|max:30', 'color' => 'required|in:' . implode(',', CategoryRepository::ALLOWED_COLORS)]
        );

        if ($validator->fails() || !Validator::isValidUtf8($name)) {
            return $this->validationResponse($validator->errors(), $name, $color);
        }

        try {
            $this->categoryRepository->create($userId, ['name' => $name, 'color' => $color]);
        } catch (RuntimeException $exception) {
            if (!in_array($exception->getMessage(), ['Category name already exists.', 'Category limit reached.', 'Invalid category data.'], true)) {
                throw $exception;
            }

            return $this->validationResponse([
                'name' => $exception->getMessage() === 'Category name already exists.'
                    ? 'Nama kategori sudah digunakan.'
                    : 'Kategori tidak dapat ditambahkan.',
            ], $name, $color);
        }

        $this->session->flash('success', 'Kategori berhasil ditambahkan.');

        return Response::redirect('/categories');
    }

    public function update(string $id): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::forbidden();
        }

        $userId = Auth::id();
        if ($userId === null) {
            return Response::redirect('/login');
        }
        if ($this->categoryRepository->findOwned($id, $userId) === null) {
            $this->logDenied($id, $userId);

            return Response::notFound();
        }

        $name = Validator::clean($this->request->string('name'));
        $color = strtoupper(Validator::clean($this->request->string('color')));
        $validator = new Validator();
        $validator->validate(
            ['name' => $name, 'color' => $color],
            ['name' => 'required|min:1|max:30', 'color' => 'required|in:' . implode(',', CategoryRepository::ALLOWED_COLORS)]
        );
        if ($validator->fails() || !Validator::isValidUtf8($name)) {
            return $this->validationResponse($validator->errors(), $name, $color);
        }

        try {
            $this->categoryRepository->update($id, $userId, ['name' => $name, 'color' => $color]);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Category not found.') {
                $this->logDenied($id, $userId);

                return Response::notFound();
            }
            if ($exception->getMessage() === 'Category name already exists.') {
                return $this->validationResponse(['name' => 'Nama kategori sudah digunakan.'], $name, $color);
            }
            throw $exception;
        }

        $this->session->flash('success', 'Kategori berhasil diperbarui.');

        return Response::redirect('/categories');
    }

    public function delete(string $id): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::forbidden();
        }

        $userId = Auth::id();
        if ($userId === null) {
            return Response::redirect('/login');
        }
        if ($this->categoryRepository->findOwned($id, $userId) === null) {
            $this->logDenied($id, $userId);

            return Response::notFound();
        }

        $this->categoryRepository->delete($id, $userId);
        $this->session->flash('success', 'Kategori dihapus. Tugas terkait menjadi tanpa kategori.');

        return Response::redirect('/categories');
    }

    private function verifyCsrf(): bool
    {
        if (Csrf::verifyRequest()) {
            return true;
        }
        $this->logger->security('csrf_failed', Auth::id(), $this->request->ip());

        return false;
    }

    private function logDenied(string $id, string $userId): void
    {
        $this->logger->security('category_access_denied', $userId, $this->request->ip(), ['resource' => $id]);
    }

    /** @param array<string, string> $errors */
    private function validationResponse(array $errors, string $name, string $color): Response
    {
        return Response::html($this->render('categories/index', [
            'title' => 'Kategori',
            'categories' => $this->categoryRepository->allByUser((string) Auth::id()),
            'task_counts' => $this->categoryRepository->taskCountsByCategory((string) Auth::id()),
            'csrf_token' => (new Csrf())->token(),
            'colors' => CategoryRepository::ALLOWED_COLORS,
            'errors' => $errors,
            'old' => ['name' => $name, 'color' => $color],
        ], 422), 422);
    }

    private function render(string $template, array $data = [], int $status = 200): string
    {
        $data['session'] = $this->session;

        return $this->view->render($template, $data);
    }
}
