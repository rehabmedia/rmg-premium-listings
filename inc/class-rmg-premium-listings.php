<?php
/**
 * Main RMG Premium Listings Class
 *
 * @package RMG_Premium_Listings
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RMG Premium Listings Class
 * Handles premium facility listings with ElasticSearch integration and customizable displays
 */
class RMG_Premium_Listings {
	/**
	 * RMG_Premium_Listings Instance
	 *
	 * @var RMG_Premium_Listings|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return RMG_Premium_Listings The singleton instance.
	 */
	public static function get_instance(): RMG_Premium_Listings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize the premium listings system.
	 */
	public function init(): void {
		// Include required files.
		$this->includes();

		// Hook into WordPress.
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
	}

	/**
	 * Include required files.
	 */
	private function includes(): void {
		// ES and REST functionality.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/es/class-rmg-premium-listings-es-query.php';
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/es/class-rmg-premium-listings-cards-registry.php';
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/rest/class-rmg-premium-listings-cards-endpoint.php';

		// Helper functions.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-rmg-premium-listings-helpers.php';

		// Renderer.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-rmg-premium-listings-cards-renderer.php';
	}

	/**
	 * Register blocks.
	 */
	public function register_blocks(): void {
		// Register listing-cards block.
		if ( file_exists( RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'build/listing-cards/block.json' ) ) {
			register_block_type( RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'build/listing-cards' );
		}
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_assets(): void {
		// Placeholder for editor assets if needed.
	}

	/**
	 * Plugin activation hook.
	 */
	public static function activate(): void {
		// Set plugin version.
		update_option( 'rmg_premium_listings_version', RMG_PREMIUM_LISTINGS_VERSION );

		// Flush rewrite rules to ensure REST API endpoints work.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook.
	 */
	public static function deactivate(): void {
		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}
