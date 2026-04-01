<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Handler;

use Apermo\SiteMonitorHub\Auth\ClientAuth;
use Apermo\SiteMonitorHub\JsonResponse;
use Apermo\SiteMonitorHub\Storage\SiteRepository;

/**
 * Handles GET /plugins — cross-site plugin report.
 */
class PluginsHandler {

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
	 * Constructor.
	 *
	 * @param SiteRepository $repo Repository.
	 * @param ClientAuth     $auth Client authenticator.
	 */
	public function __construct( SiteRepository $repo, ClientAuth $auth ) {
		$this->repo = $repo;
		$this->auth = $auth;
	}

	/**
	 * Handle the GET /plugins request.
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

		$plugins = $this->repo->getAllPlugins( $slug, $outdated );

		JsonResponse::send( $plugins );
	}
}
