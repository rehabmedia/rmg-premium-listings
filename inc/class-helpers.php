<?php
/**
 * Helper functions for RMG Premium Listings.
 *
 * @package rmg-premium-listings
 */

namespace RMG_Premium_Listings;

/**
 * Helper functions class for RMG Premium Listings.
 *
 * Provides utility methods for common operations throughout the plugin.
 *
 * @since 1.0.0
 */
class Helpers {

	/**
	 * Echo the map layer.
	 *
	 * @param string $address The address of the location.
	 * @param string $lat     The latitude of the location.
	 * @param string $lon     The longitude of the location.
	 * @param array  $options The options for the map layer.
	 *
	 * @return void
	 */
	public static function the_map_layer( string $address = '', string $lat = '', string $lon = '', array $options = array() ): void {
		echo self::get_the_map_layer( $address, $lat, $lon, $options ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get the map layer for a location.
	 *
	 * @param string $address The address of the location.
	 * @param string $lat     The latitude of the location.
	 * @param string $lon     The longitude of the location.
	 * @param array  $options The options array (optional).
	 *
	 * @return string
	 */
	public static function get_the_map_layer( string $address = '', string $lat = '', string $lon = '', array $options = array() ): string {
		// Grab API key from pronamic.
		$maps_api_key = get_option( 'pronamic_google_maps_key' );

		// If the address is empty or API key is missing, return an empty string.
		if ( empty( $address ) || empty( $maps_api_key ) ) {
			return '';
		}

		// Clean the address - replace newlines with commas and clean up.
		$address = str_replace( array( "\r\n", "\r", "\n" ), ', ', $address );
		$address = preg_replace( '/,\s*,+/', ',', $address );
		$address = trim( $address, ', ' );

		// Default iframe attributes.
		$iframe_config = array_merge(
			array(
				'title'           => 'Google Map of Center Address',
				'width'           => '720',
				'height'          => '400',
				'style'           => 'border:0',
				'loading'         => 'lazy',
				'allowfullscreen' => true,
			),
			$options['iframe'] ?? array()
		);

		// Default map parameters.
		$map_params = array_merge(
			array(
				'zoom' => '15',
			),
			$options['map'] ?? array()
		);

		// Build the Google Maps URL.
		$base_url = 'https://www.google.com/maps/embed/v1/place';

		// Core parameters.
		$query_params = array(
			'key' => $maps_api_key,
			'q'   => $address,
		);

		// Add center coordinates if provided.
		if ( ! empty( $lat ) && ! empty( $lon ) ) {
			$query_params['center'] = $lat . ',' . $lon;
		}

		// Add additional map parameters.
		$query_params = array_merge( $query_params, $map_params );

		$src_url = $base_url . '?' . http_build_query( $query_params );

		// Build iframe attributes string.
		$attributes = array();
		foreach ( $iframe_config as $attr => $value ) {
			if ( 'allowfullscreen' === $attr && $value ) {
				$attributes[] = 'allowfullscreen';
			} elseif ( false !== $value && null !== $value ) {
				$attributes[] = sprintf( '%s="%s"', $attr, esc_attr( $value ) );
			}
		}

		$iframe_attributes = ' ' . implode( ' ', $attributes );

		// Return the complete iframe.
		return sprintf(
			'<iframe%s src="%s"></iframe>',
			$iframe_attributes,
			esc_url( $src_url )
		);
	}
}
