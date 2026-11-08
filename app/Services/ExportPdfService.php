<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use App\Repositories\TaskRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

final class ExportPdfService
{
    public function __construct(private readonly TaskRepository $taskRepository)
    {
    }

    /** @param array<string, string> $filters @param array<string, mixed> $user @param array<string, array<string, mixed>> $categoryMap */
    public function export(string $userId, array $filters, array $user, array $categoryMap): string
    {
        $tasks = $this->taskRepository->allByUser($userId, $filters);
        $truncated = count($tasks) > 1000;
        $tasks = array_slice($tasks, 0, 1000);
        $view = new View(__DIR__ . '/../Views');
        $html = $view->render('export/pdf', [
            'tasks' => $tasks,
            'user' => $user,
            'category_map' => $categoryMap,
            'truncated' => $truncated,
            'summary' => $this->taskRepository->summaryByUser($userId),
        ], false);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->setChroot(__DIR__ . '/../Views');
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }
}
