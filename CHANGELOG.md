# Changelog

## [1.0.0] - 2026-11-08

### Added

- Session-based registration, login, logout, CSRF protection, rate limiting, and password hashing.
- Per-user categories with accessible custom dropdown and task CRUD.
- XLSX/PDF export and validated XLS/XLSX import with per-row error reporting.
- Security headers, JSON locking/atomic writes, ownership checks, and release backup scripts.

### Security

- Sensitive project paths return 404 through the public front controller.
- Exported spreadsheet values are explicit strings with formula-injection protection.
- Spreadsheet parser failures return a generic user-facing error without exposing internal details.

[1.0.0]: https://github.com/lynzzy404/RemindEDUU/releases/tag/v1.0.0
