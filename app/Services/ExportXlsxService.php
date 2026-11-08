<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TaskRepository;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ExportXlsxService
{
    public function __construct(private readonly TaskRepository $taskRepository)
    {
    }

    /** @param array<string, string> $filters */
    public function export(string $userId, array $filters, array $categoryMap): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Judul', 'Deskripsi', 'Kategori', 'Prioritas', 'Status', 'Tenggat'];
        foreach ($headers as $column => $header) {
            $sheet->setCellValueExplicit($this->cellCoordinate($column, 1), $header, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE14D');
        $sheet->freezePane('A2');

        $priorityLabels = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];
        $statusLabels = ['todo' => 'Belum', 'in_progress' => 'Proses', 'done' => 'Selesai'];
        $row = 2;
        foreach ($this->taskRepository->allByUser($userId, $filters) as $task) {
            $category = $categoryMap[(string) ($task['category_id'] ?? '')] ?? null;
            $values = [
                (string) ($task['title'] ?? ''),
                (string) ($task['description'] ?? ''),
                (string) ($category['name'] ?? ''),
                $priorityLabels[$task['priority'] ?? 'medium'] ?? 'Sedang',
                $statusLabels[$task['status'] ?? 'todo'] ?? 'Belum',
                (string) ($task['due_date'] ?? ''),
            ];
            foreach ($values as $column => $value) {
                $sheet->setCellValueExplicit($this->cellCoordinate($column, $row), $this->safeCell($value), DataType::TYPE_STRING);
            }
            $row++;
        }
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Export stream cannot be opened.');
        }
        $writer->save($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        if ($contents === false) {
            throw new \RuntimeException('Export cannot be read.');
        }

        return $contents;
    }

    private function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }

    private function cellCoordinate(int $column, int $row): string
    {
        return chr(65 + $column) . $row;
    }
}
