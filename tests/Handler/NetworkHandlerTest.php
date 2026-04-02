<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Handler;

use Apermo\SiteBookkeeperHub\Model\NetworkReport;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class NetworkHandlerTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testGetNetworkDetail(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-det-001', 'https://detail.example.tld', $hash, 'Detail Net' );

		$report = NetworkReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00+00:00',
				'main_site_url' => 'https://detail.example.tld',
				'subsites' => [
					[ 'blog_id' => 1, 'url' => 'https://detail.example.tld', 'label' => 'Main' ],
					[ 'blog_id' => 2, 'url' => 'https://sub.detail.example.tld', 'label' => 'Sub' ],
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
		$this->networkRepo->upsertNetworkReport( 'net-det-001', $report );

		// Also create subsites in site repo.
		$this->repo->findOrCreateSiteForNetwork(
			'net-det-001',
			'https://detail.example.tld',
			$hash,
		);
		$this->repo->findOrCreateSiteForNetwork(
			'net-det-001',
			'https://sub.detail.example.tld',
			$hash,
		);

		// Verify all data is accessible.
		$network = $this->networkRepo->findNetworkById( 'net-det-001' );
		$this->assertNotNull( $network );
		$this->assertSame( 'Detail Net', $network->label );

		$networkReport = $this->networkRepo->getNetworkReport( 'net-det-001' );
		$this->assertNotNull( $networkReport );

		$plugins = $this->networkRepo->getNetworkPlugins( 'net-det-001' );
		$this->assertCount( 1, $plugins );

		$users = $this->networkRepo->getNetworkUsers( 'net-det-001' );
		$this->assertCount( 1, $users );

		$subsites = $this->repo->getSitesByNetworkId( 'net-det-001' );
		$this->assertCount( 2, $subsites );
	}

	public function testGetNonExistentNetworkReturnsNull(): void {
		$network = $this->networkRepo->findNetworkById( 'nonexistent' );
		$this->assertNull( $network );
	}
}
