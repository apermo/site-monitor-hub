<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Storage;

use PDO;
use RuntimeException;
use Throwable;

/**
 * SQLite connection manager with schema migration.
 */
class Database {

	/**
	 * PDO connection instance.
	 *
	 * @var PDO
	 */
	private PDO $connection;

	/**
	 * Open or create the SQLite database at the given path.
	 *
	 * @param string $path Filesystem path to the SQLite file.
	 *
	 * @throws RuntimeException When the parent directory cannot be created.
	 */
	public function __construct( string $path ) {
		$directory = \dirname( $path );
		if ( ! \is_dir( $directory ) && ! \mkdir( $directory, 0755, true ) ) {
			throw new RuntimeException( "Cannot create directory: {$directory}" );
		}

		$this->connection = new PDO( "sqlite:{$path}" );
		$this->connection->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->connection->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
		$this->connection->exec( 'PRAGMA journal_mode=WAL' );
		$this->connection->exec( 'PRAGMA foreign_keys=ON' );
	}

	/**
	 * Return the underlying PDO connection.
	 *
	 * @return PDO
	 */
	public function pdo(): PDO {
		return $this->connection;
	}

	/**
	 * Run all schema migrations.
	 *
	 * @throws Throwable When a migration step fails.
	 */
	public function migrate(): void {
		$this->connection->exec( 'PRAGMA foreign_keys=OFF' );
		$this->connection->beginTransaction();

		try {
			$this->createSitesTable();
			$this->createReportsTable();
			$this->createSitePluginsTable();
			$this->createSiteThemesTable();
			$this->createSiteCustomFieldsTable();
			$this->createClientTokensTable();
			$this->createSiteUsersTable();
			$this->createSiteUserMetaTable();
			$this->createSiteRolesTable();
			$this->createNetworksTable();
			$this->createNetworkReportsTable();
			$this->createNetworkPluginsTable();
			$this->createNetworkUsersTable();
			$this->createVulnerabilitiesTable();
			$this->createVulnerabilitySyncTable();
			$this->migrateSitesAddNetworkId();
			$this->migrateSitePluginsAddNetworkActive();

			$this->connection->commit();
		} catch ( Throwable $exception ) {
			$this->connection->rollBack();
			throw $exception;
		}

		$this->connection->exec( 'PRAGMA foreign_keys=ON' );
	}

	/**
	 * Create the sites table.
	 *
	 * @return void
	 */
	private function createSitesTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS sites (
				id TEXT PRIMARY KEY,
				site_url TEXT NOT NULL UNIQUE,
				token_hash TEXT NOT NULL,
				label TEXT,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)',
		);
	}

	/**
	 * Create the reports table.
	 *
	 * @return void
	 */
	private function createReportsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS reports (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				site_id TEXT NOT NULL REFERENCES sites(id),
				received_at TEXT NOT NULL,
				schema_version INTEGER NOT NULL,
				payload JSON NOT NULL,
				wp_version TEXT,
				php_version TEXT,
				wp_update_available TEXT,
				wp_version_last_updated TEXT,
				last_updated TEXT,
				UNIQUE(site_id)
			)',
		);
	}

	/**
	 * Create the site_plugins table.
	 *
	 * @return void
	 */
	private function createSitePluginsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_plugins (
				site_id TEXT NOT NULL REFERENCES sites(id),
				slug TEXT NOT NULL,
				name TEXT NOT NULL,
				version TEXT NOT NULL,
				update_available TEXT,
				active INTEGER NOT NULL DEFAULT 1,
				last_updated TEXT NOT NULL,
				PRIMARY KEY (site_id, slug)
			)',
		);
	}

	/**
	 * Create the site_themes table.
	 *
	 * @return void
	 */
	private function createSiteThemesTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_themes (
				site_id TEXT NOT NULL REFERENCES sites(id),
				slug TEXT NOT NULL,
				name TEXT NOT NULL,
				version TEXT NOT NULL,
				update_available TEXT,
				active INTEGER NOT NULL DEFAULT 0,
				last_updated TEXT NOT NULL,
				PRIMARY KEY (site_id, slug)
			)',
		);
	}

	/**
	 * Create the site_custom_fields table.
	 *
	 * @return void
	 */
	private function createSiteCustomFieldsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_custom_fields (
				site_id TEXT NOT NULL REFERENCES sites(id),
				key TEXT NOT NULL,
				label TEXT NOT NULL,
				value TEXT NOT NULL,
				status TEXT,
				PRIMARY KEY (site_id, key)
			)',
		);
	}

	/**
	 * Create the client_tokens table.
	 *
	 * @return void
	 */
	private function createClientTokensTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS client_tokens (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				token_hash TEXT NOT NULL,
				label TEXT,
				created_at TEXT NOT NULL
			)',
		);
	}

	/**
	 * Create the site_users table.
	 *
	 * @return void
	 */
	private function createSiteUsersTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_users (
				site_id TEXT NOT NULL REFERENCES sites(id),
				user_login TEXT NOT NULL,
				display_name TEXT NOT NULL,
				email TEXT NOT NULL,
				role TEXT NOT NULL,
				PRIMARY KEY (site_id, user_login)
			)',
		);
	}

	/**
	 * Create the site_user_meta table.
	 *
	 * @return void
	 */
	private function createSiteUserMetaTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_user_meta (
				site_id TEXT NOT NULL REFERENCES sites(id),
				user_login TEXT NOT NULL,
				meta_key TEXT NOT NULL,
				meta_value TEXT NOT NULL,
				PRIMARY KEY (site_id, user_login, meta_key)
			)',
		);
	}

	/**
	 * Create the site_roles table.
	 *
	 * @return void
	 */
	private function createSiteRolesTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS site_roles (
				site_id TEXT NOT NULL REFERENCES sites(id),
				slug TEXT NOT NULL,
				name TEXT NOT NULL,
				is_custom INTEGER NOT NULL DEFAULT 0,
				is_modified INTEGER NOT NULL DEFAULT 0,
				capabilities TEXT NOT NULL,
				PRIMARY KEY (site_id, slug)
			)',
		);
	}

	/**
	 * Create the networks table.
	 *
	 * @return void
	 */
	private function createNetworksTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS networks (
				id TEXT PRIMARY KEY,
				main_site_url TEXT NOT NULL UNIQUE,
				token_hash TEXT NOT NULL,
				label TEXT,
				created_at TEXT NOT NULL,
				updated_at TEXT NOT NULL
			)',
		);
	}

	/**
	 * Create the network_reports table.
	 *
	 * @return void
	 */
	private function createNetworkReportsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS network_reports (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				network_id TEXT NOT NULL REFERENCES networks(id),
				received_at TEXT NOT NULL,
				schema_version INTEGER NOT NULL,
				payload JSON NOT NULL,
				subsite_count INTEGER,
				last_updated TEXT,
				UNIQUE(network_id)
			)',
		);
	}

	/**
	 * Create the network_plugins table.
	 *
	 * @return void
	 */
	private function createNetworkPluginsTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS network_plugins (
				network_id TEXT NOT NULL REFERENCES networks(id),
				slug TEXT NOT NULL,
				name TEXT NOT NULL,
				version TEXT NOT NULL,
				update_available TEXT,
				last_updated TEXT NOT NULL,
				PRIMARY KEY (network_id, slug)
			)',
		);
	}

	/**
	 * Create the network_users table.
	 *
	 * @return void
	 */
	private function createNetworkUsersTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS network_users (
				network_id TEXT NOT NULL REFERENCES networks(id),
				user_login TEXT NOT NULL,
				display_name TEXT NOT NULL,
				email TEXT NOT NULL,
				PRIMARY KEY (network_id, user_login)
			)',
		);
	}

	/**
	 * Create the vulnerabilities table for cached provider data.
	 *
	 * @return void
	 */
	private function createVulnerabilitiesTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS vulnerabilities (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				provider TEXT NOT NULL,
				type TEXT NOT NULL,
				slug TEXT NOT NULL,
				external_id TEXT NOT NULL,
				title TEXT NOT NULL,
				from_version TEXT,
				to_version TEXT,
				from_inclusive INTEGER NOT NULL DEFAULT 1,
				to_inclusive INTEGER NOT NULL DEFAULT 1,
				fixed_in TEXT,
				cvss_score REAL,
				cve TEXT,
				synced_at TEXT NOT NULL,
				UNIQUE(provider, external_id, slug)
			)',
		);
	}

	/**
	 * Create the vulnerability sync tracking table.
	 *
	 * @return void
	 */
	private function createVulnerabilitySyncTable(): void {
		$this->connection->exec(
			'CREATE TABLE IF NOT EXISTS vulnerability_sync (
				provider TEXT PRIMARY KEY,
				last_sync_at TEXT NOT NULL
			)',
		);
	}

	/**
	 * Add network_id column to sites table.
	 *
	 * @return void
	 */
	private function migrateSitesAddNetworkId(): void {
		try {
			$this->connection->exec(
				'ALTER TABLE sites ADD COLUMN network_id TEXT REFERENCES networks(id)',
			);
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Expected when column already exists.
		} catch ( Throwable $exception ) {
			// Column already exists — safe to ignore.
		}
	}

	/**
	 * Add network_active column to site_plugins table.
	 *
	 * @return void
	 */
	private function migrateSitePluginsAddNetworkActive(): void {
		try {
			$this->connection->exec(
				'ALTER TABLE site_plugins ADD COLUMN network_active INTEGER NOT NULL DEFAULT 0',
			);
			// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Expected when column already exists.
		} catch ( Throwable $exception ) {
			// Column already exists — safe to ignore.
		}
	}
}
