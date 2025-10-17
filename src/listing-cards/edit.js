import PropTypes from 'prop-types';

import { __ } from '@wordpress/i18n';
import { useEffect, useRef, useMemo } from '@wordpress/element';
import {
	AlignmentToolbar,
	BlockControls,
	HeadingLevelDropdown,
	RichText,
	useBlockProps,
} from '@wordpress/block-editor';
import { ToolbarGroup } from '@wordpress/components';

import Controls from './components/controls';
import { RenderMockTabs, RenderCards } from './components/render-cards';
import NativeScrollSlider from 'native-scroll-slider';

import './editor.scss';
import './style.scss';

export default function Edit( { attributes, setAttributes } ) {
	const {
		actionType = 'none',
		cardOptions = {
			hasBackground: true,
			showRank: true,
			showAddress: true,
			showInsurance: true,
		},
		hasBackground = false,
		headline = {
			text: 'Featured Facilities Near You',
			alignment: 'left',
			show: true,
		},
		layout = 'three-column',
		selectedTerms = {
			amenities: [],
			clinicalServices: [],
			levelsOfCare: [],
			paymentOptions: [],
			programs: [],
			treatmentOptions: [],
			tabTerms: [],
		},
		slidesToShow = 3,
	} = attributes;

	// Ref for the slider container
	const sliderRef = useRef( null );
	const sliderInstanceRef = useRef( null );

	// Mock card data for preview.
	const mockCards = useMemo( () => {
		const baseCard = {
			acceptsInsurance: true,
			award_description: 'Top 10 Rehab in TX',
			award: '1',
			city: 'Boynton Beach',
			rating: '4.7',
			reviews: '294 Reviews',
			state: 'FL',
			title: 'United Recovery Project – Luxury Rehab in Florida',
			zip: '33021',
		};

		const cardCount = 'slider' === layout ? 8 : 3;
		const randomIndex = Math.floor( Math.random() * cardCount );

		return Array( cardCount )
			.fill( null )
			.map( ( _, index ) => ( {
				...baseCard,
				id: index + 1,
				showAwards: index === randomIndex,
				award: index === randomIndex ? baseCard.award : '',
				award_description:
					index === randomIndex ? baseCard.award_description : '',
				cardOptions,
			} ) );
	}, [ layout, cardOptions ] );

	const blockProps = useBlockProps( {
		className: `premium-listings-cards layout-${ layout } ${
			hasBackground ? 'has-background' : ''
		}`,
		// Add ref only when layout is slider.
		...( 'slider' === layout && { ref: sliderRef } ),
		style: {
			'--slides-to-show': slidesToShow,
		},
	} );

	// Initialize slider when layout changes to slider or component mounts.
	useEffect( () => {
		if ( 'slider' === layout && sliderRef.current ) {
			// Find the slider container within our ref
			const trackElement =
				sliderRef.current.querySelector( '.slider-container' );

			if ( trackElement && ! sliderInstanceRef.current ) {
				try {
					// Create new slider instance
					sliderInstanceRef.current = new NativeScrollSlider(
						trackElement
					);
				} catch ( error ) {
					console.error( 'Error creating slider:', error );
				}
			}
		}

		// Cleanup function.
		return () => {
			if (
				sliderInstanceRef.current &&
				'function' === typeof sliderInstanceRef.current.destroy
			) {
				sliderInstanceRef.current.destroy();
				sliderInstanceRef.current = null;
			}
		};
	}, [ layout ] );

	// Cleanup on unmount.
	useEffect( () => {
		return () => {
			if (
				sliderInstanceRef.current &&
				'function' === typeof sliderInstanceRef.current.destroy
			) {
				sliderInstanceRef.current.destroy();
			}
		};
	}, [] );

	return (
		<>
			{ headline.show && (
				<BlockControls>
					<ToolbarGroup>
						<AlignmentToolbar
							value={ headline.alignment }
							onChange={ ( value ) =>
								setAttributes( {
									headline: { ...headline, alignment: value },
								} )
							}
						/>
						<BlockControls
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							group="block"
						>
							<HeadingLevelDropdown
								options={ [ 2, 3 ] }
								value={ headline.tag }
								onChange={ ( value ) =>
									setAttributes( {
										headline: { ...headline, tag: value },
									} )
								}
							/>
						</BlockControls>
					</ToolbarGroup>
				</BlockControls>
			) }

			<Controls
				attributes={ attributes }
				setAttributes={ setAttributes }
				termSelections={ selectedTerms }
				onChange={ ( newSelections ) => {
					setAttributes( {
						selectedTerms: {
							...selectedTerms,
							...newSelections,
						},
					} );
				} }
			/>

			<div { ...blockProps }>
				{ headline.show && (
					<RichText
						tagName={ `h${ headline.tag }` }
						className="listing-headline"
						value={ headline.text }
						onChange={ ( value ) =>
							setAttributes( {
								headline: { ...headline, text: value },
							} )
						}
						placeholder={ __(
							'Enter headline…',
							'rmg-premium-listings'
						) }
						style={ {
							textAlign: headline.alignment,
							marginTop: 0,
						} }
						allowedFormats={ [] }
					/>
				) }

				<RenderMockTabs
					termSelections={ selectedTerms }
					actionType={ actionType }
				/>
				<RenderCards layout={ layout } mockCards={ mockCards } />
			</div>
		</>
	);
}

Edit.propTypes = {
	attributes: PropTypes.object.isRequired,
	setAttributes: PropTypes.func.isRequired,
};
