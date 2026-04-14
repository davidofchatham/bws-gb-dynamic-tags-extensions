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
 *   ['a', 'b', ...]   — other option equals any value in the array (OR match)
 *   'value'           — other option equals 'value' exactly
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
	 * Evaluate a single condition against the current state.
	 *
	 * @param {string}          condKey   The option key whose value to test.
	 * @param {string|string[]} condValue The condition to apply.
	 * @param {Object}          state     The current extraTagParams (all option values).
	 * @return {boolean} true if condition passes.
	 */
	function evaluateCondition( condKey, condValue, state ) {
		var current = ( state && state[ condKey ] !== undefined ) ? state[ condKey ] : '';

		// Array value: match any entry (OR).
		if ( Array.isArray( condValue ) ) {
			return condValue.some( function ( v ) { return String( current ) === String( v ); } );
		}

		if ( condValue === 'not_empty' ) {
			return current !== '' && current !== false && current !== null && current !== undefined;
		}
		if ( condValue === 'empty' ) {
			return current === '' || current === false || current === null || current === undefined;
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
