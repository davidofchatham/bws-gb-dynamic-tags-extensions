/**
 * Custom format-string input for BWS datetime tags.
 *
 * Registers `bws-format-input` control type via generateblocks.editor.tagSpecificControls.
 *
 * Why: GB's tag-string parser splits `key:value` on the first colon and `|`-pairs on
 * pipes. PHP `parse_options()` unescapes `\:` and `\|` before splitting, but GB's JS
 * `parseTag()` (utils.js) splits on unescaped colon WITHOUT unescaping the value,
 * and GB's serializer writes `${key}:${value}` raw with no escaping. Format strings
 * like `l, F j, Y, g:i A` contain a colon in the time portion, so:
 *   - Raw save -> `format:l, F j, Y, g:i A` -> reopen splits at first `:` -> value
 *     truncated to `l, F j, Y, g` on the JS side (round-trip corruption).
 *
 * Fix: this control owns escape on save (`:` -> `\:`, `|` -> `\|`) and unescape on
 * display. PHP `parse_options()` unescapes before passing to `wp_date()`, so
 * render side is unchanged.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.7.4
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components ) {
		return;
	}

	var el          = wp.element.createElement;
	var TextControl = wp.components.TextControl;

	function escapeForTagString( str ) {
		if ( 'string' !== typeof str ) { return ''; }
		return str.replace( /\\/g, '\\\\' ).replace( /:/g, '\\:' ).replace( /\|/g, '\\|' );
	}

	function unescapeFromTagString( str ) {
		if ( 'string' !== typeof str ) { return ''; }
		// Walk the string treating `\` + next-char as a literal escape. Single-pass
		// avoids placeholder collisions with characters that may appear in the format.
		var out = '';
		for ( var i = 0; i < str.length; i++ ) {
			if ( '\\' === str[ i ] && i + 1 < str.length ) {
				out += str[ i + 1 ];
				i++;
			} else {
				out += str[ i ];
			}
		}
		return out;
	}

	function FormatInputControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;
		var raw      = state[ key ] || '';
		var display  = unescapeFromTagString( raw );

		function onChange( next ) {
			var newState = Object.assign( {}, state );
			if ( next ) {
				newState[ key ] = escapeForTagString( next );
			} else {
				// Match GB's native handleChange: drop the key entirely on empty
				// so the serializer emits nothing (not a bare `format:`).
				delete newState[ key ];
			}
			setState( newState );
		}

		return el( TextControl, {
			label:       props.label,
			help:        props.help,
			placeholder: props.placeholder,
			value:       display,
			onChange:    onChange,
		} );
	}

	function formatInputFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context ) { return element; }

		var cfg = allOptions[ element.key ];
		if ( ! cfg ) { return element; }

		if ( 'bws-format-input' === cfg.type ) {
			return el( FormatInputControl, {
				key:         element.key,
				optionKey:   element.key,
				label:       cfg.label,
				help:        cfg.help,
				placeholder: cfg.placeholder,
				context:     context,
			} );
		}

		return element;
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/format-input-control',
		formatInputFilter
	);

} )();
