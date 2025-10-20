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
		'layout'        => 'three-column',
		'hasBackground' => false,
		'actionType'    => 'none',
		'isInline'      => false,
		'slidesToShow'  => 3,
		'headline'      => array(
			'show'      => true,
			'text'      => __( 'Featured Facilities Near You', 'rmg-premium-listings' ),
			'alignment' => 'left',
			'tag'       => 2,
		),
		'selectedTerms' => array(
			'amenities'        => array(),
			'clinicalServices' => array(),
			'levelsOfCare'     => array(),
			'paymentOptions'   => array(),
			'programs'         => array(),
			'treatmentOptions' => array(),
		),
		'cardOptions'   => array(
			'hasBackground' => false,
			'showRank'      => true,
			'showAddress'   => true,
			'showInsurance' => true,
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
									<?php
									// Handle both old format (direct config) and new format (with overrides).
									$config    = isset( $config_data['config'] ) ? $config_data['config'] : $config_data;
									$overrides = isset( $config_data['overrides'] ) ? $config_data['overrides'] : array(
										'referrer' => '',
										'state'    => '',
										'city'     => '',
									);
									?>
									<option value="<?php echo esc_attr( $name ); ?>"
										data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"
										data-referrer="<?php echo esc_attr( $overrides['referrer'] ); ?>"
										data-state="<?php echo esc_attr( $overrides['state'] ); ?>"
										data-city="<?php echo esc_attr( $overrides['city'] ); ?>">
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
							rows="27"
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
					<div class="rmg-button-group actions">
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Save Configuration', 'rmg-premium-listings' ); ?>
						</button>
						<button type="button" id="generate-embed" class="button button-secondary button-large">
							<?php esc_html_e( 'Generate Embed Code', 'rmg-premium-listings' ); ?>
						</button>
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
					<dt><code>layout</code></dt>
					<dd><strong><?php esc_html_e( 'three-column', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Displays 3 listings in a horizontal layout. ', 'rmg-premium-listings' ); ?></dd>
					<dd><strong><?php esc_html_e( 'slider', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Displays 8 listings in a slider-format. 3 visible on screen by default.', 'rmg-premium-listings' ); ?></dd>
					<dd><strong><?php esc_html_e( 'vertical', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Displays 3 listings in a vertical layout.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>hasBackground</code></dt>
					<dd><strong><?php esc_html_e( 'true/false', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Adds background color and padding to wrapper', 'rmg-premium-listings' ); ?></dd>

					<dt><code>actionType</code></dt>
					<dd><strong><?php esc_html_e( 'none:', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'defaults to displaying cards only.', 'rmg-premium-listings' ); ?></dd>
					<dd><strong><?php esc_html_e( 'filter:', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'filter the returned results by a single taxonomy term. Add a single term to the selectedTerms array.', 'rmg-premium-listings' ); ?></dd>
					<dd><strong><?php esc_html_e( 'tabs:', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'allow filtering by multiple taxonomy terms. Displays buttons for toggling between results. Add a comma-separated list of terms to the selectedTerms arrays.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>isInline</code></dt>
					<dd><strong><?php esc_html_e( 'true/false', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Inline display mode, for use in confined spaces (eg. narrow content container).', 'rmg-premium-listings' ); ?></dd>

					<dt><code>slidesToShow</code></dt>
					<dd><?php esc_html_e( 'Number of cards to show without scroll or slider wrap.', 'rmg-premium-listings' ); ?> <strong><?php esc_html_e( 'eg. 3 or 1.6, etc.', 'rmg-premium-listings' ); ?></strong> </dd>

					<dt><code>headline.show</code></dt>
					<dd><strong><?php esc_html_e( 'true/false', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Show/hide headline. Default true.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>headline.text</code></dt>
					<dd><?php esc_html_e( 'Headline text above cards.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>headline.alignment</code></dt>
					<dd><?php esc_html_e( 'left, center, or right.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>headline.tag</code></dt>
					<dd><?php esc_html_e( '1-6 (h1 through h6).', 'rmg-premium-listings' ); ?></dd>

					<dt><code>selectedTerms</code></dt>
					<dd><?php esc_html_e( 'Filter cards by taxonomy terms (amenities, clinicalServices, levelsOfCare, paymentOptions, programs, treatmentOptions). Supports comma-separated strings. Required for filter (single term) or tabs (multiple terms).', 'rmg-premium-listings' ); ?></dd>

					<dt><code>cardOptions.hasBackground</code></dt>
					<dd><strong><?php esc_html_e( 'true/false', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Add background to individual cards. Also changes border color from dark to light, if active.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>cardOptions.showRank</code></dt>
					<dd><strong><?php esc_html_e( 'true/false', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Display rating/ranking.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>cardOptions.showAddress</code></dt>
					<dd><strong><?php esc_html_e( 'true/false', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Display facility address.', 'rmg-premium-listings' ); ?></dd>

					<dt><code>cardOptions.showInsurance</code></dt>
					<dd><strong><?php esc_html_e( 'true/false', 'rmg-premium-listings' ); ?></strong> <?php esc_html_e( 'Display insurance badge.', 'rmg-premium-listings' ); ?></dd>
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
