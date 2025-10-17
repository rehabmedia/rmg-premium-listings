import PropTypes from 'prop-types';

import { __ } from '@wordpress/i18n';

import { ArrowRightIcon, InsuranceIcon, AwardIcon } from '../../common/svg';

export const RenderCard = ( card ) => {
	const { hasBackground, showRank, showAddress, showInsurance } =
		card.cardOptions || {};

	return (
		<div
			key={ card.id }
			className={ `listing-card ${
				hasBackground ? 'has-background' : ''
			}` }
		>
			<div className="card-image">
				<span className="ad-badge">Ad</span>
			</div>
			<div className="card-content">
				<h3 className="card-title">
					<a href="#">{ card.title }</a>
				</h3>

				<div className="card-main">
					{ card.award && (
						<div className="awards">
							<div className="award">
								<div className="award-badge">
									<span>{ card.award }</span>
									{ AwardIcon }
								</div>
								<div className="award-description">
									{ card.award_description }
								</div>
							</div>
						</div>
					) }
					{ showAddress && ! card.award && (
						<div className="card-location">
							{ card.city }, { card.state }{ ' ' }
							{ card.zip && (
								<span className="zip-code">{ card.zip }</span>
							) }
						</div>
					) }
				</div>

				<div className="card-footer">
					{ showRank && (
						<div className="card-rating">
							<span className="rating-score">
								{ card.rating }
							</span>
							<span className="rating-reviews">
								({ card.reviews })
							</span>
						</div>
					) }
					{ card.acceptsInsurance && showInsurance && (
						<div className="insurance-badge">
							{ InsuranceIcon }
							{ __( 'Accepts Insurance', 'rmg-premium-listings' ) }
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

RenderCard.propTypes = {
	card: PropTypes.object.isRequired,
};

// Render the mock tabs.
export const RenderMockTabs = ( { termSelections, actionType } ) => {
	const selections = Object.values( termSelections ).flat();
	const hasSelections = 0 < selections.length;

	if ( ! hasSelections || 'tabs' !== actionType ) {
		return null;
	}

	return (
		<div className="listing-tabs">
			<div className="tab-buttons btn-group" role="tablist">
				{ selections.map( ( selection, i ) => {
					const isActive = 0 === i;

					return (
						<button
							key={ selection }
							disabled
							className={ `tab-button btn-primary-large ${
								isActive ? 'active' : 'inactive'
							}` }
							role="tab"
							data-tab={ selection }
							aria-selected="false"
						>
							{ selection }
						</button>
					);
				} ) }
			</div>
		</div>
	);
};

RenderMockTabs.propTypes = {
	termSelections: PropTypes.object.isRequired,
	actionType: PropTypes.string.isRequired,
};

// Render the cards.
export const RenderCards = ( { layout, mockCards, cardOptions } ) => {
	if ( 'vertical' === layout ) {
		return (
			<div className="cards-grid layout-vertical">
				{ mockCards.map( RenderCard ) }
			</div>
		);
	}

	if ( 'slider' === layout ) {
		return (
			<div className="cards-grid layout-slider">
				<div className="slider-container">
					{ mockCards.map( RenderCard ) }
				</div>
				<div className="slider-controls">
					<button
						className="slider-prev btn-gray-line"
						type="button"
						aria-label="Previous slide"
					>
						{ ArrowRightIcon }
					</button>
					<button
						className="slider-next btn-gray-line"
						type="button"
						aria-label="Next slide"
					>
						{ ArrowRightIcon }
					</button>
				</div>
			</div>
		);
	}

	// Default three-column layout.
	return (
		<div className="cards-grid layout-three-column">
			<div className="slider-container">
				{ mockCards.map( RenderCard ) }
			</div>
		</div>
	);
};

RenderCards.propTypes = {
	layout: PropTypes.string.isRequired,
	mockCards: PropTypes.array.isRequired,
	cardOptions: PropTypes.object.isRequired,
};
