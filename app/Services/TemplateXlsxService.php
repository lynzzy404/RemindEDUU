<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class TemplateXlsxService
{
    public function export(): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tugas');
        $headers = ['Judul', 'Deskripsi', 'Kategori', 'Prioritas', 'Status', 'Tenggat'];
        $example = ['Contoh tugas', 'Deskripsi contoh', 'Pribadi', 'Sedang', 'Belum', '2026-11-10'];
        foreach ($headers as $index => $value) {
            $sheet->setCellValueExplicit($this->cellCoordinate($index, 1), $value, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($this->cellCoordinate($index, 2), $example[$index], DataType::TYPE_STRING);
        }
        $sheet->setCellValueExplicit('A3', 'Belajar materi', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('B3', '', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('C3', 'Belajar', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('D3', 'Tinggi', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('E3', 'Proses', DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('F3', '2026-12-01', DataType::TYPE_STRING);
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE14D');
        $sheet->freezePane('A2');
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Petunjuk');
        $rows = [
            ['Kolom', 'Isi yang diizinkan'],
            ['Prioritas', 'Rendah, Sedang, Tinggi'],
            ['Status', 'Belum, Proses, Selesai'],
            ['Tenggat', 'YYYY-MM-DD atau kosong'],
            ['Batas', 'Maksimal 1000 baris data & file maksimal 2 MB'],
            [''],
            ['Pindah ke Sheet "Tugas" untuk menambahkan data tugas baru.'],
        ];
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $instructions->setCellValueExplicit($this->cellCoordinate($columnIndex, $rowIndex + 1), $value, DataType::TYPE_STRING);
            }
        }
        $instructions->getStyle('A1:B1')->getFont()->setBold(true);
        $instructions->getStyle('A7:F7')->getFont()->setBold(true)->setItalic(true);
        $instructions->getStyle('A7:F7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Template stream cannot be opened.');
        }
        (new Xlsx($spreadsheet))->save($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        $spreadsheet->disconnectWorksheets();

        if ($contents === false) {
            throw new \RuntimeException('Template cannot be read.');
        }

        return $contents;
    }

    private function cellCoordinate(int $column, int $row): string
    {
        return chr(65 + $column) . $row;
    }
}
