<?php
/**
 * Block Migration Handler
 *
 * Handles migration from rmg-blocks/listing-cards-v2 to rmg-premium-listings/cards
 *
 * @package RMG_Premium_Listings
 */

namespace RMG_Premium_Listings;

/**
 * Block_Migration class
 */
class Block_Migration {
	/**
	 * Initialize the block migration.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Register class aliases for backward compatibility.
		self::register_class_aliases();

		add_filter( 'render_block', array( __CLASS__, 'migrate_legacy_block' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_migration_script' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_legacy_rest_routes' ) );
		add_filter( 'rmg_premium_listings_wrapper_classes', array( __CLASS__, 'add_legacy_wrapper_classes' ) );
	}

	/**
	 * Register class aliases for backward compatibility.
	 *
	 * Creates aliases so old class names still work with new implementations.
	 *
	 * @return void
	 */
	public static function register_class_aliases(): void {
		// Create alias for the old Listing_Cards_V2 class to point to Cards_Renderer.
		if ( ! class_exists( 'Listing_Cards_V2' ) && class_exists( 'RMG_Premium_Listings\Cards_Renderer' ) ) {
			class_alias( 'RMG_Premium_Listings\Cards_Renderer', 'Listing_Cards_V2' );
		}

		// Also create namespaced versions for consistency.
		if ( ! class_exists( 'RMG_Blocks\Listing_Cards_V2' ) && class_exists( 'RMG_Premium_Listings\Cards_Renderer' ) ) {
			class_alias( 'RMG_Premium_Listings\Cards_Renderer', 'RMG_Blocks\Listing_Cards_V2' );
		}
	}

	/**
	 * Migrate legacy block on render
	 *
	 * @param string $block_content The block content.
	 * @param array  $block         The full block, including name and attributes.
	 * @return string Modified block content.
	 */
	public static function migrate_legacy_block( string $block_content, array $block ): string {
		// Check if this is the legacy block.
		if ( 'rmg-blocks/listing-cards-v2' !== $block['blockName'] ) {
			return $block_content;
		}

		// Create a new block array with the new name.
		$migrated_block              = $block;
		$migrated_block['blockName'] = 'rmg-premium-listings/cards';

		// Render the new block with the same attributes.
		return render_block( $migrated_block );
	}

	/**
	 * Add legacy wrapper classes for backward compatibility.
	 *
	 * Adds old block class names to maintain CSS compatibility with existing styles.
	 * This allows sites that have custom CSS targeting the old classes to continue working.
	 *
	 * @param array $class_parts Array of CSS class names.
	 * @return array Modified array of CSS class names.
	 */
	public static function add_legacy_wrapper_classes( array $class_parts ): array {
		// Prepend legacy classes to the array.
		// These are added first so they appear before the new classes in the HTML.
		array_unshift(
			$class_parts,
			'wp-block-rmg-blocks-listing-cards-v2', // Old block wrapper class.
			'listing-cards-v2' // Old base class.
		);

		return $class_parts;
	}

	/**
	 * Register legacy REST API routes.
	 *
	 * Creates alias routes for old endpoint names to redirect to new endpoints.
	 *
	 * @return void
	 */
	public static function register_legacy_rest_routes(): void {
		// Register the old endpoint as an alias to the new one.
		register_rest_route(
			'rmg/v1',
			'/listing-cards-v2',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'proxy_to_new_endpoint' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Proxy legacy REST requests to the new endpoint.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error The response from the new endpoint.
	 */
	public static function proxy_to_new_endpoint( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Log the proxying for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Block Migration: Proxying legacy endpoint /listing-cards-v2 to /premium-listing-cards' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Original params: ' . wp_json_encode( $request->get_params() ) );
		}

		// Create a new request to the updated endpoint.
		$new_request = new \WP_REST_Request( 'POST', '/rmg/v1/premium-listing-cards' );

		// Copy all parameters from the old request.
		// Use get_params() to get the merged view of all parameters.
		foreach ( $request->get_params() as $key => $value ) {
			$new_request->set_param( $key, $value );
		}

		// Copy JSON body if present.
		$body = $request->get_body();
		if ( ! empty( $body ) ) {
			$new_request->set_body( $body );
		}

		// Copy headers.
		foreach ( $request->get_headers() as $key => $value ) {
			$new_request->set_header( $key, $value );
		}

		// Execute the new endpoint and return its response.
		$response = rest_do_request( $new_request );

		// Log response for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$response_data = $response->get_data();
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Proxied response success: ' . ( $response_data['success'] ? 'true' : 'false' ) );
			if ( isset( $response_data['meta'] ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Response meta: ' . wp_json_encode( $response_data['meta'] ) );
			}
		}

		// Return the response data directly if it's a WP_REST_Response.
		return rest_ensure_response( $response );
	}

	/**
	 * Enqueue editor script to handle block transformation.
	 *
	 * @return void
	 */
	public static function enqueue_migration_script(): void {
		self::enqueue_asset(
			'block-migration',
			'js/block-migration.js'
		);
	}

	/**
	 * Enqueue a single asset file.
	 *
	 * Helper method to enqueue assets with proper dependency handling.
	 * Similar pattern to Asset_Manager but specific to migration needs.
	 * This keeps all migration-related code isolated for easy removal later.
	 *
	 * @param string $handle   The script/style handle (without 'rmg-' prefix).
	 * @param string $rel_path Relative path from build directory (e.g., 'js/block-migration.js').
	 * @return void
	 */
	private static function enqueue_asset( string $handle, string $rel_path ): void {
		$file_path  = RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'build/' . $rel_path;
		$asset_file = str_replace( '.js', '.asset.php', $file_path );

		// Bail if the main file doesn't exist.
		if ( ! file_exists( $file_path ) ) {
			return;
		}

		// Get asset data (dependencies and version) from .asset.php file.
		$asset_data = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array(),
				'version'      => RMG_PREMIUM_LISTINGS_VERSION,
			);

		// Build the URL - ensure proper trailing slash handling.
		$asset_url = trailingslashit( RMG_PREMIUM_LISTINGS_PLUGIN_URL ) . 'build/' . $rel_path;

		// Enqueue the script with dependencies.
		wp_enqueue_script(
			'rmg-' . $handle,
			$asset_url,
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);
	}
}
