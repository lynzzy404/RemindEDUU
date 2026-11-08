<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\CategoryRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Services\ExportPdfService;
use App\Services\ExportXlsxService;
use App\Services\TemplateXlsxService;

final class ExportController
{
    public function __construct(
        private readonly Request $request,
        private readonly TaskRepository $taskRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly UserRepository $userRepository,
        private readonly Session $session
    ) {
    }

    public function xlsx(): Response
    {
        $userId = (string) Auth::id();
        $categories = $this->categoryRepository->allByUser($userId);
        $body = (new ExportXlsxService($this->taskRepository))->export($userId, $this->filters(), $this->categoryMap($categories));

        return Response::download($body, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'RemindEDU-' . gmdate('Ymd-Hi') . '.xlsx');
    }

    public function pdf(): Response
    {
        $userId = (string) Auth::id();
        $categories = $this->categoryRepository->allByUser($userId);
        $user = $this->userRepository->findById($userId) ?? [];
        unset($user['password_hash']);
        $body = (new ExportPdfService($this->taskRepository))->export($userId, $this->filters(), $user, $this->categoryMap($categories));

        return Response::download($body, 'application/pdf', 'RemindEDU-' . gmdate('Ymd-Hi') . '.pdf');
    }

    public function template(): Response
    {
        return Response::download((new TemplateXlsxService())->export(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'RemindEDU-template.xlsx');
    }

    /** @return array<string, string> */
    private function filters(): array
    {
        $filters = [
            'q' => trim($this->request->string('q')),
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
        if (!in_array($filters['sort'], ['created_at', 'due_date', 'priority', 'title'], true)) {
            $filters['sort'] = 'created_at';
        }
        if (!in_array($filters['direction'], ['asc', 'desc'], true)) {
            $filters['direction'] = 'desc';
        }
        return $filters;
    }

    /** @param list<array<string, mixed>> $categories @return array<string, array<string, mixed>> */
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
}
