<?php
/**
 * Registry for tracking displayed post IDs within page load only.
 *
 * @package rmg-premium-listings
 */

namespace RMG_Premium_Listings;

/**
 * Registry class for tracking displayed listing cards.
 *
 * Manages a registry of displayed post IDs to prevent duplicate listings
 * within a single page load. Uses static storage to ensure data is only
 * persisted for the current request.
 *
 * @since 1.0.0
 */
class Cards_Registry {

	/**
	 * Static storage for current page load only.
	 *
	 * Stores displayed post IDs grouped by context key.
	 * Structure: array<string, array<int>>
	 *
	 * @var array<string, array<int>>
	 */
	private static array $current_page_displayed = array();

	/**
	 * Get context key for the current request.
	 *
	 * Generates a unique context key based on the request type:
	 * - For REST requests: Uses display_context parameter or referer path
	 * - For regular requests: Uses the current REQUEST_URI
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized context key.
	 */
	public static function get_context_key(): string {
		// For REST requests, check if context was passed.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$context = $_REQUEST['display_context'] ?? null;
			if ( $context ) {
				return sanitize_key( $context );
			}

			$referer = wp_get_referer();
			if ( $referer ) {
				$parsed = wp_parse_url( $referer );
				return md5( $parsed['path'] ?? '/' );
			}
		}

		// For regular requests, use the current URL.
		return md5( $_SERVER['REQUEST_URI'] ?? '/' );
	}

	/**
	 * Register displayed post IDs.
	 *
	 * Adds post IDs to the registry for the given context. IDs are stored
	 * in static memory and persist only for the current page load.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int>|int[] $ids     Array of post IDs to register.
	 * @param string|null      $context Optional context key. If null, uses get_context_key().
	 *
	 * @return void
	 */
	public static function register_displayed( array $ids, ?string $context = null ): void {
		if ( empty( $ids ) ) {
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// Debug: Check what context we're in.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging.
			error_log( 'Register called - REST_REQUEST: ' . ( defined( 'REST_REQUEST' ) ? 'true' : 'false' ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging.
			error_log( 'IDs to register: ' . implode( ',', $ids ) );
		}

		$context_key = $context ?? self::get_context_key();

		// ALWAYS use static storage - never transients.
		// This ensures exclusions only work within the current page load.
		if ( ! isset( self::$current_page_displayed[ $context_key ] ) ) {
			self::$current_page_displayed[ $context_key ] = array();
		}
		self::$current_page_displayed[ $context_key ] = array_unique(
			array_merge(
				self::$current_page_displayed[ $context_key ],
				array_map( 'intval', $ids )
			)
		);
	}

	/**
	 * Get displayed post IDs for a context.
	 *
	 * Retrieves all post IDs that have been registered as displayed
	 * for the given context within the current page load.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $context Optional context key. If null, uses get_context_key().
	 *
	 * @return array<int> Array of displayed post IDs.
	 */
	public static function get_displayed( ?string $context = null ): array {
		$context_key = $context ?? self::get_context_key();

		// ALWAYS use static storage - never transients.
		$displayed = self::$current_page_displayed[ $context_key ] ?? array();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// Debug: Check what we're retrieving.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging.
			error_log( 'Get displayed - REST_REQUEST: ' . ( defined( 'REST_REQUEST' ) ? 'true' : 'false' ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Intentional debug logging.
			error_log( 'Displayed: ' . json_encode( $displayed ) );
		}

		return $displayed;
	}

	/**
	 * Clear displayed post IDs from the registry.
	 *
	 * Removes stored post IDs either for a specific context or all contexts.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $context Optional context key to clear. If null, clears all contexts.
	 *
	 * @return void
	 */
	public static function clear( ?string $context = null ): void {
		if ( null === $context ) {
			// Clear all static storage.
			self::$current_page_displayed = array();
			return;
		}

		$context_key = $context ?? self::get_context_key();
		unset( self::$current_page_displayed[ $context_key ] );
	}
}
