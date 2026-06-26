# Changelog

## 1.1.0 - 2026-06-26

### Added
- A `Manage broken links` user permission, now required to view the Control Panel page and run scans
- Foreign key from broken-link rows to their entry, so records are cleared automatically when an entry is deleted

### Fixed
- SSRF hardening: outbound link checks now refuse requests that resolve to private, loopback, link-local, or reserved IP ranges (the site's own hosts are still allowed), and redirects are validated per hop
- Scan completion is now tracked with an atomic per-scan batch counter, so scans with multiple batches complete reliably under concurrent queue workers instead of finishing early
- Broken-link totals are incremented atomically to prevent lost updates across concurrent workers
- Link extraction now uses a real HTML parser (`DOMDocument`) instead of a regex, so single-quoted, unquoted, and otherwise non-standard `href`s are no longer missed
- Clearing data while a scan is in progress is now blocked, preventing a running job from resurrecting a deleted scan record
- JSON export no longer fails silently on malformed UTF-8 scraped from third-party pages

### Security
- Control Panel and export endpoints now require the `Manage broken links` permission
- External links in the results table and widget render with `rel="noopener noreferrer"` and only as links when using an `http(s)` scheme

## 1.0.0 - 2025-05-22

### Added
- Initial release
- Background queue-based scanning with configurable batch size
- Incremental scans (only re-scans entries updated since the last scan)
- Force full scan option
- Dashboard widget showing broken link summary
- CSV and JSON export
- Console commands (`scan`, `status`, `clear-data`)
