/**
 * Custom image tag controls for BWS dynamic tags.
 *
 * Registers one custom control type via the generateblocks.editor.tagSpecificControls filter:
 *
 *   bws-media-picker — WP media library picker; stores attachment URL in the option key.
 *
 * Image size is handled by GenerateBlocks' native 'image-size' support (declared in supports
 * array on image-template tags), which renders its own ComboboxControl and handles
 * 'size:' parsing/serialization (default 'full' is stripped automatically).
 *
 * @package BWS_Dynamic_Tags
 * @since   1.6.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}

	var el          = wp.element.createElement;
	var Fragment    = wp.element.Fragment;
	var Button      = wp.components.Button;
	var BaseControl = wp.components.BaseControl;

	// --- Media picker ---

	function MediaPickerControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;
		var url      = state[ key ] || '';

		function open() {
			if ( ! window.wp || ! wp.media ) { return; }
			var frame = wp.media( {
				title: 'Select Fallback Image', button: { text: 'Select' },
				multiple: false, library: { type: 'image' },
			} );
			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON();
				var upd = {}; upd[ key ] = att.url || '';
				setState( Object.assign( {}, state, upd ) );
			} );
			frame.open();
		}

		function clear() {
			var upd = {}; upd[ key ] = '';
			setState( Object.assign( {}, state, upd ) );
		}

		return el( BaseControl, { label: props.label },
			url
				? el( Fragment, null,
					el( 'img', { src: url, style: { maxWidth: '100%', maxHeight: '80px', display: 'block', marginBottom: '4px' } } ),
					el( Button, { variant: 'secondary', onClick: open }, 'Change' ),
					el( Button, { variant: 'link', isDestructive: true, onClick: clear, style: { marginLeft: '8px' } }, 'Remove' )
				)
				: el( Button, { variant: 'secondary', onClick: open }, 'Select Image' )
		);
	}

	// --- Filter ---

	function imageTagControlsFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context ) { return element; }

		var cfg = allOptions[ element.key ];
		if ( ! cfg ) { return element; }

		if ( 'bws-media-picker' === cfg.type ) {
			return el( MediaPickerControl, { key: element.key, optionKey: element.key, label: cfg.label, context: context } );
		}

		return element;
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/image-tag-controls',
		imageTagControlsFilter
	);

} )();
