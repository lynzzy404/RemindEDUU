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
use App\Repositories\TaskRepository;
use RuntimeException;

final class TaskController
{
    private readonly View $view;

    public function __construct(
        private readonly Request $request,
        private readonly TaskRepository $taskRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly Logger $logger,
        private readonly Session $session
    ) {
        $this->view = new View(__DIR__ . '/../Views');
    }

    public function index(): Response
    {
        $userId = $this->userId();
        $filters = $this->filters();
        $result = $this->taskRepository->paginateByUser(
            $userId,
            $filters,
            $this->request->int('page', 1),
            $this->request->int('per_page', 10)
        );
        $categories = $this->categoryRepository->allByUser($userId);

        return Response::html($this->render('tasks/index', [
            'title' => 'Tugas',
            'categories' => $categories,
            'category_map' => $this->categoryMap($categories),
            'tasks' => $result['items'],
            'pagination' => $result,
            'filters' => $filters,
            'summary' => $this->taskRepository->summaryByUser($userId),
            'csrf_token' => (new Csrf())->token(),
        ]));
    }

    public function createForm(): Response
    {
        return Response::html($this->render('tasks/form', $this->formData([
            'title' => 'Tambah Tugas',
            'heading' => 'Tambah tugas',
            'action' => '/tasks',
            'submit_label' => 'Simpan tugas',
            'old' => $this->defaultTask(),
        ])));
    }

    public function create(): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::forbidden();
        }

        $input = $this->taskInput();
        $errors = $this->validateTask($input);
        if ($errors !== []) {
            return $this->validationResponse('Tambah Tugas', '/tasks', 'Simpan tugas', $input, $errors);
        }

        $this->taskRepository->create($this->userId(), $input);
        $this->session->flash('success', 'Tugas berhasil ditambahkan.');

        return Response::redirect('/tasks');
    }

    public function editForm(string $id): Response
    {
        $task = $this->ownedTask($id);
        if ($task === null) {
            return Response::notFound();
        }

        return Response::html($this->render('tasks/form', $this->formData([
            'title' => 'Edit Tugas',
            'heading' => 'Edit tugas',
            'action' => '/tasks/' . $id . '/update',
            'submit_label' => 'Simpan perubahan',
            'old' => $task,
        ])));
    }

    public function update(string $id): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::forbidden();
        }
        if ($this->ownedTask($id) === null) {
            return Response::notFound();
        }

        $input = $this->taskInput();
        $errors = $this->validateTask($input);
        if ($errors !== []) {
            return $this->validationResponse('Edit Tugas', '/tasks/' . $id . '/update', 'Simpan perubahan', $input, $errors);
        }

        $this->taskRepository->update($id, $this->userId(), $input);
        $this->session->flash('success', 'Tugas berhasil diperbarui.');

        return Response::redirect('/tasks');
    }

    public function toggle(string $id): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::forbidden();
        }

        try {
            $task = $this->taskRepository->toggle($id, $this->userId());
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Task not found.') {
                $this->logDenied($id);
                return Response::notFound();
            }
            throw $exception;
        }
        $this->session->flash('success', $task['status'] === 'done' ? 'Tugas ditandai selesai.' : 'Tugas dibuka kembali.');

        return Response::redirect('/tasks');
    }

    public function delete(string $id): Response
    {
        if (!$this->verifyCsrf()) {
            return Response::forbidden();
        }

        try {
            $this->taskRepository->delete($id, $this->userId());
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'Task not found.') {
                $this->logDenied($id);
                return Response::notFound();
            }
            throw $exception;
        }
        $this->session->flash('success', 'Tugas dihapus.');

        return Response::redirect('/tasks');
    }

    /** @return array<string, string> */
    private function filters(): array
    {
        $filters = [
            'q' => Validator::clean($this->request->string('q')),
            'status' => $this->request->string('status'),
            'category' => $this->request->string('category'),
            'priority' => $this->request->string('priority'),
            'sort' => $this->request->string('sort', 'created_at'),
            'direction' => $this->request->string('direction', 'desc'),
        ];
        if (!in_array($filters['status'], TaskRepository::STATUSES, true)) {
            $filters['status'] = '';
        }
        if (!in_array($filters['priority'], TaskRepository::PRIORITIES, true)) {
            $filters['priority'] = '';
        }

        return $filters;
    }

    /** @return array<string, string> */
    private function taskInput(): array
    {
        return [
            'category_id' => Validator::clean($this->request->string('category_id')),
            'title' => Validator::clean($this->request->string('title')),
            'description' => Validator::clean($this->request->string('description')),
            'status' => $this->request->string('status', 'todo'),
            'priority' => $this->request->string('priority', 'medium'),
            'due_date' => Validator::clean($this->request->string('due_date')),
        ];
    }

    /** @param array<string, string> $input @return array<string, string> */
    private function validateTask(array $input): array
    {
        $validator = new Validator();
        $validator->validate($input, [
            'title' => 'required|min:1|max:120',
            'description' => 'max:1000',
            'status' => 'required|in:' . implode(',', TaskRepository::STATUSES),
            'priority' => 'required|in:' . implode(',', TaskRepository::PRIORITIES),
        ]);
        $errors = $validator->errors();
        if (!Validator::isValidUtf8($input['title']) || !Validator::isValidUtf8($input['description'])) {
            $errors['title'] = 'Teks tugas tidak valid.';
        }
        if ($input['due_date'] !== '' && !$this->validDate($input['due_date'])) {
            $errors['due_date'] = 'Tenggat harus berupa tanggal kalender yang valid.';
        }
        if ($input['category_id'] !== '' && $this->categoryRepository->findOwned($input['category_id'], $this->userId()) === null) {
            $errors['category_id'] = 'Kategori tidak valid.';
        }

        return $errors;
    }

    private function validDate(string $date): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) !== 1) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    /** @return array<string, mixed> */
    private function defaultTask(): array
    {
        return [
            'category_id' => '',
            'title' => '',
            'description' => '',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => '',
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function formData(array $data): array
    {
        return array_merge([
            'categories' => $this->categoryRepository->allByUser($this->userId()),
            'csrf_token' => (new Csrf())->token(),
            'errors' => [],
            'session' => $this->session,
        ], $data);
    }

    /** @param array<string, string> $input @param array<string, string> $errors */
    private function validationResponse(string $title, string $action, string $submitLabel, array $input, array $errors): Response
    {
        return Response::html($this->render('tasks/form', $this->formData([
            'title' => $title,
            'heading' => $title,
            'action' => $action,
            'submit_label' => $submitLabel,
            'old' => $input,
            'errors' => $errors,
        ])), 422);
    }

    /** @return array<string, array<string, mixed>> */
    private function categoryMap(array $categories): array
    {
        $map = [];
        foreach ($categories as $category) {
            if (isset($category['id']) && is_string($category['id'])) {
                $map[$category['id']] = $category;
            }
        }

        return $map;
    }

    /** @return array<string, mixed>|null */
    private function ownedTask(string $id): ?array
    {
        $task = $this->taskRepository->findOwned($id, $this->userId());
        if ($task === null) {
            $this->logDenied($id);
        }

        return $task;
    }

    private function verifyCsrf(): bool
    {
        if (Csrf::verifyRequest()) {
            return true;
        }
        $this->logger->security('csrf_failed', Auth::id(), $this->request->ip());

        return false;
    }

    private function logDenied(string $id): void
    {
        $this->logger->security('task_access_denied', $this->userId(), $this->request->ip(), ['resource' => $id]);
    }

    private function userId(): string
    {
        return (string) (Auth::id() ?? '');
    }

    /** @param array<string, mixed> $data */
    private function render(string $template, array $data = []): string
    {
        $data['session'] = $this->session;

        return $this->view->render($template, $data);
    }
}
