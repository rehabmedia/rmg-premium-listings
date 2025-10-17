<?php
/**
 * RMG Elasticsearch Utilities
 *
 * Shared utility functions for Elasticsearch operations,
 * including premium score calculations.
 *
 * @package rmg-premium-listings
 */

namespace RMG_Premium_Listings;

/**
 * ES Utils
 */
class ES_Utils {

	/**
	 * Calculate premium plus pacing score
	 *
	 * This function replicates the Painless script logic for calculating
	 * the premium tier score, allowing it to be pre-calculated and stored
	 * in an ACF field for better ES query performance.
	 *
	 * Score ranges:
	 * - Premium+: 3000-3999 (based on budget and remaining views)
	 * - Premium: 2000
	 * - Free: 1000
	 *
	 * @param int      $post_id The post ID.
	 * @param int|null $premium_level Optional. The premium level (0=Free, 1=Premium, 2=Premium+). If not provided, will be fetched from ACF.
	 * @param int      $budgeted_views Optional. Total budgeted views. If not provided, will be fetched from ACF.
	 * @param int      $views_remaining Optional. Remaining views (decrements from budgeted_views). If not provided, will be fetched from ACF.
	 * @param int      $override_views Optional. Override value to use instead of budgeted_views for calculations. If not provided, will be fetched from ACF.
	 * @return int The calculated premium score.
	 */
	public static function calculate_premium_plus_pacing_score(
		int $post_id,
		?int $premium_level = null,
		?int $budgeted_views = null,
		?int $views_remaining = null,
		?int $override_views = null
	): int {
		// Clear meta cache to ensure we get fresh values from database.
		// This prevents race conditions where cached values are stale during ACF save operations.
		wp_cache_delete( $post_id, 'post_meta' );

		// Get premium level if not provided.
		// ACF field 'premium' can be stored as either a simple value (0/1/2) or array with format: ['value' => 0/1/2, 'label' => 'Free'/'Premium'/'Premium+'].
		// Fallback support for old data structure.
		if ( null === $premium_level ) {
			$premium_level_raw = get_post_meta( $post_id, 'premium', true );

			// Extract value from ACF button group - handle both array and simple value formats.
			if ( is_array( $premium_level_raw ) && isset( $premium_level_raw['value'] ) ) {
				$premium_level = (int) $premium_level_raw['value'];
			} elseif ( is_numeric( $premium_level_raw ) ) {
				$premium_level = (int) $premium_level_raw;
			} else {
				// Fallback to 0 (Free) if field doesn't exist or format unexpected.
				$premium_level = 0;
			}
		}

		// Handle Premium+ logic (2 = Premium+).
		if ( 2 === $premium_level ) {
			// Get budget values if not provided.
			if ( null === $budgeted_views ) {
				$budgeted_views = (int) get_post_meta( $post_id, 'budgeted_views', true );
			}
			if ( null === $views_remaining ) {
				$views_remaining = (int) get_post_meta( $post_id, 'views_remaining', true );
			}
			if ( null === $override_views ) {
				$override_views = (int) get_post_meta( $post_id, 'override_views', true );
			}

			// Calculate total views: budgeted_views + override_views (override is additive).
			$effective_budget = $budgeted_views + $override_views;

			// Initialize views_remaining if not set but budget exists.
			// If views_remaining is 0 and views_consumed is also 0, assume full budget is available.
			if ( 0 === $views_remaining && $effective_budget > 0 ) {
				$views_consumed = (int) get_post_meta( $post_id, 'views_consumed', true );
				if ( 0 === $views_consumed ) {
					// Never consumed any views, so full budget should be available.
					$views_remaining = $effective_budget;
				}
			}

			// Ensure views_remaining is not negative.
			if ( $views_remaining < 0 ) {
				$views_remaining = 0;
			}

			// Check if budget fields exist and have values.
			if ( $effective_budget > 0 && $views_remaining > 0 ) {
				// Calculate pacing/burndown factor.
				$remaining_ratio = (float) $views_remaining / $effective_budget;

				// Base score from budget size (those who paid more get priority).
				// Log scale prevents massive differences but still rewards larger budgets.
				$budget_score = log10( $effective_budget + 1 ) * 150;

				// Pacing multiplier:
				// - Full boost (1.0) if 50%+ budget remaining.
				// - Reduced boost as budget depletes.
				// - Severe penalty if <10% remaining.
				$pacing_multiplier = 1.0;
				if ( $remaining_ratio < 0.1 ) {
					// Less than 10% remaining - severe penalty.
					$pacing_multiplier = 0.3;
				} elseif ( $remaining_ratio < 0.25 ) {
					// Less than 25% remaining - moderate penalty.
					$pacing_multiplier = 0.6;
				} elseif ( $remaining_ratio < 0.5 ) {
					// Less than 50% remaining - slight penalty.
					$pacing_multiplier = 0.85;
				}

				// Apply pacing to budget score.
				$final_score = $budget_score * $pacing_multiplier;

				// Add small boost for absolute remaining views (capped to prevent dominance).
				$remaining_boost = min( log10( $views_remaining + 1 ) * 20, 100 );
				$final_score    += $remaining_boost;

				// Cap boost at 999.
				return (int) ( 3000 + min( $final_score, 999 ) );
			}

			// Premium+ with exhausted budget (views_remaining = 0) or no budget configured = treat as regular Premium.
			return 2000;
		}

		// Regular Premium (1 = Premium).
		if ( 1 === $premium_level ) {
			return 2000;
		}

		// Free (default, 0 = Free).
		return 1000;
	}

	/**
	 * Update premium plus pacing score for a post
	 *
	 * Calculates and stores the premium_plus_pacing_score ACF field.
	 * Only updates for Premium+ listings since Premium and Free have static scores.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True if the field was updated, false otherwise.
	 */
	public static function update_premium_plus_pacing_score( int $post_id ): bool {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:ignore
			error_log( "RMG ES Utils: update_premium_plus_pacing_score() called for post {$post_id}" );
		}

		// Only calculate and store for Premium+ listings.
		// ACF field 'premium' can be stored as either a simple value (0/1/2) or array with format: ['value' => 0/1/2, 'label' => 'Free'/'Premium'/'Premium+'].
		$premium_level_raw = get_post_meta( $post_id, 'premium', true );

		// Extract value from ACF button group - handle both array and simple value formats.
		if ( is_array( $premium_level_raw ) && isset( $premium_level_raw['value'] ) ) {
			$premium_level = (int) $premium_level_raw['value'];
		} elseif ( is_numeric( $premium_level_raw ) ) {
			$premium_level = (int) $premium_level_raw;
		} else {
			// Fallback to 0 (Free) if field doesn't exist or format unexpected.
			$premium_level = 0;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			$level_name = ( 2 === $premium_level ) ? 'Premium+' : ( ( 1 === $premium_level ) ? 'Premium' : 'Free' );
			// phpcs:ignore
			error_log( "RMG ES Utils: premium_level for post {$post_id}: {$premium_level} ({$level_name})" );
		}

		if ( 2 !== $premium_level ) {
			// For Premium (1) and Free (0), store static scores to ensure proper sorting.
			// Premium = 2000, Free = 1000.
			// This ensures exhausted Premium+ (score 2000) sorts alongside regular Premium.
			$static_score = ( 1 === $premium_level ) ? 2000 : 1000;
			$result       = update_post_meta( $post_id, 'premium_plus_pacing_score', $static_score );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
				$level_name = ( 1 === $premium_level ) ? 'Premium' : 'Free';
				// phpcs:ignore
				error_log( "RMG ES Utils: Stored static score {$static_score} for {$level_name} post {$post_id}" );
			}
			return $result;
		}

		// Calculate the score for Premium+ listing.
		$score = self::calculate_premium_plus_pacing_score( $post_id, $premium_level );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// phpcs:ignore
			error_log( "RMG ES Utils: Calculated premium_plus_pacing_score for post {$post_id}: {$score}" );
		}

		// Store the calculated score.
		// Note: When budget is exhausted, score will be 2000 (same as regular Premium).
		// This ensures exhausted Premium+ sorts at the same level as regular Premium in ES queries.
		$result = update_post_meta( $post_id, 'premium_plus_pacing_score', $score );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			// Verify the value was stored.
			$stored_value = get_post_meta( $post_id, 'premium_plus_pacing_score', true );

			if ( false === $result ) {
				// phpcs:ignore
				error_log( "RMG ES Utils: Score not updated (value unchanged or already exists): {$stored_value}" );
			} else {
				// phpcs:ignore
				error_log( "RMG ES Utils: Score updated successfully: {$stored_value}" );
			}
		}

		return $result;
	}

	/**
	 * Batch update premium plus pacing scores
	 *
	 * Updates the premium_plus_pacing_score for all rehab centers,
	 * or just Premium+ centers if specified.
	 *
	 * @param bool $premium_plus_only Optional. Only update Premium+ centers. Default false.
	 * @param int  $batch_size Optional. Number of posts to process per batch. Default 50.
	 * @return array Results array with counts.
	 */
	public static function batch_update_premium_plus_pacing_scores(
		bool $premium_plus_only = false,
		int $batch_size = 50
	): array {
		$results = array(
			'total'   => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		$args = array(
			'post_type'      => 'rehab-center',
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'paged'          => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		// Add meta query for Premium+ only if specified.
		if ( $premium_plus_only ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'premium',
					'value'   => '2',
					'compare' => '=',
				),
			);
		}

		$query            = new WP_Query( $args );
		$results['total'] = $query->found_posts;

		while ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				try {
					if ( self::update_premium_plus_pacing_score( $post_id ) ) {
						++$results['updated'];
					} else {
						++$results['skipped'];
					}
				} catch ( Exception $e ) {
					$results['errors'][] = "Post ID {$post_id}: " . $e->getMessage();
				}
			}

			// Move to next page.
			++$args['paged'];
			$query = new WP_Query( $args );
		}

		wp_reset_postdata();

		return $results;
	}
}
