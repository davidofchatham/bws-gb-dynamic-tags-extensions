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
 *       prefix-match. Preset from the sibling `src` chain where that chain proves
 *       where the read lands, else "All detected fields" — NEVER assume the
 *       editor's current context is a post (that is the GB bug we escape).
 *       `presetKind()` owns which tails prove it and is the only place that says.
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
 * - ROOT-ARGUMENT NARROWING (FW-39 D22): when the sibling `src` is a single-step chain
 *   rooted at a SPECIFIC entity (`term,34`, `post,1692`), the list narrows to the fields
 *   scoped to that term's taxonomy or that post's post type. The scope handle comes off
 *   the entity-lookup route's resolve mode; the per-field `scope` it matches against is
 *   the discovery envelope's EXISTING one, unchanged (D23).
 *
 * Registered via `generateblocks.editor.tagSpecificControls`; activates for any
 * option whose PHP `type` is `bws-field-combo`.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.13.0
 * @since   1.20.0 Root-argument scope narrowing (FW-39 D22).
 * @since   1.21.0 Location preset from the repeater a chain ends on (FW-74).
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
	 * The SPECIFIC ENTITY this picker reads a field off, or null (FW-39 D22).
	 *
	 * Read off the SAME sibling `src` token `presetKind()` below reads, through the
	 * shipped chain grammar rather than a second parser — a chain's root and its
	 * argument are `window.bwsSlotFold`'s to spell, and a local split on `,` would be
	 * the second spelling that goes stale the first time the grammar grows a token.
	 *
	 * ONLY A SINGLE-STEP CHAIN ANSWERS. A root argument narrows the picker because the
	 * entity the field is read off IS the one it names; add a `refs` step and the read
	 * applies to that step's target instead, whose type nothing here knows (the same reason
	 * `presetKind()` refuses to preset under `src:ref`). Narrowing there would assert a
	 * scope the wire does not support.
	 *
	 * THE KIND COMES FROM THE ROOT ROWS, not from the root slug. `window.bwsRootArgKinds`
	 * is emitted from `bws_registered_root_rows()`, the one appender both authoring
	 * surfaces read, so a root contributed through `bws_dynamic_tags_chain_roots` is
	 * selectable here on the same terms as ours and a root that takes no argument is
	 * simply absent from the map.
	 *
	 * @param {Object} state     extraTagParams.
	 * @param {string} optionKey The key control's own option key (for slot prefix).
	 * @return {Object|null} `{ kind, id }`, or null when no entity is selected.
	 */
	function rootArgFromState( state, optionKey ) {
		var fold  = window.bwsSlotFold;
		var kinds = window.bwsRootArgKinds;
		if ( ! state || ! kinds || ! fold || 'function' !== typeof fold.parseChain ) {
			return null;
		}
		var wire = String( state[ slotPrefix( optionKey ) + 'src' ] || '' ).trim();
		if ( '' === wire ) { return null; }
		var chain = fold.parseChain( wire );
		if ( ! Array.isArray( chain ) || 1 !== chain.length ) { return null; }
		var slug = fold.chainRoot( chain );
		var arg  = fold.chainRootArg( chain );
		if ( ! slug || '' === arg || ! kinds[ slug ] ) { return null; }
		return { kind: kinds[ slug ], id: arg };
	}

	/**
	 * The sibling `src` chain's LAST step, or null (FW-74).
	 *
	 * The read applies to whatever the chain resolved to last, so the tail is the only
	 * position that can say anything about where the offerable fields live — both what
	 * KIND they are (`presetKind()`) and, when the tail names a container, exactly which
	 * one (`containerRowPath()`). Parsed through the shipped grammar for the reason
	 * `rootArgFromState()` gives: the chain's spelling is `window.bwsSlotFold`'s, and a
	 * local split on `,` is the second spelling that goes stale the first time the
	 * grammar grows a token.
	 *
	 * @param {Object} state     extraTagParams.
	 * @param {string} optionKey The key control's own option key (for the slot prefix).
	 * @return {Object|null} `{ slug, arg }` with `arg` normalized to a string, or null.
	 */
	function chainTail( state, optionKey ) {
		var fold = window.bwsSlotFold;
		if ( ! state || ! fold || 'function' !== typeof fold.parseChain ) { return null; }
		var wire = String( state[ slotPrefix( optionKey ) + 'src' ] || '' ).trim();
		if ( '' === wire ) { return null; }
		var chain = fold.parseChain( wire );
		if ( ! Array.isArray( chain ) || ! chain.length ) { return null; }
		var tail = chain[ chain.length - 1 ];
		if ( ! tail || ! tail.slug ) { return null; }
		return { slug: String( tail.slug ), arg: tail.arg ? String( tail.arg ) : '' };
	}

	/**
	 * The Location path a container field's children sit under, or '' (FW-74).
	 *
	 * A container's children hang one segment below the container's own home, under its
	 * label — exactly the breadcrumb `bws_field_discovery_flatten_fields()` builds. So the
	 * path is read off the CONTAINER'S OWN record rather than off a child's: a merged
	 * record (one key reached through two homes) would answer for whichever home sorted
	 * first, which need not be this one.
	 *
	 * A key naming no discovered CONTAINER answers '' — a `refs` or `terms` tail carries a
	 * relationship field key or a taxonomy slug, and neither owns a container record. That
	 * test is stated here AND enforced again at the caller, where a preset path absent from
	 * the option set is dropped: nothing hangs below a non-container, so the path a
	 * non-container would produce cannot exist. Two spellings of one refusal, kept because
	 * only the local one says which question was being asked.
	 *
	 * @param {Array}  records      Flat merged field records.
	 * @param {string} containerKey Resolution key of a repeater/group/flexible field.
	 * @return {string} Full location path, or ''.
	 */
	function containerRowPath( records, containerKey ) {
		if ( ! containerKey ) { return ''; }
		for ( var i = 0; i < records.length; i++ ) {
			var rec = records[ i ];
			if ( rec.key !== containerKey || ! rec.paths.length ) { continue; }
			var isContainer = rec.types.some( function ( t ) { return '' !== containerHint( t ); } );
			if ( isContainer ) { return rec.paths[ 0 ] + BREAD + rec.label; }
		}
		return '';
	}

	/**
	 * The post TYPES a `refs` tail's own field can land on, or [] (FW-13).
	 *
	 * A `refs` argument names the field stepped THROUGH, so the post it reaches has a type
	 * the wire never states — but the FIELD states it, and the discovery endpoint stamps
	 * what it says on every record (`ref_types`). This reads it back off the record the
	 * argument names, the same machine-readable route `containerRowPath()` takes for a
	 * `rows` tail.
	 *
	 * UNIONED ACROSS EVERY RECORD SHARING THE KEY, unlike `containerRowPath()`'s
	 * first-match. Two distinct fields can share a resolution key under different labels,
	 * and the wire names only the key, so either could be the one stepped through.
	 * Widening is the safe direction here: offering a type the step cannot reach is loose,
	 * refusing one it can is wrong.
	 *
	 * @param {Array}  records  Flat merged field records.
	 * @param {string} fieldKey Resolution key of the relationship / post object stepped through.
	 * @return {Array} Post-type slugs, or [] when nothing narrows.
	 */
	/**
	 * Records of ONE kind whose scope reaches any of `slugs`, plus that kind's unscoped ones.
	 *
	 * THE ONE NARROWING PREDICATE, shared by the two things that narrow a pool: the
	 * root-argument scope (FW-39 D22 — one slug, off a resolved entity lookup) and the
	 * refs-tail post types (FW-13 — the list a relationship field allows). Both ask a record
	 * the same question, "are you mine, and does your scope reach me", and only the kind and
	 * the slugs differ, so those are the parameters and the rule is written once. A second
	 * spelling of it is exactly where the two would drift apart.
	 *
	 * THE KIND IS TESTED ALONGSIDE THE SLUG, NEVER INSTEAD OF IT. A scope entry is a bare
	 * slug and a taxonomy may share its spelling with a post type, so a slug match on its own
	 * would offer a field the read cannot reach. What a group's `scope` reaches — and
	 * therefore what an EMPTY one reaches — is stated where it is derived, at
	 * `bws_field_discovery_derive_kind_scope()`; read it there. `scopeless` is that endpoint's
	 * "any subtype of MY kind", so it passes the scope test and still faces the kind one.
	 *
	 * NO FALL-BACK-TO-ALL when the result comes out empty, unlike the repeater auto-scope at
	 * the call site. There, an empty result means the scope handle matched nothing discovered
	 * and the author is stranded with no picker; here it means the selection genuinely has no
	 * fields, which is the answer the narrowing exists to give. Free text still commits any
	 * key either way.
	 *
	 * @param {Array}  records Records to narrow.
	 * @param {string} kind    Resolved-source kind the narrowing is of.
	 * @param {Array}  slugs   Subtype slugs of that kind to accept.
	 * @return {Array} The narrowed records.
	 */
	function narrowToScope( records, kind, slugs ) {
		return records.filter( function ( rec ) {
			if ( rec.kind !== kind ) { return false; }
			return rec.scopeless || rec.scopes.some( function ( sc ) {
				return slugs.indexOf( sc ) !== -1;
			} );
		} );
	}

	function refTailTypes( records, fieldKey ) {
		var out = [];
		if ( ! fieldKey ) { return out; }
		records.forEach( function ( rec ) {
			if ( rec.key !== fieldKey ) { return; }
			( rec.refTypes || [] ).forEach( function ( pt ) {
				if ( out.indexOf( pt ) === -1 ) { out.push( pt ); }
			} );
		} );
		return out;
	}

	/**
	 * Source-token -> kind preset for the Location filter, or null (=> All detected).
	 *
	 * NEVER assume post from the editor context — the kind is presetted only where a token
	 * PROVES it, which is the GB bug this control exists to escape. Reads the sibling tokens
	 * of the SAME slot (prefix-aware) so per-slot try_ keys track their own source.
	 *
	 * THE CHAIN IS THE SOURCE OF THE ANSWER, not the legacy flat keys. This used to read
	 * `srcTermIn` and a literal `src === 'site'`, both of which predate chain wire: the flat
	 * axes were absorbed by the chain control in 1.17.0 and are dropped at registration, so
	 * the term preset was reachable only from stored legacy wire while `terms,<tax>` — the
	 * spelling that replaced it — presetted nothing. `site` kept working by coincidence,
	 * its root taking no argument and so serializing as the bare slug the equality matched.
	 *
	 * The question asked is `bws_fold_chain_resolution()`'s — the tail STEP's produced kind,
	 * or the ROOT's where that answers at parse time — and both maps arrive from PHP on
	 * `window.bwsChainKinds`, so a step type or root added there presets here with no edit.
	 * A kind the picker has no root label for (`meta_row`) presets nothing through this
	 * path; `containerRowPath()` is that kind's specific and better answer.
	 *
	 * NO STEP IS EXEMPT, `refs` INCLUDED. It carried an exemption from 1.13.0 (`22bddf1`),
	 * on the ground that a `refs` argument names the field stepped THROUGH rather than the
	 * post it lands on, so the target's type is unknown. That reasoning is about post TYPE
	 * and the preset is about KIND: `refs` produces `post` unconditionally — the engine
	 * forces it, `BWS_FOLD_STEP_KINDS` records it — so refusing to say `post` here withheld
	 * a fact the render already commits to, and left the author a list holding term and site
	 * fields that a post read cannot reach. The exemption would be owed again only if `refs`
	 * could produce MORE THAN ONE kind (a relationship reaching a term or a user), and that
	 * breaks the single-valued map first: fix it there and this follows, which is the whole
	 * reason the derivation is not a table here.
	 *
	 * The two LEGACY flat arms STAY, and what they are FOR is narrower than it looks. GB seeds
	 * `extraTagParams` from the parsed tag string, so a pre-1.17.0 tag does arrive carrying
	 * one — but `BaseSrcMountMigrator` (slot-fold-migrate.js) commits the fold from a mount
	 * `useEffect`, so on a healthy stack the flat key is gone within the same tick and these
	 * arms answer for one render pass nobody sees. They are NOT the observable path and no
	 * manual row can drive them.
	 *
	 * They earn their place on a DEGRADED stack. `chainTail()` needs `window.bwsSlotFold` and
	 * the mount migrator needs `bwsSlotFoldMigrate`; where either failed to load, the fold
	 * never happens AND the chain cannot be parsed, so a legacy tag would preset nothing at
	 * all. These arms are what it presets from instead, and they answer what the steps they
	 * fold into answer. §F15.10/§F15.11 are that pair — the vocabulary withdrawn, the flat
	 * key still landing.
	 *
	 * @param {Object} state     extraTagParams.
	 * @param {string} optionKey The key control's own option key (for slot prefix).
	 * @return {string|null} 'post' | 'term' | 'site' | null (=> All detected).
	 */
	function presetKind( state, optionKey ) {
		if ( ! state ) { return null; }
		var p = slotPrefix( optionKey );
		if ( state[ p + 'srcTermIn' ] ) { return 'term'; }
		if ( 'ref' === state[ p + 'src' ] ) { return 'post'; }

		var tail = chainTail( state, optionKey );
		if ( ! tail ) { return null; }

		var vocab = window.bwsChainKinds || {};
		var kind  = ( vocab.steps || {} )[ tail.slug ];
		if ( undefined === kind ) { kind = ( vocab.roots || {} )[ tail.slug ]; }

		// A kind with no root label of its own is not a Location the filter can open on.
		// That is the honest answer for `meta_row` and for anything a later step type
		// produces that this list does not carry — never a guess at the nearest kind.
		return ( -1 !== KINDS.indexOf( kind ) ) ? kind : null;
	}

	/**
	 * Dynamic control label — meta/option storage-backend subtype pair (V4).
	 * Uses the preset kind (safe-token) when known, else the source-agnostic fallback.
	 *
	 * NAMES THE FIELD, NOT THE CONTROL — the caller appends the noun. See `labelNoun()`.
	 */
	function kindLabel( kind ) {
		if ( 'post' === kind ) { return __( 'Post Meta Field', 'generateblocks' ); }
		if ( 'term' === kind ) { return __( 'Term Meta Field', 'generateblocks' ); }
		if ( 'site' === kind ) { return __( 'Site Option Field', 'generateblocks' ); }
		return __( 'Meta/Option Field', 'generateblocks' );
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
	 *   refTypes     array of post-type slugs a `refs` step THROUGH this field can land
	 *                on (from the server `ref_types` stamp; empty for anything but a
	 *                restricted relationship / post object). Drives the refs-tail
	 *                narrowing (FW-13) — UNIONED across homes like `scopes`, because a
	 *                merged record reached through two homes reaches both.
	 *   scopes       array of the entity slugs (taxonomy slugs under kind `term`,
	 *                post-type slugs under kind `post`) this field is scoped to, from
	 *                the envelope GROUP's existing `scope`. Drives the root-argument
	 *                narrowing (FW-39 D22).
	 *   scopeless    true if ANY home this record was reached through carried NO scope.
	 *                An empty group scope is the discovery endpoint's own way of saying
	 *                "any entity of that kind", so such a record is offered under every
	 *                root argument OF ITS OWN KIND — and it is a SEPARATE flag rather
	 *                than an empty `scopes` because a record merged from one scoped home
	 *                and one unscoped one has both a slug list and unrestricted reach,
	 *                and unioning the two into one array would lose the second.
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
				var groupScope = group.scope || [];
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
							refTypes:     [],
							scopes:       [],
							scopeless:    false,
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

					// Allowed post types of a relationship / post object (server
					// `ref_types` stamp), accumulated for the same reason `scopes` is:
					// one merged record can be the same key reached through two homes,
					// and a step through it reaches whatever either home allows.
					( field.ref_types || [] ).forEach( function ( pt ) {
						if ( pt && rec.refTypes.indexOf( pt ) === -1 ) {
							rec.refTypes.push( pt );
						}
					} );

					// Entity scope, UNIONED across the homes a merged record was reached
					// through — the same conservative widening `paths` and `types` take.
					if ( groupScope.length ) {
						groupScope.forEach( function ( sc ) {
							if ( sc && rec.scopes.indexOf( sc ) === -1 ) { rec.scopes.push( sc ); }
						} );
					} else {
						rec.scopeless = true;
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
		// The bracket group carries whatever the row has not ALREADY said, in the
		// fixed order type-then-key. So a record with no distinct field label
		// (envelopeToRecords fell back to the key) shows the key once as the head and
		// never repeats it inside — `event_date (Date)`, not
		// `event_date (Date, 'event_date')` — and a record with neither type nor a
		// second fact to state gets no brackets at all rather than an empty pair.
		// Combobox filters on this whole label, so both facts stay typeable.
		var parts = [ ( rec.types || [] ).filter( Boolean ).map( typeLabel ).join( ' / ' ) ];

		if ( rec.label !== rec.key ) {
			parts.push( "'" + rec.key + "'" );
		}

		var inner = parts.filter( Boolean ).join( ', ' );

		return { value: rec.value, label: inner ? rec.label + ' (' + inner + ')' : rec.label };
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

		// THE SELECTED ENTITY'S OWN SCOPE SLUG (FW-39 D22) — the taxonomy a specific term
		// belongs to, or the post type a specific post is. Fetched from the entity-lookup
		// route's resolve mode, which is the root argument's own lookup and already the
		// shape the picker beside this control resolves its reopen label through; NOTHING
		// is added to the field-discovery endpoint, whose per-field `scope` this matches
		// against exactly as it already ships (D23 is where that line is drawn).
		//
		// A root argument that will not resolve — deleted entity, a taxonomy this user
		// may not read — answers '' and the list stays UNNARROWED. Narrowing on a failed
		// lookup would hide every scoped field on a transient error, which is the one
		// outcome an author cannot tell apart from "this taxonomy has no fields".
		var rootArg       = rootArgFromState( state, key );
		var rootArgKind   = rootArg ? rootArg.kind : '';
		var rootArgId     = rootArg ? rootArg.id : '';
		var argScopeState = useState( '' );
		var argScope      = argScopeState[ 0 ];
		var setArgScope   = argScopeState[ 1 ];

		useEffect( function () {
			if ( '' === rootArgKind || '' === rootArgId ) {
				setArgScope( '' );
				return;
			}
			var live = true;
			wp.apiFetch( {
				path: '/bws-dynamic-tags/v1/entities?kind=' + encodeURIComponent( rootArgKind ) +
					'&mode=resolve&id=' + encodeURIComponent( rootArgId ),
			} ).then( function ( res ) {
				if ( live ) { setArgScope( ( res && res.row && res.row.scope ) || '' ); }
			} ).catch( function () {
				if ( live ) { setArgScope( '' ); }
			} );
			return function () { live = false; };
		}, [ rootArgKind, rootArgId ] );

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
		// (FW-14's FU-3): table ships without blocking on it.
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

		// FW-39 D22's narrowing, applied BEFORE the repeater auto-scope so the two
		// compose: a `{{table}}` column picker under a specific entity shows that repeater's
		// sub-fields, of that entity.
		//
		// A RECORD OF ANOTHER KIND IS NEVER OFFERED, scoped or not — the predicate is
		// `narrowToScope()`'s and stated there. Observed here: a selected term is no longer
		// offered post-kind unscoped fields, a selected post no longer term-kind ones, and
		// the picker stops offering a field the render cannot reach.
		//
		// THE SLUG LIST IS ONE LONG, and it is the entity's OWN scope: the taxonomy a
		// specific term belongs to, the post type a specific post is. An argument that will
		// not resolve answers '' above, which short-circuits to the unnarrowed list rather
		// than to an empty one.
		var scopedRecords = useMemo( function () {
			if ( '' === argScope ) { return allRecords; }
			return narrowToScope( allRecords, rootArgKind, [ argScope ] );
		}, [ allRecords, argScope, rootArgKind ] );

		// The chain's tail, read once and asked two questions — which post types a `refs`
		// argument reaches (here), and which container a `rows` argument names (the
		// Location preset further down). Both are the tail's to answer, and the read sits
		// up here because the first of them narrows the pool the second reads.
		var tail = chainTail( state, key );

		// FW-13's refs-tail narrowing, applied ON TOP of D22's rather than instead of it.
		// The two answer different questions off different positions — D22 narrows by the
		// entity a ROOT names, this by the post types a `refs` step can LAND on — and a
		// chain cannot be in both states at once anyway, since a chain carrying a step has
		// no root argument to resolve. Composed rather than branched so that stays an
		// observation about today's grammar and not something the code depends on.
		//
		// THE KIND IS `post` BECAUSE THE SLUGS ARE POST TYPES, not because `refs` produces
		// `post`. A post-type slug is a subtype of kind `post` and of nothing else, so
		// handing this list to `narrowToScope()` under any other kind would be matching
		// slugs across kinds — the thing that predicate exists to refuse.
		//
		// AN UNRESTRICTED FIELD NARROWS NO FURTHER, and neither does one the discovery
		// never saw. Both answer [], and both keep every post type — loose, and honest,
		// because the type genuinely varies per target.
		var refTypes = useMemo( function () {
			return ( tail && 'refs' === tail.slug ) ? refTailTypes( allRecords, tail.arg ) : [];
		}, [ allRecords, tail ] );

		// THE KIND GATE IS UNCONDITIONAL; only the TYPE narrowing is conditional. `refs`
		// produces `post` whatever field it steps through — the engine forces it — so a
		// term or site record is unreachable through this tail either way, and which of
		// the two arms below runs says nothing about that. Gating in one arm only left the
		// other offering 16 unreadable fields one click away, behind a Location preset that
		// is a starting VIEW and widens back; the preset was doing a POOL's job.
		//
		// The kind still arrives as a literal here rather than off the vocabulary, which is
		// the narrow form of the rule: binding the pool to whatever kind the chain resolves
		// to, for every step type and not just this one, is FW-13's Open item — it has to
		// stand down for a declaring root whose argument will not resolve (§F13.7), and
		// that is three rules in conversation rather than this one.
		var refScoped = useMemo( function () {
			if ( ! tail || 'refs' !== tail.slug ) { return scopedRecords; }
			if ( refTypes.length ) { return narrowToScope( scopedRecords, 'post', refTypes ); }
			return scopedRecords.filter( function ( rec ) { return 'post' === rec.kind; } );
		}, [ scopedRecords, refTypes, tail ] );

		var records = useMemo( function () {
			if ( ! scopeToRepeater ) { return refScoped; }
			var scoped = refScoped.filter( function ( rec ) {
				return rec.repeaterKeys && rec.repeaterKeys.indexOf( scopeRepeaterKey ) !== -1;
			} );
			// If the repeater key matched NO discovered sub-fields (an unregistered /
			// free-typed repeater, or a non-repeater key), do NOT collapse to an empty
			// list — that would strand the author with no picker and no way back. Fall
			// through to the full pool; free-text still commits any sub-field name.
			return scoped.length ? scoped : refScoped;
		}, [ refScoped, scopeToRepeater, scopeRepeaterKey ] );

		var locationOptions = useMemo( function () {
			return buildLocationOptions( records );
		}, [ records ] );

		var typeOptions = useMemo( function () {
			return buildTypeOptions( records );
		}, [ records ] );

		// Effective location: explicit override, else the preset path, else All.
		//
		// TWO PRESETS, most-specific first. A chain ending on a repeater names the exact
		// home of every field the read can reach, so it beats the sibling token's KIND,
		// which is that same home three segments shallower. Either way this is a starting
		// view and not a lock — the selector stays visible and widens back to All.
		var preset       = presetKind( state, key );
		var rowPath      = containerRowPath( records, tail ? tail.arg : '' );
		// Only use a preset path that actually exists in the options — fields of that kind,
		// or children of that container, were discovered. Otherwise fall back: a repeater
		// nobody discovered sub-fields for still leaves the sibling token's kind to say
		// something, and a kind with no fields at all falls through to All.
		function locExists( p ) {
			return locationOptions.some( function ( o ) { return o.value === p; } );
		}
		//
		// A DECLARING ROOT presets the LABEL and not the FILTER. Its scope narrowing (D22)
		// already answers which fields are readable off that entity, and it answers with a
		// rule the kind filter cannot see: an UNSCOPED group stays offered, so the narrowed
		// pool holds records whose kind root the filter would drop (§F13.2 is that rule).
		// Two narrowings derived from one token, and the finer one wins — the coarser must
		// not silently overrule it, least of all on a selection that failed to resolve,
		// where narrowing to nothing and "this entity has no fields" look identical
		// (§F13.7). The kind still reaches the LABEL, which describes the read without
		// claiming anything about the list.
		//
		// THE TEST IS THE ROOT, NOT ITS ARGUMENT. Gating on a RESOLVED argument instead made
		// the filter LOOSEN as the author supplied information — an empty Term source preset
		// "Term fields" (nothing to defer to yet), and choosing a term dropped it back to
		// "All detected fields". One source, one answer, whichever state it is in.
		var isDeclaringRoot = tail && ( window.bwsRootArgKinds || {} )[ tail.slug ];
		var presetPath   = ( rowPath && locExists( rowPath ) )
			? rowPath
			: ( ( preset && ! isDeclaringRoot ) ? kindRootLabel( preset ) : ALL_LOC );
		var activeLoc    = locOverride !== null ? locOverride : ( locExists( presetPath ) ? presetPath : ALL_LOC );

		// Effective type: explicit override, else the option's typeDefault (e.g. the
		// {{table}} tag-level `key` pre-scopes to 'repeater' so the picker opens showing
		// only repeater fields), else All. Only apply the default if that type was
		// actually discovered (mirrors presetExists) — otherwise the picker would open on
		// an empty list. The two filter SelectControls stay visible either way, so the
		// author can widen to All or pick another type; this is a starting view, not a
		// lock. Orthogonal to props.scope ('row'), which HIDES the filters and narrows to
		// one repeater's sub-fields. FW-53 owns that OTHER axis.
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
			// "<Group> Field" (e.g. "Client Details Field"). Group names are ACF
			// author-supplied, so a simple concat reads correctly across locales.
			var base = groupLbl
				? groupLbl + ' ' + __( 'Field', 'generateblocks' )
				: kindLabel( kindFromLocation( activeLoc ) || preset );
			// "KEY" IS WHAT MAKES THIS A CONTROL LABEL RATHER THAN A RESTATEMENT. Every
			// static label this replaces ends in it ("Meta/Option Field Key"), and the
			// dynamic path used to drop it — which put the word-for-word string
			// "Meta/Option Field" directly under the `use` select's own VALUE of the same
			// name, one as a chosen option and one as the next control's label. The noun
			// is appended HERE, once, rather than carried in `kindLabel()` and again in the
			// group branch, because both name the FIELD and neither names the control.
			base = base + ' ' + __( 'Key', 'generateblocks' );
			label = props.labelPrefix ? props.labelPrefix + ' ' + base : base;
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
