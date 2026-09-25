/**
 * The `use` select on a base tag with a read axis ({{text}}, {{content}}, {{image}}),
 * displayed and written by the READ RULE rather than by its raw state (FW-142).
 *
 * The rule's owner is bws_use_effective() (includes/helpers/registration-helpers.php):
 * an explicit `use` wins, else a field token that implies a mode (`key` → key-mode),
 * else the tag's stripped default. effective() below is its twin, fed by the inlined
 * `window.bwsUseRules` (bws_use_read_rules()) so neither constant is copied here, and
 * tools/test/editor-filter-chain-test.js holds the two to one answer case for case.
 *
 * WRAP, DON'T REPLACE. GB's own SelectControl element is cloned with the DERIVED value and
 * a wrapped onChange, inside an invisible component that keeps the element's key:
 *
 *   - DISPLAY: `{{content key:foo}}` shows Meta/Option Field, not the Post Content row
 *     GB would paint for an absent `use`.
 *   - WRITE (a mode picked): `use` is written only when the tokens would not imply it,
 *     and a field token of a mode being LEFT is deleted (`delete`, never '') — the
 *     render already ignores it beside an explicit `use`, and left behind it would
 *     become the read the moment `use` went back to the stripped default.
 *   - WRITE (a field token changed): a `use` the tokens now imply is dropped, so picking
 *     a field on `{{content use:key}}` saves `{{content key:foo}}`.
 *
 *   - MOUNT: stored redundant wire gets the same normalize() — `{{content use:key|key:foo}}`
 *     → `{{content key:foo}}`, a stale `key` beside another mode dropped; keyed-pending
 *     `{{content use:key}}` is left alone. Licensed as LEGACY because current writes never
 *     emit it (docs/editor-controls.md §Why the image composite does NOT migrate on
 *     mount, the on-mount rule), and decidable
 *     here because `key` stays in extraTagParams on our tags. Every drop is a token the
 *     render ignores or one restating the implied mode, so output does not move; no
 *     converter entry. It persists only when the author confirms the modal.
 *
 * editor-conditional-options.js evaluates a `use` condition against effective() too, so
 * the field-key control stays visible while key-mode is only implied.
 *
 * The tag is identified by `readTag` on the `use` option definition (stamped on the field
 * leaves in base-shared.php) because the filter is handed options, never a tag name.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.21.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element ) {
		return;
	}

	var el        = wp.element.createElement;
	var useEffect = wp.element.useEffect;

	function rules() {
		var r = window.bwsUseRules || {};
		return { implied: r.implied || {}, defaults: r.defaults || {} };
	}

	function str( v ) {
		return ( v === null || v === undefined ) ? '' : String( v );
	}

	/**
	 * The effective `use` — bws_use_effective()'s twin. '' for a tag with no read axis.
	 *
	 * @param {string} tag   Base tag name.
	 * @param {Object} state extraTagParams.
	 * @return {string}
	 */
	function effective( tag, state ) {
		var r   = rules();
		var use = str( state && state.use );
		if ( '' !== use ) {
			return use;
		}
		var def = str( r.defaults[ tag ] );
		if ( '' === def ) {
			return '';
		}
		var tokens = Object.keys( r.implied );
		for ( var i = 0; i < tokens.length; i++ ) {
			if ( '' !== str( state && state[ tokens[ i ] ] ) ) {
				return r.implied[ tokens[ i ] ];
			}
		}
		return def;
	}

	/**
	 * Drop what an explicit `use` makes redundant: a field token of ANOTHER mode (stale),
	 * then `use` itself when the remaining tokens already imply it. Returns `state` itself
	 * when nothing changes, so a caller can bail on identity.
	 *
	 * @param {string} tag
	 * @param {Object} state
	 * @return {Object}
	 */
	function normalize( tag, state ) {
		var r   = rules();
		var use = str( state && state.use );
		if ( '' === use || '' === str( r.defaults[ tag ] ) ) {
			return state;
		}
		var next = null;
		function drop( k ) {
			if ( Object.prototype.hasOwnProperty.call( next || state, k ) ) {
				next = next || Object.assign( {}, state );
				delete next[ k ];
			}
		}
		Object.keys( r.implied ).forEach( function ( token ) {
			if ( r.implied[ token ] !== use ) {
				drop( token );
			}
		} );
		var without = Object.assign( {}, next || state );
		delete without.use;
		if ( effective( tag, without ) === use ) {
			drop( 'use' );
		}
		return next || state;
	}

	/**
	 * The select's value: the effective mode, or '' where that mode IS the stripped
	 * default — registration blanked that row's value, so '' is how the select names it.
	 */
	function displayValue( tag, state ) {
		var mode = effective( tag, state );
		return mode === str( rules().defaults[ tag ] ) ? '' : mode;
	}

	/** The next state for a mode picked in the select ('' = the stripped-default row). */
	function pick( tag, state, value ) {
		var mode = '' === str( value ) ? str( rules().defaults[ tag ] ) : str( value );
		return normalize( tag, Object.assign( {}, state, { use: mode } ) );
	}

	/** The implied field tokens' values, as one comparable string. */
	function tokenSig( state ) {
		return Object.keys( rules().implied ).map( function ( t ) {
			return str( state[ t ] );
		} ).join( '\u0000' );
	}

	function UseReadControl( props ) {
		var tag      = props.tag;
		var state    = props.context.state || {};
		var setState = props.context.setState;

		// On MOUNT, stored redundant wire normalizes; after that, a field token written by
		// ANOTHER control (the field picker) can make `use` redundant. Keyed on the tokens,
		// not on every state change, so the order normalizer's rewrite is not a trigger.
		useEffect( function () {
			setState( function ( prev ) {
				return normalize( tag, prev );
			} );
		}, [ tokenSig( state ) ] );

		return wp.element.cloneElement( props.element, {
			value:    displayValue( tag, state ),
			onChange: function ( v ) {
				setState( function ( prev ) {
					return pick( tag, prev, v );
				} );
			},
		} );
	}

	function useReadFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context || 'use' !== element.key ) {
			return element;
		}
		var cfg = allOptions.use;
		if ( ! cfg || ! cfg.readTag || '' === str( rules().defaults[ cfg.readTag ] ) ) {
			return element;
		}
		return el( UseReadControl, { key: element.key, element: element, tag: cfg.readTag, context: context } );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/use-read-control',
		useReadFilter
	);

	window.bwsUseRead = {
		effective:    effective,
		normalize:    normalize,
		displayValue: displayValue,
		pick:         pick,
	};
} )();
