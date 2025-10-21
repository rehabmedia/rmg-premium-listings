<?php
/**
 * Listing Cards Block Class
 *
 * @package rmg-premium-listings
 */

namespace RMG_Premium_Listings;

use RehabMediaGroup\Elasticsearch\Utilities;

/**
 * Class for rendering listing cards block.
 */
class Cards_Renderer {

	/**
	 * Block name.
	 *
	 * @var string
	 */
	private const BLOCK_NAME = 'rmg-premium-listings/cards';

	/**
	 * Default arguments for rendering.
	 *
	 * @var array
	 */
	private $default_args = array();

	/**
	 * Current rendering arguments.
	 *
	 * @var array
	 */
	private $args = array();

	/**
	 * Cards data.
	 *
	 * @var array
	 */
	private $cards_data = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_default_args();
	}

	/**
	 * Set default arguments.
	 *
	 * @return void
	 */
	private function set_default_args(): void {
		$this->default_args = array(
			'action_type'        => 'none',
			'card_count'         => 3,
			'card_options'       => array(
				'hasBackground' => false,
				'showRank'      => true,
				'showAddress'   => true,
				'showInsurance' => true,
			),
			'context'            => array(
				'post_id'                => get_the_ID(),
				'post_type'              => get_post_type(),
				'is_admin'               => is_admin(),
				'is_preview'             => defined( 'REST_REQUEST' ) && REST_REQUEST,
				'requires_location_data' => false,
			),
			'exclude_displayed'  => false,
			'has_background'     => false,
			'headline'           => array(
				'show'      => false,
				'text'      => __( 'Featured Facilities Near You', 'rmg-premium-listings' ),
				'alignment' => 'left',
				'tag'       => 2,
			),
			'slides_to_show'     => 3,
			'is_inline'          => false,
			'layout'             => 'three-column',
			'render_id'          => '',
			'selected_terms'     => array(
				'amenities'        => array(),
				'clinicalServices' => array(),
				'levelsOfCare'     => array(),
				'paymentOptions'   => array(),
				'programs'         => array(),
				'treatmentOptions' => array(),
			),
			'card_data'          => array(),
			'wrapper_classes'    => array(),
			'wrapper_attributes' => array(),
			'user_location'      => array(),
		);
	}

	/**
	 * Get rendered listing cards.
	 *
	 * @param array $args Rendering arguments.
	 * @return string The rendered HTML.
	 */
	public function get_render( array $args = array() ): string {
		// Auto-enqueue block assets if not already loaded.
		$this->ensure_assets_loaded();

		// Prepare arguments.
		$this->prepare_args( $args );

		// Check if location data is required but not provided.
		$requires_location = isset( $this->args['context']['requires_location_data'] )
			&& true === $this->args['context']['requires_location_data'];

		$has_user_location = ! empty( $this->args['user_location'] );

		// If location is required but not provided, render placeholder for client-side fetching.
		if ( $requires_location && ! $has_user_location ) {
			return $this->build_placeholder_output();
		}

		// Get cards data.
		$this->cards_data         = $this->get_cards_data();
		$this->args['cards_data'] = $this->cards_data;

		// Filter available terms if needed.
		if ( 'tabs' === $this->args['action_type'] && $this->has_selected_terms( $this->args['selected_terms'] ) ) {
			$this->args['selected_terms'] = $this->extract_selected_terms_from_cards();
		}

		// Build the output.
		return $this->build_output();
	}

	/**
	 * Echo rendered listing cards.
	 *
	 * @param array $args Rendering arguments.
	 * @return void
	 */
	public function render( array $args = array() ): void {
		echo $this->get_render( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Prepare rendering arguments.
	 *
	 * @param array $args Input arguments.
	 * @return void
	 */
	private function prepare_args( array $args ): void {
		// Set default card_count based on layout if not provided.
		if ( ! isset( $args['card_count'] ) && isset( $args['layout'] ) && 'slider' === $args['layout'] ) {
			$this->default_args['card_count'] = 8;
		}

		// Set default render_id.
		$this->default_args['render_id'] = uniqid( 'listing_cards_', true );

		// Merge arguments recursively.
		$merged_args = $this->parse_args_recursive( $args, $this->default_args );

		/**
		 * Filter the rendering arguments.
		 *
		 * @param array $args The rendering arguments.
		 * @return array The filtered rendering arguments.
		 */
		$this->args = apply_filters( 'rmg_premium_listings_render_args', $merged_args );
	}

	/**
	 * Recursively parse arguments, similar to wp_parse_args but handles nested arrays.
	 *
	 * @param array $args     The arguments to parse.
	 * @param array $defaults The default values.
	 * @return array The merged array.
	 */
	private function parse_args_recursive( $args, $defaults ): array {
		// Ensure both parameters are arrays.
		if ( ! is_array( $args ) ) {
			$args = array();
		}
		if ( ! is_array( $defaults ) ) {
			return $args;
		}

		// Start with the defaults.
		$result = $defaults;

		// Merge in the provided arguments.
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) && isset( $result[ $key ] ) && is_array( $result[ $key ] ) ) {
				// If both are arrays, merge recursively.
				$result[ $key ] = $this->parse_args_recursive( $value, $result[ $key ] );
			} else {
				// Otherwise, use the provided value.
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Render placeholder for location-based loading
	 *
	 * @return string The placeholder HTML
	 */
	private function build_placeholder_output(): string {
		// Prepare data attributes for client-side fetching.
		$data_args = $this->args;

		// Add the current display context to args.
		$data_args['display_context'] = Cards_Registry::get_context_key();

		// Add a flag to indicate this is initial location-based loading.
		$data_args['is_location_load'] = true;

		// Get already displayed IDs if exclude_displayed is true.
		if ( ! empty( $data_args['exclude_displayed'] ) ) {
			$data_args['already_displayed'] = Cards_Registry::get_displayed();
		}

		// Remove unnecessary data from data attributes.
		unset( $data_args['context']['is_admin'] );
		unset( $data_args['context']['is_preview'] );
		unset( $data_args['context']['requires_location_data'] );

		$this->args['wrapper_classes'][] = 'listing-cards-placeholder';
		$wrapper_attributes              = $this->get_wrapper_attributes();

		ob_start();
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<section
			<?php echo $wrapper_attributes; ?>
			id="<?php echo esc_attr( $this->args['render_id'] ); ?>-placeholder"
			data-listing-args="<?php echo esc_attr( wp_json_encode( $data_args ) ); ?>"
			data-requires-location="true"
		>
			<?php echo $this->build_base_section_output( 'render_placeholder_layout' ); ?>
		</section>
		<?php
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}

	/**
	 * Build location placeholder markup.
	 *
	 * @return string
	 */
	private function render_placeholder_layout(): string {
		ob_start();
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="cards-grid layout-three-column">
			<div class="listing-cards-loading">
				<div class="loading-spinner"></div>
				<p><?php esc_html_e( 'Finding facilities near you...', 'rmg-premium-listings' ); ?></p>
			</div>
		</div>
		<?php
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}

	/**
	 * Build the output HTML.
	 *
	 * @return string The HTML output.
	 */
	private function build_output(): string {
		$wrapper_attributes = $this->get_wrapper_attributes();

		ob_start();
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<section <?php echo $wrapper_attributes; ?>>
			<?php echo $this->build_base_section_output( 'render_layout' ); ?>
		</section>
		<?php
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}

	/**
	 * Render a section with common structure
	 *
	 * @param string $content_callback   Callback method name to render the main content.
	 * @return string The HTML output
	 */
	private function build_base_section_output( string $content_callback = '' ): string {
		$heading_tag = 'h' . ( $this->args['headline']['tag'] ?? 2 );

		ob_start();
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<header>
			<?php if ( $this->args['headline']['show'] && ! empty( $this->args['headline']['text'] ) ) : ?>
				<<?php echo wp_kses_post( $heading_tag ); ?>
					id="<?php echo esc_attr( $this->args['render_id'] ); ?>-headline"
					class="listing-headline"
					style="text-align: <?php echo esc_attr( $this->args['headline']['alignment'] ); ?>;"
					aria-describedby="<?php echo esc_attr( $this->args['render_id'] ); ?>-listings"
				>
					<?php echo wp_kses_post( $this->args['headline']['text'] ); ?>
				</<?php echo wp_kses_post( $heading_tag ); ?>>
			<?php endif; ?>

			<?php echo $this->render_tabs(); ?>
		</header>
		<?php
		// Call the content callback method if provided.
		if ( ! empty( $content_callback ) && method_exists( $this, $content_callback ) ) {
			echo $this->$content_callback();
		}
		?>
		<?php
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		return ob_get_clean();
	}

	/**
	 * Get wrapper attributes.
	 *
	 * @return string The wrapper attributes HTML.
	 */
	private function get_wrapper_attributes(): string {
		// Build the wrapper classes.
		$class_parts = array(
			'premium-listing-cards',
			'layout-' . sanitize_html_class( $this->args['layout'] ),
			'action-' . sanitize_html_class( $this->args['action_type'] ),
			$this->args['has_background'] ? 'has-background' : '',
			$this->args['is_inline'] ? 'inline' : '',
			...$this->args['wrapper_classes'],
		);

		/**
		 * Filter the wrapper classes for the listing cards block.
		 *
		 * Allows modification of the CSS classes applied to the block wrapper.
		 * Useful for backward compatibility or custom styling needs.
		 *
		 * @param array $class_parts Array of CSS class names.
		 * @param array $args        The rendering arguments for this block instance.
		 * @return array Modified array of CSS class names.
		 */
		$class_parts = apply_filters( 'rmg_premium_listings_wrapper_classes', $class_parts, $this->args );

		$this->args['wrapper_attributes'] = array_merge(
			$this->args['wrapper_attributes'],
			array(
				'style' => '--slides-to-show: ' . $this->args['slides_to_show'] . ';',
				'id'    => $this->args['render_id'],
			)
		);

		// Handle wrapper attributes based on context.
		if ( has_filter( 'render_block' ) && function_exists( 'get_block_wrapper_attributes' ) && did_action( 'render_block' ) ) {
			// We're in block context, use the block function.
			return get_block_wrapper_attributes(
				array(
					'class' => implode( ' ', $class_parts ),
					...$this->args['wrapper_attributes'],
				)
			);
		} else {
			// We're outside block context, build attributes manually.
			$attributes = array(
				'class' => 'wp-block-rmg-premium-listings-cards ' . implode( ' ', $class_parts ),
				...$this->args['wrapper_attributes'],
			);

			$wrapper_attributes = '';
			foreach ( $attributes as $key => $value ) {
				if ( ! empty( $value ) ) {
					$wrapper_attributes .= sprintf(
						' %s="%s"',
						esc_attr( $key ),
						esc_attr( $value )
					);
				}
			}
			return trim( $wrapper_attributes );
		}
	}

	/**
	 * Ensure listing cards block assets are enqueued.
	 *
	 * @return void
	 */
	private function ensure_assets_loaded(): void {
		// Get the registered block type.
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );

		if ( ! $block_type ) {
			return;
		}

		// Enqueue view scripts (frontend JS like view.js).
		if ( ! empty( $block_type->view_script_handles ) ) {
			foreach ( $block_type->view_script_handles as $handle ) {
				if ( ! wp_script_is( $handle, 'enqueued' ) ) {
					wp_enqueue_script( $handle );
				}
			}
		}

		// Enqueue styles (frontend CSS).
		if ( ! empty( $block_type->style_handles ) ) {
			foreach ( $block_type->style_handles as $handle ) {
				if ( ! wp_style_is( $handle, 'enqueued' ) ) {
					wp_enqueue_style( $handle );
				}
			}
		}
	}

	/**
	 * Get listing cards data based on context and filters.
	 *
	 * @return array Array of card data.
	 */
	private function get_cards_data(): array {
		$data_handler = new ES_Query();
		$result       = $data_handler->init( $this->args );

		// Handle REST response format.
		if ( is_array( $result ) && isset( $result['cards'] ) ) {
			return $result['cards'];
		}

		return $result;
	}

	/**
	 * Extract available terms from cards data.
	 *
	 * @return array Array of available terms.
	 */
	private function extract_selected_terms_from_cards(): array {
		$selected_terms = array();

		if ( 'tabs' === $this->args['action_type'] ) {
			foreach ( $this->cards_data as $term => $cards ) {
				foreach ( $cards as $card ) {
					if ( ! empty( $card['term_label'] ) ) {
						$selected_terms[] = $card['term_label'];
					}
				}
			}
		} else {
			foreach ( $this->cards_data as $card ) {
				if ( ! empty( $card['term_label'] ) ) {
					$selected_terms[] = $card['term_label'];
				}
			}
		}

		return array(
			'extracted' => array_values( array_unique( $selected_terms ) ),
		);
	}

	/**
	 * Determine if there are any selected terms.
	 *
	 * @param array|null $selected_terms Selected terms.
	 * @return boolean
	 */
	private function has_selected_terms( ?array $selected_terms ): bool {
		return ! empty( array_merge( ...array_values( (array) $selected_terms ) ) );
	}

	/**
	 * Render the tabs if applicable.
	 *
	 * @return string The tabs HTML.
	 */
	private function render_tabs(): string {
		if ( 'tabs' !== $this->args['action_type'] || ! $this->has_selected_terms( $this->args['selected_terms'] ) ) {
			return '';
		}

		$all_selected_terms = array_merge( ...array_values( $this->args['selected_terms'] ) );

		if ( empty( $all_selected_terms ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="listing-tabs">
			<div role="tablist" aria-labelledby="<?php echo esc_attr( $this->args['render_id'] ); ?>-headline" class="tab-buttons btn-group">
				<?php foreach ( $all_selected_terms as $index => $term ) : ?>
					<button
						class="btn-primary-large tab-button <?php echo 0 !== $index ? 'inactive' : 'active'; ?>"
						role="tab"
						id="<?php echo esc_attr( sanitize_title( $term ) ); ?>-tab"
						aria-controls="<?php echo esc_attr( sanitize_title( $term ) ); ?>-panel"
						data-tab="<?php echo esc_attr( sanitize_title( $term ) ); ?>"
						aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						tabindex="<?php echo 0 === $index ? '-1' : '0'; ?>"
					>
						<?php echo esc_html( $term ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the cards based on the selected layout.
	 *
	 * @return string The rendered cards HTML.
	 */
	private function render_layout(): string {
		if ( empty( $this->cards_data ) ) {
			return '';
		}

		$wrapper_tag = 'tabs' === $this->args['action_type'] ? 'div' : 'ul';

		ob_start();

		switch ( $this->args['layout'] ) {
			case 'slider':
				$this->render_slider_layout( $wrapper_tag );
				break;

			case 'vertical':
				$this->render_vertical_layout( $wrapper_tag );
				break;

			case 'three-column':
			default:
				$this->render_three_column_layout( $wrapper_tag );
				break;
		}

		return ob_get_clean();
	}

	/**
	 * Render slider layout.
	 *
	 * @param string $wrapper_tag The wrapper tag.
	 * @return void
	 */
	private function render_slider_layout( string $wrapper_tag ): void {
		/**
		 * Filter the slider configuration.
		 *
		 * @param array $slider_config The slider configuration.
		 * @return array The filtered slider configuration.
		 */
		$slider_config = apply_filters( 'rmg_premium_listings_slider_config', array() );
		?>
		<div class="cards-grid layout-slider">
			<<?php echo esc_attr( $wrapper_tag ); ?>
				aria-labelledby="<?php echo esc_attr( $this->args['render_id'] ); ?>-headline"
				id="<?php echo esc_attr( $this->args['render_id'] ); ?>-listings"
				<?php if ( 'tabs' !== $this->args['action_type'] ) : ?>
					class="slider-container"
				<?php endif; ?>
				<?php if ( ! empty( $slider_config ) ) : ?>
					data-slider-config="<?php echo esc_attr( wp_json_encode( $slider_config ) ); ?>"
				<?php endif; ?>
			>
				<?php $this->render_cards(); ?>
			</<?php echo esc_attr( $wrapper_tag ); ?>>
			<?php $this->render_slider_controls(); ?>
		</div>
		<?php
	}

	/**
	 * Render vertical layout.
	 *
	 * @param string $wrapper_tag The wrapper tag.
	 * @return void
	 */
	private function render_vertical_layout( string $wrapper_tag ): void {
		?>
		<<?php echo esc_attr( $wrapper_tag ); ?>
			aria-labelledby="<?php echo esc_attr( $this->args['render_id'] ); ?>-headline"
			id="<?php echo esc_attr( $this->args['render_id'] ); ?>-listings"
			class="cards-grid layout-vertical"
		>
			<?php $this->render_cards(); ?>
		</<?php echo esc_attr( $wrapper_tag ); ?>>
		<?php
	}

	/**
	 * Render three column layout.
	 *
	 * @param string $wrapper_tag The wrapper tag.
	 * @return void
	 */
	private function render_three_column_layout( string $wrapper_tag ): void {
		?>
		<div class="cards-grid layout-three-column">
			<<?php echo esc_attr( $wrapper_tag ); ?>
				aria-labelledby="<?php echo esc_attr( $this->args['render_id'] ); ?>-headline"
				id="<?php echo esc_attr( $this->args['render_id'] ); ?>-listings"
				<?php if ( 'tabs' !== $this->args['action_type'] ) : ?>
					class="slider-container"
				<?php endif; ?>
			>
				<?php $this->render_cards(); ?>
			</<?php echo esc_attr( $wrapper_tag ); ?>>
		</div>
		<?php
	}

	/**
	 * Main render function - decides between regular and tabbed layout.
	 *
	 * @return void
	 */
	private function render_cards(): void {
		if ( 'tabs' === $this->args['action_type'] ) {
			$this->render_tabbed_cards();
		} else {
			$this->render_default_cards();
		}
	}

	/**
	 * Render the regular listing cards.
	 *
	 * @return void
	 */
	private function render_default_cards(): void {
		foreach ( $this->cards_data as $card ) {
			$this->render_single_card( $card );
		}
	}

	/**
	 * Render the tabbed listing cards.
	 *
	 * @return void
	 */
	private function render_tabbed_cards(): void {
		$index = 0;
		foreach ( $this->cards_data as $term => $cards ) :
			$temp_args                = $this->args;
			$temp_args['action_type'] = 'none';
			?>
			<div
				role="tabpanel"
				id="<?php echo esc_attr( sanitize_title( $term ) ); ?>-panel"
				aria-labelledby="<?php echo esc_attr( sanitize_title( $term ) ); ?>-tab"
				<?php echo 0 !== $index ? 'hidden' : ''; ?>
			>
				<ul class="slider-container">
					<?php
					foreach ( $cards as $card ) {
						$this->render_single_card( $card );
					}
					?>
				</ul>
				<?php $this->render_tab_slider_controls(); ?>
			</div>
			<?php
			++$index;
		endforeach;
	}

	/**
	 * Render a single listing card.
	 *
	 * @param array $card Card data.
	 * @return void
	 */
	private function render_single_card( array $card ): void {
		/**
		 * Filter the card data.
		 *
		 * @param array $card The card data.
		 * @return array The filtered card data.
		 */
		$card = apply_filters( 'rmg_premium_listings_card_data', $card );

		// Build CSS classes.
		$classes = array( 'listing-card' );
		$classes = array_merge( $classes, $this->card_options_to_classes( $card['card_options'] ) );

		require RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'src/listing-cards/templates/listing-card.php';
	}

	/**
	 * Get the slider controls HTML markup.
	 *
	 * @return string The slider controls HTML.
	 */
	private function get_slider_controls_markup(): string {
		ob_start();
		?>
		<div class="slider-controls">
			<button class="slider-prev btn-gray-line" type="button" aria-label="<?php esc_attr_e( 'Previous slide', 'rmg-premium-listings' ); ?>">
				<?php require RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/svg/arrow-right.php'; ?>
			</button>
			<button class="slider-next btn-gray-line" type="button" aria-label="<?php esc_attr_e( 'Next slide', 'rmg-premium-listings' ); ?>">
				<?php require RMG_PREMIUM_LISTINGS_PLUGIN_DIR . 'inc/svg/arrow-right.php'; ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the slider controls for standard sliders (not within tabs).
	 *
	 * @return void
	 */
	private function render_slider_controls(): void {
		// Only render for slider layout when NOT in tabs mode.
		if ( 'slider' !== $this->args['layout'] || 'tabs' === $this->args['action_type'] ) {
			return;
		}

		echo $this->get_slider_controls_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render slider controls within tab panels.
	 *
	 * @return void
	 */
	private function render_tab_slider_controls(): void {
		// Only check if it's a slider layout.
		if ( 'slider' !== $this->args['layout'] ) {
			return;
		}

		echo $this->get_slider_controls_markup();  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Convert card options into CSS classes.
	 *
	 * @param array $options Array of options with boolean values.
	 * @return array Array of CSS classes.
	 */
	private function card_options_to_classes( array $options ): array {
		$classes = array();

		foreach ( $options as $key => $value ) {
			if ( $value ) {
				$class     = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $key ) );
				$classes[] = $class;
			}
		}

		return $classes;
	}

	/**
	 * Get the ranked place.
	 *
	 * @param int $number The number.
	 * @return string The ranked place.
	 */
	public static function get_ranked_place( $number ): string {
		if ( ! is_numeric( $number ) ) {
			return '';
		}

		// Handle special cases: 11th, 12th, 13th.
		if ( in_array( $number % 100, array( 11, 12, 13 ), true ) ) {
			$suffix = 'th';
		} else {
			// Handle standard suffixes.
			switch ( $number % 10 ) {
				case 1:
					$suffix = 'st';
					break;
				case 2:
					$suffix = 'nd';
					break;
				case 3:
					$suffix = 'rd';
					break;
				default:
					$suffix = 'th';
			}
		}

		/* translators: 1: ranking number, 2: ordinal suffix (st, nd, rd, th) */
		return sprintf( __( 'Ranked %1$d%2$s Place', 'rmg-premium-listings' ), $number, $suffix );
	}

	/**
	 * Echo the ranked place.
	 *
	 * @param int $number The number.
	 * @return void
	 */
	public static function the_ranked_place( $number ): void {
		echo self::get_ranked_place( $number ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Parse a single US address into street, city, state, and zip.
	 *
	 * @param string $address Address string.
	 * @param int    $post_id Post ID.
	 * @return array {street: string, city: string, state: string, zip: string}
	 */
	public static function parse_single_address_field( string $address, $post_id ): array {
		$parsed = array(
			'street' => '',
			'city'   => '',
			'state'  => '',
			'zip'    => '',
		);

		// Verify post exists before attempting to get terms.
		if ( ! get_post( $post_id ) ) {
			return $parsed;
		}

		$term = Utilities::rmg_get_primary_term( $post_id, 'rehab-centers' );

		if ( $term instanceof \WP_Term && ! empty( $address ) ) {
			$street = get_field( 'street_address_1', $post_id );
			$city   = $term?->name;
			$state  = get_field( 'state_abbr', 'rehab-centers_' . $term->parent );
			$zip    = get_field( 'zip_code', $post_id );

			$address = "{$street} {$city}, {$state} {$zip}";
		}

		if ( empty( $address ) ) {
			return $parsed;
		}

		// Normalize spaces and comma.
		$address = preg_replace( '/\s+/', ' ', trim( $address ) );
		$address = preg_replace( '/\s*,\s*/', ', ', $address );

		// First, try to extract state and ZIP from the end (most reliable).
		if ( preg_match( '/\b([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i', $address, $m ) ) {
			$parsed['state'] = strtoupper( $m[1] );
			$parsed['zip']   = $m[2];

			// Remove state and ZIP from address.
			$remaining = trim( preg_replace( '/' . preg_quote( $m[0], '/' ) . '$/', '', $address ) );

			// Remove trailing comma if present.
			$remaining = rtrim( $remaining, ', ' );

			// Now we need to separate street from city.
			// Strategy 1: Look for comma separation.
			if ( strpos( $remaining, ',' ) !== false ) {
				// Split on the LAST comma (in case street has commas).
				$last_comma_pos   = strrpos( $remaining, ',' );
				$parsed['street'] = trim( substr( $remaining, 0, $last_comma_pos ) );
				$parsed['city']   = trim( substr( $remaining, $last_comma_pos + 1 ) );
			} else {
				// Strategy 2: No comma found - need to intelligently split
				// Look for common street suffixes to identify where street ends.
				$street_patterns = array(
					// Common street suffixes.
					'/^(.+?\d+\s+\S+)\s+(Road|Rd|Street|St|Avenue|Ave|Boulevard|Blvd|Drive|Dr|Lane|Ln|Court|Ct|Circle|Cir|Place|Pl|Way|Parkway|Pkwy|Highway|Hwy|Trail|Trl|Square|Sq|Terrace|Ter|Pike|Loop|Path)\s+(.+)$/i',
					// Numbered streets (e.g., "123 5th Avenue").
					'/^(.+?\d+\s+\d+(?:st|nd|rd|th)\s+\S+)\s+(.+)$/i',
					// Streets with apartment/suite numbers.
					'/^(.+?(?:Apt|Suite|Ste|Unit|#)\s*\S+)\s+(.+)$/i',
					// Generic: number followed by 1-3 words as street, rest as city.
					'/^(\d+\s+(?:\S+\s+){0,2}\S+)\s+(.+)$/i',
				);

				$found = false;
				foreach ( $street_patterns as $pattern ) {
					if ( preg_match( $pattern, $remaining, $matches ) ) {
						$parsed['street'] = trim( $matches[1] );
						// Get the city part - it's the last captured group.
						$parsed['city'] = trim( $matches[ count( $matches ) - 1 ] );
						$found          = true;
						break;
					}
				}

				if ( ! $found ) {
					// Last resort: Split on last space-separated word as city
					// This handles simple cases like "123 Main Cityname".
					$words = explode( ' ', $remaining );
					if ( count( $words ) >= 2 ) {
						$parsed['city']   = array_pop( $words );
						$parsed['street'] = implode( ' ', $words );
					} else {
						// Give up and put everything in street.
						$parsed['street'] = $remaining;
					}
				}
			}
		} else {
			// No state/ZIP found - try the original master regex as fallback.
			$pattern = '/^(.+?),?\s+([A-Za-z\s\-]+?),?\s+([A-Z]{2})\s+(\d{5}(?:-\d{4})?)$/i';

			if ( preg_match( $pattern, $address, $m ) ) {
				$parsed['street'] = trim( $m[1] );
				$parsed['city']   = trim( $m[2] );
				$parsed['state']  = strtoupper( $m[3] );
				$parsed['zip']    = $m[4];
			} else {
				// If all else fails, store as street.
				$parsed['street'] = $address;
			}
		}

		return $parsed;
	}
}
