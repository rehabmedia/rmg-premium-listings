<?php
/**
 * RMG Premium Listings Admin
 *
 * Handles admin settings page for embed configuration.
 *
 * @package RMG_Premium_Listings
 */

namespace RMG_Premium_Listings;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Class
 */
class Admin {
	/**
	 * Option name for storing saved configurations.
	 */
	const OPTION_NAME = 'rmg_premium_listings_embed_configs';

	/**
	 * Initialize the admin system.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_form_submission' ) );
		add_action( 'wp_ajax_rmg_validate_json', array( __CLASS__, 'ajax_validate_json' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public static function add_admin_menu(): void {
		add_options_page(
			__( 'RMG Premium Listings', 'rmg-premium-listings' ),
			__( 'Premium Listings', 'rmg-premium-listings' ),
			'manage_options',
			'rmg-premium-listings-embed',
			array( __CLASS__, 'render_admin_page' )
		);
	}


	/**
	 * Handle form submission.
	 */
	public static function handle_form_submission(): void {
		if ( ! isset( $_POST['rmg_embed_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'rmg_embed_settings' ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['rmg_embed_action'] ) );

		switch ( $action ) {
			case 'save':
				self::handle_save_config();
				break;
			case 'delete':
				self::handle_delete_config();
				break;
		}
	}

	/**
	 * Sanitize multiple space-separated CSS class names.
	 *
	 * @param string $classes Space-separated class names.
	 * @return string Sanitized space-separated class names.
	 */
	private static function sanitize_html_classes( string $classes ): string {
		$classes = trim( $classes );
		if ( empty( $classes ) ) {
			return '';
		}

		// Split on whitespace, sanitize each class, filter out empties, rejoin with space.
		$class_array = array_filter(
			array_map(
				'sanitize_html_class',
				preg_split( '/\s+/', $classes )
			)
		);

		return implode( ' ', $class_array );
	}

	/**
	 * Handle saving a configuration.
	 */
	private static function handle_save_config(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission().
		$config_name = isset( $_POST['config_name'] ) ? sanitize_text_field( wp_unslash( $_POST['config_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission().
		$config_json = isset( $_POST['config_json'] ) ? wp_unslash( $_POST['config_json'] ) : '';

		if ( empty( $config_name ) || empty( $config_json ) ) {
			add_settings_error(
				'rmg_embed_settings',
				'missing_fields',
				__( 'Configuration name and JSON are required.', 'rmg-premium-listings' ),
				'error'
			);
			return;
		}

		// Validate JSON.
		$config = json_decode( $config_json, true );
		if ( null === $config ) {
			add_settings_error(
				'rmg_embed_settings',
				'invalid_json',
				__( 'Invalid JSON format.', 'rmg-premium-listings' ),
				'error'
			);
			return;
		}

		// Validate config structure.
		$validated = Embed::validate_config( $config );
		if ( \is_wp_error( $validated ) ) {
			add_settings_error(
				'rmg_embed_settings',
				'invalid_config',
				$validated->get_error_message(),
				'error'
			);
			return;
		}

		// Capture override parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission().
		$referrer = isset( $_POST['referrer_site'] ) ? sanitize_text_field( wp_unslash( $_POST['referrer_site'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission().
		$state = isset( $_POST['state_override'] ) ? sanitize_text_field( wp_unslash( $_POST['state_override'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission().
		$city = isset( $_POST['city_override'] ) ? sanitize_text_field( wp_unslash( $_POST['city_override'] ) ) : '';

		// Get existing configs.
		$configs = get_option( self::OPTION_NAME, array() );

		// Save config with override parameters (styling now in config.displayOptions).
		$configs[ $config_name ] = array(
			'config'    => $config,
			'overrides' => array(
				'referrer' => $referrer,
				'state'    => $state,
				'city'     => $city,
			),
		);
		update_option( self::OPTION_NAME, $configs );

		// Store last saved config in transient for form repopulation.
		set_transient(
			'rmg_last_saved_config_' . get_current_user_id(),
			array(
				'name'     => $config_name,
				'json'     => $config_json,
				'referrer' => $referrer,
				'state'    => $state,
				'city'     => $city,
			),
			300 // 5 minutes.
		);

		add_settings_error(
			'rmg_embed_settings',
			'config_saved',
			sprintf(
				/* translators: %s: configuration name */
				__( 'Configuration "%s" saved successfully.', 'rmg-premium-listings' ),
				esc_html( $config_name )
			),
			'success'
		);
	}

	/**
	 * Handle deleting a configuration.
	 */
	private static function handle_delete_config(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in handle_form_submission().
		$config_name = isset( $_POST['config_to_delete'] ) ? sanitize_text_field( wp_unslash( $_POST['config_to_delete'] ) ) : '';

		if ( empty( $config_name ) ) {
			return;
		}

		$configs = get_option( self::OPTION_NAME, array() );

		if ( isset( $configs[ $config_name ] ) ) {
			unset( $configs[ $config_name ] );
			update_option( self::OPTION_NAME, $configs );

			add_settings_error(
				'rmg_embed_settings',
				'config_deleted',
				sprintf(
					/* translators: %s: configuration name */
					__( 'Configuration "%s" deleted successfully.', 'rmg-premium-listings' ),
					esc_html( $config_name )
				),
				'success'
			);
		}
	}

	/**
	 * AJAX handler for JSON validation.
	 */
	public static function ajax_validate_json(): void {
		check_ajax_referer( 'rmg_premium_listings_admin', 'nonce' );

		$json = isset( $_POST['json'] ) ? wp_unslash( $_POST['json'] ) : '';

		$config = json_decode( $json, true );
		if ( null === $config ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON format', 'rmg-premium-listings' ) ) );
		}

		$validated = Embed::validate_config( $config );
		if ( \is_wp_error( $validated ) ) {
			wp_send_json_error( array( 'message' => $validated->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Configuration is valid!', 'rmg-premium-listings' ) ) );
	}

	/**
	 * Render the admin page.
	 */
	public static function render_admin_page(): void {
		$configs        = get_option( self::OPTION_NAME, array() );
		$default_config = Embed::encode_config( array() );

		// Get last saved values for form repopulation.
		$last_saved = get_transient( 'rmg_last_saved_config_' . get_current_user_id() );
		if ( false === $last_saved ) {
			$last_saved = array(
				'name'             => '',
				'json'             => '',
				'referrer'         => '',
				'state'            => '',
				'city'             => '',
				'classname'        => '',
				'background_color' => '',
				'border_color'     => '',
				'text_color'       => '',
				'font_family'      => '',
			);
		}

		// Clear transient after retrieving.
		delete_transient( 'rmg_last_saved_config_' . get_current_user_id() );

		require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/admin/views/settings-page.php';
	}

	/**
	 * Get saved configurations.
	 *
	 * @return array Saved configurations.
	 */
	public static function get_saved_configs(): array {
		return get_option( self::OPTION_NAME, array() );
	}
}
