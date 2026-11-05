<?php

declare(strict_types=1);

require_once __DIR__ . '/../partials/category_dropdown.php';
$categories = is_array($data['categories'] ?? null) ? $data['categories'] : [];
?>
<section class="dashboard-header">
    <div>
        <p class="eyebrow">DASHBOARD</p>
        <h1 class="page-title">Tugas kamu</h1>
        <p class="page-lead">Satu tempat untuk semua hal yang perlu kamu selesaikan.</p>
    </div>
    <a class="btn btn--primary" href="/tasks/create">Tambah tugas pertama</a>
</section>

<section class="summary-grid" aria-label="Ringkasan tugas">
    <article class="card summary-card"><span>Total</span><strong>0</strong><small>semua tugas</small></article>
    <article class="card summary-card summary-card--green"><span>Selesai</span><strong>0</strong><small>sudah beres</small></article>
    <article class="card summary-card summary-card--pink"><span>Terlambat</span><strong>0</strong><small>perlu perhatian</small></article>
</section>

<section class="filter-bar card" aria-label="Filter tugas">
    <form method="GET" action="/tasks" class="filter-bar__form">
        <div class="form-group">
            <label for="task-search">Cari tugas</label>
            <input class="input" id="task-search" name="q" type="search" placeholder="Cari judul...">
        </div>
        <?php category_dropdown($categories, 'category_id', (string) ($data['selected_category'] ?? '')); ?>
        <button type="submit" class="btn btn--secondary">Terapkan</button>
    </form>
</section>

<section class="empty-state card" aria-label="Daftar tugas kosong">
    <div class="empty-state__mark">0</div>
    <h2>Belum ada tugas</h2>
    <p>Mulai dari satu langkah kecil hari ini.</p>
    <a class="btn btn--primary" href="/tasks/create">Tambah tugas pertama</a>
</section>
