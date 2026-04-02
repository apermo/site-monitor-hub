<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Handler;

use Apermo\SiteBookkeeperHub\Auth\NetworkAuth;
use Apermo\SiteBookkeeperHub\Model\SiteReport;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class ReportHandlerNetworkTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testNetworkTokenCreatesSubsite(): void {
		$token = bin2hex( random_bytes( 32 ) );
		$hash = password_hash( $token, PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-sub-001', 'https://network.example.tld', $hash, null );

		// Simulate: a subsite uses the network token to push a report.
		$auth = new NetworkAuth( $this->database );
		$network = $auth->authenticate( $token );
		$this->assertNotNull( $network );

		// Auto-register the subsite.
		$site = $this->repo->findOrCreateSiteForNetwork(
			$network->id,
			'https://sub.network.example.tld',
			$network->tokenHash,
		);

		$this->assertSame( 'https://sub.network.example.tld', $site->siteUrl );

		// Upsert a report for the subsite.
		$report = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://sub.network.example.tld',
				'environment' => [ 'wp_version' => '6.7', 'php_version' => '8.2.10' ],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.3',
						'active' => true,
					],
				],
			],
		);
		$this->repo->upsertReport( $site->id, $report );

		$stored = $this->repo->getReport( $site->id );
		$this->assertNotNull( $stored );
		$this->assertSame( '6.7', $stored['wp_version'] );

		// Verify the site belongs to the network.
		$networkSites = $this->repo->getSitesByNetworkId( 'net-sub-001' );
		$this->assertCount( 1, $networkSites );
	}
}
