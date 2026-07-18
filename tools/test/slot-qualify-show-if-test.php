<?php
/**
 * Standalone unit harness for bws_slot_qualify_show_if() in
 * includes/tags/base-shared.php.
 *
 * Pure array transform — no WordPress required. The function makes no WP/GB
 * calls. base-shared.php top-level IS guarded but defines many WP-dependent
 * functions at parse, so rather than require the whole file we shim the
 * minimal WP symbols its top-level references and pull only what parses.
 * Simpler + drift-proof: re-declare nothing — load the real file under a
 * shimmed __() and a no-op for the GB/registration side effects.
 *
 * SCOPE — bws_slot_qualify_show_if only:
 *   - slot 1 → bare keys (no prefix), passthrough
 *   - slot ≥2 → sibling keys rewritten to {N}-key
 *   - non-sibling keys left untouched
 *   - condition VALUES unchanged ('ref', 'not:site')
 *   - empty show_if → empty out
 *
 * Run:
 *   php tools/test/slot-qualify-show-if-test.php
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

// base-shared.php registers tags / hooks at parse via add_action etc. Shim the
// WP entry points it touches at top level to no-ops so the require is inert;
// we only call the pure helper.
foreach ( array( 'add_action', 'add_filter', 'do_action', 'apply_filters' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return func_num_args() > 1 ? func_get_arg(1) : null; }" );
	}
}

require __DIR__ . '/../../includes/tags/base-shared.php';

$failures = 0;
$count    = 0;

/**
 * Assert two arrays are identical (===, order-sensitive).
 */
function assert_same_array( $label, array $expected, array $actual ): void {
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

$siblings = array( 'src', 'ref', 'srcTermIn' );

echo "bws_slot_qualify_show_if\n";

// Slot 1 — bare keys, passthrough.
assert_same_array(
	'slot 1 ref show_if stays bare',
	array( 'src' => 'ref' ),
	bws_slot_qualify_show_if( array( 'src' => 'ref' ), 1, $siblings )
);

assert_same_array(
	'slot 1 srcTermIn not:site stays bare',
	array( 'src' => 'not:site' ),
	bws_slot_qualify_show_if( array( 'src' => 'not:site' ), 1, $siblings )
);

// Slot ≥2 — sibling key prefixed, value unchanged.
assert_same_array(
	'slot 2 src → 2-src, value ref unchanged',
	array( '2-src' => 'ref' ),
	bws_slot_qualify_show_if( array( 'src' => 'ref' ), 2, $siblings )
);

assert_same_array(
	'slot 3 src → 3-src, value not:site unchanged',
	array( '3-src' => 'not:site' ),
	bws_slot_qualify_show_if( array( 'src' => 'not:site' ), 3, $siblings )
);

// Non-sibling key untouched even on slot ≥2.
assert_same_array(
	'slot 2 non-sibling key (use) left bare, sibling (src) prefixed',
	array( 'use' => 'key', '2-src' => 'ref' ),
	bws_slot_qualify_show_if( array( 'use' => 'key', 'src' => 'ref' ), 2, $siblings )
);

// Empty show_if → empty out (slot 1 and ≥2).
assert_same_array( 'slot 1 empty → empty', array(), bws_slot_qualify_show_if( array(), 1, $siblings ) );
assert_same_array( 'slot 4 empty → empty', array(), bws_slot_qualify_show_if( array(), 4, $siblings ) );

// Multi-key condition: all siblings prefixed, value list preserved.
assert_same_array(
	'slot 2 multi sibling keys all prefixed',
	array( '2-src' => 'ref', '2-srcTermIn' => 'not_empty' ),
	bws_slot_qualify_show_if( array( 'src' => 'ref', 'srcTermIn' => 'not_empty' ), 2, $siblings )
);

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
