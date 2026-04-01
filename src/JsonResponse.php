<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorHub;

/**
 * Utility for sending JSON HTTP responses.
 */
class JsonResponse {

	/**
	 * Send a JSON response and terminate.
	 *
	 * @param array<string, mixed> $data   Response payload.
	 * @param int                  $status HTTP status code.
	 *
	 * @return void
	 */
	public static function send( array $data, int $status = 200 ): void {
		\http_response_code( $status );
		\header( 'Content-Type: application/json' );
		echo \json_encode( $data );
	}

	/**
	 * Send a JSON error response and terminate.
	 *
	 * @param string $error   Short error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status code.
	 *
	 * @return void
	 */
	public static function error( string $error, string $message, int $status = 400 ): void {
		self::send(
			[
				'error'   => $error,
				'message' => $message,
			],
			$status,
		);
	}
}
