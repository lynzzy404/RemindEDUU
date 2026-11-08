<?php

declare(strict_types=1);

$tasks = is_array($data['tasks'] ?? null) ? $data['tasks'] : [];
$categoryMap = is_array($data['category_map'] ?? null) ? $data['category_map'] : [];
$user = is_array($data['user'] ?? null) ? $data['user'] : [];
$summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
$statusLabels = ['todo' => 'Belum', 'in_progress' => 'Proses', 'done' => 'Selesai'];
$priorityLabels = ['low' => 'Rendah', 'medium' => 'Sedang', 'high' => 'Tinggi'];
$printedAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Makassar'));
$monthNames = [
	1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
	7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
?>
<!doctype html>
<html lang="id">
<head>
	<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body {
			color: #000;
			font-family: DejaVu Sans, sans-serif;
			font-size: 20px;
			line-height: 1.4;
		}

		h1 {
			margin: 0 0 8px;
			font-size: 36px;
		}

		.meta {
			margin-bottom: 12px;
			font-size: 18px;
		}

		table {
			width: 100%;
			border-collapse: collapse;
			font-size: 17px;
		}

		th,
		td {
			padding: 10px;
			border: 2px solid #000;
			vertical-align: top;
			line-height: 1.35;
			overflow-wrap: anywhere;
		}

		th {
			background: #FFE14D;
			font-weight: bold;
		}

		.notice {
			padding: 10px;
			border: 2px solid #000;
			background: #FF9F43;
			margin-bottom: 12px;
			font-size: 18px;
		}

		@media screen and (max-width: 600px) {
			body {
				font-size: 19px;
			}

			h1 {
				font-size: 36px;
			}

			.meta,
			.notice {
				font-size: 18px;
			}

			table {
				font-size: 17px;
			}

			th,
			td {
				padding: 10px;
			}
		}
	</style>
</head>
<body>
	<h1>RemindEDU</h1>

	<div class="meta">
		Pengguna: <?= e((string) ($user['name'] ?? '')) ?>
	</div>

	<div class="meta">
		Dicetak: <?= e($printedAt->format('d') . ' ' . $monthNames[(int) $printedAt->format('n')] . ' ' . $printedAt->format('Y') . ' ' . $printedAt->format('H:i') . ' WITA') ?>
	</div>

	<div class="meta">
        Terlambat: <?= (int) ($summary['overdue'] ?? 0) ?>
        · Selesai: <?= (int) ($summary['completed'] ?? 0) ?>
		· Total: <?= (int) ($summary['total'] ?? 0) ?>
	</div>

	<?php if (($data['truncated'] ?? false) === true): ?>
		<div class="notice">Data dibatasi hingga 1000 tugas.</div>
	<?php endif; ?>

	<table>
		<thead>
			<tr>
				<th>Judul</th>
				<th>Deskripsi</th>
				<th>Kategori</th>
				<th>Prioritas</th>
				<th>Status</th>
				<th>Tenggat</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($tasks as $task): ?>
				<?php $category = $categoryMap[(string) ($task['category_id'] ?? '')] ?? null; ?>
				<tr>
					<td><?= e((string) ($task['title'] ?? '')) ?></td>
					<td><?= e((string) ($task['description'] ?? '')) ?></td>
					<td><?= e((string) ($category['name'] ?? 'Tanpa Kategori')) ?></td>
					<td><?= e($priorityLabels[$task['priority'] ?? 'medium'] ?? 'Sedang') ?></td>
					<td><?= e($statusLabels[$task['status'] ?? 'todo'] ?? 'Belum') ?></td>
					<td><?= e((string) ($task['due_date'] ?? '')) ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</body></html>
