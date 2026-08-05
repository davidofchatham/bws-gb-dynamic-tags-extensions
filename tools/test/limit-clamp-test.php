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
 *   - non-numeric (null, '', 'abc', array) ⇒ UNSET ⇒ the caller's stated $default;
 *   - numeric <= 0 (0, -1, -3) ⇒ 0, meaning UNLIMITED;
 *   - numeric >= 1 ⇒ (int) $raw.
 *
 * `$default` is REQUIRED (1.17.0). It is not a convenience: since the tag-level
 * default is selected by the source SPELLING (bws_limit_default — flat wire caps at
 * 1, chain wire does not), a site that forgets to state which default it wants
 * would silently render legacy behaviour on chain wire, which is wrong output that
 * looks normal in review. Omission must be an ArgumentCountError, which §L0 pins.
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
 *   4. bws_try_join_items()        — includes/tags/base-shared.php. NOT a clamp site
 *      any more: it holds no options, so it structurally cannot know the era. It
 *      takes an already-resolved int and slices with it.
 *
 * Run:  php tools/test/limit-clamp-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// bws_limit_default() asks the compiler whether the `src` value is chain wire, so
// the real chain files come along. They are loaded, never stubbed: a test-local
// "is this chain wire" would be a second copy of the era test, which is precisely
// the rule the single interpreter exists to keep in one place.
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
}
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
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

echo "§L0 — \$default is REQUIRED, not defaulted to the legacy 1\n";

// The posture the twin harnesses take on a missing `node`: fail, never skip. A
// site that forgets which default it wants must break at the call, because the
// alternative is chain wire silently rendering flat-wire behaviour.
$threw = false;
try {
	// @phpstan-ignore-next-line — deliberately wrong arity.
	call_user_func( 'bws_clamp_limit', 2 );
} catch ( ArgumentCountError $e ) {
	$threw = true;
}
assert_same( 'omitting $default is an ArgumentCountError', true, $threw );

echo "\n§L4 — bws_limit_default(): the SPELLING selects the default\n";

// The whole compatibility mechanism for base-tag source chains, and the reason it
// needs no migration: an unmigrated tag gets its default from its own wire,
// wherever that wire lives.
assert_same( 'bare tag → 1', 1, bws_limit_default( array() ) );
assert_same( "src:current → 1", 1, bws_limit_default( array( 'src' => 'current' ) ) );
assert_same( 'src:site → 1', 1, bws_limit_default( array( 'src' => 'site' ) ) );
assert_same( 'FLAT src:ref → 1 (the legacy default it has always had)', 1, bws_limit_default( array( 'src' => 'ref', 'ref' => 'office' ) ) );
assert_same( 'FLAT srcTermIn → 1 — srcTermIn is not part of the src VALUE', 1, bws_limit_default( array( 'srcTermIn' => 'department' ) ) );
assert_same( 'CHAIN src:refs,office → 0 (uncapped)', 0, bws_limit_default( array( 'src' => 'refs,office' ) ) );
assert_same( 'CHAIN src:terms,department → 0', 0, bws_limit_default( array( 'src' => 'terms,department' ) ) );
assert_same( 'CHAIN src:site;entries,rows → 0', 0, bws_limit_default( array( 'src' => 'site;entries,rows' ) ) );
assert_same( 'a bare fanning slug is one-hop chain wire → 0', 0, bws_limit_default( array( 'src' => 'refs' ) ) );
assert_same( 'legacy `source` key is read too', 0, bws_limit_default( array( 'source' => 'refs,office' ) ) );

// Ordinary option precedence, no extra rule: an EXPLICIT value beats the
// spelling-selected default in both directions. This is what a migrated or
// author-converted tag looks like, so it is not an anomaly.
assert_same(
	'explicit limit:1 on chain wire caps at 1',
	1,
	bws_clamp_limit( '1', bws_limit_default( array( 'src' => 'refs,office' ) ) )
);
assert_same(
	'explicit limit:0 on flat wire is unlimited',
	0,
	bws_clamp_limit( '0', bws_limit_default( array( 'src' => 'ref', 'ref' => 'office' ) ) )
);
assert_same(
	'GARBAGE on chain wire falls to the CHAIN default, not to 1',
	0,
	bws_clamp_limit( 'abc', bws_limit_default( array( 'src' => 'refs,office' ) ) )
);

echo "\nbws_clamp_limit — unset\n";

assert_same( 'null → 1 (absent key)', 1, bws_clamp_limit( null, 1 ) );
assert_same( "'' → 1 (cleared control)", 1, bws_clamp_limit( '', 1 ) );

echo "\nbws_clamp_limit — non-numeric is UNSET, never 0\n";

// The is_numeric() gate. Invisible today ((int)'abc' === 0, which the floor lifts
// to 1) and load-bearing the moment 0 means unlimited: a typo must not fan out.
assert_same( "'abc' → 1", 1, bws_clamp_limit( 'abc', 1 ) );
assert_same( "'2 posts' → 1 (leading-numeric string is NOT numeric)", 1, bws_clamp_limit( '2 posts', 1 ) );
assert_same( 'array → 1', 1, bws_clamp_limit( array( 2 ), 1 ) );
assert_same( 'true → 1 (bool is not numeric)', 1, bws_clamp_limit( true, 1 ) );
assert_same( 'false → 1', 1, bws_clamp_limit( false, 1 ) );

echo "\nbws_clamp_limit — UNLIMITED (0); parse 0 AND -1, canonical emit is 0\n";

// These four asserted 1 before 1.17.0. A pre-1.17.0 `limit:0` was an author's
// written value silently discarded by a max(1,…) clamp, not a designed 1 — the
// change honors the wire rather than freezing the clamp.
assert_same( 'int 0 → 0 (unlimited)', 0, bws_clamp_limit( 0, 1 ) );
assert_same( "string '0' → 0", 0, bws_clamp_limit( '0', 1 ) );
assert_same( 'int -1 → 0 (GB Posts-Per-Page convention, parsed tolerantly)', 0, bws_clamp_limit( -1, 1 ) );
assert_same( "string '-3' → 0 (any negative is unlimited)", 0, bws_clamp_limit( -3, 1 ) );
assert_same( "'-0.5' → 0 (truncates to 0, then unlimited)", 0, bws_clamp_limit( '-0.5', 1 ) );
assert_same( "'0.9' → 0 (truncates below 1 ⇒ unlimited, not 1)", 0, bws_clamp_limit( '0.9', 1 ) );

echo "\nbws_clamp_limit — pass-through\n";

assert_same( 'int 1 → 1', 1, bws_clamp_limit( 1, 1 ) );
assert_same( 'int 5 → 5', 5, bws_clamp_limit( 5, 1 ) );
assert_same( "string '5' → 5 (the wire always arrives as a string)", 5, bws_clamp_limit( '5', 1 ) );
assert_same( "'2.7' → 2 (truncates, does not round)", 2, bws_clamp_limit( '2.7', 1 ) );
assert_same( 'no ceiling — 999 passes through', 999, bws_clamp_limit( 999, 1 ) );

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
	$limit = bws_clamp_limit( $raw, 1 );
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
assert_same( 'limit 1 stops after one item', 1, $breaks( bws_clamp_limit( null, 1 ) ) );
assert_same( 'limit 2 stops after two items', 2, $breaks( bws_clamp_limit( 2, 1 ) ) );
assert_same( 'limit 0 never breaks early', 3, $breaks( bws_clamp_limit( 0, 1 ) ) );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
