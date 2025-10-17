<?php
/**
 * RMG Premium Listings Asset Manager
 *
 * Handles enqueuing of CSS and JavaScript assets based on context.
 *
 * @package RMG_Premium_Listings
 */

namespace RMG_Premium_Listings;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Asset Manager Class
 */
class Asset_Manager {
	/**
	 * Asset types configuration.
	 *
	 * @var array
	 */
	private const ASSET_TYPES = array(
		'css' => array(
			'dir'                => 'build/css/',
			'extension'          => '.css',
			'function'           => 'wp_enqueue_style',
			'require_asset_file' => false,
		),
		'js'  => array(
			'dir'                => 'build/js/',
			'extension'          => '.js',
			'function'           => 'wp_enqueue_script',
			'require_asset_file' => true,
			'in_footer'          => true,
		),
	);

	/**
	 * File loading context configuration.
	 *
	 * Context mapping:
	 * - view.css   → Frontend only
	 * - editor.css → Block editor only
	 * - admin.css  → All admin pages + Block editor
	 * - index.css  → Frontend + Block editor
	 *
	 * @var array
	 */
	private const FILE_CONTEXTS = array(
		'view'   => array( 'frontend' ),
		'editor' => array( 'editor' ),
		'admin'  => array( 'admin', 'editor' ),
		'index'  => array( 'frontend', 'editor' ),
	);

	/**
	 * Current context.
	 *
	 * @var string|null
	 */
	private $current_context = null;

	/**
	 * Initialize the asset manager.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets(): void {
		$this->current_context = 'frontend';
		$this->enqueue_assets();
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin_assets(): void {
		$this->current_context = 'admin';
		$this->enqueue_assets();
	}

	/**
	 * Enqueue editor assets.
	 */
	public function enqueue_editor_assets(): void {
		$this->current_context = 'editor';
		$this->enqueue_assets();
	}

	/**
	 * Enqueue css and js assets based on current context.
	 */
	private function enqueue_assets(): void {
		foreach ( self::ASSET_TYPES as $type => $config ) {
			$this->enqueue_assets_by_type( $type, $config );
		}
	}

	/**
	 * Enqueue assets by type.
	 *
	 * @param string $type   Asset type identifier.
	 * @param array  $config Type configuration.
	 */
	private function enqueue_assets_by_type( string $type, array $config ): void {
		$dir = RMG_PREMIUM_LISTINGS_PLUGIN_DIR . $config['dir'];

		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . '*' . $config['extension'] );

		if ( empty( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( $this->should_load_in_context( $file, $config['extension'] ) ) {
				$this->enqueue_single_asset( $file, $type, $config );
			}
		}
	}

	/**
	 * Determine if a file should be loaded in the current context.
	 *
	 * @param string $file      Full path to the asset file.
	 * @param string $extension File extension including dot.
	 * @return bool
	 */
	private function should_load_in_context( string $file, string $extension ): bool {
		$filename  = basename( $file, $extension );
		$base_name = $this->extract_base_name( $filename );
		$contexts  = $this->get_file_contexts( $base_name );

		return in_array( $this->current_context, $contexts, true );
	}

	/**
	 * Extract the base name from a filename, removing common suffixes.
	 *
	 * @param string $filename The filename to process.
	 * @return string
	 */
	private function extract_base_name( string $filename ): string {
		// Remove common suffixes like .min, .bundle, etc.
		$filename = preg_replace( '/\.(min|bundle|dist|prod|dev)$/', '', $filename );

		// For compound names like "editor-styles", get the first part.
		$parts = explode( '-', $filename );

		if ( isset( self::FILE_CONTEXTS[ $parts[0] ] ) ) {
			return $parts[0];
		}

		return $filename;
	}

	/**
	 * Get the loading contexts for a file based on its name.
	 *
	 * @param string $base_name The base name of the file.
	 * @return array
	 */
	private function get_file_contexts( string $base_name ): array {
		if ( isset( self::FILE_CONTEXTS[ $base_name ] ) ) {
			return self::FILE_CONTEXTS[ $base_name ];
		}

		// Check for pattern matches.
		foreach ( self::FILE_CONTEXTS as $pattern => $contexts ) {
			if ( strpos( $base_name, $pattern ) === 0 ) {
				return $contexts;
			}
		}

		// Default: load in frontend and editor.
		return array( 'frontend', 'editor' );
	}

	/**
	 * Enqueue a single asset file.
	 *
	 * @param string $file   Full path to the asset file.
	 * @param string $type   Asset type identifier.
	 * @param array  $config Type configuration.
	 */
	private function enqueue_single_asset( string $file, string $type, array $config ): void {
		$filename   = basename( $file, $config['extension'] );
		$asset_file = dirname( $file ) . '/' . $filename . '.asset.php';

		$asset_data = $this->get_asset_data( $asset_file, $config['require_asset_file'] );

		if ( null === $asset_data ) {
			return;
		}

		// Add jQuery as dependency for admin JS files.
		$dependencies = $asset_data['dependencies'];
		if ( 'js' === $type && 'admin' === $filename ) {
			$dependencies[] = 'jquery';
		}

		$handle = 'rmg-premium-listings-' . $this->current_context . '-' . $filename;
		$url    = RMG_PREMIUM_LISTINGS_PLUGIN_URL . $config['dir'] . basename( $file );

		$args = array(
			$handle,
			$url,
			$dependencies,
			$asset_data['version'] ?? RMG_PREMIUM_LISTINGS_VERSION,
		);

		if ( 'js' === $type && ! empty( $config['in_footer'] ) ) {
			$args[] = $config['in_footer'];
		}

		call_user_func_array( $config['function'], $args );

		// Localize script for admin JS.
		if ( 'js' === $type && 'admin' === $this->current_context && 'admin' === $filename ) {
			wp_localize_script(
				$handle,
				'rmgPremiumListings',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'rmg_premium_listings_admin' ),
				)
			);
		}
	}

	/**
	 * Get asset data from asset file or defaults.
	 *
	 * @param string $asset_file Path to asset file.
	 * @param bool   $required   Whether asset file is required.
	 * @return array|null Asset data or null if required file missing.
	 */
	private function get_asset_data( string $asset_file, bool $required ): ?array {
		if ( file_exists( $asset_file ) ) {
			return require $asset_file;
		}

		if ( $required ) {
			return null;
		}

		return array(
			'dependencies' => array(),
			'version'      => RMG_PREMIUM_LISTINGS_VERSION,
		);
	}
}
