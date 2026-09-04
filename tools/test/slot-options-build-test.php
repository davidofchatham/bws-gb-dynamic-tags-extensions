<?php
/**
 * Standalone unit harness for bws_build_slot_traversal_options() in
 * includes/tags/base-shared.php — the #26 slot-option derive (V7/V9 auto-gate).
 *
 * Pure fn of (slot ordinal, base src options, base traversal options). No WP
 * beyond __() (shimmed). Asserts the DERIVED slot src/ref/srcTermIn defs against
 * the byte-exact pre-derive inline shapes (V7), EXCEPT the two intentional ref
 * drift-fixes (placeholder related_post→related_posts, fuller help) — those are
 * asserted at their NEW base values (C4/V7 carve-out, V10).
 *
 * Covers:
 *   V1  — slot src derives from base, `site` filtered, slot ≥2 prepends `same`.
 *   V5  — `_strip_default` persists on derived src.
 *   V6  — `site` ∉ derived src options.
 *   V2  — ref/srcTermIn derive from base traversal, re-keyed show_if.
 *   V10 — only `N: ` label/pickLabel prefix overlaid; body/placeholder/help = base.
 *   V7  — current/ref slot JSON identical to pre-derive EXCEPT ref drift-fix.
 *
 * Run:  php tools/test/slot-options-build-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
// The registry bootstrap FIRST: the chain-root section drives the shipped registry, and
// its filter route needs hooks that actually dispatch. The no-op shims below then skip
// what it has already defined.
require_once __DIR__ . '/lib-source-registry.php';
foreach ( array( 'add_action', 'add_filter', 'do_action', 'apply_filters' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return func_num_args() > 1 ? func_get_arg(1) : null; }" );
	}
}

require __DIR__ . '/../../includes/tags/base-shared.php';
// The fold config's `flatAxes` derives through slot-fold.php's single owner of the
// tag-level subtraction; without it the builder's function_exists guard silently ships an
// empty list, which is exactly the drift the field exists to prevent.
require __DIR__ . '/../../includes/helpers/slot-fold.php';
// The step vocabulary's `arg`/`accepts`/`produces` facts are DERIVED at registration
// from the compiler seam and the engine's input-kind allowlist (traversal-pipeline.php)
// — the whole point being that the editor cannot hold a second copy. Without both, the
// builder's defined() guards ship label-only records and every step is offered
// everywhere, silently.
require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';

$failures = 0;
$count    = 0;

function assert_same( $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . json_encode( $expected ) . "\n";
	echo "       actual:   " . json_encode( $actual ) . "\n";
}

$base_src  = bws_base_source_option();
$base_trav = bws_base_traversal_options();

echo "bws_build_slot_traversal_options\n";

// ============ Slot 1 ============
$s1 = bws_build_slot_traversal_options( 1, $base_src, $base_trav );

// V1/V6: src options = base minus 'site', NO 'same' prepend (slot 1).
assert_same(
	'slot1 src options = current,ref (site filtered, no same)',
	array(
		array( 'value' => 'current', 'label' => 'Current' ),
		array( 'value' => 'ref',     'label' => 'In Reference/Relational Field' ),
	),
	$s1['src']['options']
);
// V5: _strip_default persists.
assert_same( 'slot1 src _strip_default true', true, $s1['src']['_strip_default'] ?? null );
// V10: label gets "1: " prefix.
assert_same( 'slot1 src label "1: Source"', '1: Source', $s1['src']['label'] );
assert_same( 'slot1 src type select', 'select', $s1['src']['type'] );

// V7 ref — derived from base verbatim (label prefixed). type is bws-field-combo:
// the ref key uses the field-discovery combobox control (unscoped for src:ref;
// SPEC V3), not a plain text input.
assert_same(
	'slot1 ref derived (label prefixed, base body/help/placeholder, show_if bare src:ref)',
	array(
		'type'        => 'bws-field-combo',
		'label'       => '1: Relationship Field Key',
		'help'        => 'ACF relationship or post object field key.',
		'placeholder' => 'related_posts',
		'show_if'     => array( 'src' => 'ref' ),
	),
	$s1['ref']
);

// srcTermIn — derived: new not:site guard, "1: " label+pickLabel prefix.
assert_same(
	'slot1 srcTermIn derived (not:site guard, prefixed labels, base help)',
	array(
		'type'      => 'bws-term-hop',
		'label'     => '1: Get from taxonomy term?',
		'help'      => 'Field is in a taxonomy term on this source.',
		'pickLabel' => '1: Taxonomy',
		'pickHelp'  => 'Pick the taxonomy.',
		'show_if'   => array( 'src' => 'not:site' ),
	),
	$s1['srcTermIn']
);

// ============ Slot 2 ============
$s2 = bws_build_slot_traversal_options( 2, $base_src, $base_trav );

// V1: slot ≥2 prepends 'same' `same` row.
assert_same(
	'slot2 src options = same,current,ref',
	array(
		array( 'value' => 'same',    'label' => 'Same as Previous Source' ),
		array( 'value' => 'current', 'label' => 'Current' ),
		array( 'value' => 'ref',     'label' => 'In Reference/Relational Field' ),
	),
	$s2['src']['options']
);
assert_same( 'slot2 src label "2: Source"', '2: Source', $s2['src']['label'] );

// V2: ref show_if re-keyed to 2-src.
assert_same( 'slot2 ref show_if re-keyed 2-src:ref', array( '2-src' => 'ref' ), $s2['ref']['show_if'] );
assert_same( 'slot2 ref label "2: ..."', '2: Relationship Field Key', $s2['ref']['label'] );

// V2: srcTermIn show_if re-keyed to 2-src.
assert_same( 'slot2 srcTermIn show_if re-keyed 2-src:not:site', array( '2-src' => 'not:site' ), $s2['srcTermIn']['show_if'] );
assert_same( 'slot2 srcTermIn pickLabel "2: Taxonomy"', '2: Taxonomy', $s2['srcTermIn']['pickLabel'] );

// V6 explicit: no 'site' value anywhere in derived src options (any slot).
$has_site = false;
foreach ( array( $s1, $s2 ) as $s ) {
	foreach ( $s['src']['options'] as $o ) {
		if ( 'site' === ( $o['value'] ?? '' ) ) { $has_site = true; }
	}
}
assert_same( 'V6 site absent from derived src options', false, $has_site );

// ============ bws_filter_site_from_src — rooting-modifier src filter (#37) ============
// term_/view_ tags omit `site`; the base tag keeps it. Same gate, source level (I4).
echo "\nbws_filter_site_from_src (#37)\n";

// Base source option still offers site (base tag is the unrooted site read).
$base_has_site = false;
foreach ( $base_src['src']['options'] as $o ) {
	if ( 'site' === ( $o['value'] ?? '' ) ) { $base_has_site = true; }
}
assert_same( 'base src still offers site', true, $base_has_site );

// Filtered (rooting-modifier) src drops site, keeps current + ref in order.
$mod_src = bws_filter_site_from_src( $base_src );
assert_same(
	'rooting-modifier src = current,ref (site filtered)',
	array(
		array( 'value' => 'current', 'label' => 'Current' ),
		array( 'value' => 'ref',     'label' => 'In Reference/Relational Field' ),
	),
	$mod_src['src']['options']
);

// Idempotent + non-mutating: re-filtering is a no-op, original untouched.
assert_same( 'filter idempotent', $mod_src['src']['options'], bws_filter_site_from_src( $mod_src )['src']['options'] );
assert_same( 'source_opt not mutated in place', true, in_array( 'site', array_column( $base_src['src']['options'], 'value' ), true ) );

// _strip_default and other src props survive the filter.
assert_same( 'filter preserves _strip_default', true, $mod_src['src']['_strip_default'] ?? null );

echo "bws_pick_src_values (allowlist — complement of the site blocklist)\n";

// Keep current+ref (the {{call}} case): canonical rows from base, reordered to $keep.
$picked = bws_pick_src_values( bws_base_source_option(), array( 'current', 'ref' ) );
assert_same(
	'pick current,ref → exactly those rows, base labels',
	array(
		array( 'value' => 'current', 'label' => 'Current' ),
		array( 'value' => 'ref',     'label' => 'In Reference/Relational Field' ),
	),
	$picked['src']['options']
);
// _strip_default + label survive the pick.
assert_same( 'pick preserves _strip_default', true, $picked['src']['_strip_default'] ?? null );
assert_same( 'pick preserves label', 'Source', $picked['src']['label'] );

// Order follows $keep, NOT base order (ref before current here).
$rev = bws_pick_src_values( bws_base_source_option(), array( 'ref', 'current' ) );
assert_same(
	'pick order follows $keep',
	array( 'ref', 'current' ),
	array_column( $rev['src']['options'], 'value' )
);

// A $keep value with no base row is silently skipped (no fabricated row).
$missing = bws_pick_src_values( bws_base_source_option(), array( 'current', 'nope' ) );
assert_same(
	'unknown keep value skipped',
	array( 'current' ),
	array_column( $missing['src']['options'], 'value' )
);

// Allowlist EXCLUDES a value the blocklist would leak: pick current,ref drops site
// WITHOUT naming it — the closed-set guarantee {{call}} relies on (VC2).
assert_same(
	'site excluded by allowlist without naming it',
	false,
	in_array( 'site', array_column( $picked['src']['options'], 'value' ), true )
);

// Source not mutated in place.
assert_same( 'pick does not mutate source', true, in_array( 'site', array_column( bws_base_source_option()['src']['options'], 'value' ), true ) );

// ============================================================
// bws_get_text_field_options() — the text FIELD LEAF (build step 3)
// ============================================================

echo "\nbws_get_text_field_options (leaf)\n";

$leaf = bws_get_text_field_options();

// Group-pure: SOURCE-group keys only. A format/fallback key here means the leaf
// grew into a composer and callers can no longer place it by group (FW-52).
assert_same( 'leaf returns exactly use+key', array( 'use', 'key' ), array_keys( $leaf ) );

// Enum + control shape (the four-copy definition, now one).
assert_same( 'leaf use options = key,title', array( 'key', 'title' ), array_column( $leaf['use']['options'], 'value' ) );
assert_same( 'leaf use label "Text Field"', 'Text Field', $leaf['use']['label'] );
assert_same( 'leaf use _strip_default true', true, $leaf['use']['_strip_default'] );
assert_same( 'leaf key control = bws-field-combo', 'bws-field-combo', $leaf['key']['type'] );
assert_same( 'leaf key dynamicLabel true', true, $leaf['key']['dynamicLabel'] );

// Leaf contract: NO show_if. Base overlays use:not:title; the template encodes the
// same fact via try_use_no_key_values. Ship it on the leaf and both callers inherit
// a condition one of them must then strip.
assert_same( 'leaf use carries no show_if', false, isset( $leaf['use']['show_if'] ) );
assert_same( 'leaf key carries no show_if', false, isset( $leaf['key']['show_if'] ) );

// ============================================================
// bws_build_slot_read_options() — the READ twin (build step 4)
// ============================================================

echo "\nbws_build_slot_read_options\n";

// --- Slot 1: base enum verbatim, no `same` row regardless of $allow_same. ---
$r1 = bws_build_slot_read_options( 1, $leaf['use'], true );
assert_same( 'slot1 read options = key,title (no same)', array( 'key', 'title' ), array_column( $r1['options'], 'value' ) );
assert_same( 'slot1 read label "1: Text Field"', '1: Text Field', $r1['label'] );
assert_same( 'slot1 read type select', 'select', $r1['type'] );
assert_same( 'slot1 read _strip_default derived from base', true, $r1['_strip_default'] );

// --- Slot ≥2 SELECTING (try_): `same` row prepended, shipped string. ---
$r2 = bws_build_slot_read_options( 2, $leaf['use'], true );
assert_same( 'slot2 selecting prepends same', array( 'same', 'key', 'title' ), array_column( $r2['options'], 'value' ) );
assert_same( 'slot2 same row label', 'Same as Previous Field', $r2['options'][0]['label'] );
assert_same( 'slot2 read label "2: Text Field"', '2: Text Field', $r2['label'] );

// --- Slot ≥2 COMBINING ({{join}}): NO `same` row. Byte-for-byte the literal the
// twin replaced — join's rejoin is a behavioral no-op. Not-built-yet (per-slot
// handlers), NOT degenerate: flipping the flag is the whole change when they ship.
$j2 = bws_build_slot_read_options( 2, $leaf['use'], false );
assert_same(
	'slot2 combining == pre-twin join literal',
	array(
		'type'           => 'select',
		'label'          => '2: Text Field',
		'options'        => array(
			array( 'value' => 'key',   'label' => 'Meta/Option Field' ),
			array( 'value' => 'title', 'label' => 'Title/Name' ),
		),
		'_strip_default' => true,
	),
	$j2
);

// --- Label NOUN comes off the base def, never hand-authored per container. ---
$img = bws_build_slot_read_options( 3, array( 'label' => 'Image Field', 'options' => array( array( 'value' => 'url', 'label' => 'URL' ) ) ), true );
assert_same( 'read label noun derives from base def', '3: Image Field', $img['label'] );
assert_same( 'read _strip_default false when base omits it', false, $img['_strip_default'] );

// --- No options = nothing to select: empty def, so the caller registers no key. ---
assert_same( 'empty base read → empty def', array(), bws_build_slot_read_options( 2, array(), true ) );

// ===========================================================================
// bws_build_fold_slot_options() — the FOLDED registration (FW-56/57)
// ===========================================================================
//
// One option key per slot, each carrying the `fold` config the repeater control reads.
// What matters here is that every enum in that config is DERIVED: the control
// hand-authors no vocabulary, so a value it renders must be traceable to a shipped
// builder. These asserts compare against the builders themselves rather than against
// transcribed rows, so a base-list change can never leave the fold behind.

echo "\nbws_build_fold_slot_options\n";

$text  = bws_get_text_field_options();
$joins = bws_build_fold_slot_options(
	array(
		'container'       => 'join',
		'combining'       => true,
		'max'             => 3,
		'min'             => 2,
		'base_read'       => $text['use'],
		'base_key'        => $text['key'],
		'allow_site'      => true,
		'allow_same_read' => false,
		'steps'            => array( 'terms' ),
		'noun'            => 'field',
	)
);

// ONE registered noun, BOTH surfaces. The Add button reads `noun` and the panel header
// reads the derived `label` — a container registers no second string, because two
// strings for one unit is how "Add attempt" ended up over a panel headed "Slot A".
assert_same( 'noun ships to the control verbatim', 'field', $joins['A']['fold']['noun'] );
assert_same( 'header derives from the noun + the slot ORDINAL', 'Field B', $joins['B']['label'] );

// The registered key IS the wire spelling, so nothing downstream translates: a slot's
// option key, its `{{join A:…}}` token and the control's `props.optionKey` are one string.
assert_same( 'one option key per slot, spelled as the ordinal', array( 'A', 'B', 'C' ), array_keys( $joins ) );
// THE TRAP THIS SPELLING RETIRED, kept as a regression: while slot keys were all digits
// PHP stored them as INTs, and array_merge RENUMBERS integer keys — folding them into a
// leading option group slid every slot down one (`1`..`5` → `0`..`4`), inventing a slot 0
// the grammar has no ordinal for and dropping the top slot. Capitals are ordinary string
// keys, so array_merge now preserves them. (Consumers still append by key; the habit is
// free and this assertion is what would notice the hazard coming back.)
assert_same(
	'array_merge preserves the slot keys (the digit-era renumbering is gone)',
	array( 'as', 'A', 'B', 'C' ),
	array_keys( array_merge( array( 'as' => array() ), $joins ) )
);
assert_same( 'slot key declares the fold control type', 'bws-slot-fold', $joins['B']['type'] );
// The label carries the slot's ORDINAL SPELLING, not its number. That is the one place
// the capital key prefix becomes legible: an author reading `B:` on the wire finds the
// panel headed "Field B", and its {{join}} format token is `%B`.
assert_same( 'per-slot label carries the ordinal spelling, not the number', 'Field B', $joins['B']['label'] );

$fold = $joins['A']['fold'];

// Source enum: DERIVED through the slot twin, so the `site` arm and the `same` row
// are the shipped ones. Slot 1 has no `same` row; slot ≥2 does. The twin's `ref` row
// is respelled to the wire slug `refs` (#70): that row is the relationship STEP's UI,
// and with the slugMap retired the picker's value must BE the stored step's slug.
$respell_ref = static function ( array $rows ): array {
	foreach ( $rows as &$row ) {
		if ( 'ref' === ( $row['value'] ?? '' ) ) {
			$row['value'] = 'refs';
		}
	}
	return $rows;
};
assert_same(
	'srcRows derive from the slot twin at slot 1, `ref` respelled to the wire slug',
	$respell_ref( bws_build_slot_traversal_options( 1, $base_src, $base_trav, true )['src']['options'] ),
	$fold['srcRows']
);
assert_same(
	'srcRowsWithSame derive from the slot twin at slot 2 (same-row included)',
	$respell_ref( bws_build_slot_traversal_options( 2, $base_src, $base_trav, true )['src']['options'] ),
	$fold['srcRowsWithSame']
);
assert_same( 'the respelled row keeps its label (only the value moves)', true, in_array( 'refs', array_column( $fold['srcRows'], 'value' ), true ) );
assert_same( '...and no legacy `ref` value survives in the enum', false, in_array( 'ref', array_column( $fold['srcRows'], 'value' ), true ) );
assert_same( 'site arm present when allowed', true, in_array( 'site', array_column( $fold['srcRows'], 'value' ), true ) );

// Read enum: the twin's rows, with a COMBINING container's explicit unset row in front
// — absent there means unconfigured (slot skipped), which is not what the first enum
// row means. `allow_same_read` false ⇒ no `same` row, matching the resolver.
assert_same(
	'readRows = unset row + the read twin rows (combining)',
	array_merge(
		array( array( 'value' => '', 'label' => 'Select…' ) ),
		bws_build_slot_read_options( 1, $text['use'], false )['options']
	),
	$fold['readRows']
);
assert_same( 'no read `same` row while allow_same_read is false', false, in_array( 'same', array_column( $fold['readRowsWithSame'], 'value' ), true ) );
assert_same( 'readLabel is the base read noun, not a container copy', 'Text Field', $fold['readLabel'] );

// The OFFER is a CAPABILITY list: only what the container's resolver can express,
// shipped as an ordered slug array — the builders' `steps` parameter as-is, not rows.
// Labels live on the shared vocabulary record, declared once (#70).
assert_same( 'offer carries only the requested steps, as wire slugs', array( 'terms' ), $fold['offer'] );
assert_same( 'step label declared once on the vocabulary, step-shaped', 'In Taxonomy Term', $fold['steps']['terms']['label'] );

// Picker configs come off the base definitions (label/help/placeholder), so the
// repeater's field pickers read exactly like the flat ones did.
assert_same( 'refOption label derives from base traversal', $base_trav['ref']['label'], $fold['refOption']['label'] );
assert_same( 'keyOption label derives from the text leaf', $text['key']['label'], $fold['keyOption']['label'] );
assert_same( 'keyOption keeps the dynamic-label flag', true, $fold['keyOption']['dynamicLabel'] );

// LEGACY AXES — the per-slot keys the editor may fold and delete. Join excludes nothing:
// its `limit` was a SLOT axis (one per slot, threaded into that slot's resolve), the
// opposite of try_'s tag-level limit. Shipping the list is what stops the control keeping
// its own.
assert_same(
	'flatAxes = every axis when the container excludes none',
	array( 'src', 'ref', 'srcTermIn', 'use', 'key', 'limit' ),
	$fold['flatAxes']
);

// RETIRED SRC TOKENS — the slots the mount migrator must DECLINE rather than fold (#56).
// Asserted against the constant rather than a literal, because the property is that the
// two migration paths read ONE list: the converter's guard reads the constant directly
// and the mount path can only see what registration ships. A missing field does not
// error — the JS falls back to an empty list, silently folds a retired token into a
// chain root that resolves to nothing, and stores the tag differently from the converter.
// That is exactly the divergence the fold-migration twin exists to catch, and it would be
// INVISIBLE there, because the twin's corpus supplies its own conf.
assert_same(
	'retiredSrc ships to the editor, and IS the constant (not a copy of it)',
	BWS_FOLD_RETIRED_SRC_TOKENS,
	$fold['retiredSrc']
);

// The root an absent chain SPELLS — the control displays it rather than rendering a
// picker whose value matches no row, which paints the first row while believing nothing
// is selected (so the row on screen cannot be picked, and Add step never appears). Two
// properties, and the second is the one worth asserting: it must BE the enum's own first
// row, not a literal beside it. A hand-typed 'current' here would keep working right up
// until the source list's lead row changed, and then display a selection the author
// cannot reproduce from the list.
assert_same(
	'defaultRoot ships, and IS the source enum\'s first row',
	$fold['srcRows'][0]['value'],
	$fold['defaultRoot']
);

// THE CONSOLIDATED STEP RECORD (#70) — one `steps[slug]` per WIRE slug, every fact
// derived, none re-typed. A copy would not error; it would drift, and the symptom is
// an author writing a chain that renders nothing with no control saying why.
assert_same(
	'steps is keyed by WIRE slug, in the vocabulary\'s own order',
	array( 'refs', 'terms', 'rows' ),
	array_keys( $fold['steps'] )
);
// `accepts` IS the engine's refusal list. Since V9 that list is itself keyed by the
// wire slug, so this reads as a tautology — it is not: it pins that the SHIPPED config
// is the engine constant and not a re-typed copy that agrees today. `terms` is the
// sharp case: post only.
assert_same(
	'steps[*].accepts derives from the engine\'s own refusal list',
	BWS_TRAVERSAL_STEP_INPUT_KINDS['terms'],
	$fold['steps']['terms']['accepts']
);
// `arg` is the compiler seam's value, shipped for the first time — comparing two
// slugs' `arg` is what decides whether a slug switch keeps the field.
assert_same(
	'steps[*].arg IS the compiler seam\'s map',
	BWS_FOLD_STEP_TYPES,
	array_combine( array_keys( $fold['steps'] ), array_column( $fold['steps'], 'arg' ) )
);
assert_same(
	'steps[*].produces IS the produced-kind map',
	BWS_FOLD_STEP_KINDS,
	array_combine( array_keys( $fold['steps'] ), array_column( $fold['steps'], 'produces' ) )
);
// Only `site` has a parse-time root kind. Every other root resolves at render, so
// the editor must offer everything there rather than guess whether `current` is a post
// or a term — a guess would hide the taxonomy step on every ordinary tag. `roots` is
// its own top-level key: a fact about roots, not about steps.
assert_same(
	'roots is its own key and only `site` has a parse-time kind',
	BWS_FOLD_PARSE_TIME_ROOT_KINDS,
	$fold['roots']
);
// ...and the map the EDITOR filters by is the same one the RENDER path dispatches on.
// The two read it in opposite directions (render answers "what did this resolve to",
// the editor asks "what may follow this"), so a divergence would not error anywhere —
// the editor would simply offer, or withhold, a step against a kind the renderer does
// not agree the root has. Driven through the shipped resolution rather than compared to
// the constant twice, so the assertion covers the `?? 'render_time'` fallback too.
assert_same(
	'the parse-time root map agrees with what a root-only chain RESOLVES to',
	array( 'site', 'render_time' ),
	array(
		bws_fold_chain_resolution( array( array( 'slug' => 'site' ) ) )['kind'],
		bws_fold_chain_resolution( array( array( 'slug' => 'current' ) ) )['kind'],
	)
);

// Container facts the control and the renderer BOTH read.
assert_same( 'combining flag is carried', true, $fold['combining'] );
assert_same( 'floor + ceiling are carried', array( 2, 3 ), array( $fold['min'], $fold['max'] ) );
assert_same( 'noun is carried for the add affordance', 'field', $fold['noun'] );

// A SELECTING container: no unset read row (absent there IS the stripped default), and
// the `same` row appears when the caller allows it.
$sel = bws_build_fold_slot_options(
	array(
		'container'       => 'try',
		'combining'       => false,
		'max'             => 2,
		'base_read'       => $text['use'],
		'base_key'        => $text['key'],
		'allow_site'      => false,
		'allow_same_read' => true,
		// try_'s bare `limit` is the TAG-level limit for every slot, so it is not a slot axis.
		'tag_level'       => array( 'limit' ),
		'noun'            => 'attempt',
	)
);
$sel_fold = $sel['A']['fold'];
assert_same( 'selecting header derives from ITS noun', 'Attempt A', $sel['A']['label'] );
assert_same(
	'selecting readRows are the twin rows with NO unset row',
	bws_build_slot_read_options( 1, $text['use'], false )['options'],
	$sel_fold['readRows']
);
assert_same( 'selecting slot ≥2 offers the read `same` row', true, in_array( 'same', array_column( $sel_fold['readRowsWithSame'], 'value' ), true ) );
assert_same( 'site arm filtered when not allowed', false, in_array( 'site', array_column( $sel_fold['srcRows'], 'value' ), true ) );
assert_same( 'combining flag reflects the container', false, $sel_fold['combining'] );
assert_same(
	'selecting flatAxes drop the tag-level limit',
	array( 'src', 'ref', 'srcTermIn', 'use', 'key' ),
	$sel_fold['flatAxes']
);

// The other two READ SHAPES a selecting container comes in. Both are described by the
// derived config alone — the control picks its rendering from these fields, never from
// the container name, so the shapes must be distinguishable HERE.
//
// KEY-ONLY (try_email / try_phone: a per-slot key with no `use` enum). No read rows, a
// key definition present: the control renders the picker alone, and an empty field is
// how that slot says "carry over".
$key_only = bws_build_fold_slot_options(
	array(
		'container'    => 'try',
		'combining'    => false,
		'per_slot_use' => false,
		'max'          => 2,
		'base_read'    => array(),                       // no `use` axis exists
		'base_key'     => $text['key'],
		'allow_site'   => true,
		'tag_level'    => array( 'use', 'limit' ),
	)
);
$key_fold = $key_only['A']['fold'];
assert_same( 'key-only: no read enum', array(), $key_fold['readRows'] );
assert_same( 'key-only: no read enum at slot ≥2 either', array(), $key_fold['readRowsWithSame'] );
assert_same( 'key-only: the key picker is still configured', $text['key']['label'], $key_fold['keyOption']['label'] );
assert_same( 'key-only: perSlotUse false reaches the control', false, $key_fold['perSlotUse'] );
assert_same( 'key-only: readLabel is empty, so the control falls back to the key noun', '', $key_fold['readLabel'] );
assert_same(
	'key-only: flatAxes keep `key` and drop the tag-level `use`',
	array( 'src', 'ref', 'srcTermIn', 'key' ),
	$key_fold['flatAxes']
);

// NO READ AXIS AT ALL (try_title / try_permalink / try_datetime_*): the read is a
// TAG-level option, so a slot is a source chain and nothing else.
$chain_only = bws_build_fold_slot_options(
	array(
		'container'    => 'try',
		'combining'    => false,
		'per_slot_use' => false,
		'max'          => 2,
		'base_read'    => array(),
		'base_key'     => array(),
		'allow_site'   => true,
		'tag_level'    => array( 'use', 'key', 'limit' ),
	)
);
$chain_fold = $chain_only['A']['fold'];
assert_same( 'chain-only: no read enum', array(), $chain_fold['readRows'] );
assert_same( 'chain-only: no key picker either', null, $chain_fold['keyOption'] ?? null );
assert_same( 'chain-only: the source enum is still whole', true, count( $chain_fold['srcRows'] ) > 1 );
// The shape where the axis list MATTERS MOST: `key` is this template's own datetime field
// option. Folding it into slot 1 would duplicate it inside the slot value and delete the
// tag-level key the resolver actually reads.
assert_same(
	'chain-only: flatAxes are the chain axes alone',
	array( 'src', 'ref', 'srcTermIn' ),
	$chain_fold['flatAxes']
);

// ── The REGISTRATION pass ────────────────────────────────────────────────────
//
// bws_prepare_registration_options() is the single pass every BWS registration goes
// through, so two things ride it: the visual group stamp, and dropping the flat source
// options a chain control has taken over. Both are gated — the stamp on our registrations
// (a name-keyed map applied in JS would also wrap GB core tags' `key`/`source`), the drop
// on the control TYPE (a `term_`/`table`/`call` source is a plain select and still
// authors the flat pair).
//
// The drop is asserted DERIVED, not by name: it reads the chain option's own `flatAxes`,
// the same list the control deletes by, so a change to what the chain absorbs cannot
// leave a stray control behind it.

require_once __DIR__ . '/../../includes/helpers/registration-helpers.php';
// The link group is asserted against its SHIPPED definitions, not a local literal — the
// three keys and their reveal conditions are what decide when the box appears.
require_once __DIR__ . '/../../includes/helpers/link-helpers.php';

$chain_tag = bws_prepare_registration_options(
	array_merge( bws_build_src_chain_option(), bws_base_traversal_options(), bws_get_text_field_options() )
);
assert_same( 'chain tag: the absorbed `ref` control is gone', false, isset( $chain_tag['ref'] ) );
assert_same( 'chain tag: the absorbed `srcTermIn` control is gone', false, isset( $chain_tag['srcTermIn'] ) );
assert_same( 'chain tag: the source itself survives', 'bws-src-chain', $chain_tag['src']['type'] );
assert_same( 'chain tag: unabsorbed options survive', true, isset( $chain_tag['use'], $chain_tag['key'] ) );

// ── The BASE control's defaultRoot answers TWO questions that must not diverge ───────
//
// It is a DISPLAY value, so it has to be a row the picker paints: a SelectControl whose
// value matches no option paints its FIRST row while believing nothing is selected, so
// the row on screen cannot be picked (selecting it fires no change event) and `+ Add
// step`, needing a step to append to, never appears. That is why it derives from the ROOT
// rows and not from the enum they are filtered out of — `ref` is a step, not a root, so
// the two lists differ by a row.
//
// It also STANDS FOR what an absent `src` means, and that is decided by `_strip_default`,
// which blanks the UNFILTERED enum's first row. So the two derives must agree, and they
// agree today only because `current` leads both. Asserted rather than assumed: if `ref`
// ever led the enum, the editor would display `current` as the root while the wire's
// absence meant `ref` — a silent lie about a stored tag, and a bug in the enum's ordering
// rather than something the fold config should absorb.
$base_chain = bws_build_src_chain_option();
$base_fold  = $base_chain['src']['fold'];
assert_same(
	'base chain: defaultRoot IS a row the picker paints',
	$base_fold['srcRows'][0]['value'],
	$base_fold['defaultRoot']
);
assert_same(
	'base chain: ...and IS the row `_strip_default` blanks',
	$base_chain['src']['options'][0]['value'],
	$base_fold['defaultRoot']
);
// The base tag OFFERS both steps (`rows` deliberately absent — no base-tag arm
// consumes a meta_row), and the vocabulary record it ships is BYTE-IDENTICAL to the
// slot builder's: container-invariant by construction, both consuming one helper (#70).
assert_same( 'base chain: offer = refs,terms in offer order', array( 'refs', 'terms' ), $base_fold['offer'] );
assert_same( 'the step vocabulary is container-invariant (base ≡ slot record)', $fold['steps'], $base_fold['steps'] );
assert_same( '...and so is roots', $fold['roots'], $base_fold['roots'] );
assert_same( '...and so is the per-step limit control (#95)', $fold['limitOption'], $base_fold['limitOption'] );

// THE PER-STEP LIMIT CONTROL'S VOCABULARY (#95). Registration is the only observer of
// these strings: the rendering harness asserts the control renders WHAT IT IS GIVEN
// (sentinels), which is the half that rots — a value the control needs but registration
// omits fails silently there, exactly as a missing `flatAxes` or `retiredSrc` would.
//
// BOTH helps, and the pair is the point. One string would force the control to compose
// the per-input clause, which is a rule; the rule already exists once, as
// bws_fold_chain_fanning_steps() and its grammar twin.
// REFRAMED 1.18.0 (the determinism reversal, ADR 0007): the number counts ITEMS
// READ, never results shown — an empty field keeps its place — so no string may
// promise output. Wording pending user prose review; update here WITH the copy.
assert_same( 'limitOption: labelled for what it COUNTS — items read, not results shown', 'Limit items read', $fold['limitOption']['label'] );
// PER-KIND LABELS (1.18.0, second pass): the generic string above is the FALLBACK, and
// every shipped slug names what it produces instead. Asserted per slug rather than as a
// set, because the failure worth catching is one slug losing its noun and silently
// wearing the generic label — which reads fine and is wrong.
assert_same( 'refs: the limit names what the step PRODUCES', 'Limit Posts Read', $fold['steps']['refs']['limitLabel'] );
assert_same( 'terms: likewise', 'Limit Terms Read', $fold['steps']['terms']['limitLabel'] );
assert_same( 'rows: likewise, in the author\'s noun and not the engine\'s meta_row', 'Limit Repeater Rows Read', $fold['steps']['rows']['limitLabel'] );
assert_same( 'limitOption: the placeholder names the unlimited VALUE', '0 (all)', $fold['limitOption']['placeholder'] );
assert_same(
	'limitOption: the plain help, for a step with nothing fanning above it',
	'How many items this step reads, in stored order. An item with an empty field keeps its place. Leave blank for all.',
	$fold['limitOption']['help']
);
assert_same(
	'limitOption: the per-input help, for a step below a fanning one',
	'How many items this step reads for each previous-step item, in stored order. An item with an empty field keeps its place. Leave blank for all.',
	$fold['limitOption']['helpFanning']
);
// THE COLLISION, asserted absent. The draft label "Limit per source" named a source three
// rows under a `Source` control meaning something else; it never shipped (#95), so this
// pins a hazard rather than guarding a migration. Every string is swept, not just the
// label: putting "per source" back into the HELP would re-state the same confusion where
// no doc row would catch it either.
assert_same(
	'limitOption: no string names a SOURCE — the control bounds results',
	true,
	false === strpos( implode( ' ', $fold['limitOption'] ), 'per source' )
);

$flat_tag = bws_prepare_registration_options(
	array_merge( bws_base_source_option(), bws_base_traversal_options() )
);
assert_same( 'plain-select tag: `ref` KEPT', true, isset( $flat_tag['ref'] ) );
assert_same( 'plain-select tag: `srcTermIn` KEPT', true, isset( $flat_tag['srcTermIn'] ) );

// The stamp. `key` leads its group as well as `use`, because on {{email}}/{{phone}}/
// {{table}} the field key IS the whole read and a lone-member opt-out would leave the
// simplest reads as the only ones with no field group.
assert_same( 'stamp: src leads the source group', 'source', $chain_tag['src']['_group'] );
assert_same( 'stamp: src is a lead', true, ! empty( $chain_tag['src']['_group_lead'] ) );
assert_same( 'stamp: use is a field lead', true, ! empty( $chain_tag['use']['_group_lead'] ) );
assert_same( 'stamp: key is a field lead too', true, ! empty( $chain_tag['key']['_group_lead'] ) );
$ungrouped = bws_prepare_registration_options( array( 'fallback' => array( 'type' => 'text' ) ) );
assert_same( 'stamp: an unmapped option gets no group', false, isset( $ungrouped['fallback']['_group'] ) );

// The FORMAT group holds one lead and one deliberate non-lead, and the pair is the whole
// mechanism: `as` is a COMPOSITE (return type + image size from one key), so on {{image}}
// it is already a group of two and must keep its box when the size control hides — the
// wrapper cannot see inside a composite. `format` alone is {{join}}'s assembly template,
// one control, where a border is noise; on datetime it sits in a run with `as` and boxes
// anyway. One name-keyed map, no per-tag knowledge.
$fmt = bws_prepare_registration_options( array(
	'as'              => array( 'type' => 'bws-as-size' ),
	'format'          => array( 'type' => 'bws-format-input' ),
	'showCurrentYear' => array( 'type' => 'checkbox' ),
) );
assert_same( 'stamp: the datetime format cluster shares one group', 'format', $fmt['as']['_group'] );
assert_same( 'stamp: ...and its siblings agree', array( 'format', 'format' ), array( $fmt['format']['_group'], $fmt['showCurrentYear']['_group'] ) );
assert_same( 'stamp: `as` LEADS (a composite keeps its box when a sub-control hides)', true, ! empty( $fmt['as']['_group_lead'] ) );
assert_same(
	'stamp: `format` does NOT lead (it never stands alone once `mode` joins it)',
	array( false, false ),
	array( ! empty( $fmt['format']['_group_lead'] ), ! empty( $fmt['showCurrentYear']['_group_lead'] ) )
);

// {{join}}'s assembly pair lands in the SAME group as datetime's formatting — both answer
// "how is the value rendered". `mode` reveals exactly one of `valueSep`/`format`, so two
// members are always visible and the group boxes without a lead.
$join_fmt = bws_prepare_registration_options( array(
	'mode'     => array( 'type' => 'select' ),
	'valueSep' => array( 'type' => 'text' ),
	'format'   => array( 'type' => 'bws-format-input' ),
) );
assert_same(
	'stamp: join mode + valueSep + format share the format group',
	array( 'format', 'format', 'format' ),
	array( $join_fmt['mode']['_group'], $join_fmt['valueSep']['_group'], $join_fmt['format']['_group'] )
);

// The link group has no lead, so a tag left on "No Link" shows a bare select and the box
// appears when a link is configured. Decided by trying both (user, 2026-08-05): the link
// is the one OPTIONAL group here — a source and a field read are what every tag does, so
// those boxes stand whether or not they are configured.
$link = bws_prepare_registration_options( bws_get_link_options() );
assert_same(
	'stamp: no lead in the link group — box only once a link is configured',
	array( false, false, false ),
	array( ! empty( $link['linkTo']['_group_lead'] ), ! empty( $link['linkKey']['_group_lead'] ), ! empty( $link['newTab']['_group_lead'] ) )
);

// ── Registered chain ROOTS (#83) ─────────────────────────────────────────────────────
//
// Everything above ran with an EMPTY registry, which is what a base tag's enum looks like
// on a site with no integrator. From here the registry is live, so every enum is rebuilt
// rather than re-read: a stale row array would assert the state this section exists to
// change.
//
// The subject is REGISTRATION OUTPUT — what the enums contain — not which function
// produced it. That is why both surfaces are asserted against the SAME appender result
// instead of against transcribed rows: "offered here and absent there" is the failure
// this is written to catch, and two literals could agree with each other while disagreeing
// with the source of truth.

echo "\nRegistered chain roots (#83)\n";

// Pre-init registration — the documented Pattern B route (plugins_loaded < 20), and the
// one the registry logs as a "pre-init external".
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Offered_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Unoffered_Source() );

add_filter( 'bws_dynamic_tags_chain_roots', static function ( $roots ) {
	$roots['filterroot'] = array(
		'label'   => 'Filter Root',
		'context' => 'post',
		'resolve' => static function ( $options, $instance ) { return 5150; },
	);
	// COLLIDES with the class-route source registered above. Must be ignored, not
	// overwrite it: a plugin shipping a real source class must not have it shadowed by a
	// spec someone else declared.
	$roots['testroot'] = array(
		'label'   => 'HIJACKED',
		'resolve' => static function ( $options, $instance ) { return 1; },
	);
	// Half-formed specs. A root with no label has no row to be; one with no resolver
	// would answer nothing. Registering either would put a dead row in the dropdown.
	$roots['nolabel']   = array( 'resolve' => static function () { return 1; } );
	$roots['noresolve'] = array( 'label' => 'No Resolver' );
	// Keys that cannot BE a root token. `refs` is a chain STEP slug, so selecting such a
	// row would author wire that parses as a step off the ambient entity and never runs
	// the source; `same` is the slot carry over sentinel; the third carries grammar
	// characters that split the value. Each would be an offered root that cannot resolve
	// — the one guarantee this route exists to keep.
	$roots['refs']      = array( 'label' => 'Step Slug', 'resolve' => static function () { return 1; } );
	$roots['same']      = array( 'label' => 'Inherit Sentinel', 'resolve' => static function () { return 1; } );
	$roots['a;b,c']     = array( 'label' => 'Grammar Chars', 'resolve' => static function () { return 1; } );
	return $roots;
} );

\BWS\DynamicTags\SourceRegistry::init();

$root_values = static function ( array $rows ): array {
	return array_map( static function ( $row ) { return $row['value']; }, $rows );
};

// The appender is the single answer both surfaces take their rows from.
$appended = bws_registered_root_rows();
assert_same(
	'the appender offers the opted-in source and the filter-declared root, in registration order',
	array( 'testroot', 'filterroot' ),
	$root_values( $appended )
);
assert_same(
	'...labelled by the source\'s OWN accessor, so an integrator names their concept',
	array( 'Test Root', 'Filter Root' ),
	array_map( static function ( $row ) { return $row['label']; }, $appended )
);
// The collision rule, stated as an outcome rather than as an absence: the key resolves to
// the CLASS-route source, whose label is intact.
assert_same(
	'a colliding filter spec is IGNORED, not merged over the class route',
	'Test Root',
	\BWS\DynamicTags\SourceRegistry::get_source( 'testroot' )->get_source_label()
);
assert_same( 'a spec with no label registers nothing', null, \BWS\DynamicTags\SourceRegistry::get_source( 'nolabel' ) );
assert_same( 'a spec with no resolver registers nothing', null, \BWS\DynamicTags\SourceRegistry::get_source( 'noresolve' ) );
// An INEXPRESSIBLE key is refused at registration, not filtered out of the enum later: a
// registered-but-unofferable source would still be a source, and the point is that it can
// never be one. Asserted through the registry for that reason.
foreach ( array( 'refs', 'same', 'a;b,c' ) as $unwritable ) {
	assert_same(
		"a key that cannot BE a root token registers nothing: `{$unwritable}`",
		null,
		\BWS\DynamicTags\SourceRegistry::get_source( $unwritable )
	);
}

// A registered source that never opted in. It IS in the registry (the harness resolves it
// in traversal-pipeline-test.php) and is nowhere in an authoring surface.
assert_same(
	'a registered source that has NOT opted in is absent',
	false,
	in_array( 'quietsource', $root_values( $appended ), true )
);
// The registry keeps its dead by policy — four traversal-substitute classes retired when
// the generic relationship step subsumed them, plus the two INTERNAL keys, which would
// otherwise promote to roots that duplicate Current and collide with the planned
// pinned-entity spelling. Every one of them is a registered source right now.
foreach ( array( 'related_post', 'second_related_post', 'post_term_related_post', 'term_related_post', 'post', 'term' ) as $never ) {
	assert_same(
		"the registry's own `{$never}` stays out of the root enum",
		array( true, false ),
		array( null !== \BWS\DynamicTags\SourceRegistry::get_source( $never ), in_array( $never, $root_values( $appended ), true ) )
	);
}

// ── Both surfaces, one appender ──────────────────────────────────────────────────────
$rooted_base = bws_build_src_chain_option();
$rooted_fold = bws_build_fold_slot_options(
	array(
		'container'  => 'join',
		'max'        => 2,
		'base_read'  => $text['use'],
		'base_key'   => $text['key'],
		'allow_site' => true,
		'noun'       => 'field',
	)
)['A']['fold'];

assert_same(
	'BASE root enum = built-ins then the appended roots',
	array( 'current', 'site', 'testroot', 'filterroot' ),
	$root_values( $rooted_base['src']['fold']['srcRows'] )
);
assert_same(
	'SLOT source enum carries the same roots (a root offered on a tag is offered in a field)',
	array( 'current', 'refs', 'site', 'testroot', 'filterroot' ),
	$root_values( $rooted_fold['srcRows'] )
);
assert_same(
	'...and so does a slot ≥2, behind its `same` row',
	array( 'same', 'current', 'refs', 'site', 'testroot', 'filterroot' ),
	$root_values( $rooted_fold['srcRowsWithSame'] )
);
// APPENDED, never prepended: `defaultRoot` is derived from the first row and stands for
// what an absent `src` means, which is `current` on every tag whatever is registered.
assert_same( 'base defaultRoot is untouched by the appended roots', 'current', $rooted_base['src']['fold']['defaultRoot'] );
assert_same( 'slot defaultRoot likewise', 'current', $rooted_fold['defaultRoot'] );

// ── The SHARED base builder is untouched ─────────────────────────────────────────────
//
// Rows attach at the CHAIN-ROOT layer only. `term_*`, `try_*`, `{{table}}` and `{{call}}`
// build their own surfaces from this enum's rows, so a root leaking in here would offer a
// root inside its own modifier family's Source dropdown and widen {{call}}'s deliberate
// allowlist.
assert_same(
	'bws_base_source_option() does NOT grow registered roots',
	array( 'current', 'ref', 'site' ),
	$root_values( bws_base_source_option()['src']['options'] )
);
assert_same(
	'...so the derived slot twin does not either',
	array( 'current', 'ref' ),
	$root_values( bws_build_slot_traversal_options( 1, bws_base_source_option(), $base_trav, false )['src']['options'] )
);

// ── The settings gate ────────────────────────────────────────────────────────────────
//
// Two different questions: `is_selectable_root()` is the source's claim that it MAY be
// chosen, `is_source_enabled()` is this site saying whether that context is switched on
// at all. A term-context root follows the term_ modifier toggle exactly as every other
// term surface does. Neither gate reaches resolution.
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Term_Root_Source() );
assert_same(
	'a term-context root is offered while the term_ modifier toggle is ON',
	true,
	in_array( 'testtermroot', $root_values( bws_registered_root_rows() ), true )
);
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = false;
assert_same(
	'...and disappears with the toggle, like every other term surface',
	false,
	in_array( 'testtermroot', $root_values( bws_registered_root_rows() ), true )
);
assert_same(
	'...while a post-context root is unaffected by it',
	true,
	in_array( 'testroot', $root_values( bws_registered_root_rows() ), true )
);
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = true;

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
