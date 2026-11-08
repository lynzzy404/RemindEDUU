# Changelog

## Unreleased

### Changed

- Made the Neo-Brutalist layout fluid across mobile, tablet, and wide desktop viewports.
- Compressed summary cards and task cards on small screens while preserving task metadata and touch targets.
- Added responsive design tokens for spacing, typography, borders, shadows, and content width.
- Added live task search with collapsible advanced filters and a server-side GET fallback.

## [1.1.0] - 2026-11-08

### Added

- Responsive authenticated navigation with a fixed desktop sidebar and accessible mobile/tablet drawer.
- Keyboard focus trap, Escape/overlay close, scroll locking, reduced-motion support, and no-JavaScript navigation fallback.

### Changed

- Replaced the authenticated top navbar with the responsive sidebar shell while keeping guest and error pages on a simple header.

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
