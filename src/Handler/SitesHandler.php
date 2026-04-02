<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Model\Site;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

/**
 * Handles GET /sites — returns a summary list of all monitored sites.
 */
class SitesHandler {

	/**
	 * Site repository.
	 *
	 * @var SiteRepository
	 */
	private SiteRepository $repo;

	/**
	 * Client authenticator.
	 *
	 * @var ClientAuth
	 */
	private ClientAuth $auth;

	/**
	 * Hours before a site is considered stale.
	 *
	 * @var int
	 */
	private int $staleHours;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository $repo       Repository.
	 * @param ClientAuth     $auth       Client authenticator.
	 * @param int            $staleHours Stale threshold in hours.
	 */
	public function __construct( SiteRepository $repo, ClientAuth $auth, int $staleHours = 48 ) {
		$this->repo = $repo;
		$this->auth = $auth;
		$this->staleHours = $staleHours;
	}

	/**
	 * Handle the GET /sites request.
	 *
	 * @param array<string, string> $params Route parameters (unused).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function handle( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		$sites = $this->repo->getAllSites();
		$staleThreshold = \time() - ( $this->staleHours * 3600 );
		$result = [];

		foreach ( $sites as $site ) {
			$result[] = $this->buildSiteSummary( $site, $staleThreshold );
		}

		JsonResponse::send( [ 'sites' => $result ] );
	}

	/**
	 * Build a summary array for a single site.
	 *
	 * @param Site $site           Site entity.
	 * @param int  $staleThreshold Unix timestamp threshold.
	 *
	 * @return array<string, mixed>
	 */
	private function buildSiteSummary( Site $site, int $staleThreshold ): array {
		$report = $this->repo->getReport( $site->id );
		$plugins = $this->repo->getPlugins( $site->id );
		$themes = $this->repo->getThemes( $site->id );

		$pendingPlugins = 0;
		foreach ( $plugins as $plugin ) {
			if ( $plugin['update_available'] !== null ) {
				$pendingPlugins++;
			}
		}

		$pendingThemes = 0;
		foreach ( $themes as $theme ) {
			if ( $theme['update_available'] !== null ) {
				$pendingThemes++;
			}
		}

		$lastSeen = $report['last_updated'] ?? $site->updatedAt;
		$isStale = \strtotime( $lastSeen ) < $staleThreshold;

		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeysError -- API contract requires 12 keys.
		return [
			'id' => $site->id,
			'site_url' => $site->siteUrl,
			'label' => $site->label,
			'network_id' => $site->networkId,
			'environment_type' => $report['environment_type'] ?? null,
			'wp_version' => $report['wp_version'] ?? null,
			'wp_update_available' => $report['wp_update_available'] ?? null,
			'php_version' => $report['php_version'] ?? null,
			'pending_plugin_updates' => $pendingPlugins,
			'pending_theme_updates' => $pendingThemes,
			'last_updated' => $report['last_updated'] ?? null,
			'last_seen' => $lastSeen,
			'stale' => $isStale,
		];
	}
}
