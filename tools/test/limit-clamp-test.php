<?php
/**
 * Standalone unit harness for bws_clamp_limit() — THE single interpreter of a
 * `limit` option value (includes/helpers/field-helpers.php).
 *
 * The real function is loaded, not copied: it is pure (is_numeric + max, no WP/GB
 * symbols) and a test-local copy of the clamp rule is the exact drift the
 * extraction exists to prevent. field-helpers.php defines functions only, so it
 * loads inert with ABSPATH defined.
 *
 * SCOPE — the rule as it stands AFTER the `0 = unlimited` semantics change:
 *   - non-numeric (null, '', 'abc', array) ⇒ UNSET ⇒ 1;
 *   - numeric <= 0 (0, -1, -3) ⇒ 0, meaning UNLIMITED;
 *   - numeric >= 1 ⇒ (int) $raw.
 *
 * The 0 / -1 rows are the ones that INVERTED at the semantics step (they asserted
 * 1 under the old max(1,…) clamp); the non-numeric rows are the ones that did NOT
 * (garbage resolves to the default, never to "no limit"). Both halves are pinned
 * below so the diff between the two rules stays readable in one file.
 *
 * The CALLER contract is pinned too: 0 means "no slice", so every call site must
 * write `array_slice( $x, 0, $limit ?: null )` and guard early-breaks on
 * `$limit &&`. A site that passes a bare 0 to array_slice returns an EMPTY list —
 * the exact regression the last section here exists to catch.
 *
 * Callers folded onto this rule (four sites, one rule):
 *   1. bws_resolve_field_values()  — field-helpers.php (the seam)
 *   2. bws_collect_value_list()    — field-helpers.php (the shared list fold)
 *   3. try_ slot dispatch          — includes/classes/class-tag-template-registry.php
 *   4. bws_try_join_items()        — includes/tags/base-shared.php (defensive re-clamp)
 *
 * Run:  php tools/test/limit-clamp-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/field-helpers.php';

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
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

echo "bws_clamp_limit — unset\n";

assert_same( 'null → 1 (absent key)', 1, bws_clamp_limit( null ) );
assert_same( "'' → 1 (cleared control)", 1, bws_clamp_limit( '' ) );

echo "\nbws_clamp_limit — non-numeric is UNSET, never 0\n";

// The is_numeric() gate. Invisible today ((int)'abc' === 0, which the floor lifts
// to 1) and load-bearing the moment 0 means unlimited: a typo must not fan out.
assert_same( "'abc' → 1", 1, bws_clamp_limit( 'abc' ) );
assert_same( "'2 posts' → 1 (leading-numeric string is NOT numeric)", 1, bws_clamp_limit( '2 posts' ) );
assert_same( 'array → 1', 1, bws_clamp_limit( array( 2 ) ) );
assert_same( 'true → 1 (bool is not numeric)', 1, bws_clamp_limit( true ) );
assert_same( 'false → 1', 1, bws_clamp_limit( false ) );

echo "\nbws_clamp_limit — UNLIMITED (0); parse 0 AND -1, canonical emit is 0\n";

// These four asserted 1 before 1.17.0. A pre-1.17.0 `limit:0` was an author's
// written value silently discarded by a max(1,…) clamp, not a designed 1 — the
// change honors the wire rather than freezing the clamp.
assert_same( 'int 0 → 0 (unlimited)', 0, bws_clamp_limit( 0 ) );
assert_same( "string '0' → 0", 0, bws_clamp_limit( '0' ) );
assert_same( 'int -1 → 0 (GB Posts-Per-Page convention, parsed tolerantly)', 0, bws_clamp_limit( -1 ) );
assert_same( "string '-3' → 0 (any negative is unlimited)", 0, bws_clamp_limit( -3 ) );
assert_same( "'-0.5' → 0 (truncates to 0, then unlimited)", 0, bws_clamp_limit( '-0.5' ) );
assert_same( "'0.9' → 0 (truncates below 1 ⇒ unlimited, not 1)", 0, bws_clamp_limit( '0.9' ) );

echo "\nbws_clamp_limit — pass-through\n";

assert_same( 'int 1 → 1', 1, bws_clamp_limit( 1 ) );
assert_same( 'int 5 → 5', 5, bws_clamp_limit( 5 ) );
assert_same( "string '5' → 5 (the wire always arrives as a string)", 5, bws_clamp_limit( '5' ) );
assert_same( "'2.7' → 2 (truncates, does not round)", 2, bws_clamp_limit( '2.7' ) );
assert_same( 'no ceiling — 999 passes through', 999, bws_clamp_limit( 999 ) );

echo "\nbws_clamp_limit — the caller slice contract (`?: null`)\n";

// Not testing array_slice; testing that the documented idiom yields the intended
// list for each class of input. A site that drops the `?: null` truncates to
// nothing on 0 while still reading as "limit applied" in review.
$list = array( 'a', 'b', 'c', 'd' );
foreach ( array(
	array( 'unset',      null,  array( 'a' ) ),
	array( 'garbage',    'abc', array( 'a' ) ),
	array( 'explicit 2', 2,     array( 'a', 'b' ) ),
	array( 'zero',       0,     array( 'a', 'b', 'c', 'd' ) ),
	array( 'minus one',  -1,    array( 'a', 'b', 'c', 'd' ) ),
	array( 'over-long',  99,    array( 'a', 'b', 'c', 'd' ) ),
) as $case ) {
	list( $label, $raw, $expected ) = $case;
	$limit = bws_clamp_limit( $raw );
	assert_same( "slice {$label}", $expected, array_slice( $list, 0, $limit ?: null ) );
}

echo "\nbws_clamp_limit — the early-break guard (try_ term-hop dispatch)\n";

// class-tag-template-registry.php stops hopping terms once enough items are
// collected. A bare `count >= $slot_max` breaks on the FIRST item when the limit
// is 0, silently turning unlimited into one.
$breaks = static function ( int $slot_max ): int {
	$items = array();
	foreach ( array( 'x', 'y', 'z' ) as $found ) {
		$items[] = $found;
		if ( $slot_max && count( $items ) >= $slot_max ) {
			break;
		}
	}
	return count( $items );
};
assert_same( 'limit 1 stops after one item', 1, $breaks( bws_clamp_limit( null ) ) );
assert_same( 'limit 2 stops after two items', 2, $breaks( bws_clamp_limit( 2 ) ) );
assert_same( 'limit 0 never breaks early', 3, $breaks( bws_clamp_limit( 0 ) ) );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
