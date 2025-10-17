<?php
/**
 * Render the listing cards block.
 *
 * @package rmg-premium-listings
 */

// Extract attributes with defaults.
$render_id              = uniqid( 'listing_cards_', true );
$layout                 = $attributes['layout'] ?? 'three-column';
$has_background         = ! empty( $attributes['hasBackground'] );
$action_type            = $attributes['actionType'] ?? 'none';
$is_inline              = ! empty( $attributes['isInline'] );
$slides_to_show         = $attributes['slidesToShow'] ?? 3;
$exclude_displayed      = ! empty( $attributes['excludeDisplayed'] );
$requires_location_data = $attributes['requiresLocationData'] ?? true;
$headline               = $attributes['headline'] ?? array(
	'show'      => true,
	'text'      => __( 'Featured Facilities Near You', 'rmg-premium-listings' ),
	'alignment' => 'left',
	'tag'       => 2,
);
$selected_terms         = $attributes['selectedTerms'] ?? array(
	'amenities'        => array(),
	'clinicalServices' => array(),
	'levelsOfCare'     => array(),
	'paymentOptions'   => array(),
	'programs'         => array(),
	'treatmentOptions' => array(),
);
// Handle cardOptions with proper boolean conversion for WordPress empty string issue.
$card_options_raw = $attributes['cardOptions'] ?? array();
$card_options     = array(
	'hasBackground' => ! empty( $card_options_raw['hasBackground'] ),
	'showRank'      => ! empty( $card_options_raw['showRank'] ?? true ),
	'showAddress'   => ! empty( $card_options_raw['showAddress'] ?? true ),
	'showInsurance' => ! empty( $card_options_raw['showInsurance'] ?? true ),
);
$card_count       = 'slider' === $layout ? 8 : 3;
$context          = array(
	'post_id'                => get_the_ID(),
	'post_type'              => get_post_type(),
	'is_admin'               => is_admin(),
	'is_preview'             => defined( 'REST_REQUEST' ) && REST_REQUEST,
	'requires_location_data' => $requires_location_data,
);

// Build the render arguments.
$render_args = array(
	// Render ID.
	'render_id'          => $render_id,
	// Filtered by taxonomy or filterable by taxonomy.
	'action_type'        => $action_type,
	// Set, not dynamic.
	'card_count'         => $card_count,
	// Card options.
	'card_options'       => $card_options,
	// Card data (used for filter hook).
	'card_data'          => array(),
	// Context (tbd).
	'context'            => $context,
	// Excluded post IDs.
	'excluded_post_ids'  => array(),
	// Whether or not to exclude displayed posts.
	'exclude_displayed'  => $exclude_displayed,
	// Full block element has background.
	'has_background'     => $has_background,
	// Headline display, text, and alignment.
	'headline'           => $headline,
	// Whether or not to append an "inline" class.
	'is_inline'          => $is_inline,
	// Layout.
	'layout'             => $layout,
	// Selected tab terms.
	'selected_terms'     => $selected_terms,
	// Slides to show.
	'slides_to_show'     => $slides_to_show,
	// Wrapper classes.
	'wrapper_classes'    => array(
		'wp-block',
	),
	// Wrapper attributes.
	'wrapper_attributes' => array(),
);

// Render the block using the reusable function.
$listing_cards = new RMG_Premium_Listings_Cards_Renderer();
$listing_cards->render( $render_args );
