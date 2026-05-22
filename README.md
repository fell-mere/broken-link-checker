# Broken Links

A Craft CMS plugin that scans your site for broken links by crawling each entry's rendered page and checking every outbound link with a HEAD request. Results are stored in the database and viewable in the Control Panel.

## Requirements

- Craft CMS 5.5.0 or later
- PHP 8.2 or later

## Features

- **Background scanning** — scans run as Craft queue jobs, so large sites won't time out
- **Incremental scans** — by default only re-scans entries updated since the last completed scan; force a full scan when needed
- **Configurable batch size** — control how many entries are processed per queue job
- **Dashboard widget** — shows a summary of broken links on the Craft dashboard
- **Export** — download results as CSV or JSON
- **Console command** — trigger scans from the CLI (useful for cron jobs)

## Installation

#### With Composer

```bash
cd /path/to/my-project

composer require craig-clement/craft-brokenlinks

./craft plugin/install brokenlinks
```

## Usage

### Control Panel

Navigate to **Broken Links** in the CP sidebar.

| Button | Description |
|--------|-------------|
| Start New Scan | Scans only entries updated since the last completed scan |
| Force Full Scan | Scans all entries regardless of last updated date |
| Advanced Options | Set a custom batch size (default: 100 entries per job) |
| View Queue | Opens the Craft queue manager to monitor progress |
| Clear All Data | Deletes all stored broken link records and scan history |

Results can be exported as **CSV** or **JSON** from the results table.

### Console

```bash
# Run an incremental scan (only updated entries)
./craft broken-links/scan

# Force a full scan of all entries
./craft broken-links/scan --force-full-scan

# Set a custom batch size
./craft broken-links/scan --batch-size=50

# Scan a specific URL
./craft broken-links/scan --base-url=https://example.com

# Wait for the scan to complete before exiting (useful in CI)
./craft broken-links/scan --wait

# Check the status of the latest scan
./craft broken-links/status

# Check the status of a specific scan by ID
./craft broken-links/status 42

# Clear all stored data
./craft broken-links/clear-data
```

### Dashboard Widget

Add the **Broken Links** widget to your Craft dashboard. It shows the count of broken links found in the last scan and links directly to the full results. Configure the number of links shown via the widget settings.

## How It Works

1. A **GenerateSitemapJob** fetches all matching entry IDs and splits them into batches.
2. Each batch becomes a **CheckBrokenLinksJob** that:
   - Fetches the rendered HTML of each entry's public URL
   - Extracts all `<a href>` links
   - Sends a HEAD request to each link
   - Saves any link returning HTTP 400+ or that is unreachable
3. Results accumulate in the database across batches; the scan is marked complete when the last batch finishes.

## Notes

- Only `http://` and `https://` links are checked; `mailto:`, `tel:`, anchor, and relative links are skipped.
- Each HTTP request has a 5-second timeout. Unreachable hosts (connection refused, DNS failure, etc.) are recorded separately from HTTP error responses.
- The field column in results is not yet populated — it will show which CMS field contains the broken link in a future release.
