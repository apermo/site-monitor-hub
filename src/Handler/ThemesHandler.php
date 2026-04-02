<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;
use Apermo\SiteBookkeeperHub\Vulnerability\VulnerabilityManager;

/**
 * Handles GET /themes — cross-site theme report.
 */
class ThemesHandler {

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
	 * Vulnerability manager (optional).
	 *
	 * @var VulnerabilityManager|null
	 */
	private ?VulnerabilityManager $vulnManager;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository            $repo         Repository.
	 * @param ClientAuth                $auth         Client authenticator.
	 * @param VulnerabilityManager|null $vuln_manager Vulnerability manager.
	 */
	public function __construct(
		SiteRepository $repo,
		ClientAuth $auth,
		?VulnerabilityManager $vuln_manager = null,
	) {
		$this->repo = $repo;
		$this->auth = $auth;
		$this->vulnManager = $vuln_manager;
	}

	/**
	 * Handle the GET /themes request.
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

		$slug = $_GET['slug'] ?? null;
		$outdated = isset( $_GET['outdated'] ) && $_GET['outdated'] === 'true';

		$rows = $this->repo->getAllThemes( $slug, $outdated );

		JsonResponse::send( [ 'themes' => $this->groupBySlug( $rows ) ] );
	}

	/**
	 * Group flat theme rows by slug with nested sites array.
	 *
	 * @param array<int, array<string, mixed>> $rows Flat rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function groupBySlug( array $rows ): array {
		$grouped = [];

		foreach ( $rows as $row ) {
			$slug = $row['slug'];
			if ( ! isset( $grouped[ $slug ] ) ) {
				$grouped[ $slug ] = [
					'slug' => $slug,
					'name' => $row['name'],
					'sites' => [],
				];
			}

			$site_entry = [
				'site_id' => $row['site_id'],
				'site_url' => $row['site_url'],
				'label' => $row['site_label'] ?? null,
				'version' => $row['version'],
				'update_available' => $row['update_available'],
				'active' => $row['active'],
				'last_updated' => $row['last_updated'],
			];

			if ( $this->vulnManager !== null ) {
				$vulns = $this->vulnManager->lookup( $slug, $row['version'], 'theme' );
				$site_entry['vulnerabilities'] = $vulns;
				$site_entry['security_update'] = $vulns !== [];
			}

			$grouped[ $slug ]['sites'][] = $site_entry;
		}

		return \array_values( $grouped );
	}
}
