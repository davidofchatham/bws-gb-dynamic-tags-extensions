<?php
/**
 * Standalone unit harness for the field-discovery pure transforms in
 * includes/rest/field-discovery.php.
 *
 * No WordPress required. The discovery derivation pipeline is pure PHP: the
 * helpers take ACF-shaped arrays as arguments and return plain arrays. Only the
 * live orchestrator (bws_field_discovery_collect) calls acf_get_* / core WP, and
 * it is NOT exercised here — the harness drives the pure helpers directly.
 *
 * SCOPE — pure transforms only (SPEC.md §V5/§V6/§V7/§V8):
 *   bws_field_discovery_derive_kind_scope()   location -> kind + candidate scope
 *   bws_field_discovery_flatten_fields()      sub-field recurse (group/repeater/flex)
 *   bws_field_discovery_group_entry()         group array -> envelope entry
 *   bws_field_discovery_registered_meta_group() register_meta map -> group entry
 *   bws_field_discovery_dedupe()              within-(kind,scope) dedupe, ACF wins
 *   bws_field_discovery_scopes_equal()        scope-equality merge gate (keep-both on differing reach)
 *   bws_field_key_disallowed()                shared DISALLOWED_KEYS predicate (case-sensitive)
 *   bws_field_discovery_filter_disallowed()   DISALLOWED_KEYS gate
 *
 * EXCLUDED — REST route wiring, permission callback, live ACF/collect(), and the
 * JS control (no JS build/test pipeline in repo; covered by the manual matrix
 * tools/test/field-selector-test-matrix.md).
 *
 * Run:
 *   php tools/test/field-discovery-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

// field-discovery.php top-level is ABSPATH-guarded and makes no WP calls at parse
// (const + function defs only), so a bare define + the shims below suffice.
define( 'ABSPATH', __DIR__ );

// __() shim — identity. Only used inside collect() group titles, not exercised
// by the pure-helper tests, but defined so require does not warn if reached.
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = 'default' ) { return $s; }
}

// Fake GB security class for the DISALLOWED_KEYS gate test. Two blocked keys.
if ( ! class_exists( 'GenerateBlocks_Dynamic_Tag_Security' ) ) {
	class GenerateBlocks_Dynamic_Tag_Security {
		const DISALLOWED_KEYS = array( 'user_pass', 'session_tokens' );
	}
}

// Provides bws_field_key_disallowed() — the shared DISALLOWED_KEYS predicate the
// discovery gate calls. Load-safe standalone (only guards + function defs at top
// level); its WP-dependent bodies are never invoked by these pure tests.
require __DIR__ . '/../../includes/helpers/field-helpers.php';
require __DIR__ . '/../../includes/rest/field-discovery.php';

$failures = 0;
$count    = 0;

/**
 * Assert two values are deeply equal (order-sensitive).
 */
function assert_eq( $label, $expected, $actual ) {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  PASS  {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL  {$label}\n";
	echo "        expected: " . var_export( $expected, true ) . "\n";
	echo "        actual:   " . var_export( $actual, true ) . "\n";
}

/**
 * Assert a boolean is true.
 */
function assert_true( $label, $actual ) {
	assert_eq( $label, true, $actual );
}

// -----------------------------------------------------------------------------
echo "\n== derive_kind_scope ==\n";

// post_type location -> kind post, scope = the slugs.
$loc_post = array(
	array(
		array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ),
	),
);
assert_eq( 'post_type -> post kind', 'post', bws_field_discovery_derive_kind_scope( $loc_post )['kind'] );
assert_eq( 'post_type -> scope [event]', array( 'event' ), bws_field_discovery_derive_kind_scope( $loc_post )['scope'] );

// taxonomy location -> kind term.
$loc_term = array(
	array(
		array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'event_category' ),
	),
);
assert_eq( 'taxonomy -> term kind', 'term', bws_field_discovery_derive_kind_scope( $loc_term )['kind'] );
assert_eq( 'taxonomy -> scope [event_category]', array( 'event_category' ), bws_field_discovery_derive_kind_scope( $loc_term )['scope'] );

// options_page location -> kind site.
$loc_site = array(
	array(
		array( 'param' => 'options_page', 'operator' => '==', 'value' => 'theme-settings' ),
	),
);
assert_eq( 'options_page -> site kind', 'site', bws_field_discovery_derive_kind_scope( $loc_site )['kind'] );

// Multiple OR-groups of post_type -> scope collects both, deduped.
$loc_multi = array(
	array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ) ),
	array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
	array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ) ),
);
assert_eq( 'multi post_type -> deduped scope', array( 'event', 'page' ), bws_field_discovery_derive_kind_scope( $loc_multi )['scope'] );

// Kind-less location (page_template only) -> defaults to post, empty scope.
$loc_none = array(
	array( array( 'param' => 'page_template', 'operator' => '==', 'value' => 'full-width.php' ) ),
);
assert_eq( 'no kind param -> post default', 'post', bws_field_discovery_derive_kind_scope( $loc_none )['kind'] );
assert_eq( 'no kind param -> empty scope', array(), bws_field_discovery_derive_kind_scope( $loc_none )['scope'] );

// post_type + taxonomy in same location -> post wins (priority), scope from post.
$loc_mixed = array(
	array(
		array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'cat' ),
		array( 'param' => 'post_type', 'operator' => '==', 'value' => 'event' ),
	),
);
assert_eq( 'mixed params -> post priority', 'post', bws_field_discovery_derive_kind_scope( $loc_mixed )['kind'] );
assert_eq( 'mixed params -> post scope only', array( 'event' ), bws_field_discovery_derive_kind_scope( $loc_mixed )['scope'] );

// != operator still fixes kind + contributes scope value.
$loc_neq = array(
	array( array( 'param' => 'post_type', 'operator' => '!=', 'value' => 'page' ) ),
);
assert_eq( '!= operator fixes kind', 'post', bws_field_discovery_derive_kind_scope( $loc_neq )['kind'] );
assert_eq( '!= operator collects value', array( 'page' ), bws_field_discovery_derive_kind_scope( $loc_neq )['scope'] );

// -----------------------------------------------------------------------------
echo "\n== flatten_fields ==\n";

// Top-level fields -> bare name, context field.
$flat_top = bws_field_discovery_flatten_fields( array(
	array( 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text' ),
	array( 'name' => 'event_date', 'label' => 'Event Date', 'type' => 'date_picker', 'return_format' => 'Ymd' ),
) );
assert_eq( 'top-level count', 2, count( $flat_top ) );
assert_eq( 'top-level name = bare', 'subtitle', $flat_top[0]['name'] );
assert_eq( 'top-level context = field', 'field', $flat_top[0]['name'] === 'subtitle' ? $flat_top[0]['context_hint'] : 'X' );
assert_eq( 'top-level return_format carried', 'Ymd', $flat_top[1]['return_format'] );

// Missing label falls back to name.
$flat_nolabel = bws_field_discovery_flatten_fields( array(
	array( 'name' => 'raw_key', 'type' => 'text' ),
) );
assert_eq( 'missing label -> name', 'raw_key', $flat_nolabel[0]['label'] );

// GROUP child -> composite parent_child key, context field.
$flat_group = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'venue', 'label' => 'Venue', 'type' => 'group',
		'sub_fields' => array(
			array( 'name' => 'city', 'label' => 'City', 'type' => 'text' ),
		),
	),
) );
// [0] = the group itself, [1] = child composite.
assert_eq( 'group child count (parent+child)', 2, count( $flat_group ) );
assert_eq( 'group child composite name', 'venue_city', $flat_group[1]['name'] );
assert_eq( 'group child context = field', 'field', $flat_group[1]['context_hint'] );
assert_eq( 'group child breadcrumb', 'Venue', $flat_group[1]['parent_path'] );

// REPEATER child -> bare name, context row.
$flat_rep = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'sessions', 'label' => 'Sessions', 'type' => 'repeater',
		'sub_fields' => array(
			array( 'name' => 'session_title', 'label' => 'Title', 'type' => 'text' ),
		),
	),
) );
assert_eq( 'repeater child bare name', 'session_title', $flat_rep[1]['name'] );
assert_eq( 'repeater child context = row', 'row', $flat_rep[1]['context_hint'] );
// #12: the child carries the owning repeater's key (machine-readable scope handle,
// not a parsed breadcrumb) so a picker can narrow to exactly this repeater.
assert_eq( 'repeater child repeater_key = owning repeater', 'sessions', $flat_rep[1]['repeater_key'] );
assert_eq( 'repeater field itself has empty repeater_key', '', $flat_rep[0]['repeater_key'] );

// FLEXIBLE content layout child -> bare name, context row.
$flat_flex = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'blocks', 'label' => 'Blocks', 'type' => 'flexible_content',
		'layouts' => array(
			array(
				'label' => 'Hero',
				'sub_fields' => array(
					array( 'name' => 'headline', 'label' => 'Headline', 'type' => 'text' ),
				),
			),
		),
	),
) );
assert_eq( 'flex layout child bare name', 'headline', $flat_flex[1]['name'] );
assert_eq( 'flex layout child context = row', 'row', $flat_flex[1]['context_hint'] );
// F1: layout path nests under the flex field's OWN label (Blocks), then the layout
// (Hero) — so two flex fields sharing a layout name stay under distinct paths.
assert_eq( 'flex layout child breadcrumb includes flex field', 'Blocks › Hero', $flat_flex[1]['parent_path'] );
// #12: flex children scope to the FLEX field's key (its rows are the layouts).
assert_eq( 'flex layout child repeater_key = flex field', 'blocks', $flat_flex[1]['repeater_key'] );

// Nested GROUP inside GROUP -> double composite.
$flat_nested = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'a', 'label' => 'A', 'type' => 'group',
		'sub_fields' => array(
			array(
				'name' => 'b', 'label' => 'B', 'type' => 'group',
				'sub_fields' => array(
					array( 'name' => 'c', 'label' => 'C', 'type' => 'text' ),
				),
			),
		),
	),
) );
$names = array_column( $flat_nested, 'name' );
assert_true( 'nested group composite a_b_c present', in_array( 'a_b_c', $names, true ) );

// #12: nested REPEATER — an inner repeater's grandchildren keep the INNER key
// (recursion stamps the direct parent first; the outer branch does not overwrite).
$flat_nested_rep = bws_field_discovery_flatten_fields( array(
	array(
		'name' => 'outer', 'label' => 'Outer', 'type' => 'repeater',
		'sub_fields' => array(
			array(
				'name' => 'inner', 'label' => 'Inner', 'type' => 'repeater',
				'sub_fields' => array(
					array( 'name' => 'cell', 'label' => 'Cell', 'type' => 'text' ),
				),
			),
		),
	),
) );
$by_name = array();
foreach ( $flat_nested_rep as $r ) { $by_name[ $r['name'] ] = $r; }
assert_eq( 'inner repeater scopes to outer', 'outer', $by_name['inner']['repeater_key'] );
assert_eq( 'grandchild cell scopes to INNER (not overwritten by outer)', 'inner', $by_name['cell']['repeater_key'] );

// -----------------------------------------------------------------------------
echo "\n== field configuration note (#96) ==\n";

// The note is a pure function of the field DEFINITION plus the resolved-source kind —
// no value read, which is the whole reason it works in a Pattern with no post in scope
// (V5). These rows drive it with synthetic ACF field arrays, exactly as the flatten
// rows above do.
//
// EVERY EXPECTATION IS THE LITERAL SENTENCE an author reads. Asserting a case NAME
// (or a settings tuple) would pass against any wording, and the wording IS the
// deliverable here: it was arrived at by iteration and is recorded in
// docs/tag-reference.md §Field configuration note.

$note_native = function ( array $extra ) {
	// ACF native bidirectional needs BOTH the toggle and a target list — the same gate
	// acf_update_bidirectional_values() applies before writing anything back.
	return array_merge(
		array( 'bidirectional' => 1, 'bidirectional_target' => array( 'field_partner' ) ),
		$extra
	);
};
$note_acfe = function ( array $extra ) {
	// ACF Extended keeps its own pair under its own key, and gates on both halves too
	// (acfe_bidirectional::is_enabled() / get_related()).
	return array_merge(
		array( 'acfe_bidirectional' => array(
			'acfe_bidirectional_enabled' => true,
			'acfe_bidirectional_related' => array( 'field_partner' ),
		) ),
		$extra
	);
};

$CASE1 = 'Bidirectional field with a configured limit of 3. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF.';
$CASE2 = 'Bidirectional field with no configured limit. Edits to its bidirectional target field(s) on other posts, terms, or users can add entries.';
$CASE3 = 'Field with a configured limit of 3. The limit is enforced only when this field is edited directly, using ACF.';
$CASE4 = 'Bidirectional field configured as single-entry. Edits to its bidirectional target field(s) on other posts, terms, or users can add more entries; the limit is enforced only when this field is edited directly, using ACF.';
$CASE5 = 'Bidirectional field configured as single-entry. Edits to its bidirectional target field(s) on other posts, terms, or users replace an existing entry.';
$CASE6 = 'Field configured as single-entry. The limit is enforced only when this field is edited directly, using ACF.';
$CONSEQ = 'The first stored entry will be the only result while this field is single-entry; all entries will be results if it is reconfigured as multiple-entry.';

// Case 1 — relationship, configured limit, bidirectional.
assert_eq(
	'case 1: relationship + limit + bidirectional',
	array( array( 'text' => $CASE1, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'relationship', 'max' => 3 ) ) )
);

// Case 2 — relationship, bidirectional, no configured limit. `max` 0 is ACF's own
// default for the field, so an unset limit and an explicit 0 are one state.
assert_eq(
	'case 2: relationship + bidirectional, max unset',
	array( array( 'text' => $CASE2, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'relationship' ) ) )
);
assert_eq(
	'case 2: an explicit max of 0 is the same as unset',
	array( array( 'text' => $CASE2, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'relationship', 'max' => 0 ) ) )
);

// Case 3 — relationship, configured limit, NOT bidirectional. No reciprocal-write
// sentence, because nothing writes back; the unenforcement statement stands alone.
assert_eq(
	'case 3: relationship + limit, not bidirectional',
	array( array( 'text' => $CASE3, 'emph' => false ) ),
	bws_field_discovery_field_note( array( 'type' => 'relationship', 'max' => 3 ) )
);

// Case 4 — single-entry post object, ACF NATIVE bidirectional: accumulate-and-hide.
assert_eq(
	'case 4: single-entry post object + native bidirectional',
	array(
		array( 'text' => $CASE4, 'emph' => false ),
		array( 'text' => $CONSEQ, 'emph' => true, 'consequence' => true ),
	),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'post_object' ) ) )
);

// Case 5 — single-entry post object, ACF EXTENDED bidirectional: replace-and-discard.
// NO unenforcement clause and NO consequence clause: that implementation honours the
// single-value setting at write, so nothing accumulates and nothing hides. The
// asymmetry with case 4 is the finding, not an omission.
assert_eq(
	'case 5: single-entry post object + ACF Extended bidirectional',
	array( array( 'text' => $CASE5, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_acfe( array( 'type' => 'post_object' ) ) )
);

// Case 6 — single-entry post object, not bidirectional. Carries the SAME closing clause
// as case 4: hiding-then-resurrecting follows from the format-time collapse, not from
// bidirectionality, so the two cases differ only by the sentence naming the writer.
assert_eq(
	'case 6: single-entry post object, not bidirectional',
	array(
		array( 'text' => $CASE6, 'emph' => false ),
		array( 'text' => $CONSEQ, 'emph' => true, 'consequence' => true ),
	),
	bws_field_discovery_field_note( array( 'type' => 'post_object' ) )
);

// BOTH implementations enabled -> the NATIVE description, because silent retention is
// the harder condition to diagnose.
assert_eq(
	'both bidirectional flavours enabled -> native wording',
	array(
		array( 'text' => $CASE4, 'emph' => false ),
		array( 'text' => $CONSEQ, 'emph' => true, 'consequence' => true ),
	),
	bws_field_discovery_field_note( $note_acfe( $note_native( array( 'type' => 'post_object' ) ) ) )
);

// SEGMENT SPLIT. A case with no emphasis emits ONE segment, never an empty second one —
// the shape reason the note is a list rather than a string plus a trailing field.
assert_eq( 'no-emphasis case emits a single segment', 1, count( bws_field_discovery_field_note( $note_native( array( 'type' => 'relationship', 'max' => 3 ) ) ) ) );
assert_eq( 'emphasis case emits two', 2, count( bws_field_discovery_field_note( array( 'type' => 'post_object' ) ) ) );
$emph_case = bws_field_discovery_field_note( array( 'type' => 'post_object' ) );
assert_eq( 'emphasis falls on the CLOSING segment only', array( false, true ), array_column( $emph_case, 'emph' ) );

// OPTIONS-PAGE SUPPRESSION. ACF resolves valid bidirectional targets by object type and
// has no case for options, so such a field never receives a reciprocal write even with
// the setting ticked — it takes the corresponding NON-bidirectional case instead.
assert_eq(
	'options-page relationship takes case 3, not case 1',
	array( array( 'text' => $CASE3, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'relationship', 'max' => 3 ) ), 'site' )
);
assert_eq(
	'options-page single-entry post object takes case 6, not case 4',
	array(
		array( 'text' => $CASE6, 'emph' => false ),
		array( 'text' => $CONSEQ, 'emph' => true, 'consequence' => true ),
	),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'post_object' ) ), 'site' )
);
assert_eq(
	'options-page bidirectional relationship with no limit falls SILENT',
	null,
	bws_field_discovery_field_note( $note_native( array( 'type' => 'relationship' ) ), 'site' )
);
// A TERM field is a valid bidirectional target, so suppression must be `site`-only.
assert_eq(
	'term-kind relationship keeps the bidirectional wording',
	array( array( 'text' => $CASE1, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'relationship', 'max' => 3 ) ), 'term' )
);

// TAXONOMY and USER fields are valid bidirectional targets with no limit setting of
// their own, so they take case 2 unchanged — by rule, not by a case each.
assert_eq(
	'taxonomy field + bidirectional -> case 2',
	array( array( 'text' => $CASE2, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'taxonomy' ) ) )
);
assert_eq(
	'user field + bidirectional -> case 2',
	array( array( 'text' => $CASE2, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_acfe( array( 'type' => 'user' ) ) )
);
// A MULTIPLE-entry post object reaches case 2 by the same rule: it is a bidirectional
// target with no limit setting, and there is no format-time collapse to warn about.
assert_eq(
	'multiple-entry post object + bidirectional -> case 2',
	array( array( 'text' => $CASE2, 'emph' => false ) ),
	bws_field_discovery_field_note( $note_native( array( 'type' => 'post_object', 'multiple' => 1 ) ) )
);

// SILENCE, and silence is information: the presence of a note means there is something
// to know, so every shape with nothing to say must emit nothing at all.
assert_eq( 'plain text field -> no note', null, bws_field_discovery_field_note( array( 'type' => 'text', 'max' => 3 ) ) );
assert_eq( 'image field -> no note', null, bws_field_discovery_field_note( $note_native( array( 'type' => 'image' ) ) ) );
assert_eq( 'typeless entry (registered meta) -> no note', null, bws_field_discovery_field_note( array( 'name' => 'plain_key' ) ) );
assert_eq( 'relationship, no limit, not bidirectional -> no note', null, bws_field_discovery_field_note( array( 'type' => 'relationship' ) ) );
assert_eq( 'multiple-entry post object, not bidirectional -> no note', null, bws_field_discovery_field_note( array( 'type' => 'post_object', 'multiple' => 1 ) ) );
assert_eq( 'taxonomy field, not bidirectional -> no note', null, bws_field_discovery_field_note( array( 'type' => 'taxonomy' ) ) );

// A HALF-CONFIGURED bidirectional setting is not bidirectional. Both plugins gate their
// own writer on the toggle AND the target list, so a toggle with no target never writes
// back — reading only the toggle would warn about a reciprocal write that cannot occur.
assert_eq(
	'native toggle with no target is not bidirectional',
	array( array( 'text' => $CASE3, 'emph' => false ) ),
	bws_field_discovery_field_note( array( 'type' => 'relationship', 'max' => 3, 'bidirectional' => 1, 'bidirectional_target' => array() ) )
);
assert_eq(
	'ACF Extended toggle with no related field is not bidirectional',
	null,
	bws_field_discovery_field_note( array(
		'type'               => 'relationship',
		'acfe_bidirectional' => array( 'acfe_bidirectional_enabled' => true, 'acfe_bidirectional_related' => array() ),
	) )
);

// The note reaches the ENVELOPE through the flatten, stamped per entry and threaded
// with the group's kind — the entry shape the editor control reads.
$flat_note = bws_field_discovery_flatten_fields(
	array(
		$note_native( array( 'name' => 'partners', 'label' => 'Partners', 'type' => 'relationship', 'max' => 3 ) ),
		array( 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text' ),
	)
);
assert_eq( 'flatten stamps the note on the entry', array( array( 'text' => $CASE1, 'emph' => false ) ), $flat_note[0]['note'] );
assert_eq( 'flatten stamps null where there is nothing to say', null, $flat_note[1]['note'] );
// Kind threads through to the note, which is how an options-page group's fields are
// suppressed at the one place the kind is known.
$flat_site = bws_field_discovery_flatten_fields(
	array( $note_native( array( 'name' => 'partners', 'label' => 'Partners', 'type' => 'relationship', 'max' => 3 ) ) ),
	'',
	'',
	'site'
);
assert_eq( 'flatten threads the kind into the note', array( array( 'text' => $CASE3, 'emph' => false ) ), $flat_site[0]['note'] );
// SUB-FIELDS take the enclosing group's kind too — the recursion must carry it, or a
// relationship inside a group on an options page would warn about a write that cannot
// happen.
$flat_sub = bws_field_discovery_flatten_fields(
	array(
		array(
			'name' => 'links', 'label' => 'Links', 'type' => 'group',
			'sub_fields' => array( $note_native( array( 'name' => 'partners', 'label' => 'Partners', 'type' => 'relationship', 'max' => 3 ) ) ),
		),
	),
	'',
	'',
	'site'
);
assert_eq( 'group sub-field inherits the kind for its note', array( array( 'text' => $CASE3, 'emph' => false ) ), $flat_sub[1]['note'] );
// Registered meta carries no ACF definition, so its entries state a null note rather
// than omitting the key — one entry shape across the whole envelope.
$rmg_note = bws_field_discovery_registered_meta_group( array( 'featured' => array() ), 'post', '', 'Registered post meta' );
assert_true( 'registered-meta entries carry the note key', array_key_exists( 'note', $rmg_note['fields'][0] ) );
assert_eq( 'registered-meta note is null', null, $rmg_note['fields'][0]['note'] );

// -----------------------------------------------------------------------------
echo "\n== group_entry ==\n";

$entry = bws_field_discovery_group_entry(
	array( 'title' => 'Event Details', 'location' => $loc_post ),
	$flat_top
);
assert_eq( 'group_entry title', 'Event Details', $entry['title'] ?? $entry['group_title'] );
assert_eq( 'group_entry kind', 'post', $entry['kind'] );
assert_eq( 'group_entry scope', array( 'event' ), $entry['scope'] );
assert_eq( 'group_entry source = acf', 'acf', $entry['source'] );
assert_eq( 'group_entry field count', 2, count( $entry['fields'] ) );

// -----------------------------------------------------------------------------
echo "\n== registered_meta_group ==\n";

$rmg = bws_field_discovery_registered_meta_group(
	array(
		'featured'  => array( 'description' => 'Featured flag' ),
		'raw_only'  => array(),
	),
	'post',
	'event',
	'Registered post meta'
);
assert_eq( 'reg group source', 'registered', $rmg['source'] );
assert_eq( 'reg group kind', 'post', $rmg['kind'] );
assert_eq( 'reg group scope', array( 'event' ), $rmg['scope'] );
assert_eq( 'reg field label from description', 'Featured flag', $rmg['fields'][0]['label'] );
assert_eq( 'reg field label fallback to key', 'raw_only', $rmg['fields'][1]['label'] );
assert_eq( 'empty map -> null', null, bws_field_discovery_registered_meta_group( array(), 'post', '', 'X' ) );

// -----------------------------------------------------------------------------
echo "\n== scopes_equal ==\n";

assert_true( 'both empty (both global) are equal', bws_field_discovery_scopes_equal( array(), array() ) );
assert_true( 'same single slug equal', bws_field_discovery_scopes_equal( array( 'event' ), array( 'event' ) ) );
assert_true( 'same set diff order equal', bws_field_discovery_scopes_equal( array( 'event', 'page' ), array( 'page', 'event' ) ) );
assert_eq( 'global vs scoped NOT equal', false, bws_field_discovery_scopes_equal( array(), array( 'event' ) ) );
assert_eq( 'partial overlap NOT equal', false, bws_field_discovery_scopes_equal( array( 'event', 'page' ), array( 'event' ) ) );
assert_eq( 'disjoint NOT equal', false, bws_field_discovery_scopes_equal( array( 'event' ), array( 'page' ) ) );

// -----------------------------------------------------------------------------
echo "\n== dedupe (within kind,scope; ACF wins) ==\n";

// Same name, same kind, overlapping scope: ACF entry wins over registered.
// Registered group listed FIRST, ACF SECOND -> ACF must displace registered.
$env_dupe = array(
	'post' => array(
		array(
			'group_title' => 'Registered post meta', 'kind' => 'post',
			'scope' => array( 'event' ), 'source' => 'registered',
			'fields' => array(
				array( 'name' => 'event_date', 'label' => 'event_date', 'type' => '', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
			),
		),
		array(
			'group_title' => 'Event Details', 'kind' => 'post',
			'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array(
				array( 'name' => 'event_date', 'label' => 'Event Date', 'type' => 'date_picker', 'return_format' => 'Ymd', 'context_hint' => 'field', 'parent_path' => '' ),
			),
		),
	),
	'term' => array(),
	'site' => array(),
);
$deduped = bws_field_discovery_dedupe( $env_dupe );
// Registered group emptied + pruned; only the ACF group remains.
assert_eq( 'ACF-wins: one group left', 1, count( $deduped['post'] ) );
assert_eq( 'ACF-wins: kept group is ACF', 'acf', $deduped['post'][0]['source'] );
assert_eq( 'ACF-wins: kept label is ACF', 'Event Date', $deduped['post'][0]['fields'][0]['label'] );

// Same name across DIFFERENT kinds is NOT merged.
$env_cross = array(
	'post' => array(
		array( 'group_title' => 'P', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'date', 'label' => 'Post Date', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(
		array( 'group_title' => 'T', 'kind' => 'term', 'scope' => array( 'cat' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'date', 'label' => 'Term Date', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'site' => array(),
);
$dc = bws_field_discovery_dedupe( $env_cross );
assert_eq( 'cross-kind kept in post', 1, count( $dc['post'][0]['fields'] ) );
assert_eq( 'cross-kind kept in term', 1, count( $dc['term'][0]['fields'] ) );

// Disjoint scope, same name, same kind: NOT merged (different buckets).
$env_disjoint = array(
	'post' => array(
		array( 'group_title' => 'A', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'x', 'label' => 'X-event', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
		array( 'group_title' => 'B', 'kind' => 'post', 'scope' => array( 'page' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'x', 'label' => 'X-page', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(), 'site' => array(),
);
$dd = bws_field_discovery_dedupe( $env_disjoint );
assert_eq( 'disjoint scope: both groups kept', 2, count( $dd['post'] ) );

// ACF-vs-ACF same bucket, same name: BOTH kept (distinct fields). Server dedupe
// only collapses ACF-vs-registered; two ACF fields sharing a bare name (e.g. a
// `description` sub-field in two different repeaters) are distinct and must both
// survive — the client merges/labels them by (kind, key, label).
$env_acfacf = array(
	'post' => array(
		array( 'group_title' => 'First', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'y', 'label' => 'First Y', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
		array( 'group_title' => 'Second', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'y', 'label' => 'Second Y', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(), 'site' => array(),
);
$da       = bws_field_discovery_dedupe( $env_acfacf );
$da_names = array();
foreach ( $da['post'] as $g ) {
	foreach ( $g['fields'] as $f ) { $da_names[] = $f['label']; }
}
sort( $da_names );
assert_eq( 'ACF-vs-ACF same name: both fields kept', array( 'First Y', 'Second Y' ), $da_names );

// Sub-field collision: the same bare key in two different repeaters (both ACF,
// same scope) — both must survive server dedupe (the Features>description bug).
$env_subdupe = array(
	'post' => array(
		array( 'group_title' => 'Benefit Details', 'kind' => 'post', 'scope' => array( 'benefit' ), 'source' => 'acf',
			'fields' => array(
				array( 'name' => 'description', 'label' => 'Description', 'type' => 'text', 'return_format' => null, 'context_hint' => 'row', 'parent_path' => 'Highlights' ),
				array( 'name' => 'description', 'label' => 'Description', 'type' => 'text', 'return_format' => null, 'context_hint' => 'row', 'parent_path' => 'Features' ),
			) ),
	),
	'term' => array(), 'site' => array(),
);
$ds    = bws_field_discovery_dedupe( $env_subdupe );
$paths = array();
foreach ( $ds['post'] as $g ) {
	foreach ( $g['fields'] as $f ) { $paths[] = $f['parent_path']; }
}
sort( $paths );
assert_eq( 'sub-field bare-name collision: both parents survive', array( 'Features', 'Highlights' ), $paths );

// B4-regression: a GLOBAL registered-meta key (scope []) and a SCOPED ACF field
// (scope ['event']) of the same name have DIFFERENT reach, so both are kept. The
// global key must survive on post types the scoped ACF field does not apply to;
// merging by overlap (old behavior) dropped it envelope-wide.
$env_globalreg = array(
	'post' => array(
		// ACF appended first (mirrors collect() ordering), scoped to one post type.
		array( 'group_title' => 'Event Details', 'kind' => 'post', 'scope' => array( 'event' ), 'source' => 'acf',
			'fields' => array( array( 'name' => 'subtitle', 'label' => 'Event Subtitle', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
		// Global registered meta (empty scope = all post types).
		array( 'group_title' => 'Registered post meta', 'kind' => 'post', 'scope' => array(), 'source' => 'registered',
			'fields' => array( array( 'name' => 'subtitle', 'label' => 'subtitle', 'type' => '', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(), 'site' => array(),
);
$dg     = bws_field_discovery_dedupe( $env_globalreg );
$dg_src = array();
foreach ( $dg['post'] as $g ) {
	foreach ( $g['fields'] as $f ) { $dg_src[] = $g['source']; }
}
sort( $dg_src );
assert_eq( 'global-registered survives a scoped ACF of same name (keep both)', array( 'acf', 'registered' ), $dg_src );

// Same name, SAME scope (both global): ACF still wins (truly one field).
$env_bothglobal = array(
	'post' => array(
		array( 'group_title' => 'ACF Global', 'kind' => 'post', 'scope' => array(), 'source' => 'acf',
			'fields' => array( array( 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
		array( 'group_title' => 'Registered post meta', 'kind' => 'post', 'scope' => array(), 'source' => 'registered',
			'fields' => array( array( 'name' => 'subtitle', 'label' => 'subtitle', 'type' => '', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ) ) ),
	),
	'term' => array(), 'site' => array(),
);
$dbg = bws_field_discovery_dedupe( $env_bothglobal );
assert_eq( 'equal-scope global: ACF wins, one group left', 1, count( $dbg['post'] ) );
assert_eq( 'equal-scope global: kept is ACF', 'acf', $dbg['post'][0]['source'] );

// -----------------------------------------------------------------------------
echo "\n== bws_field_key_disallowed (shared gate, J2) ==\n";

assert_true( 'disallowed key -> true', bws_field_key_disallowed( 'user_pass' ) );
assert_eq( 'allowed key -> false', false, bws_field_key_disallowed( 'subtitle' ) );
assert_eq( 'underscore-protected -> false (resolver allows)', false, bws_field_key_disallowed( '_piecal_start_date' ) );
// Case-sensitivity lock: WP meta keys are case-sensitive; the gate must NOT fold
// case (a folded gate would wrongly refuse a legitimately-distinct cased key and
// desync offer-vs-resolve). Regression guard for the case-fold defect fixed twice.
assert_eq( 'case-variant of disallowed -> false (exact match only)', false, bws_field_key_disallowed( 'User_Pass' ) );

// -----------------------------------------------------------------------------
echo "\n== filter_disallowed (V6) ==\n";

$env_dis = array(
	'post' => array(
		array( 'group_title' => 'G', 'kind' => 'post', 'scope' => array(), 'source' => 'acf',
			'fields' => array(
				array( 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
				array( 'name' => 'user_pass', 'label' => 'Pass', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
				array( 'name' => '_piecal_start_date', 'label' => 'Start', 'type' => 'text', 'return_format' => null, 'context_hint' => 'field', 'parent_path' => '' ),
			),
		),
	),
	'term' => array(), 'site' => array(),
);
$filtered = bws_field_discovery_filter_disallowed( $env_dis );
$fnames   = array_column( $filtered['post'][0]['fields'], 'name' );
assert_true( 'DISALLOWED user_pass removed', ! in_array( 'user_pass', $fnames, true ) );
assert_true( 'allowed subtitle kept', in_array( 'subtitle', $fnames, true ) );
assert_true( 'underscore-protected _piecal kept (resolver allows)', in_array( '_piecal_start_date', $fnames, true ) );

// -----------------------------------------------------------------------------
echo "\n== envelope shape ==\n";

$empty = bws_field_discovery_filter_disallowed( array( 'post' => array(), 'term' => array(), 'site' => array() ) );
assert_true( 'envelope has post key', array_key_exists( 'post', $empty ) );
assert_true( 'envelope has term key', array_key_exists( 'term', $empty ) );
assert_true( 'envelope has site key', array_key_exists( 'site', $empty ) );

// -----------------------------------------------------------------------------
echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} of {$count} assertions failed.\n";
	exit( 1 );
}
echo "OK: all {$count} assertions passed.\n";
exit( 0 );
