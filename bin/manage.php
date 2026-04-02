<?php
/**
 * CLI management tool for Site Bookkeeper Hub.
 *
 * Commands:
 *   site:add <url> [--label=<label>]       Register a new site
 *   site:list                              List all registered sites
 *   site:rotate-token <url>                Rotate a site's bearer token
 *   client:add [--label=<label>]           Create a new client read token
 *   network:add <url> [--label=<label>]    Register a new network
 *   network:list                           List all registered networks
 *   network:rotate-token <url>             Rotate a network's bearer token
 *   vuln:sync [provider]                   Sync vulnerability data
 *
 * @phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Apermo\SiteBookkeeperHub\Storage\Database;
use Apermo\SiteBookkeeperHub\Storage\NetworkRepository;
use Apermo\SiteBookkeeperHub\Storage\SiteRepository;
use Apermo\SiteBookkeeperHub\Vulnerability\VulnerabilityManager;
use Apermo\SiteBookkeeperHub\Vulnerability\VulnerabilityRepository;
use Apermo\SiteBookkeeperHub\Vulnerability\WordfenceProvider;
use Apermo\SiteBookkeeperHub\Vulnerability\WPScanProvider;

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
$database = new Database( $database_path );
$database->migrate();
$repo = new SiteRepository( $database );
$network_repo = new NetworkRepository( $database );

$command = $argv[1] ?? '';

switch ( $command ) {
	case 'site:add':
		handle_site_add( $repo, array_slice( $argv, 2 ) );
		break;
	case 'site:list':
		handle_site_list( $repo );
		break;
	case 'site:rotate-token':
		handle_site_rotate_token( $repo, array_slice( $argv, 2 ) );
		break;
	case 'client:add':
		handle_client_add( $repo, array_slice( $argv, 2 ) );
		break;
	case 'network:add':
		handle_network_add( $network_repo, array_slice( $argv, 2 ) );
		break;
	case 'network:list':
		handle_network_list( $network_repo );
		break;
	case 'network:rotate-token':
		handle_network_rotate_token( $network_repo, array_slice( $argv, 2 ) );
		break;
	case 'vuln:sync':
		handle_vuln_sync( $database, array_slice( $argv, 2 ) );
		break;
	default:
		fwrite( STDERR, "Usage: php bin/manage.php <command>\n\n" );
		fwrite( STDERR, "Commands:\n" );
		fwrite( STDERR, "  site:add <url> [--label=<label>]\n" );
		fwrite( STDERR, "  site:list\n" );
		fwrite( STDERR, "  site:rotate-token <url>\n" );
		fwrite( STDERR, "  client:add [--label=<label>]\n" );
		fwrite( STDERR, "  network:add <url> [--label=<label>]\n" );
		fwrite( STDERR, "  network:list\n" );
		fwrite( STDERR, "  network:rotate-token <url>\n" );
		fwrite( STDERR, "  vuln:sync [provider]\n" );
		exit( 1 );
}

/**
 * Register a new site.
 *
 * @param SiteRepository     $repo Repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_site_add( SiteRepository $repo, array $arguments ): void {
	$url = '';
	$label = null;

	foreach ( $arguments as $argument ) {
		if ( str_starts_with( $argument, '--label=' ) ) {
			$label = substr( $argument, 8 );
		} elseif ( $url === '' ) {
			$url = $argument;
		}
	}

	if ( $url === '' ) {
		fwrite( STDERR, "Error: URL is required.\n" );
		fwrite( STDERR, "Usage: php bin/manage.php site:add <url> [--label=<label>]\n" );
		exit( 1 );
	}

	$existing = $repo->findSiteByUrl( $url );
	if ( $existing !== null ) {
		fwrite( STDERR, "Error: Site '{$url}' already exists.\n" );
		exit( 1 );
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );
	$uuid = generate_uuid();

	$repo->addSite( $uuid, $url, $hash, $label );

	echo "Site registered successfully.\n";
	echo "ID:    {$uuid}\n";
	echo "URL:   {$url}\n";
	if ( $label !== null ) {
		echo "Label: {$label}\n";
	}
	echo "\nBearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * List all registered sites.
 *
 * @param SiteRepository $repo Repository.
 *
 * @return void
 */
function handle_site_list( SiteRepository $repo ): void {
	$sites = $repo->getAllSites();

	if ( $sites === [] ) {
		echo "No sites registered.\n";
		return;
	}

	foreach ( $sites as $site ) {
		$label = $site->label !== null ? " ({$site->label})" : '';
		echo "{$site->id}  {$site->siteUrl}{$label}\n";
	}

	echo "\nTotal: " . count( $sites ) . " site(s)\n";
}

/**
 * Rotate a site's bearer token.
 *
 * @param SiteRepository     $repo Repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_site_rotate_token( SiteRepository $repo, array $arguments ): void {
	$url = $arguments[0] ?? '';

	if ( $url === '' ) {
		fwrite( STDERR, "Error: URL is required.\n" );
		fwrite( STDERR, "Usage: php bin/manage.php site:rotate-token <url>\n" );
		exit( 1 );
	}

	$site = $repo->findSiteByUrl( $url );
	if ( $site === null ) {
		fwrite( STDERR, "Error: Site '{$url}' not found.\n" );
		exit( 1 );
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );

	$repo->updateSiteTokenHash( $site->id, $hash );

	echo "Token rotated for {$url}.\n";
	echo "\nNew bearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * Create a new client read token.
 *
 * @param SiteRepository     $repo Repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_client_add( SiteRepository $repo, array $arguments ): void {
	$label = null;

	foreach ( $arguments as $argument ) {
		if ( str_starts_with( $argument, '--label=' ) ) {
			$label = substr( $argument, 8 );
		}
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );

	$id = $repo->addClientToken( $hash, $label );

	echo "Client token created.\n";
	echo "ID: {$id}\n";
	if ( $label !== null ) {
		echo "Label: {$label}\n";
	}
	echo "\nBearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * Register a new network.
 *
 * @param NetworkRepository  $repo      Network repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_network_add( NetworkRepository $repo, array $arguments ): void {
	$url = '';
	$label = null;

	foreach ( $arguments as $argument ) {
		if ( str_starts_with( $argument, '--label=' ) ) {
			$label = substr( $argument, 8 );
		} elseif ( $url === '' ) {
			$url = $argument;
		}
	}

	if ( $url === '' ) {
		fwrite( STDERR, "Error: URL is required.\n" );
		fwrite( STDERR, "Usage: php bin/manage.php network:add <url> [--label=<label>]\n" );
		exit( 1 );
	}

	$existing = $repo->findNetworkByMainSiteUrl( $url );
	if ( $existing !== null ) {
		fwrite( STDERR, "Error: Network '{$url}' already exists.\n" );
		exit( 1 );
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );
	$uuid = generate_uuid();

	$repo->addNetwork( $uuid, $url, $hash, $label );

	echo "Network registered successfully.\n";
	echo "ID:    {$uuid}\n";
	echo "URL:   {$url}\n";
	if ( $label !== null ) {
		echo "Label: {$label}\n";
	}
	echo "\nBearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * List all registered networks.
 *
 * @param NetworkRepository $repo Network repository.
 *
 * @return void
 */
function handle_network_list( NetworkRepository $repo ): void {
	$networks = $repo->getAllNetworks();

	if ( $networks === [] ) {
		echo "No networks registered.\n";
		return;
	}

	foreach ( $networks as $network ) {
		$label = $network->label !== null ? " ({$network->label})" : '';
		echo "{$network->id}  {$network->mainSiteUrl}{$label}\n";
	}

	echo "\nTotal: " . count( $networks ) . " network(s)\n";
}

/**
 * Rotate a network's bearer token.
 *
 * @param NetworkRepository  $repo      Network repository.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_network_rotate_token( NetworkRepository $repo, array $arguments ): void {
	$url = $arguments[0] ?? '';

	if ( $url === '' ) {
		fwrite( STDERR, "Error: URL is required.\n" );
		fwrite( STDERR, "Usage: php bin/manage.php network:rotate-token <url>\n" );
		exit( 1 );
	}

	$network = $repo->findNetworkByMainSiteUrl( $url );
	if ( $network === null ) {
		fwrite( STDERR, "Error: Network '{$url}' not found.\n" );
		exit( 1 );
	}

	$token = bin2hex( random_bytes( 32 ) );
	$hash = password_hash( $token, PASSWORD_ARGON2ID );

	$repo->updateNetworkTokenHash( $network->id, $hash );

	echo "Token rotated for {$url}.\n";
	echo "\nNew bearer token (save this — it cannot be retrieved later):\n";
	echo "{$token}\n";
}

/**
 * Build a VulnerabilityManager with configured providers.
 *
 * @param Database $database Database connection.
 *
 * @return VulnerabilityManager
 */
function build_vuln_manager( Database $database ): VulnerabilityManager {
	$vuln_repo = new VulnerabilityRepository( $database );
	$manager = new VulnerabilityManager( $vuln_repo );

	$providers = array_filter( explode( ',', (string) getenv( 'VULN_PROVIDERS' ) ) );

	foreach ( $providers as $name ) {
		$provider = build_vuln_provider( trim( $name ), $vuln_repo );
		if ( $provider !== null ) {
			$manager->addProvider( $provider );
		}
	}

	return $manager;
}

/**
 * Build a single vulnerability provider by name.
 *
 * @param string                  $name      Provider name.
 * @param VulnerabilityRepository $vuln_repo Repository instance.
 *
 * @return VulnerabilityProvider|null
 */
function build_vuln_provider( string $name, VulnerabilityRepository $vuln_repo ): ?VulnerabilityProvider {
	if ( $name === 'wordfence' ) {
		$wordfence_key = getenv( 'WORDFENCE_API_KEY' ) ?: null;

		return new WordfenceProvider( $vuln_repo, $wordfence_key );
	}

	if ( $name === 'wpscan' ) {
		$wpscan_key = (string) getenv( 'WPSCAN_API_KEY' );
		if ( $wpscan_key === '' ) {
			return null;
		}
		$wpscan_hours = (int) ( getenv( 'WPSCAN_SYNC_HOURS' ) ?: 24 );
		$wpscan_include = getenv( 'WPSCAN_SLUG_INCLUDE' ) ?: null;
		$wpscan_exclude = getenv( 'WPSCAN_SLUG_EXCLUDE' ) ?: null;

		return new WPScanProvider( $vuln_repo, $wpscan_key, $wpscan_hours, $wpscan_include, $wpscan_exclude );
	}

	return null;
}

/**
 * Sync vulnerability data from configured providers.
 *
 * @param Database           $database  Database connection.
 * @param array<int, string> $arguments CLI arguments.
 *
 * @return void
 */
function handle_vuln_sync( Database $database, array $arguments ): void {
	$manager = build_vuln_manager( $database );
	$names = $manager->getProviderNames();

	if ( $names === [] ) {
		fwrite( STDERR, "No vulnerability providers configured.\n" );
		fwrite( STDERR, "Set VULN_PROVIDERS in your .env (e.g. VULN_PROVIDERS=wordfence,wpscan).\n" );
		exit( 1 );
	}

	$target = $arguments[0] ?? null;

	if ( $target !== null ) {
		echo "Syncing provider: {$target}\n";
		if ( ! $manager->syncProvider( $target ) ) {
			fwrite( STDERR, "Unknown provider: {$target}\n" );
			fwrite( STDERR, 'Configured: ' . implode( ', ', $names ) . "\n" );
			exit( 1 );
		}
		echo "Done.\n";
		return;
	}

	echo 'Syncing all providers: ' . implode( ', ', $names ) . "\n";
	$manager->syncAll();
	echo "Done.\n";
}

/**
 * Generate a v4 UUID.
 *
 * @return string
 */
function generate_uuid(): string {
	$data = random_bytes( 16 );
	$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
	$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
}
