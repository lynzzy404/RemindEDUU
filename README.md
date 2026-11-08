# RemindEDU

Aplikasi manajemen tugas sederhana bergaya **Neu Brutalism**, dibangun dengan **PHP Native** dan penyimpanan **file JSON lokal** (tanpa database).

![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Storage](https://img.shields.io/badge/Storage-JSON-000000?style=for-the-badge)
![Style](https://img.shields.io/badge/UI-Neu%20Brutalism-FFE14D?style=for-the-badge&labelColor=000000)
![License](https://img.shields.io/badge/License-MIT-5EEAA0?style=for-the-badge&labelColor=000000)

---

## Fitur

| Fitur | Deskripsi |
|---|---|
| Login & Register | Session aman, password di-hash dengan `password_hash()`, CSRF & rate limit |
| CRUD Tugas | Tambah, ubah, hapus, tandai selesai; prioritas, status, dan tenggat |
| Kategori Kustom | Kategori pribadi berwarna dengan **custom dropdown** yang aksesibel |
| Filter & Cari | Cari judul, filter status/kategori/prioritas, sorting, pagination |
| Export | Unduh tugas sebagai **XLSX** atau **PDF** |
| Import | Unggah **XLS/XLSX** dengan validasi per baris + template siap pakai |
| Navigasi responsif | Sidebar tetap di desktop dan drawer aksesibel di mobile/tablet |
| Aman | Proteksi file JSON, anti-XSS, anti-CSRF, anti formula injection |

---

## Preview

Screenshot rilis disimpan di `docs/screenshots/` ketika tersedia. Instalasi dan verifikasi tidak
bergantung pada screenshot.

| Halaman | Preview |
|---|---|
| Login | Belum disertakan dalam source distribution |
| Daftar Tugas | Belum disertakan dalam source distribution |
| Kategori & Dropdown | Belum disertakan dalam source distribution |
| Import/Export | Belum disertakan dalam source distribution |

**Gaya visual:** border hitam 3px, hard shadow `4px 4px 0 #000`, latar krem `#FFF8E7`, aksen kuning, pink, biru, dan hijau.

---

## Tech Stack

- **PHP >= 8.1** (Native, tanpa framework)
- **Storage:** JSON (`storage/data/*.json`) dengan `flock` + atomic write
- **Library (Composer):** [`phpoffice/phpspreadsheet`](https://github.com/PHPOffice/PhpSpreadsheet) (XLS/XLSX), [`dompdf/dompdf`](https://github.com/dompdf/dompdf) (PDF)
- **Frontend:** HTML + CSS + JavaScript vanilla (tanpa CDN pihak ketiga)

---

## Struktur Proyek (ringkas)

```
RemindEDU/
├── public/        # document root (index.php, assets)
├── app/           # Core, Controllers, Repositories, Services, Views
├── config/        # konfigurasi
├── storage/       # data JSON, log, tmp  (DI LUAR web root)
├── planner/       # M0.md ... M6.md (rencana kerja)
├── SPEC.md
├── SECURITY-STANDARDS.md
└── README.md
```

Struktur lengkap ada di [`SPEC.md`](./SPEC.md#23-struktur-folder-worktree).

---

## Instalasi & Menjalankan di Lokal

### Prasyarat
- PHP **8.1+** dengan ekstensi: `mbstring`, `json`, `zip`, `xml`, `gd`, `fileinfo`
- [Composer](https://getcomposer.org/)

Cek cepat:
```bash
php -v
php -m | grep -E "mbstring|json|zip|xml|gd|fileinfo"
composer -V
```

### Langkah

```bash
# 1. Clone / masuk ke folder proyek
cd RemindEDU

# 2. Install dependency
composer install

# 3. Siapkan storage (jika belum ada)
mkdir -p storage/data storage/logs storage/ratelimit storage/tmp
for f in users categories tasks; do
  [ -f storage/data/$f.json ] || echo '{"schema_version":1,"items":[]}' > storage/data/$f.json
done

# 4. Atur izin (Linux/macOS)
chmod -R 750 storage
chmod 640 storage/data/*.json

# 5. Jalankan server development
php -S localhost:8000 -t public public/index.php
```

Buka **http://localhost:8000** lalu daftar akun baru.

> **Peringatan:** `php -S` hanya untuk **development**. Jangan dipakai di production.

Untuk instalasi bersih, salin `config/config.example.php` menjadi
`config/config.local.php`, lalu sesuaikan `app_env`, `app_url`, dan `data_path` bila perlu.
File `config.local.php` tidak boleh di-commit.

### Konfigurasi
Edit `config/config.php` (atau buat `config/config.local.php` yang tidak di-commit):

| Key | Default | Keterangan |
|---|---|---|
| `app_env` | `development` | Ganti ke `production` saat deploy |
| `timezone` | `Asia/Jakarta` | Zona waktu tampilan |
| `max_upload_bytes` | `2097152` | Batas upload import (2 MB) |
| `max_import_rows` | `1000` | Batas baris import |
| `per_page` | `10` | Jumlah tugas per halaman |

---

## Deploy (Apache / Nginx)

**Aturan utama:** arahkan document root **hanya ke `public/`**.

**Apache (VirtualHost):**
```apache
<VirtualHost *:80>
    ServerName RemindEDU.local
    DocumentRoot /var/www/RemindEDU/public
    <Directory /var/www/RemindEDU/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx:** lihat contoh lengkap di [`SECURITY-STANDARDS.md` → SEC-02](./SECURITY-STANDARDS.md#sec-02--defense-in-depth-blokir-akses-langsung-wajib).

Contoh konfigurasi minimum Nginx (document root tetap hanya `public/`):

```nginx
server {
    listen 80;
    server_name RemindEDU.local;
    root /var/www/RemindEDU/public;
    index index.php;

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        if ($uri != "/index.php") { return 404; }
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\. { deny all; return 404; }
    location ~* \.(json|log|ini|env|bak|sql|md|lock)$ {
        deny all;
        return 404;
    }
}
```

Setelah deploy, **uji** bahwa URL berikut menghasilkan 403/404:
```
/storage/data/users.json
/config/config.php
/app/bootstrap.php
```

### Permission dan backup

Jalankan sebagai user web server pada Linux:

```bash
find storage -type d -exec chmod 750 {} +
find storage/data -type f -name '*.json' -exec chmod 640 {} +
```

Backup sebelum pemeliharaan:

```bash
./scripts/backup.sh
```

Backup berisi salinan `storage/data/` dengan nama timestamp dan disimpan di luar
document root. Jangan mengunggah backup ke `public/`.

---

## Format Import

Header baris pertama harus **persis**:

| Judul | Deskripsi | Kategori | Prioritas | Status | Tenggat |
|---|---|---|---|---|---|
| Kerjakan laporan | Bab 1–3 | Kuliah | Tinggi | Proses | 2026-11-10 |

- Prioritas: `Rendah` / `Sedang` / `Tinggi`
- Status: `Belum` / `Proses` / `Selesai`
- Tenggat: `YYYY-MM-DD` (boleh kosong)
- Maks 2 MB dan 1000 baris. Unduh template di halaman **Import**.

---

## Keamanan

Aplikasi mengikuti [`SECURITY-STANDARDS.md`](./SECURITY-STANDARDS.md), antara lain:
- Hashing password `password_hash()` (Argon2id / bcrypt)
- Escape output (`htmlspecialchars`) + CSP ketat
- Token CSRF di semua request mutasi
- File JSON di luar web root + `Require all denied`
- Validasi upload berlapis & pencegahan formula injection saat export

---

## Rencana Pengembangan

| Milestone | Fokus | File |
|---|---|---|
| M0 | Setup & fondasi | [`planner/M0.md`](./planner/M0.md) |
| M1 | Autentikasi | [`planner/M1.md`](./planner/M1.md) |
| M2 | Kategori & custom dropdown | [`planner/M2.md`](./planner/M2.md) |
| M3 | CRUD tugas | [`planner/M3.md`](./planner/M3.md) |
| M4 | Import & export | [`planner/M4.md`](./planner/M4.md) |
| M5 | Hardening, QA & rilis | [`planner/M5.md`](./planner/M5.md) |

> **Untuk AI Agent / kontributor:** sebelum mengerjakan milestone apa pun, **wajib baca [`SPEC.md`](./SPEC.md) dan [`SECURITY-STANDARDS.md`](./SECURITY-STANDARDS.md)** terlebih dahulu.

---

## Batasan

- Storage file-based cocok untuk skala kecil (± ≤ 1.000 user / ≤ 10.000 tugas).
- Tidak dirancang untuk multi-server (locking bersifat lokal).
- Jika data membesar, migrasikan ke database (SQLite/MySQL).

Import dibatasi 2 MB dan 1.000 baris. Export PDF dibatasi 250 tugas per file
agar proses Dompdf tetap memiliki batas memori yang aman.
Penyimpanan JSON memakai lock lokal sehingga tidak cocok untuk multi-server atau
volume jaringan tanpa koordinasi lock.

## Rilis v1.0.0

Versi rilis yang diusulkan: `v1.0.0`. Sebelum membuat tag, jalankan:

```bash
composer install --no-dev --prefer-dist
composer audit
```

Lalu buat annotated tag pada commit rilis yang sudah direview:

```bash
git tag -a v1.0.0 -m "Release v1.0.0"
```

---

## Troubleshooting

| Masalah | Solusi |
|---|---|
| Blank page / error 500 | Cek `storage/logs/app.log` |
| `Class "ZipArchive" not found` | Aktifkan ekstensi `zip` di `php.ini` |
| Gagal menulis data | Pastikan `storage/` bisa ditulis oleh user web server |
| Export PDF karakter aneh | Pastikan ekstensi `mbstring` aktif |
| Upload ditolak | Cek `upload_max_filesize` dan `post_max_size` di `php.ini` |

---

## Lisensi

Proyek ini dilisensikan di bawah [MIT License](LICENSE).
