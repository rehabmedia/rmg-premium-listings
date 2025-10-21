/**
 * RMG Premium Listings Admin JavaScript
 *
 * @package
 */

import '../scss/admin.scss';

( function () {
	'use strict';

	const RMGAdmin = {
		/**
		 * Initialize admin functionality.
		 */
		init() {
			this.bindEvents();
			this.setupEmbedResize();
			this.checkEmbedSections();
		},

		/**
		 * Check if embed sections should be visible on page load.
		 * If a config is loaded (from transient after save), auto-generate embed.
		 */
		checkEmbedSections() {
			const embedCodeEl = document.getElementById( 'embed-code' );
			const embedOutput = document.getElementById( 'embed-output' );
			const embedPreview = document.getElementById( 'embed-preview' );
			const configJson = document.getElementById( 'config-json' );
			const savedConfigsDropdown = document.getElementById( 'saved-configs' );

			// If embed code exists, show the sections.
			if ( embedCodeEl && embedCodeEl.textContent.trim() !== '' ) {
				if ( embedOutput ) {
					embedOutput.style.display = 'block';
				}
				if ( embedPreview ) {
					embedPreview.style.display = 'block';
				}
			}

			// If a config is selected and JSON is populated, auto-generate embed.
			if ( savedConfigsDropdown && savedConfigsDropdown.value !== '' && configJson && configJson.value.trim() !== '' ) {
				// Small delay to ensure DOM is ready.
				setTimeout( () => {
					this.generateEmbed();
				}, 100 );
			}
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents() {
			const loadConfigBtn = document.getElementById( 'load-config' );
			const deleteConfigBtn = document.getElementById( 'delete-config' );
			const resetTemplateBtn =
				document.getElementById( 'reset-template' );
			const validateJsonBtn = document.getElementById( 'validate-json' );
			const formatJsonBtn = document.getElementById( 'format-json' );
			const generateEmbedBtn =
				document.getElementById( 'generate-embed' );
			const copyEmbedBtn = document.getElementById( 'copy-embed' );
			const copyUrlBtn = document.getElementById( 'copy-url' );

			if ( loadConfigBtn ) {
				loadConfigBtn.addEventListener(
					'click',
					this.loadConfig.bind( this )
				);
			}
			if ( deleteConfigBtn ) {
				deleteConfigBtn.addEventListener(
					'click',
					this.deleteConfig.bind( this )
				);
			}
			if ( resetTemplateBtn ) {
				resetTemplateBtn.addEventListener(
					'click',
					this.resetTemplate.bind( this )
				);
			}
			if ( validateJsonBtn ) {
				validateJsonBtn.addEventListener(
					'click',
					this.validateJSON.bind( this )
				);
			}
			if ( formatJsonBtn ) {
				formatJsonBtn.addEventListener(
					'click',
					this.formatJSON.bind( this )
				);
			}
			if ( generateEmbedBtn ) {
				generateEmbedBtn.addEventListener(
					'click',
					this.generateEmbed.bind( this )
				);
			}
			if ( copyEmbedBtn ) {
				copyEmbedBtn.addEventListener(
					'click',
					this.copyEmbed.bind( this )
				);
			}
			if ( copyUrlBtn ) {
				copyUrlBtn.addEventListener(
					'click',
					this.copyUrl.bind( this )
				);
			}
		},

		/**
		 * Load selected configuration.
		 */
		loadConfig() {
			const select = document.getElementById( 'saved-configs' );
			const selectedOption = select.options[ select.selectedIndex ];
			const configData = selectedOption.dataset.config;

			if ( ! configData ) {
				// eslint-disable-next-line no-alert
				alert( 'Please select a configuration to load.' );
				return;
			}

			try {
				const config = JSON.parse( configData );
				document.getElementById( 'config-name' ).value =
					selectedOption.value;
				document.getElementById( 'config-json' ).value = JSON.stringify(
					config,
					null,
					2
				);

				// Load override parameters.
				document.getElementById( 'referrer-site' ).value =
					selectedOption.dataset.referrer || '';
				document.getElementById( 'state-override' ).value =
					selectedOption.dataset.state || '';
				document.getElementById( 'city-override' ).value =
					selectedOption.dataset.city || '';

				// All styling options now come from config.displayOptions in JSON.

				this.showValidationMessage(
					'Configuration loaded successfully.',
					'success'
				);
			} catch ( e ) {
				this.showValidationMessage(
					'Error loading configuration: ' + e.message,
					'error'
				);
			}
		},

		/**
		 * Delete selected configuration.
		 */
		deleteConfig() {
			const select = document.getElementById( 'saved-configs' );
			const configName = select.value;

			if ( ! configName ) {
				// eslint-disable-next-line no-alert
				alert( 'Please select a configuration to delete.' );
				return;
			}

			if (
				// eslint-disable-next-line no-alert
				! confirm(
					`Are you sure you want to delete the configuration "${ configName }"?`
				)
			) {
				return;
			}

			document.getElementById( 'config-to-delete' ).value = configName;
			document.getElementById( 'delete-config-form' ).submit();
		},

		/**
		 * Reset to default template.
		 */
		resetTemplate() {
			if (
				// eslint-disable-next-line no-alert
				! confirm(
					'Are you sure you want to reset all fields to default values?'
				)
			) {
				return;
			}

			const template = {
				layout: 'three-column',
				hasBackground: false,
				actionType: 'none',
				isInline: false,
				slidesToShow: 3,
				headline: {
					show: true,
					text: 'Featured Facilities Near You',
					alignment: 'left',
					tag: 2,
				},
				selectedTerms: {
					amenities: [],
					clinicalServices: [],
					levelsOfCare: [],
					paymentOptions: [],
					programs: [],
					treatmentOptions: [],
				},
				cardOptions: {
					hasBackground: false,
					showRank: true,
					showAddress: true,
					showInsurance: true,
				},
			};

			// Reset JSON configuration.
			document.getElementById( 'config-json' ).value = JSON.stringify(
				template,
				null,
				2
			);

			// Reset configuration name.
			document.getElementById( 'config-name' ).value = '';

			// Reset saved configuration dropdown.
			document.getElementById( 'saved-configs' ).value = '';

			// Reset override parameters.
			document.getElementById( 'referrer-site' ).value = '';
			document.getElementById( 'state-override' ).value = '';
			document.getElementById( 'city-override' ).value = '';

			// Hide embed sections on reset.
			const embedOutput = document.getElementById( 'embed-output' );
			const embedPreview = document.getElementById( 'embed-preview' );
			if ( embedOutput ) {
				embedOutput.style.display = 'none';
			}
			if ( embedPreview ) {
				embedPreview.style.display = 'none';
			}

			this.showValidationMessage(
				'All fields reset to default values.',
				'success'
			);
		},

		/**
		 * Validate JSON configuration.
		 */
		validateJSON() {
			const json = document.getElementById( 'config-json' ).value;

			// First check if it's valid JSON.
			try {
				JSON.parse( json );
			} catch ( e ) {
				this.showValidationMessage(
					'Invalid JSON: ' + e.message,
					'error'
				);
				return;
			}

			// Send to server for schema validation.
			const formData = new FormData();
			formData.append( 'action', 'rmg_validate_json' );
			formData.append( 'nonce', rmgPremiumListings.nonce ); // eslint-disable-line no-undef
			formData.append( 'json', json );

			// eslint-disable-next-line no-undef
			fetch( rmgPremiumListings.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			} )
				.then( ( response ) => response.json() )
				.then( ( data ) => {
					if ( data.success ) {
						this.showValidationMessage(
							data.data.message,
							'success'
						);
					} else {
						this.showValidationMessage(
							data.data.message,
							'error'
						);
					}
				} )
				.catch( () => {
					this.showValidationMessage(
						'Validation request failed.',
						'error'
					);
				} );
		},

		/**
		 * Format JSON with proper indentation.
		 */
		formatJSON() {
			const textarea = document.getElementById( 'config-json' );
			const json = textarea.value;

			try {
				const parsed = JSON.parse( json );
				textarea.value = JSON.stringify( parsed, null, 2 );
				this.showValidationMessage(
					'JSON formatted successfully.',
					'success'
				);
			} catch ( e ) {
				this.showValidationMessage(
					'Cannot format: Invalid JSON',
					'error'
				);
			}
		},

		/**
		 * Generate embed code.
		 */
		generateEmbed() {
			const json = document.getElementById( 'config-json' ).value;

			// Validate JSON first.
			let config;
			try {
				config = JSON.parse( json );
			} catch ( e ) {
				this.showValidationMessage(
					'Cannot generate: Invalid JSON',
					'error'
				);
				return;
			}

			// Build query parameters.
			const params = new URLSearchParams();
			params.append( 'config', btoa( JSON.stringify( config ) ) );

			const referrer = document
				.getElementById( 'referrer-site' )
				.value.trim();
			if ( referrer ) {
				params.append( 'referrer', referrer );
			}

			const state = document
				.getElementById( 'state-override' )
				.value.trim();
			if ( state ) {
				params.append( 'state', state );
			}

			const city = document
				.getElementById( 'city-override' )
				.value.trim();
			if ( city ) {
				params.append( 'city', city );
			}

			// Extract display options from config JSON.
			if ( config.displayOptions ) {
				if ( config.displayOptions.bgColor ) {
					params.append( 'bg_color', config.displayOptions.bgColor );
				}
				if ( config.displayOptions.borderColor ) {
					params.append(
						'border_color',
						config.displayOptions.borderColor
					);
				}
				if ( config.displayOptions.textColor ) {
					params.append(
						'text_color',
						config.displayOptions.textColor
					);
				}
				if ( config.displayOptions.headingColor ) {
					params.append(
						'heading_color',
						config.displayOptions.headingColor
					);
				}
				if ( config.displayOptions.textHoverColor ) {
					params.append(
						'text_hover_color',
						config.displayOptions.textHoverColor
					);
				}
				if ( config.displayOptions.borderHoverColor ) {
					params.append(
						'border_hover_color',
						config.displayOptions.borderHoverColor
					);
				}
				if ( config.displayOptions.fontFamily ) {
					params.append(
						'font_family',
						config.displayOptions.fontFamily
					);
				}
				if ( config.displayOptions.padding ) {
					params.append( 'padding', config.displayOptions.padding );
				}
				if ( config.displayOptions.margin ) {
					params.append( 'margin', config.displayOptions.margin );
				}
				if ( config.displayOptions.borderRadius ) {
					params.append( 'border_radius', config.displayOptions.borderRadius );
				}
			}

			const embedUrl = `${
				window.location.origin
			}/embed/listing-cards/?${ params.toString() }`;

			// Store the URL and custom classname for later use.
			this.currentEmbedUrl = embedUrl;
			this.customClassname =
				config.displayOptions && config.displayOptions.classname
					? config.displayOptions.classname
					: '';

			// Generate embed code.
			const embedCode = this.generateEmbedCode(
				embedUrl,
				this.customClassname
			);

			// Display embed code.
			const embedCodeEl = document.getElementById( 'embed-code' );
			const embedOutput = document.getElementById( 'embed-output' );
			const embedPreview = document.getElementById( 'embed-preview' );
			const previewIframe = document.getElementById( 'preview-iframe' );

			embedCodeEl.textContent = embedCode;
			embedOutput.style.display = 'block';

			// Show preview with parent page parameters for admin preview detection.
			const previewUrl = new URL( embedUrl, window.location.origin );
			// Note: URLSearchParams.append() automatically encodes values, so we don't use encodeURIComponent
			previewUrl.searchParams.append(
				'parent_url',
				window.location.href
			);
			previewUrl.searchParams.append(
				'parent_host',
				window.location.hostname
			);
			previewUrl.searchParams.append(
				'parent_referrer',
				document.referrer || ''
			);
			previewUrl.searchParams.append( 'parent_title', document.title );
			previewIframe.src = previewUrl.toString();
			embedPreview.style.display = 'block';

			this.showValidationMessage(
				'Embed code generated successfully!',
				'success'
			);

			// Scroll to the generated embed code section.
			embedOutput.scrollIntoView( {
				behavior: 'smooth',
				block: 'start',
			} );
		},

		/**
		 * Generate the embed code snippet.
		 *
		 * @param {string} url       Embed URL.
		 * @param {string} classname Optional custom CSS classname.
		 * @return {string} Embed code.
		 */
		generateEmbedCode( url, classname = '' ) {
			const divClass = classname
				? `rmg-premium-listings-embed ${ classname }`
				: 'rmg-premium-listings-embed';
			return `<!-- RMG Premium Listings Embed -->
<div id="rmg-premium-listings-embed" class="${ divClass }"></div>
<script>
(function() {
	var u = new URL('${ url }');
	u.searchParams.append('parent_url', location.href);
	u.searchParams.append('parent_host', location.hostname);
	u.searchParams.append('parent_referrer', document.referrer);
	u.searchParams.append('parent_title', document.title);

	var i = document.createElement('iframe');
	i.src = u;
	i.style.cssText = 'width:100%;border:none;overflow:hidden';
	i.scrolling = 'no';

	addEventListener('message', function(e) {
		if (e.data.type === 'rmg-embed-resize') i.style.height = e.data.height + 'px';
	});

	document.getElementById('rmg-premium-listings-embed').appendChild(i);
})();
</script>`;
		},

		/**
		 * Copy embed code to clipboard.
		 */
		copyEmbed() {
			const code = document.getElementById( 'embed-code' ).textContent;
			const copySuccess = document.getElementById( 'copy-success' );

			navigator.clipboard
				.writeText( code )
				.then( () => {
					copySuccess.style.display = 'inline';
					setTimeout( () => {
						copySuccess.style.display = 'none';
					}, 2000 );
				} )
				.catch( () => {
					// Fallback for older browsers.
					const textarea = document.createElement( 'textarea' );
					textarea.value = code;
					textarea.style.position = 'fixed';
					textarea.style.opacity = '0';
					document.body.appendChild( textarea );
					textarea.select();
					document.execCommand( 'copy' );
					document.body.removeChild( textarea );
					copySuccess.style.display = 'inline';
					setTimeout( () => {
						copySuccess.style.display = 'none';
					}, 2000 );
				} );
		},

		/**
		 * Copy embed URL to clipboard.
		 */
		copyUrl() {
			const url = this.currentEmbedUrl;
			const urlCopySuccess = document.getElementById( 'url-copy-success' );

			if ( ! url ) {
				this.showValidationMessage(
					'Please generate embed code first.',
					'error'
				);
				return;
			}

			navigator.clipboard
				.writeText( url )
				.then( () => {
					urlCopySuccess.style.display = 'inline';
					setTimeout( () => {
						urlCopySuccess.style.display = 'none';
					}, 2000 );
				} )
				.catch( () => {
					// Fallback for older browsers.
					const textarea = document.createElement( 'textarea' );
					textarea.value = url;
					textarea.style.position = 'fixed';
					textarea.style.opacity = '0';
					document.body.appendChild( textarea );
					textarea.select();
					document.execCommand( 'copy' );
					document.body.removeChild( textarea );
					urlCopySuccess.style.display = 'inline';
					setTimeout( () => {
						urlCopySuccess.style.display = 'none';
					}, 2000 );
				} );
		},

		/**
		 * Show validation message.
		 *
		 * @param {string} message Message text.
		 * @param {string} type    Message type (success/error).
		 */
		showValidationMessage( message, type ) {
			const msgEl = document.getElementById( 'validation-message' );
			msgEl.className = 'rmg-validation-message ' + type;
			msgEl.textContent = message;
			msgEl.style.display = 'block';
			setTimeout( () => {
				msgEl.style.display = 'none';
			}, 3000 );
		},

		/**
		 * Setup embed iframe resizing.
		 */
		setupEmbedResize() {
			window.addEventListener( 'message', ( e ) => {
				if ( 'rmg-embed-resize' === e.data.type ) {
					const iframe = document.getElementById( 'preview-iframe' );
					if ( iframe ) {
						iframe.style.height = e.data.height + 'px';
					}
				}
			} );
		},
	};

	// Initialize on DOM ready.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			RMGAdmin.init();
		} );
	} else {
		RMGAdmin.init();
	}
} )();
