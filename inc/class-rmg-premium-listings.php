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

		// Initialize asset manager.
		new \RMG_Premium_Listings\Asset_Manager();

		// Initialize embed system.
		\RMG_Premium_Listings\Embed::init();

		// Initialize block migration.
		\RMG_Premium_Listings\Block_Migration::init();

		// Initialize admin if in admin context.
		if ( is_admin() ) {
			\RMG_Premium_Listings\Admin::init();
		}
	}

	/**
	 * Include required files.
	 */
	private function includes(): void {
		// ES and REST functionality.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/es/class-es-query.php';
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/es/class-es-utils.php';
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/es/class-cards-registry.php';
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/rest/class-cards-endpoint.php';

		// Helper functions.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-helpers.php';

		// Renderer.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-cards-renderer.php';

		// Asset manager.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-asset-manager.php';

		// Embed functionality.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-embed.php';

		// Admin functionality.
		if ( is_admin() ) {
			require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/admin/class-admin.php';
		}

		// Block migration handler.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-block-migration.php';
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

		// Initialize embed system to register rewrite rules.
		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-embed.php';
		\RMG_Premium_Listings\Embed::init();

		// Flush rewrite rules to ensure embed endpoint and REST API work.
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
