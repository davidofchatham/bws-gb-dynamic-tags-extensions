/**
 * Entity picker control (`bws-entity-picker`) for a chain root's ARGUMENT (FW-39).
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
 * THE GROUP FILTER IS REQUEST-TIME UI STATE AND NEVER PART OF THE STORED WIRE (D16). It
 * rides the browse request as the route's `group` param, because the server caps each
 * group's rows and a client-side narrowing of a capped list would hide entities. The
 * filter's option set is a bounded, stable list inlined into the page, independent of the
 * rows, so the selector renders on mount and does not collapse when the typed search or
 * a chosen group leaves one group's rows.
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
	// The placeholder option's value while a list loads — never passed to onChange.
	var LOADING = '__bws_loading';

	function lookupPath( params ) {
		var qs = Object.keys( params )
			.filter( function ( k ) { return '' !== params[ k ] && undefined !== params[ k ] && null !== params[ k ]; } )
			.map( function ( k ) { return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] ); } )
			.join( '&' );
		return '/bws-dynamic-tags/v1/entities' + ( qs ? '?' + qs : '' );
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
		// The filter's options are inlined (`window.bwsEntityGroups`, present-but-empty for a
		// user who may not browse) so the selector renders on mount. Only an ABSENT global
		// — the inline failed — falls back to the browse response's own `groups`.
		var inlined     = window.bwsEntityGroups ? ( window.bwsEntityGroups[ kind ] || [] ) : null;
		var stateGroups = useState( inlined || [] );
		var groups      = stateGroups[ 0 ];
		var setGroups   = stateGroups[ 1 ];
		var stateLoading = useState( true );
		var loading      = stateLoading[ 0 ];
		var setLoading   = stateLoading[ 1 ];
		var stateTrunc  = useState( false );
		var truncated   = stateTrunc[ 0 ];
		var setTruncated = stateTrunc[ 1 ];
		var stateFilter = useState( '' );
		var taxFilter   = stateFilter[ 0 ];
		var setTaxFilter = stateFilter[ 1 ];
		var stateQuery  = useState( '' );
		var query       = stateQuery[ 0 ];
		var setQuery    = stateQuery[ 1 ];
		var stateLabel  = useState( '' );
		var resolvedLabel = stateLabel[ 0 ];
		var setResolvedLabel = stateLabel[ 1 ];

		// BROWSE/SEARCH (D17): re-fetched whenever the typed query or the group filter
		// changes, including the initial empty one — that empty fetch IS what "opens
		// browsable" means. The server caps each group's rows; the filter and the search
		// are how an author reaches the rest.
		useEffect( function () {
			var cancelled = false;
			setLoading( true );
			wp.apiFetch( { path: lookupPath( { kind: kind, mode: 'browse', q: query, group: taxFilter } ) } )
				.then( function ( res ) {
					if ( ! cancelled ) {
						setRows( ( res && res.rows ) || [] );
						setTruncated( !! ( res && res.truncated ) );
						setLoading( false );
						if ( ! inlined ) {
							setGroups( ( res && res.groups ) || [] );
						}
					}
				} )
				.catch( function () {
					if ( ! cancelled ) {
						setRows( [] );
						setTruncated( false );
						setLoading( false );
					}
				} );
			return function () { cancelled = true; };
		}, [ kind, query, taxFilter ] );

		// RESOLVE (the reopen case, D14/D20): the stored value's label, independent of
		// whatever the browse list currently holds — a selected entity that would not
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

		// While the first rows for a list are in flight an empty option set would render
		// "No items found", which reads as a finding. A placeholder option says what is
		// happening instead; it is never a value (see onChange).
		var options = useMemo( function () {
			if ( loading && ! ( rows || [] ).length ) {
				return [ { value: LOADING, label: __( 'Loading…', 'generateblocks' ) } ];
			}
			return ( rows || [] ).map( function ( r ) {
				return { value: String( r.id ), label: r.label };
			} );
		}, [ rows, loading ] );

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
						groups.map( function ( g ) { return { value: g.scope, label: g.label }; } )
					),
					// The old group's rows would be the wrong list while the new one loads.
					onChange: function ( g ) { setRows( [] ); setTaxFilter( g ); },
					__nextHasNoMarginBottom: true,
				} )
				: null,
			// The server caps each group's rows; say so rather than let a cut-short list read
			// as the whole set. ABOVE the combobox, not below: the open list covers whatever
			// sits under the input.
			truncated
				? el( 'p', {
					key: 'truncated',
					style: { fontSize: '11px', opacity: 0.75, margin: '0 0 4px' },
				}, __( 'Some entries are not shown. Search or choose a filter to narrow the list.', 'generateblocks' ) )
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
					if ( LOADING !== v ) {
						onChange( v || '' );
					}
				},
				allowReset: true,
				__nextHasNoMarginBottom: true,
			} ),
			// The RESOLVED label as a quiet caption, ONLY when the combo itself cannot
			// show it: a selection outside the current browse/search list (a different
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
