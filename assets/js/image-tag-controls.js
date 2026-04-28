/**
 * Custom image tag controls for BWS dynamic tags.
 *
 * Registers two custom control types via the generateblocks.editor.tagSpecificControls filter:
 *
 *   bws-img-size     — ComboboxControl populated from generateBlocksInfo.imageSizes.
 *   bws-media-picker — WP media library picker; stores attachment URL in the option key.
 *
 * One filter registration handles base `image`, `term_image`, and `try_image` tags.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.6.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}

	var el             = wp.element.createElement;
	var Fragment       = wp.element.Fragment;
	var ComboboxControl = wp.components.ComboboxControl;
	var Button         = wp.components.Button;
	var BaseControl    = wp.components.BaseControl;

	// --- Image size combobox ---

	function ImageSizeControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;

		var raw = window.generateBlocksInfo && generateBlocksInfo.imageSizes
			? generateBlocksInfo.imageSizes : [];

		var options = Array.isArray( raw )
			? raw
			: Object.keys( raw ).map( function ( slug ) {
				return { value: slug, label: typeof raw[ slug ] === 'string' ? raw[ slug ] : slug };
			} );

		return el( ComboboxControl, {
			label:    props.label,
			value:    state[ key ] || '',
			options:  options,
			onChange: function ( val ) {
				var upd = {}; upd[ key ] = val || '';
				setState( Object.assign( {}, state, upd ) );
			},
		} );
	}

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

		if ( 'bws-img-size' === cfg.type ) {
			return el( ImageSizeControl, { key: element.key, optionKey: element.key, label: cfg.label, context: context } );
		}
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
