<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Auth;

use Apermo\SiteMonitorHub\Storage\Database;

/**
 * Validates bearer tokens for read-only API clients.
 */
class ClientAuth {

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
		return TokenAuth::extractBearerToken();
	}

	/**
	 * Authenticate a client by its bearer token.
	 *
	 * @param string $token Plain-text bearer token.
	 *
	 * @return bool True if valid.
	 */
	public function authenticate( string $token ): bool {
		$stmt = $this->database->pdo()->query( 'SELECT * FROM client_tokens' );
		$rows = $stmt->fetchAll();

		foreach ( $rows as $row ) {
			if ( \password_verify( $token, $row['token_hash'] ) ) {
				return true;
			}
		}

		return false;
	}
}
