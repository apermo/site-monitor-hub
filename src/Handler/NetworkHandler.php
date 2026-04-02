<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

/**
 * Handles GET /networks/{id} — returns full detail for a single network.
 */
class NetworkHandler {

	/**
	 * Network repository.
	 *
	 * @var NetworkRepository
	 */
	private NetworkRepository $networkRepo;

	/**
	 * Site repository.
	 *
	 * @var SiteRepository
	 */
	private SiteRepository $siteRepo;

	/**
	 * Client authenticator.
	 *
	 * @var ClientAuth
	 */
	private ClientAuth $auth;

	/**
	 * Constructor.
	 *
	 * @param NetworkRepository $networkRepo Network repository.
	 * @param SiteRepository    $siteRepo    Site repository.
	 * @param ClientAuth        $auth        Client authenticator.
	 */
	public function __construct( NetworkRepository $networkRepo, SiteRepository $siteRepo, ClientAuth $auth ) {
		$this->networkRepo = $networkRepo;
		$this->siteRepo = $siteRepo;
		$this->auth = $auth;
	}

	/**
	 * Handle the GET /networks/{id} request.
	 *
	 * @param array<string, string> $params Route parameters with 'id'.
	 *
	 * @return void
	 */
	public function handle( array $params ): void {
		$token = ClientAuth::extractBearerToken();
		if ( $token === null || ! $this->auth->authenticate( $token ) ) {
			JsonResponse::error( 'unauthorized', 'Invalid or missing client token.', 401 );
			return;
		}

		$networkId = $params['id'] ?? '';
		$network = $this->networkRepo->findNetworkById( $networkId );

		if ( $network === null ) {
			JsonResponse::error( 'not_found', 'Network not found.', 404 );
			return;
		}

		$report = $this->networkRepo->getNetworkReport( $network->id );
		$plugins = $this->networkRepo->getNetworkPlugins( $network->id );
		$superAdmins = $this->networkRepo->getNetworkUsers( $network->id );
		$subsites = $this->siteRepo->getSitesByNetworkId( $network->id );

		$subsiteList = [];
		foreach ( $subsites as $site ) {
			$subsiteList[] = [
				'id' => $site->id,
				'site_url' => $site->siteUrl,
				'label' => $site->label,
			];
		}

		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeysError -- API contract requires full detail.
		$response = [
			'id' => $network->id,
			'main_site_url' => $network->mainSiteUrl,
			'label' => $network->label,
			'subsite_count' => $report ? (int) $report['subsite_count'] : 0,
			'last_updated' => $report['last_updated'] ?? null,
			'network_plugins' => $plugins,
			'super_admins' => $superAdmins,
			'subsites' => $subsiteList,
		];

		JsonResponse::send( $response );
	}
}
