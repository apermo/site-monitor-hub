<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Storage;

use Apermo\SiteMonitorHub\Model\Site;
use Apermo\SiteMonitorHub\Model\SiteReport;
use PDO;

/**
 * Repository for persisting and querying site data.
 */
class SiteRepository {

	/**
	 * Database instance.
	 *
	 * @var Database
	 */
	private Database $database;

	/**
	 * Constructor.
	 *
	 * @param Database $database Database connection.
	 */
	public function __construct( Database $database ) {
		$this->database = $database;
	}

	/**
	 * Return the underlying PDO connection.
	 *
	 * @return PDO
	 */
	public function pdo(): PDO {
		return $this->database->pdo();
	}

	/**
	 * Insert a new site record.
	 *
	 * @param string      $id        UUID.
	 * @param string      $siteUrl   Site URL.
	 * @param string      $tokenHash Argon2id token hash.
	 * @param string|null $label     Optional label.
	 *
	 * @return Site
	 */
	public function addSite( string $id, string $siteUrl, string $tokenHash, ?string $label = null ): Site {
		$timestamp = \gmdate( 'Y-m-d\TH:i:s\Z' );
		$stmt = $this->database->pdo()->prepare(
			'INSERT INTO sites (id, site_url, token_hash, label, created_at, updated_at)
			 VALUES (:id, :site_url, :token_hash, :label, :created_at, :updated_at)',
		);
		$stmt->execute(
			[
				':id' => $id,
				':site_url' => $siteUrl,
				':token_hash' => $tokenHash,
				':label' => $label,
				':created_at' => $timestamp,
				':updated_at' => $timestamp,
			],
		);

		return new Site( $id, $siteUrl, $tokenHash, $label, $timestamp, $timestamp );
	}

	/**
	 * Find a site by its ID.
	 *
	 * @param string $id Site UUID.
	 *
	 * @return Site|null
	 */
	public function findSiteById( string $id ): ?Site {
		$stmt = $this->database->pdo()->prepare( 'SELECT * FROM sites WHERE id = :id' );
		$stmt->execute( [ ':id' => $id ] );
		$row = $stmt->fetch();

		return $row ? Site::fromRow( $row ) : null;
	}

	/**
	 * Find a site by its URL.
	 *
	 * @param string $url Site URL.
	 *
	 * @return Site|null
	 */
	public function findSiteByUrl( string $url ): ?Site {
		$stmt = $this->database->pdo()->prepare( 'SELECT * FROM sites WHERE site_url = :url' );
		$stmt->execute( [ ':url' => $url ] );
		$row = $stmt->fetch();

		return $row ? Site::fromRow( $row ) : null;
	}

	/**
	 * Get all sites.
	 *
	 * @return array<int, Site>
	 */
	public function getAllSites(): array {
		$stmt = $this->database->pdo()->query( 'SELECT * FROM sites ORDER BY site_url' );
		$rows = $stmt->fetchAll();

		return \array_map( [ Site::class, 'fromRow' ], $rows );
	}

	/**
	 * Upsert a report and its related data.
	 *
	 * @param string     $siteId Site UUID.
	 * @param SiteReport $report Incoming report DTO.
	 *
	 * @return void
	 */
	public function upsertReport( string $siteId, SiteReport $report ): void {
		$timestamp = \gmdate( 'Y-m-d\TH:i:s\Z' );

		$this->upsertReportRow( $siteId, $report, $timestamp );
		$this->touchSiteTimestamp( $siteId, $timestamp );
		$this->upsertPlugins( $siteId, $report->plugins, $timestamp );
		$this->upsertThemes( $siteId, $report->themes, $timestamp );
		$this->upsertCustomFields( $siteId, $report->customFields );
		$this->upsertUsers( $siteId, $report->users );
		$this->upsertRoles( $siteId, $report->roles );
	}

	/**
	 * Get the report for a site.
	 *
	 * @param string $siteId Site UUID.
	 *
	 * @return array<string, mixed>|null
	 */
	public function getReport( string $siteId ): ?array {
		$stmt = $this->database->pdo()->prepare( 'SELECT * FROM reports WHERE site_id = :site_id' );
		$stmt->execute( [ ':site_id' => $siteId ] );

		$row = $stmt->fetch();
		return $row ?: null;
	}

	/**
	 * Get plugins for a site.
	 *
	 * @param string $siteId Site UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getPlugins( string $siteId ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM site_plugins WHERE site_id = :site_id ORDER BY slug',
		);
		$stmt->execute( [ ':site_id' => $siteId ] );

		return $stmt->fetchAll();
	}

	/**
	 * Get themes for a site.
	 *
	 * @param string $siteId Site UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getThemes( string $siteId ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM site_themes WHERE site_id = :site_id ORDER BY slug',
		);
		$stmt->execute( [ ':site_id' => $siteId ] );

		return $stmt->fetchAll();
	}

	/**
	 * Get custom fields for a site.
	 *
	 * @param string $siteId Site UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getCustomFields( string $siteId ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM site_custom_fields WHERE site_id = :site_id ORDER BY key',
		);
		$stmt->execute( [ ':site_id' => $siteId ] );

		return $stmt->fetchAll();
	}

	/**
	 * Get users for a site.
	 *
	 * @param string $siteId Site UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getUsers( string $siteId ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM site_users WHERE site_id = :site_id ORDER BY user_login',
		);
		$stmt->execute( [ ':site_id' => $siteId ] );

		return $stmt->fetchAll();
	}

	/**
	 * Get user meta for a specific user on a site.
	 *
	 * @param string $siteId    Site UUID.
	 * @param string $userLogin User login.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getUserMeta( string $siteId, string $userLogin ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM site_user_meta WHERE site_id = :site_id AND user_login = :user_login ORDER BY meta_key',
		);
		$stmt->execute(
			[
				':site_id' => $siteId,
				':user_login' => $userLogin,
			],
		);

		return $stmt->fetchAll();
	}

	/**
	 * Get roles for a site.
	 *
	 * @param string $siteId Site UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getRoles( string $siteId ): array {
		$stmt = $this->database->pdo()->prepare(
			'SELECT * FROM site_roles WHERE site_id = :site_id ORDER BY slug',
		);
		$stmt->execute( [ ':site_id' => $siteId ] );

		return $stmt->fetchAll();
	}

	/**
	 * Get all plugins across all sites, optionally filtered.
	 *
	 * @param string|null $slug     Filter by plugin slug.
	 * @param bool        $outdated Only plugins with pending updates.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAllPlugins( ?string $slug = null, bool $outdated = false ): array {
		$query = 'SELECT sp.*, s.site_url, s.label AS site_label'
			. ' FROM site_plugins sp JOIN sites s ON sp.site_id = s.id';
		$params = [];
		$where = [];

		if ( $slug !== null ) {
			$where[] = 'sp.slug = :slug';
			$params[':slug'] = $slug;
		}
		if ( $outdated ) {
			$where[] = 'sp.update_available IS NOT NULL';
		}
		if ( $where !== [] ) {
			$query .= ' WHERE ' . \implode( ' AND ', $where );
		}

		$query .= ' ORDER BY sp.slug, s.site_url';

		$stmt = $this->database->pdo()->prepare( $query );
		$stmt->execute( $params );

		return $stmt->fetchAll();
	}

	/**
	 * Get all themes across all sites, optionally filtered.
	 *
	 * @param string|null $slug     Filter by theme slug.
	 * @param bool        $outdated Only themes with pending updates.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getAllThemes( ?string $slug = null, bool $outdated = false ): array {
		$query = 'SELECT st.*, s.site_url, s.label AS site_label'
			. ' FROM site_themes st JOIN sites s ON st.site_id = s.id';
		$params = [];
		$where = [];

		if ( $slug !== null ) {
			$where[] = 'st.slug = :slug';
			$params[':slug'] = $slug;
		}
		if ( $outdated ) {
			$where[] = 'st.update_available IS NOT NULL';
		}
		if ( $where !== [] ) {
			$query .= ' WHERE ' . \implode( ' AND ', $where );
		}

		$query .= ' ORDER BY st.slug, s.site_url';

		$stmt = $this->database->pdo()->prepare( $query );
		$stmt->execute( $params );

		return $stmt->fetchAll();
	}

	/**
	 * Update the token hash for a site.
	 *
	 * @param string $siteId    Site UUID.
	 * @param string $tokenHash New argon2id hash.
	 *
	 * @return void
	 */
	public function updateSiteTokenHash( string $siteId, string $tokenHash ): void {
		$stmt = $this->database->pdo()->prepare(
			'UPDATE sites SET token_hash = :hash, updated_at = :now WHERE id = :id',
		);
		$stmt->execute(
			[
				':hash' => $tokenHash,
				':now' => \gmdate( 'Y-m-d\TH:i:s\Z' ),
				':id' => $siteId,
			],
		);
	}

	/**
	 * Add a client token.
	 *
	 * @param string      $tokenHash Argon2id hash.
	 * @param string|null $label     Optional label.
	 *
	 * @return int The new token ID.
	 */
	public function addClientToken( string $tokenHash, ?string $label = null ): int {
		$stmt = $this->database->pdo()->prepare(
			'INSERT INTO client_tokens (token_hash, label, created_at) VALUES (:hash, :label, :now)',
		);
		$stmt->execute(
			[
				':hash' => $tokenHash,
				':label' => $label,
				':now' => \gmdate( 'Y-m-d\TH:i:s\Z' ),
			],
		);

		return (int) $this->database->pdo()->lastInsertId();
	}

	/**
	 * Upsert the main report row.
	 *
	 * @param string     $siteId    Site UUID.
	 * @param SiteReport $report    Report DTO.
	 * @param string     $timestamp Current ISO 8601 timestamp.
	 *
	 * @return void
	 */
	private function upsertReportRow( string $siteId, SiteReport $report, string $timestamp ): void {
		$stmt = $this->database->pdo()->prepare(
			'INSERT INTO reports (site_id, received_at, schema_version, payload, wp_version, php_version, wp_update_available, wp_version_last_updated, last_updated)
			 VALUES (:site_id, :received_at, :schema_version, :payload, :wp_version, :php_version, :wp_update_available, :wp_version_last_updated, :last_updated)
			 ON CONFLICT(site_id) DO UPDATE SET
				received_at = :received_at,
				schema_version = :schema_version,
				payload = :payload,
				wp_version = :wp_version,
				php_version = :php_version,
				wp_update_available = :wp_update_available,
				wp_version_last_updated = :wp_version_last_updated,
				last_updated = :last_updated',
		);

		$payload = \json_encode(
			[
				'schema_version' => $report->schemaVersion,
				'timestamp' => $report->timestamp,
				'site_url' => $report->siteUrl,
				'environment' => $report->environment,
				'plugins' => $report->plugins,
				'themes' => $report->themes,
				'custom_fields' => $report->customFields,
				'users' => $report->users,
				'roles' => $report->roles,
			],
		);

		$stmt->execute(
			[
				':site_id' => $siteId,
				':received_at' => $timestamp,
				':schema_version' => $report->schemaVersion,
				':payload' => $payload,
				':wp_version' => $report->environment['wp_version'] ?? null,
				':php_version' => $report->environment['php_version'] ?? null,
				':wp_update_available' => $report->environment['wp_update_available'] ?? null,
				':wp_version_last_updated' => $timestamp,
				':last_updated' => $timestamp,
			],
		);
	}

	/**
	 * Update the site's updated_at timestamp.
	 *
	 * @param string $siteId    Site UUID.
	 * @param string $timestamp ISO 8601 timestamp.
	 *
	 * @return void
	 */
	private function touchSiteTimestamp( string $siteId, string $timestamp ): void {
		$stmt = $this->database->pdo()->prepare(
			'UPDATE sites SET updated_at = :timestamp WHERE id = :id',
		);
		$stmt->execute(
			[
				':timestamp' => $timestamp,
				':id' => $siteId,
			],
		);
	}

	/**
	 * Replace plugin records for a site.
	 *
	 * @param string                           $siteId    Site UUID.
	 * @param array<int, array<string, mixed>> $plugins   Plugin data from report.
	 * @param string                           $timestamp Current timestamp.
	 *
	 * @return void
	 */
	private function upsertPlugins( string $siteId, array $plugins, string $timestamp ): void {
		$conn = $this->database->pdo();

		$stmt = $conn->prepare( 'DELETE FROM site_plugins WHERE site_id = :site_id' );
		$stmt->execute( [ ':site_id' => $siteId ] );

		$stmt = $conn->prepare(
			'INSERT INTO site_plugins (site_id, slug, name, version, update_available, active, last_updated)
			 VALUES (:site_id, :slug, :name, :version, :update_available, :active, :last_updated)',
		);

		foreach ( $plugins as $plugin ) {
			$stmt->execute(
				[
					':site_id' => $siteId,
					':slug' => $plugin['slug'] ?? '',
					':name' => $plugin['name'] ?? '',
					':version' => $plugin['version'] ?? '',
					':update_available' => $plugin['update_available'] ?? null,
					':active' => (int) ( $plugin['active'] ?? 1 ),
					':last_updated' => $timestamp,
				],
			);
		}
	}

	/**
	 * Replace theme records for a site.
	 *
	 * @param string                           $siteId    Site UUID.
	 * @param array<int, array<string, mixed>> $themes    Theme data from report.
	 * @param string                           $timestamp Current timestamp.
	 *
	 * @return void
	 */
	private function upsertThemes( string $siteId, array $themes, string $timestamp ): void {
		$conn = $this->database->pdo();

		$stmt = $conn->prepare( 'DELETE FROM site_themes WHERE site_id = :site_id' );
		$stmt->execute( [ ':site_id' => $siteId ] );

		$stmt = $conn->prepare(
			'INSERT INTO site_themes (site_id, slug, name, version, update_available, active, last_updated)
			 VALUES (:site_id, :slug, :name, :version, :update_available, :active, :last_updated)',
		);

		foreach ( $themes as $theme ) {
			$stmt->execute(
				[
					':site_id' => $siteId,
					':slug' => $theme['slug'] ?? '',
					':name' => $theme['name'] ?? '',
					':version' => $theme['version'] ?? '',
					':update_available' => $theme['update_available'] ?? null,
					':active' => (int) ( $theme['active'] ?? 0 ),
					':last_updated' => $timestamp,
				],
			);
		}
	}

	/**
	 * Replace custom fields for a site.
	 *
	 * @param string                           $siteId       Site UUID.
	 * @param array<int, array<string, mixed>> $customFields Custom field data.
	 *
	 * @return void
	 */
	private function upsertCustomFields( string $siteId, array $customFields ): void {
		$conn = $this->database->pdo();

		$stmt = $conn->prepare( 'DELETE FROM site_custom_fields WHERE site_id = :site_id' );
		$stmt->execute( [ ':site_id' => $siteId ] );

		$stmt = $conn->prepare(
			'INSERT INTO site_custom_fields (site_id, key, label, value, status)
			 VALUES (:site_id, :key, :label, :value, :status)',
		);

		foreach ( $customFields as $field ) {
			$stmt->execute(
				[
					':site_id' => $siteId,
					':key' => $field['key'] ?? '',
					':label' => $field['label'] ?? '',
					':value' => $field['value'] ?? '',
					':status' => $field['status'] ?? null,
				],
			);
		}
	}

	/**
	 * Replace user records for a site.
	 *
	 * @param string                           $siteId Site UUID.
	 * @param array<int, array<string, mixed>> $users  User data from report.
	 *
	 * @return void
	 */
	private function upsertUsers( string $siteId, array $users ): void {
		$conn = $this->database->pdo();

		$stmt = $conn->prepare( 'DELETE FROM site_user_meta WHERE site_id = :site_id' );
		$stmt->execute( [ ':site_id' => $siteId ] );

		$stmt = $conn->prepare( 'DELETE FROM site_users WHERE site_id = :site_id' );
		$stmt->execute( [ ':site_id' => $siteId ] );

		$userStmt = $conn->prepare(
			'INSERT INTO site_users (site_id, user_login, display_name, email, role)
			 VALUES (:site_id, :user_login, :display_name, :email, :role)',
		);

		$metaStmt = $conn->prepare(
			'INSERT INTO site_user_meta (site_id, user_login, meta_key, meta_value)
			 VALUES (:site_id, :user_login, :meta_key, :meta_value)',
		);

		foreach ( $users as $user ) {
			$userStmt->execute(
				[
					':site_id' => $siteId,
					':user_login' => $user['user_login'] ?? '',
					':display_name' => $user['display_name'] ?? '',
					':email' => $user['email'] ?? '',
					':role' => $user['role'] ?? '',
				],
			);

			$meta = $user['meta'] ?? [];
			foreach ( $meta as $key => $value ) {
				$metaStmt->execute(
					[
						':site_id' => $siteId,
						':user_login' => $user['user_login'] ?? '',
						':meta_key' => $key,
						':meta_value' => \is_string( $value ) ? $value : \json_encode( $value ),
					],
				);
			}
		}
	}

	/**
	 * Replace role records for a site.
	 *
	 * @param string                           $siteId Site UUID.
	 * @param array<int, array<string, mixed>> $roles  Role data from report.
	 *
	 * @return void
	 */
	private function upsertRoles( string $siteId, array $roles ): void {
		$conn = $this->database->pdo();

		$stmt = $conn->prepare( 'DELETE FROM site_roles WHERE site_id = :site_id' );
		$stmt->execute( [ ':site_id' => $siteId ] );

		$stmt = $conn->prepare(
			'INSERT INTO site_roles (site_id, slug, name, is_custom, is_modified, capabilities)
			 VALUES (:site_id, :slug, :name, :is_custom, :is_modified, :capabilities)',
		);

		foreach ( $roles as $role ) {
			$capabilities = $role['capabilities'] ?? [];
			$stmt->execute(
				[
					':site_id' => $siteId,
					':slug' => $role['slug'] ?? '',
					':name' => $role['name'] ?? '',
					':is_custom' => (int) ( $role['is_custom'] ?? 0 ),
					':is_modified' => (int) ( $role['is_modified'] ?? 0 ),
					':capabilities' => \is_string( $capabilities ) ? $capabilities : \json_encode( $capabilities ),
				],
			);
		}
	}
}
