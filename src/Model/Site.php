<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperHub\Model;

/**
 * Stored site entity.
 */
class Site {

	/**
	 * Build a Site entity.
	 *
	 * @param string      $id        UUID.
	 * @param string      $siteUrl   Site URL.
	 * @param string      $tokenHash Argon2id hash of the bearer token.
	 * @param string|null $label     Human-readable label.
	 * @param string      $createdAt ISO 8601 creation timestamp.
	 * @param string      $updatedAt ISO 8601 last-update timestamp.
	 * @param string|null $networkId Optional network UUID.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $siteUrl,
		public readonly string $tokenHash,
		public readonly ?string $label,
		public readonly string $createdAt,
		public readonly string $updatedAt,
		public readonly ?string $networkId = null,
	) {
	}

	/**
	 * Create a Site from a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 *
	 * @return self
	 */
	public static function fromRow( array $row ): self {
		return new self(
			id: (string) $row['id'],
			siteUrl: (string) $row['site_url'],
			tokenHash: (string) $row['token_hash'],
			label: $row['label'] ?? null,
			createdAt: (string) $row['created_at'],
			updatedAt: (string) $row['updated_at'],
			networkId: $row['network_id'] ?? null,
		);
	}
}
