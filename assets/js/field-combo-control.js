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
 * UI (field-selector plan §List schema + §Filter schema, LOCKED 2026-07-03):
 * - FLAT alphabetized field list, NOT grouped by ACF field group. One row per
 *   resolution key (merged — same key across ACF groups collapses; bare value is
 *   unique so it round-trips cleanly on reopen). A parent group/repeater FIELD
 *   owns its children (children sort directly under their parent, not scattered).
 * - Label: `Venue › City ('venue_city')` — breadcrumb (parent group/repeater,
 *   display-only) + field label + resolution key in single quotes. `loop-only`
 *   suffix when the key resolves ONLY in a repeater/flex row context.
 * - TWO filter selectors ABOVE the field combobox, AND-composed:
 *     Filter 1 Location — searchable combobox, flat path-strings
 *       (All detected fields / Post fields / Post fields › Group A / …),
 *       prefix-match. Preset from SAFE source tokens only (srcTermIn→Term,
 *       src:site→Site, src:ref→Post) else "All detected fields" — NEVER assume
 *       the editor's current context is a post (that is the GB bug we escape).
 *     Filter 2 Field type — plain select
 *       (All field types / Loop fields / <ACF types>).
 * - Free-text commit via synthetic option (ComboboxControl does NOT commit
 *   off-list text): typing an unmatched key injects a "Use custom key" option that
 *   commits the BARE typed key. Clear via built-in allowReset -> onChange(null).
 * - Pure render swap: writes the SAME plain-string key the text input did
 *   (whole-object setState; `delete` on empty, never '' — GB serializes bare key:).
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
	var useMemo         = wp.element.useMemo;
	var ComboboxControl = wp.components.ComboboxControl;
	var SelectControl   = wp.components.SelectControl;
	var Flex            = wp.components.Flex;
	var FlexItem        = wp.components.FlexItem;
	var apiFetch        = wp.apiFetch;
	var __              = wp.i18n ? wp.i18n.__ : function ( s ) { return s; };

	var KINDS   = [ 'post', 'term', 'site' ];
	var BREAD   = ' › ';          // ' › ' breadcrumb separator
	var ALL_LOC = '__all_locations';
	var ALL_TYPE = '__all_types';
	var LOOP_TYPE = '__loop';

	// Envelope source. The server inlines the field envelope directly into the editor
	// page as `window.bwsFieldEnvelope` (via wp_add_inline_script), so the control
	// reads it synchronously with NO runtime REST request — it never queues behind
	// GB's dynamic-tag-replacement swarm (the 30-40s head-of-line block). If the
	// global is absent (unexpected), fall back to a real /fields request.
	var envelopePromise = null;

	function fetchEnvelope() {
		if ( ! envelopePromise ) {
			if ( window.bwsFieldEnvelope && typeof window.bwsFieldEnvelope === 'object' ) {
				envelopePromise = Promise.resolve( window.bwsFieldEnvelope );
			} else {
				envelopePromise = apiFetch( { path: 'bws-dynamic-tags/v1/fields' } )
					.catch( function () {
						envelopePromise = null;
						return { post: [], term: [], site: [] };
					} );
			}
		}
		return envelopePromise;
	}

	/**
	 * Human "<Kind> fields" root label for the Location filter path.
	 */
	function kindRootLabel( kind ) {
		if ( 'post' === kind ) { return __( 'Post fields', 'generateblocks' ); }
		if ( 'term' === kind ) { return __( 'Term fields', 'generateblocks' ); }
		if ( 'site' === kind ) { return __( 'Site fields', 'generateblocks' ); }
		return __( 'Fields', 'generateblocks' );
	}

	/**
	 * Safe source-token -> kind preset for the Location filter (NEVER assume post
	 * from the editor context — only when the src TOKEN proves the kind).
	 *
	 * @param {Object} state extraTagParams.
	 * @return {string|null} 'post' | 'term' | 'site' | null (=> All detected).
	 */
	function presetKind( state ) {
		if ( ! state ) { return null; }
		if ( state.srcTermIn ) { return 'term'; }
		if ( 'site' === state.src ) { return 'site'; }
		if ( 'ref' === state.src ) { return 'post'; }
		return null;
	}

	/**
	 * Dynamic control label — meta/option storage-backend subtype pair (V4).
	 * Uses the preset kind (safe-token) when known, else the source-agnostic fallback.
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
	 * Friendly label for an ACF field type string.
	 */
	function typeLabel( type ) {
		var map = {
			text: __( 'Text', 'generateblocks' ),
			textarea: __( 'Text Area', 'generateblocks' ),
			wysiwyg: __( 'WYSIWYG', 'generateblocks' ),
			email: __( 'Email', 'generateblocks' ),
			url: __( 'URL', 'generateblocks' ),
			number: __( 'Number', 'generateblocks' ),
			date_picker: __( 'Date', 'generateblocks' ),
			date_time_picker: __( 'Date & Time', 'generateblocks' ),
			time_picker: __( 'Time', 'generateblocks' ),
			relationship: __( 'Relationship', 'generateblocks' ),
			post_object: __( 'Post Object', 'generateblocks' ),
			image: __( 'Image', 'generateblocks' ),
			taxonomy: __( 'Taxonomy', 'generateblocks' ),
			'true_false': __( 'True / False', 'generateblocks' ),
			group: __( 'Group', 'generateblocks' ),
			repeater: __( 'Repeater', 'generateblocks' ),
			flexible_content: __( 'Flexible Content', 'generateblocks' ),
		};
		return map[ type ] || type;
	}

	/**
	 * Flatten the whole envelope into flat field RECORDS, merged by resolution key.
	 *
	 * One record per unique (key) — same key across ACF groups collapses; the record
	 * accumulates every location path the key appears under (for the Location filter)
	 * and ORs the row flag conservatively (see `rowOnly` below). Bare value is unique
	 * so it round-trips on reopen.
	 *
	 * Each record:
	 *   value        resolution key (what commits + serializes)
	 *   label        field label (or key)
	 *   key          bare/composite key (for the ('key') display)
	 *   type         ACF type string ('' if none)
	 *   bread        breadcrumb (parent group/repeater path), '' at top level
	 *   sortKey      lower-cased [bread + label] so children sort under their parent
	 *   paths        array of full location path strings (kind root › group › parent…)
	 *   rowSeen      true if ANY instance is a repeater/flex child
	 *   nonRowSeen   true if ANY instance is NOT a row child
	 *   (loopOnly    = rowSeen && !nonRowSeen, computed at label time)
	 *
	 * @param {Object} envelope { post:[groups], term:[groups], site:[groups] }.
	 * @return {Array} Flat merged field records.
	 */
	function envelopeToRecords( envelope ) {
		var index = Object.create( null );
		var order = [];

		KINDS.forEach( function ( kind ) {
			var groups = ( envelope && envelope[ kind ] ) || [];
			var root   = kindRootLabel( kind );
			groups.forEach( function ( group ) {
				var groupTitle = group.group_title || '';
				( group.fields || [] ).forEach( function ( field ) {
					var key   = field.name;
					if ( ! key ) { return; }
					var bread = field.parent_path || '';
					var isRow = 'row' === field.context_hint;

					// Full location path for the Location filter: kind root › group › parent…
					var pathParts = [ root ];
					if ( groupTitle ) { pathParts.push( groupTitle ); }
					if ( bread ) { pathParts.push( bread ); }
					var path = pathParts.join( BREAD );

					var v = String( key );
					if ( ! index[ v ] ) {
						index[ v ] = {
							value:      key,
							label:      field.label && field.label !== key ? field.label : key,
							key:        key,
							type:       field.type || '',
							bread:      bread,
							paths:      [],
							rowSeen:    false,
							nonRowSeen: false,
						};
						order.push( v );
					}
					var rec = index[ v ];
					// First non-empty label/type/breadcrumb wins (server dedupe order).
					if ( ( ! rec.label || rec.label === rec.key ) && field.label && field.label !== key ) {
						rec.label = field.label;
					}
					if ( ! rec.type && field.type ) { rec.type = field.type; }
					if ( ! rec.bread && bread ) { rec.bread = bread; }
					if ( rec.paths.indexOf( path ) === -1 ) { rec.paths.push( path ); }
					rec.rowSeen    = rec.rowSeen || isRow;
					rec.nonRowSeen = rec.nonRowSeen || ! isRow;
				} );
			} );
		} );

		var records = order.map( function ( v ) { return index[ v ]; } );

		// Sort: breadcrumb + label, case-insensitive, so a parent group/repeater
		// field and its children cluster (child bread = parent's label, sorts right
		// after the bare parent).
		records.forEach( function ( r ) {
			r.sortKey = ( ( r.bread ? r.bread + BREAD : '' ) + r.label ).toLowerCase();
		} );
		records.sort( function ( a, b ) {
			return a.sortKey < b.sortKey ? -1 : ( a.sortKey > b.sortKey ? 1 : 0 );
		} );

		return records;
	}

	/**
	 * Compose a record's ComboboxControl option { value, label }.
	 *
	 * Label = "<breadcrumb ›> <label> ('<key>')" + " — loop-only" when the key is
	 * found ONLY in a repeater/flex row context (AND-fold: a key also present
	 * top-level resolves normally, no marker).
	 */
	function recordToOption( rec ) {
		var label = '';
		if ( rec.bread ) { label += rec.bread + BREAD; }
		label += rec.label + " ('" + rec.key + "')";
		if ( rec.rowSeen && ! rec.nonRowSeen ) {
			label += ' — ' + __( 'loop-only', 'generateblocks' );
		}
		return { value: rec.value, label: label };
	}

	/**
	 * Build the Location filter option list (flat path-strings, prefix set).
	 *
	 * Distinct set of every path PREFIX present across records: the kind roots, then
	 * each "root › group", then each "root › group › parent…". Prefixed with
	 * "All detected fields". Alpha within, roots first.
	 */
	function buildLocationOptions( records ) {
		var seen = Object.create( null );
		var paths = [];
		records.forEach( function ( rec ) {
			rec.paths.forEach( function ( full ) {
				var parts = full.split( BREAD );
				var acc = '';
				for ( var i = 0; i < parts.length; i++ ) {
					acc = i === 0 ? parts[ 0 ] : acc + BREAD + parts[ i ];
					if ( ! seen[ acc ] ) { seen[ acc ] = true; paths.push( acc ); }
				}
			} );
		} );
		paths.sort( function ( a, b ) { return a < b ? -1 : ( a > b ? 1 : 0 ); } );

		var options = [ { value: ALL_LOC, label: __( 'All detected fields', 'generateblocks' ) } ];
		paths.forEach( function ( p ) { options.push( { value: p, label: p } ); } );
		return options;
	}

	/**
	 * Build the Field-type filter option list: All / Loop fields / <ACF types>.
	 */
	function buildTypeOptions( records ) {
		var seen = Object.create( null );
		var types = [];
		records.forEach( function ( rec ) {
			if ( rec.type && ! seen[ rec.type ] ) { seen[ rec.type ] = true; types.push( rec.type ); }
		} );
		types.sort( function ( a, b ) {
			var la = typeLabel( a ), lb = typeLabel( b );
			return la < lb ? -1 : ( la > lb ? 1 : 0 );
		} );

		var options = [
			{ value: ALL_TYPE, label: __( 'All field types', 'generateblocks' ) },
			{ value: LOOP_TYPE, label: __( 'Loop fields', 'generateblocks' ) },
		];
		types.forEach( function ( t ) { options.push( { value: t, label: typeLabel( t ) } ); } );
		return options;
	}

	/**
	 * Filter records by the active Location (prefix-match) + Type (exact / loop) filters.
	 */
	function applyFilters( records, loc, type ) {
		return records.filter( function ( rec ) {
			if ( loc !== ALL_LOC ) {
				var hit = rec.paths.some( function ( p ) {
					return p === loc || p.indexOf( loc + BREAD ) === 0;
				} );
				if ( ! hit ) { return false; }
			}
			if ( type === LOOP_TYPE ) {
				if ( ! ( rec.rowSeen && ! rec.nonRowSeen ) ) { return false; }
			} else if ( type !== ALL_TYPE ) {
				if ( rec.type !== type ) { return false; }
			}
			return true;
		} );
	}

	function FieldComboControl( props ) {
		var ctx      = props.context;
		var state    = ctx.state || {};
		var setState = ctx.setState;
		var key      = props.optionKey;
		var value    = state[ key ] || '';

		var envState    = useState( null );
		var envelope    = envState[ 0 ];
		var setEnvelope = envState[ 1 ];

		var filterState   = useState( '' );
		var filterText    = filterState[ 0 ];
		var setFilterText = filterState[ 1 ];

		// Location filter: null => follow the safe-token preset; a string => explicit
		// author pick (lasts the modal session, not persisted — ephemeral view state).
		var locState    = useState( null );
		var locOverride = locState[ 0 ];
		var setLoc      = locState[ 1 ];

		// Type filter (ephemeral).
		var typeState = useState( ALL_TYPE );
		var typeVal   = typeState[ 0 ];
		var setType   = typeState[ 1 ];

		useEffect( function () {
			var live = true;
			fetchEnvelope().then( function ( env ) {
				if ( live ) { setEnvelope( env ); }
			} );
			return function () { live = false; };
		}, [] );

		var records = useMemo( function () {
			return envelope ? envelopeToRecords( envelope ) : [];
		}, [ envelope ] );

		var locationOptions = useMemo( function () {
			return buildLocationOptions( records );
		}, [ records ] );

		var typeOptions = useMemo( function () {
			return buildTypeOptions( records );
		}, [ records ] );

		// Effective location: explicit override, else safe-token preset path, else All.
		var preset       = presetKind( state );
		var presetPath   = preset ? kindRootLabel( preset ) : ALL_LOC;
		// Only use the preset path if it actually exists in the options (fields of
		// that kind were discovered); otherwise fall back to All.
		var presetExists = locationOptions.some( function ( o ) { return o.value === presetPath; } );
		var activeLoc    = locOverride !== null ? locOverride : ( presetExists ? presetPath : ALL_LOC );

		var filtered = applyFilters( records, activeLoc, typeVal );
		var options  = filtered.map( recordToOption );

		// Synthetic free-text option: typing an unmatched key offers to commit it bare.
		var typed = ( filterText || '' ).trim();
		if ( typed ) {
			var matches = options.some( function ( o ) {
				return o.value === typed ||
					( o.label && o.label.toLowerCase().indexOf( typed.toLowerCase() ) !== -1 );
			} );
			if ( ! matches ) {
				options = [ {
					value: typed,
					label: __( 'Use custom key:', 'generateblocks' ) + ' "' + typed + '"',
				} ].concat( options );
			}
		}

		// Persisted value not in the (filtered) option set — inject it so the control
		// shows it selected rather than blank (custom key, or filtered-out own value).
		if ( value && ! options.some( function ( o ) { return o.value === value; } ) ) {
			options = [ { value: value, label: value } ].concat( options );
		}

		function onChange( next ) {
			var upd = Object.assign( {}, state );
			if ( next === null || next === undefined || next === '' ) {
				delete upd[ key ];
			} else {
				upd[ key ] = next;
			}
			setState( upd );
		}

		var label = props.dynamicLabel
			? kindLabel( preset, props.labelPrefix )
			: props.label;

		return el( Fragment, null, [
			// Two filters side-by-side above the field selector. align:flex-end keeps
			// the dropdowns aligned when labels wrap to different heights.
			el( Flex, { key: 'filters', gap: 2, align: 'flex-end', wrap: true }, [
				el( FlexItem, { key: 'loc', isBlock: true },
					el( SelectControl, {
						label:    __( 'Filter: location', 'generateblocks' ),
						value:    activeLoc,
						options:  locationOptions,
						onChange: function ( v ) { setLoc( v ); },
						__nextHasNoMarginBottom: true,
					} )
				),
				el( FlexItem, { key: 'type', isBlock: true },
					el( SelectControl, {
						label:    __( 'Filter: field type', 'generateblocks' ),
						value:    typeVal,
						options:  typeOptions,
						onChange: function ( v ) { setType( v ); },
						__nextHasNoMarginBottom: true,
					} )
				),
			] ),
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
		] );
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
