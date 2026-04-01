<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests\Handler;

use Apermo\SiteMonitorHub\Model\SiteReport;
use Apermo\SiteMonitorHub\Tests\TestCase;

class ThemesHandlerTest extends TestCase {

	public function testGetAllThemesAcrossSites(): void {
		$result1 = $this->createTestSite( 'https://alpha.tld' );
		$result2 = $this->createTestSite( 'https://beta.tld' );

		$this->repo->upsertReport(
			$result1['site']->id,
			SiteReport::fromArray(
				[
					'schema_version' => 1,
					'timestamp' => '2026-04-01T12:00:00Z',
					'site_url' => 'https://alpha.tld',
					'environment' => [ 'wp_version' => '6.7' ],
					'themes' => [
						[
							'slug' => 'twentytwentyfive',
							'name' => 'Twenty Twenty-Five',
							'version' => '1.0',
							'active' => true,
							'update_available' => '1.1',
						],
					],
				],
			),
		);

		$this->repo->upsertReport(
			$result2['site']->id,
			SiteReport::fromArray(
				[
					'schema_version' => 1,
					'timestamp' => '2026-04-01T12:00:00Z',
					'site_url' => 'https://beta.tld',
					'environment' => [ 'wp_version' => '6.7' ],
					'themes' => [
						[
							'slug' => 'twentytwentyfive',
							'name' => 'Twenty Twenty-Five',
							'version' => '1.0',
							'active' => true,
						],
						[
							'slug' => 'astra',
							'name' => 'Astra',
							'version' => '4.0',
							'active' => false,
						],
					],
				],
			),
		);

		$allThemes = $this->repo->getAllThemes();
		$this->assertCount( 3, $allThemes );
	}

	public function testFilterOutdatedThemes(): void {
		$result = $this->createTestSite( 'https://themes.tld' );

		$this->repo->upsertReport(
			$result['site']->id,
			SiteReport::fromArray(
				[
					'schema_version' => 1,
					'timestamp' => '2026-04-01T12:00:00Z',
					'site_url' => 'https://themes.tld',
					'environment' => [ 'wp_version' => '6.7' ],
					'themes' => [
						[
							'slug' => 'twentytwentyfive',
							'name' => 'Twenty Twenty-Five',
							'version' => '1.0',
							'active' => true,
							'update_available' => '1.1',
						],
						[
							'slug' => 'astra',
							'name' => 'Astra',
							'version' => '4.0',
							'active' => false,
						],
					],
				],
			),
		);

		$outdated = $this->repo->getAllThemes( null, true );
		$this->assertCount( 1, $outdated );
		$this->assertSame( 'twentytwentyfive', $outdated[0]['slug'] );
	}
}
