<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Auth;

use Apermo\SiteBookkeeperHub\Auth\NetworkAuth;
use Apermo\SiteBookkeeperHub\Model\Network;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class NetworkAuthTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testAuthenticateValidToken(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-auth-001', 'https://auth.example.tld', $hash, 'Auth Net' );

		$auth = new NetworkAuth( $this->database );
		$network = $auth->authenticate( $token );

		$this->assertInstanceOf( Network::class, $network );
		$this->assertSame( 'net-auth-001', $network->id );
		$this->assertSame( 'https://auth.example.tld', $network->mainSiteUrl );
	}

	public function testAuthenticateInvalidToken(): void {
		$hash = password_hash( 'correct-token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-auth-002', 'https://invalid.example.tld', $hash, null );

		$auth = new NetworkAuth( $this->database );
		$network = $auth->authenticate( 'wrong-token' );

		$this->assertNull( $network );
	}

	public function testAuthenticateNoNetworks(): void {
		$auth = new NetworkAuth( $this->database );
		$network = $auth->authenticate( 'any-token' );

		$this->assertNull( $network );
	}

	public function testAuthenticateMatchesCorrectNetwork(): void {
		$token1 = bin2hex( random_bytes( 32 ) );
		$hash1 = password_hash( $token1, PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-auth-003', 'https://first.example.tld', $hash1, 'First' );

		$token2 = bin2hex( random_bytes( 32 ) );
		$hash2 = password_hash( $token2, PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-auth-004', 'https://second.example.tld', $hash2, 'Second' );

		$auth = new NetworkAuth( $this->database );

		$network1 = $auth->authenticate( $token1 );
		$this->assertNotNull( $network1 );
		$this->assertSame( 'net-auth-003', $network1->id );

		$network2 = $auth->authenticate( $token2 );
		$this->assertNotNull( $network2 );
		$this->assertSame( 'net-auth-004', $network2->id );
	}
}
