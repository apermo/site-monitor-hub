<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Auth;

use Apermo\SiteMonitorHub\Model\Site;
use Apermo\SiteMonitorHub\Storage\Database;

/**
 * Validates bearer tokens for sites pushing reports.
 */
class TokenAuth {

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
	 * Extract the bearer token from the Authorization header.
	 *
	 * @return string|null The token, or null if missing/malformed.
	 */
	public static function extractBearerToken(): ?string {
		$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

		if ( \preg_match( '/^Bearer\s+(.+)$/i', $header, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Authenticate a site by its bearer token.
	 *
	 * @param string $token Plain-text bearer token.
	 *
	 * @return Site|null The authenticated site, or null on failure.
	 */
	public function authenticate( string $token ): ?Site {
		$stmt = $this->database->pdo()->query( 'SELECT * FROM sites' );
		$rows = $stmt->fetchAll();

		foreach ( $rows as $row ) {
			if ( \password_verify( $token, $row['token_hash'] ) ) {
				return Site::fromRow( $row );
			}
		}

		return null;
	}
}
