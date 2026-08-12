<?php
/**
 * Standalone unit harness for the try_ list-join seam in
 * includes/tags/base-shared.php:
 *   - bws_try_normalize_items( $raw ): array
 *   - bws_try_join_items( array $items, $sep, int $limit ): string
 *
 * Both are pure array/string transforms — no WordPress required. base-shared.php
 * is loaded inert (shimmed __ + no-op WP entry points) per the same pattern as
 * slot-qualify-show-if-test.php; we call only the pure helpers.
 *
 * SCOPE — the SPEC §32 Phase-2 seam contract:
 *   normalize: string→[s], array→non-empty items, ''/false/null→[]
 *   join:      byte-identical gate (1 item + default = verbatim), N-item join,
 *              limit arriving RESOLVED (int, `0` = UNLIMITED)
 *              (1.17.0), sep default ', ', explicit '' sep honored.
 *
 * Run:
 *   php tools/test/try-join-seam-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
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

// bws_try_join_items delegates its limit interpretation to bws_clamp_limit. Load
// the REAL helper file rather than restating the rule here — a test-local copy of
// the clamp is the exact drift the extraction exists to prevent. field-helpers.php
// defines functions only (no load-time WP calls), so it loads inert.
require __DIR__ . '/../../includes/helpers/field-helpers.php';
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

echo "bws_try_normalize_items\n";

assert_same( 'string → 1-element list', array( 'a@x.com' ), bws_try_normalize_items( 'a@x.com' ) );
assert_same( 'empty string → []', array(), bws_try_normalize_items( '' ) );
assert_same( 'false → []', array(), bws_try_normalize_items( false ) );
assert_same( 'null → []', array(), bws_try_normalize_items( null ) );
assert_same( 'array passthrough', array( 'a', 'b' ), bws_try_normalize_items( array( 'a', 'b' ) ) );
assert_same( 'array drops empty items + re-indexes', array( 'a', 'b' ), bws_try_normalize_items( array( 'a', '', 'b' ) ) );
assert_same( 'array drops false/null', array( 'a' ), bws_try_normalize_items( array( false, 'a', null ) ) );
assert_same( 'empty array → []', array(), bws_try_normalize_items( array() ) );
assert_same( 'all-empty array → []', array(), bws_try_normalize_items( array( '', false ) ) );
// '0' is a legitimate value (not empty in our contract — only '', false, null drop).
assert_same( "string '0' preserved", array( '0' ), bws_try_normalize_items( '0' ) );

echo "\nbws_try_join_items — byte-identical gate (1 item)\n";

// The Phase-2 hard gate: 1 finished string + default limit + no explicit sep =
// the element verbatim, NO trailing separator, NO wrapping. [V3]
assert_same( '1 item, limit 1 → verbatim', 'a@x.com', bws_try_join_items( array( 'a@x.com' ), null, 1 ) );
assert_same( '1 item, limit 1, sep absent → verbatim', 'a@x.com', bws_try_join_items( array( 'a@x.com' ), null, 1 ) );
assert_same( '1 item, even with high limit → verbatim (no sep on lone item)', 'solo', bws_try_join_items( array( 'solo' ), ', ', 5 ) );
assert_same( '1 item, explicit empty sep → verbatim', 'solo', bws_try_join_items( array( 'solo' ), '', 5 ) );

echo "\nbws_try_join_items — list mode\n";

// Default limit is 1 → multi-item list truncates to first (matches base text
// core default; author opts into list via limit>1). [V4,V5]
assert_same( 'N items, limit 1 → first only', 'a', bws_try_join_items( array( 'a', 'b', 'c' ), null, 1 ) );
assert_same( 'N items, limit 2, default sep', 'a, b', bws_try_join_items( array( 'a', 'b', 'c' ), null, 2 ) );
assert_same( 'N items, limit 3, default sep', 'a, b, c', bws_try_join_items( array( 'a', 'b', 'c' ), null, 3 ) );
assert_same( 'limit no ceiling — limit > count joins all', 'a, b', bws_try_join_items( array( 'a', 'b' ), null, 99 ) );
assert_same( 'custom sep', 'a | b', bws_try_join_items( array( 'a', 'b' ), ' | ', 2 ) );
assert_same( 'explicit empty sep joins with nothing', 'ab', bws_try_join_items( array( 'a', 'b' ), '', 2 ) );

echo "\nbws_try_join_items — the limit arrives RESOLVED (1.17.0)\n";

// This function holds no options, so it structurally cannot know whether the tag's
// source is spelled flat or as a chain — and since 1.17.0 that spelling is what
// selects the unset default. So it stopped interpreting `limit` and takes the
// caller's resolved int. The parameter is required and typed, which is what makes a
// caller that forgot to resolve it a TypeError rather than a silent legacy 1.
$threw = false;
try {
	call_user_func( 'bws_try_join_items', array( 'a', 'b' ) );
} catch ( ArgumentCountError $e ) {
	$threw = true;
}
assert_same( 'omitting the resolved limit is an ArgumentCountError', true, $threw );

$threw = false;
try {
	call_user_func( 'bws_try_join_items', array( 'a', 'b' ), null, null );
} catch ( TypeError $e ) {
	$threw = true;
}
assert_same( 'and passing null is a TypeError — it must never receive one again', true, $threw );
// 0 still means UNLIMITED at the slice, which is the half of the rule that stayed
// here. A negative never reaches it any more — bws_clamp_limit normalizes one to 0
// upstream, and the int type makes that the caller's job rather than this one's.
assert_same( 'limit 0 → unlimited', 'a, b, c', bws_try_join_items( array( 'a', 'b', 'c' ), null, 0 ) );
assert_same( 'empty items → empty string', '', bws_try_join_items( array(), ', ', 5 ) );

echo "\nseam composition (normalize → join, the machinery path)\n";

assert_same(
	'string dispatch + defaults = verbatim (shipped-tag path)',
	'plain',
	bws_try_join_items( bws_try_normalize_items( 'plain' ), null, 1 )
);
assert_same(
	'empty dispatch → empty (slot skipped)',
	'',
	bws_try_join_items( bws_try_normalize_items( '' ), null, 1 )
);
assert_same(
	'array dispatch + limit 2 (list-mode producer path)',
	'one, two',
	bws_try_join_items( bws_try_normalize_items( array( 'one', 'two', 'three' ) ), null, 2 )
);

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
