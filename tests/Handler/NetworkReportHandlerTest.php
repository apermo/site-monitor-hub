<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Handler;

use Apermo\SiteBookkeeperHub\Auth\NetworkAuth;
use Apermo\SiteBookkeeperHub\Model\NetworkReport;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class NetworkReportHandlerTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testNetworkReportUpsert(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-rpt-001', 'https://network.example.tld', $hash, 'Test Net' );

		$report = NetworkReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00+00:00',
				'main_site_url' => 'https://network.example.tld',
				'subsites' => [
					[
						'blog_id' => 1,
						'url' => 'https://network.example.tld',
						'label' => 'Main Site',
					],
					[
						'blog_id' => 2,
						'url' => 'https://sub.network.example.tld',
						'label' => 'Subsite',
					],
				],
				'network_plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.6',
						'update_available' => null,
					],
				],
				'super_admins' => [
					[
						'user_login' => 'admin',
						'display_name' => 'Admin',
						'email' => 'admin@example.tld',
					],
				],
			],
		);

		$this->networkRepo->upsertNetworkReport( 'net-rpt-001', $report );

		$stored = $this->networkRepo->getNetworkReport( 'net-rpt-001' );
		$this->assertNotNull( $stored );
		$this->assertSame( 2, (int) $stored['subsite_count'] );

		$plugins = $this->networkRepo->getNetworkPlugins( 'net-rpt-001' );
		$this->assertCount( 1, $plugins );

		$users = $this->networkRepo->getNetworkUsers( 'net-rpt-001' );
		$this->assertCount( 1, $users );
	}

	public function testNetworkAuthForReport(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-rpt-002', 'https://auth-net.example.tld', $hash, null );

		$auth = new NetworkAuth( $this->database );
		$network = $auth->authenticate( $token );

		$this->assertNotNull( $network );
		$this->assertSame( 'net-rpt-002', $network->id );
	}
}
