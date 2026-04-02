<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Tests\Handler;

use Apermo\SiteBookkeeperHub\Model\NetworkReport;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Tests\TestCase;

class NetworksHandlerTest extends TestCase {

	private NetworkRepository $networkRepo;

	protected function setUp(): void {
		parent::setUp();
		$this->networkRepo = new NetworkRepository( $this->database );
	}

	public function testGetAllNetworksSummary(): void {
		$hash = password_hash( 'token', PASSWORD_ARGON2ID );
		$this->networkRepo->addNetwork( 'net-sum-001', 'https://alpha.example.tld', $hash, 'Alpha' );
		$this->networkRepo->addNetwork( 'net-sum-002', 'https://beta.example.tld', $hash, 'Beta' );

		$report = NetworkReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00+00:00',
				'main_site_url' => 'https://alpha.example.tld',
				'subsites' => [
					[ 'blog_id' => 1, 'url' => 'https://alpha.example.tld', 'label' => 'Main' ],
					[ 'blog_id' => 2, 'url' => 'https://sub.alpha.example.tld', 'label' => 'Sub' ],
				],
			],
		);
		$this->networkRepo->upsertNetworkReport( 'net-sum-001', $report );

		$networks = $this->networkRepo->getAllNetworks();
		$this->assertCount( 2, $networks );

		$stored = $this->networkRepo->getNetworkReport( 'net-sum-001' );
		$this->assertNotNull( $stored );
		$this->assertSame( 2, (int) $stored['subsite_count'] );
	}
}
