<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Repositories\CategoryRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
use App\Services\ImportService;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use RuntimeException;

final class ImportController
{
    private readonly View $view;

    public function __construct(
        private readonly string $dataPath,
        private readonly RateLimiter $rateLimiter,
        private readonly UserRepository $userRepository,
        private readonly TaskRepository $taskRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly Logger $logger,
        private readonly Session $session
    ) {
        $this->view = new View(__DIR__ . '/../Views');
    }

    public function show(): Response
    {
        return Response::html($this->render(['title' => 'Import Tugas', 'csrf_token' => (new Csrf())->token()]));
    }

    public function import(): Response
    {
        if (!Csrf::verifyRequest()) {
            $this->logger->security('csrf_failed', Auth::id(), (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
            return Response::forbidden();
        }
        $userId = (string) Auth::id();
        $user = $this->userRepository->findById($userId) ?? [];
        $key = RateLimiter::keyFromIpAndEmail((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), (string) ($user['email'] ?? ''));
        if ($this->rateLimiter->tooManyAttempts($key)) {
            $this->logger->security('import_lockout', $userId, (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
            return new Response('Terlalu banyak percobaan import.', 429, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $this->rateLimiter->hit($key);
        try {
            $result = (new ImportService($this->dataPath, $this->taskRepository, $this->categoryRepository))
                ->import($userId, $_FILES['file'] ?? []);
            $this->logger->security('import_success', $userId, (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), ['rows' => $result['imported']]);
            $this->rateLimiter->clear($key);
            return Response::html($this->render(['title' => 'Import Tugas', 'csrf_token' => (new Csrf())->token(), 'result' => $result]));
        } catch (RuntimeException|ReaderException $exception) {
            $this->logger->security('upload_rejected', $userId, (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), ['reason' => $exception->getMessage()]);
            return Response::html($this->render(['title' => 'Import Tugas', 'csrf_token' => (new Csrf())->token(), 'error' => $exception->getMessage()]), 422);
        }
    }

    /** @param array<string, mixed> $data */
    private function render(array $data): string
    {
        $data['session'] = $this->session;
        return $this->view->render('import/index', $data);
    }
}
