# Site Bookkeeper Hub

A monitoring tool for your WordPress Sites — Central API

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](LICENSE)

Standalone PHP/SQLite API that receives, stores, and serves WordPress site health data. Sites push their
status via `POST /report`, and clients (dashboards, apps) read aggregated data through authenticated GET
endpoints. Supports multisite networks as first-class entities.

## Requirements

- PHP 8.1+
- SQLite 3 (PDO)
- Composer

## Installation

```bash
git clone git@github.com:apermo/site-bookkeeper-hub.git
cd site-bookkeeper-hub
composer install
cp .env.example .env
```

Point your web server (nginx, Caddy, Apache) at `public/index.php` as the single entry point.

## Configuration

Edit `.env` to customize:

| Variable | Default | Description |
|---|---|---|
| `DATABASE_PATH` | `./data/monitor.sqlite` | Path to the SQLite database file |
| `STALE_THRESHOLD_HOURS` | `48` | Hours before a site is marked as stale |

## Site Management

```bash
# Register a single site (outputs a one-time bearer token)
php bin/manage.php site:add https://example.tld --label="My Site"

# List all registered sites
php bin/manage.php site:list

# Rotate a site's token
php bin/manage.php site:rotate-token https://example.tld

# Create a read-only client token (for dashboards / apps)
php bin/manage.php client:add --label="dashboard"
```

## Network Management (Multisite)

```bash
# Register a multisite network (one token for all subsites)
php bin/manage.php network:add https://network.example.tld --label="My Network"

# List all networks
php bin/manage.php network:list

# Rotate a network token
php bin/manage.php network:rotate-token https://network.example.tld
```

Subsites auto-register on their first report when using a network token.

## API Endpoints

### Site Reporting (Bearer: site or network token)

| Method | Path | Description |
|---|---|---|
| POST | `/report` | Push a site health report (upsert) |
| POST | `/network-report` | Push a network-level report (multisite) |

### Reading Data (Bearer: client token)

| Method | Path | Description |
|---|---|---|
| GET | `/sites` | List all sites with summary data |
| GET | `/sites/{id}` | Full report for a single site |
| GET | `/plugins` | Cross-site plugin overview |
| GET | `/themes` | Cross-site theme overview |
| GET | `/networks` | List all multisite networks |
| GET | `/networks/{id}` | Full network detail with subsites |

Optional query parameters on `/plugins` and `/themes`: `?slug=<slug>`, `?outdated=true`.

## Development

```bash
composer test        # Run PHPUnit tests
composer cs          # Check coding standards
composer cs:fix      # Auto-fix coding standards
```

### DDEV

```bash
ddev start
```

The hub is available at `https://site-bookkeeper-hub.ddev.site`.

## License

[GPL-2.0-or-later](LICENSE)
