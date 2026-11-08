# RemindEDU Security Audit — M5

Tanggal audit: 2026-11-06  
Basis: `SECURITY-STANDARDS.md` §11, `SPEC.md`, planner M5

## Checklist

| Item | Status | Bukti |
|---|---|---|
| SEC-07 sensitive URL probe | PASS | `/storage/...`, `/config/...`, `/app/...`, `/vendor/...`, `/composer.json`, and `/SPEC.md` returned 404. |
| Output escaping and XSS | PASS | Static scan found no unescaped user output; XSS payloads were literal in HTML/PDF. |
| Dangerous functions | PASS | No `innerHTML`, `eval`, `unserialize`, or `extract` in application/public code. |
| POST CSRF and GET mutation | PASS | POST controllers call CSRF validation; mutation routes are POST-only. |
| User ownership / IDOR | PASS | Repository queries filter `user_id`; two-user IDOR tests returned 404. |
| Response headers | PASS | CSP, `X-Content-Type-Options`, frame protection, Referrer-Policy, and Permissions-Policy observed; HSTS observed with HTTPS production config. |
| Cookie and session fixation | PASS | Login regenerated the session ID; production cookie had `Secure`, `HttpOnly`, and `SameSite=Lax`. |
| Rate limiting | PASS | Login, registration, and import probes returned 429 after the configured threshold. |
| Upload validation | PASS | PHP upload, renamed fake spreadsheet, and oversized file were rejected with 422; temporary files were removed. |
| Composer audit | PASS | `composer audit --no-interaction`: no security vulnerability advisories found. |
| Sensitive logging | PASS | Security log review found no password, token, or password-hash values. |
| File permissions and ignore rules | PASS | Runtime permissions and `.gitignore` were reviewed; README documents Linux `0750` directories and `0640` JSON files. |
| Corrupt JSON resilience | PASS | Corrupt `users.json` returned a generic 500, checksum stayed unchanged, and the valid baseline was restored afterward. |

## Functional QA

- End-to-end auth, category, task, export, import, delete, and logout flows passed in the existing M1–M4 regression suites.
- Unicode and emoji survived JSON, HTML, and XLSX paths.
- A 5,000-task dashboard request measured approximately 47–50 ms locally.
- PDF export is intentionally capped at 250 rows. A 5,000-task export completed in 67,499 bytes and included the truncation notice; the former 1,000-row cap exhausted the 512 MB local PHP memory limit.
- A MIME-valid corrupt spreadsheet returned HTTP 422 with a generic processing message and no internal path leakage.
- Clean-install copy completed `composer install` and loaded the home page with HTTP 200.
- Responsive browser checks passed at 360, 768, and 1280 px in the available Chromium environment. Firefox, Safari, and Edge were not installed in this environment and were not claimed as executed.

## Changes applied

- Added production-oriented `config/config.example.php` and documented Apache/Nginx, permissions, backup, file-storage limitations, and release procedure.
- Added backup scripts for PowerShell and POSIX shells.
- Added `CHANGELOG.md` for the proposed v1.0.0 release.
- Added external application JavaScript loading and responsive import/export UI states.
- Reduced PDF rendering cap to prevent memory exhaustion and updated the truncation notice.
- Generalized spreadsheet parser failures so internal parser paths/details are not exposed to users.

## Residual risks

| Severity | Risk | Mitigation / follow-up |
|---|---|---|
| MEDIUM | Dompdf's bundled DejaVu font does not cover every CJK/emoji glyph. | HTML/XLSX retain Unicode; use a licensed wider font before relying on PDF Unicode completeness. |
| MEDIUM | JSON storage is not suitable for multi-instance/serverless production persistence or very large datasets. | Use a shared persistent filesystem for v1.0; migrate to a transactional database as scale grows. |
| LOW | Cross-file category creation precedes the task write during import, so an exceptional task-write failure could leave an unused category. | Keep import backups and add a coordinated storage transaction when the storage layer is expanded. |
| LOW | Full four-browser matrix and PHP latest-version run were unavailable locally. | Run these in CI or release staging before broad deployment. |

No High or Critical findings remain open.
