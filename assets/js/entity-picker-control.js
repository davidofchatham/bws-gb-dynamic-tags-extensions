/**
 * Entity picker control (`bws-entity-picker`) for a PINNED chain root (FW-39).
 *
 * NOT registered via `generateblocks.editor.tagSpecificControls` — a root's argument is
 * not an option key with a `type`, it is a value carried INSIDE the chain wire's position
 * 0 (`bws_fold_chain_root_arg()`). The component is exposed for COMPOSITION instead
 * (`window.bwsEntityPickerControl`), the same pattern `field-combo-control.js` uses for
 * the same reason, and `slot-fold-control.js`'s `chainSteps()` mounts it at position 0
 * when the selected root's row declares `arg.control === 'bws-entity-picker'` — the ONE
 * place a chain's root and its steps are both rendered, for base tags, `{{join}}` fields
 * and `try_` attempts alike (D11, D15, D18's acceptance criterion: "the same picker").
 *
 * DELIBERATELY NOT the field-discovery envelope shape (D14): a field vocabulary is
 * bounded and ships up front; an entity vocabulary is not, so this control queries the
 * entity-lookup REST route (`includes/rest/entity-lookup.php`) on demand rather than
 * reading an inlined global.
 *
 * NO MINIMUM-CHARACTER GATE (D17): the browse fetch runs with an EMPTY search on mount,
 * so the list opens populated rather than waiting for the first keystroke.
 *
 * THE TAXONOMY FILTER IS CLIENT-SIDE AND NEVER SENT ON THE WIRE (D16). The browse fetch
 * asks the route for every readable entity matching the typed search; narrowing to one
 * taxonomy is applied to that same response, so re-selecting the filter needs no second
 * round trip and — the point of D16 — there is no server-side parameter for a stored value
 * to leak into.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.20.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.element || ! wp.components || ! wp.apiFetch ) {
		return;
	}

	var el         = wp.element.createElement;
	var Fragment   = wp.element.Fragment;
	var useState   = wp.element.useState;
	var useEffect  = wp.element.useEffect;
	var useMemo    = wp.element.useMemo;
	var ComboboxControl = wp.components.ComboboxControl;
	var SelectControl   = wp.components.SelectControl;
	var __ = ( wp.i18n && wp.i18n.__ ) ? wp.i18n.__ : function ( s ) { return s; };

	/**
	 * The route path for one request — browse/search or resolve-by-id (D14's two modes).
	 *
	 * @param {Object} params { kind, mode, q, id }.
	 * @return {string}
	 */
	function lookupPath( params ) {
		var qs = Object.keys( params )
			.filter( function ( k ) { return '' !== params[ k ] && undefined !== params[ k ] && null !== params[ k ]; } )
			.map( function ( k ) { return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] ); } )
			.join( '&' );
		return '/bws-dynamic-tags/v1/entities' + ( qs ? '?' + qs : '' );
	}

	/**
	 * The distinct GROUP labels present in a row list, alphabetical — the taxonomy
	 * filter's own option set, derived from what actually came back rather than a
	 * second fetch of "every taxonomy" (D16: this filter is UI state, and deriving it
	 * from the browse response is the cheapest way to keep it in step with what the
	 * user is actually allowed to see, per-taxonomy, D19).
	 *
	 * @param {Array} rows
	 * @return {Array}
	 */
	function groupsOf( rows ) {
		var seen = {};
		( rows || [] ).forEach( function ( r ) { seen[ r.group ] = true; } );
		return Object.keys( seen ).sort();
	}

	/**
	 * EntityPickerControl — one ROOT ARGUMENT, one kind.
	 *
	 * @param {Object}   props
	 * @param {string}   props.kind     Resolved-source kind ('term').
	 * @param {string}   props.value    The root's argument as stored, verbatim ('' = none).
	 * @param {Function} props.onChange fn( nextArg: string ).
	 * @param {string}   props.label    What the argument means ("Term").
	 * @param {string}   [props.help]
	 */
	function EntityPickerControl( props ) {
		var kind     = props.kind || '';
		var value    = props.value || '';
		var onChange = props.onChange;

		var stateRows   = useState( [] );
		var rows        = stateRows[ 0 ];
		var setRows     = stateRows[ 1 ];
		var stateFilter = useState( '' );
		var taxFilter   = stateFilter[ 0 ];
		var setTaxFilter = stateFilter[ 1 ];
		var stateQuery  = useState( '' );
		var query       = stateQuery[ 0 ];
		var setQuery    = stateQuery[ 1 ];
		var stateLabel  = useState( '' );
		var resolvedLabel = stateLabel[ 0 ];
		var setResolvedLabel = stateLabel[ 1 ];

		// BROWSE/SEARCH (D17): re-fetched whenever the typed query changes, including
		// the initial empty one — that empty fetch IS what "opens browsable" means.
		useEffect( function () {
			var cancelled = false;
			wp.apiFetch( { path: lookupPath( { kind: kind, mode: 'browse', q: query } ) } )
				.then( function ( res ) {
					if ( ! cancelled ) {
						setRows( ( res && res.rows ) || [] );
					}
				} )
				.catch( function () {
					if ( ! cancelled ) {
						setRows( [] );
					}
				} );
			return function () { cancelled = true; };
		}, [ kind, query ] );

		// RESOLVE (the reopen case, D14/D20): the stored value's label, independent of
		// whatever the browse list currently holds — a pin whose entity would not
		// currently match the typed search must still show its own name.
		useEffect( function () {
			if ( '' === value ) {
				setResolvedLabel( '' );
				return;
			}
			var cancelled = false;
			wp.apiFetch( { path: lookupPath( { kind: kind, mode: 'resolve', id: value } ) } )
				.then( function ( res ) {
					if ( ! cancelled ) {
						setResolvedLabel( res && res.row ? res.row.label : '' );
					}
				} )
				.catch( function () {
					if ( ! cancelled ) {
						setResolvedLabel( '' );
					}
				} );
			return function () { cancelled = true; };
		}, [ kind, value ] );

		var groups = useMemo( function () { return groupsOf( rows ); }, [ rows ] );

		var filteredRows = useMemo( function () {
			if ( ! taxFilter ) {
				return rows;
			}
			return ( rows || [] ).filter( function ( r ) { return r.group === taxFilter; } );
		}, [ rows, taxFilter ] );

		var options = useMemo( function () {
			return ( filteredRows || [] ).map( function ( r ) {
				return { value: String( r.id ), label: r.label };
			} );
		}, [ filteredRows ] );

		var valueInOptions = useMemo( function () {
			return ( options || [] ).some( function ( o ) { return o.value === value; } );
		}, [ options, value ] );

		return el( Fragment, null, [
			groups.length > 1
				? el( SelectControl, {
					key:    'taxfilter',
					label:  __( 'Filter', 'generateblocks' ),
					value:  taxFilter,
					options: [ { value: '', label: __( 'All', 'generateblocks' ) } ].concat(
						groups.map( function ( g ) { return { value: g, label: g }; } )
					),
					onChange: setTaxFilter,
					__nextHasNoMarginBottom: true,
				} )
				: null,
			el( ComboboxControl, {
				key:         'combo',
				label:       props.label,
				help:        props.help,
				// D21: an empty picker is an incomplete root, and the placeholder says
				// what to do — never "ambient".
				placeholder: __( 'Search…', 'generateblocks' ),
				value:       value,
				options:     options,
				onFilterValueChange: setQuery,
				onChange: function ( v ) {
					onChange( v || '' );
				},
				allowReset: true,
				__nextHasNoMarginBottom: true,
			} ),
			// The RESOLVED label as a quiet caption, ONLY when the combo itself cannot
			// show it: a pin outside the current browse/search list (a different
			// taxonomy selected, or the list simply has not loaded it yet) has no
			// matching option, so ComboboxControl renders an empty box. When the
			// option IS present the combo already reads as the entity's name and the
			// caption would only repeat it.
			( value && resolvedLabel && ! valueInOptions )
				? el( 'p', {
					key: 'resolved',
					style: { fontSize: '11px', opacity: 0.75, margin: '4px 0 0' },
				}, resolvedLabel )
				: null,
		] );
	}

	// NO NEW EXPORTS for the pure helpers above (#94) — entity-picker-control-test.js
	// reaches them the way field-combo-control-test.js reaches its own private
	// functions: by rendering this component against stubbed `wp.element` hooks and a
	// stubbed `wp.apiFetch`, and reading the tree and the request paths it produces.
	window.bwsEntityPickerControl = EntityPickerControl;

	// REGISTERED under the CONTROL NAME the root row declares (`arg.control`,
	// SourceInterface::get_root_argument()) — `slot-fold-control.js` looks a root's
	// argument control up by this name, not by testing whether this particular script
	// happens to be loaded. A future control (an integrator's own picker, a later
	// `bws-post-picker`) registers under its own key here without this file knowing it
	// exists, and a root naming a control nothing registered falls back to plain text
	// rather than silently mounting whichever picker happened to load.
	window.bwsRootArgControls = window.bwsRootArgControls || {};
	window.bwsRootArgControls[ 'bws-entity-picker' ] = EntityPickerControl;
} )();
