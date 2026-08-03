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
 * SCOPE — the rule as it stands BEFORE the `0 = unlimited` semantics change:
 *   - non-numeric (null, '', 'abc', array) ⇒ UNSET ⇒ 1;
 *   - numeric ⇒ max( 1, (int) $raw ), so 0 and negatives clamp to 1.
 *
 * These cases pin the pre-change behavior so the semantics step can be read as a
 * DELIBERATE diff: the 0 / -1 rows below are the ones expected to invert, and the
 * non-numeric rows are the ones that must NOT (garbage resolves to the default,
 * never to "no limit").
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

echo "\nbws_clamp_limit — floor (these rows INVERT at the semantics change)\n";

assert_same( 'int 0 → 1', 1, bws_clamp_limit( 0 ) );
assert_same( "string '0' → 1", 1, bws_clamp_limit( '0' ) );
assert_same( 'int -1 → 1', 1, bws_clamp_limit( -1 ) );
assert_same( "string '-3' → 1", 1, bws_clamp_limit( -3 ) );

echo "\nbws_clamp_limit — pass-through\n";

assert_same( 'int 1 → 1', 1, bws_clamp_limit( 1 ) );
assert_same( 'int 5 → 5', 5, bws_clamp_limit( 5 ) );
assert_same( "string '5' → 5 (the wire always arrives as a string)", 5, bws_clamp_limit( '5' ) );
assert_same( "'2.7' → 2 (truncates, does not round)", 2, bws_clamp_limit( '2.7' ) );
assert_same( 'no ceiling — 999 passes through', 999, bws_clamp_limit( 999 ) );

echo "\nbws_clamp_limit — the two legacy expressions it replaces AGREE today\n";

// Site 3 read `$limit ?: 1`, sites 1-2 read `$limit ?? 1`. They diverge on '' and
// '0' (falsy but set) — pinned equal here, which is why the extraction is a no-op
// and why it had to land BEFORE 0 changes meaning.
foreach ( array( null, '', '0', 0, -1, 'abc', '1', '5' ) as $raw ) {
	$legacy_coalesce = max( 1, (int) ( $raw ?? 1 ) );
	$legacy_elvis    = max( 1, (int) ( $raw ?: 1 ) );
	$label           = var_export( $raw, true );
	assert_same( "{$label}: matches legacy ?? expression", $legacy_coalesce, bws_clamp_limit( $raw ) );
	assert_same( "{$label}: matches legacy ?: expression", $legacy_elvis, bws_clamp_limit( $raw ) );
}

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
