<?php
/**
 * Plugin Name: RMG Premium Listings
 * Plugin URI: https://rehabmedia.com
 * Description: Premium facility listings with ElasticSearch integration, advanced filtering, and customizable card displays
 * Version: 1.0.0
 * Author: Jo Murgel, Rehab Media Group
 * Date: 2025-01-16
 * Author URI: https://rehabmedia.com
 * License: Internal use only
 * Text Domain: rmg-premium-listings
 *
 * @package RMG_Premium_Listings
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'RMG_PREMIUM_LISTINGS_VERSION', '1.0.0' );
define( 'RMG_PREMIUM_LISTINGS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RMG_PREMIUM_LISTINGS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'RMG_PREMIUM_LISTINGS_PLUGIN_FILE', __FILE__ );

// Include the main class file.
require_once RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/class-rmg-premium-listings.php';

/**
 * Initialize the plugin.
 *
 * @return RMG_Premium_Listings The plugin instance.
 */
function rmg_premium_listings_init(): RMG_Premium_Listings {
	return RMG_Premium_Listings::get_instance();
}

// Initialize plugin.
add_action( 'plugins_loaded', 'rmg_premium_listings_init' );

// Plugin activation/deactivation hooks.
register_activation_hook( __FILE__, array( 'RMG_Premium_Listings', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RMG_Premium_Listings', 'deactivate' ) );
