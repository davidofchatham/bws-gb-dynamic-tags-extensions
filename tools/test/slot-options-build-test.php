<?php
/**
 * Standalone unit harness for bws_build_slot_src_rows() and bws_build_slot_read_rows() in
 * includes/tags/base-shared.php, the slot enum-row derives the folded registration reads,
 * plus the field leaves and bws_build_fold_slot_options() built on them.
 *
 * Pure fn of (slot ordinal, base options). No WP beyond __() (shimmed).
 *
 * Covers:
 *   V1  — slot src rows derive from base, `site` filtered, slot >=2 prepends `same`.
 *   V6  — `site` is absent from derived src rows unless the container allows it.

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

echo "bws_build_slot_src_rows\n";

// V1/V6: slot 1 = base minus 'site', NO 'same' prepend.
$s1 = bws_build_slot_src_rows( 1, $base_src );
assert_same(
	'slot1 src rows = current,ref (site filtered, no same)',
	array(
		array( 'value' => 'current', 'label' => 'Current Context' ),
		array( 'value' => 'ref',     'label' => 'Posts in Reference/Relational Field' ),
	),
	$s1
);

// V1: slot >=2 prepends the `same` row.
$s2 = bws_build_slot_src_rows( 2, $base_src );
assert_same(
	'slot2 src rows = same,current,ref',
	array(
		array( 'value' => 'same',    'label' => 'Same as Previous Source' ),
		array( 'value' => 'current', 'label' => 'Current Context' ),
		array( 'value' => 'ref',     'label' => 'Posts in Reference/Relational Field' ),
	),
	$s2
);

// V6: site only where the container allows it.
assert_same( 'allow_site keeps the site row', true, in_array( 'site', array_column( bws_build_slot_src_rows( 1, $base_src, true ), 'value' ), true ) );
assert_same( 'site absent by default, any slot', false, in_array( 'site', array_merge( array_column( $s1, 'value' ), array_column( $s2, 'value' ) ), true ) );

// Base source option offers site (the base tag is the unrooted site read).
$base_has_site = false;
foreach ( $base_src['src']['options'] as $o ) {
	if ( 'site' === ( $o['value'] ?? '' ) ) { $base_has_site = true; }
}
assert_same( 'base src still offers site', true, $base_has_site );


echo "bws_pick_src_values (allowlist — complement of the site blocklist)\n";

// Keep current+ref (the {{call}} case): canonical rows from base, reordered to $keep.
$picked = bws_pick_src_values( bws_base_source_option(), array( 'current', 'ref' ) );
assert_same(
	'pick current,ref → exactly those rows, base labels',
	array(
		array( 'value' => 'current', 'label' => 'Current Context' ),
		array( 'value' => 'ref',     'label' => 'Posts in Reference/Relational Field' ),
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
assert_same( 'leaf returns exactly use+key+fixed', array( 'use', 'key', 'fixed' ), array_keys( $leaf ) );

// Enum + control shape (the four-copy definition, now one).
assert_same( 'leaf use options = key,title,fixed', array( 'key', 'title', 'fixed' ), array_column( $leaf['use']['options'], 'value' ) );
assert_same( 'leaf use label "Text Field"', 'Text Field', $leaf['use']['label'] );
assert_same( 'leaf use _strip_default true', true, $leaf['use']['_strip_default'] );
assert_same( 'leaf key control = bws-field-combo', 'bws-field-combo', $leaf['key']['type'] );
assert_same( 'leaf key dynamicLabel true', true, $leaf['key']['dynamicLabel'] );

// Leaf contract: NO show_if. Base overlays use:not:title; the template encodes the
// same fact via try_use_no_key_values. Ship it on the leaf and both callers inherit
// a condition one of them must then strip.
assert_same( 'leaf use carries no show_if', false, isset( $leaf['use']['show_if'] ) );
assert_same( 'leaf key carries no show_if', false, isset( $leaf['key']['show_if'] ) );

// The FIXED read (FW-141) rides the leaf: every text consumer reads it.
assert_same( 'fixed row and input share one label', end( $leaf['use']['options'] )['label'], $leaf['fixed']['label'] );
assert_same( 'fixed input label "Fixed Text"', 'Fixed Text', $leaf['fixed']['label'] );
assert_same( 'fixed input escapes `:`/`|` (bws-format-input)', 'bws-format-input', $leaf['fixed']['type'] );
assert_same( 'fixed input carries no show_if (caller overlay)', false, isset( $leaf['fixed']['show_if'] ) );

// The contact leaf (FW-141 04): email and phone, one builder, per-family words.
foreach ( array(
	'email' => array( 'Email Field', 'Fixed Email' ),
	'phone' => array( 'Phone Number Field', 'Fixed Phone Number' ),
) as $contact_tag => $words ) {
	$contact_leaf = bws_get_contact_field_options( $contact_tag );
	assert_same( "contact leaf {$contact_tag}: use+key+fixed", array( 'use', 'key', 'fixed' ), array_keys( $contact_leaf ) );
	assert_same( "contact leaf {$contact_tag}: use options = key,fixed", array( 'key', 'fixed' ), array_column( $contact_leaf['use']['options'], 'value' ) );
	assert_same( "contact leaf {$contact_tag}: use label", $words[0], $contact_leaf['use']['label'] );
	assert_same( "contact leaf {$contact_tag}: fixed row and input share the label", $words[1], $contact_leaf['fixed']['label'] );
	assert_same( "contact leaf {$contact_tag}: fixed row label", $words[1], end( $contact_leaf['use']['options'] )['label'] );
	assert_same( "contact leaf {$contact_tag}: key label", 'Meta/Option Field Key', $contact_leaf['key']['label'] );
	assert_same( "contact leaf {$contact_tag}: first use row is strip-marked", true, ! empty( $contact_leaf['use']['_strip_default'] ) );
	assert_same( "contact leaf {$contact_tag}: readTag names the row", $contact_tag, $contact_leaf['use']['readTag'] );
	assert_same( "contact leaf {$contact_tag}: fixed input is bws-format-input", 'bws-format-input', $contact_leaf['fixed']['type'] );
	assert_same( "contact leaf {$contact_tag}: no show_if (caller overlay)", false, isset( $contact_leaf['key']['show_if'] ) || isset( $contact_leaf['fixed']['show_if'] ) );
}

// ============================================================
// bws_build_slot_read_rows() — the READ twin (build step 4)
// ============================================================

echo "\nbws_build_slot_read_rows\n";

// --- Slot 1: base enum verbatim, no `same` row regardless of $allow_same. ---
assert_same( 'slot1 read rows = key,title,fixed (no same)', array( 'key', 'title', 'fixed' ), array_column( bws_build_slot_read_rows( 1, $leaf['use'], true ), 'value' ) );

// --- Slot >=2 SELECTING (try_): `same` row prepended, shipped string. ---
$r2 = bws_build_slot_read_rows( 2, $leaf['use'], true );
assert_same( 'slot2 selecting prepends same', array( 'same', 'key', 'title', 'fixed' ), array_column( $r2, 'value' ) );
assert_same( 'slot2 same row label', 'Same as Previous Field', $r2[0]['label'] );

// --- Slot >=2 COMBINING ({{join}}): NO `same` row. Not-built-yet (per-slot handlers),
// NOT degenerate: flipping the flag is the whole change when they ship.
assert_same(
	'slot2 combining = the base rows verbatim',
	array(
		array( 'value' => 'key',   'label' => 'Meta/Option Field' ),
		array( 'value' => 'title', 'label' => 'Title/Name' ),
		array( 'value' => 'fixed', 'label' => 'Fixed Text' ),
	),
	bws_build_slot_read_rows( 2, $leaf['use'], false )
);

// --- No options = nothing to select: no rows, so the caller offers none. ---
assert_same( 'empty base read → no rows', array(), bws_build_slot_read_rows( 2, array(), true ) );

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
assert_same(
	'srcRows at slot 1, `ref` respelled to the wire slug (value only, label kept)',
	array( 'current', 'refs', 'site' ),
	array_column( $fold['srcRows'], 'value' )
);
assert_same(
	'srcRowsWithSame at slot 2 (same-row first)',
	array( 'same', 'current', 'refs', 'site' ),
	array_column( $fold['srcRowsWithSame'], 'value' )
);
assert_same(
	'the respelled row keeps the base label',
	'Posts in Reference/Relational Field',
	array_column( $fold['srcRows'], 'label', 'value' )['refs']
);
assert_same( 'the respelled row keeps its label (only the value moves)', true, in_array( 'refs', array_column( $fold['srcRows'], 'value' ), true ) );
assert_same( '...and no legacy `ref` value survives in the enum', false, in_array( 'ref', array_column( $fold['srcRows'], 'value' ), true ) );
assert_same( 'site arm present when allowed', true, in_array( 'site', array_column( $fold['srcRows'], 'value' ), true ) );

// Read enum: the twin's rows, with a COMBINING container's explicit unset row in front
// — absent there means unconfigured (slot skipped), which is not what the first enum
// row means. `allow_same_read` false ⇒ no `same` row, matching the resolver.
assert_same(
	'readRows = unset row + the text leaf rows (combining)',
	array(
		array( 'value' => '',      'label' => 'Select…' ),
		array( 'value' => 'key',   'label' => 'Meta/Option Field' ),
		array( 'value' => 'title', 'label' => 'Title/Name' ),
		array( 'value' => 'fixed', 'label' => 'Fixed Text' ),
	),
	$fold['readRows']
);
assert_same( 'no read `same` row while allow_same_read is false', false, in_array( 'same', array_column( $fold['readRowsWithSame'], 'value' ), true ) );
assert_same( 'readLabel is the base read noun, not a container copy', 'Text Field', $fold['readLabel'] );

// The OFFER is a CAPABILITY list: only what the container's resolver can express,
// shipped as an ordered slug array — the builders' `steps` parameter as-is, not rows.
// Labels live on the shared vocabulary record, declared once (#70).
assert_same( 'offer carries only the requested steps, as wire slugs', array( 'terms' ), $fold['offer'] );
assert_same( 'step label declared once on the vocabulary, step-shaped', 'Terms in Taxonomy', $fold['steps']['terms']['label'] );

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
// A root ABSENT from BWS_FOLD_PARSE_TIME_ROOT_KINDS resolves at render — the editor must
// offer everything there rather than guess whether `current` is a post or a term, a guess
// that would hide the taxonomy step on every ordinary tag. `roots` is its own top-level
// key: a fact about roots, not about steps. The const OWNS which roots have a parse-time
// kind (see its own PHPDoc); this assertion pins the count and content mechanically
// rather than restating the axis in prose that could drift from it.
assert_same(
	'roots is its own key, straight off the const that owns which roots have a parse-time kind',
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
	'selecting readRows are the text leaf rows with NO unset row',
	array(
		array( 'value' => 'key',   'label' => 'Meta/Option Field' ),
		array( 'value' => 'title', 'label' => 'Title/Name' ),
		array( 'value' => 'fixed', 'label' => 'Fixed Text' ),
	),
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
// KEY-ONLY (a per-slot key with no `use` enum: try_email / try_phone until FW-141 05). No read rows, a
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
// on the control TYPE (a `{{table}}`/`{{call}}` source is a plain select and still
// authors the flat `ref`).
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
// The base tag OFFERS all three steps — `rows` joined in 1.21.0, once every keyed arm
// read a repeater row (FW-74); it was held out while no arm consumed a meta_row, because
// a step nothing reads authors a chain that renders empty. The vocabulary record it ships
// is BYTE-IDENTICAL to the slot builder's: container-invariant by construction, both
// consuming one helper (#70).
assert_same( 'base chain: offer = refs,terms,rows in offer order', array( 'refs', 'terms', 'rows' ), $base_fold['offer'] );
// The `rows` step's ARG picker ships wherever the step is offered, or the control paints
// a field combo with no label. One definition, both builders (bws_fold_rows_picker_def).
assert_same( 'base chain: the offered `rows` step ships its argument picker', true, ! empty( $base_fold['rowsOption']['label'] ) );
assert_same( 'slot: ...and so does the slot builder', $base_fold['rowsOption'], $fold['rowsOption'] );
// The picker opens on repeater fields and keeps its filter controls, so a plain-meta
// repeater (no discovered type) is still reachable — the same axis {{table}}'s tag-level
// `key` takes. Pinned here because the rendering harness exercises the TextControl
// fallback, which carries no type filter to observe.
assert_same( 'the `rows` picker pre-scopes its type filter to repeaters', 'repeater', $base_fold['rowsOption']['typeDefault'] ?? '' );
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

// FW-67, the deletion's pin. The term-hop CONTROL is offered by nothing: the leaf no
// longer defines it, so neither spelling can carry one, and the drop above has nothing
// left to do for it. Asserted at BOTH spellings because the chain arm would hide a
// re-added definition (it absorbs whatever `flatAxes` names) while the plain-select arm
// would paint it — a leaf regression shows up on one side only.
assert_same( 'the term-hop control is gone from the traversal leaf', false, isset( bws_base_traversal_options()['srcTermIn'] ) );
assert_same( 'chain tag: ...so no term-hop control to absorb', false, isset( $chain_tag['srcTermIn'] ) );
assert_same( 'plain-select tag: ...and none to paint either', false, isset( $flat_tag['srcTermIn'] ) );

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
	// ROOT ARGUMENTS through the filter route (FW-39). An argument only in-repo sources
	// could declare would make the public route second-class, and FW-69/70 made it
	// integration surface.
	$roots['argfilterroot'] = array(
		'label'   => 'Arg Filter Root',
		'resolve' => static function ( $options, $instance ) { return 1; },
		'arg'     => array( 'label' => 'Department', 'control' => 'bws-test-roster-picker', 'argless' => 'owner-resolves' ),
	);
	// A spec whose declaration names no control meets the same rule a class does: the
	// route hands it through unvalidated and the ONE reader drops it, rather than the
	// rule living at two sites that can come to disagree.
	$roots['argfilterhalf'] = array(
		'label'   => 'Arg Filter Half',
		'resolve' => static function ( $options, $instance ) { return 1; },
		'arg'     => array( 'control' => 'bws-test-roster-picker' ),
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
	$roots['same']      = array( 'label' => 'Carry Over Sentinel', 'resolve' => static function () { return 1; } );
	// `inherit` is the parent-source root and the four hierarchical step names are reserved:
	// none may be claimed by a source.
	$roots['inherit']   = array( 'label' => 'Parent Source', 'resolve' => static function () { return 1; } );
	$roots['parent']    = array( 'label' => 'Reserved', 'resolve' => static function () { return 1; } );
	$roots['ancestor']  = array( 'label' => 'Reserved', 'resolve' => static function () { return 1; } );
	$roots['children']  = array( 'label' => 'Reserved', 'resolve' => static function () { return 1; } );
	$roots['siblings']  = array( 'label' => 'Reserved', 'resolve' => static function () { return 1; } );
	$roots['a;b,c']     = array( 'label' => 'Grammar Chars', 'resolve' => static function () { return 1; } );
	return $roots;
} );

\BWS\DynamicTags\SourceRegistry::init();

$root_values = static function ( array $rows ): array {
	return array_map( static function ( $row ) { return $row['value']; }, $rows );
};

// The appender is the single answer both surfaces take their rows from. `post` now sits
// between `testroot` and `term` because CurrentPost registers before TaxonomyTerm in
// SourceRegistry::init() (FW-39 ticket 03) — both are now selectable pinning roots.
$appended = bws_registered_root_rows();
assert_same(
	'the appender offers the opted-in source and the filter-declared root, in registration order',
	array( 'testroot', 'post', 'term', 'filterroot', 'argfilterroot', 'argfilterhalf' ),
	$root_values( $appended )
);
assert_same(
	'...labelled by the source\'s OWN accessor, so an integrator names their concept',
	array( 'Test Root', 'Specific Post', 'Specific Term', 'Filter Root', 'Arg Filter Root', 'Arg Filter Half' ),
	array_map( static function ( $row ) { return $row['label']; }, $appended )
);
assert_same(
	'...and every root another plugin registered (class or filter route) is flagged `integration`, ours are not',
	array( 'testroot', 'filterroot', 'argfilterroot', 'argfilterhalf' ),
	$root_values( array_values( array_filter( $appended, static function ( $row ) { return ! empty( $row['integration'] ); } ) ) )
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
foreach ( array( 'refs', 'same', 'inherit', 'parent', 'ancestor', 'children', 'siblings', 'a;b,c' ) as $unwritable ) {
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
// the generic relationship step subsumed them. Every one of them is a registered source
// right now, and none is offerable: nothing here resolves its own id from ambient context
// in a way an author could usefully pin, and no argument seam changes that.
//
// `term` AND `post` USED TO BE ON THIS LIST and are pinning roots now (FW-39, tickets 02
// and 03). What changed is not that the objection was wrong: a bare `term`/`post` root
// really would duplicate what a bare base tag does. It is that `term,<ID>`/`post,<ID>` is
// a different offering, and the argument is REQUIRED — asserted just below, and enforced
// at the factory seam rather than here.
foreach ( array( 'related_post', 'second_related_post', 'post_term_related_post', 'term_related_post' ) as $never ) {
	assert_same(
		"the registry's own `{$never}` stays out of the root enum",
		array( true, false ),
		array( null !== \BWS\DynamicTags\SourceRegistry::get_source( $never ), in_array( $never, $root_values( $appended ), true ) )
	);
}

// The SHIPPED pinning root (FW-39), through the same appender an integrator's reaches.
// Both halves matter: `term` is offered at all (it never has been), and it is offered
// WITH an argument whose policy is refuse — a bare `term` row would be the ambient read a
// bare base tag already gives, under a second name.
$term_row = null;
foreach ( $appended as $row ) {
	if ( 'term' === $row['value'] ) { $term_row = $row; }
}
assert_same(
	'`term` is offered as a PINNING root — declared argument, refuse when bare',
	array(
		'label'   => 'Term',
		'control' => 'bws-entity-picker',
		'argless' => \BWS\DynamicTags\SourceInterface::ROOT_ARGLESS_REFUSE,
		'kind'    => 'term',
	),
	$term_row['arg'] ?? null
);
// The picker's kind is DERIVED from the source's own context type, never declared beside
// the control. A declared kind could come to disagree with what the source resolves —
// browsing terms for a root that answers posts — and nothing would report it.
assert_same(
	'...and its kind is the source\'s own context type, not a second declaration',
	\BWS\DynamicTags\SourceRegistry::get_source( 'term' )->get_context_type(),
	$term_row['arg']['kind'] ?? null
);
// The declaration is what the EDITOR reads; BWS_FOLD_PARSE_TIME_ROOT_KINDS is what it
// filters steps by. A pinning root needs both, and the pair is the census below.
assert_same(
	'`term` answers its kind at PARSE TIME, so steps can be offered off it with no render',
	'term',
	BWS_FOLD_PARSE_TIME_ROOT_KINDS['term'] ?? null
);

// The SECOND pinning root (FW-39 ticket 03) — same appender, same declaration shape,
// different kind. `post` has no legacy `id`-carrying modifier family (D13), so unlike
// `term` there is no compatibility constraint to note here; the declaration itself is
// identical in shape.
$post_row = null;
foreach ( $appended as $row ) {
	if ( 'post' === $row['value'] ) { $post_row = $row; }
}
assert_same(
	'`post` is offered as a PINNING root — declared argument, refuse when bare',
	array(
		'label'   => 'Post',
		'control' => 'bws-entity-picker',
		'argless' => \BWS\DynamicTags\SourceInterface::ROOT_ARGLESS_REFUSE,
		'kind'    => 'post',
	),
	$post_row['arg'] ?? null
);
assert_same(
	'...and its kind is the source\'s own context type too',
	\BWS\DynamicTags\SourceRegistry::get_source( 'post' )->get_context_type(),
	$post_row['arg']['kind'] ?? null
);
assert_same(
	'`post` answers its kind at PARSE TIME too',
	'post',
	BWS_FOLD_PARSE_TIME_ROOT_KINDS['post'] ?? null
);

// ── The parse-time root CENSUS (FW-39) ───────────────────────────────────────────────
//
// Every root that DECLARES AN ARGUMENT pins one entity, so its kind is knowable from the
// wire and must be stated here. A pinning root that omits it produces no error anywhere:
// bws_fold_chain_resolution() falls to `render_time`, the editor keeps offering every step
// off it, and a chain that can never resolve is authorable with nothing saying so. That is
// block-context-keys-test.php's hazard exactly — a legal read with a plausible answer.
//
// Scoped to the SHIPPED sources. An integrator's pinning root is theirs to declare, and
// nothing here can census a constant it does not own.
foreach ( \BWS\DynamicTags\SourceRegistry::get_selectable_roots() as $root_key => $root_source ) {
	if ( array() === bws_root_argument_row( $root_source->get_root_argument() ) ) {
		continue;
	}
	if ( ! in_array( $root_key, array( 'term', 'post' ), true ) ) {
		continue; // Fixture roots stand in for an integrator's; they own no constant here.
	}
	assert_same(
		"a pinning root declares its parse-time kind: `{$root_key}`",
		true,
		isset( BWS_FOLD_PARSE_TIME_ROOT_KINDS[ $root_key ] )
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
	array( 'current', 'site', 'testroot', 'post', 'term', 'filterroot', 'argfilterroot', 'argfilterhalf' ),
	$root_values( $rooted_base['src']['fold']['srcRows'] )
);
assert_same(
	'SLOT source enum carries the same roots (a root offered on a tag is offered in a field)',
	array( 'current', 'refs', 'site', 'testroot', 'post', 'term', 'filterroot', 'argfilterroot', 'argfilterhalf' ),
	$root_values( $rooted_fold['srcRows'] )
);
assert_same(
	'...and so does a slot ≥2, behind its `same` row',
	array( 'same', 'current', 'refs', 'site', 'testroot', 'post', 'term', 'filterroot', 'argfilterroot', 'argfilterhalf' ),
	$root_values( $rooted_fold['srcRowsWithSame'] )
);
// APPENDED, never prepended: `defaultRoot` is derived from the first row and stands for
// what an absent `src` means, which is `current` on every tag whatever is registered.
assert_same( 'base defaultRoot is untouched by the appended roots', 'current', $rooted_base['src']['fold']['defaultRoot'] );
assert_same( 'slot defaultRoot likewise', 'current', $rooted_fold['defaultRoot'] );

// ── `{{table}}`'s root menu: `inherit` leads, per container ───────────
//
// A column hangs off its row, so the root an absent chain means is the PARENT's source:
// `inherit` is PREPENDED for the table container ONLY, which is what makes it the derived
// `defaultRoot`. Root lists are per-container; `{{join}}` is pinned unchanged below.
$table_fold = bws_build_fold_slot_options(
	array(
		'container'  => 'table',
		'max'        => 2,
		'base_read'  => $text['use'],
		'base_key'   => $text['key'],
		'allow_site' => true,
		'noun'       => 'column',
	)
)['A']['fold'];
assert_same( 'table: `inherit` leads the root enum', 'inherit', $table_fold['srcRows'][0]['value'] ?? null );
assert_same( 'table: `inherit` is labeled "Tag Source"', 'Tag Source', $table_fold['srcRows'][0]['label'] ?? null );
assert_same( 'table: `inherit` is the DERIVED defaultRoot', 'inherit', $table_fold['defaultRoot'] );
assert_same(
	'table: the rest of the enum is the join enum, unmoved, behind it',
	$root_values( $rooted_fold['srcRows'] ),
	array_slice( $root_values( $table_fold['srcRows'] ), 1 )
);
assert_same( 'table: `current` stays a pickable root', true, in_array( 'current', $root_values( $table_fold['srcRows'] ), true ) );
assert_same( 'table: the first-step steps group reads "Starting from Tag Source"', 'Starting from Tag Source', $table_fold['stepGroup'] ?? null );
assert_same( 'table: ships the header control label', 'Header', $table_fold['headerOption']['label'] ?? null );
assert_same( 'table: ships the field-label action copy, one %s for the label', 1, substr_count( $table_fold['headerOption']['labelApply'] ?? '', '%s' ) );
assert_same( 'join: ships no header control (a <th> means nothing in a join slot)', false, isset( $rooted_fold['headerOption'] ) );
assert_same( 'join: no `inherit` row', false, in_array( 'inherit', $root_values( $rooted_fold['srcRows'] ), true ) );
assert_same( 'join: defaultRoot is untouched', 'current', $rooted_fold['defaultRoot'] );
assert_same( 'join: ships no stepGroup override (the control keeps its own)', false, isset( $rooted_fold['stepGroup'] ) );
assert_same( 'try_ attempts ship no `inherit` row either', false, in_array( 'inherit', $root_values( bws_build_fold_slot_options( array( 'container' => 'try', 'max' => 2, 'base_read' => $text['use'], 'base_key' => $text['key'], 'noun' => 'attempt' ) )['A']['fold']['srcRows'] ), true ) );

// ── `{{table}}`'s REGISTERED options: columns are folded slots, nothing flat ──────────
//
// The real registration, so a flat `{N}-*` key or the retired tag-level `key` coming back
// fails here by name. `flatAxes` is EMPTY rather than absent: the editor reads an empty list
// as "no flat era" and neither recovers a column from flat keys nor deletes one on commit —
// the bare `src` a slot-1 flat read would take is the TAG's source.
require_once __DIR__ . '/../../includes/tags/table-tags.php';
$table_opts = bws_get_table_options();
$table_keys = array_keys( $table_opts );
assert_same( 'table: columns run A..J (BWS_TABLE_MAX_COLS)', array( true, true, false ), array( isset( $table_opts['A'] ), isset( $table_opts['J'] ), isset( $table_opts['K'] ) ) );
assert_same( 'table: every column is a `bws-slot-fold`', array( 'bws-slot-fold' ), array_values( array_unique( array_map( static function ( $k ) use ( $table_opts ) { return $table_opts[ $k ]['type']; }, array_filter( $table_keys, static function ( $k ) { return 1 === preg_match( '/^[A-Z]+$/', $k ); } ) ) ) ) );
assert_same( 'table: no flat column key survives', array(), array_values( preg_grep( '/^\d+-/', $table_keys ) ) );
assert_same( 'table: no tag-level `key` survives', false, isset( $table_opts['key'] ) );
assert_same( 'table: the tag source is the chain control, as on a base tag', 'bws-src-chain', $table_opts['src']['type'] ?? null );
assert_same( 'table: no flat relationship/taxonomy control rides beside the chain', array( false, false ), array( isset( $table_opts['ref'] ), isset( $table_opts['srcTermIn'] ) ) );
assert_same( 'table: the chain offers the same steps a base tag does', bws_build_src_chain_option()['src']['fold']['offer'], $table_opts['src']['fold']['offer'] );
assert_same( 'table: `caption` stays tag-level', true, isset( $table_opts['caption'] ) );
assert_same( 'table: no per-slot flat axes', array(), $table_opts['A']['fold']['flatAxes'] );
assert_same( 'table: columns offer the fixed read', true, ! empty( $table_opts['A']['fold']['fixedOption'] ) );
assert_same( 'table: column noun', 'column', $table_opts['A']['fold']['noun'] );
assert_same( 'table: the column card is offered the styling fields', true, $table_opts['A']['fold']['styleOption'] ?? null );
assert_same( 'join: a column width means nothing in a join slot', false, isset( $rooted_fold['styleOption'] ) );
assert_same(
	'table: the presentation keys register contiguously, in control order, after `caption` and before the columns',
	array( 'caption', 'stylePreview', 'layout', 'width', 'color', 'fontSize', 'fontWeight', 'fontStyle', 'textAlign', 'textTransform', 'stripe', 'border', 'borderRadius', 'A' ),
	array_slice( $table_keys, array_search( 'caption', $table_keys, true ), 14 )
);
assert_same(
	'table: the preview leads, then four composites own the style keys, the rest are members that draw nothing',
	array( 'bws-table-style-preview', 'bws-table-sizing', 'bws-table-style-member', 'bws-table-typography', 'bws-table-style-member', 'bws-table-style-member', 'bws-table-style-member', 'bws-table-style-member', 'bws-table-style-member', 'bws-table-stripes', 'bws-table-borders', 'bws-table-style-member' ),
	array_map( static function ( $k ) use ( $table_opts ) { return $table_opts[ $k ]['type']; }, array( 'stylePreview', 'layout', 'width', 'color', 'fontSize', 'fontWeight', 'fontStyle', 'textAlign', 'textTransform', 'stripe', 'border', 'borderRadius' ) )
);
assert_same( 'table: every style key is in the `style` visual group', array( 'style' ), array_values( array_unique( array_map( static function ( $k ) use ( $table_opts ) { return $table_opts[ $k ]['_group'] ?? null; }, array( 'stylePreview', 'layout', 'width', 'color', 'fontSize', 'fontWeight', 'fontStyle', 'textAlign', 'textTransform', 'stripe', 'border', 'borderRadius' ) ) ) ) );
assert_same( 'table: no picker scope rides the fold any more', array( false, false ), array( isset( $table_opts['A']['fold']['fieldScope'] ), isset( $table_opts['A']['fold']['scopeStateKey'] ) ) );

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
	'...so the derived slot rows do not either',
	array( 'current', 'ref' ),
	$root_values( bws_build_slot_src_rows( 1, bws_base_source_option(), false ) )
);

// ── The term_ toggle does NOT gate offering (1.20.0, FW-39/D39) ──────────────────────
//
// ONE gate on an offered root: `is_selectable_root()`, the source's own claim that it MAY
// be chosen. The settings gate that stood beside it until 1.20.0 read a source's CONTEXT
// TYPE and answered the term_ modifier toggle, which now means the term_ TAG FAMILY alone
// — deprecated, seeded off on a new install, and a fresh site must still be able to author
// the `term` chain root. So a TERM-context root is offered with the toggle either way.
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Term_Root_Source() );
assert_same(
	'a term-context root is offered while the term_ modifier toggle is ON',
	true,
	in_array( 'testtermroot', $root_values( bws_registered_root_rows() ), true )
);
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = false;
assert_same(
	'...and is STILL offered with the toggle off — offering is decoupled from the family',
	true,
	in_array( 'testtermroot', $root_values( bws_registered_root_rows() ), true )
);
assert_same(
	'...as is a post-context root, which never answered the toggle',
	true,
	in_array( 'testroot', $root_values( bws_registered_root_rows() ), true )
);
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = true;

echo "\n";
// ── The ROOT ARGUMENT declaration, carried (FW-39) ───────────────────────────────────
//
// A root that PINS an entity needs an author to say which one, and the declaration of
// that argument rides the same row the label does. The class-route fixtures register HERE,
// after the enum-order block above, so that block keeps asserting the order it was
// written for; the filter-route pair had to be declared before init and is in the enum
// order above for the same reason.
//
// Both surfaces carry the declaration WITHOUT KNOWING WHAT IT MEANS — one opaque token,
// interpreted only by the control it names. That is the property that lets a filter-route
// root take one on the same terms as a class.
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Pinned_Root_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Owner_Resolves_Root_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Half_Declared_Root_Source() );

$row_arg = static function ( array $rows, string $value ) {
	foreach ( $rows as $row ) {
		if ( $value === $row['value'] ) {
			return $row['arg'] ?? null;
		}
	}
	return 'NO SUCH ROW';
};
$arg_of = static function ( string $value ) use ( $row_arg ) {
	return $row_arg( bws_registered_root_rows(), $value );
};

assert_same(
	'a declaring root carries its argument on the row, argless defaulting to REFUSE',
	array( 'label' => 'Which One', 'control' => 'bws-test-picker', 'argless' => 'refuse', 'kind' => 'post' ),
	$arg_of( 'pinroot' )
);
assert_same(
	'...and the second policy is carried as STATED, never inferred from what exists',
	array( 'label' => 'Dimension', 'control' => 'bws-test-roster-picker', 'argless' => 'owner-resolves', 'kind' => 'post' ),
	$arg_of( 'ownerroot' )
);
// ABSENT rather than empty on a root that takes no argument: the key's presence IS the
// claim that this root takes one, so an empty array would say the opposite of what it
// means and every surface would have to test emptiness instead of presence.
assert_same( 'a root that declares nothing carries no `arg` key at all', null, $arg_of( 'testroot' ) );
// DROPPED WHOLE, not repaired. There is no default control to fall back to, and a
// declared argument an author cannot fill leaves a root whose only behaviour is refusing.
assert_same( 'a declaration naming no control is dropped', null, $arg_of( 'halfroot' ) );
assert_same(
	'...and the root itself is still offered — a bad optional declaration retires nothing',
	true,
	in_array( 'halfroot', $root_values( bws_registered_root_rows() ), true )
);

// THE FILTER ROUTE, on the same terms. The route hands its spec's declaration through
// UNVALIDATED and the one reader drops a bad one, rather than the rule living at two
// sites that can come to disagree — which is why the half-declared spec answers exactly
// as the half-declared class does.
assert_same(
	'a filter-declared root carries an argument on the same terms as a class',
	array( 'label' => 'Department', 'control' => 'bws-test-roster-picker', 'argless' => 'owner-resolves', 'kind' => 'post' ),
	$arg_of( 'argfilterroot' )
);
assert_same( '...and a half-declared spec is dropped by that same one reader', null, $arg_of( 'argfilterhalf' ) );

// Both surfaces, one appender — the enum-order block's property, re-asked for the
// argument. A root offered on a base tag with a picker and offered in a `{{join}}` field
// without one is exactly the asymmetry the single appender exists to remove.
// A declaration of the WRONG SHAPE lands where a missing key does. The gate reads an
// integrator's array, so a non-string is a shape to EXPECT: cast without checking, an
// array passes as the literal "Array" and captions a picker with it, and an object with
// no `__toString` throws an uncaught Error that takes down the option build for every
// base tag and folded slot on the site.
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Nonscalar_Arg_Root_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Nonscalar_Policy_Root_Source() );
assert_same( 'a non-scalar label or control is dropped, not stringified', null, $arg_of( 'nonscalarroot' ) );
assert_same(
	'...leaving that root offered too',
	true,
	in_array( 'nonscalarroot', $root_values( bws_registered_root_rows() ), true )
);
// The two axes stay independent: a broken POLICY must not delete the argument an author
// is being asked to fill, so it lands on the conservative value an unrecognized string
// gets.
assert_same(
	'a non-scalar argless policy falls back to REFUSE, keeping the argument',
	array( 'label' => 'Which One', 'control' => 'bws-test-picker', 'argless' => 'refuse', 'kind' => 'post' ),
	$arg_of( 'badpolicyroot' )
);

$argued_base = bws_build_src_chain_option()['src']['fold']['srcRows'];
$argued_fold = bws_build_fold_slot_options(
	array(
		'container'  => 'join',
		'max'        => 2,
		'base_read'  => $text['use'],
		'base_key'   => $text['key'],
		'allow_site' => true,
		'noun'       => 'field',
	)
)['A']['fold'];
$expected_pin = array( 'label' => 'Which One', 'control' => 'bws-test-picker', 'argless' => 'refuse', 'kind' => 'post' );
assert_same( 'BASE root enum carries the declaration', $expected_pin, $row_arg( $argued_base, 'pinroot' ) );
assert_same( 'SLOT source enum carries the same declaration', $expected_pin, $row_arg( $argued_fold['srcRows'], 'pinroot' ) );
assert_same( '...and so does a slot 2 and up, behind its `same` row', $expected_pin, $row_arg( $argued_fold['srcRowsWithSame'], 'pinroot' ) );

if ( $failures > 0 ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
