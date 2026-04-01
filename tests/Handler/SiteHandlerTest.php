<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Tests\Handler;

use Apermo\SiteMonitorHub\Model\SiteReport;
use Apermo\SiteMonitorHub\Tests\TestCase;

class SiteHandlerTest extends TestCase {

	public function testGetSiteDetailReturnsFullReport(): void {
		$result = $this->createTestSite( 'https://detail.tld', 'Detail Site' );
		$site = $result['site'];

		$report = SiteReport::fromArray(
			[
				'schema_version' => 1,
				'timestamp' => '2026-04-01T12:00:00Z',
				'site_url' => 'https://detail.tld',
				'environment' => [
					'wp_version' => '6.7',
					'php_version' => '8.2.10',
					'wp_update_available' => '6.8',
				],
				'plugins' => [
					[
						'slug' => 'akismet',
						'name' => 'Akismet Anti-spam',
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
				'themes' => [
					[
						'slug' => 'twentytwentyfive',
						'name' => 'Twenty Twenty-Five',
						'version' => '1.0',
						'active' => true,
					],
				],
				'custom_fields' => [
					[
						'key' => 'ssl_expiry',
						'label' => 'SSL Expiry',
						'value' => '2027-01-15',
						'status' => 'ok',
					],
				],
				'users' => [
					[
						'user_login' => 'admin',
						'display_name' => 'Admin User',
						'email' => 'admin@detail.tld',
						'role' => 'administrator',
						'meta' => [
							'last_login' => '2026-03-31',
						],
					],
				],
				'roles' => [
					[
						'slug' => 'administrator',
						'name' => 'Administrator',
						'is_custom' => false,
						'is_modified' => false,
						'capabilities' => [ 'manage_options' => true ],
					],
				],
			],
		);

		$this->repo->upsertReport( $site->id, $report );

		// Verify all data is stored and retrievable.
		$storedReport = $this->repo->getReport( $site->id );
		$this->assertNotNull( $storedReport );
		$this->assertSame( '6.7', $storedReport['wp_version'] );
		$this->assertSame( '6.8', $storedReport['wp_update_available'] );

		$plugins = $this->repo->getPlugins( $site->id );
		$this->assertCount( 2, $plugins );

		$themes = $this->repo->getThemes( $site->id );
		$this->assertCount( 1, $themes );

		$customFields = $this->repo->getCustomFields( $site->id );
		$this->assertCount( 1, $customFields );
		$this->assertSame( 'ssl_expiry', $customFields[0]['key'] );

		$users = $this->repo->getUsers( $site->id );
		$this->assertCount( 1, $users );
		$this->assertSame( 'admin', $users[0]['user_login'] );

		$meta = $this->repo->getUserMeta( $site->id, 'admin' );
		$this->assertCount( 1, $meta );
		$this->assertSame( 'last_login', $meta[0]['meta_key'] );

		$roles = $this->repo->getRoles( $site->id );
		$this->assertCount( 1, $roles );
		$this->assertSame( 'administrator', $roles[0]['slug'] );
	}

	public function testGetNonExistentSiteReturnsNull(): void {
		$site = $this->repo->findSiteById( 'non-existent-uuid' );
		$this->assertNull( $site );
	}
}
