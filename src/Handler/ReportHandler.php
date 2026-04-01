<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Handler;

use Apermo\SiteMonitorHub\Auth\TokenAuth;
use Apermo\SiteMonitorHub\JsonResponse;
use Apermo\SiteMonitorHub\Model\SiteReport;
use Apermo\SiteMonitorHub\Storage\SiteRepository;

/**
 * Handles POST /report — upserts a site health report.
 */
class ReportHandler {

	/**
	 * Site repository.
	 *
	 * @var SiteRepository
	 */
	private SiteRepository $repo;

	/**
	 * Site token authenticator.
	 *
	 * @var TokenAuth
	 */
	private TokenAuth $auth;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository $repo Repository.
	 * @param TokenAuth      $auth Token authenticator.
	 */
	public function __construct( SiteRepository $repo, TokenAuth $auth ) {
		$this->repo = $repo;
		$this->auth = $auth;
	}

	/**
	 * Handle the incoming report request.
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

		$site = $this->auth->authenticate( $token );
		if ( $site === null ) {
			JsonResponse::error( 'unauthorized', 'Invalid token.', 401 );
			return;
		}

		$body = \file_get_contents( 'php://input' );
		$data = \json_decode( $body, true );

		if ( ! \is_array( $data ) ) {
			JsonResponse::error( 'bad_request', 'Invalid JSON payload.', 400 );
			return;
		}

		$report = SiteReport::fromArray( $data );

		if ( $report->siteUrl === '' ) {
			JsonResponse::error( 'bad_request', 'Missing site_url.', 400 );
			return;
		}

		$this->repo->upsertReport( $site->id, $report );

		JsonResponse::send( [ 'status' => 'ok' ], 201 );
	}
}
