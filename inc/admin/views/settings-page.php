<?php
/**
 * Admin Settings Page Template
 *
 * Variables available: $configs, $default_config, $last_saved
 *
 * @package RMG_Premium_Listings
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template variables.

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$default_json = wp_json_encode(
	array(
		'layout'         => 'three-column',
		'hasBackground'  => false,
		'actionType'     => 'none',
		'isInline'       => false,
		'slidesToShow'   => 3,
		'headline'       => array(
			'show'      => true,
			'text'      => __( 'Featured Facilities Near You', 'rmg-premium-listings' ),
			'alignment' => 'left',
			'tag'       => 2,
		),
		'selectedTerms'  => array(
			'amenities'        => array(),
			'clinicalServices' => array(),
			'levelsOfCare'     => array(),
			'paymentOptions'   => array(),
			'programs'         => array(),
			'treatmentOptions' => array(),
		),
		'cardOptions'    => array(
			'hasBackground' => false,
			'showRank'      => true,
			'showAddress'   => true,
			'showInsurance' => true,
		),
		'displayOptions' => array(
			'bgColor'          => '',
			'borderColor'      => '',
			'textColor'        => '',
			'headingColor'     => '',
			'textHoverColor'   => '',
			'borderHoverColor' => '',
			'fontFamily'       => '',
			'padding'          => '',
			'margin'           => '',
			'borderRadius'     => '16px',
			'classname'        => '',
		),
	),
	JSON_PRETTY_PRINT
);
?>

<div class="wrap rmg-premium-listings-admin">
	<h1><?php esc_html_e( 'RMG Premium Listings - Embed Generator', 'rmg-premium-listings' ); ?></h1>

	<?php settings_errors( 'rmg_embed_settings' ); ?>

	<div class="rmg-admin-container">
		<div class="rmg-admin-main">
			<form method="post" action="" id="rmg-embed-form">
				<?php wp_nonce_field( 'rmg_embed_settings' ); ?>
				<input type="hidden" name="rmg_embed_action" value="save">

				<!-- Saved Configurations -->
				<div class="rmg-card">
					<h2><?php esc_html_e( 'Saved Configurations', 'rmg-premium-listings' ); ?></h2>
					<div class="rmg-form-row">
						<label for="saved-configs"><?php esc_html_e( 'Load Configuration:', 'rmg-premium-listings' ); ?></label>
						<div class="rmg-form-group">
							<select id="saved-configs" class="regular-text">
								<option value=""><?php esc_html_e( '-- Select a configuration --', 'rmg-premium-listings' ); ?></option>
								<?php foreach ( $configs as $name => $config_data ) : ?>
									<?php $is_selected = ( ! empty( $last_saved['name'] ) && $last_saved['name'] === $name ); ?>
									<?php
									// Handle both old format (direct config) and new format (with overrides).
									$config    = isset( $config_data['config'] ) ? $config_data['config'] : $config_data;
									$overrides = isset( $config_data['overrides'] ) ? $config_data['overrides'] : array(
										'referrer' => '',
										'state'    => '',
										'city'     => '',
									);
									$styling   = isset( $config_data['styling'] ) ? $config_data['styling'] : array(
										'classname'   => '',
										'font_family' => '',
										'padding'     => '',
										'margin'      => '',
									);
									// Get ref ID (generate from name if not stored).
									$ref = isset( $config_data['ref'] ) ? $config_data['ref'] : sanitize_key( $name );
									?>
									<option value="<?php echo esc_attr( $name ); ?>"
										<?php selected( $is_selected, true ); ?>
										data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
										data-ref="<?php echo esc_attr( $ref ); ?>"
										data-referrer="<?php echo esc_attr( $overrides['referrer'] ); ?>"
										data-state="<?php echo esc_attr( $overrides['state'] ); ?>"
										data-city="<?php echo esc_attr( $overrides['city'] ); ?>"
										data-classname="<?php echo esc_attr( $styling['classname'] ); ?>"
										data-font-family="<?php echo esc_attr( $styling['font_family'] ); ?>"
										data-padding="<?php echo esc_attr( $styling['padding'] ); ?>"
										data-margin="<?php echo esc_attr( $styling['margin'] ); ?>">
										<?php echo esc_html( $name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<button type="button" id="load-config" class="button">
								<?php esc_html_e( 'Load', 'rmg-premium-listings' ); ?>
							</button>
							<button type="button" id="delete-config" class="button button-secondary">
								<?php esc_html_e( 'Delete', 'rmg-premium-listings' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Configuration Name -->
				<div class="rmg-card">
					<h2><?php esc_html_e( 'Configuration Name', 'rmg-premium-listings' ); ?></h2>
					<div class="rmg-form-row">
						<label for="config-name"><?php esc_html_e( 'Name:', 'rmg-premium-listings' ); ?></label>
						<input type="text"
							id="config-name"
							name="config_name"
							class="regular-text"
							value="<?php echo esc_attr( $last_saved['name'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g., Sober.com - Homepage', 'rmg-premium-listings' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Give this configuration a descriptive name to save it for later use.', 'rmg-premium-listings' ); ?>
						</p>
					</div>
				</div>

				<!-- JSON Configuration -->
				<div class="rmg-card">
					<h2><?php esc_html_e( 'Embed Configuration (JSON)', 'rmg-premium-listings' ); ?></h2>
					<div class="rmg-form-row">
						<textarea id="config-json"
							name="config_json"
							rows="40"
							class="large-text code"
							spellcheck="false"><?php echo esc_textarea( ! empty( $last_saved['json'] ) ? $last_saved['json'] : $default_json ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Configure the embed settings using JSON.', 'rmg-premium-listings' ); ?>
						</p>
						<div class="rmg-button-group">
							<button type="button" id="validate-json" class="button">
								<?php esc_html_e( 'Validate JSON', 'rmg-premium-listings' ); ?>
							</button>
							<button type="button" id="format-json" class="button">
								<?php esc_html_e( 'Format JSON', 'rmg-premium-listings' ); ?>
							</button>
						</div>
						<div id="validation-message" class="rmg-validation-message"></div>
					</div>
				</div>

				<!-- Override Parameters -->
				<div class="rmg-card">
					<h2><?php esc_html_e( 'Override Parameters', 'rmg-premium-listings' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'These values will be passed as query parameters in the embed URL. They can override configuration values at runtime.', 'rmg-premium-listings' ); ?>
					</p>
					<div class="rmg-form-row">
						<label for="referrer-site"><?php esc_html_e( 'Referrer Site:', 'rmg-premium-listings' ); ?></label>
						<input type="text"
							id="referrer-site"
							name="referrer_site"
							class="regular-text"
							value="<?php echo esc_attr( $last_saved['referrer'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g., sober.com', 'rmg-premium-listings' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The domain where this embed will be placed (for tracking purposes).', 'rmg-premium-listings' ); ?>
						</p>
					</div>
					<div class="rmg-form-row">
						<label for="state-override"><?php esc_html_e( 'State Override:', 'rmg-premium-listings' ); ?></label>
						<input type="text"
							id="state-override"
							name="state_override"
							class="regular-text"
							value="<?php echo esc_attr( $last_saved['state'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g., florida', 'rmg-premium-listings' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Force results for a specific state (optional).', 'rmg-premium-listings' ); ?>
						</p>
					</div>
					<div class="rmg-form-row">
						<label for="city-override"><?php esc_html_e( 'City Override:', 'rmg-premium-listings' ); ?></label>
						<input type="text"
							id="city-override"
							name="city_override"
							class="regular-text"
							value="<?php echo esc_attr( $last_saved['city'] ); ?>"
							placeholder="<?php esc_attr_e( 'e.g., miami', 'rmg-premium-listings' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Force results for a specific city (optional).', 'rmg-premium-listings' ); ?>
						</p>
					</div>
				</div>


				<!-- Actions -->
				<div class="rmg-card">
					<div class="rmg-button-group actions" style="display: flex; justify-content: space-between; align-items: center;">
						<div>
							<button type="submit" class="button button-primary button-large">
								<?php esc_html_e( 'Save Configuration', 'rmg-premium-listings' ); ?>
							</button>
							<button type="button" id="generate-embed" class="button button-secondary button-large">
								<?php esc_html_e( 'Generate Embed Code', 'rmg-premium-listings' ); ?>
							</button>
						</div>
						<button type="button" id="reset-template" class="button button-link-delete button-large">
							<?php esc_html_e( 'Reset Template', 'rmg-premium-listings' ); ?>
						</button>
					</div>
				</div>
			</form>
		</div>

		<div class="rmg-admin-sidebar">
			<div class="rmg-card">
				<h3><?php esc_html_e( 'Configuration Options', 'rmg-premium-listings' ); ?></h3>
				<dl class="rmg-help-list">
					<!-- Layout Options -->
					<dt><code>layout</code></dt>
					<dd><strong>three-column</strong> - Displays 3 listings in a horizontal layout.</dd>
					<dd><strong>slider</strong> - Displays 8 listings in a slider format (3 visible by default).</dd>
					<dd><strong>vertical</strong> - Displays 3 listings in a vertical layout.</dd>

					<dt><code>hasBackground</code></dt>
					<dd><strong>Boolean</strong> - Adds background color and padding to the wrapper.</dd>

					<dt><code>actionType</code></dt>
					<dd><strong>none</strong> - Displays cards only (no filtering).</dd>
					<dd><strong>filter</strong> - Filters results by a single taxonomy term. Add one term to selectedTerms.</dd>
					<dd><strong>tabs</strong> - Shows tabs for filtering by multiple terms. Add multiple terms to selectedTerms.</dd>

					<dt><code>isInline</code></dt>
					<dd><strong>Boolean</strong> - Inline display mode for confined spaces (e.g., narrow containers).</dd>

					<dt><code>slidesToShow</code></dt>
					<dd><strong>Number</strong> - Cards visible without scrolling. Examples: 3, 1.6, 2.5</dd>

					<!-- Headline Options -->
					<dt><code>headline.show</code></dt>
					<dd><strong>Boolean</strong> - Show or hide the headline. Default: true</dd>

					<dt><code>headline.text</code></dt>
					<dd><strong>String</strong> - Headline text displayed above cards.</dd>

					<dt><code>headline.alignment</code></dt>
					<dd><strong>String</strong> - Text alignment: left, center, or right.</dd>

					<dt><code>headline.tag</code></dt>
					<dd><strong>Number</strong> - Heading level: 1-6 (h1 through h6).</dd>

					<!-- Filter Options -->
					<dt><code>selectedTerms</code></dt>
					<dd><strong>Object</strong> - Filter by taxonomy terms: amenities, clinicalServices, levelsOfCare, paymentOptions, programs, treatmentOptions. Use arrays for each taxonomy. Required for filter (single term) or tabs (multiple terms).</dd>

					<!-- Card Display Options -->
					<dt><code>cardOptions.hasBackground</code></dt>
					<dd><strong>Boolean</strong> - Adds background to individual cards. Changes border from dark to light.</dd>

					<dt><code>cardOptions.showRank</code></dt>
					<dd><strong>Boolean</strong> - Display rating and ranking information.</dd>

					<dt><code>cardOptions.showAddress</code></dt>
					<dd><strong>Boolean</strong> - Display facility address.</dd>

					<dt><code>cardOptions.showInsurance</code></dt>
					<dd><strong>Boolean</strong> - Display insurance acceptance badge.</dd>

					<!-- Style Options -->
					<dt><code>displayOptions.bgColor</code></dt>
					<dd><strong>String</strong> - Background color. Supports hex (#fff) or gradients. <em>CSS var: <code>--rmg-bg-color</code></em></dd>

					<dt><code>displayOptions.borderColor</code></dt>
					<dd><strong>String</strong> - Card border color. Supports hex (#fff) or gradients. <em>CSS var: <code>--rmg-border-color</code></em></dd>

					<dt><code>displayOptions.textColor</code></dt>
					<dd><strong>String</strong> - Primary text color. Supports hex (#fff) or gradients. <em>CSS var: <code>--rmg-text-color</code></em></dd>

					<dt><code>displayOptions.headingColor</code></dt>
					<dd><strong>String</strong> - Headline color. Supports hex (#fff) or gradients. <em>CSS var: <code>--rmg-heading-color</code></em></dd>

					<dt><code>displayOptions.textHoverColor</code></dt>
					<dd><strong>String</strong> - Link hover color. Supports hex (#fff) or gradients. <em>CSS var: <code>--rmg-text-hover-color</code></em></dd>

					<dt><code>displayOptions.borderHoverColor</code></dt>
					<dd><strong>String</strong> - Card border hover color. Supports hex (#fff) or gradients. <em>CSS var: <code>--rmg-border-hover-color</code></em></dd>

					<dt><code>displayOptions.fontFamily</code></dt>
					<dd><strong>String</strong> - Font family. Example: "Arial, sans-serif" or "Poppins, sans-serif". <em>CSS var: <code>--rmg-font-family</code></em></dd>

					<dt><code>displayOptions.padding</code></dt>
					<dd><strong>String</strong> - Block padding. Example: "1.5rem" or "24px". <em>CSS var: <code>--rmg-padding</code></em></dd>

					<dt><code>displayOptions.margin</code></dt>
					<dd><strong>String</strong> - Block margin. Example: "0 auto" or "20px 0". <em>CSS var: <code>--rmg-margin</code></em></dd>

					<dt><code>displayOptions.borderRadius</code></dt>
					<dd><strong>String</strong> - Border radius for cards and container. Example: "16px" or "8px". Default: "16px". <em>CSS var: <code>--rmg-border-radius</code></em></dd>

					<dt><code>displayOptions.classname</code></dt>
					<dd><strong>String</strong> - Custom CSS class(es) added to embed container. Supports space-separated values.</dd>
				</dl>
			</div>
		</div>
	</div>

	<!-- Generated Embed Code (Full Width) -->
	<div id="embed-output" class="rmg-card rmg-full-width" style="display: none;">
		<h2><?php esc_html_e( 'Generated Embed Code', 'rmg-premium-listings' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Copy and paste this code into your website where you want the listing cards to appear.', 'rmg-premium-listings' ); ?>
		</p>
		<div class="rmg-code-output">
			<pre><code id="embed-code"></code></pre>
		</div>
		<div class="rmg-button-group">
			<button type="button" id="copy-embed" class="button button-primary">
				<?php esc_html_e( 'Copy Embed to Clipboard', 'rmg-premium-listings' ); ?>
			</button>
			<button type="button" id="copy-url" class="button button-secondary">
				<?php esc_html_e( 'Copy URL to Clipboard', 'rmg-premium-listings' ); ?>
			</button>
			<span id="copy-success" class="rmg-copy-success" style="display: none;">
				<?php esc_html_e( 'Copied!', 'rmg-premium-listings' ); ?>
			</span>
			<span id="url-copy-success" class="rmg-copy-success" style="display: none;">
				<?php esc_html_e( 'URL Copied!', 'rmg-premium-listings' ); ?>
			</span>
		</div>
	</div>

	<!-- Preview (Full Width) -->
	<div id="embed-preview" class="rmg-card rmg-full-width" style="display: none;">
		<h2><?php esc_html_e( 'Preview', 'rmg-premium-listings' ); ?></h2>
		<div class="rmg-preview-container">
			<iframe id="preview-iframe" frameborder="0" width="100%"></iframe>
		</div>
	</div>
</div>

<!-- Delete Configuration Form -->
<form method="post" id="delete-config-form" style="display: none;">
	<?php wp_nonce_field( 'rmg_embed_settings' ); ?>
	<input type="hidden" name="rmg_embed_action" value="delete">
	<input type="hidden" name="config_to_delete" id="config-to-delete">
</form>
