<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests;

use Apermo\SiteMonitorHub\Storage\Database;
use Apermo\SiteMonitorHub\Storage\SiteRepository;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case with database helper methods.
 */
abstract class TestCase extends BaseTestCase {

	protected Database $database;
	protected SiteRepository $repo;
	private string $dbPath;

	protected function setUp(): void {
		parent::setUp();

		$this->dbPath = sys_get_temp_dir() . '/smh_test_' . uniqid() . '.sqlite';
		$this->database = new Database( $this->dbPath );
		$this->database->migrate();
		$this->repo = new SiteRepository( $this->database );
	}

	protected function tearDown(): void {
		unset( $this->database, $this->repo );

		if ( file_exists( $this->dbPath ) ) {
			unlink( $this->dbPath );
		}

		parent::tearDown();
	}

	/**
	 * Create a test site and return the plain-text token + site data.
	 *
	 * @param string      $url   Site URL.
	 * @param string|null $label Optional label.
	 *
	 * @return array{token: string, site: \Apermo\SiteMonitorHub\Model\Site}
	 */
	protected function createTestSite( string $url = 'https://example.tld', ?string $label = null ): array {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$site = $this->repo->addSite( $this->generateUuid(), $url, $hash, $label );

		return [ 'token' => $token, 'site' => $site ];
	}

	/**
	 * Create a test client token and return the plain-text token.
	 *
	 * @param string|null $label Optional label.
	 *
	 * @return string Plain-text token.
	 */
	protected function createTestClientToken( ?string $label = null ): string {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$this->repo->addClientToken( $hash, $label );

		return $token;
	}

	/**
	 * Generate a v4 UUID.
	 *
	 * @return string
	 */
	protected function generateUuid(): string {
		$data = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}
}
