# Project: Site Monitor Hub

## Repository
- **GitHub**: apermo/site-monitor-hub

## Overview
Standalone PHP API that receives, stores, and serves WordPress site health data. No framework — plain PHP with
SQLite storage.

## Tech Stack
- PHP 8.1+ with `declare(strict_types=1)` everywhere
- SQLite via PDO
- Composer for autoloading and dependencies
- PHPUnit 11 for tests
- PHPCS with `apermo/apermo-coding-standards`

## Project Structure
- `public/index.php` — Single web entry point
- `src/` — Application code (PSR-4 namespace `Apermo\SiteMonitorHub`)
- `bin/manage.php` — CLI management tool
- `schema/report-payload.json` — JSON Schema for report validation
- `tests/` — PHPUnit tests
- `data/` — SQLite database (gitignored except `.gitkeep`)

## Code Style
- PSR-4 autoloading
- Coding standards enforced via `composer cs` (PHPCS with Apermo ruleset)
- All PHP files must start with `declare(strict_types=1)`

## Testing
- `composer test` runs PHPUnit
- TDD workflow: write test first, verify red, write code, verify green, commit

## Issue Tracking
- GitHub Issues on apermo/site-monitor-hub

## Example Domains
- Always use `.tld` TLD in examples and tests (e.g. `https://example.tld`)
