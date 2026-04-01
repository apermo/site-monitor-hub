<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests\Handler;

use Apermo\SiteMonitorHub\Auth\TokenAuth;
use Apermo\SiteMonitorHub\Handler\ReportHandler;
use Apermo\SiteMonitorHub\Model\SiteReport;
use Apermo\SiteMonitorHub\Tests\TestCase;

class ReportHandlerTest extends TestCase {

	public function testUpsertReportStoresData(): void {
		$result = $this->createTestSite( 'https://example.tld', 'Test Site' );
		$site = $result['site'];

		$report = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://example.tld',
				'environment' => [
					'wp_version' => '6.7',
					'php_version' => '8.2.10',
				],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet Anti-spam',
						'version' => '5.3',
						'active' => true,
					],
				],
				'themes' => [
					[
						'slug' => 'twentytwentyfive',
						'name' => 'Twenty Twenty-Five',
						'version' => '1.0',
						'active' => true,
					],
				],
			],
		);

		$this->repo->upsertReport( $site->id, $report );

		$stored = $this->repo->getReport( $site->id );
		$this->assertNotNull( $stored );
		$this->assertSame( '6.7', $stored['wp_version'] );
		$this->assertSame( '8.2.10', $stored['php_version'] );

		$plugins = $this->repo->getPlugins( $site->id );
		$this->assertCount( 1, $plugins );
		$this->assertSame( 'akismet', $plugins[0]['slug'] );

		$themes = $this->repo->getThemes( $site->id );
		$this->assertCount( 1, $themes );
		$this->assertSame( 'twentytwentyfive', $themes[0]['slug'] );
	}

	public function testUpsertReportReplacesExistingData(): void {
		$result = $this->createTestSite( 'https://example.tld' );
		$site = $result['site'];

		$report1 = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://example.tld',
				'environment' => [ 'wp_version' => '6.6' ],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.2',
						'active' => true,
					],
				],
			],
		);
		$this->repo->upsertReport( $site->id, $report1 );

		$report2 = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-02T12:00:00Z',
				'site_url' => 'https://example.tld',
				'environment' => [ 'wp_version' => '6.7' ],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet',
						'version' => '5.3',
						'active' => true,
						'update_available' => null,
					],
					[
						'slug' => 'jetpack',
						'name' => 'Jetpack',
						'version' => '13.0',
						'active' => true,
					],
				],
			],
		);
		$this->repo->upsertReport( $site->id, $report2 );

		$stored = $this->repo->getReport( $site->id );
		$this->assertSame( '6.7', $stored['wp_version'] );

		$plugins = $this->repo->getPlugins( $site->id );
		$this->assertCount( 2, $plugins );
	}

	public function testTokenAuthenticatesSite(): void {
		$result = $this->createTestSite( 'https://auth-test.tld' );

		$auth = new TokenAuth( $this->database );

		$site = $auth->authenticate( $result['token'] );
		$this->assertNotNull( $site );
		$this->assertSame( 'https://auth-test.tld', $site->siteUrl );
	}

	public function testTokenAuthRejectsInvalidToken(): void {
		$this->createTestSite( 'https://auth-test.tld' );

		$auth = new TokenAuth( $this->database );
		$site = $auth->authenticate( 'invalid-token' );

		$this->assertNull( $site );
	}

	public function testReportStoresCustomFields(): void {
		$result = $this->createTestSite( 'https://custom.tld' );
		$site = $result['site'];

		$report = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://custom.tld',
				'environment' => [ 'wp_version' => '6.7' ],
				'custom_fields' => [
					[
						'key' => 'ssl_expiry',
						'label' => 'SSL Expiry',
						'value' => '2026-12-31',
						'status' => 'ok',
					],
				],
			],
		);

		$this->repo->upsertReport( $site->id, $report );

		$fields = $this->repo->getCustomFields( $site->id );
		$this->assertCount( 1, $fields );
		$this->assertSame( 'ssl_expiry', $fields[0]['key'] );
		$this->assertSame( 'ok', $fields[0]['status'] );
	}
}
