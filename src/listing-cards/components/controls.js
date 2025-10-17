import PropTypes from 'prop-types';

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	RangeControl,
	SelectControl,
} from '@wordpress/components';

import GroupedTaxonomyDropdown from '../../components/taxonomy-dropdown';
import Notification from '../../components/notification';

export default function Controls( {
	attributes,
	setAttributes,
	termSelections,
	onChange,
} ) {
	const {
		actionType,
		cardOptions,
		excludeDisplayed,
		hasBackground,
		headline,
		isInline,
		layout,
		requiresLocationData,
		slidesToShow,
	} = attributes;

	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Layout Settings', 'rmg-premium-listings' ) }
				initialOpen
			>
				<Notification
					type="info"
					text={ __(
						'The cards visible are intended to be a preview of layout and functionality only. It is not a live preview of data or functional in most cases.',
						'rmg-premium-listings'
					) }
					style={ { marginBottom: 16 } }
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Show Headline', 'rmg-premium-listings' ) }
					checked={ headline.show }
					onChange={ ( value ) =>
						setAttributes( {
							headline: { ...headline, show: value },
						} )
					}
				/>

				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Layout Type', 'rmg-premium-listings' ) }
					value={ layout }
					options={ [
						{
							label: __( 'Three Column Grid', 'rmg-premium-listings' ),
							value: 'three-column',
						},
						{
							label: __( 'Horizontal Slider', 'rmg-premium-listings' ),
							value: 'slider',
						},
						{
							label: __( 'Vertical List', 'rmg-premium-listings' ),
							value: 'vertical',
						},
					] }
					onChange={ ( value ) => setAttributes( { layout: value } ) }
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Has Background', 'rmg-premium-listings' ) }
					checked={ hasBackground }
					help={ __(
						'Add background color and padding around the block.',
						'rmg-premium-listings'
					) }
					onChange={ ( value ) =>
						setAttributes( { hasBackground: value } )
					}
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Is Inline', 'rmg-premium-listings' ) }
					checked={ isInline }
					onChange={ ( value ) =>
						setAttributes( { isInline: value } )
					}
					help={ __(
						'Will append an "inline" class and adjust the output differently. For used inside limited-width or fix-width containers.',
						'rmg-premium-listings'
					) }
				/>

				{ ( 'slider' === layout || 'three-column' === layout ) && (
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Slides to Show', 'rmg-premium-listings' ) }
						value={ slidesToShow }
						onChange={ ( value ) =>
							setAttributes( { slidesToShow: value } )
						}
						min={ 1 }
						max={ 4 }
						step={ 0.1 }
					/>
				) }

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Use User Location Data?', 'rmg-premium-listings' ) }
					checked={ requiresLocationData }
					help={ __(
						'By default this block will output top centers. Toggle this on to attempt to fetch user location data and query targeted details.',
						'rmg-premium-listings'
					) }
					onChange={ ( value ) =>
						setAttributes( { requiresLocationData: value } )
					}
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Exclude Displayed', 'rmg-premium-listings' ) }
					checked={ excludeDisplayed }
					onChange={ ( value ) =>
						setAttributes( { excludeDisplayed: value } )
					}
					help={ __(
						'Exclude centers from being displayed if already shown earlier on the page. If this is the first instance of the block, this will have no effect.',
						'rmg-premium-listings'
					) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Filtering Options', 'rmg-premium-listings' ) }
				initialOpen={ 'none' !== actionType }
			>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Action Type', 'rmg-premium-listings' ) }
					value={ actionType }
					options={ [
						{ label: __( 'None', 'rmg-premium-listings' ), value: 'none' },
						{
							label: __( 'Filter results by term', 'rmg-premium-listings' ),
							value: 'filter',
						},
						{
							label: __(
								'Enable frontend tabbed filtering',
								'rmg-premium-listings'
							),
							value: 'tabs',
						},
					] }
					onChange={ ( value ) =>
						setAttributes( { actionType: value } )
					}
					help={ __(
						'Choose how results are displayed.',
						'rmg-premium-listings'
					) }
				/>

				{ 'filter' === actionType && (
					<div style={ { marginTop: '16px' } }>
						<GroupedTaxonomyDropdown
							label={ __(
								'Filter by Single Term',
								'rmg-premium-listings'
							) }
							selections={ termSelections }
							onChange={ onChange }
							placeholder={ __(
								'Select a single filter…',
								'rmg-premium-listings'
							) }
							multiSelect={ false }
						/>
					</div>
				) }

				{ 'tabs' === actionType && (
					<div style={ { marginTop: '16px' } }>
						<GroupedTaxonomyDropdown
							label={ __( 'Tab Terms', 'rmg-premium-listings' ) }
							selections={ termSelections }
							onChange={ onChange }
							placeholder={ __(
								'Select terms for tabs…',
								'rmg-premium-listings'
							) }
							multiSelect={ true }
						/>
					</div>
				) }
			</PanelBody>

			<PanelBody
				title={ __( 'Card Options', 'rmg-premium-listings' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Has Background', 'rmg-premium-listings' ) }
					checked={ cardOptions.hasBackground }
					onChange={ ( value ) =>
						setAttributes( {
							cardOptions: {
								...cardOptions,
								hasBackground: value,
							},
						} )
					}
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Show Rank', 'rmg-premium-listings' ) }
					checked={ cardOptions.showRank }
					onChange={ ( value ) =>
						setAttributes( {
							cardOptions: { ...cardOptions, showRank: value },
						} )
					}
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Show Address', 'rmg-premium-listings' ) }
					checked={ cardOptions.showAddress }
					onChange={ ( value ) =>
						setAttributes( {
							cardOptions: { ...cardOptions, showAddress: value },
						} )
					}
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Show Insurance', 'rmg-premium-listings' ) }
					checked={ cardOptions.showInsurance }
					onChange={ ( value ) =>
						setAttributes( {
							cardOptions: {
								...cardOptions,
								showInsurance: value,
							},
						} )
					}
				/>
			</PanelBody>
		</InspectorControls>
	);
}

Controls.propTypes = {
	attributes: PropTypes.object.isRequired,
	setAttributes: PropTypes.func.isRequired,
	termSelections: PropTypes.shape( {
		selectedAmenities: PropTypes.array,
		selectedClinicalServices: PropTypes.array,
		selectedLevelsOfCare: PropTypes.array,
		selectedPaymentOptions: PropTypes.array,
		selectedPrograms: PropTypes.array,
		selectedTabTerms: PropTypes.array,
		selectedTreatmentOptions: PropTypes.array,
	} ).isRequired,
	onChange: PropTypes.func.isRequired,
};
