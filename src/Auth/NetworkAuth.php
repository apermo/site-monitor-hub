<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Auth;

use Apermo\SiteBookkeeperHub\Model\Network;
use Apermo\SiteBookkeeperHub\Storage\Database;

/**
 * Validates bearer tokens for networks pushing reports.
 */
class NetworkAuth {

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
	 * Authenticate a network by its bearer token.
	 *
	 * @param string $token Plain-text bearer token.
	 *
	 * @return Network|null The authenticated network, or null on failure.
	 */
	public function authenticate( string $token ): ?Network {
		$stmt = $this->database->pdo()->query( 'SELECT * FROM networks' );
		$rows = $stmt->fetchAll();

		foreach ( $rows as $row ) {
			if ( \password_verify( $token, $row['token_hash'] ) ) {
				return Network::fromRow( $row );
			}
		}

		return null;
	}
}
