# SPEC.md — RemindEDU

> Spesifikasi teknis resmi proyek **RemindEDU**. Dokumen ini adalah *single source of truth*.
> Jika ada konflik antara planner dan SPEC, **SPEC.md menang**. Aturan keamanan ada di [`SECURITY-STANDARDS.md`](./SECURITY-STANDARDS.md) dan berlaku mengikat.

| Item | Nilai |
|---|---|
| Nama proyek | RemindEDU |
| Versi spec | 1.1.0 |
| Bahasa UI | Indonesia |
| Tech stack | PHP Native (>= 8.1), tanpa framework |
| Storage | File JSON lokal (tanpa MySQL/SQLite) |
| UI/UX | Neu Brutalism |
| Dependency | Composer: `phpoffice/phpspreadsheet`, `dompdf/dompdf` |

---

## 1. Tujuan & Ruang Lingkup

**Tujuan:** aplikasi manajemen tugas sederhana multi-user, ringan, dan bisa jalan tanpa database.

**In scope**
- Register, login, logout (session-based).
- CRUD tugas milik user sendiri.
- Kategori kustom per user + *custom dropdown* kategori (komponen UI buatan sendiri, bukan `<select>` bawaan).
- Export tugas ke XLSX dan PDF.
- Import tugas dari XLS/XLSX.
- Filter, pencarian, dan sorting sederhana.

**Out of scope (v1)**
- Reset password via email, OAuth, 2FA.
- Kolaborasi/berbagi tugas antar user.
- API publik/REST, notifikasi, aplikasi mobile native.

---

## 2. Arsitektur

### 2.1 Pola
- **Front Controller**: semua request masuk lewat `public/index.php`.
- **Layering sederhana** (MVC ringan):
  `Router → Controller → Service/Repository → JsonStore → file JSON`
- **Document root hanya `public/`**. Folder `app/`, `config/`, `storage/`, `vendor/` berada di luar web root.

### 2.2 Alur Request

```
Browser → public/index.php → bootstrap (session, headers, autoload)
        → Router::dispatch()
        → Middleware (auth, csrf)
        → Controller → Service → Repository → JsonStore (flock + atomic write)
        → View (escape output) → Response
```

### 2.3 Struktur Folder (Worktree)

```
RemindEDU/
├── public/                      # <-- DOCUMENT ROOT (satu-satunya yang diakses web)
│   ├── index.php                # front controller
│   ├── .htaccess                # rewrite ke index.php (Apache)
│   └── assets/
│       ├── css/
│       │   └── app.css          # design tokens + komponen neu brutalism
│       ├── js/
│       │   ├── dropdown.js      # custom dropdown kategori
│       │   ├── sidebar.js      # drawer, focus trap, responsive state
│       │   └── app.js           # konfirmasi hapus, toast, dll.
│       └── img/
├── app/
│   ├── bootstrap.php            # autoload, session, security headers, error handler
│   ├── Core/
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── View.php             # render + helper e() untuk escape
│   │   ├── Session.php          # session hardening + flash message
│   │   ├── Csrf.php
│   │   ├── Auth.php             # login state, current user
│   │   ├── Validator.php
│   │   ├── RateLimiter.php
│   │   └── JsonStore.php        # baca/tulis JSON aman (flock + atomic)
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── TaskController.php
│   │   ├── CategoryController.php
│   │   ├── ImportController.php
│   │   └── ExportController.php
│   ├── Repositories/
│   │   ├── UserRepository.php
│   │   ├── CategoryRepository.php
│   │   └── TaskRepository.php
│   ├── Services/
│   │   ├── ImportService.php    # parse XLS/XLSX → validasi → simpan
│   │   ├── ExportXlsxService.php
│   │   └── ExportPdfService.php
│   └── Views/
│       ├── layouts/main.php
│       ├── partials/            # sidebar, flash, dropdown, pagination
│       │   ├── sidebar.php      # navigasi user: desktop sidebar + mobile drawer
│       ├── auth/{login,register}.php
│       ├── tasks/{index,form}.php
│       ├── categories/index.php
│       ├── import/index.php
│       ├── export/pdf.php       # template HTML untuk Dompdf
│       └── errors/{404,403,500}.php
├── config/
│   └── config.php               # konstanta & setting (tanpa secret di git)
├── storage/                     # DI LUAR web root
│   ├── .htaccess                # Require all denied (defense in depth)
│   ├── data/
│   │   ├── .htaccess            # Require all denied
│   │   ├── index.html           # kosong
│   │   ├── users.json
│   │   ├── categories.json
│   │   └── tasks.json
│   ├── logs/                    # app.log, security.log
│   ├── ratelimit/               # counter percobaan login
│   └── tmp/                     # file upload sementara (dihapus setelah proses)
├── vendor/                      # hasil composer install
├── tests/                       # test manual/otomatis sederhana
├── planner/
│   ├── M0.md  M1.md  M2.md  M3.md  M4.md  M5.md
├── composer.json
├── .gitignore                   # abaikan storage/data/*.json, logs, vendor
├── README.md
├── SPEC.md
└── SECURITY-STANDARDS.md
```

> **Aturan emas:** `storage/` dan `vendor/` **tidak boleh** berada di dalam `public/`.

---

## 3. Skema Data JSON

### 3.1 Konvensi Umum
- Encoding **UTF-8**, pretty print untuk kemudahan debug, `JSON_THROW_ON_ERROR`.
- Setiap file memakai *envelope*:

```json
{
  "schema_version": 1,
  "items": []
}
```

- **ID**: string acak, format `{prefix}_{16 hex}` dari `bin2hex(random_bytes(8))`.
  Prefix: `usr_`, `cat_`, `tsk_`. **Bukan** auto-increment (tidak bisa ditebak).
- **Timestamp**: ISO 8601 UTC, contoh `2026-11-03T08:30:00Z`.
- **Tanggal tenggat** (`due_date`): `YYYY-MM-DD` atau `null`.
- Relasi dilakukan manual lewat `user_id` dan `category_id`.
- **Isolasi data**: setiap query WAJIB difilter `user_id` milik user yang login.

### 3.2 `users.json`

```json
{
  "schema_version": 1,
  "items": [
    {
      "id": "usr_a1b2c3d4e5f60718",
      "name": "Budi Santoso",
      "email": "budi@example.com",
      "password_hash": "$argon2id$v=19$m=65536,t=4,p=1$...",
      "created_at": "2026-11-03T08:00:00Z",
      "updated_at": "2026-11-03T08:00:00Z",
      "last_login_at": "2026-11-03T09:15:00Z"
    }
  ]
}
```

| Field | Tipe | Wajib | Aturan |
|---|---|---|---|
| `id` | string | ya | `usr_` + 16 hex, unik |
| `name` | string | ya | 2–60 karakter, trim |
| `email` | string | ya | format email valid, lowercase, **unik** |
| `password_hash` | string | ya | hasil `password_hash()`, **tidak pernah** dikirim ke view/log |
| `created_at` / `updated_at` | string | ya | ISO 8601 UTC |
| `last_login_at` | string\|null | tidak | diperbarui saat login sukses |

Password: minimal 8 karakter, maksimal 72 byte (batas bcrypt) / 128 karakter.

### 3.3 `categories.json`

```json
{
  "schema_version": 1,
  "items": [
    {
      "id": "cat_0f1e2d3c4b5a6978",
      "user_id": "usr_a1b2c3d4e5f60718",
      "name": "Kuliah",
      "color": "#FFE14D",
      "created_at": "2026-11-03T08:00:00Z",
      "updated_at": "2026-11-03T08:00:00Z"
    }
  ]
}
```

| Field | Tipe | Wajib | Aturan |
|---|---|---|---|
| `id` | string | ya | `cat_` + 16 hex |
| `user_id` | string | ya | referensi `users.id` |
| `name` | string | ya | 1–30 karakter, **unik per user** (case-insensitive) |
| `color` | string | ya | whitelist palet (lihat §6.2), format `#RRGGBB` |
| `created_at` / `updated_at` | string | ya | ISO 8601 UTC |

**Seed saat register:** `Pribadi`, `Pekerjaan`, `Belajar`.
**Hapus kategori:** tugas di kategori tsb. dipindah ke `category_id: null` (tampil "Tanpa Kategori"). Tidak ada cascade delete.

### 3.4 `tasks.json`

```json
{
  "schema_version": 1,
  "items": [
    {
      "id": "tsk_9a8b7c6d5e4f3021",
      "user_id": "usr_a1b2c3d4e5f60718",
      "category_id": "cat_0f1e2d3c4b5a6978",
      "title": "Kerjakan laporan praktikum",
      "description": "Bab 1 sampai 3",
      "status": "in_progress",
      "priority": "high",
      "due_date": "2026-11-10",
      "created_at": "2026-11-03T08:30:00Z",
      "updated_at": "2026-11-03T09:00:00Z",
      "completed_at": null
    }
  ]
}
```

| Field | Tipe | Wajib | Aturan |
|---|---|---|---|
| `id` | string | ya | `tsk_` + 16 hex |
| `user_id` | string | ya | pemilik tugas |
| `category_id` | string\|null | tidak | harus milik user yang sama |
| `title` | string | ya | 1–120 karakter |
| `description` | string | tidak | maks 1000 karakter, plain text |
| `status` | enum | ya | `todo` \| `in_progress` \| `done` |
| `priority` | enum | ya | `low` \| `medium` \| `high` |
| `due_date` | string\|null | tidak | `YYYY-MM-DD`, valid kalender |
| `created_at` / `updated_at` | string | ya | ISO 8601 UTC |
| `completed_at` | string\|null | tidak | diisi saat status → `done`, di-null-kan jika kembali |

### 3.5 Relasi

```
users (1) ──< categories (N)
users (1) ──< tasks (N)
categories (1) ──< tasks (N)   [category_id nullable]
```

### 3.6 Aturan JsonStore (WAJIB)
1. **Locking**: `flock(LOCK_SH)` saat baca, `flock(LOCK_EX)` saat tulis.
2. **Atomic write**: tulis ke file temp di folder yang sama → `rename()` ke file target.
3. **Read-modify-write** dilakukan dalam satu lock (tidak boleh baca, lepas lock, lalu tulis).
4. File rusak / JSON invalid → log error, tampilkan halaman 500 generik, **jangan** menimpa file.
5. Backup sederhana: salin ke `storage/data/backup/` sebelum operasi import massal.

---

## 4. Routing & Fitur

| Method | Path | Auth | Deskripsi |
|---|---|---|---|
| GET | `/` | – | Redirect ke `/tasks` (login) atau `/login` |
| GET/POST | `/register` | guest | Form & proses registrasi |
| GET/POST | `/login` | guest | Form & proses login |
| POST | `/logout` | user | Logout (wajib CSRF) |
| GET | `/tasks` | user | Daftar tugas + filter `?q=&status=&category=&priority=&sort=&page=` |
| GET | `/tasks/create` | user | Form tambah |
| POST | `/tasks` | user | Simpan tugas baru |
| GET | `/tasks/{id}/edit` | user | Form edit |
| POST | `/tasks/{id}/update` | user | Update tugas |
| POST | `/tasks/{id}/toggle` | user | Toggle selesai/belum |
| POST | `/tasks/{id}/delete` | user | Hapus tugas |
| GET | `/categories` | user | Kelola kategori |
| POST | `/categories` | user | Tambah kategori |
| POST | `/categories/{id}/update` | user | Ubah kategori |
| POST | `/categories/{id}/delete` | user | Hapus kategori |
| GET | `/export/xlsx` | user | Unduh tugas (.xlsx), mengikuti filter aktif |
| GET | `/export/pdf` | user | Unduh tugas (.pdf), mengikuti filter aktif |
| GET | `/import` | user | Halaman import |
| GET | `/import/template` | user | Unduh template .xlsx |
| POST | `/import` | user | Upload & proses import |

> Semua aksi mengubah data **wajib POST + token CSRF**. Tidak ada aksi mutasi via GET.

### 4.1 Custom Dropdown Kategori
- Komponen buatan sendiri: `<button>` pemicu + `<ul role="listbox">` + `<input type="hidden" name="category_id">`.
- Menampilkan **chip warna** + nama kategori; opsi pertama "Tanpa Kategori".
- Aksesibilitas: `aria-haspopup="listbox"`, `aria-expanded`, `aria-activedescendant`; navigasi keyboard (↑ ↓ Enter Esc Tab, type-ahead).
- **Progressive enhancement**: `<select>` native tetap dirender di dalam `<noscript>` / sebagai fallback.
- Nilai yang dikirim tetap divalidasi server (ID harus milik user).
- Dipakai di: form tugas dan filter daftar tugas.

### 4.2 Import (XLS/XLSX)
- Format file: `.xls` atau `.xlsx`, maks **2 MB**, maks **1000 baris**.
- Header kolom (baris 1) **persis**: `Judul | Deskripsi | Kategori | Prioritas | Status | Tenggat`.
- Pemetaan nilai: Prioritas `Rendah/Sedang/Tinggi`, Status `Belum/Proses/Selesai`, Tenggat `YYYY-MM-DD`.
- Kategori yang belum ada akan **dibuat otomatis** (maks 20 kategori baru per import).
- Validasi per baris; hasil: ringkasan *berhasil / dilewati* + daftar error per baris. Baris valid tetap diimpor.
- Hanya dibaca sebagai **data** (nilai sel). Formula tidak dieksekusi, makro diabaikan.

### 4.3 Export
- **XLSX**: kolom sama dengan format import (supaya bisa round-trip), header bold, auto-width.
  Sel yang diawali `=`, `+`, `-`, `@` di-prefix `'` / dipaksa tipe string (cegah formula injection).
- **PDF**: Dompdf dari template HTML, judul, nama user, tanggal cetak, tabel tugas, orientasi landscape.
  `isRemoteEnabled = false`, `isPhpEnabled = false`.
- Nama file: `RemindEDU-YYYYMMDD-HHMM.xlsx|pdf`. Header `Content-Disposition: attachment`.

---

## 5. Validasi Ringkas

| Input | Aturan |
|---|---|
| email | `filter_var(FILTER_VALIDATE_EMAIL)`, lowercase, maks 254 |
| name | 2–60, tanpa tag HTML |
| password | 8–128 karakter; `password_confirm` harus sama saat register |
| task.title | wajib, 1–120 |
| task.description | maks 1000 |
| task.status / priority | whitelist enum |
| task.due_date | regex + `checkdate()` |
| category.name | 1–30, unik per user |
| category.color | whitelist palet |
| `{id}` di URL | regex `^(usr|cat|tsk)_[a-f0-9]{16}$` |
| page / per_page | integer, batas 1–100 |

Validasi **selalu server-side**; validasi di JS hanya untuk UX.

---

## 6. Desain UI/UX — Neu Brutalism

### 6.1 Prinsip
- Border tebal hitam, **hard shadow** tanpa blur, sudut tajam (atau radius kecil maks 6px).
- Warna cerah & kontras tinggi di atas latar krem.
- Tipografi tebal, ukuran besar, huruf kapital untuk label/tombol.
- Interaksi terasa fisik: hover mengangkat, active menekan (shadow mengecil).
- Kontras teks minimal **WCAG AA**. Fokus keyboard harus terlihat jelas.

### 6.2 Design Tokens (`app.css`)

```css
:root {
  --bg:        #FFF8E7;  /* krem */
  --ink:       #000000;  /* teks & border */
  --paper:     #FFFFFF;
  --yellow:    #FFE14D;
  --pink:      #FF6B9D;
  --blue:      #4D9DFF;
  --green:     #5EEAA0;
  --orange:    #FF9F43;
  --purple:    #B794F6;
  --danger:    #FF4D4D;

  --border:    3px solid var(--ink);
  --shadow:    4px 4px 0 var(--ink);
  --shadow-lg: 8px 8px 0 var(--ink);
  --radius:    0px;

  --font: "Space Grotesk", ui-sans-serif, system-ui, "Segoe UI", Arial, sans-serif;
}
```

Palet kategori yang diizinkan (`color`): `#FFE14D #FF6B9D #4D9DFF #5EEAA0 #FF9F43 #B794F6`.
Font **self-hosted** atau system font (tidak memuat dari CDN pihak ketiga, selaras dengan CSP).

### 6.2a Token skala responsif
Selain token warna dan komponen di atas, `app.css` menyediakan token ukuran responsif berikut: `--space-1` sampai `--space-6`, `--text-body`, `--text-card-title`, `--text-summary-value`, `--border-width`, `--shadow-size`, `--shadow-lg-size`, `--content-max`, dan `--content-gutter`. Nilainya menggunakan `clamp()`/unit relatif agar spacing, tipografi, border, shadow, dan lebar konten beradaptasi dari 320px sampai layar lebar. Komponen wajib memakai token ini, bukan ukuran desktop tetap yang diulang.

Target layout: pada <=480px `summary-grid` memakai kartu ringkas dalam 2–3 kolom tanpa overflow (3 kolom bila lebar minimum memungkinkan), `task-grid` satu kolom; pada 481–1023px grid memakai 2 kolom; pada >=1024px grid memakai 3 kolom dan konten dibatasi `--content-max`. Seluruh halaman tidak boleh memiliki scroll horizontal; wrapper tabel boleh scroll secara lokal.

### 6.3 Komponen
| Komponen | Spesifikasi |
|---|---|
| Button | border 3px, shadow 4px, hover `translate(-2px,-2px)` + shadow 6px, active `translate(4px,4px)` + shadow 0 |
| Card tugas | background putih, border + shadow, strip warna kategori di kiri |
| Input | border 3px, fokus: background kuning + outline hitam |
| Badge status | `todo`=kuning, `in_progress`=biru, `done`=hijau |
| Badge prioritas | `low`=hijau, `medium`=oranye, `high`=pink/merah |
| Flash/Toast | kotak berborder tebal, warna sesuai tipe (sukses/error/info) |
| Dropdown | panel berborder + shadow, opsi hover kuning, opsi aktif tebal |
| Modal konfirmasi hapus | overlay solid semi-transparan, kartu putih berborder |

### 6.3a Kepadatan responsif
Summary card pada layar <=480px memiliki tinggi target sekitar 64–88px dengan nilai tetap terlihat. Task card memakai padding responsif dan deskripsi boleh dibatasi dua baris dengan line clamp hanya untuk presentasi; status, prioritas, kategori, dan tenggat tetap dirender. Kontrol interaktif tetap memiliki target minimal 44x44px. `prefers-reduced-motion` wajib dihormati.

### Sidebar Navigasi Responsif
- Hanya dirender untuk user yang sudah login. Guest dan halaman error memakai header sederhana tanpa sidebar.
- Urutan menu: logo `RemindEDU`, Tugas (`/tasks`), Kategori (`/categories`), Import (`/import`), Export XLSX (`/export/xlsx`), Export PDF (`/export/pdf`). Area bawah berisi nama user ter-escape dan form Logout `POST` dengan token CSRF.
- Path aktif dihitung server-side. Item aktif memakai latar `--yellow`, hard shadow, dan `aria-current="page"`.
- Pada desktop (`min-width: 1024px`), sidebar fixed/sticky di kiri selebar 260px, tinggi viewport, border kanan 3px, dan menu dapat di-scroll.
- Di bawah 1024px, top bar menampilkan logo dan tombol hamburger. Drawer muncul dari kiri dengan overlay; tombol hamburger memakai `aria-label`, `aria-expanded`, dan `aria-controls`.
- Drawer dapat ditutup melalui tombol X, overlay, Escape, atau klik menu. Saat terbuka fokus masuk dan terperangkap di drawer; saat tertutup fokus kembali ke hamburger. Scroll body dikunci melalui class CSS, bukan inline style.
- JavaScript berada di file eksternal dan hanya progressive enhancement. Tanpa JavaScript, menu tetap tampil sebagai blok navigasi yang dapat digunakan.
- Target sentuh minimal 44px, nama panjang dipotong ellipsis, drawer mendukung scroll internal, resize ke desktop membersihkan state, dan `prefers-reduced-motion` meniadakan animasi geser.

### 6.4 Layout & Responsif
- Layout user memakai shell sidebar responsif dari §6.3; navbar atas lama tidak digunakan pada halaman privat.
- Desktop (`min-width: 1024px`): sidebar tetap di kiri selebar 260px; konten utama bergeser 260px dan memiliki area scroll sendiri.
- Mobile/tablet (`< 1024px`): sidebar tersembunyi, top bar berada di atas konten, dan navigasi dibuka sebagai drawer dengan overlay.
- Guest (`/login`, `/register`) dan halaman error memakai header sederhana tanpa sidebar.
- Grid 2–3 kolom kartu pada layar lebar dan 1 kolom pada layar sempit.
- Empty state: ilustrasi sederhana + CTA "Tambah Tugas Pertama".
- Modal konfirmasi, custom dropdown kategori, flash message, dan filter bar harus tetap berada di atas konten/drawer sesuai konteksnya tanpa tertimpa overlay secara tidak sengaja.

---

## 7. Konfigurasi (`config/config.php`)

| Key | Contoh | Catatan |
|---|---|---|
| `app_env` | `production` / `development` | `display_errors` hanya aktif di development |
| `app_url` | `http://localhost:8000` | untuk cookie & redirect |
| `data_path` | `__DIR__.'/../storage/data'` | di luar web root |
| `session_name` | `RemindEDU_SID` | |
| `session_lifetime` | `7200` | detik |
| `max_upload_bytes` | `2097152` | 2 MB |
| `max_import_rows` | `1000` | |
| `login_max_attempts` | `5` | per 15 menit per IP+email |
| `per_page` | `10` | |
| `timezone` | `Asia/Jakarta` | tampilan; data tetap UTC |

Secret/konfigurasi sensitif jika ada → `config/config.local.php` (di-`.gitignore`).

---

## 8. Non-Functional Requirements
- **Kompatibilitas:** PHP >= 8.1, ekstensi `mbstring`, `json`, `zip`, `xml`, `gd`, `fileinfo`.
- **Performa:** nyaman untuk ≤ 10.000 tugas total / ≤ 1.000 user (batasan nyata file-based; di atas itu migrasi ke DB).
- **Skalabilitas:** tidak dirancang multi-server (file lock lokal).
- **Maintainability:** `declare(strict_types=1)`, PSR-12, PSR-4 autoload (`App\` → `app/`).
- **Aksesibilitas:** label form, `alt`, fokus terlihat, ARIA pada dropdown.
- **Browser:** versi stabil terbaru Chrome, Firefox, Safari, Edge.

---

## 9. Roadmap & Milestone

| Milestone | File | Fokus | Output utama |
|---|---|---|---|
| M0 | [`planner/M0.md`](./planner/M0.md) | Setup & fondasi | Struktur folder, bootstrap, router, JsonStore, design system dasar |
| M1 | [`planner/M1.md`](./planner/M1.md) | Auth | Register, login, logout, session, CSRF, rate limit |
| M2 | [`planner/M2.md`](./planner/M2.md) | Kategori | CRUD kategori + custom dropdown |
| M3 | [`planner/M3.md`](./planner/M3.md) | Tugas | CRUD tugas, filter, search, sort, pagination |
| M4 | [`planner/M4.md`](./planner/M4.md) | Import/Export | XLSX, PDF, import XLS/XLSX |
| M5 | [`planner/M5.md`](./planner/M5.md) | Hardening & rilis | Audit security, QA, polish UI, dokumentasi final |

Urutan eksekusi **wajib berurutan** (M0 → M5). Tiap milestone memiliki *Definition of Done* sendiri.

---

## 10. Definition of Done (Global)
- [ ] Sesuai SPEC ini dan lolos checklist `SECURITY-STANDARDS.md`.
- [ ] Tidak ada PHP warning/notice/deprecation di log.
- [ ] Semua output ke HTML di-escape; semua mutasi dilindungi CSRF.
- [ ] File `storage/data/*.json` tidak bisa diakses via URL (diuji manual).
- [ ] Tampilan responsif & konsisten dengan design tokens.
- [ ] Fitur diuji manual dengan skenario di file milestone terkait.

---

## 11. Glosarium
- **Front Controller**: satu titik masuk untuk semua request.
- **Atomic write**: menulis ke file temp lalu `rename()` agar tidak ada file setengah-tertulis.
- **Hard shadow**: bayangan tanpa blur (`box-shadow: 4px 4px 0 #000`).
- **CSRF**: serangan yang memaksa browser user yang sedang login mengirim request tak diinginkan.
