<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Handler;

use Apermo\SiteMonitorHub\Auth\ClientAuth;
use Apermo\SiteMonitorHub\JsonResponse;
use Apermo\SiteMonitorHub\Model\Site;
use Apermo\SiteMonitorHub\Storage\SiteRepository;

/**
 * Handles GET /sites/{id} — returns the full report for a single site.
 */
class SiteHandler {

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
	 * Handle the GET /sites/{id} request.
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

		$siteId = $params['id'] ?? '';
		$site = $this->repo->findSiteById( $siteId );

		if ( $site === null ) {
			JsonResponse::error( 'not_found', 'Site not found.', 404 );
			return;
		}

		$report = $this->repo->getReport( $site->id );
		$plugins = $this->repo->getPlugins( $site->id );
		$themes = $this->repo->getThemes( $site->id );
		$customFields = $this->repo->getCustomFields( $site->id );
		$users = $this->buildUsersWithMeta( $site->id );
		$roles = $this->repo->getRoles( $site->id );

		$lastSeen = $report['last_updated'] ?? $site->updatedAt;
		$staleThreshold = \time() - ( $this->staleHours * 3600 );
		$isStale = \strtotime( $lastSeen ) < $staleThreshold;

		$response = $this->buildResponse( $site, $report, $plugins, $themes, $customFields, $users, $roles, $lastSeen, $isStale );

		JsonResponse::send( $response );
	}

	/**
	 * Build users array with meta attached.
	 *
	 * @param string $siteId Site UUID.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function buildUsersWithMeta( string $siteId ): array {
		$users = $this->repo->getUsers( $siteId );
		$result = [];

		foreach ( $users as $user ) {
			$meta = $this->repo->getUserMeta( $siteId, $user['user_login'] );
			$metaMap = [];
			foreach ( $meta as $item ) {
				$metaMap[ $item['meta_key'] ] = $item['meta_value'];
			}
			$user['meta'] = $metaMap;
			$result[] = $user;
		}

		return $result;
	}

	/**
	 * Build the full response array.
	 *
	 * @param \Apermo\SiteMonitorHub\Model\Site $site         Site entity.
	 * @param array<string, mixed>|null         $report       Report row.
	 * @param array<int, array<string, mixed>>  $plugins      Plugins.
	 * @param array<int, array<string, mixed>>  $themes       Themes.
	 * @param array<int, array<string, mixed>>  $customFields Custom fields.
	 * @param array<int, array<string, mixed>>  $users        Users with meta.
	 * @param array<int, array<string, mixed>>  $roles        Roles.
	 * @param string                            $lastSeen     Last seen timestamp.
	 * @param bool                              $isStale      Whether site is stale.
	 *
	 * @return array<string, mixed>
	 */
	private function buildResponse(
		Site $site,
		?array $report,
		array $plugins,
		array $themes,
		array $customFields,
		array $users,
		array $roles,
		string $lastSeen,
		bool $isStale,
	): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeysError -- API contract requires full detail.
		return [
			'id' => $site->id,
			'site_url' => $site->siteUrl,
			'label' => $site->label,
			'wp_version' => $report['wp_version'] ?? null,
			'wp_update_available' => $report['wp_update_available'] ?? null,
			'php_version' => $report['php_version'] ?? null,
			'last_updated' => $report['last_updated'] ?? null,
			'last_seen' => $lastSeen,
			'stale' => $isStale,
			'plugins' => $plugins,
			'themes' => $themes,
			'custom_fields' => $customFields,
			'users' => $users,
			'roles' => $roles,
		];
	}
}
