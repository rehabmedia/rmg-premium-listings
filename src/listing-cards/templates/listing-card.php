<?php
/**
 * Template for a single listing card.
 *
 * @package rmg-premium-listings
 */

if ( ! class_exists( 'RMG_Premium_Listings\Cards_Renderer' ) ) {
	return;
}

$listing_cards   = new \RMG_Premium_Listings\Cards_Renderer();
$address         = $listing_cards->parse_single_address_field( $card['address'], $card['id'] );
$card_options    = $card['card_options'];
$show_rank       = $card_options['showRank'];
$show_address    = $card_options['showAddress'];
$show_insurance  = $card_options['showInsurance'];
$has_award       = ! empty( $card['award'] ) && ! empty( $card['award_description'] );
$has_address     = ! empty( $address['city'] ) && ! empty( $address['state'] );
$has_rating      = ! empty( $card['rating'] ) && ! empty( $card['reviews'] );
$card_link       = $card['listing_link'];
$image_url       = $card['listing_image'];
$latitude        = get_field( '_pronamic_google_maps_latitude', $card['id'] );
$longitude       = get_field( '_pronamic_google_maps_longitude', $card['id'] );
$is_premium      = $card['premium'];
$is_premium_plus = 'premium+' === strtolower( $card['premium_level'] );

if ( isset( $card['term_label'] ) ) {
	$classes[] = sanitize_title( $card['term_label'] );
}

$card_wrapper_tag = $is_premium ? 'aside' : 'article';
?>

<li
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	data-card-id="<?php echo esc_attr( $card['id'] ); ?>"
	data-post-id="<?php echo esc_attr( $card['id'] ); ?>"
	data-impression-url="<?php echo esc_url( $card_link ); ?>"
	data-impression-post-id="<?php echo esc_attr( $card['id'] ); ?>"
	data-impression-type="<?php echo esc_attr( 'listing-card' ); ?>"
	data-impression-name="<?php echo esc_attr( $card['title'] ); ?>"
	data-impression-id="<?php echo esc_attr( 'listing-card-' . $card['id'] ); ?>"
	data-impression-state="<?php echo esc_attr( $card['state'] ); ?>"
	data-impression-city="<?php echo esc_attr( $card['city'] ); ?>"
	data-impression-premium="<?php echo esc_attr( $is_premium ? '1' : '0' ); ?>"
	data-impression-premium_plus="<?php echo esc_attr( $is_premium_plus ? '1' : '0' ); ?>"
>
		<<?php echo esc_attr( $card_wrapper_tag ); ?>>
		<div class="card-image">
			<a
				href="<?php echo esc_url( $card_link ); ?>"
				class="card-image-link"
				aria-label="
				<?php
				echo esc_attr(
					/* translators: %s is the card title */
					sprintf( __( 'View details for %s', 'rmg-premium-listings' ), $card['title'] )
				);
				?>
				"
			>
				<?php if ( ! empty( $image_url ) ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $card['title'] ); ?>" loading="lazy">
				<?php elseif ( ! empty( $latitude ) && ! empty( $longitude ) ) : ?>
					<?php
						\RMG_Premium_Listings\Helpers::the_map_layer(
							$card['address'],
							$latitude ?? '',
							$longitude ?? '',
							array(
								'iframe' => array(
									'height'          => '100%',
									'width'           => '100%',
									'allowfullscreen' => false,
								),
							)
						);
					?>
				<?php else : ?>
					<div class="card-image-placeholder">
						<span><?php echo esc_html( $card['title'] ); ?></span>
					</div>
				<?php endif; ?>
			</a>

			<?php if ( $is_premium ) : ?>
				<span class="ad-badge" aria-label="<?php esc_html_e( 'Advertisement', 'rmg-premium-listings' ); ?>"><?php esc_html_e( 'Ad', 'rmg-premium-listings' ); ?></span>
			<?php endif; ?>
		</div>

		<div class="card-content">
			<header class="card-header">
				<h3 class="card-title">
					<a href="<?php echo esc_url( $card_link ); ?>">
						<?php echo esc_html( $card['title'] ); ?>
					</a>
				</h3>
			</header>

			<div class="card-main">
				<?php if ( $has_award ) : ?>
					<div class="awards">
						<div class="award">
							<div class="award-badge" aria-label="<?php $listing_cards->the_ranked_place( $card['award'] ); ?>">
								<span><?php echo esc_html( $card['award'] ); ?></span>
								<?php require RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/svg/award.php'; ?>
							</div>
							<div class="award-description"><?php echo esc_html( $card['award_description'] ); ?></div>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( ! $has_award && $show_address && $has_address ) : ?>
					<div class="card-location">
						<?php echo esc_html( $address['city'] . ', ' . $address['state'] ); ?>
						<?php if ( ! empty( $address['zip'] ) ) : ?>
							<span class="zip-code"><?php echo esc_html( $address['zip'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<footer class="card-footer">
				<?php if ( $show_rank && $has_rating ) : ?>
					<div class="card-rating">
						<data class="rating-score" value="<?php echo esc_attr( round( $card['rating'], 1 ) ); ?>">
							<?php echo esc_html( round( $card['rating'], 1 ) ); ?>
						</data>
						<span class="rating-reviews">(<?php echo esc_html( $card['reviews'] ); ?> <?php esc_html_e( 'Reviews', 'rmg-premium-listings' ); ?>)</span>
					</div>
				<?php endif; ?>

				<?php if ( $card['accepts_insurance'] && $show_insurance ) : ?>
					<div class="insurance-badge">
						<?php require RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/svg/insurance.php'; ?>
						<?php esc_html_e( 'Accepts Insurance', 'rmg-premium-listings' ); ?>
					</div>
				<?php endif; ?>
			</footer>
		</div>
	</<?php echo esc_attr( $card_wrapper_tag ); ?>>
</li>
