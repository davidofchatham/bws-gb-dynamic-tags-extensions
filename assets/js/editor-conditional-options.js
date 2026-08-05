/**
 * Conditional option visibility for BWS dynamic tags.
 *
 * Hooks into GenerateBlocks' tagSpecificControls filter to show/hide
 * individual option controls based on the current values of other options.
 *
 * PHP option definitions can include show_if (AND) and/or show_if_any (OR):
 *
 *   'my_option' => [
 *       'type'        => 'text',
 *       'label'       => 'My Option',
 *       'show_if'     => [ 'other' => 'not_empty' ],      // ALL must pass
 *       'show_if_any' => [ 'src_1' => 'not_empty',        // ANY must pass
 *                          'key_1' => 'not_empty' ],
 *   ]
 *
 * Both properties may coexist; the element is shown only when both pass.
 *
 * Condition value syntax (used in both show_if and show_if_any):
 *   'not_empty'       — other option has any non-empty value
 *   'empty'           — other option is blank/unset
 *   'not:value'       — other option does NOT equal 'value'
 *   'in:v1,v2,...'    — other option equals any value in the comma-separated list
 *   'not_in:v1,v2,..' — other option equals none of the values in the list
 *   'chain_fans'      — other option holds a SOURCE CHAIN that hops (see below)
 *   ['a', 'b', ...]   — other option equals any value in the array (OR match)
 *   'value'           — other option equals 'value' exactly
 *
 * Every condition but `chain_fans` compares a value to a literal. That is why the
 * list-mode options (`limit`, `sep`) hid the moment a source was spelled as a
 * chain: their predicate named the two TOKENS that used to be the only fanning
 * sources (`srcTermIn` not empty, `src` equal to `ref`), and chain wire is neither.
 * `chain_fans` asks the question those tokens were standing in for.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.4.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.hooks ) {
		return;
	}

	/**
	 * Whether a `src` value states a chain that HOPS — the JS half of the render
	 * path's fan question (bws_fold_chain_resolution()'s `fans`).
	 *
	 * Fanning is CAPACITY read from the wire, never a claim about a given render,
	 * so this is decidable with no query and no resolve. It has to be: a control's
	 * reveal predicate runs while the author is still typing the source.
	 *
	 * Mirrors the PHP rule exactly, including the two edges that look like
	 * oversights and are not: a bare fanning slug (`src:refs`) IS a one-hop chain,
	 * and a step at position 0 counts only when its slug fans, because otherwise it
	 * is the chain's ROOT rather than a hop.
	 *
	 * @param {*} raw The `src` option value.
	 * @return {boolean}
	 */
	function srcChainFans( raw ) {
		var value = String( ( raw === null || raw === undefined ) ? '' : raw ).trim();
		if ( '' === value ) {
			return false;
		}

		var api = window.bwsSlotFold;
		if ( ! api || 'function' !== typeof api.parseChain ) {
			return false;
		}
		var fanning = ( api.grammar && api.grammar.fanningSlugs ) || [];

		if ( -1 !== fanning.indexOf( value ) ) {
			return true;   // `src:refs` — argless one-hop chain.
		}
		// Conservative, as the PHP twin is: every legacy `src` value is a single
		// bare token and cannot hold a chain separator or bracket, so a value
		// carrying one IS a chain and everything else stays a token.
		if ( ! /[;,[\]()]/.test( value ) ) {
			return false;
		}

		var chain = api.parseChain( value );
		if ( ! chain || chain.error || ! chain.length ) {
			return false;   // Malformed wire falls back to the legacy reading.
		}
		return chain.some( function ( link, i ) {
			return i > 0 || -1 !== fanning.indexOf( link.slug );
		} );
	}

	/**
	 * Evaluate a single condition against the current state.
	 *
	 * @param {string}          condKey   The option key whose value to test.
	 * @param {string|string[]} condValue The condition to apply.
	 * @param {Object}          state     The current extraTagParams (all option values).
	 * @return {boolean} true if condition passes.
	 */
	function evaluateCondition( condKey, condValue, state ) {
		var current = ( state && state[ condKey ] !== undefined ) ? state[ condKey ] : '';

		// Array value: ANY entry passes (OR). Entries are full conditions, not just
		// literals, so one key can carry a literal and a predicate together —
		// `'src' => [ 'ref', 'chain_fans' ]`. That is not cosmetic: show_if_any is
		// keyed BY OPTION, so two rules about `src` cannot be two entries.
		// Back-compatible: a plain literal recurses straight to the equality arm.
		if ( Array.isArray( condValue ) ) {
			return condValue.some( function ( v ) { return evaluateCondition( condKey, v, state ); } );
		}

		if ( condValue === 'not_empty' ) {
			return current !== '' && current !== false && current !== null && current !== undefined;
		}
		if ( condValue === 'empty' ) {
			return current === '' || current === false || current === null || current === undefined;
		}
		if ( condValue === 'chain_fans' ) {
			return srcChainFans( current );
		}
		if ( String( condValue ).indexOf( 'not:' ) === 0 ) {
			return String( current ) !== String( condValue.substring( 4 ) );
		}
		if ( String( condValue ).indexOf( 'in:' ) === 0 ) {
			var inValues = condValue.substring( 3 ).split( ',' );
			return inValues.some( function ( v ) { return String( current ) === v; } );
		}
		if ( String( condValue ).indexOf( 'not_in:' ) === 0 ) {
			var notInValues = condValue.substring( 7 ).split( ',' );
			return notInValues.every( function ( v ) { return String( current ) !== v; } );
		}
		return String( current ) === String( condValue );
	}

	/**
	 * Filter handler: hide or show a tag option control based on show_if / show_if_any rules.
	 *
	 * show_if     — object of conditions; ALL must pass (AND).
	 * show_if_any — object of conditions; AT LEAST ONE must pass (OR).
	 * Both may be present; both must pass for the element to be shown.
	 *
	 * @param {Object|null} element    The React element GB rendered for this control.
	 * @param {Object}      allOptions The full options object for the selected tag.
	 * @param {Object}      context    { state: extraTagParams, setState: fn }
	 * @return {Object|null} The element to render, or null to hide it.
	 */
	function conditionalOptionsFilter( element, allOptions, context ) {
		if ( ! element || ! allOptions || ! context ) {
			return element;
		}

		var optionKey = element.key;
		if ( ! optionKey ) {
			return element;
		}

		var optionConfig = allOptions[ optionKey ];
		if ( ! optionConfig ) {
			return element;
		}

		var state = context.state || {};

		// AND conditions — all must pass.
		if ( optionConfig.show_if ) {
			var showIf = optionConfig.show_if;
			var keys   = Object.keys( showIf );
			for ( var i = 0; i < keys.length; i++ ) {
				if ( ! evaluateCondition( keys[ i ], showIf[ keys[ i ] ], state ) ) {
					return null;
				}
			}
		}

		// OR conditions — at least one must pass.
		if ( optionConfig.show_if_any ) {
			var showIfAny = optionConfig.show_if_any;
			var anyKeys   = Object.keys( showIfAny );
			var anyPassed = false;
			for ( var j = 0; j < anyKeys.length; j++ ) {
				if ( evaluateCondition( anyKeys[ j ], showIfAny[ anyKeys[ j ] ], state ) ) {
					anyPassed = true;
					break;
				}
			}
			if ( ! anyPassed ) {
				return null;
			}
		}

		return element;
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/conditional-options',
		conditionalOptionsFilter
	);

} )();
