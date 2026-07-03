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
 * - Free-text entry via synthetic option (ComboboxControl does NOT accept off-list
 *   text): typing an unmatched key injects a "Use custom key" option that
 *   serializes the BARE typed key. Clear via built-in allowReset -> onChange(null).
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
	 * Kind implied by an active Location filter value, or null.
	 *
	 * The Location value is a path whose ROOT segment is the kind root
	 * ("Post fields" / "Term fields" / "Site fields"). "All detected fields" (or an
	 * unrecognized value) → null so the caller falls back to the sibling preset.
	 *
	 * @param {string} loc Active location filter value.
	 * @return {string|null} 'post' | 'term' | 'site' | null.
	 */
	function kindFromLocation( loc ) {
		if ( ! loc || loc === ALL_LOC ) { return null; }
		var root = String( loc ).split( BREAD )[ 0 ];
		if ( root === kindRootLabel( 'post' ) ) { return 'post'; }
		if ( root === kindRootLabel( 'term' ) ) { return 'term'; }
		if ( root === kindRootLabel( 'site' ) ) { return 'site'; }
		return null;
	}

	/**
	 * Deepest GROUP/container segment of an active Location filter value, or null.
	 *
	 * When the author has narrowed Location past the kind root (e.g.
	 * "Post fields › Client Details" or "… › Coverage Options (repeater)"), the
	 * control label can name that group instead of the generic kind. Returns the
	 * leaf segment with any "(repeater)"/"(group)" hint stripped. Root-only
	 * ("Post fields") or "All detected fields" → null (caller uses the kind label).
	 *
	 * @param {string} loc Active location filter value.
	 * @return {string|null} Group/container name, or null.
	 */
	function locationGroupLabel( loc ) {
		if ( ! loc || loc === ALL_LOC ) { return null; }
		var parts = String( loc ).split( BREAD );
		if ( parts.length < 2 ) { return null; } // kind root only → no group
		var leaf = parts[ parts.length - 1 ];
		return leaf.replace( /\s*\([^)]*\)\s*$/, '' ); // strip "(repeater)" etc.
	}

	/**
	 * Slot prefix of a try_ option key, or '' for a base (non-slotted) key.
	 *
	 * try_ tags serialize per-slot options with an "N-" prefix (`2-key`, `2-src`,
	 * `2-srcTermIn`), while slot 1 uses the bare names (`key`, `src`, `srcTermIn`).
	 * So a key control must read its OWN slot's sibling source tokens. Derive the
	 * prefix from the option key: `2-key` → `2-`, `key` → ``.
	 *
	 * @param {string} optionKey The option this control renders (e.g. '2-key').
	 * @return {string} The slot prefix ('' | 'N-').
	 */
	function slotPrefix( optionKey ) {
		var m = /^(\d+-)/.exec( String( optionKey || '' ) );
		return m ? m[ 1 ] : '';
	}

	/**
	 * Safe source-token -> kind preset for the Location filter (NEVER assume post
	 * from the editor context — only when the src TOKEN proves the kind). Reads the
	 * sibling tokens of the SAME slot (prefix-aware) so per-slot try_ keys track
	 * their own source.
	 *
	 * @param {Object} state     extraTagParams.
	 * @param {string} optionKey The key control's own option key (for slot prefix).
	 * @return {string|null} 'post' | 'term' | 'site' | null (=> All detected).
	 */
	function presetKind( state, optionKey ) {
		if ( ! state ) { return null; }
		var p = slotPrefix( optionKey );
		if ( state[ p + 'srcTermIn' ] ) { return 'term'; }
		if ( 'site' === state[ p + 'src' ] ) { return 'site'; }
		if ( 'ref' === state[ p + 'src' ] ) { return 'post'; }
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
	 *   value        unique merge key = React/option identity (NOT the serialized key)
	 *   key          bare field key = what gets serialized into the tag
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
					var lbl   = field.label && field.label !== key ? field.label : key;
					var type  = field.type || '';

					// Full location path for the Location filter: kind root › group › parent…
					var pathParts = [ root ];
					if ( groupTitle ) { pathParts.push( groupTitle ); }
					if ( bread ) { pathParts.push( bread ); }
					var path = pathParts.join( BREAD );

					// Merge identity = (kind, key, label). Same key + same label within a
					// kind = the SAME field surfaced in multiple homes → collapse to one
					// row that lists under every home (accumulate paths + types). Same key
					// + DIFFERENT label (e.g. `name` = "Name" vs "Feature Name") = distinct
					// fields → separate rows. A control char (U+001F) joins the parts so
					// ordinary field text can't forge a collision. `kind` is included
					// because a post `email` and a site `email` read via different paths.
					var mkey = kind + '' + key + '' + lbl;
					if ( ! index[ mkey ] ) {
						index[ mkey ] = {
							// React list key / ComboboxControl option value — unique per row.
							// The SERIALIZED value is the bare `key` (see onChange), not this.
							value:      mkey,
							key:        key,
							label:      lbl,
							kind:       kind,
							types:      [],
							paths:      [],
							rowSeen:    false,
							nonRowSeen: false,
						};
						order.push( mkey );
					}
					var rec = index[ mkey ];
					if ( type && rec.types.indexOf( type ) === -1 ) { rec.types.push( type ); }
					if ( rec.paths.indexOf( path ) === -1 ) { rec.paths.push( path ); }
					// Tracked for the "Loop fields" TYPE filter (row-only). Not shown as a
					// label marker anymore — filters carry that meaning now.
					rec.rowSeen    = rec.rowSeen || ( 'row' === field.context_hint );
					rec.nonRowSeen = rec.nonRowSeen || ( 'row' !== field.context_hint );
				} );
			} );
		} );

		var records = order.map( function ( m ) { return index[ m ]; } );

		// Flat alphabetical by label (then key for stable tiebreak). No breadcrumb
		// grouping — the filters carry location/type; the list is a plain index.
		records.forEach( function ( r ) {
			r.sortKey = ( r.label + '' + r.key ).toLowerCase();
		} );
		records.sort( function ( a, b ) {
			return a.sortKey < b.sortKey ? -1 : ( a.sortKey > b.sortKey ? 1 : 0 );
		} );

		return records;
	}

	/**
	 * Compose a record's ComboboxControl option { value, label }.
	 *
	 * Flat label: "<label> ('<key>')". No breadcrumb, no loop-only marker — the two
	 * filters disambiguate location/type. `value` is the unique merge key (React
	 * list identity); the serialized value is the bare `key`, resolved in onChange.
	 */
	function recordToOption( rec ) {
		return { value: rec.value, label: rec.label + " ('" + rec.key + "')" };
	}

	/**
	 * Map a container field TYPE to a short location-path hint, or '' if not a
	 * container (only group / repeater / flexible_content nest children).
	 */
	function containerHint( type ) {
		if ( 'repeater' === type ) { return __( 'repeater', 'generateblocks' ); }
		if ( 'group' === type ) { return __( 'group', 'generateblocks' ); }
		if ( 'flexible_content' === type ) { return __( 'flexible', 'generateblocks' ); }
		return '';
	}

	/**
	 * Build the Location filter option list (flat path-strings, prefix set).
	 *
	 * Distinct set of every path PREFIX present across records: the kind roots, then
	 * each "root › group", then each "root › group › parent…". Prefixed with
	 * "All detected fields". Alpha within, roots first.
	 *
	 * The filter `value` stays the raw path (applyFilters prefix-matches it). The
	 * displayed `label` decorates a segment that names a container FIELD (repeater /
	 * group / flexible) with a "(repeater)" etc. hint, so the author sees what kind
	 * of container a path drills into. Container types come from the records
	 * themselves (a repeater field has its own row, type:'repeater'), keyed by label.
	 */
	function buildLocationOptions( records ) {
		// label -> container hint, from any field that IS a container.
		var containerByLabel = Object.create( null );
		records.forEach( function ( rec ) {
			var hint = '';
			for ( var i = 0; i < rec.types.length; i++ ) {
				hint = containerHint( rec.types[ i ] );
				if ( hint ) { break; }
			}
			if ( hint && ! containerByLabel[ rec.label ] ) {
				containerByLabel[ rec.label ] = hint;
			}
		} );

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
		paths.forEach( function ( p ) {
			// Decorate the LAST segment if it names a container field.
			var parts = p.split( BREAD );
			var leaf  = parts[ parts.length - 1 ];
			var hint  = containerByLabel[ leaf ];
			options.push( { value: p, label: hint ? p + ' (' + hint + ')' : p } );
		} );
		return options;
	}

	/**
	 * Build the Field-type filter option list: All / Loop fields / <ACF types>.
	 */
	function buildTypeOptions( records ) {
		var seen = Object.create( null );
		var types = [];
		records.forEach( function ( rec ) {
			rec.types.forEach( function ( t ) {
				if ( t && ! seen[ t ] ) { seen[ t ] = true; types.push( t ); }
			} );
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
				if ( rec.types.indexOf( type ) === -1 ) { return false; }
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
		var preset       = presetKind( state, key );
		var presetPath   = preset ? kindRootLabel( preset ) : ALL_LOC;
		// Only use the preset path if it actually exists in the options (fields of
		// that kind were discovered); otherwise fall back to All.
		var presetExists = locationOptions.some( function ( o ) { return o.value === presetPath; } );
		var activeLoc    = locOverride !== null ? locOverride : ( presetExists ? presetPath : ALL_LOC );

		var filtered = applyFilters( records, activeLoc, typeVal );
		var options  = filtered.map( recordToOption );

		// Option `value` is the unique merge key, but the SERIALIZED tag value is the
		// bare field key. Map option-value → bare key so onChange can strip it. Custom
		// / synthetic / persisted-passthrough options carry value === bare key, so they
		// map to themselves.
		var valueToKey = Object.create( null );
		filtered.forEach( function ( rec ) { valueToKey[ rec.value ] = rec.key; } );

		// Synthetic free-text option: typing an unmatched key offers to commit it bare.
		// Its value IS the bare key (self-committing).
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
				valueToKey[ typed ] = typed;
			}
		}

		// Which option should show as selected for the persisted bare key? Prefer the
		// first row whose bare key matches (highlights it under the current filters).
		// If none matches (custom / filtered-out key), inject a passthrough option
		// whose value === bare key so the control shows it selected rather than blank.
		var selectedValue = value;
		if ( value ) {
			var hit = filtered.filter( function ( rec ) { return rec.key === value; } )[ 0 ];
			if ( hit ) {
				selectedValue = hit.value;
			} else if ( ! options.some( function ( o ) { return o.value === value; } ) ) {
				options = [ { value: value, label: value } ].concat( options );
				valueToKey[ value ] = value;
			}
		}

		function onChange( next ) {
			var upd = Object.assign( {}, state );
			if ( next === null || next === undefined || next === '' ) {
				delete upd[ key ];
			} else {
				// Strip the merge-key wrapper → commit the bare field key.
				upd[ key ] = valueToKey[ next ] !== undefined ? valueToKey[ next ] : next;
			}
			setState( upd );
		}

		// Dynamic label, most-specific-wins:
		//   1. active Location narrowed to a GROUP → "<Group> Field" (e.g. "Client
		//      Details Field") — the author has named the exact home;
		//   2. else the active Location's KIND → "Post/Term/Site Meta Field";
		//   3. else the sibling-source preset kind; else the source-agnostic fallback.
		// labelPrefix (e.g. "URL") is honored in every case.
		var label;
		if ( props.dynamicLabel ) {
			var groupLbl = locationGroupLabel( activeLoc );
			if ( groupLbl ) {
				// "<Group> Field" (e.g. "Client Details Field"). Group names are ACF
				// author-supplied, so a simple concat reads correctly across locales.
				var base = groupLbl + ' ' + __( 'Field', 'generateblocks' );
				label = props.labelPrefix ? props.labelPrefix + ' ' + base : base;
			} else {
				label = kindLabel( kindFromLocation( activeLoc ) || preset, props.labelPrefix );
			}
		} else {
			label = props.label;
		}

		return el( Fragment, null, [
			// Two filters side-by-side above the field selector. align:flex-end keeps
			// the dropdowns aligned when labels wrap to different heights.
			el( Flex, { key: 'filters', gap: 2, align: 'flex-end', wrap: true }, [
				el( FlexItem, { key: 'loc', isBlock: true },
					el( SelectControl, {
						label:    __( 'Filter fields by location', 'generateblocks' ),
						value:    activeLoc,
						options:  locationOptions,
						onChange: function ( v ) { setLoc( v ); },
						__nextHasNoMarginBottom: true,
					} )
				),
				el( FlexItem, { key: 'type', isBlock: true },
					el( SelectControl, {
						label:    __( 'Filter fields by type', 'generateblocks' ),
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
				value:               selectedValue,
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
