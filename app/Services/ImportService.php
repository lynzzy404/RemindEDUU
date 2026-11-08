<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\JsonStore;
use App\Repositories\CategoryRepository;
use App\Repositories\TaskRepository;
use DateTime;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

final class ImportService
{
    private const MAX_BYTES = 2097152;
    private const MAX_ROWS = 1000;
    private const MAX_COLUMNS = 6;

    public function __construct(
        private readonly string $dataPath,
        private readonly TaskRepository $taskRepository,
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    /** @param array<string, mixed> $file @return array{imported: int, skipped: int, categories: int, errors: list<array{row: int, message: string}>} */
    public function import(string $userId, array $file): array
    {
        $temporaryPath = null;
        try {
            $temporaryPath = $this->validateAndMove($file);
            $reader = IOFactory::createReaderForFile($temporaryPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($temporaryPath);
            $sheet = $spreadsheet->getSheet(0);
            if ($sheet->getHighestColumn() > 'F' || $sheet->getHighestRow() > self::MAX_ROWS + 1) {
                throw new RuntimeException('File melebihi batas kolom atau 1000 baris data.');
            }
            $header = $sheet->rangeToArray('A1:F1', null, true, true, false)[0] ?? [];
            if ($header !== ['Judul', 'Deskripsi', 'Kategori', 'Prioritas', 'Status', 'Tenggat']) {
                throw new RuntimeException('Header kolom tidak sesuai template.');
            }

            $rows = [];
            $errors = [];
            $lastRow = $sheet->getHighestRow();
            for ($rowNumber = 2; $rowNumber <= $lastRow; $rowNumber++) {
                $values = $sheet->rangeToArray('A' . $rowNumber . ':F' . $rowNumber, null, true, true, false)[0] ?? [];
                if ($this->rowEmpty($values)) {
                    continue;
                }
                $parsed = $this->parseRow($values);
                if ($parsed['error'] !== null) {
                    $errors[] = ['row' => $rowNumber, 'message' => $parsed['error']];
                    continue;
                }
                $rows[] = $parsed['task'];
            }
            $newCategories = $this->resolveCategories($userId, $rows);
            if (count($newCategories) > 20) {
                throw new RuntimeException('Maksimal 20 kategori baru per import.');
            }
            $categoryIds = [];
            foreach ($newCategories as $name => $color) {
                $created = $this->categoryRepository->create($userId, ['name' => $name, 'color' => $color]);
                $categoryIds[mb_strtolower($name)] = $created['id'];
            }
            foreach ($this->categoryRepository->allByUser($userId) as $category) {
                $categoryIds[mb_strtolower((string) $category['name'])] = $category['id'];
            }
            $tasks = [];
            foreach ($rows as $row) {
                $categoryName = mb_strtolower($row['category_name']);
                $row['category_id'] = $categoryName === '' ? null : ($categoryIds[$categoryName] ?? null);
                unset($row['category_name']);
                $task = $row;
                $task['id'] = JsonStore::generateId('tsk');
                $now = gmdate('Y-m-d\TH:i:s\Z');
                $task['user_id'] = $userId;
                $task['created_at'] = $now;
                $task['updated_at'] = $now;
                $task['completed_at'] = $task['status'] === 'done' ? $now : null;
                $tasks[] = $task;
            }
            $this->backupTasks();
            $store = new JsonStore($this->dataPath . '/tasks.json');
            $store->transaction(static function (array &$document) use ($tasks): void {
                foreach ($tasks as $task) {
                    $document['items'][] = $task;
                }
            });
            $spreadsheet->disconnectWorksheets();

            return ['imported' => count($tasks), 'skipped' => count($errors), 'categories' => count($newCategories), 'errors' => $errors];
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }

    /** @param array<string, mixed> $file */
    private function validateAndMove(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Upload file tidak valid.');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Ukuran file maksimal 2 MB.');
        }
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, ['xls', 'xlsx'], true)) {
            throw new RuntimeException('Ekstensi file harus .xls atau .xlsx.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = $extension === 'xlsx'
            ? ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip']
            : ['application/vnd.ms-excel', 'application/octet-stream'];
        if (!is_string($mime) || !in_array($mime, $allowed, true)) {
            throw new RuntimeException('MIME file tidak sesuai.');
        }
        $temporaryPath = $this->dataPath . '/../tmp/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $temporaryPath)) {
            throw new RuntimeException('File upload tidak dapat dipindahkan.');
        }

        return $temporaryPath;
    }

    /** @param list<mixed> $values */
    private function rowEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @param list<mixed> $values @return array{task: array<string, mixed>, error: string|null} */
    private function parseRow(array $values): array
    {
        $title = $this->clean((string) ($values[0] ?? ''));
        $description = $this->clean((string) ($values[1] ?? ''));
        $category = $this->clean((string) ($values[2] ?? ''));
        $priorityValue = mb_strtolower(trim((string) ($values[3] ?? '')));
        $priority = ['rendah' => 'low', 'sedang' => 'medium', 'tinggi' => 'high'][$priorityValue] ?? null;
        $statusValue = mb_strtolower(trim((string) ($values[4] ?? '')));
        $status = ['belum' => 'todo', 'proses' => 'in_progress', 'selesai' => 'done'][$statusValue] ?? null;
        $date = $this->dateValue($values[5] ?? '');
        if ($title === '' || mb_strlen($title) > 120) {
            return ['task' => [], 'error' => 'Judul wajib diisi dan maksimal 120 karakter.'];
        }
        if (mb_strlen($description) > 1000) {
            return ['task' => [], 'error' => 'Deskripsi maksimal 1000 karakter.'];
        }
        if ($priority === null || $status === null) {
            return ['task' => [], 'error' => 'Prioritas atau status tidak valid.'];
        }
        if ($date === false) {
            return ['task' => [], 'error' => 'Tenggat tidak valid.'];
        }

        return ['task' => ['category_name' => $category, 'title' => $title, 'description' => $description, 'status' => $status, 'priority' => $priority, 'due_date' => $date], 'error' => null];
    }

    private function clean(string $value): string
    {
        $value = trim($value);
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }

    private function dateValue(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }
        $value = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) !== 1 || !checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            return false;
        }
        return $value;
    }

    /** @param list<array<string, mixed>> $rows @return array<string, string> */
    private function resolveCategories(string $userId, array $rows): array
    {
        $existing = [];
        foreach ($this->categoryRepository->allByUser($userId) as $category) {
            $existing[mb_strtolower((string) $category['name'])] = true;
        }
        $new = [];
        $newKeys = [];
        $colors = CategoryRepository::allowedColors();
        foreach ($rows as $row) {
            $name = trim((string) $row['category_name']);
            $key = mb_strtolower($name);
            if ($name !== '' && !isset($existing[$key]) && !isset($newKeys[$key])) {
                $new[$name] = $colors[count($new) % count($colors)];
                $newKeys[$key] = true;
            }
        }
        return $new;
    }

    private function backupTasks(): void
    {
        $backupDirectory = $this->dataPath . '/backup';
        if (!is_dir($backupDirectory)) {
            mkdir($backupDirectory, 0750, true);
        }
        $backup = $backupDirectory . '/tasks-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.bak';
        if (!copy($this->dataPath . '/tasks.json', $backup)) {
            throw new RuntimeException('Backup tasks gagal dibuat.');
        }
    }
}
