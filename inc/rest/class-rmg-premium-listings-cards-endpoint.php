<?php
/**
 * REST API endpoint for Listing Cards
 *
 * @package rmg-premium-listings
 */

class RMG_Premium_Listings_Cards_Endpoint {

	/**
	 * Initialize the endpoint.
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'rmg/v1',
			'/premium-listing-cards',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'get_listing_cards' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_endpoint_args(),
			)
		);
	}

	/**
	 * REST API callback.
	 *
	 * @param WP_REST_Request $request rest request.
	 * @return WP_REST_Response|WP_Error error/response.
	 */
	public function get_listing_cards( $request ) {
		$debug_notes = array();
		$warnings    = array();

		if ( ! class_exists( 'RMG_Premium_Listings_Cards_Renderer' ) ) {
			return new WP_Error(
				'class_not_found',
				__( 'Listing Cards class not available.', 'rmg-premium-listings' ),
				array( 'status' => 500 )
			);
		}

		$params = $request->get_params();

		// Handle location if requested.
		if ( ! empty( $params['fetch_location'] ) ) {
			$params['user_location'] = $this->get_location_from_headers();
			$debug_notes[]           = 'Location fetched from headers';
		}

		// Prepare arguments.
		$args = $this->prepare_args( $params );

		try {
			// Get cards data.
			$data_handler = new RMG_Premium_Listings_ES_Query();
			$cards_data   = $data_handler->init( $args );

			// Count cards returned from query.
			$query_card_count = 0;
			if ( 'tabs' === $args['action_type'] && ! isset( $cards_data[0] ) ) {
				// Tabbed structure - count cards in each tab.
				$tab_counts = array();
				foreach ( $cards_data as $tab_key => $cards ) {
					$count                  = is_array( $cards ) ? count( $cards ) : 0;
					$tab_counts[ $tab_key ] = $count;
					$query_card_count      += $count;
				}
				$debug_notes[] = 'Tab card counts: ' . wp_json_encode( $tab_counts );
			} else {
				// Regular structure.
				$query_card_count = is_array( $cards_data ) ? count( $cards_data ) : 0;
			}

			// Add debug info about cards from query.
			$debug_notes[] = sprintf( 'Cards returned from ES query: %d', $query_card_count );
			$debug_notes[] = sprintf( 'Requested card count: %d', $args['card_count'] );

			// Log the structure of cards_data for debugging.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && ! empty( $cards_data ) ) {
				// phpcs:ignore
				error_log( 'RMG Cards Data Structure: ' . wp_json_encode( $cards_data ) );

				// Show a sample of the data structure in debug notes.
				if ( is_array( $cards_data ) && ! empty( $cards_data ) ) {
					$first_card = reset( $cards_data );
					if ( is_array( $first_card ) ) {
						$debug_notes[] = 'Sample card fields: ' . wp_json_encode( array_keys( $first_card ) );
					}
				}
			}

			// Render HTML first to get actual card count.
			$renderer = new RMG_Premium_Listings_Cards_Renderer();
			$html     = $renderer->get_render( $args );

			// Count actual cards in the rendered HTML.
			$rendered_card_count = 0;
			if ( ! empty( $html ) ) {
				// Count <li> elements with class "listing-card" or elements with data-card-id.
				$rendered_card_count = max(
					substr_count( $html, 'class="listing-card' ),
					substr_count( $html, 'data-card-id=' )
				);
			}

			$debug_notes[] = sprintf( 'Cards actually rendered in HTML: %d', $rendered_card_count );

			// Only warn about actual problems - when the user doesn't get what they requested.
			if ( $rendered_card_count < $args['card_count'] ) {
				$warnings[] = sprintf(
					'Unable to fulfill request: Only %d cards rendered (requested %d)',
					$rendered_card_count,
					$args['card_count']
				);

				// Add context about why the request couldn't be fulfilled.
				if ( 0 === $query_card_count ) {
					$warnings[] = 'No cards returned from ES query';

					if ( ! empty( $args['selected_terms'] ) ) {
						$active_terms = array_filter( $args['selected_terms'] );
						if ( ! empty( $active_terms ) ) {
							$warnings[] = 'Active term filters: ' . wp_json_encode( array_keys( $active_terms ) );
						}
					}

					if ( ! empty( $args['user_location'] ) ) {
						$warnings[] = sprintf(
							'Location used: %s, %s (lat: %f, lon: %f)',
							$args['user_location']['city'] ?? 'Unknown',
							$args['user_location']['region'] ?? '',
							$args['user_location']['lat'] ?? 0,
							$args['user_location']['lon'] ?? 0
						);
					}

					if ( ! empty( $args['already_displayed'] ) ) {
						$warnings[] = sprintf( 'Excluding %d already displayed IDs', count( $args['already_displayed'] ) );
					}
				}
			}

			// Extract displayed IDs from cards data first.
			$query_displayed_ids = $this->extract_displayed_ids( $cards_data, $args['action_type'] );
			$debug_notes[]       = sprintf( 'IDs from ES query data: %d', count( $query_displayed_ids ) );

			// Extract displayed IDs from actual HTML (more reliable).
			$displayed_ids = $this->extract_ids_from_html( $html );
			$debug_notes[] = sprintf( 'IDs extracted from HTML: %d', count( $displayed_ids ) );

			// Check if HTML is empty or contains no cards.
			if ( empty( $html ) ) {
				$warnings[] = 'Renderer returned empty HTML';
			} elseif ( 0 === $rendered_card_count ) {
				$warnings[] = 'HTML contains no card elements';
			}

			// Check for discrepancies between query results and rendered cards.
			if ( $rendered_card_count !== $query_card_count ) {
				$debug_notes[] = sprintf(
					'Discrepancy: ES query returned %d cards but %d were rendered',
					$query_card_count,
					$rendered_card_count
				);
			}

			// Compare IDs from query vs HTML.
			if ( count( $query_displayed_ids ) !== count( $displayed_ids ) ) {
				$debug_notes[] = sprintf(
					'ID mismatch: Query data has %d IDs, HTML has %d IDs',
					count( $query_displayed_ids ),
					count( $displayed_ids )
				);
			}

			// Show ID differences if they exist.
			$missing_from_html = array_diff( $query_displayed_ids, $displayed_ids );
			$extra_in_html     = array_diff( $displayed_ids, $query_displayed_ids );

			if ( ! empty( $missing_from_html ) ) {
				$debug_notes[] = 'IDs in query but not in HTML: ' . wp_json_encode( $missing_from_html );
			}

			if ( ! empty( $extra_in_html ) ) {
				$debug_notes[] = 'IDs in HTML but not in query: ' . wp_json_encode( $extra_in_html );
			}

			// Log to error log if debug is enabled and we have warnings.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true && ! empty( $warnings ) ) {
				// phpcs:ignore
				error_log( 'RMG Listing Cards - Warnings: ' . wp_json_encode( $warnings ) );
				// phpcs:ignore
				error_log( 'RMG Listing Cards - Debug Notes: ' . wp_json_encode( $debug_notes ) );
			}

			return new WP_REST_Response(
				array(
					'success'       => true,
					'html'          => $html,
					'displayed_ids' => $displayed_ids,
					'location'      => $args['user_location'] ?? array(),
					'meta'          => array(
						'card_count'       => $args['card_count'],
						'cards_from_query' => $query_card_count,
						'cards_rendered'   => $rendered_card_count,
						'layout'           => $args['layout'],
						'action_type'      => $args['action_type'],
					),
					'debug'         => defined( 'WP_DEBUG' ) && WP_DEBUG ? array(
						'notes'    => $debug_notes,
						'warnings' => $warnings,
					) : null,
				),
				200
			);

		} catch ( Exception $e ) {
			// Log the exception details.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				// phpcs:ignore
				error_log( 'RMG Listing Cards Exception: ' . $e->getMessage() );
				// phpcs:ignore
				error_log( 'Stack trace: ' . $e->getTraceAsString() );
			}

			return new WP_Error(
				'render_error',
				$e->getMessage(),
				array(
					'status' => 500,
					'debug'  => defined( 'WP_DEBUG' ) && WP_DEBUG ? array(
						'notes'    => $debug_notes,
						'warnings' => $warnings,
						'trace'    => $e->getTraceAsString(),
					) : null,
				)
			);
		}
	}

	/**
	 * Get location from HTTP headers.
	 *
	 * @return array
	 */
	private function get_location_from_headers(): array {
		if ( isset( $_GET['debug-location'] ) ) {
			return array();
		}

		$lat = sanitize_text_field( $_SERVER['HTTP_CF_IPLATITUDE'] ?? '' );
		$lon = sanitize_text_field( $_SERVER['HTTP_CF_IPLONGITUDE'] ?? '' );

		if ( ! $lat || ! $lon ) {
			return array();
		}

		return array(
			'lat'     => (float) $lat,
			'lon'     => (float) $lon,
			'city'    => sanitize_text_field( $_SERVER['HTTP_CF_IPCITY'] ?? '' ),
			'region'  => sanitize_text_field( $_SERVER['HTTP_CF_IPREGION'] ?? '' ),
			'country' => sanitize_text_field( $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '' ),
			'type'    => 'user',
		);
	}

	/**
	 * Prepare arguments from request.
	 *
	 * @param array $params rest params.
	 * @return array
	 */
	private function prepare_args( array $params ): array {
		$layout             = $params['layout'] ?? 'three-column';
		$default_card_count = 'slider' === $layout ? 8 : 3;

		$args = array(
			'action_type'       => sanitize_text_field( $params['action_type'] ?? 'none' ),
			'card_count'        => absint( $params['card_count'] ?? $default_card_count ),
			'layout'            => sanitize_text_field( $layout ),
			'exclude_displayed' => (bool) ( $params['exclude_displayed'] ?? false ),
			'has_background'    => (bool) ( $params['has_background'] ?? false ),
			'is_inline'         => (bool) ( $params['is_inline'] ?? false ),
			'slides_to_show'    => (float) ( $params['slides_to_show'] ?? 3 ),
			'render_id'         => uniqid( 'listing_cards_', true ),
			'excluded_post_ids' => array_map( 'absint', (array) ( $params['excluded_post_ids'] ?? array() ) ),
			'wrapper_classes'   => array_map( 'sanitize_html_class', (array) ( $params['wrapper_classes'] ?? array() ) ),
		);

		// Handle card options
		$args['card_options'] = $this->prepare_card_options( $params['card_options'] ?? array() );

		// Handle context
		$args['context'] = $this->prepare_context( $params['context'] ?? array() );

		// Handle headline
		$args['headline'] = $this->prepare_headline( $params['headline'] ?? array() );

		// Handle selected terms
		$args['selected_terms'] = $this->prepare_selected_terms( $params['selected_terms'] ?? array() );

		// Handle wrapper attributes
		$args['wrapper_attributes'] = $this->prepare_wrapper_attributes( $params['wrapper_attributes'] ?? array() );

		// Handle location
		if ( ! empty( $params['user_location'] ) ) {
			$args['user_location'] = $this->prepare_location( $params['user_location'] );
		}

		// Handle display context
		if ( ! empty( $params['display_context'] ) ) {
			$args['display_context'] = sanitize_key( $params['display_context'] );
		}

		// Handle already displayed
		if ( ! empty( $params['already_displayed'] ) ) {
			$args['already_displayed'] = array_map( 'absint', (array) $params['already_displayed'] );
		}

		return $args;
	}

	/**
	 * Prepare card options.
	 *
	 * @param array $options card options.
	 * @return array
	 */
	private function prepare_card_options( array $options ): array {
		return array(
			'hasBackground' => (bool) ( $options['hasBackground'] ?? false ),
			'showRank'      => (bool) ( $options['showRank'] ?? true ),
			'showAddress'   => (bool) ( $options['showAddress'] ?? true ),
			'showInsurance' => (bool) ( $options['showInsurance'] ?? true ),
		);
	}

	/**
	 * Prepare context.
	 *
	 * @param array $context card context.
	 * @return array
	 */
	private function prepare_context( array $context ): array {
		return array(
			'post_id'                => absint( $context['post_id'] ?? 0 ),
			'post_type'              => sanitize_text_field( $context['post_type'] ?? '' ),
			'requires_location_data' => (bool) ( $context['requires_location_data'] ?? false ),
		);
	}

	/**
	 * Prepare headline.
	 *
	 * @param array $headline headling content.
	 * @return array
	 */
	private function prepare_headline( array $headline ): array {
		return array(
			'show'      => (bool) ( $headline['show'] ?? false ),
			'text'      => sanitize_text_field( $headline['text'] ?? __( 'Featured Facilities Near You', 'rmg-premium-listings' ) ),
			'alignment' => in_array( $headline['alignment'] ?? '', array( 'left', 'center', 'right' ) )
				? $headline['alignment']
				: 'left',
			'tag'       => min( 6, max( 1, absint( $headline['tag'] ?? 2 ) ) ),
		);
	}

	/**
	 * Prepare selected terms.
	 *
	 * @param array $terms terms array.
	 * @return array
	 */
	private function prepare_selected_terms( array $terms ): array {
		$term_types = array(
			'amenities',
			'clinicalServices',
			'levelsOfCare',
			'paymentOptions',
			'programs',
			'treatmentOptions',
		);

		$prepared = array();
		foreach ( $term_types as $type ) {
			$prepared[ $type ] = isset( $terms[ $type ] ) && is_array( $terms[ $type ] )
				? array_map( 'sanitize_text_field', $terms[ $type ] )
				: array();
		}

		return $prepared;
	}

	/**
	 * Prepare wrapper attributes.
	 *
	 * @param array $attributes block attributes.
	 * @return array
	 */
	private function prepare_wrapper_attributes( $attributes ): array {
		if ( ! is_array( $attributes ) ) {
			return array();
		}

		$prepared = array();
		foreach ( $attributes as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			if ( $sanitized_key ) {
				$prepared[ $sanitized_key ] = sanitize_text_field( $value );
			}
		}

		return $prepared;
	}

	/**
	 * Prepare location data.
	 *
	 * @param array $location location data.
	 * @return array
	 */
	private function prepare_location( array $location ): array {
		return array(
			'lat'     => (float) ( $location['lat'] ?? 0 ),
			'lon'     => (float) ( $location['lon'] ?? 0 ),
			'city'    => sanitize_text_field( $location['city'] ?? '' ),
			'region'  => sanitize_text_field( $location['region'] ?? '' ),
			'country' => sanitize_text_field( $location['country'] ?? '' ),
			'type'    => sanitize_text_field( $location['type'] ?? 'user' ),
		);
	}

	/**
	 * Extract displayed IDs from cards data.
	 *
	 * @param array  $cards_data card data array.
	 * @param string $action_type action type defines render output.
	 * @return array
	 */
	private function extract_displayed_ids( array $cards_data, string $action_type ): array {
		if ( empty( $cards_data ) ) {
			return array();
		}

		$ids = array();

		// Check structure based on action type.
		if ( 'tabs' === $action_type && ! isset( $cards_data[0] ) ) {
			// Tabbed structure.
			foreach ( $cards_data as $cards ) {
				if ( is_array( $cards ) ) {
					$ids = array_merge( $ids, array_column( $cards, 'id' ) );
				}
			}
		} else {
			// Regular structure.
			$ids = array_column( $cards_data, 'id' );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Extract IDs from rendered HTML.
	 *
	 * @param string $html The rendered HTML.
	 * @return array Array of post IDs found in the HTML.
	 */
	private function extract_ids_from_html( string $html ): array {
		if ( empty( $html ) ) {
			return array();
		}

		$ids = array();

		// Extract data-card-id values.
		if ( preg_match_all( '/data-card-id="(\d+)"/', $html, $matches ) ) {
			$ids = array_merge( $ids, $matches[1] );
		}

		// Fallback: extract data-post-id values.
		if ( empty( $ids ) && preg_match_all( '/data-post-id="(\d+)"/', $html, $matches ) ) {
			$ids = array_merge( $ids, $matches[1] );
		}

		// Convert to integers and remove duplicates.
		$ids = array_map( 'intval', $ids );
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Get endpoint arguments schema.
	 *
	 * @return array get enpoint args validation.
	 */
	private function get_endpoint_args(): array {
		return array(
			'action_type'        => array(
				'type'    => 'string',
				'default' => 'none',
				'enum'    => array( 'none', 'tabs', 'filter' ),
			),
			'card_count'         => array(
				'type'    => 'integer',
				'default' => 3,
				'minimum' => 1,
				'maximum' => 60,
			),
			'card_options'       => array(
				'type'    => 'object',
				'default' => array(),
			),
			'display_context'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'already_displayed'  => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'integer' ),
			),
			'context'            => array(
				'type'    => 'object',
				'default' => array(),
			),
			'exclude_displayed'  => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'has_background'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'headline'           => array(
				'type'    => 'object',
				'default' => array(),
			),
			'layout'             => array(
				'type'    => 'string',
				'default' => 'three-column',
				'enum'    => array( 'three-column', 'slider', 'vertical' ),
			),
			'selected_terms'     => array(
				'type'    => 'object',
				'default' => array(),
			),
			'wrapper_classes'    => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'string' ),
			),
			'wrapper_attributes' => array(
				'type'    => 'object',
				'default' => array(),
			),
			'slides_to_show'     => array(
				'type'    => 'number',
				'default' => 3,
			),
			'is_inline'          => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'excluded_post_ids'  => array(
				'type'    => 'array',
				'default' => array(),
				'items'   => array( 'type' => 'integer' ),
			),
			'fetch_location'     => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'user_location'      => array(
				'type'    => 'object',
				'default' => array(),
			),
		);
	}
}

// Initialize the endpoint.
$rmg_listing_cards_endpoint = new RMG_Premium_Listings_Cards_Endpoint();
$rmg_listing_cards_endpoint->init();
