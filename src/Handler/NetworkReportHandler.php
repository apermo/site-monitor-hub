<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\NetworkAuth;
use Apermo\SiteBookkeeperHub\Auth\TokenAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Model\NetworkReport;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;

/**
 * Handles POST /network-report — upserts a network health report.
 */
class NetworkReportHandler {

	/**
	 * Network repository.
	 *
	 * @var NetworkRepository
	 */
	private NetworkRepository $repo;

	/**
	 * Network token authenticator.
	 *
	 * @var NetworkAuth
	 */
	private NetworkAuth $auth;

	/**
	 * Constructor.
	 *
	 * @param NetworkRepository $repo Repository.
	 * @param NetworkAuth       $auth Network authenticator.
	 */
	public function __construct( NetworkRepository $repo, NetworkAuth $auth ) {
		$this->repo = $repo;
		$this->auth = $auth;
	}

	/**
	 * Handle the incoming network report request.
	 *
	 * @param array<string, string> $params Route parameters (unused).
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public function handle( array $params ): void {
		$token = TokenAuth::extractBearerToken();
		if ( $token === null ) {
			JsonResponse::error( 'unauthorized', 'Missing or malformed Authorization header.', 401 );
			return;
		}

		$network = $this->auth->authenticate( $token );
		if ( $network === null ) {
			JsonResponse::error( 'unauthorized', 'Invalid token.', 401 );
			return;
		}

		$body = \file_get_contents( 'php://input' );
		$data = \json_decode( $body, true );

		if ( ! \is_array( $data ) ) {
			JsonResponse::error( 'bad_request', 'Invalid JSON payload.', 400 );
			return;
		}

		$report = NetworkReport::fromArray( $data );

		if ( $report->mainSiteUrl === '' ) {
			JsonResponse::error( 'bad_request', 'Missing main_site_url.', 400 );
			return;
		}

		$this->repo->upsertNetworkReport( $network->id, $report );

		JsonResponse::send( [ 'status' => 'ok' ], 201 );
	}
}
