/**
 * Smart field selector control (`bws-field-combo`) for BWS dynamic tags.
 *
 * Replaces the plain `key`/`ref`/datetime-key text inputs with a discovery-backed
 * searchable combobox. Fields come from the REST route
 * `bws-dynamic-tags/v1/fields` (see includes/rest/field-discovery.php), which
 * lists registered field DEFINITIONS in ANY editor context — including WP
 * Patterns / GP Elements / templates, where the GB-native selector shows nothing
 * because it reads the container post's meta.
 *
 * Behaviour (SPEC.md §V1/§V2/§V4/§V9/§V11):
 * - Pure render swap: writes the SAME plain-string key the text input did
 *   (whole-object setState; `delete` on empty, never '' — GB serializes bare key:).
 * - Free-text commit via synthetic option: ComboboxControl does NOT commit
 *   off-list text, so when the filter string matches no option we inject a
 *   `Use custom key: "X"` option that, when chosen, commits the BARE typed key.
 * - Clear via built-in `allowReset` (default) -> onChange(null) -> delete key.
 * - Scope = resolved-source KIND (post/term/site), inferred from sibling
 *   `src`/`srcTermIn` tokens, always overridable by a kind selector. Filters the
 *   cached field list client-side (no re-fetch on kind switch).
 * - Label tracks kind via the meta/option storage-backend subtype pair.
 * - Composes with existing tagSpecificControls filters: `if (!element) return
 *   element` so conditional-options hiding (show_if -> null) still wins.
 *
 * Registered via `generateblocks.editor.tagSpecificControls`; activates for any
 * option whose PHP `type` is `bws-field-combo`.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.13.0
 */
( function () {
	'use strict';

	if ( ! window.wp || ! wp.hooks || ! wp.element || ! wp.components || ! wp.apiFetch ) {
		return;
	}

	var el              = wp.element.createElement;
	var Fragment        = wp.element.Fragment;
	var useState        = wp.element.useState;
	var useEffect       = wp.element.useEffect;
	var ComboboxControl = wp.components.ComboboxControl;
	var SelectControl   = wp.components.SelectControl;
	var apiFetch        = wp.apiFetch;
	var __              = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };

	var KINDS = [ 'post', 'term', 'site' ];

	// Module-level cache: one fetch of the field envelope, reused for the life of
	// the page (all modals share it). Per-modal freshness is a non-goal; the point
	// is the searchable list, not staleness (field-selector plan Plan-purpose).
	var envelopePromise = null;

	function fetchEnvelope() {
		if ( ! envelopePromise ) {
			envelopePromise = apiFetch( { path: 'bws-dynamic-tags/v1/fields' } ).catch( function () {
				// On failure, degrade to free-text only (empty envelope). Reset the
				// promise so a later modal can retry.
				envelopePromise = null;
				return { post: [], term: [], site: [] };
			} );
		}
		return envelopePromise;
	}

	/**
	 * Derive the resolved-source KIND from sibling source tokens (V2).
	 *
	 * Static token -> kind map, NO runtime L1 call:
	 *   srcTermIn set        -> term
	 *   src === 'site'       -> site
	 *   (else)               -> post
	 *
	 * @param {Object} state extraTagParams.
	 * @return {string} 'post' | 'term' | 'site'.
	 */
	function inferKind( state ) {
		if ( ! state ) { return 'post'; }
		if ( state.srcTermIn ) { return 'term'; }
		if ( 'site' === state.src ) { return 'site'; }
		return 'post';
	}

	/**
	 * Label for a kind, using the meta/option storage-backend subtype pair (V4).
	 * A purpose prefix (e.g. 'URL ') is prepended when supplied by the option cfg.
	 */
	function kindLabel( kind, prefix ) {
		var base;
		if ( 'post' === kind ) { base = __( 'Post Meta Field', 'generateblocks' ); }
		else if ( 'term' === kind ) { base = __( 'Term Meta Field', 'generateblocks' ); }
		else if ( 'site' === kind ) { base = __( 'Site Option Field', 'generateblocks' ); }
		else { base = __( 'Meta/Option Field', 'generateblocks' ); }
		return prefix ? prefix + ' ' + base : base;
	}

	/**
	 * Flatten one kind bucket of the envelope into ComboboxControl options.
	 *
	 * Each field becomes `{ value: <resolution key>, label: '<Label> (<key>) [group]' }`.
	 * Both label and key go in the display label so typing either matches (WP#64056:
	 * Combobox filters on label, not value). Row-context children get a marker.
	 *
	 * @param {Array} groups Envelope groups for one kind.
	 * @return {Array} Combobox options.
	 */
	function groupsToOptions( groups ) {
		var options = [];
		( groups || [] ).forEach( function ( group ) {
			( group.fields || [] ).forEach( function ( field ) {
				var key   = field.name;
				var lbl   = field.label && field.label !== key ? field.label + ' (' + key + ')' : key;
				var extra = [];
				if ( group.group_title ) { extra.push( group.group_title ); }
				if ( 'row' === field.context_hint ) { extra.push( __( 'row context', 'generateblocks' ) ); }
				if ( extra.length ) { lbl += ' — ' + extra.join( ', ' ); }
				options.push( { value: key, label: lbl } );
			} );
		} );
		return options;
	}

	function FieldComboControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;
		var value    = state[ key ] || '';

		// Field envelope, fetched once.
		var envState    = useState( null );
		var envelope    = envState[ 0 ];
		var setEnvelope = envState[ 1 ];

		// Filter string driving the synthetic free-text option (V11).
		var filterState  = useState( '' );
		var filterText   = filterState[ 0 ];
		var setFilterText = filterState[ 1 ];

		// Kind: inferred from siblings, but overridable by the selector below.
		// null override = "follow inference"; a string = explicit author choice.
		var overrideState = useState( null );
		var kindOverride  = overrideState[ 0 ];
		var setKindOverride = overrideState[ 1 ];

		// Under src:ref the hopped-to PT is unknown (ref-hop parity unbuilt) -> the
		// `key` combobox is UNSCOPED in v1 (V3). cfg.unscoped marks that option.
		var unscoped   = !! props.unscoped;
		var inferred   = inferKind( state );
		var kind       = unscoped ? null : ( kindOverride || inferred );

		useEffect( function () {
			var live = true;
			fetchEnvelope().then( function ( env ) {
				if ( live ) { setEnvelope( env ); }
			} );
			return function () { live = false; };
		}, [] );

		// Build options for the active kind (or all kinds when unscoped/unset).
		var options = [];
		if ( envelope ) {
			if ( kind && envelope[ kind ] ) {
				options = groupsToOptions( envelope[ kind ] );
			} else {
				KINDS.forEach( function ( k ) {
					options = options.concat( groupsToOptions( envelope[ k ] ) );
				} );
			}
		}

		// Synthetic free-text option (V11): when the author typed something that
		// matches no existing option value/label, offer to commit the BARE key.
		var typed = ( filterText || '' ).trim();
		if ( typed ) {
			var matches = options.some( function ( o ) {
				return o.value === typed ||
					( o.label && o.label.toLowerCase().indexOf( typed.toLowerCase() ) !== -1 );
			} );
			if ( ! matches ) {
				options = [ {
					value: typed,
					// Display-only wording; committed value is the bare key (NOT "Create").
					label: __( 'Use custom key:', 'generateblocks' ) + ' "' + typed + '"',
				} ].concat( options );
			}
		}

		// If the persisted value is not among the fetched options (custom/unregistered
		// key), inject it so the control shows it as selected rather than blank.
		if ( value && ! options.some( function ( o ) { return o.value === value; } ) ) {
			options = [ { value: value, label: value } ].concat( options );
		}

		function onChange( next ) {
			var upd = Object.assign( {}, state );
			if ( next === null || next === undefined || next === '' ) {
				// allowReset clear, or empty -> omit the key entirely (never '').
				delete upd[ key ];
			} else {
				upd[ key ] = next;
			}
			setState( upd );
		}

		var label = props.dynamicLabel
			? kindLabel( unscoped ? null : ( kindOverride || inferred ), props.labelPrefix )
			: props.label;

		var children = [
			el( ComboboxControl, {
				key:                 'combo',
				label:               label,
				help:                props.help,
				value:               value,
				options:             options,
				onChange:            onChange,
				onFilterValueChange: setFilterText,
				allowReset:          true,
				__nextHasNoMarginBottom: true,
			} ),
		];

		// Scope (kind) selector — always shown (the override GB structurally lacks),
		// except when the option is unscoped by design (key-under-src:ref, V3).
		if ( ! unscoped ) {
			children.push( el( SelectControl, {
				key:      'scope',
				label:    __( 'Field source', 'generateblocks' ),
				help:     __( 'Which source the field belongs to. Auto-set from the tag source; override to list fields from a different source.', 'generateblocks' ),
				value:    kindOverride || inferred,
				options:  [
					{ value: 'post', label: __( 'Post', 'generateblocks' ) },
					{ value: 'term', label: __( 'Term', 'generateblocks' ) },
					{ value: 'site', label: __( 'Site', 'generateblocks' ) },
				],
				onChange: function ( k ) { setKindOverride( k ); },
				__nextHasNoMarginBottom: true,
			} ) );
		}

		return el( Fragment, null, children );
	}

	function fieldComboFilter( element, allOptions, context ) {
		// Compose: if a prior filter (conditional-options) hid this control, keep it
		// hidden regardless of order (V9).
		if ( ! element ) { return element; }
		if ( ! allOptions || ! context ) { return element; }

		var cfg = allOptions[ element.key ];
		if ( ! cfg || 'bws-field-combo' !== cfg.type ) { return element; }

		return el( FieldComboControl, {
			key:          element.key,
			optionKey:    element.key,
			label:        cfg.label,
			help:         cfg.help,
			unscoped:     cfg.unscoped,
			dynamicLabel: cfg.dynamicLabel,
			labelPrefix:  cfg.labelPrefix,
			context:      context,
		} );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/field-combo-control',
		fieldComboFilter
	);

} )();
