<?php
/**
 * RMG Listing Cards Data Handler
 *
 * Optimized implementation using simplified aggregations based on the existing fast approach.
 * Modified to support term-based bucket aggregations for frontend filtering.
 *
 * @package rmg-premium-listings
 */

namespace RMG_Premium_Listings;

use RehabMediaGroup\Elasticsearch\Elasticsearch;
use RehabMediaGroup\Elasticsearch\Utilities;

/**
 * ES Query
 */
class ES_Query {

	/**
	 * Page type constants
	 */
	const PAGE_TYPE_DEFAULT      = 'default';
	const PAGE_TYPE_REHAB_CENTER = 'rehab-center';
	const PAGE_TYPE_STATE        = 'state';
	const PAGE_TYPE_CITY         = 'city';
	const CACHE_DURATION         = 900;

	/**
	 * Action type
	 *
	 * @var string
	 */
	private $action_type = 'all';

	/**
	 * Card Count
	 *
	 * @var int
	 */
	private $card_count = 3;

	/**
	 * Card options
	 *
	 * @var array
	 */
	private $card_options = array();

	/**
	 * Excluded post IDs
	 *
	 * @var array
	 */
	private $excluded_post_ids = array();

	/**
	 * Whether or not to exclude displayed posts
	 *
	 * @var boolean
	 */
	private $exclude_displayed = false;

	/**
	 * Location
	 *
	 * @var array
	 */
	private $location = array();

	/**
	 * Page type
	 *
	 * @var string
	 */
	private $page_type = '';

	/**
	 * Page type
	 *
	 * @var int
	 */
	private $post_id = 0;

	/**
	 * Selected terms
	 *
	 * @var array
	 */
	private $selected_terms = array();

	/**
	 * Selected terms
	 *
	 * @var array
	 */
	private $selected_terms_flat = array();

	/**
	 * Render class
	 *
	 * @var Cards_Renderer
	 */
	private $render_class = null;

	/**
	 * Processing cache to prevent duplicate queries in same request
	 *
	 * @var array
	 */
	private static $processing_cache = array();

	/**
	 * User location override
	 *
	 * @var array|null
	 */
	private $user_location_override = null;

	/**
	 * Context data (contains state, city, page_type overrides for embeds)
	 *
	 * @var array
	 */
	private $context = array();


	/**
	 * Get listing cards data.
	 *
	 * @param array $args The arguments.
	 *
	 * @example args array(
	 *  'action_type'        => 'none',
	 *  'card_count'         => 3,
	 *  'context'            => [],
	 *  'excluded_post_ids'  => [],
	 *  'exclude_displayed'  => false,
	 *  'has_background'     => false,
	 *  'headline'           => [
	 *      'show'      => true,
	 *      'text'      => __( 'Featured Facilities Near You', 'rmg-premium-listings' ),
	 *      'alignment' => 'left',
	 *      'tag'       => 2,
	 *  ],
	 *  'slides_to_show'     => 3,
	 *  'is_inline'         => false,
	 *  'layout'             => 'three-column',
	 *  'selected_terms'     => [
	 *      'amenities'        => [],
	 *      'clinicalServices' => [],
	 *      'levelsOfCare'     => [],
	 *      'paymentOptions'   => [],
	 *      'programs'         => [],
	 *      'treatmentOptions' => [],
	 *  ],
	 *  'render_id'          => uniqid( 'listing_cards_', true ),
	 *  'card_options'       => $card_options,
	 *  'card_data'          => [],
	 *  'wrapper_classes'    => [],
	 *  'wrapper_attributes' => [],
	 *  'user_location'      => [],
	 * )
	 *
	 * @return array The cards data.
	 */
	public function init( array $args ): array {
		try {
			// Store context for embed overrides.
			$this->context = $args['context'] ?? array();

			$this->page_type = $this->get_page_type();

			// Check if user location is provided in args.
			if ( ! empty( $args['user_location'] ) ) {
				$this->user_location_override = $args['user_location'];
			}

			$this->location = $this->get_location_data( $this->page_type );

			// Don't cache based on this or ids which are dynamic.
			$cache_params = $args;
			$cache_params = $args;
			unset( $cache_params['exclude_displayed'] );
			unset( $cache_params['excluded_post_ids'] );
			unset( $cache_params['render_id'] );
			unset( $cache_params['wrapper_classes'] );
			unset( $cache_params['wrapper_attributes'] );
			unset( $cache_params['context'] );
			unset( $cache_params['headline'] );
			unset( $cache_params['has_background'] );
			unset( $cache_params['is_inline'] );
			unset( $cache_params['slides_to_show'] );
			unset( $cache_params['layout'] );

			// Create cache key including state/city overrides.
			$cache_key = 'rmg_es_' . md5(
				wp_json_encode(
					array_merge(
						$cache_params,
						array(
							'url'       => sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ),
							'seed'      => $this->get_randomization_seed(),
							'location'  => $this->location,
							'state'     => $this->context['state'] ?? '',
							'city'      => $this->context['city'] ?? '',
							'page_type' => $this->page_type,
						)
					)
				)
			);

			// Check processing cache FIRST (prevents duplicate queries in same request).
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( self::$processing_cache[ $cache_key ] ) && ! $args['exclude_displayed'] && ! isset( $_GET['debug-bypass-cache'] ) ) {
				return self::$processing_cache[ $cache_key ];
			}

			// Check transient cache.
			$cached = get_transient( $cache_key );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( false !== $cached && ! $args['exclude_displayed'] && ! isset( $_GET['debug-bypass-cache'] ) ) {
				// Store in processing cache too.
				self::$processing_cache[ $cache_key ] = $cached;
				return $cached;
			}

			// Set up properties.
			$this->action_type         = $args['action_type'] ?? 'all';
			$this->card_count          = $args['card_count'] ?? 3;
			$this->card_options        = $args['card_options'] ?? array();
			$this->exclude_displayed   = $args['exclude_displayed'] ?? false;
			$this->post_id             = $args['context']['post_id'] ?? 0;
			$this->render_class        = new Cards_Renderer();
			$this->selected_terms      = $args['selected_terms'] ?? array();
			$this->selected_terms_flat = array_merge( ...array_values( $this->selected_terms ) );

			// Set context if provided (for REST requests).
			$display_context = null;
			if ( ! empty( $args['display_context'] ) ) {
				$display_context = $args['display_context'];
			}

			// Build excluded post IDs.
			$this->excluded_post_ids = $args['excluded_post_ids'] ?? array();

			// Add previously displayed IDs if exclude_displayed is true.
			if ( $this->exclude_displayed ) {
				$previously_displayed    = Cards_Registry::get_displayed( $display_context );
				$this->excluded_post_ids = array_unique(
					array_merge(
						$this->excluded_post_ids,
						$previously_displayed
					)
				);
			}

			// Get data.
			$card_data = array();
			switch ( $this->action_type ) {
				case 'tabs':
					$card_data = ! empty( $this->selected_terms_flat )
						? $this->get_tabbed_card_data()
						: $this->get_card_data();
					break;
				default:
					$card_data = $this->get_card_data();
					break;
			}

			// Cache for 15 minutes.
			self::$processing_cache[ $cache_key ] = $card_data;
			set_transient( $cache_key, $card_data, self::CACHE_DURATION );

			// Update displayed IDs section.
			if ( ! empty( $card_data ) ) {
				$displayed_ids = array();

				// Check if this is tabbed data (nested array) or regular data.
				if ( 'tabs' === $this->action_type && ! empty( $this->selected_terms_flat ) ) {
					foreach ( $card_data as $term => $cards ) {
						if ( is_array( $cards ) ) {
							$tab_ids       = array_column( $cards, 'id' );
							$displayed_ids = array_merge( $displayed_ids, $tab_ids );
						}
					}
				} else {
					$displayed_ids = array_column( $card_data, 'id' );
				}

				// Add array_unique here to remove duplicates before registering.
				$displayed_ids = array_unique( $displayed_ids );

				// Register all displayed IDs.
				if ( ! empty( $displayed_ids ) ) {
					Cards_Registry::register_displayed(
						$displayed_ids,
						$display_context
					);
				}

				// Add displayed IDs to the return data for REST responses.
				if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
					return array(
						'cards'         => $card_data,
						'displayed_ids' => $displayed_ids,
					);
				}
			}

			return $card_data;

		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				// phpcs:ignore
				error_log( $e->getMessage() );
			}
			return array();
		}
	}

	/**
	 * Determine the current page type based on context.
	 *
	 * @return string The page type.
	 */
	public function get_page_type(): string {
		// Check context first (for embeds with overrides).
		if ( ! empty( $this->context['page_type'] ) ) {
			return $this->context['page_type'];
		}

		// Otherwise derive page_type from context state/city.
		if ( ! empty( $this->context['city'] ) ) {
			return self::PAGE_TYPE_CITY;
		}
		if ( ! empty( $this->context['state'] ) ) {
			return self::PAGE_TYPE_STATE;
		}

		// Fall back to WordPress query context.
		if ( is_tax() ) {
			$taxonomy = get_queried_object()->taxonomy;

			if ( 'rehab-centers' === $taxonomy ) {
				$term      = get_queried_object();
				$parent_id = $term->parent;

				if ( $parent_id > 0 ) {
					return self::PAGE_TYPE_CITY;
				} else {
					return self::PAGE_TYPE_STATE;
				}
			}
		} elseif ( is_singular( 'rehab-center' ) ) {
			return self::PAGE_TYPE_REHAB_CENTER;
		}

		return self::PAGE_TYPE_DEFAULT;
	}

	/**
	 * Get location data based on page type.
	 *
	 * @return array The location data.
	 */
	public function get_location_data(): array {
		// If user location override is provided, use it for default page type.
		if ( $this->user_location_override && self::PAGE_TYPE_DEFAULT === $this->page_type ) {
			return $this->user_location_override;
		}

		switch ( $this->page_type ) {
			case self::PAGE_TYPE_REHAB_CENTER:
			case self::PAGE_TYPE_CITY:
				return $this->get_taxonomy_location_data();
			case self::PAGE_TYPE_DEFAULT:
				return array();
			default:
				return array();
		}
	}

	/**
	 * Get location data from taxonomy ACF fields.
	 *
	 * @return array The location data.
	 */
	private function get_taxonomy_location_data(): array {
		// Bail if debug-location is set.
		// phpcs:ignore
		if ( isset( $_GET['debug-location'] ) ) {
			return array();
		}

		$term = null;

		// For city pages with context override (embeds), try to find the city term.
		if ( self::PAGE_TYPE_CITY === $this->page_type && ! empty( $this->context['city'] ) ) {
			// Try to find the city term by slug in the rehab-centers taxonomy.
			$term = get_term_by( 'slug', $this->context['city'], 'rehab-centers' );

			// If city not found, return empty array to fall back to state filtering.
			if ( ! $term ) {
				return array();
			}
		} elseif ( self::PAGE_TYPE_REHAB_CENTER === $this->page_type ) {
			$term = Utilities::rmg_get_primary_term( $this->post_id, 'rehab-centers' );
		} else {
			$term = get_queried_object();
		}

		if ( ! $term ) {
			return array();
		}

		$term_id   = $term->term_id;
		$latitude  = get_field(
			'_pronamic_google_maps_latitude',
			'rehab-centers_' . $term_id
		);
		$longitude = get_field(
			'_pronamic_google_maps_longitude',
			'rehab-centers_' . $term_id
		);

		if ( ! $latitude || ! $longitude ) {
			return array();
		}

		return array(
			'lat'       => (float) $latitude,
			'lon'       => (float) $longitude,
			'term_id'   => $term_id,
			'term_name' => $term->name,
			'type'      => 'taxonomy',
		);
	}

	/**
	 * Get card data implementation
	 *
	 * @return array The card data.
	 */
	private function get_card_data(): array {
		// Build and execute the query.
		$query = $this->build_base_query();

		// Add selected terms filter if present and action_type is 'filtered'.
		if ( 'filtered' === $this->action_type && ! empty( $this->selected_terms ) ) {
			$query = $this->add_terms_filter( $query );
		}

		// Execute query.
		$results = $this->execute_elasticsearch_query( $query );

		if ( ! $results ) {
			return array();
		}

		// Parse and process results.
		return $this->process_query_results( $results );
	}

	/**
	 * Get tabbed card data using _msearch.
	 *
	 * @return array The tabbed card data.
	 */
	private function get_tabbed_card_data(): array {
		// Extract all unique terms that need separate queries.
		$all_terms = $this->extract_all_terms( $this->selected_terms );

		if ( empty( $all_terms ) ) {
			// Execute base query.
			$results = $this->get_card_data();
		} else {
			// Build multi-search query.
			$msearch_body = $this->build_msearch_queries( $all_terms );

			// Execute multi-search.
			$results = $this->execute_elasticsearch_query( $msearch_body, '_msearch' );
		}

		if ( ! $results ) {
			return array();
		}

		// Process results for each term.
		return $this->process_tabbed_results( $results, $all_terms );
	}

	/**
	 * Add terms filter to query.
	 *
	 * @param array $query The query.
	 * @return array The query.
	 */
	private function add_terms_filter( array $query ): array {
		$term_filters = $this->build_term_filters();

		if ( ! empty( $term_filters ) ) {
			if ( ! isset( $query['query']['bool']['filter'] ) ) {
				$query['query']['bool']['filter'] = array();
			} elseif ( ! is_array( $query['query']['bool']['filter'] ) ) {
				// Convert to array if it's a single filter.
				$query['query']['bool']['filter'] = array( $query['query']['bool']['filter'] );
			}

			// Add term filters as a should clause with minimum_should_match.
			$query['query']['bool']['filter'][] = array(
				'bool' => array(
					'should'               => $term_filters,
					'minimum_should_match' => 1,
				),
			);
		}

		return $query;
	}

	/**
	 * Build term filters from selected_terms.
	 *
	 * @return array The term filters.
	 */
	private function build_term_filters(): array {
		$filters = array();

		$term_mappings = array(
			'treatmentOptions' => 'rmg.treatment',
			'paymentOptions'   => 'rmg.payment',
			'programs'         => 'rmg.programs',
			'levelsOfCare'     => 'rmg.levels_of_care',
			'clinicalServices' => 'rmg.clinical_services',
			'amenities'        => 'rmg.amenities',
		);

		foreach ( $term_mappings as $term_type => $es_field ) {
			if ( ! empty( $this->selected_terms[ $term_type ] ) ) {
				foreach ( $this->selected_terms[ $term_type ] as $term ) {
					$filters[] = array( 'match' => array( $es_field => $term ) );
				}
			}
		}

		return $filters;
	}

	/**
	 * Build multi-search queries for tabbed interface.
	 *
	 * @param array $all_terms The all terms.
	 * @return string The msearch queries.
	 */
	private function build_msearch_queries( array $all_terms ): string {
		// Build the msearch queries.
		$msearch_body = '';

		foreach ( $all_terms as $term_key => $term_data ) {
			// Index line (empty for default index).
			$msearch_body .= "{}\n";

			// Build query for this specific term.
			$base_query = $this->build_base_query();

			// Add specific term filter.
			if ( ! isset( $base_query['query']['bool']['filter'] ) ) {
				$base_query['query']['bool']['filter'] = array();
			}

			$base_query['query']['bool']['filter'][] = array(
				'match' => array( $term_data['es_field'] => $term_data['value'] ),
			);

			$msearch_body .= wp_json_encode( $base_query ) . "\n";
		}

		return $msearch_body;
	}

	/**
	 * Extract all unique terms from selected_terms.
	 *
	 * @param array $selected_terms The selected terms.
	 * @ret
	 * rn array The all terms.
	 */
	private function extract_all_terms( array $selected_terms ): array {
		$all_terms = array();

		$term_mappings = array(
			'treatmentOptions' => 'rmg.treatment',
			'paymentOptions'   => 'rmg.payment',
			'programs'         => 'rmg.programs',
			'levelsOfCare'     => 'rmg.levels_of_care',
			'clinicalServices' => 'rmg.clinical_services',
			'amenities'        => 'rmg.amenities',
		);

		foreach ( $term_mappings as $term_type => $es_field ) {
			if ( ! empty( $selected_terms[ $term_type ] ) ) {
				foreach ( $selected_terms[ $term_type ] as $term ) {
					$term_key               = $term_type . '_' . sanitize_title( $term );
					$all_terms[ $term_key ] = array(
						'type'     => $term_type,
						'value'    => $term,
						'es_field' => $es_field,
						'label'    => $term,
					);
				}
			}
		}

		return $all_terms;
	}

	/**
	 * Build base query with geo/taxonomy logic.
	 *
	 * @return array The base query.
	 */
	private function build_base_query(): array {
		$query = array(
			'size'  => $this->card_count * 3,
			'query' => array(
				'bool' => array(
					'must' => array(
						array( 'match' => array( 'post_status' => 'publish' ) ),
						array( 'match' => array( 'post_type' => 'rehab-center' ) ),
					),
				),
			),
		);

		// Add exclusion for current rehab center and any other excluded IDs.
		$exclude_ids = $this->excluded_post_ids;

		// If we're on a rehab center page, exclude the current post.
		if ( self::PAGE_TYPE_REHAB_CENTER === $this->page_type && $this->post_id > 0 ) {
			$exclude_ids[] = $this->post_id;
		}

		// Remove duplicates and filter out empty values.
		$exclude_ids = array_unique( array_filter( $exclude_ids ) );

		// Add must_not clause if we have IDs to exclude.
		if ( ! empty( $exclude_ids ) ) {
			$query['query']['bool']['must_not'] = array(
				array(
					'terms' => array(
						'post_id' => $exclude_ids,
					),
				),
			);
		}

		// Add state filter for state pages, or for city pages with no location (fallback).
		if ( self::PAGE_TYPE_STATE === $this->page_type || ( self::PAGE_TYPE_CITY === $this->page_type && empty( $this->location ) ) ) {
			$state_slug = $this->get_state_slug();
			if ( $state_slug ) {
				$query['query']['bool']['must'][] = array(
					'term' => array(
						'rmg.state_slug' => $state_slug,
					),
				);
			}
		}

		// Add location filter for other page types.
		if ( ! empty( $this->location ) && self::PAGE_TYPE_STATE !== $this->page_type ) {
			$max_radius                       = $this->get_max_radius_for_page_type();
			$query['query']['bool']['filter'] = array(
				array(
					'geo_distance' => array(
						'distance'     => $max_radius,
						'rmg.location' => array(
							'lat' => $this->location['lat'],
							'lon' => $this->location['lon'],
						),
					),
				),
			);
		}

		// Add sort criteria.
		$query['sort'] = $this->get_sort_criteria();

		// Include only needed fields.
		$query['_source'] = array(
			'includes' => $this->get_source_fields(),
		);

		return $query;
	}

	/**
	 * Get state slug for state pages or city pages (when falling back).
	 */
	private function get_state_slug(): ?string {
		// Only need this for state pages, or city pages when falling back.
		if ( self::PAGE_TYPE_STATE !== $this->page_type && self::PAGE_TYPE_CITY !== $this->page_type ) {
			return null;
		}

		// Check context for state override (for embeds).
		if ( ! empty( $this->context['state'] ) ) {
			return $this->context['state'];
		}

		// Fall back to WordPress query context.
		$term = get_queried_object();
		if ( ! $term || ! isset( $term->slug ) ) {
			return null;
		}

		// State slugs are typically the parent terms.
		// Assuming state taxonomy slugs match the state_slug field.
		return $term->slug;
	}

	/**
	 * Get maximum radius based on page type.
	 *
	 * @return string The maximum radius.
	 */
	private function get_max_radius_for_page_type(): string {
		switch ( $this->page_type ) {
			case self::PAGE_TYPE_CITY:
			case self::PAGE_TYPE_REHAB_CENTER:
			case self::PAGE_TYPE_DEFAULT:
			default:
				return '500mi';
		}
	}

	/**
	 * Get sort criteria with randomization.
	 *
	 * @return array The sort criteria.
	 */
	private function get_sort_criteria(): array {
		$sort_criteria = array();

		// 1. Primary sort: Premium level (Premium+ > Premium > Free).
		// This ensures correct tier ordering regardless of pacing score data.
		// premium_level values: "Premium+", "Premium", "Free" (or empty/missing = Free)
		$sort_criteria[] = array(
			'rmg.premium_level.keyword' => array(
				'order'         => 'desc',
				'missing'       => '_last', // Treat missing as Free (lowest priority).
				'unmapped_type' => 'keyword',
			),
		);

		// 2. Secondary sort: Pacing score (only matters for Premium+ with active budgets).
		// This provides granular sorting within Premium+ tier based on budget/pacing.
		// - Premium+ with budget: 3000-3999
		// - Premium+ exhausted OR Premium: 2000
		// - Free: 1000
		//
		// Since we sort by premium_level first, bad pacing scores won't affect tier ordering.
		$sort_criteria[] = array(
			'rmg.premium_plus_pacing_score' => array(
				'order'         => 'desc',
				'missing'       => 0,
				'unmapped_type' => 'integer',
			),
		);

		// 3. Tertiary sort: total_points + distance boost (NO randomization here to preserve pacing order).
		// This only affects items with the same premium level and pacing score.
		if ( ! empty( $this->location ) && self::PAGE_TYPE_STATE !== $this->page_type ) {
			$sort_criteria[] = array(
				'_script' => array(
					'type'   => 'number',
					'script' => array(
						'source' => "
						// Get base score.
						double baseScore = 0;
						if (doc.containsKey('rmg.total_points') && !doc['rmg.total_points'].empty) {
							baseScore = doc['rmg.total_points'].value;
						}

						// Calculate distance in miles.
						double distanceInMiles = 0;
						if (doc.containsKey('rmg.location') && !doc['rmg.location'].empty) {
							double distance = doc['rmg.location'].arcDistance(params.lat, params.lon);
							distanceInMiles = distance * 0.000621371;
						}

						// Distance boost: closer facilities get more points.
						// 0-25 miles: +10 points.
						// 25-50 miles: +5 points.
						// 50-100 miles: +2 points.
						// 100+ miles: 0 points.
						double distanceBoost = 0;
						if (distanceInMiles <= 25) {
							distanceBoost = 10;
						} else if (distanceInMiles <= 50) {
							distanceBoost = 5;
						} else if (distanceInMiles <= 100) {
							distanceBoost = 2;
						}

						// Final score: base + distance boost (no randomization).
						return baseScore + distanceBoost;
					",
						'params' => array(
							'lat' => $this->location['lat'],
							'lon' => $this->location['lon'],
						),
						'lang'   => 'painless',
					),
					'order'  => 'desc',
				),
			);
		} else {
			// No location available - just use total_points.
			$sort_criteria[] = array(
				'rmg.total_points' => array(
					'order'         => 'desc',
					'missing'       => '_last',
					'unmapped_type' => 'integer',
				),
			);
		}

		// 4. Quaternary sort: Randomization as tiebreaker only.
		// This provides variety for items with identical scores.
		$sort_criteria[] = array(
			'_script' => array(
				'type'   => 'number',
				'script' => array(
					'source' => "
					long postId = doc['post_id'].value;
					long seed = params.seed;
					Random random = new Random(seed + postId);
					return random.nextInt(100);
				",
					'params' => array(
						'seed' => $this->get_randomization_seed(),
					),
					'lang'   => 'painless',
				),
				'order'  => 'desc',
			),
		);

		// 5. Final tiebreaker: Distance (if location available).
		if ( ! empty( $this->location ) && self::PAGE_TYPE_STATE !== $this->page_type ) {
			$sort_criteria[] = array(
				'_geo_distance' => array(
					'rmg.location' => array(
						'lat' => $this->location['lat'],
						'lon' => $this->location['lon'],
					),
					'order'        => 'asc',
					'unit'         => 'mi',
				),
			);
		}

		return $sort_criteria;
	}

	/**
	 * Get randomization seed for consistent random sorting.
	 *
	 * @return int The seed value.
	 */
	private function get_randomization_seed(): int {

		/**
		 * Modify the listing randomization seed timout.
		 *
		 * @param int $interval integer to define time. eg. 900 for 15 minutes.
		 *
		 *  @return int $interval.
		 */
		$interval = apply_filters( 'rmg_premium_listings_randomization_interval', (int) floor( time() / self::CACHE_DURATION ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a debug flag for cache bypass.
		if ( 0 === $interval || isset( $_GET['debug-bypass-cache'] ) ) {
			// Random every time.
			return wp_rand();
		}

		// Randomizes every 15 minutes. Matches cache.
		// This provides good balance between variety and cache efficiency.
		return (int) floor( time() / $interval );
	}

	/**
	 * Get source fields to retrieve.
	 *
	 * @return array The source fields.
	 */
	private function get_source_fields(): array {
		return array(
			'post_title',
			'post_id',
			'permalink',
			'rmg.premium',
			'rmg.premium_level',
			'rmg.premium_plus_pacing_score',
			'rmg.budgeted_views',
			'rmg.views_remaining',
			'rmg.views_consumed',
			'rmg.override_views',
			'rmg.total_points',
			'rmg.address',
			'rmg.phone',
			'rmg.rating_avg',
			'rmg.review_count',
			'rmg.image_url',
			'rmg.featured_image',
			'rmg.claimed',
			'rmg.website',
			'rmg.tracking_number',
			'rmg.overview',
			'rmg.winner_name',
			'rmg.winner_name2',
			'rmg.winner_rank',
			'rmg.winner_rank2',
			'rmg.city',
			'rmg.state',
			'rmg.insurance',
			'rmg.zip_code',
		);
	}

	/**
	 * Execute Elasticsearch query.
	 *
	 * @param array|string $query The query. String if _msearch is the type.
	 * @param string       $type The type of query. _search or _msearch. Defaults to _search.
	 * @return array|false The results or false if there was an error.
	 * @throws Exception If there was an error.
	 */
	private function execute_elasticsearch_query( array|string $query, string $type = '_search' ): array|false {
		try {

			$payload = '_msearch' === $type ? $query : wp_json_encode( $query );

			// Debug log the full query.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				// phpcs:ignore
				error_log( 'RMG ES Query - Full query payload: ' . $payload );
			}

			$response = Elasticsearch::rmg_es_query( $type, $payload );

			if ( is_wp_error( $response ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					// phpcs:ignore
					error_log( 'RMG Listing Cards Elasticsearch Error: ' . $response->get_error_message() );
					// phpcs:ignore
					error_log( 'RMG ES Query - Failed payload: ' . $payload );
				}
				return false;
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					// phpcs:ignore
					error_log( 'RMG Listing Cards: Elasticsearch returned HTTP ' . $response_code );
				}
				return false;
			}

			$response_body = wp_remote_retrieve_body( $response );
			$results       = json_decode( $response_body, true );

			if ( null === $results ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
					// phpcs:ignore
					error_log( 'RMG Listing Cards: Failed to decode Elasticsearch response JSON' );
				}
				return false;
			}

			return $results;
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				// phpcs:ignore
				error_log( 'RMG Listing Cards Query Execution Error: ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Process query results.
	 *
	 * @param array $results The results.
	 * @return array The cards or an empty array if there were no cards.
	 */
	private function process_query_results( array $results ): array {
		// Check for direct hits (no more aggregations).
		if ( ! isset( $results['hits']['hits'] ) || empty( $results['hits']['hits'] ) ) {
			return array();
		}

		$all_hits = $results['hits']['hits'];

		// Debug: Log sort values for each hit.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['debug-sort'] ) ) {
				foreach ( $all_hits as $hit ) {
					$source       = $hit['_source'] ?? array();
					$rmg          = $source['rmg'] ?? array();
					$pacing_score = $rmg['premium_plus_pacing_score'] ?? 'N/A';
					$sort_values  = $hit['sort'] ?? array();
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log(
						sprintf(
							'ES Result - ID: %d, Title: %s, Pacing Score: %s, Sort Values: %s',
							$source['post_id'] ?? 0,
							$source['post_title'] ?? 'N/A',
							$pacing_score,
							wp_json_encode( $sort_values )
						)
					);
				}
			}
		}

		// Transform hits to cards.
		$cards = $this->transform_hits_to_cards( $all_hits );

		if ( empty( $cards ) ) {
			return array();
		}

		// ES already sorted by premium status and randomized score,
		// so we just need to slice to get the right number.
		$final_cards = array_slice( $cards, 0, $this->card_count );

		return $final_cards;
	}

	/**
	 * Process tabbed results from msearch.
	 *
	 * @param array $results The results.
	 * @param array $all_terms The all terms.
	 * @return array The tabbed results grouped by term for tabs, or flat array for other action types.
	 */
	private function process_tabbed_results( array $results, array $all_terms ): array {
		// Bail if no response exists.
		if ( ! isset( $results['responses'] ) || ! is_array( $results['responses'] ) ) {
			return array();
		}

		$tabbed_results = array();
		$term_keys      = array_keys( $all_terms );
		$seen_cards     = array();

		foreach ( $results['responses'] as $index => $response ) {
			if ( ! isset( $term_keys[ $index ] ) ) {
				continue;
			}

			$term_key    = $term_keys[ $index ];
			$term_data   = $all_terms[ $term_key ];
			$cards       = $this->process_single_tab_results( $response, $term_data );
			$cards_count = count( $cards );

			if ( ! empty( $cards ) ) {
				$has_duplicate = false;

				// Check if either of first 2 cards is a duplicate.
				$check_limit = min( 2, $cards_count );
				$i           = 0;
				while ( $i < $check_limit ) {
					$card_id = $cards[ $i ]['id'] ?? md5( wp_json_encode( $cards[ $i ] ) );
					if ( isset( $seen_cards[ $card_id ] ) ) {
						$has_duplicate = true;
						break;
					}
					++$i;
				}

				// Add metadata.
				array_walk(
					$cards,
					function ( &$card ) use ( $term_key, $term_data ) {
						$card['term_key']   = $term_key;
						$card['term_label'] = $term_data['label'];
						$card['term_type']  = $term_data['type'];
						$card['term_value'] = $term_data['value'];
					}
				);

				// If duplicate found, rotate first 2 to the end.
				if ( $has_duplicate && $cards_count > 2 ) {
					$cards = array_merge(
						array_slice( $cards, 2 ),
						array_slice( $cards, 0, 2 )
					);
				}

				// Track the new first 2 cards.
				$track_limit = min( 2, $cards_count );
				$i           = 0;
				while ( $i < $track_limit ) {
					$card_id                = $cards[ $i ]['id'] ?? md5( wp_json_encode( $cards[ $i ] ) );
					$seen_cards[ $card_id ] = true;
					++$i;
				}
			}

			$tabbed_results[ $term_data['label'] ] = $cards;
		}

		return $tabbed_results;
	}



	/**
	 * Process results for a single tab.
	 *
	 * @param array $results The results.
	 * @param array $term_data The term data.
	 * @return array The cards or an empty array if there were no cards.
	 */
	private function process_single_tab_results( array $results, array $term_data ): array {
		// Check for direct hits (no more aggregations).
		if ( ! isset( $results['hits']['hits'] ) || empty( $results['hits']['hits'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				// phpcs:ignore
				error_log( 'No results found for term: ' . $term_data['label'] );
			}
			return array();
		}

		$all_hits = $results['hits']['hits'];

		// Transform hits to cards.
		$cards = $this->transform_hits_to_cards( $all_hits );

		if ( empty( $cards ) ) {
			return array();
		}

		// ES already sorted properly, just slice to get the right count.
		return array_slice( $cards, 0, $this->card_count );
	}

	/**
	 * Transform ES hits to card data format.
	 *
	 * @param array $hits The hits.
	 * @return array The cards or an empty array if there were no cards.
	 * @throws Exception If there was an error.
	 */
	private function transform_hits_to_cards( array $hits ): array {
		$cards = array();

		foreach ( $hits as $hit ) {
			$card = $this->transform_single_hit_to_card( $hit );
			if ( $card ) {
				$cards[] = $card;
			}
		}

		return $cards;
	}

	/**
	 * Transform a single hit to card format.
	 *
	 * @param array $hit The hit.
	 * @return array The card or an empty array if there was no card.
	 * @throws Exception If there was an error.
	 */
	private function transform_single_hit_to_card( array $hit ): array {
		$source = $hit['_source'] ?? array();
		$rmg    = $source['rmg'] ?? array();

		// Parse address for template.
		$address_parts = $this->render_class->parse_single_address_field( $rmg['address'] ?? '', $source['post_id'] ?? 0 );

		// Process awards data.
		$awards_data   = $this->process_awards_data( $rmg );
		$premium_level = $rmg['premium_level'] ?? '';

		$card = array(
			// Core fields.
			'id'                    => $source['post_id'] ?? 0,
			'title'                 => $source['post_title'] ?? '',
			'listing_link'          => $source['permalink'] ?? '#',
			'listing_image'         => $rmg['featured_image'] ?? $rmg['image_url'] ?? '',
			// Premium status.
			'premium'               => $this->is_premium( $premium_level ),
			'premium_level'         => $premium_level,
			// Scoring.
			'original_total_points' => $rmg['total_points'] ?? 0,
			// Contact info.
			'phone'                 => $rmg['phone'] ?? '',
			'tracking_number'       => $rmg['tracking_number'] ?? '',
			'address'               => $rmg['address'] ?? '',
			// Parsed address for template.
			'city'                  => $address_parts['city'] ?? '',
			'state'                 => $address_parts['state'] ?? '',
			'zip'                   => $address_parts['zip'] ?? '',
			// Ratings.
			'rating'                => $rmg['rating_avg'] ?? 0,
			'reviews'               => $rmg['review_count'] ?? 0,
			// Awards.
			'award'                 => $awards_data['award'] ?? '',
			'award_description'     => $awards_data['award_description'] ?? '',
			// Other fields.
			'accepts_insurance'     => ! empty( $rmg['insurance'] ),
			'website'               => $rmg['website'] ?? '',
			'overview'              => $rmg['overview'] ?? '',
			'claimed'               => ! empty( $rmg['claimed'] ),
			// Card options from args.
			'card_options'          => $this->card_options,
			// Program (for listing tracking).
			'program'               => $rmg['program'] ?? '',
		);

		return $card;
	}

	/**
	 * Process awards data for template.
	 *
	 * @param array $rmg The RMG data.
	 * @return array The awards data or an empty array if there were no awards data.
	 * @throws Exception If there was an error.
	 */
	private function process_awards_data( array $rmg ): array {
		$awards = array(
			'award'             => '',
			'award_description' => '',
		);

		// Check for winner data.
		if ( ! empty( $rmg['winner_rank'] ) && ! empty( $rmg['winner_name'] ) ) {
			$awards['award']             = $rmg['winner_rank'];
			$awards['award_description'] = 'Top 10 Rehab In ' . ucfirst( $rmg['winner_name'] );
		} elseif ( ! empty( $rmg['winner_rank2'] ) && ! empty( $rmg['winner_name2'] ) ) {
			$awards['award']             = $rmg['winner_rank2'];
			$awards['award_description'] = 'Top 10 Rehab In ' . ucfirst( $rmg['winner_name2'] );
		}

		return $awards;
	}

	/**
	 * Check if premium level indicates premium or premium+ status.
	 *
	 * @param string $premium_level The premium level value.
	 * @return bool True if premium or premium+, false if free.
	 */
	private function is_premium( string $premium_level ): bool {
		return in_array( $premium_level, array( 'Premium', 'Premium+' ), true );
	}
}
