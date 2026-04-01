<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests\Handler;

use Apermo\SiteMonitorHub\Model\SiteReport;
use Apermo\SiteMonitorHub\Tests\TestCase;

class PluginsHandlerTest extends TestCase {

	public function testGetAllPluginsAcrossSites(): void {
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
					'plugins' => [
						[
							'slug' => 'akismet',
							'name' => 'Akismet',
							'version' => '5.3',
							'active' => true,
							'update_available' => '5.4',
						],
						[
							'slug' => 'jetpack',
							'name' => 'Jetpack',
							'version' => '13.0',
							'active' => true,
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
					'plugins' => [
						[
							'slug' => 'akismet',
							'name' => 'Akismet',
							'version' => '5.2',
							'active' => true,
						],
					],
				],
			),
		);

		$allPlugins = $this->repo->getAllPlugins();
		$this->assertCount( 3, $allPlugins );
	}

	public function testFilterBySlug(): void {
		$result = $this->createTestSite( 'https://filter.tld' );

		$this->repo->upsertReport(
			$result['site']->id,
			SiteReport::fromArray(
				[
					'schema_version' => 1,
					'timestamp' => '2026-04-01T12:00:00Z',
					'site_url' => 'https://filter.tld',
					'environment' => [ 'wp_version' => '6.7' ],
					'plugins' => [
						[
							'slug' => 'akismet',
							'name' => 'Akismet',
							'version' => '5.3',
							'active' => true,
						],
						[
							'slug' => 'jetpack',
							'name' => 'Jetpack',
							'version' => '13.0',
							'active' => true,
						],
					],
				],
			),
		);

		$filtered = $this->repo->getAllPlugins( 'akismet' );
		$this->assertCount( 1, $filtered );
		$this->assertSame( 'akismet', $filtered[0]['slug'] );
	}

	public function testFilterOutdated(): void {
		$result = $this->createTestSite( 'https://outdated.tld' );

		$this->repo->upsertReport(
			$result['site']->id,
			SiteReport::fromArray(
				[
					'schema_version' => 1,
					'timestamp' => '2026-04-01T12:00:00Z',
					'site_url' => 'https://outdated.tld',
					'environment' => [ 'wp_version' => '6.7' ],
					'plugins' => [
						[
							'slug' => 'akismet',
							'name' => 'Akismet',
							'version' => '5.3',
							'active' => true,
							'update_available' => '5.4',
						],
						[
							'slug' => 'jetpack',
							'name' => 'Jetpack',
							'version' => '13.0',
							'active' => true,
						],
					],
				],
			),
		);

		$outdated = $this->repo->getAllPlugins( null, true );
		$this->assertCount( 1, $outdated );
		$this->assertSame( 'akismet', $outdated[0]['slug'] );
	}

	public function testPluginsIncludeSiteInfo(): void {
		$result = $this->createTestSite( 'https://info.tld', 'Info Site' );

		$this->repo->upsertReport(
			$result['site']->id,
			SiteReport::fromArray(
				[
					'schema_version' => 1,
					'timestamp' => '2026-04-01T12:00:00Z',
					'site_url' => 'https://info.tld',
					'environment' => [ 'wp_version' => '6.7' ],
					'plugins' => [
						[
							'slug' => 'akismet',
							'name' => 'Akismet',
							'version' => '5.3',
							'active' => true,
						],
					],
				],
			),
		);

		$plugins = $this->repo->getAllPlugins();
		$this->assertSame( 'https://info.tld', $plugins[0]['site_url'] );
		$this->assertSame( 'Info Site', $plugins[0]['site_label'] );
	}
}
