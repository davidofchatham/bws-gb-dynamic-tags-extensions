/**
 * A SelectControl's children for rows that carry a `group`, shared by every control that
 * offers a grouped select (the source picker in slot-fold-control.js, the field picker's Type
 * filter in field-combo-control.js).
 *
 * Each run of one group becomes a native <optgroup>, whose heading is not selectable and
 * which a screen reader announces as a group; the rest are plain options. A group that must
 * itself be pickable is built by its caller as the group's FIRST row, since the heading
 * cannot be.
 *
 * @package BWS_Dynamic_Tags
 * @since   Unreleased
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;

	/**
	 * @param {Array} rows `{ value, label, group? }` rows.
	 * @return {Array} Elements.
	 */
	window.bwsOptionNodes = function ( rows ) {
		var out = [];
		var run = null;
		rows.forEach( function ( r ) {
			var opt = el( 'option', { key: r.value, value: r.value }, r.label );
			if ( ! r.group ) {
				run = null;
				out.push( opt );
				return;
			}
			if ( ! run || run.label !== r.group ) {
				run = { label: r.group, kids: [] };
				out.push( run );
			}
			run.kids.push( opt );
		} );
		return out.map( function ( n ) {
			return n.kids ? el( 'optgroup', { key: 'g:' + n.label, label: n.label }, n.kids ) : n;
		} );
	};
}() );
