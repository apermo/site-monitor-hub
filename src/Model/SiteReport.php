<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub\Model;

/**
 * DTO representing an incoming site health report.
 */
class SiteReport {

	/**
	 * Build a SiteReport from the decoded JSON payload.
	 *
	 * @param int                                                                                                      $schemaVersion Schema version.
	 * @param string                                                                                                   $timestamp     ISO 8601 timestamp.
	 * @param string                                                                                                   $siteUrl       Site URL.
	 * @param array<string, mixed>                                                                                     $environment   WP/PHP version info.
	 * @param array<int, array<string, mixed>>                                                                         $plugins       Plugin list.
	 * @param array<int, array<string, mixed>>                                                                         $themes        Theme list.
	 * @param array<int, array<string, mixed>>                                                                         $customFields  Custom field list.
	 * @param array<int, array<string, mixed>>                                                                         $users         User list.
	 * @param array<int, array{slug: string, name: string, capabilities: mixed, is_custom?: bool, is_modified?: bool}> $roles Role list.
	 */
	public function __construct(
		public readonly int $schemaVersion,
		public readonly string $timestamp,
		public readonly string $siteUrl,
		public readonly array $environment,
		public readonly array $plugins = [],
		public readonly array $themes = [],
		public readonly array $customFields = [],
		public readonly array $users = [],
		public readonly array $roles = [],
	) {
	}

	/**
	 * Create a SiteReport from a decoded JSON array.
	 *
	 * @param array<string, mixed> $data Decoded JSON payload.
	 *
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		return new self(
			schemaVersion: (int) ( $data['schema_version'] ?? 1 ),
			timestamp: (string) ( $data['timestamp'] ?? '' ),
			siteUrl: (string) ( $data['site_url'] ?? '' ),
			environment: (array) ( $data['environment'] ?? [] ),
			plugins: (array) ( $data['plugins'] ?? [] ),
			themes: (array) ( $data['themes'] ?? [] ),
			customFields: (array) ( $data['custom_fields'] ?? [] ),
			users: (array) ( $data['users'] ?? [] ),
			roles: (array) ( $data['roles'] ?? [] ),
		);
	}
}
