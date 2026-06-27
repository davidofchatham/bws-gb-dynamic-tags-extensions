<?php
/**
 * Standalone unit harness for the pure `{{call}}` helpers in
 * includes/tags/fn-tags.php:
 *   - bws_call_get_allowlist()        (VC-allow: filter read + normalize)
 *   - bws_call_passes_gate( $fn )     (VC-gate: function_exists && ! isInternal)
 *   - bws_call_build_args( $pid, $o ) (VC-arg: post_id pos-0 + optional arg)
 *
 * fn-tags.php is loaded inert: ABSPATH defined, the few WP symbols the file
 * references at include time / in these helpers are shimmed (__, apply_filters,
 * add_filter, sanitize_text_field, array_is_list polyfill for < PHP 8.1). We
 * call ONLY the pure helpers — the GB-bound register/callback paths are not
 * exercised here.
 *
 * Run:
 *   php tools/test/call-tag-test.php
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
if ( ! function_exists( 'sanitize_text_field' ) ) {
	// Minimal stand-in: strip tags + collapse whitespace (enough for arg tests).
	function sanitize_text_field( $s ) {
		$s = wp_strip_all_tags_shim( (string) $s );
		return trim( preg_replace( '/[\r\n\t ]+/', ' ', $s ) );
	}
	function wp_strip_all_tags_shim( $s ) { return preg_replace( '/<[^>]*>/', '', $s ); }
}
if ( ! function_exists( 'array_is_list' ) ) {
	// PHP < 8.1 polyfill (the harness's only need; runtime requires 8.1+ anyway).
	function array_is_list( array $a ): bool {
		$i = 0;
		foreach ( $a as $k => $_ ) {
			if ( $k !== $i++ ) { return false; }
		}
		return true;
	}
}

// Mutable allowlist backing the apply_filters shim, so each test can set it.
$GLOBALS['__bws_test_allowlist'] = array();
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		if ( 'bws_fn_passthrough_functions' === $hook ) {
			return $GLOBALS['__bws_test_allowlist'];
		}
		return $value;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() { return true; }
}
if ( ! function_exists( '_doing_it_wrong' ) ) {
	function _doing_it_wrong() {}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return $s; }
}

require __DIR__ . '/../../includes/tags/fn-tags.php';

// A site-defined (non-internal) function for the gate-accept case.
function bws_test_site_fn( $post_id = null, $arg = 'full' ) {
	return 'ok';
}

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

// ---------------------------------------------------------------------------
echo "bws_call_get_allowlist (VC-allow)\n";

// bws_call_get_allowlist() memoizes per registration generation, so each test
// that changes the backing filter must bump the generation — exactly the
// contract a raw add_filter caller follows (bws_call_bump_allowlist_generation).
function set_allowlist( $value ): void {
	$GLOBALS['__bws_test_allowlist'] = $value;
	bws_call_bump_allowlist_generation();
}

set_allowlist( array() );
assert_same( 'empty filter → []', array(), bws_call_get_allowlist() );

set_allowlist( array( 'foo', 'bar' ) );
assert_same(
	'bare-string list normalized to assoc',
	array( 'foo' => array(), 'bar' => array() ),
	bws_call_get_allowlist()
);

set_allowlist( array( 'foo' => array( 'label' => 'Foo' ) ) );
assert_same(
	'associative preserved (meta kept)',
	array( 'foo' => array( 'label' => 'Foo' ) ),
	bws_call_get_allowlist()
);

set_allowlist( array( 'foo' => 'notarray' ) );
assert_same(
	'assoc with non-array meta coerced to []',
	array( 'foo' => array() ),
	bws_call_get_allowlist()
);

set_allowlist( array( '', 'bar' ) );
assert_same(
	'empty-string entry dropped',
	array( 'bar' => array() ),
	bws_call_get_allowlist()
);

set_allowlist( 'not-an-array' );
assert_same( 'non-array filter → []', array(), bws_call_get_allowlist() );

// Memo invariance: WITHOUT a generation bump, a second read returns the cached
// result even though the backing filter changed (the production contract).
$GLOBALS['__bws_test_allowlist'] = array( 'stale' => array() ); // no bump
assert_same( 'memo holds until generation bumps', array(), bws_call_get_allowlist() );
bws_call_bump_allowlist_generation();
assert_same( 'memo refreshes after bump', array( 'stale' => array() ), bws_call_get_allowlist() );

// ---------------------------------------------------------------------------
echo "bws_call_passes_gate (VC-gate)\n";

assert_same( 'site function passes', true, bws_call_passes_gate( 'bws_test_site_fn' ) );
assert_same( 'PHP builtin strlen rejected (isInternal)', false, bws_call_passes_gate( 'strlen' ) );
assert_same( 'PHP builtin system rejected', false, bws_call_passes_gate( 'system' ) );
assert_same( 'nonexistent function rejected', false, bws_call_passes_gate( 'bws_no_such_fn_xyz' ) );
assert_same( 'empty name rejected', false, bws_call_passes_gate( '' ) );

// ---------------------------------------------------------------------------
echo "bws_call_build_args (VC-arg)\n";

assert_same( 'no arg → [post_id] only', array( 42 ), bws_call_build_args( 42, array() ) );
assert_same( 'empty arg → [post_id] only', array( 42 ), bws_call_build_args( 42, array( 'arg' => '' ) ) );
assert_same(
	'non-empty arg → [post_id, sanitized arg]',
	array( 42, 'short' ),
	bws_call_build_args( 42, array( 'arg' => 'short' ) )
);
assert_same(
	'arg sanitized (tags stripped)',
	array( 7, 'abc' ),
	bws_call_build_args( 7, array( 'arg' => '<b>abc</b>' ) )
);
assert_same(
	'post_id is always position 0',
	7,
	bws_call_build_args( 7, array( 'arg' => 'x' ) )[0]
);

// ---------------------------------------------------------------------------
echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} of {$count} assertions failed.\n";
	exit( 1 );
}
echo "PASSED: all {$count} assertions.\n";
exit( 0 );
