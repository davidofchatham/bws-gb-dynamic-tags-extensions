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
foreach ( array( 'add_action', 'add_filter', 'do_action', 'apply_filters' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return func_num_args() > 1 ? func_get_arg(1) : null; }" );
	}
}

require __DIR__ . '/../../includes/tags/base-shared.php';

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

// V1: slot ≥2 prepends 'same' inherit row.
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

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
