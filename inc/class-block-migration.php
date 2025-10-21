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
	 */
	public static function init() {
		// Register class aliases for backward compatibility.
		self::register_class_aliases();

		add_filter( 'render_block', array( __CLASS__, 'migrate_legacy_block' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_migration_script' ) );
	}

	/**
	 * Register class aliases for backward compatibility.
	 *
	 * Creates aliases so old class names still work with new implementations.
	 */
	public static function register_class_aliases() {
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
	public static function migrate_legacy_block( $block_content, $block ) {
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
	 * Enqueue editor script to handle block transformation.
	 */
	public static function enqueue_migration_script() {
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
