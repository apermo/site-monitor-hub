<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Model\Network;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;

/**
 * Handles GET /networks — returns a summary list of all networks.
 */
class NetworksHandler {

	/**
	 * Network repository.
	 *
	 * @var NetworkRepository
	 */
	private NetworkRepository $repo;

	/**
	 * Client authenticator.
	 *
	 * @var ClientAuth
	 */
	private ClientAuth $auth;

	/**
	 * Constructor.
	 *
	 * @param NetworkRepository $repo Repository.
	 * @param ClientAuth        $auth Client authenticator.
	 */
	public function __construct( NetworkRepository $repo, ClientAuth $auth ) {
		$this->repo = $repo;
		$this->auth = $auth;
	}

	/**
	 * Handle the GET /networks request.
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

		$networks = $this->repo->getAllNetworks();
		$result = [];

		foreach ( $networks as $network ) {
			$result[] = $this->buildNetworkSummary( $network );
		}

		JsonResponse::send( [ 'networks' => $result ] );
	}

	/**
	 * Build a summary array for a single network.
	 *
	 * @param Network $network Network entity.
	 *
	 * @return array<string, mixed>
	 */
	private function buildNetworkSummary( Network $network ): array {
		$report = $this->repo->getNetworkReport( $network->id );

		return [
			'id' => $network->id,
			'main_site_url' => $network->mainSiteUrl,
			'label' => $network->label,
			'subsite_count' => $report ? (int) $report['subsite_count'] : 0,
			'last_seen' => $report['last_updated'] ?? $network->updatedAt,
		];
	}
}
