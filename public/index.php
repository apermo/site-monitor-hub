<?php
/**
 * Single web entry point.
 *
 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Apermo\SiteBookkeeperHub\Auth\ClientAuth;
use Apermo\SiteBookkeeperHub\Auth\NetworkAuth;
use Apermo\SiteBookkeeperHub\Auth\TokenAuth;
use Apermo\SiteBookkeeperHub\Handler\NetworkHandler;
use Apermo\SiteBookkeeperHub\Handler\NetworkReportHandler;
use Apermo\SiteBookkeeperHub\Handler\NetworksHandler;
use Apermo\SiteBookkeeperHub\Handler\PluginsHandler;
use Apermo\SiteBookkeeperHub\Handler\ReportHandler;
use Apermo\SiteBookkeeperHub\Handler\SiteHandler;
use Apermo\SiteBookkeeperHub\Handler\SitesHandler;
use Apermo\SiteBookkeeperHub\Handler\ThemesHandler;
use Apermo\SiteBookkeeperHub\Router;
use Apermo\SiteBookkeeperHub\Storage\Database;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;

// Load .env if it exists.
$env_file = __DIR__ . '/../.env';
if ( file_exists( $env_file ) ) {
	$lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === '' || str_starts_with( $line, '#' ) ) {
			continue;
		}
		putenv( $line );
	}
}

$database_path = getenv( 'DATABASE_PATH' ) ?: __DIR__ . '/../data/monitor.sqlite';
$stale_hours = (int) ( getenv( 'STALE_THRESHOLD_HOURS' ) ?: 48 );
$database = new Database( $database_path );
$database->migrate();
$repo = new SiteRepository( $database );
$network_repo = new NetworkRepository( $database );
$token_auth = new TokenAuth( $database );
$network_auth = new NetworkAuth( $database );
$client_auth = new ClientAuth( $database );
$report_handler = new ReportHandler( $repo, $token_auth, $network_auth );
$network_report_handler = new NetworkReportHandler( $network_repo, $network_auth );
$sites_handler = new SitesHandler( $repo, $client_auth, $stale_hours );
$site_handler = new SiteHandler( $repo, $client_auth, $stale_hours );
$plugins_handler = new PluginsHandler( $repo, $client_auth );
$themes_handler = new ThemesHandler( $repo, $client_auth );
$networks_handler = new NetworksHandler( $network_repo, $client_auth );
$network_handler = new NetworkHandler( $network_repo, $repo, $client_auth );

$router = new Router();
$router->post( '/report', [ $report_handler, 'handle' ] );
$router->post( '/network-report', [ $network_report_handler, 'handle' ] );
$router->get( '/sites', [ $sites_handler, 'handle' ] );
$router->get( '/sites/{id}', [ $site_handler, 'handle' ] );
$router->get( '/plugins', [ $plugins_handler, 'handle' ] );
$router->get( '/themes', [ $themes_handler, 'handle' ] );
$router->get( '/networks', [ $networks_handler, 'handle' ] );
$router->get( '/networks/{id}', [ $network_handler, 'handle' ] );

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
$path = rtrim( $path, '/' ) ?: '/';

$result = $router->match( $method, $path );

if ( $result === null ) {
	$code = $router->pathExists( $path ) ? 405 : 404;
	http_response_code( $code );
	header( 'Content-Type: application/json' );
	echo json_encode(
		[
			'error' => $code === 405 ? 'method_not_allowed' : 'not_found',
			'message' => $code === 405 ? 'Method not allowed.' : 'Endpoint not found.',
		],
	);
	exit();
}

[ $handler, $params ] = $result;

try {
	$handler( $params );
} catch ( Throwable $throwable ) {
	http_response_code( 500 );
	header( 'Content-Type: application/json' );
	echo json_encode(
		[
			'error' => 'internal_error',
			'message' => 'An internal error occurred.',
		],
	);

	error_log( (string) $throwable );
}
