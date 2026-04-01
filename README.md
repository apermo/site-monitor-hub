# Site Monitor Hub

Standalone PHP API that receives, stores, and serves WordPress site health data. Sites push their status via
`POST /report`, and clients (dashboards, apps) read aggregated data through authenticated GET endpoints.

## Requirements

- PHP 8.1+
- SQLite 3 (PDO)
- Composer

## Installation

```bash
git clone git@github.com:apermo/site-monitor-hub.git
cd site-monitor-hub
composer install
cp .env.example .env
```

## Configuration

Edit `.env` to customize:

| Variable               | Default                  | Description                            |
|------------------------|--------------------------|----------------------------------------|
| `DATABASE_PATH`        | `./data/monitor.sqlite`  | Path to the SQLite database file       |
| `STALE_THRESHOLD_HOURS`| `48`                     | Hours before a site is marked as stale |

## Usage

### Register a site

```bash
php bin/manage.php site:add https://example.tld --label="My Site"
```

The command outputs a one-time bearer token. Configure the remote site to push reports using this token.

### Add a client token

```bash
php bin/manage.php client:add --label="macos-app"
```

### Point your web server at `public/`

The single entry point is `public/index.php`. Configure your web server (Apache, nginx, Caddy) to route all
requests to this file.

## API Endpoints

| Method | Path           | Auth         | Description                     |
|--------|----------------|--------------|---------------------------------|
| POST   | `/report`      | Site token   | Push a site health report       |
| GET    | `/sites`       | Client token | List all sites (summary)        |
| GET    | `/sites/{id}`  | Client token | Full report for a single site   |
| GET    | `/plugins`     | Client token | Cross-site plugin overview      |
| GET    | `/themes`      | Client token | Cross-site theme overview       |

## Development

```bash
composer test        # Run PHPUnit tests
composer cs          # Check coding standards
composer cs:fix      # Auto-fix coding standards
```

## License

MIT
