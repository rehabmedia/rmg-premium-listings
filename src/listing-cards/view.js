import NativeScrollSlider from 'native-scroll-slider';

/**
 * Listing Cards Slider Module
 * Manages slider initialization, tabs, and location-based content loading
 */
const ListingCardsModule = ( () => {
	const SELECTORS = {
		container:
			'.listing-cards.layout-slider.wp-block-rmg-premium-listings-listing-cards',
		tabContainer: '.listing-cards.action-tabs',
		sliderTrack: '.slider-container',
		tabPanel: '[role="tabpanel"]',
		tabButton: '.tab-button[role="tab"]',
		tabList: '.listing-tabs',
		locationPlaceholder:
			'.listing-cards-placeholder[data-requires-location="true"]',
	};

	const ATTRS = {
		initialized: 'data-slider-initialized',
		listingArgs: 'data-listing-args',
		tab: 'data-tab',
	};

	const sliderInstances = new Map();

	/**
	 * Throttle function to limit scroll event firing rate
	 * @param {Function} func  - Function to throttle
	 * @param {number}   delay - Delay in milliseconds
	 * @return {Function} Throttled function
	 */
	const throttle = ( func, delay ) => {
		let timeoutId;
		let lastExecTime = 0;
		return function ( ...args ) {
			const currentTime = Date.now();

			if ( currentTime - lastExecTime > delay ) {
				func.apply( this, args );
				lastExecTime = currentTime;
			} else {
				clearTimeout( timeoutId );
				timeoutId = setTimeout(
					() => {
						func.apply( this, args );
						lastExecTime = Date.now();
					},
					delay - ( currentTime - lastExecTime )
				);
			}
		};
	};

	/**
	 * Get cards that are currently visible in the slider viewport
	 * @param {HTMLElement} sliderContainer - The slider container element
	 * @return {Array} Array of visible card elements
	 */
	const getVisibleCardsInSlider = ( sliderContainer ) => {
		const cards = sliderContainer.querySelectorAll(
			'.listing-card[data-impression-id]'
		);
		const containerRect = sliderContainer.getBoundingClientRect();

		return Array.from( cards ).filter( ( card ) => {
			const cardRect = card.getBoundingClientRect();

			// Check if card is within the slider container's visible area
			const isHorizontallyVisible =
				cardRect.left < containerRect.right &&
				cardRect.right > containerRect.left;

			const isVerticallyVisible =
				cardRect.top < containerRect.bottom &&
				cardRect.bottom > containerRect.top;

			// Card must have non-zero dimensions and be visible in both directions
			return (
				isHorizontallyVisible &&
				isVerticallyVisible &&
				0 < cardRect.width &&
				0 < cardRect.height
			);
		} );
	};

	/**
	 * Initialize a single slider element
	 * @param {HTMLElement} trackElement - The slider track element to initialize
	 * @param {string|null} panelId      - Optional panel ID for storing the slider instance
	 * @return {boolean} True if initialization was successful, false otherwise
	 */
	const initSlider = ( trackElement, panelId = null ) => {
		if ( trackElement.hasAttribute( ATTRS.initialized ) ) {
			return false;
		}

		try {
			const slider = new NativeScrollSlider( trackElement, {} );
			trackElement.setAttribute( ATTRS.initialized, 'true' );

			// Add scroll event listener to ensure newly visible cards are set up for tracking
			trackElement.addEventListener(
				'scroll',
				throttle( () => {
					if ( window.RMG_Impression_Tracker ) {
						const visibleCards =
							getVisibleCardsInSlider( trackElement );
						if ( 0 < visibleCards.length ) {
							console.log(
								`Slider scroll detected, setting up tracking for ${ visibleCards.length } visible cards`
							);
							// Just track the cards normally - let the impression tracker handle duplicates
							window.RMG_Impression_Tracker.track( visibleCards );
						}
					}
				}, 500 ),
				{ passive: true }
			);

			if ( panelId ) {
				sliderInstances.set( panelId, slider );
			}
			return true;
		} catch ( error ) {
			console.error( 'Error creating slider:', error );
			return false;
		}
	};

	/**
	 * Recalculate slider dimensions (for tabs)
	 * @param {Object} slider - The slider instance to recalculate
	 * @return {void}
	 */
	const recalculateSlider = ( slider ) => {
		if ( ! slider ) {
			return;
		}

		[
			'setupSlides',
			'calculateSlidePositions',
			'updateNavigation',
		].forEach( ( method ) => {
			if ( 'function' === typeof slider[ method ] ) {
				slider[ method ]();
			}
		} );
	};

	/**
	 * Initialize sliders for given containers
	 * @param {NodeList|Array|null} containers - Optional list of containers to initialize, defaults to all matching containers
	 * @return {void}
	 */
	const initializeSliders = ( containers = null ) => {
		const targets =
			containers || document.querySelectorAll( SELECTORS.container );

		targets.forEach( ( container ) => {
			if ( container.classList.contains( 'action-tabs' ) ) {
				initializeTabSlider( container );
			} else {
				const track = container.querySelector( SELECTORS.sliderTrack );
				if ( track ) {
					initSlider( track );
				}
			}
		} );
	};

	/**
	 * Initialize a tabbed slider container
	 * @param {HTMLElement} container - The container element with tabs
	 * @return {void}
	 */
	const initializeTabSlider = ( container ) => {
		const panels = container.querySelectorAll( SELECTORS.tabPanel );

		panels.forEach( ( panel ) => {
			const track = panel.querySelector( SELECTORS.sliderTrack );
			if ( track ) {
				initSlider( track, panel.id );
			}
		} );
	};

	/**
	 * Tab management class
	 * Handles tab navigation, keyboard controls, and panel visibility
	 */
	class TabManager {
		/**
		 * Create a new TabManager instance
		 * @param {HTMLElement} container - The container element for the tabbed interface
		 */
		constructor( container ) {
			this.container = container;
			this.tabList = container.querySelector( SELECTORS.tabList );

			if ( ! this.tabList ) {
				return;
			}

			this.tabs = [
				...this.tabList.querySelectorAll( SELECTORS.tabButton ),
			];
			this.panels = [
				...container.querySelectorAll( SELECTORS.tabPanel ),
			];

			this.init();
		}

		/**
		 * Initialize the tab manager
		 * Sets up event listeners and activates the first tab
		 * @return {void}
		 */
		init() {
			if ( 0 === this.tabs.length ) {
				return;
			}

			// Set up event listeners.
			this.tabs.forEach( ( tab ) => {
				tab.addEventListener( 'click', () => this.selectTab( tab ) );
				tab.addEventListener( 'keydown', ( e ) =>
					this.handleKeyNav( e, tab )
				);
			} );

			// Activate first tab.
			this.selectTab( this.tabs[ 0 ] );
		}

		/**
		 * Select and activate a specific tab
		 * @param {HTMLElement} selectedTab - The tab element to activate
		 * @return {void}
		 */
		selectTab( selectedTab ) {
			// Update tab states.
			this.tabs.forEach( ( tab ) => {
				const isActive = tab === selectedTab;
				tab.classList.toggle( 'active', isActive );
				tab.classList.toggle( 'inactive', ! isActive );
				tab.setAttribute( 'aria-selected', isActive );
				tab.setAttribute( 'tabindex', isActive ? '-1' : '0' );
			} );

			// Update panel visibility.
			this.panels.forEach( ( panel ) => {
				const panelId = panel.id;
				const shouldShow =
					panelId === selectedTab.getAttribute( 'aria-controls' );

				panel.hidden = ! shouldShow;
				panel.setAttribute( 'aria-hidden', ! shouldShow );

				if ( shouldShow ) {
					// Reset scroll and recalculate slider.
					const scrollContainer = panel.querySelector(
						SELECTORS.sliderTrack
					);
					if ( scrollContainer ) {
						scrollContainer.scrollTo( {
							left: 0,
							behavior: 'smooth',
						} );
					}

					// Recalculate after a brief delay for DOM update.
					setTimeout(
						() =>
							recalculateSlider( sliderInstances.get( panelId ) ),
						10
					);
				}
			} );

			// Set up tracking for newly visible cards (for clicks and normal impressions)
			if ( window.RMG_Impression_Tracker ) {
				setTimeout( () => {
					const activePanel = document.querySelector(
						`[id="${ selectedTab.getAttribute(
							'aria-controls'
						) }"]`
					);
					if ( activePanel && ! activePanel.hidden ) {
						const visibleCards = activePanel.querySelectorAll(
							'.listing-card[data-impression-id]'
						);

						if ( 0 < visibleCards.length ) {
							// Just track the cards normally - let the impression tracker handle duplicates
							window.RMG_Impression_Tracker.track( visibleCards );
						}
					}
				}, 50 );
			}
		}

		/**
		 * Handle keyboard navigation for tabs
		 * @param {KeyboardEvent} e          - The keyboard event
		 * @param {HTMLElement}   currentTab - The currently focused tab
		 * @return {void}
		 */
		handleKeyNav( e, currentTab ) {
			const currentIndex = this.tabs.indexOf( currentTab );
			let targetTab = null;

			const keyActions = {
				ArrowLeft: () =>
					this.tabs[ currentIndex - 1 ] ||
					this.tabs[ this.tabs.length - 1 ],
				ArrowRight: () =>
					this.tabs[ currentIndex + 1 ] || this.tabs[ 0 ],
				Home: () => this.tabs[ 0 ],
				End: () => this.tabs[ this.tabs.length - 1 ],
			};

			if ( keyActions[ e.key ] ) {
				e.preventDefault();
				targetTab = keyActions[ e.key ]();
				this.selectTab( targetTab );
				targetTab.focus();
			}
		}
	}

	/**
	 * Load location-based listings via AJAX
	 * Fetches and replaces placeholder elements with actual listing content
	 * @return {Promise<void>}
	 */
	const loadLocationBasedListings = async () => {
		const placeholders = document.querySelectorAll(
			SELECTORS.locationPlaceholder
		);

		// Track all displayed IDs from previous requests on this page
		let allDisplayedIds = [];

		// Process placeholders sequentially to ensure proper exclusion ordering
		for ( const placeholder of placeholders ) {
			try {
				const args = JSON.parse(
					placeholder.getAttribute( ATTRS.listingArgs ) || '{}'
				);

				// If this widget excludes displayed posts, add the accumulated IDs
				if ( args.exclude_displayed && 0 < allDisplayedIds.length ) {
					// Merge with any existing excluded IDs
					args.excluded_post_ids = [
						...( args.excluded_post_ids || [] ),
						...allDisplayedIds,
					];
					// Remove duplicates
					args.excluded_post_ids = [
						...new Set( args.excluded_post_ids ),
					];
				}

				const response = await fetch(
					'/wp-json/rmg/v1/premium-listing-cards',
					{
						method: 'POST',
						headers: {
							Accept: 'application/json',
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							...args,
							fetch_location: true,
						} ),
					}
				);

				if ( ! response.ok ) {
					throw new Error(
						`HTTP error! status: ${ response.status }`
					);
				}

				const data = await response.json();

				// Accumulate displayed IDs from this response
				if (
					data.displayed_ids &&
					Array.isArray( data.displayed_ids )
				) {
					allDisplayedIds = [
						...allDisplayedIds,
						...data.displayed_ids,
					];
					// Remove duplicates
					allDisplayedIds = [ ...new Set( allDisplayedIds ) ];
				}

				if ( data.success && data.html ) {
					replacePlaceholder( placeholder, data.html );
				} else {
					console.error(
						'No data in response for',
						`#${ placeholder.id }`
					);
				}
			} catch ( error ) {
				console.error(
					'Error loading listings:',
					error,
					`#${ placeholder.id }`
				);
				placeholder.hidden = true;
			}
		}
	};

	/**
	 * Replace placeholder element with new content
	 * @param {HTMLElement} placeholder - The placeholder element to replace
	 * @param {string}      html        - The HTML string to insert
	 * @return {void}
	 */
	const replacePlaceholder = ( placeholder, html ) => {
		const tempDiv = document.createElement( 'div' );
		tempDiv.innerHTML = html;
		const newElement = tempDiv.firstElementChild;

		if ( ! newElement || ! placeholder.parentNode ) {
			return;
		}

		placeholder.parentNode.replaceChild( newElement, placeholder );

		// Initialize features on new content.
		if ( newElement.classList.contains( 'layout-slider' ) ) {
			initializeSliders( [ newElement ] );
		}
		if ( newElement.classList.contains( 'action-tabs' ) ) {
			new TabManager( newElement );
		}
	};

	/**
	 * Set up mutation observer for dynamic content
	 * Watches for new slider containers added to the DOM
	 * @return {MutationObserver} The created observer instance
	 */
	const setupObserver = () => {
		const observer = new MutationObserver( ( mutations ) => {
			const newContainers = [];

			mutations.forEach( ( mutation ) => {
				mutation.addedNodes.forEach( ( node ) => {
					if ( node.nodeType !== Node.ELEMENT_NODE ) {
						return;
					}

					// Check if node is or contains slider containers.
					if ( node.matches?.( SELECTORS.container ) ) {
						newContainers.push( node );
					}
					const found = node.querySelectorAll?.(
						SELECTORS.container
					);
					if ( found?.length ) {
						newContainers.push( ...found );
					}
				} );
			} );

			if ( newContainers.length ) {
				requestAnimationFrame( () =>
					initializeSliders( newContainers )
				);
			}
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
		return observer;
	};

	/**
	 * Initialize module on DOM ready
	 * Sets up sliders, loads location-based content, and initializes tabs
	 * @return {void}
	 */
	const init = () => {
		initializeSliders();
		loadLocationBasedListings();
		setupObserver();

		// Initialize all tab containers.
		document
			.querySelectorAll( SELECTORS.tabContainer )
			.forEach( ( container ) => {
				new TabManager( container );
			} );
	};

	// Public API.
	return {
		init,
		initializeSliders,
		recalculateSlider,
	};
} )();

// Initialize on DOM ready.
if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', ListingCardsModule.init );
} else {
	ListingCardsModule.init();
}

// Export for global access if needed.
window.ListingCardsModule = ListingCardsModule;
