<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Handler;

use Apermo\SiteBookkeeperHub\Auth\NetworkAuth;
use Apermo\SiteBookkeeperHub\Auth\TokenAuth;
use Apermo\SiteBookkeeperHub\JsonResponse;
use Apermo\SiteBookkeeperHub\Model\SiteReport;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

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
	 * Optional network authenticator for multisite fallback.
	 *
	 * @var NetworkAuth|null
	 */
	private ?NetworkAuth $networkAuth;

	/**
	 * Accepted environment types (empty = accept all).
	 *
	 * @var array<int, string>
	 */
	private array $acceptedEnvironments;

	/**
	 * Constructor.
	 *
	 * @param SiteRepository     $repo                  Repository.
	 * @param TokenAuth          $auth                  Token authenticator.
	 * @param NetworkAuth|null   $networkAuth            Optional network authenticator.
	 * @param array<int, string> $accepted_environments Allowed environment types.
	 */
	public function __construct(
		SiteRepository $repo,
		TokenAuth $auth,
		?NetworkAuth $networkAuth = null,
		array $accepted_environments = [],
	) {
		$this->repo = $repo;
		$this->auth = $auth;
		$this->networkAuth = $networkAuth;
		$this->acceptedEnvironments = $accepted_environments;
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

		if ( ! $this->isAcceptedEnvironment( $report ) ) {
			$env_type = $report->environment['environment_type'] ?? 'unknown';
			JsonResponse::error(
				'forbidden',
				"Environment type '{$env_type}' is not accepted.",
				403,
			);
			return;
		}

		// Try site token auth first.
		$site = $this->auth->authenticate( $token );

		// Fall back to network auth if site auth fails.
		if ( $site === null && $this->networkAuth !== null ) {
			$network = $this->networkAuth->authenticate( $token );
			if ( $network !== null ) {
				$site = $this->repo->findOrCreateSiteForNetwork(
					$network->id,
					$report->siteUrl,
					$network->tokenHash,
				);
			}
		}

		if ( $site === null ) {
			JsonResponse::error( 'unauthorized', 'Invalid token.', 401 );
			return;
		}

		$this->repo->upsertReport( $site->id, $report );

		JsonResponse::send( [ 'status' => 'ok' ], 201 );
	}

	/**
	 * Check whether the report's environment type is accepted.
	 *
	 * @param SiteReport $report Incoming report.
	 *
	 * @return bool True if accepted (or no filter configured).
	 */
	private function isAcceptedEnvironment( SiteReport $report ): bool {
		if ( $this->acceptedEnvironments === [] ) {
			return true;
		}

		$env_type = $report->environment['environment_type'] ?? '';

		return \in_array( (string) $env_type, $this->acceptedEnvironments, true );
	}
}
