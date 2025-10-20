<?php
/**
 * Embed Template for Listing Cards
 *
 * Minimal HTML template for iframe embedding.
 * Variables available: $config, $render_args, $referrer, $state, $city
 *
 * @package RMG_Premium_Listings
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Allow third-party hooks.

use RMG_Premium_Listings\Cards_Renderer;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Disable admin bar for embeds.
add_filter( 'show_admin_bar', '__return_false' );

// Disable Query Monitor for embeds.
add_filter( 'qm/dispatch/html', '__return_false' );

/**
 * Enqueue embed-specific assets.
 */
function rmg_premium_listings_embed_enqueue_assets() {
	// Enqueue listing cards styles.
	$style_path = RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'build/listing-cards/style-index.css';
	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'rmg-premium-listings-cards',
			RMG_PREMIUM_LISTINGS_PLUGIN_URL . 'build/listing-cards/style-index.css',
			array(),
			filemtime( $style_path )
		);
	}

	// Enqueue listing cards JavaScript.
	$script_path = RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'build/listing-cards/view.js';
	if ( file_exists( $script_path ) ) {
		wp_enqueue_script(
			'rmg-premium-listings-cards-view',
			RMG_PREMIUM_LISTINGS_PLUGIN_URL . 'build/listing-cards/view.js',
			array(),
			filemtime( $script_path ),
			true
		);
	}

	// Enqueue iframe resize script.
	wp_add_inline_script(
		'rmg-premium-listings-cards-view',
		"
		(function() {
			function sendHeight() {
				const height = document.documentElement.scrollHeight;
				window.parent.postMessage({
					type: 'rmg-embed-resize',
					height: height
				}, '*');
			}

			// Send initial height.
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', sendHeight);
			} else {
				sendHeight();
			}

			// Send height on window resize.
			window.addEventListener('resize', sendHeight);

			// Watch for DOM changes.
			const observer = new MutationObserver(sendHeight);
			observer.observe(document.body, {
				childList: true,
				subtree: true,
				attributes: true
			});

			// Send height periodically for safety.
			setInterval(sendHeight, 1000);
		})();
		",
		'after'
	);

	// Allow impression tracking plugin to load if it exists.
	do_action( 'rmg_embed_enqueue_assets' );
}

// Enqueue assets early, before wp_head runs.
add_action( 'wp_enqueue_scripts', 'rmg_premium_listings_embed_enqueue_assets' );

/**
 * Pass parent page data to impression tracking script.
 * Filters the impression tracking configuration to include parent page information
 * when the embed is loaded within an iframe.
 */
add_filter(
	'rmg_impression_tracking_config',
	function ( $config ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$parent_url = isset( $_GET['parent_url'] ) ? esc_url_raw( wp_unslash( $_GET['parent_url'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$parent_host = isset( $_GET['parent_host'] ) ? sanitize_text_field( wp_unslash( $_GET['parent_host'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$parent_referrer = isset( $_GET['parent_referrer'] ) ? esc_url_raw( wp_unslash( $_GET['parent_referrer'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public embed endpoint.
		$parent_title = isset( $_GET['parent_title'] ) ? sanitize_text_field( wp_unslash( $_GET['parent_title'] ) ) : '';

		// Check if we're in the admin preview (iframe within WordPress admin).
		$is_admin_preview = false;
		if ( ! empty( $parent_url ) ) {
			$is_admin_preview = ( strpos( $parent_url, '/wp-admin/' ) !== false || strpos( $parent_url, 'page=rmg-premium-listings-embed' ) !== false );
		}

		// Add parent page data to config for impression tracking JavaScript.
		$config['parentUrl']      = $parent_url;
		$config['parentHost']     = $parent_host;
		$config['parentReferrer'] = $parent_referrer;
		$config['parentTitle']    = $parent_title;
		$config['isAdminPreview'] = $is_admin_preview;

		return $config;
	}
);

// Remove unnecessary WordPress and theme hooks.
add_action(
	'wp_head',
	function () {
		// Remove WordPress defaults that aren't needed in embed.
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_generator' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

		// Remove theme styles.
		global $wp_styles;
		if ( isset( $wp_styles->queue ) ) {
			foreach ( $wp_styles->queue as $handle ) {
				// Only remove theme styles, keep our plugin styles.
				if ( strpos( $handle, 'rmg-' ) === false && strpos( $handle, 'rehab-' ) === false ) {
					wp_dequeue_style( $handle );
				}
			}
		}
	},
	1
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Premium Listing Cards Embed', 'rmg-premium-listings' ); ?></title>
	<?php wp_head(); ?>
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		html,
		body {
			overflow-x: hidden;
			width: 100%;
		}
		body {
			background: transparent;
			font-family: 'Lato', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			padding-top: 0;
		}
	</style>
</head>
<body class="rmg-embed-body">
	<?php
	// Render the listing cards.
	$renderer = new Cards_Renderer();
	$renderer->render( $render_args );
	?>
	<?php wp_footer(); ?>
</body>
</html>
