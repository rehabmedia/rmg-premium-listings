/**
 * Block Migration Script
 *
 * Automatically transforms rmg-blocks/listing-cards-v2 to rmg-premium-listings/cards
 */
import domReady from '@wordpress/dom-ready';
import { createBlock } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';

domReady( () => {
	// Unregister the old block if it exists to prevent it from showing in the inserter
	if ( wp.blocks.getBlockType( 'rmg-blocks/listing-cards-v2' ) ) {
		wp.blocks.unregisterBlockType( 'rmg-blocks/listing-cards-v2' );
	}

	// Re-register the old block as a hidden alias that auto-transforms
	// This allows existing content to still render and be edited
	const newBlockSettings = wp.blocks.getBlockType(
		'rmg-premium-listings/cards'
	);

	if ( newBlockSettings ) {
		wp.blocks.registerBlockType( 'rmg-blocks/listing-cards-v2', {
			...newBlockSettings,
			name: 'rmg-blocks/listing-cards-v2',
			title: 'Listing Cards V2 (Legacy - Auto-migrating)',
			parent: null,
			supports: {
				...newBlockSettings.supports,
				inserter: false, // Don't show in inserter
			},
		} );
	}
} );

/**
 * Add transformation rules to the new block
 * This allows users to manually convert old blocks to new ones
 */
addFilter(
	'blocks.registerBlockType',
	'rmg-premium-listings/add-legacy-transform',
	( settings, name ) => {
		if ( 'rmg-premium-listings/cards' === name ) {
			return {
				...settings,
				transforms: {
					...settings.transforms,
					from: [
						...( settings.transforms?.from || [] ),
						{
							type: 'block',
							blocks: [ 'rmg-blocks/listing-cards-v2' ],
							transform: ( attributes ) => {
								return createBlock(
									'rmg-premium-listings/cards',
									attributes
								);
							},
						},
					],
				},
			};
		}
		return settings;
	}
);
