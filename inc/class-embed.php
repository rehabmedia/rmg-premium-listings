<?php
/**
 * RMG Premium Listings Embed Handler
 *
 * Handles embed template rendering and rewrite rules.
 *
 * @package RMG_Premium_Listings
 */

namespace RMG_Premium_Listings;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Embed Class
 */
class Embed {
	/**
	 * Initialize the embed system.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_embed_template' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
	}

	/**
	 * Add custom rewrite rules for embed endpoint.
	 */
	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^embed/listing-cards/?$',
			'index.php?rmg_embed=listing-cards',
			'top'
		);
	}

	/**
	 * Add custom query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array Modified query vars.
	 */
	public static function add_query_vars( array $vars ): array {
		$vars[] = 'rmg_embed';
		return $vars;
	}

	/**
	 * Handle embed template rendering.
	 */
	public static function handle_embed_template(): void {
		$embed_type = get_query_var( 'rmg_embed' );

		if ( 'listing-cards' !== $embed_type ) {
			return;
		}

		// Get and decode configuration.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$config_encoded = isset( $_GET['config'] ) ? sanitize_text_field( wp_unslash( $_GET['config'] ) ) : '';
		$config         = self::decode_config( $config_encoded );

		// Get optional override parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$referrer = isset( $_GET['referrer'] ) ? sanitize_text_field( wp_unslash( $_GET['referrer'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$city = isset( $_GET['city'] ) ? sanitize_text_field( wp_unslash( $_GET['city'] ) ) : '';

		// Get parent page parameters (passed from embed code for impression tracking).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$parent_url = isset( $_GET['parent_url'] ) ? esc_url_raw( wp_unslash( $_GET['parent_url'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$parent_host = isset( $_GET['parent_host'] ) ? sanitize_text_field( wp_unslash( $_GET['parent_host'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$parent_referrer = isset( $_GET['parent_referrer'] ) ? esc_url_raw( wp_unslash( $_GET['parent_referrer'] ) ) : '';

		// Get styling options.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$bg_color = isset( $_GET['bg_color'] ) ? self::sanitize_css_color( wp_unslash( $_GET['bg_color'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$border_color = isset( $_GET['border_color'] ) ? self::sanitize_css_color( wp_unslash( $_GET['border_color'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$text_color = isset( $_GET['text_color'] ) ? self::sanitize_css_color( wp_unslash( $_GET['text_color'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$font_family = isset( $_GET['font_family'] ) ? sanitize_text_field( wp_unslash( $_GET['font_family'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$heading_color = isset( $_GET['heading_color'] ) ? self::sanitize_css_color( wp_unslash( $_GET['heading_color'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$text_hover_color = isset( $_GET['text_hover_color'] ) ? self::sanitize_css_color( wp_unslash( $_GET['text_hover_color'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$border_hover_color = isset( $_GET['border_hover_color'] ) ? self::sanitize_css_color( wp_unslash( $_GET['border_hover_color'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$padding = isset( $_GET['padding'] ) ? sanitize_text_field( wp_unslash( $_GET['padding'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$margin = isset( $_GET['margin'] ) ? sanitize_text_field( wp_unslash( $_GET['margin'] ) ) : '';

		// Load the embed template.
		self::load_embed_template( $config, $referrer, $state, $city, $parent_url, $parent_host, $parent_referrer, $bg_color, $border_color, $text_color, $font_family, $heading_color, $text_hover_color, $border_hover_color, $padding, $margin );
		exit;
	}

	/**
	 * Sanitize CSS color value.
	 *
	 * Supports hex colors and linear gradients only.
	 *
	 * @param string $color Color value to sanitize.
	 * @return string Sanitized color value or empty string if invalid.
	 */
	private static function sanitize_css_color( string $color ): string {
		$color = trim( $color );

		// Return empty string for empty input.
		if ( empty( $color ) ) {
			return '';
		}

		// Allow hex colors (with optional alpha).
		if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{4}|[a-f0-9]{6}|[a-f0-9]{8})$/i', $color ) ) {
			return sanitize_text_field( $color );
		}

		// Allow linear gradients only.
		if ( preg_match( '/^linear-gradient\s*\([^)]+\)$/i', $color ) ) {
			return sanitize_text_field( $color );
		}

		// Invalid color value.
		return '';
	}

	/**
	 * Decode base64 JSON configuration.
	 *
	 * @param string $encoded Encoded configuration.
	 * @return array Decoded configuration array.
	 */
	private static function decode_config( string $encoded ): array {
		if ( empty( $encoded ) ) {
			return self::get_default_config();
		}

		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded ) {
			return self::get_default_config();
		}

		$config = json_decode( $decoded, true );
		if ( ! is_array( $config ) ) {
			return self::get_default_config();
		}

		return wp_parse_args( $config, self::get_default_config() );
	}

	/**
	 * Get default embed configuration.
	 *
	 * @return array Default configuration.
	 */
	private static function get_default_config(): array {
		return array(
			'layout'        => 'three-column',
			'hasBackground' => false,
			'actionType'    => 'none',
			'isInline'      => false,
			'slidesToShow'  => 3,
			'headline'      => array(
				'show'      => true,
				'text'      => __( 'Featured Facilities Near You', 'rmg-premium-listings' ),
				'alignment' => 'left',
				'tag'       => 2,
			),
			'selectedTerms' => array(
				'amenities'        => array(),
				'clinicalServices' => array(),
				'levelsOfCare'     => array(),
				'paymentOptions'   => array(),
				'programs'         => array(),
				'treatmentOptions' => array(),
			),
			'cardOptions'   => array(
				'hasBackground' => false,
				'showRank'      => true,
				'showAddress'   => true,
				'showInsurance' => true,
			),
		);
	}

	/**
	 * Load the embed template.
	 *
	 * @param array  $config             Configuration array.
	 * @param string $referrer           Referrer site.
	 * @param string $state              State override.
	 * @param string $city               City override.
	 * @param string $parent_url         Parent page URL (for impression tracking).
	 * @param string $parent_host        Parent page host (for impression tracking).
	 * @param string $parent_referrer    Parent page referrer (for impression tracking).
	 * @param string $bg_color           Background color.
	 * @param string $border_color       Border color.
	 * @param string $text_color         Text color.
	 * @param string $font_family        Font family.
	 * @param string $heading_color      Heading color.
	 * @param string $text_hover_color   Text hover color.
	 * @param string $border_hover_color Border hover color.
	 * @param string $padding            Block padding.
	 * @param string $margin             Block margin.
	 */
	private static function load_embed_template( array $config, string $referrer, string $state, string $city, string $parent_url = '', string $parent_host = '', string $parent_referrer = '', string $bg_color = '', string $border_color = '', string $text_color = '', string $font_family = '', string $heading_color = '', string $text_hover_color = '', string $border_hover_color = '', string $padding = '', string $margin = '' ): void {
		// Set up render arguments.
		$render_args = self::build_render_args( $config, $referrer, $state, $city );

		// Load template file.
		$template_path = RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'templates/embed-listing-cards.php';

		if ( ! file_exists( $template_path ) ) {
			wp_die( esc_html__( 'Embed template not found.', 'rmg-premium-listings' ) );
		}

		// Make variables available to template.
		$config          = $config;
		$render_args     = $render_args;
		$referrer        = $referrer;
		$state           = $state;
		$city            = $city;
		$parent_url      = $parent_url;
		$parent_host     = $parent_host;
		$parent_referrer = $parent_referrer;
		$bg_color        = $bg_color;
		$border_color    = $border_color;
		$text_color      = $text_color;
		$font_family     = $font_family;

		include $template_path;
	}

	/**
	 * Build render arguments from configuration.
	 *
	 * @param array  $config   Configuration array.
	 * @param string $referrer Referrer site.
	 * @param string $state    State override.
	 * @param string $city     City override.
	 * @return array Render arguments.
	 */
	private static function build_render_args( array $config, string $referrer, string $state, string $city ): array {
		$render_id  = uniqid( 'listing_cards_embed_', true );
		$card_count = 'slider' === $config['layout'] ? 8 : 3;

		// Determine page type based on parameters.
		$page_type = 'home';
		if ( ! empty( $city ) ) {
			$page_type = 'city';
		} elseif ( ! empty( $state ) ) {
			$page_type = 'state';
		}

		// Location data is required only if neither state nor city are provided.
		$requires_location_data = empty( $state ) && empty( $city );

		$context = array(
			'post_id'                => 0,
			'post_type'              => 'embed',
			'is_admin'               => false,
			'is_preview'             => false,
			'is_embed'               => true,
			'referrer'               => $referrer,
			'page_type'              => $page_type,
			'state'                  => $state,
			'city'                   => $city,
			'requires_location_data' => $requires_location_data,
		);

		return array(
			'render_id'          => $render_id,
			'action_type'        => $config['actionType'],
			'card_count'         => $card_count,
			'card_options'       => $config['cardOptions'],
			'card_data'          => array(),
			'context'            => $context,
			'excluded_post_ids'  => array(),
			'has_background'     => $config['hasBackground'],
			'headline'           => $config['headline'],
			'is_inline'          => $config['isInline'],
			'layout'             => $config['layout'],
			'selected_terms'     => $config['selectedTerms'],
			'slides_to_show'     => $config['slidesToShow'],
			'wrapper_classes'    => array( 'rmg-embed' ),
			'wrapper_attributes' => array(
				'data-referrer' => $referrer,
				'data-state'    => $state,
				'data-city'     => $city,
			),
		);
	}

	/**
	 * Encode configuration to base64 JSON.
	 *
	 * @param array $config Configuration array.
	 * @return string Encoded configuration.
	 */
	public static function encode_config( array $config ): string {
		$json = wp_json_encode( $config );
		return base64_encode( $json );
	}

	/**
	 * Validate configuration against block.json schema.
	 *
	 * @param array $config Configuration to validate.
	 * @return array|WP_Error Validated config or WP_Error on failure.
	 */
	public static function validate_config( array $config ) {
		$errors = array();

		// Validate layout.
		$valid_layouts = array( 'three-column', 'slider', 'vertical' );
		if ( isset( $config['layout'] ) && ! in_array( $config['layout'], $valid_layouts, true ) ) {
			$errors[] = sprintf(
				/* translators: %s: comma-separated list of valid layouts */
				__( 'Invalid layout. Must be one of: %s', 'rmg-premium-listings' ),
				implode( ', ', $valid_layouts )
			);
		}

		// Validate actionType.
		$valid_actions = array( 'none', 'filter', 'tabs' );
		if ( isset( $config['actionType'] ) && ! in_array( $config['actionType'], $valid_actions, true ) ) {
			$errors[] = sprintf(
				/* translators: %s: comma-separated list of valid action types */
				__( 'Invalid actionType. Must be one of: %s', 'rmg-premium-listings' ),
				implode( ', ', $valid_actions )
			);
		}

		// Validate headline if present.
		if ( isset( $config['headline'] ) ) {
			if ( ! is_array( $config['headline'] ) ) {
				$errors[] = __( 'headline must be an object/array', 'rmg-premium-listings' );
			} elseif ( isset( $config['headline']['tag'] ) && ( $config['headline']['tag'] < 1 || $config['headline']['tag'] > 6 ) ) {
					$errors[] = __( 'headline.tag must be between 1 and 6', 'rmg-premium-listings' );
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'invalid_config', implode( ' ', $errors ), array( 'errors' => $errors ) );
		}

		return $config;
	}
}
