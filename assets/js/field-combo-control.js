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
 * - Label: `City (Text, 'venue_city')` — the field label, then one bracket group
 *   holding the field TYPE and the resolution key. The type is derived from the field
 *   definition for every row alike, never hand-written into a label; it shares the
 *   key's brackets because both are facts ABOUT the field the label names. It joins
 *   the combobox's search text, so typing "post object" narrows the list, but it does
 *   NOT move the sort, which stays alphabetical by field label. The breadcrumb and
 *   the `loop-only` suffix this line used to describe were dropped when the two
 *   filters took over location and loop-ness.
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

	// The render uses ComboboxControl/SelectControl/Flex/FlexItem specifically.
	// Flex/FlexItem are newer @wordpress/components exports than ComboboxControl,
	// so gate on the exact components used: if any is missing (older/shimmed stack)
	// bail rather than throwing el(undefined) mid-render, which would leave the
	// field-key input unusable (worse than the text box this replaced).
	if ( ! wp.components.ComboboxControl || ! wp.components.SelectControl
		|| ! wp.components.Flex || ! wp.components.FlexItem ) {
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
	// Merge-key field delimiter. Built via fromCharCode so no literal control char
	// sits in the source (a raw U+001F renders as an empty '' and misreads as "no
	// separator"). U+001F not NUL: NUL broke the build; printable chars are forgeable
	// by ordinary field text.
	var UNIT_SEP = String.fromCharCode( 31 ); // U+001F
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
				envelopePromise = apiFetch( { path: '/bws-dynamic-tags/v1/fields' } )
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
	 * leaf segment with a trailing SYNTHETIC container hint stripped. Root-only
	 * ("Post fields") or "All detected fields" → null (caller uses the kind label).
	 *
	 * The Location filter VALUE is the raw path (container hints are added only to
	 * the display label, not the value), so the leaf here is a real author segment.
	 * Strip only an exact trailing synthetic hint (defensive; the old code stripped
	 * ANY "(...)", mangling a real group name like "Details (US)" into "Details").
	 *
	 * @param {string} loc Active location filter value.
	 * @return {string|null} Group/container name, or null.
	 */
	function locationGroupLabel( loc ) {
		if ( ! loc || loc === ALL_LOC ) { return null; }
		var parts = String( loc ).split( BREAD );
		if ( parts.length < 2 ) { return null; } // kind root only → no group
		var leaf  = parts[ parts.length - 1 ];
		var hints = [ containerHint( 'repeater' ), containerHint( 'group' ), containerHint( 'flexible_content' ) ];
		for ( var i = 0; i < hints.length; i++ ) {
			var suffix = ' (' + hints[ i ] + ')';
			if ( hints[ i ] && leaf.slice( -suffix.length ) === suffix ) {
				return leaf.slice( 0, -suffix.length );
			}
		}
		return leaf;
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
		// src:ref is deliberately NOT preset. Under src:ref the ref-hop target
		// post type is not reliably known (parity unbuilt), so `key`-under-src:ref
		// stays UNSCOPED — all groups + free-text — with the source-agnostic
		// "Meta/Option Field" label rather than falsely asserting "Post". (SPEC V3.)
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
	 *
	 * The map names the types whose slug reads badly; everything else TITLE-CASES the
	 * slug rather than printing it raw (`page_link` → "Page Link"). That fallback is
	 * what makes the map OPTIONAL: a type this plugin has never heard of — a
	 * third-party one, or a new ACF release's — reads as a name in the type filter and
	 * in every field row without an edit here. A map entry is then a wording
	 * improvement, never a prerequisite for a type to be presentable.
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
		if ( map[ type ] ) {
			return map[ type ];
		}
		return String( type || '' ).split( '_' ).map( function ( word ) {
			return word ? word.charAt( 0 ).toUpperCase() + word.slice( 1 ) : word;
		} ).join( ' ' );
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
	 *   rowSeen      true if ANY instance is a repeater/flex child (drives the
	 *                "Loop fields" type filter)
	 *   repeaterKeys array of owning repeater/flex resolution keys this field is a
	 *                sub-field of (from the server `repeater_key` stamp; empty for a
	 *                top-level field). Drives the {{table}} {N}-key auto-scope (#12):
	 *                a picker scoped to repeater R keeps only records whose
	 *                repeaterKeys include R. Machine-readable — NOT parsed from the
	 *                breadcrumb (parent_path), which stays display-only.
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
					var mkey = kind + UNIT_SEP + key + UNIT_SEP + lbl;
					if ( ! index[ mkey ] ) {
						index[ mkey ] = {
							// React list key / ComboboxControl option value — unique per row.
							// The SERIALIZED value is the bare `key` (see onChange), not this.
							value:      mkey,
							key:        key,
							label:      lbl,
							kind:         kind,
							types:        [],
							paths:        [],
							rowSeen:      false,
							repeaterKeys: [],
						};
						order.push( mkey );
					}
					var rec = index[ mkey ];
					if ( type && rec.types.indexOf( type ) === -1 ) { rec.types.push( type ); }
					if ( rec.paths.indexOf( path ) === -1 ) { rec.paths.push( path ); }
					// Tracked for the "Loop fields" TYPE filter. Not shown as a label
					// marker anymore; the filter carries that meaning now.
					rec.rowSeen = rec.rowSeen || ( 'row' === field.context_hint );

					// Owning repeater/flex key (server `repeater_key` stamp), accumulated
					// because one merged record can be a sub-field of more than one
					// container (same bare key + label in two repeaters). Drives #12
					// scope. Empty string = top-level field → not recorded.
					var rk = field.repeater_key || '';
					if ( rk && rec.repeaterKeys.indexOf( rk ) === -1 ) {
						rec.repeaterKeys.push( rk );
					}
				} );
			} );
		} );

		var records = order.map( function ( m ) { return index[ m ]; } );

		// Flat alphabetical by label (then key for stable tiebreak). No breadcrumb
		// grouping — the filters carry location/type; the list is a plain index.
		// Underscore-prefixed resolution keys (protected/internal meta like
		// `_gb_*`, `_acf_*`) are DEMOTED to the bottom, not hidden: a rank prefix
		// (`0` normal, `1` underscore) sorts them into a trailing block, still
		// alphabetical within it. They stay resolvable and selectable.
		records.forEach( function ( r ) {
			var rank  = ( r.key.charAt( 0 ) === '_' ) ? '1' : '0';
			r.sortKey = rank + ( r.label + UNIT_SEP + r.key ).toLowerCase();
		} );
		records.sort( function ( a, b ) {
			return a.sortKey < b.sortKey ? -1 : ( a.sortKey > b.sortKey ? 1 : 0 );
		} );

		return records;
	}

	/**
	 * Compose a record's ComboboxControl option { value, label }.
	 *
	 * Flat label: "<label> (<Type>, '<key>')". No breadcrumb, no loop-only marker —
	 * the Location filter disambiguates location. `value` is the unique merge key
	 * (React list identity); the serialized value is the bare `key`, resolved in
	 * onChange.
	 *
	 * THE TYPE IS UNIVERSAL AND DERIVED, which is the point of it being here at all.
	 * A field's type governs what a tag can do with it — a `refs` step wants a
	 * relationship or post object, a datetime tag wants a date field — and it was
	 * previously reachable only through the type FILTER, i.e. by narrowing the list
	 * rather than by reading a row. Fixture and real-site authors had taken to writing
	 * it into the field LABEL ("Lead Staff (post object, object format)"), which
	 * produced a hand-cased annotation on some fields and none on others, and doubled
	 * brackets against the quoted key. Deriving it from `rec.types` gives every row the
	 * same annotation, spelt one way, with nothing to keep in step.
	 *
	 * IT JOINS THE EXISTING BRACKET GROUP rather than opening a second one. The LABEL
	 * is what an author scans for, so it keeps the front of the row; type and key are
	 * both machine facts ABOUT that field, so they belong together behind it. A leading
	 * or trailing group of its own would put three bracket groups on one row, which is
	 * the doubling this replaced.
	 *
	 * IT DOES NOT MOVE THE SORT. `sortKey` is built from the label and key, never from
	 * this text, so the list stays alphabetical by field name rather than clustering by
	 * type — that is what the type filter is for, and the flat-alphabetical list is the
	 * locked design. It DOES join the combobox's own search text, so typing "post
	 * object" narrows to post object fields, which is a free affordance rather than a
	 * second filter.
	 *
	 * A merged record can carry several types (the same key + label reached through two
	 * homes), so they are joined rather than one being picked; a record with no type at
	 * all (registered meta) gets no annotation rather than an empty slot.
	 */
	function recordToOption( rec ) {
		var types = ( rec.types || [] ).filter( Boolean ).map( typeLabel ).join( ' / ' );

		// When there is no distinct field label (envelopeToRecords fell back to the
		// key), show the key ONCE — `event_date (Text)`, not
		// `event_date (Text, 'event_date')`. The bracket group still carries whatever
		// the row has not already said.
		if ( rec.label === rec.key ) {
			return { value: rec.value, label: types ? rec.key + ' (' + types + ')' : rec.key };
		}

		// Combobox filters on this label; the key is present either way so typing it
		// still matches.
		var inner = types ? types + ", '" + rec.key + "'" : "'" + rec.key + "'";
		return { value: rec.value, label: rec.label + ' (' + inner + ')' };
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
				// Any field with a loop (repeater/flex row) home. A field that ALSO
				// resolves outside a row still shows here — it is usable in a loop,
				// which is what the filter asks. (Not "row-exclusive": that would
				// hide a dual-context field that has a legitimate loop home.)
				if ( ! rec.rowSeen ) { return false; }
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

		// Type filter: null => follow the typeDefault preset (below); a string => explicit
		// author pick (ephemeral view state, same as locOverride). Author can always widen
		// back to "All field types" — the default is a starting view, not a lock.
		var typeState   = useState( null );
		var typeOverride = typeState[ 0 ];
		var setType      = typeState[ 1 ];

		useEffect( function () {
			var live = true;
			fetchEnvelope().then( function ( env ) {
				if ( live ) { setEnvelope( env ); }
			} );
			return function () { live = false; };
		}, [] );

		var allRecords = useMemo( function () {
			return envelope ? envelopeToRecords( envelope ) : [];
		}, [ envelope ] );

		// #12 auto-scope: when props.scope === 'row' (the {{table}} {N}-key column
		// controls), narrow the field pool to the sub-fields of the repeater named by
		// the TAG-LEVEL `key` option (always the bare `key`, whatever this control's own
		// prefix), and hide the two filter selectors below. The scope handle is machine-
		// readable (rec.repeaterKeys, from the server repeater_key stamp) — NOT parsed
		// from the display breadcrumb. Empty / unknown repeater key → no scoping (the
		// picker degrades to the full list; free-text of any key still works). This is a
		// per-instance render conditional, deliberately NOT the FU-3 shared-state channel
		// (see .claude/plans/field-selector.md FU-3): table ships without blocking on it.
		//
		// THE SCOPE HANDLE IS A PROP FIRST, a state read only as fallback (FW-56/57).
		// Reading `state.key` is this control DISCOVERING its own scope by reaching
		// outward into sibling tag state. That works only while "the bare `key`" has
		// exactly one meaning — and under the folded slot wire it does not: a column's
		// own READ is also spelled `key(...)`, one level in, so a folded slot's
		// synthetic context presents its own field under the same bare name. The defect
		// is the REACH, not the spelling: renaming either token would paper over one
		// instance and leave any future two-level tag to re-break it. So whatever
		// registers the column control — which alone knows the tag's shape — passes
		// `scopeKey` explicitly. The state read stays for the shipped flat `{N}-key`
		// registrations, which have no prop to pass.
		var scopeRepeaterKey = ( 'row' === props.scope )
			? String( ( void 0 !== props.scopeKey && null !== props.scopeKey ) ? props.scopeKey : ( state.key || '' ) ).trim()
			: '';
		var scopeToRepeater  = '' !== scopeRepeaterKey;

		var records = useMemo( function () {
			if ( ! scopeToRepeater ) { return allRecords; }
			var scoped = allRecords.filter( function ( rec ) {
				return rec.repeaterKeys && rec.repeaterKeys.indexOf( scopeRepeaterKey ) !== -1;
			} );
			// If the repeater key matched NO discovered sub-fields (an unregistered /
			// free-typed repeater, or a non-repeater key), do NOT collapse to an empty
			// list — that would strand the author with no picker and no way back. Fall
			// through to the full pool; free-text still commits any sub-field name.
			return scoped.length ? scoped : allRecords;
		}, [ allRecords, scopeToRepeater, scopeRepeaterKey ] );

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

		// Effective type: explicit override, else the option's typeDefault (e.g. the
		// {{table}} tag-level `key` pre-scopes to 'repeater' so the picker opens showing
		// only repeater fields), else All. Only apply the default if that type was
		// actually discovered (mirrors presetExists) — otherwise the picker would open on
		// an empty list. The two filter SelectControls stay visible either way, so the
		// author can widen to All or pick another type; this is a starting view, not a
		// lock. Orthogonal to props.scope ('row'), which HIDES the filters and narrows to
		// one repeater's sub-fields (#12). See .claude/plans/table-tag.md #12 (OTHER-axis).
		var typeDefault  = props.typeDefault || '';
		var typeExists   = typeDefault && typeOptions.some( function ( o ) { return o.value === typeDefault; } );
		var typeVal      = typeOverride !== null ? typeOverride : ( typeExists ? typeDefault : ALL_TYPE );

		// Derive the option list, the option-value→bare-key map, and the selected
		// value together. Pure function of [records, activeLoc, typeVal, filterText,
		// value] — memoized so typing (which fires setFilterText → re-render on every
		// keystroke) does not re-filter the whole record set + re-allocate the map
		// unless one of those inputs actually changed.
		var derived = useMemo( function () {
			var filtered   = applyFilters( records, activeLoc, typeVal );
			var options    = filtered.map( recordToOption );

			// Option `value` is the unique merge key, but the SERIALIZED tag value is
			// the bare field key. Map option-value → bare key so onChange can strip it.
			// Custom / synthetic / persisted-passthrough options carry value === bare
			// key, so they map to themselves.
			var valueToKey = Object.create( null );
			filtered.forEach( function ( rec ) { valueToKey[ rec.value ] = rec.key; } );

			// Synthetic free-text option: typing an unmatched key offers to commit it
			// bare. Its value IS the bare key (self-committing).
			var typed = ( filterText || '' ).trim();
			if ( typed ) {
				// Suppress the synthetic option only when the typed text ALREADY commits
				// an existing option: its bare key (valueToKey) or raw option value
				// equals the typed text. Match is EXACT and case-SENSITIVE: WP meta/ACF
				// keys are case-sensitive, so `event_date` and `Event_Date` are DIFFERENT
				// keys; a case-fold here would hide the escape hatch and leave the
				// lower-cased variant uncommittable. (Substring-of-LABEL would also
				// over-suppress, e.g. `city` vs a visible "City ('venue_city')".)
				var matches = options.some( function ( o ) {
					return o.value === typed || valueToKey[ o.value ] === typed;
				} );
				if ( ! matches ) {
					options = [ {
						value: typed,
						label: __( 'Use custom key:', 'generateblocks' ) + ' "' + typed + '"',
					} ].concat( options );
					valueToKey[ typed ] = typed;
				}
			}

			// Which option should show as selected for the persisted bare key?
			// The serialized value is only the bare key, which can map to more than one
			// discovered field (same key, DIFFERENT labels — e.g. `name` = "Name" vs
			// "Feature Name"). We must NOT auto-select one labeled row in that case: it
			// would falsely assert the author picked that specific field. So:
			//   1. Key matches EXACTLY ONE record → select it (friendly "Label ('key')"),
			//      injecting the option if the active filter hides it.
			//   2. Key is AMBIGUOUS (>1 record) or UNKNOWN (0 records) → neutral
			//      passthrough option showing the raw key, no false label. The author
			//      re-picks the exact row to disambiguate.
			var selectedValue = value;
			if ( value ) {
				var matchesKey = records.filter( function ( rec ) { return rec.key === value; } );
				if ( 1 === matchesKey.length ) {
					var known = matchesKey[ 0 ];
					selectedValue = known.value;
					if ( ! options.some( function ( o ) { return o.value === known.value; } ) ) {
						options = [ recordToOption( known ) ].concat( options );
						valueToKey[ known.value ] = known.key;
					}
				} else if ( ! options.some( function ( o ) { return o.value === value; } ) ) {
					// Ambiguous or unknown: show the bare key, assert nothing.
					options = [ { value: value, label: value } ].concat( options );
					valueToKey[ value ] = value;
				}
			}

			return { options: options, valueToKey: valueToKey, selectedValue: selectedValue };
		}, [ records, activeLoc, typeVal, filterText, value ] );

		var options       = derived.options;
		var valueToKey    = derived.valueToKey;
		var selectedValue = derived.selectedValue;

		function onChange( next ) {
			var upd = Object.assign( {}, state );
			if ( next === null || next === undefined || next === '' ) {
				delete upd[ key ];
			} else if ( valueToKey[ next ] !== undefined ) {
				// Strip the merge-key wrapper → commit the bare field key.
				upd[ key ] = valueToKey[ next ];
			} else if ( next.indexOf( UNIT_SEP ) !== -1 ) {
				// A private merge key with no valueToKey entry: an option was rendered
				// without registering its bare key (a bug in the option-build paths).
				// Never serialize the U+001F wrapper into the tag; drop instead.
				return;
			} else {
				// Genuine free-text custom key (no wrapper) → commit verbatim.
				upd[ key ] = next;
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

		// #12: hide the location/type filters when auto-scoped to a repeater — the
		// scope IS the filter (the picker already shows only that repeater's sub-fields),
		// so the two selectors would be redundant + misleading. A null child renders
		// nothing (React); the combobox stands alone.
		var filtersBlock = scopeToRepeater ? null : el( Flex, { key: 'filters', gap: 2, align: 'flex-end', wrap: true }, [
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
		] );

		return el( Fragment, null, [
			// Two filters side-by-side above the field selector (hidden when auto-scoped).
			filtersBlock,
			el( ComboboxControl, {
				key:                 'combo',
				label:               label,
				help:                props.help,
				placeholder:         props.placeholder,
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
			placeholder:  cfg.placeholder,
			dynamicLabel: cfg.dynamicLabel,
			labelPrefix:  cfg.labelPrefix,
			// #12: 'row' auto-scopes the picker to a sibling repeater's sub-fields
			// (the {N}-key column controls) and hides the two filter selectors.
			scope:        cfg.scope,
			// Explicit scope handle when the registrar knows it. Absent on the flat
			// `{N}-key` registrations, which fall back to the sibling state read.
			scopeKey:     cfg.scopeKey,
			// Pre-selects the type filter (e.g. 'repeater' for the {{table}} tag-level
			// `key`) without hiding the filters — the OTHER scope axis vs `scope:'row'`.
			typeDefault:  cfg.typeDefault,
			context:      context,
		} );
	}

	wp.hooks.addFilter(
		'generateblocks.editor.tagSpecificControls',
		'bws/field-combo-control',
		fieldComboFilter
	);

	// Expose the component for COMPOSITION by other controls. The filter above
	// mounts it per option key, which assumes the field key IS an option — true
	// today, but not when a composite control owns a folded value that carries the
	// field key inside it (FW-57). Such a parent renders this component directly
	// against a synthetic context instead. Export only; no behavior change, and
	// the mount path above is untouched.
	window.bwsFieldComboControl = FieldComboControl;

} )();
