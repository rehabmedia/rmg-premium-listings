import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import metadata from './block.json';
import { pullquote } from '@wordpress/icons';

registerBlockType( metadata.name, {
	icon: pullquote,
	edit: Edit,
} );
