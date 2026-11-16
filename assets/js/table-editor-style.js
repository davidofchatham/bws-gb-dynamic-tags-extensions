/**
 * {{table}} editor stylesheet: gives the editor canvas's styled tables their presentation.
 *
 * The preview replacement carries no CSS: GenerateBlocks runs kses over every replacement and kses
 * drops a `<style>` tag. A styled table carries its raw presentation tokens in a
 * `data-bws-table-style` attribute instead (bws_table_editor_payload() owns what is in it), and
 * this script builds the stylesheet from them with the grammar twin, once per scope class, in the
 * document the table lives in.
 *
 * Nothing hooks GB's replacement, so a MutationObserver watches for tables, in the admin document
 * and in any same-origin iframe in it (the block editor canvas, when it is one).
 *
 * The payload is data a replacement can carry, so it is distrusted: every value in it goes through
 * the twin's own validators, and only a scope class of the shape PHP makes is ever put in a
 * selector.
 *
 * @package BWS_Dynamic_Tags
 */
( function () {
	'use strict';

	var T = window.bwsTableStyle;
	if ( ! T ) {
		return;
	}

	var ATTR = 'data-bws-table-style';
	// The class bws_table_style_class() makes. Held to it by the corpus cases in
	// tools/test/table-editor-style-test.js: a PHP class this does not match gets no stylesheet there.
	var SCOPE = /^bws-dte-table-[0-9a-f]{10}$/;
	var V = T.vocab || {};

	var watched = new WeakSet();
	var hooked = new WeakSet();

	/** Add the value to the set; false when it was already there. */
	function addOnce( set, value ) {
		if ( set.has( value ) ) {
			return false;
		}
		set.add( value );
		return true;
	}

	function isPlainObject( value ) {
		return value !== null && typeof value === 'object' && ! Array.isArray( value );
	}

	/** The stripe defaults, each re-validated: a bad one falls back to the built-in. */
	function stripeDefaults( raw ) {
		var out = { color: V.stripeDefaultColor, opacity: V.stripeDefaultOpacity };
		if ( ! isPlainObject( raw ) ) {
			return out;
		}
		var color = typeof raw.color === 'string' ? T.colorValue( raw.color, false ) : null;
		if ( color !== null ) {
			out.color = color;
		}
		if ( typeof raw.opacity === 'number' && raw.opacity >= 0 && raw.opacity <= 100 ) {
			out.opacity = raw.opacity;
		}
		return out;
	}

	/** The scope class off a table's own class list, or '' when it has none of the PHP shape. */
	function scopeOf( table ) {
		var found = String( table.className || '' ).split( /\s+/ ).filter( function ( c ) {
			return SCOPE.test( c );
		} );
		return found.length === 1 ? found[ 0 ] : '';
	}

	/** The stylesheet text a table's payload asks for, or '' for anything unreadable. */
	function cssFor( table, scope ) {
		var payload;
		try {
			payload = JSON.parse( table.getAttribute( ATTR ) );
		} catch ( e ) {
			return '';
		}
		if ( ! isPlainObject( payload ) || ! isPlainObject( payload.t ) || ! Array.isArray( payload.c ) ) {
			return '';
		}
		var columns = payload.c.map( function ( col ) {
			return T.columnStyleRead( isPlainObject( col ) ? col : {} );
		} );
		var built = T.rules( T.styleRead( payload.t ), columns, payload.h === true, stripeDefaults( payload.s ) );
		return T.rulesCss( scope, built );
	}

	/** One stylesheet per scope class per document: identical tables share the scope, so the rules. */
	function apply( table ) {
		if ( ! table || ! table.hasAttribute || ! table.hasAttribute( ATTR ) ) {
			return;
		}
		var scope = scopeOf( table );
		var doc = table.ownerDocument;
		if ( scope === '' || ! doc || doc.getElementById( 'bws-table-' + scope ) ) {
			return;
		}
		var css = cssFor( table, scope );
		if ( css === '' ) {
			return;
		}
		var style = doc.createElement( 'style' );
		style.id = 'bws-table-' + scope;
		style.textContent = css;
		( doc.head || doc.documentElement ).appendChild( style );
	}

	function queryAll( root, selector ) {
		return root && root.querySelectorAll ? Array.prototype.slice.call( root.querySelectorAll( selector ) ) : [];
	}

	/** Attach a frame's document now and again whenever the frame loads. */
	function hookFrame( frame ) {
		if ( ! addOnce( hooked, frame ) ) {
			return;
		}
		function attach() {
			try {
				watch( frame.contentDocument );
			} catch ( e ) {
				// A frame this window may not read has nothing for us.
			}
		}
		attach();
		frame.addEventListener( 'load', attach );
	}

	/** Read everything under a node, the node included. */
	function scan( node ) {
		apply( node );
		queryAll( node, 'table[' + ATTR + ']' ).forEach( apply );
		if ( node && node.tagName === 'IFRAME' ) {
			hookFrame( node );
		}
		queryAll( node, 'iframe' ).forEach( hookFrame );
	}

	function watch( doc ) {
		if ( ! doc || ! addOnce( watched, doc ) ) {
			return;
		}
		scan( doc );
		new MutationObserver( function ( records ) {
			records.forEach( function ( record ) {
				if ( record.type === 'attributes' ) {
					apply( record.target );
					return;
				}
				Array.prototype.forEach.call( record.addedNodes, function ( node ) {
					if ( node.nodeType === 1 ) {
						scan( node );
					}
				} );
			} );
		} ).observe( doc, { childList: true, subtree: true, attributes: true, attributeFilter: [ ATTR ] } );
	}

	window.bwsTableEditorStyle = { watch: watch };
	watch( document );
}() );
