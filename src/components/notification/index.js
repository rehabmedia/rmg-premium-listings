import PropTypes from 'prop-types';

/**
 * Get the colors for a notification based on the type
 * @param {string} type - The type of notification
 * @return {Object} - The colors for the notification
 */
export const getNotificationColors = ( type ) => {
	switch ( type ) {
		case 'info':
			return {
				backgroundColor: '#e3f2fd',
				color: '#1565c0',
			};
		case 'success':
			return {
				backgroundColor: '#e8f5e8',
				color: '#2e7d32',
			};
		case 'warning':
			return {
				backgroundColor: '#fff3e0',
				color: '#f57c00',
			};
		case 'error':
			return {
				backgroundColor: '#ffebee',
				color: '#c62828',
			};
		default:
			return {
				backgroundColor: '#f8f9fa',
				color: '#666',
			};
	}
};

/**
 * Notification component. Slim version of the Notice component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/components/notice/
 *
 * @param {Object} param0       - The props for the notification
 * @param {string} param0.text  - The text to display in the notification
 * @param {string} param0.type  - The type of notification
 * @param {Object} param0.style - The style to apply to the notification
 * @return {JSX.Element} - The notification component
 */
export default function Notification( { text, type, style = {} } ) {
	const colors = getNotificationColors( type );

	return (
		<div
			style={ {
				padding: 8,
				backgroundColor: colors.backgroundColor,
				borderRadius: 4,
				...style,
			} }
		>
			<small
				style={ {
					color: colors.color,
					fontSize: 12,
				} }
			>
				{ text }
			</small>
		</div>
	);
}

Notification.propTypes = {
	text: PropTypes.string.isRequired,
	type: PropTypes.string.isRequired,
	style: PropTypes.object,
};
