import PropTypes from 'prop-types';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Dropdown,
	MenuGroup,
	MenuItem,
	Spinner,
	BaseControl,
	CheckboxControl,
} from '@wordpress/components';
import Notification from '../notification';

export default function GroupedTaxonomyDropdown( {
	label,
	selections = {},
	onChange,
	placeholder = 'Select an option',
	multiSelect = false,
} ) {
	const [ allTerms, setAllTerms ] = useState( {
		treatmentOptions: { terms: [], loading: true, error: null },
		paymentOptions: { terms: [], loading: true, error: null },
		programs: { terms: [], loading: true, error: null },
		levelsOfCare: { terms: [], loading: true, error: null },
		clinicalServices: { terms: [], loading: true, error: null },
		amenities: { terms: [], loading: true, error: null },
	} );

	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	// Static taxonomy configuration
	const taxonomyGroups = [
		{
			key: 'treatmentOptions',
			fieldName: 'rmg.treatment.keyword',
			label: 'Treatment Options',
		},
		{
			key: 'paymentOptions',
			fieldName: 'rmg.payment.keyword',
			label: 'Payment Options',
		},
		{
			key: 'programs',
			fieldName: 'rmg.programs.keyword',
			label: 'Programs',
		},
		{
			key: 'levelsOfCare',
			fieldName: 'rmg.levels_of_care.keyword',
			label: 'Levels of Care',
		},
		{
			key: 'clinicalServices',
			fieldName: 'rmg.clinical_services.keyword',
			label: 'Clinical Services',
		},
		{
			key: 'amenities',
			fieldName: 'rmg.amenities.keyword',
			label: 'Amenities',
		},
	];

	useEffect( () => {
		let isMounted = true;

		const fetchAllTerms = async () => {
			try {
				// Create aggregations for all fields in a single query
				const aggregations = {};
				taxonomyGroups.forEach( ( group ) => {
					const aggKey = group.fieldName
						.replace( /\./g, '_' )
						.replace( /\[|\]/g, '' );
					aggregations[ aggKey ] = {
						terms: {
							field: group.fieldName,
							size: 100,
							order: { _count: 'desc' },
						},
					};
				} );

				const payload = JSON.stringify( {
					size: 0,
					query: {
						bool: {
							must: [
								{ match: { post_status: 'publish' } },
								{ match: { post_type: 'rehab-center' } },
								{ match: { 'rmg.status': true } },
								{
									bool: {
										must: [
											{
												exists: {
													field: 'rmg.featured_image',
												},
											},
											{
												bool: {
													must_not: [
														{
															term: {
																'rmg.featured_image.keyword':
																	'',
															},
														},
													],
												},
											},
										],
									},
								},
								{
									nested: {
										path: 'rmg.reviews',
										query: {
											bool: {
												must: [
													{
														exists: {
															field: 'rmg.reviews',
														},
													},
												],
											},
										},
									},
								},
							],
						},
					},
					aggs: aggregations,
				} );

				const response = await fetch(
					'/wp-json/rehab/v1/elasticsearch',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify( {
							query_string: '_search',
							payload,
						} ),
					}
				);

				if ( ! response.ok ) {
					throw new Error(
						`HTTP ${ response.status }: ${ response.statusText }`
					);
				}

				const data = await response.json();

				if ( isMounted ) {
					const newAllTerms = {};

					// Map the data to the newAllTerms object.
					// Replace the dots and square brackets with an underscore.
					taxonomyGroups.forEach( ( group ) => {
						const aggKey = group.fieldName
							.replace( /\./g, '_' )
							.replace( /\[|\]/g, '' );
						const buckets =
							data?.aggregations?.[ aggKey ]?.buckets || [];

						newAllTerms[ group.key ] = {
							terms: buckets.map( ( bucket ) => ( {
								label: bucket.key,
								value: bucket.key,
								count: bucket.doc_count,
							} ) ),
							loading: false,
							error: null,
						};
					} );

					setAllTerms( newAllTerms );
					setIsLoading( false );
				}
			} catch ( err ) {
				if ( isMounted ) {
					setError( err.message );
					setIsLoading( false );
				}
			}
		};

		fetchAllTerms();

		return () => {
			isMounted = false;
		};
	}, [] );

	/**
	 * Get the display text for the dropdown.
	 *
	 * @return {string} The display text for the dropdown.
	 */
	const getDisplayText = () => {
		// Count total selections across all groups.
		const totalSelections = Object.values( selections ).reduce(
			( acc, groupSelections ) => {
				return (
					acc +
					( Array.isArray( groupSelections )
						? groupSelections.length
						: 0 )
				);
			},
			0
		);

		if ( 0 === totalSelections ) {
			return placeholder;
		}

		if ( 1 === totalSelections ) {
			// Find the single selection and show it with group name.
			for ( const group of taxonomyGroups ) {
				const groupSelections = selections[ group.key ] || [];
				if ( 0 < groupSelections.length ) {
					const terms = allTerms[ group.key ]?.terms || [];
					const term = terms.find(
						( t ) => t.value === groupSelections[ 0 ]
					);
					return term
						? `${ group.label }: ${ term.label }`
						: `${ group.label }: ${ groupSelections[ 0 ] }`;
				}
			}
		}

		return `${ totalSelections } ${ __(
			'options selected',
			'rmg-premium-listings'
		) }`;
	};

	/**
	 * Handle the selection of a term.
	 *
	 * @param {string}  termValue  - The value of the term.
	 * @param {string}  groupKey   - The key of the group.
	 * @param {boolean} isSelected - Whether the term is selected.
	 */
	const handleSelection = ( termValue, groupKey, isSelected = null ) => {
		const currentGroupSelections = selections[ groupKey ] || [];

		if ( multiSelect ) {
			// Multi-select mode: toggle the term in the group.
			let newGroupSelections;
			const termIsSelected =
				null !== isSelected
					? isSelected
					: currentGroupSelections.includes( termValue );

			if ( termIsSelected ) {
				// Remove term.
				newGroupSelections = currentGroupSelections.filter(
					( val ) => val !== termValue
				);
			} else {
				// Add term.
				newGroupSelections = [ ...currentGroupSelections, termValue ];
			}

			onChange( {
				...selections,
				[ groupKey ]: newGroupSelections,
			} );
		} else {
			// Single-select mode: clear all other selections.
			const newSelections = {};
			taxonomyGroups.forEach( ( group ) => {
				newSelections[ group.key ] =
					group.key === groupKey ? [ termValue ] : [];
			} );
			onChange( newSelections );
		}
	};

	/**
	 * Render the dropdown.
	 *
	 * @return {JSX.Element} The dropdown component.
	 */
	if ( error ) {
		return (
			<BaseControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
			>
				<Notification
					text={ `${ __(
						'Error loading options:',
						'rmg-premium-listings'
					) } ${ error }` }
					type="error"
				/>
			</BaseControl>
		);
	}

	/**
	 * Render the dropdown.
	 *
	 * @param  root0
	 * @param  root0.isOpen
	 * @param  root0.onToggle
	 * @param  root0.onClose
	 * @return {JSX.Element} The dropdown component.
	 */
	return (
		<BaseControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
		>
			<Dropdown
				popoverProps={ { position: 'bottom left' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<button
						type="button"
						className="components-button dropdown-toggle-button"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						style={ {
							width: 248,
							maxWidth: '100%',
							justifyContent: 'space-between',
							textAlign: 'left',
							display: 'flex',
							alignItems: 'center',
							padding: '8px 12px',
							border: '1px solid #8c8f94',
							borderRadius: '4px',
							backgroundColor: 'white',
						} }
					>
						<span
							style={ {
								overflow: 'hidden',
								textOverflow: 'ellipsis',
								whiteSpace: 'nowrap',
								flex: 1,
							} }
						>
							{ getDisplayText() }
						</span>
						<span
							className="dashicons dashicons-arrow-down-alt2"
							style={ { marginLeft: '8px', flexShrink: 0 } }
						/>
					</button>
				) }
				renderContent={ ( { onClose } ) => (
					<div
						style={ {
							minWidth: '320px',
							maxHeight: '400px',
							overflowY: 'auto',
						} }
					>
						{ isLoading ? (
							<div
								style={ {
									padding: '16px',
									textAlign: 'center',
								} }
							>
								<Spinner />
								<p>
									{ __( 'Loading options…', 'rmg-premium-listings' ) }
								</p>
							</div>
						) : (
							<>
								<Notification
									text={
										! multiSelect
											? __(
												'Single selection mode - choosing an option clears others',
												'rmg-premium-listings'
											  )
											: __(
												'Multi-select mode: You can select multiple options per category for tab filtering.',
												'rmg-premium-listings'
											  )
									}
									type="info"
								/>

								{ taxonomyGroups.map( ( group ) => {
									const groupTerms =
										allTerms[ group.key ]?.terms || [];
									const groupSelections =
										selections[ group.key ] || [];

									return (
										<MenuGroup
											key={ group.key }
											label={ group.label }
										>
											{ groupTerms.map( ( term ) => {
												const isSelected =
													groupSelections.includes(
														term.value
													);

												if ( multiSelect ) {
													return (
														<div
															key={ term.value }
															style={ {
																padding:
																	'4px 12px',
															} }
														>
															<CheckboxControl
																__nextHasNoMarginBottom
																label={ `${ term.label } (${ term.count })` }
																checked={
																	isSelected
																}
																onChange={ (
																	checked
																) =>
																	handleSelection(
																		term.value,
																		group.key,
																		! checked
																	)
																}
															/>
														</div>
													);
												}
												return (
													<MenuItem
														key={ term.value }
														onClick={ () => {
															handleSelection(
																term.value,
																group.key
															);
															onClose();
														} }
														isSelected={
															isSelected
														}
													>
														{ `${ term.label } (${ term.count })` }
													</MenuItem>
												);
											} ) }

											{ 0 === groupTerms.length && (
												<MenuItem disabled>
													{ __(
														'No options available',
														'rmg-premium-listings'
													) }
												</MenuItem>
											) }
										</MenuGroup>
									);
								} ) }
							</>
						) }
					</div>
				) }
			/>
		</BaseControl>
	);
}

GroupedTaxonomyDropdown.propTypes = {
	label: PropTypes.string.isRequired,
	selections: PropTypes.object.isRequired,
	onChange: PropTypes.func.isRequired,
	placeholder: PropTypes.string,
	multiSelect: PropTypes.bool,
};
