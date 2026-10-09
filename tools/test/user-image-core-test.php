<?php
/**
 * bws_user_custom_image_core() — the author image core's branches.
 *
 * Requires the REAL includes/tags/image-tags.php (function definitions only) and stubs the
 * WordPress-bound calls it makes. What is under test is the core's BRANCHING: when it reads,
 * what it reads, and which exits emit the stated fallback. What the read returns for a real
 * ACF field, and how a returned value is rendered, is the testbed's business
 * (text-test-matrix.md T8.13-T8.28); the ambient seam's claim is traversal-pipeline-test.php's.
 *
 * Run: php tools/test/user-image-core-test.php
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['reads']  = array();
$GLOBALS['stored'] = array();

function sanitize_text_field( $v ) { return trim( (string) $v ); }
function bws_is_valid_meta_key( $k ) { return 1 === preg_match( '/^[A-Za-z0-9_\-]+$/', (string) $k ); }
function bws_use_effective( $tag, $options ) { return $options['use'] ?? 'key'; }
function bws_parse_as_option( $options ) { return array( 'mode' => $options['as'] ?? 'url', 'size' => 'full' ); }
function bws_gb_tag_output( $value, $options = array(), $instance = null ) { return $value; }
function bws_handle_media_fallback( $fallback, $mode, $size, $options, $instance ) {
	return '' === (string) $fallback ? '' : 'FALLBACK(' . $fallback . ')';
}
// Records every read so a branch that must NOT read can be told from one that read and missed.
function bws_read_resolved_source_value( array $source, string $key, $instance ) {
	$GLOBALS['reads'][] = array( $source['kind'], $source['id'], $key );
	return $GLOBALS['stored'][ $source['id'] . ':' . $key ] ?? '';
}
// Stands in for the shared processor: '' for a value it cannot make an image of.
function bws_process_meta_image_value( $meta_value, $mode, $size ) {
	return 'unprocessable' === $meta_value ? '' : 'IMG(' . json_encode( $meta_value ) . ',' . $mode . ')';
}

require __DIR__ . '/../../includes/tags/image-tags.php';

$pass = 0;
$fail = 0;
function eq( $label, $expected, $actual ) {
	global $pass, $fail;
	if ( $expected === $actual ) {
		$pass++;
		return;
	}
	$fail++;
	echo "FAIL: $label\n  expected: " . json_encode( $expected ) . "\n  actual:   " . json_encode( $actual ) . "\n";
}
function run_core( $user_id, array $options, array $stored = array() ) {
	$GLOBALS['reads']  = array();
	$GLOBALS['stored'] = $stored;
	return bws_user_custom_image_core( $user_id, $options, null );
}

// A keyed hit reads THIS user's field and renders what the shared processor makes of it.
eq( 'keyed hit', 'IMG(128,id)', run_core( 7, array( 'key' => 'photo', 'as' => 'id' ), array( '7:photo' => 128 ) ) );
eq( 'keyed hit reads kind user, the given id, the given key', array( array( 'user', 7, 'photo' ) ), $GLOBALS['reads'] );
eq( 'legacy field_key alias reads', 'IMG(128,id)', run_core( 7, array( 'field_key' => 'photo', 'as' => 'id' ), array( '7:photo' => 128 ) ) );
eq( 'a hit beats a stated fallback', 'IMG(128,id)', run_core( 7, array( 'key' => 'photo', 'as' => 'id', 'fallback' => 55 ), array( '7:photo' => 128 ) ) );

// A keyed miss emits the stated fallback, and nothing when none is stated.
eq( 'keyed miss + fallback', 'FALLBACK(55)', run_core( 7, array( 'key' => 'photo', 'fallback' => 55 ) ) );
eq( 'keyed miss, no fallback', '', run_core( 7, array( 'key' => 'photo' ) ) );
eq( 'a value the processor cannot render is a miss', 'FALLBACK(55)', run_core( 7, array( 'key' => 'photo', 'fallback' => 55 ), array( '7:photo' => 'unprocessable' ) ) );

// The branches that must not READ at all: an author has no featured image, no key names no field,
// and an invalid key is never handed to the store.
eq( 'use:featured beats a key and reads nothing', 'FALLBACK(55)', run_core( 7, array( 'use' => 'featured', 'key' => 'photo', 'fallback' => 55 ), array( '7:photo' => 128 ) ) );
eq( 'use:featured read nothing', array(), $GLOBALS['reads'] );
eq( 'no key -> fallback only', 'FALLBACK(55)', run_core( 7, array( 'fallback' => 55 ), array( '7:photo' => 128 ) ) );
eq( 'no key read nothing', array(), $GLOBALS['reads'] );
eq( 'no key, no fallback', '', run_core( 7, array() ) );
eq( 'invalid key -> fallback only', 'FALLBACK(55)', run_core( 7, array( 'key' => 'bad key!', 'fallback' => 55 ) ) );
eq( 'invalid key read nothing', array(), $GLOBALS['reads'] );

// The legacy `id` option is the fallback attachment when `fallback` is absent (the shared stated-fallback rule).
eq( 'id is the fallback attachment', 'FALLBACK(9)', run_core( 7, array( 'key' => 'photo', 'id' => 9 ) ) );

echo "user-image-core: $pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
