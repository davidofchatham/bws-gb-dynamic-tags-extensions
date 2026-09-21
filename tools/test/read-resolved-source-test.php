<?php
/**
 * Standalone unit harness for the L2 READ SEAM — bws_read_resolved_source_value()
 * (the kind dispatch, returning what the store holds) and bws_read_resolved_source()
 * (the string coercion over it), plus the array-preserving read the post arm makes,
 * bws_read_field_preserving_arrays(). All in includes/helpers/field-helpers.php.
 *
 * THE REAL FILE IS LOADED, NOT COPIED — same posture as loop-item-classify-test.php:
 * field-helpers.php defines functions only, so it loads inert with ABSPATH defined.
 *
 * WHAT IS STUBBED, AND THE ONE THAT MATTERS. get_post_meta / get_term_meta /
 * get_user_meta are stubbed against a fixed store below. GenerateBlocks_Meta_Handler
 * is stubbed too, and it is stubbed to REPRODUCE THE QUIRK the two-pass read exists
 * for: a filter-populated scalar answers the fallback ('') when the caller asks
 * array-preserving. Without that behavior in the stub, §R3.2 would pass against a
 * single-pass read and pin nothing.
 *
 * WHAT THIS HARNESS CANNOT SEE. Whether GB's real Meta_Handler still behaves that
 * way — that is measured on the testbed against a real ACF image field, and the
 * stub is a model of a measurement, not the measurement. It sees no rendered tag
 * either; a moved render is fold-test-matrix.md's.
 *
 * SCOPE:
 *   §R1  the string seam, one row per kind — the pre-split behavior, unchanged
 *   §R2  the raw seam beside it — where the two ANSWER DIFFERENTLY, and where they do not
 *   §R3  bws_read_field_preserving_arrays() — each pass, and the family of miss it covers
 *   §R4  the post/0 guard, on both halves
 *
 * Run:  php tools/test/read-resolved-source-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// ── The stub store ────────────────────────────────────────────────────────────
// `gallery` is the ACF Image Array format — the value no string-typed seam can carry.
// `logo` is the URL format, and it is FILTER-POPULATED, which is the half of the
// quirk a single array-preserving read would drop.
const STUB_META = array(
	'gallery' => array( 'ID' => 44, 'url' => 'https://example.test/logo.png' ),
	'logo'    => 'https://example.test/logo.png',
	'name'    => 'Ada Lovelace',
	'zero'    => '0',
);
const STUB_FILTER_POPULATED = array( 'logo' );

if ( ! class_exists( 'GenerateBlocks_Meta_Handler' ) ) {
	class GenerateBlocks_Meta_Handler {
		public static function get_meta( $id, $key, $single_only = true, $callable = null, $fallback = '' ) {
			$raw = STUB_META[ $key ] ?? '';

			if ( is_array( $raw ) || is_object( $raw ) ) {
				return $single_only ? $fallback : $raw;
			}
			// THE QUIRK (GB Meta_Handler::get_value): an upstream filter supplied this
			// scalar, and asking array-preserving hands back the fallback instead of it.
			if ( ! $single_only && in_array( $key, STUB_FILTER_POPULATED, true ) ) {
				return $fallback;
			}
			return $raw;
		}
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		return STUB_META[ $key ] ?? '';
	}
}
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $id, $key = '', $single = false ) {
		return STUB_META[ $key ] ?? '';
	}
}
if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $id, $key = '', $single = false ) {
		return STUB_META[ $key ] ?? '';
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id = null ) {
		return null;
	}
}
if ( ! function_exists( 'get_term' ) ) {
	function get_term( $id, $tax = '' ) {
		return null;
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) {
		return false;
	}
}
if ( ! function_exists( 'get_queried_object' ) ) {
	function get_queried_object() {
		return null;
	}
}
// Stubbed by definition, as traversal-pipeline-test.php and loop-item-classify-test.php
// each declare in their own preamble: the real one is WP-bound and nothing here is a loop.
if ( ! function_exists( 'bws_source_gate' ) ) {
	function bws_source_gate( array $source ) {
		return true;
	}
}
if ( ! function_exists( 'bws_wp_is_term_archive' ) ) {
	function bws_wp_is_term_archive(): bool {
		return false;
	}
}

require __DIR__ . '/../../includes/helpers/field-helpers.php';

$failures = 0;
$count    = 0;

function assert_same( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo '         expected: ' . var_export( $expected, true ) . "\n";
	echo '         actual:   ' . var_export( $actual, true ) . "\n";
}

/** One value read through the string seam. */
function str_read( array $source, string $key ): string {
	return bws_read_resolved_source( $source, $key, null );
}

/** The same read through the raw seam. */
function raw_read( array $source, string $key ) {
	return bws_read_resolved_source_value( $source, $key, null );
}

const POST_SRC = array( 'kind' => 'post', 'id' => 5 );
const TERM_SRC = array( 'kind' => 'term', 'id' => 7 );
const USER_SRC = array( 'kind' => 'user', 'id' => 2 );
const ROW_SRC  = array(
	'kind' => 'meta_row',
	'row'  => array(
		'name'    => 'Ada Lovelace',
		'gallery' => array( 'ID' => 44, 'url' => 'https://example.test/logo.png' ),
		'zero'    => '0',
	),
);

echo "\n=== R1 - the string seam, one row per kind (the pre-split behavior) ===\n";

assert_same( 'R1.1 post', 'Ada Lovelace', str_read( POST_SRC, 'name' ) );
assert_same( 'R1.2 term', 'Ada Lovelace', str_read( TERM_SRC, 'name' ) );
assert_same( 'R1.3 user', 'Ada Lovelace', str_read( USER_SRC, 'name' ) );
assert_same( 'R1.4 meta_row', 'Ada Lovelace', str_read( ROW_SRC, 'name' ) );
assert_same( 'R1.5 an unknown kind reads nothing', '', str_read( array( 'kind' => 'nonsense' ), 'name' ) );
assert_same( 'R1.6 a missing key is a miss, not a notice', '', str_read( POST_SRC, 'absent' ) );
assert_same( 'R1.7 a missing ROW key likewise', '', str_read( ROW_SRC, 'absent' ) );

// '0' RENDERS. The coercion drops '' and nothing else, so the one scalar PHP calls
// falsy survives both halves - the same guard includes/hooks.php pads at the GB seam.
assert_same( 'R1.8 a zero string is a VALUE at the string seam', '0', str_read( POST_SRC, 'zero' ) );
assert_same( 'R1.9 and at the raw seam', '0', raw_read( ROW_SRC, 'zero' ) );

echo "\n=== R2 - the raw seam beside it ===\n";

// THE SPLIT'S WHOLE POINT, on the kind that reaches it without a store: an image
// sub-field inside a repeater row is an array, and a string-typed seam drops it
// before any caller sees it (FW-74).
assert_same(
	'R2.1 a ROW array value survives the raw seam',
	array( 'ID' => 44, 'url' => 'https://example.test/logo.png' ),
	raw_read( ROW_SRC, 'gallery' )
);
assert_same( 'R2.2 and the string seam answers empty for that same source, as it always has', '', str_read( ROW_SRC, 'gallery' ) );

// The post arm reads through the two-pass, so an ACF Image Array on a POST is
// reachable too - the half ticket 05 reads an image on a chained post source through.
assert_same(
	'R2.3 a POST array value survives the raw seam',
	array( 'ID' => 44, 'url' => 'https://example.test/logo.png' ),
	raw_read( POST_SRC, 'gallery' )
);
assert_same( 'R2.4 and the string seam still answers empty for it', '', str_read( POST_SRC, 'gallery' ) );

// WHERE THEY AGREE. Every scalar read answers identically through either half -
// which is what says the split moved no caller.
assert_same( 'R2.5 a scalar is the same value through either half (post)', 'Ada Lovelace', raw_read( POST_SRC, 'name' ) );
assert_same( 'R2.6 a filter-populated scalar too (post)', 'https://example.test/logo.png', raw_read( POST_SRC, 'logo' ) );
assert_same( 'R2.7 and through the string half', 'https://example.test/logo.png', str_read( POST_SRC, 'logo' ) );

echo "\n=== R3 - the array-preserving read, and why the passes are in that order ===\n";

assert_same(
	'R3.1 pass 2 is what carries an array back',
	array( 'ID' => 44, 'url' => 'https://example.test/logo.png' ),
	bws_read_field_preserving_arrays( 'gallery', null, 5 )
);

// `logo` is filter-populated, so the array-preserving pass answers '' for it and the
// OTHER pass is what returns the URL. THE ORDER IS NOT WHAT THIS PINS, and the
// distinction was measured: swapping the two passes leaves every row here green,
// because no value answers non-empty through both. What R3.2 and R3.1 pin together
// is that the fallthrough covers BOTH families of miss, in whichever order it asks.
assert_same( 'R3.2 a filter-populated scalar comes back from pass 1, not pass 2', 'https://example.test/logo.png', bws_read_field_preserving_arrays( 'logo', null, 5 ) );
assert_same( 'R3.3 the array-preserving pass ALONE would drop it (the quirk, stated as a row)', '', bws_read_field( 'logo', null, 5, false ) );
assert_same( 'R3.4 a plain scalar is unaffected either way', 'Ada Lovelace', bws_read_field_preserving_arrays( 'name', null, 5 ) );
assert_same( 'R3.5 a miss stays a miss after both passes', '', bws_read_field_preserving_arrays( 'absent', null, 5 ) );

echo "\n=== R4 - the post/0 guard (SPEC §V18) ===\n";

// A {kind:post,id:0} means the factory found NO current post. Neither half may hand
// that to bws_read_field(), which would treat the 0 as not-explicit and re-run its own
// inference against a context the factory rejected.
assert_same( 'R4.1 the raw seam refuses post 0', '', raw_read( array( 'kind' => 'post', 'id' => 0 ), 'name' ) );
assert_same( 'R4.2 the string seam refuses it too', '', str_read( array( 'kind' => 'post', 'id' => 0 ), 'name' ) );
assert_same( 'R4.3 a post source with no id at all is the same answer', '', raw_read( array( 'kind' => 'post' ), 'name' ) );
assert_same( 'R4.4 and the user arm guards its own id the same way', '', raw_read( array( 'kind' => 'user', 'id' => 0 ), 'name' ) );

echo "\n";
echo $failures
	? "FAILED - {$failures} of {$count} assertions\n"
	: "PASSED - {$count} assertions\n";
exit( $failures ? 1 : 0 );
