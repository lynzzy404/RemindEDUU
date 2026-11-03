# SECURITY-STANDARDS.md — RemindEDU

> Standar keamanan **wajib** untuk aplikasi PHP Native berbasis file JSON.
> Setiap aturan punya ID (`SEC-xx`) agar bisa dirujuk di planner, code review, dan commit message.
> Kata **WAJIB** = tidak boleh dilanggar. **DISARANKAN** = lakukan kecuali ada alasan kuat.

Dokumen terkait: [`SPEC.md`](./SPEC.md)

---

## 0. Prinsip Dasar
1. **Never trust input** — semua data dari user, file upload, header, cookie, dan file JSON dianggap tidak tepercaya.
2. **Escape on output** — validasi saat masuk, escape saat keluar sesuai konteks (HTML, atribut, JS, URL).
3. **Least privilege** — user hanya boleh mengakses data miliknya sendiri.
4. **Defense in depth** — satu lapisan gagal, lapisan lain tetap melindungi.
5. **Fail safely** — error tidak boleh membocorkan path, stack trace, atau isi data.
6. **Secure by default** — default menolak, izinkan secara eksplisit.

---

## 1. Proteksi File Data JSON (PRIORITAS TERTINGGI)

Karena storage berupa file, kebocoran file = kebocoran seluruh database (termasuk hash password).

### SEC-01 — `storage/` berada di luar document root (WAJIB)
- Document root server **hanya** `public/`.
- `storage/`, `app/`, `config/`, `vendor/` **tidak boleh** bisa diakses lewat URL.

### SEC-02 — Defense in depth: blokir akses langsung (WAJIB)

**Apache** — `storage/.htaccess` dan `storage/data/.htaccess`:
```apache
# Apache 2.4+
Require all denied

# Apache 2.2 (fallback)
<IfModule !mod_authz_core.c>
  Order allow,deny
  Deny from all
</IfModule>
```

**Nginx** — hanya arahkan `root` ke `public/` dan tolak file sensitif:
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
        # hanya front controller yang boleh dieksekusi
        if ($uri != "/index.php") { return 404; }
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    # tolak dotfile & ekstensi data/config/log
    location ~ /\.            { deny all; return 404; }
    location ~* \.(json|log|ini|env|bak|sql|md|lock)$ { deny all; return 404; }
}
```

Tambahkan `storage/data/index.html` kosong agar directory listing tidak menampilkan apa pun.

### SEC-03 — Nama & lokasi file tidak bisa ditebak, tidak ada path dari input (WAJIB)
- Nama file data **hardcoded** di konfigurasi (`users.json`, dst.). **Dilarang** membentuk path file dari input user.
- Jika perlu file per-user/temp, gunakan nama dari `random_bytes()`.

### SEC-04 — Izin file/folder (WAJIB)
```
folder storage/*  : 0750   (atau 0700)
file   *.json     : 0640   (atau 0600)
owner             : user web server (www-data / apache)
```
Jangan pernah `chmod 777`.

### SEC-05 — Penulisan data aman (WAJIB)
- `flock()` + **atomic write** (temp file → `rename()`), lihat SPEC §3.6.
- Gunakan `JSON_THROW_ON_ERROR`; jangan menimpa file jika decode gagal.
- Jangan simpan data di file berekstensi `.php`/`.inc` yang bisa dieksekusi dari web.

### SEC-06 — Git & backup (WAJIB)
`.gitignore` minimal:
```
/vendor/
/storage/data/*.json
/storage/data/backup/
/storage/logs/*
/storage/tmp/*
/storage/ratelimit/*
/config/config.local.php
.env
```
Backup disimpan di luar web root, jangan pernah berekstensi yang bisa diunduh (mis. `users.json.bak` di folder publik).

### SEC-07 — Verifikasi (WAJIB, tiap milestone yang menyentuh storage)
Uji manual, semuanya harus **403/404**:
```
GET /storage/data/users.json
GET /../storage/data/users.json
GET /storage/data/
GET /config/config.php
GET /app/bootstrap.php
```

---

## 2. Autentikasi & Password

### SEC-10 — Hashing password dengan `password_hash()` (WAJIB)
```php
// Simpan
$hash = password_hash($password, PASSWORD_ARGON2ID);
// fallback jika Argon2 tidak tersedia
// $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verifikasi
if (password_verify($input, $user['password_hash'])) {
    if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
        // hash ulang & simpan
    }
}
```
**Dilarang:** `md5`, `sha1`, `sha256` polos, enkripsi reversibel, atau menyimpan password plaintext. **Jangan** membuat salt manual.

### SEC-11 — Kebijakan password (WAJIB)
- Minimal 8 karakter, maksimal 128. Jangan memotong/mengubah password (tanpa `trim`).
- DISARANKAN: tolak password umum (daftar 100 password teratas).

### SEC-12 — Login yang tidak membocorkan informasi (WAJIB)
- Pesan error generik: *"Email atau password salah."* (jangan beda antara email tidak ada vs password salah).
- Hindari *timing attack* enumerasi user: tetap panggil `password_verify()` dengan hash dummy bila email tidak ditemukan.
- Pada register, pesan "email sudah terdaftar" boleh ditampilkan (trade-off UX) namun tetap kena rate limit.

### SEC-13 — Rate limiting & brute force (WAJIB)
- Maks **5 percobaan gagal / 15 menit** per kombinasi IP + email; lewat batas → HTTP 429 + jeda.
- Counter disimpan di `storage/ratelimit/` (hash dari identitas sebagai nama file).
- Terapkan juga ke `/register` dan `/import`.

### SEC-14 — Session hardening (WAJIB)
```php
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('TUGASKU_SID');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,     // true di production (HTTPS)
    'httponly' => true,
    'samesite' => 'Lax',        // atau 'Strict'
]);
session_start();
```
- Panggil `session_regenerate_id(true)` **setelah login sukses** dan saat privilege berubah.
- Logout: kosongkan `$_SESSION`, hapus cookie, `session_destroy()`.
- Timeout idle (mis. 2 jam) dan absolute lifetime.
- Jangan simpan `password_hash` atau data sensitif lain di session; cukup `user_id`.

---

## 3. Otorisasi & Isolasi Data

### SEC-20 — Cek kepemilikan di setiap akses (WAJIB)
Setiap operasi baca/ubah/hapus tugas dan kategori harus memastikan `item.user_id === $currentUserId`.
```php
$task = $tasks->findById($id);
if (!$task || $task['user_id'] !== Auth::id()) {
    Response::notFound();   // 404 agar tidak membocorkan keberadaan data (anti-IDOR)
}
```
- `category_id` pada tugas harus milik user yang sama.
- Jangan percaya `user_id` dari form/hidden input; selalu ambil dari session.

### SEC-21 — Semua route privat lewat middleware auth (WAJIB)
Default-deny: route baru otomatis butuh login kecuali ditandai `guest`/`public`.

---

## 4. Validasi & Sanitasi Input

### SEC-30 — Validasi server-side dengan whitelist (WAJIB)
- Tipe, panjang, format, dan rentang divalidasi. Enum (`status`, `priority`, `color`) memakai **whitelist**.
- ID di URL divalidasi regex `^(usr|cat|tsk)_[a-f0-9]{16}$`.
- Gunakan `filter_var()`, `ctype_*`, `checkdate()`, `mb_strlen()`.

### SEC-31 — Normalisasi input (WAJIB)
```php
function clean(string $v): string {
    $v = trim($v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v); // buang control char
    return $v;
}
```
- Pastikan UTF-8 valid (`mb_check_encoding`). Tolak input yang tidak valid.
- **Jangan** mengandalkan `strip_tags()`/`addslashes()` sebagai pengaman utama. Simpan data apa adanya (bersih dari control char), **escape saat output**.

### SEC-32 — Hindari fungsi berbahaya (WAJIB)
Dilarang memakai input user pada: `eval`, `exec`, `system`, `shell_exec`, `passthru`, `proc_open`, `include/require` dinamis, `unserialize()` (gunakan JSON), `extract($_POST)`, `$$var`.

---

## 5. Pencegahan XSS

### SEC-40 — Escape semua output (WAJIB)
```php
function e(?string $v): string {
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}
```
```php
<h3><?= e($task['title']) ?></h3>
<input name="title" value="<?= e($old['title'] ?? '') ?>">
```
- **Tidak ada** `echo $var` mentah di view.
- Atribut selalu dalam tanda kutip.
- Data untuk JavaScript: `json_encode($data, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)` pada atribut `data-*`, bukan string-concat di `<script>`.
- URL dari user: validasi skema (`http`/`https` saja); tolak `javascript:`.
- Di JS gunakan `textContent`, bukan `innerHTML`, untuk data user.

### SEC-41 — Content Security Policy & security headers (WAJIB)
Dikirim dari `bootstrap.php` untuk setiap response:
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// Production + HTTPS:
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
```
- **Tanpa** inline `<script>`/`onclick=""`/inline style; semua JS & CSS di file terpisah (agar CSP ketat bisa dipakai).
- Tidak memuat CDN pihak ketiga; font & library self-hosted.
- `header_remove('X-Powered-By')`, set `expose_php = Off`.

---

## 6. CSRF

### SEC-50 — Token CSRF pada semua request mutasi (WAJIB)
```php
// Generate (per session)
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

// Form: <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">

// Verifikasi
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
    Response::forbidden();
}
```
- Berlaku untuk **semua** POST termasuk **login, register, logout, hapus, toggle, import**.
- Aksi yang mengubah data **dilarang** via GET.
- Cookie `SameSite=Lax/Strict` sebagai lapisan tambahan.

---

## 7. Keamanan Upload & Import/Export

### SEC-60 — Validasi file upload (WAJIB)
- Cek `$_FILES['file']['error'] === UPLOAD_ERR_OK` dan `is_uploaded_file()`.
- Batas ukuran (2 MB) dicek di server, bukan hanya `accept=` di HTML.
- Whitelist ekstensi `xls`, `xlsx` **dan** verifikasi MIME asli via `finfo_file()` (jangan percaya `$_FILES['type']`).
- Nama file asli **tidak dipakai**; simpan sementara sebagai `random_bytes` hex + ekstensi hasil whitelist di `storage/tmp/`, lalu **hapus** di blok `finally`.
- Jangan pernah menyimpan upload di dalam `public/`.
- Pindahkan dengan `move_uploaded_file()`.

### SEC-61 — Parsing spreadsheet aman (WAJIB)
- Gunakan `PhpSpreadsheet` dengan `setReadDataOnly(true)`; formula **tidak** dievaluasi. Cegah XXE (library modern sudah menonaktifkan entitas eksternal; pastikan versi terbaru).
- Batasi jumlah baris (1000) dan kolom; hentikan jika melebihi.
- Setiap sel divalidasi seperti input form (SEC-30/31). Batasi panjang string.
- Batasi jumlah kategori baru per import.
- Atur `memory_limit`/`max_execution_time` wajar; tangkap exception dan tampilkan pesan generik.

### SEC-62 — Cegah Formula/CSV Injection saat export (WAJIB)
Sel teks yang diawali `=`, `+`, `-`, `@`, tab, atau CR harus diperlakukan sebagai string:
```php
function safeCell(string $v): string {
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}
// atau: $sheet->setCellValueExplicit($cell, $v, DataType::TYPE_STRING);
```

### SEC-63 — PDF aman (WAJIB)
- Dompdf: `isRemoteEnabled=false`, `isPhpEnabled=false`, `chroot` ke folder aman.
- Semua data di template PDF di-escape dengan `e()`.
- Kirim dengan header `Content-Type: application/pdf`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`.
- Export hanya memuat data milik user yang login (SEC-20).

### SEC-64 — Download header aman (WAJIB)
Nama file download dibuat server (`tugasku-YYYYMMDD-HHMM.ext`), bukan dari input user (cegah header injection).

---

## 8. Error Handling & Logging

### SEC-70 — Jangan bocorkan detail error (WAJIB)
Production:
```php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage/logs/app.log');
error_reporting(E_ALL);
```
- Tampilkan halaman 404/403/500 generik. Tidak ada stack trace, path, atau isi JSON ke user.
- `set_exception_handler` + `set_error_handler` global di bootstrap.

### SEC-71 — Logging keamanan (WAJIB)
Catat ke `storage/logs/security.log`: login gagal/sukses, lockout rate limit, CSRF gagal, akses ditolak (IDOR), upload ditolak.
- Format: waktu UTC, event, user_id (jika ada), IP, user agent ringkas.
- **Jangan** log password, token, hash, atau isi session.
- Sanitasi newline pada data log (cegah log injection).

---

## 9. Konfigurasi Server & PHP

### SEC-80 — `php.ini` production (WAJIB)
```ini
expose_php = Off
display_errors = Off
allow_url_fopen = Off
allow_url_include = Off
file_uploads = On
upload_max_filesize = 2M
post_max_size = 3M
session.cookie_httponly = 1
session.use_strict_mode = 1
```

### SEC-81 — Apache (`public/.htaccess`) (WAJIB)
```apache
Options -Indexes -MultiViews
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteRule ^ index.php [QSA,L]
</IfModule>
# Blokir dotfile & ekstensi sensitif
<FilesMatch "(^\.|\.(json|log|ini|env|bak|sql|lock|md)$)">
  Require all denied
</FilesMatch>
```

### SEC-82 — HTTPS (WAJIB di production)
- Paksa HTTPS + HSTS; cookie `Secure`.
- Jangan jalankan production dengan `php -S` (built-in server hanya untuk development).

---

## 10. Dependency & Supply Chain

### SEC-90 — Kelola dependency (WAJIB)
- Commit `composer.lock`. Jalankan `composer audit` secara berkala dan sebelum rilis.
- Hanya paket yang dibutuhkan (`phpoffice/phpspreadsheet`, `dompdf/dompdf`). Gunakan versi stabil, bukan `dev-master`.
- `composer install --no-dev --optimize-autoloader` untuk production.
- `vendor/` di luar web root.

---

## 11. Checklist Review (dipakai di akhir tiap milestone)

**Data & Storage**
- [ ] `storage/` di luar document root; `.htaccess`/nginx rule aktif (SEC-01/02)
- [ ] File JSON tidak bisa diakses via URL — sudah diuji (SEC-07)
- [ ] Izin file benar; tidak ada 777 (SEC-04)
- [ ] `flock` + atomic write dipakai (SEC-05)
- [ ] `.gitignore` mengabaikan data, log, vendor (SEC-06)

**Auth & Session**
- [ ] `password_hash()` / `password_verify()` (SEC-10)
- [ ] Pesan login generik + rate limit (SEC-12/13)
- [ ] Session cookie HttpOnly, SameSite, Secure(prod); regenerate setelah login (SEC-14)

**Akses & Input**
- [ ] Cek kepemilikan `user_id` di semua operasi (SEC-20)
- [ ] Validasi whitelist + normalisasi input (SEC-30/31)
- [ ] Tidak ada fungsi berbahaya / `unserialize` (SEC-32)

**Output & Browser**
- [ ] Semua output lewat `e()` (SEC-40)
- [ ] CSP & security headers aktif; tanpa inline script/style (SEC-41)
- [ ] Token CSRF di semua POST; tanpa mutasi via GET (SEC-50)

**Import/Export**
- [ ] Upload: ukuran, ekstensi, finfo, nama acak, dihapus setelah proses (SEC-60)
- [ ] Parsing read-only, batas baris, validasi per sel (SEC-61)
- [ ] Anti formula injection saat export (SEC-62)
- [ ] Dompdf remote & PHP dimatikan (SEC-63)

**Operasional**
- [ ] `display_errors=Off` di production; halaman error generik (SEC-70)
- [ ] Security log aktif tanpa data sensitif (SEC-71)
- [ ] `composer audit` bersih (SEC-90)

---

## 12. Prosedur Jika Ada Insiden
1. Putus akses (maintenance mode), amankan salinan `storage/` dan `logs/`.
2. Identifikasi cakupan dari `security.log`.
3. Jika `users.json` berpotensi bocor: paksa reset (invalidasi session, minta user ganti password).
4. Perbaiki akar masalah, tambahkan aturan/uji ke dokumen ini.
