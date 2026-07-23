<?php
/**
 * Standalone unit harness for the image as+size fold (FW-52 Phase 3).
 *
 * Covers TWO pure surfaces, both copied INLINE (house pattern — no WP, no autoload):
 *
 *   1. bws_parse_as_option()  — read seam: split `as:<mode>[,<size>]`, legacy `size:`
 *      fallback. Mirror of includes/helpers/image-helpers.php.
 *   2. the as+size migration fold — value-conditional rewrite folding a legacy separate
 *      `size:` (bare + N- try_ slots) into `as`'s value, dropping a dead size on a
 *      nullary return. Mirror of bws_migrate_image_as_size_fold() in
 *      includes/tags/deprecated-tags.php (which delegates parse/serialize to
 *      MigrationRegistry; here a minimal GB-format parse/serialize stands in).
 *
 * Keep in sync with those two functions on any fold change (CLAUDE.md §Update triggers).
 *
 * Run:  php tools/test/as-size-fold-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$failures = 0;
$count    = 0;

function assert_eq( string $label, $expected, $actual ): void {
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

// ---------------------------------------------------------------------------
// INLINE COPY 1 — bws_parse_as_option (read seam). sanitize_text_field stubbed
// to a trim/strip so the pure algorithm runs without WP.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}

function t_parse_as_option( $options ) {
	$raw  = $options['as'] ?? $options['return_type'] ?? 'url';
	$raw  = sanitize_text_field( (string) $raw );
	$bits = explode( ',', $raw, 2 );

	$mode = ( '' !== $bits[0] ) ? $bits[0] : 'url';
	$size = isset( $bits[1] ) && '' !== $bits[1]
		? $bits[1]
		: sanitize_text_field( $options['size'] ?? 'full' );

	return array( 'mode' => $mode, 'size' => $size );
}

// ---------------------------------------------------------------------------
// Minimal GB-format parse/serialize (mirrors MigrationRegistry, insertion-ordered).
// ---------------------------------------------------------------------------
function t_parse( string $s ): array {
	$inner = trim( $s );
	if ( str_starts_with( $inner, '{{' ) ) { $inner = substr( $inner, 2 ); }
	if ( str_ends_with( $inner, '}}' ) ) { $inner = substr( $inner, 0, -2 ); }
	$inner = trim( $inner );
	$sp = strpos( $inner, ' ' );
	if ( false === $sp ) { return array( $inner, array() ); }
	$tag = substr( $inner, 0, $sp );
	$opts = array();
	foreach ( explode( '|', trim( substr( $inner, $sp + 1 ) ) ) as $pair ) {
		$c = strpos( $pair, ':' );
		if ( false !== $c ) {
			$k = substr( $pair, 0, $c );
			if ( '' !== $k ) { $opts[ $k ] = substr( $pair, $c + 1 ); }
		}
	}
	return array( $tag, $opts );
}

function t_serialize( string $tag, array $opts ): string {
	$pairs = array();
	foreach ( $opts as $k => $v ) {
		if ( '' !== (string) $v ) { $pairs[] = $k . ':' . $v; }
	}
	return $pairs ? '{{' . $tag . ' ' . implode( '|', $pairs ) . '}}' : '{{' . $tag . '}}';
}

// ---------------------------------------------------------------------------
// INLINE COPY 2 — the fold (operates on the parsed options; string in/out).
// ---------------------------------------------------------------------------
function t_fold( string $tag_string ): string {
	[ $tag_name, $options ] = t_parse( $tag_string );
	$nullary = array( 'id', 'alt', 'title', 'caption' );

	foreach ( array( '', '2', '3', '4', '5' ) as $slot ) {
		$prefix   = ( '' === $slot ) ? '' : $slot . '-';
		$as_key   = $prefix . 'as';
		$size_key = $prefix . 'size';

		if ( ! array_key_exists( $size_key, $options ) ) { continue; }

		$size = trim( (string) $options[ $size_key ] );
		unset( $options[ $size_key ] );

		$as_raw  = isset( $options[ $as_key ] ) ? (string) $options[ $as_key ] : 'url';
		$as_bits = explode( ',', $as_raw, 2 );
		$mode    = ( '' !== $as_bits[0] ) ? $as_bits[0] : 'url';
		$folded  = isset( $as_bits[1] ) && '' !== $as_bits[1];

		if ( in_array( $mode, $nullary, true ) ) {
			$options[ $as_key ] = $mode;
		} elseif ( $folded ) {
			$options[ $as_key ] = $mode . ',' . $as_bits[1];
		} else {
			$options[ $as_key ] = 'url,' . ( '' !== $size ? $size : 'full' );
		}
	}

	return t_serialize( $tag_name, $options );
}

// ===========================================================================
echo "bws_parse_as_option (read seam)\n";

assert_eq( 'folded url,medium → mode url size medium',
	array( 'mode' => 'url', 'size' => 'medium' ), t_parse_as_option( array( 'as' => 'url,medium' ) ) );
assert_eq( 'bare url → size defaults full',
	array( 'mode' => 'url', 'size' => 'full' ), t_parse_as_option( array( 'as' => 'url' ) ) );
assert_eq( 'nullary alt → mode alt, size full (ignored downstream)',
	array( 'mode' => 'alt', 'size' => 'full' ), t_parse_as_option( array( 'as' => 'alt' ) ) );
assert_eq( 'legacy split (as:url + size:large) → url,large',
	array( 'mode' => 'url', 'size' => 'large' ), t_parse_as_option( array( 'as' => 'url', 'size' => 'large' ) ) );
assert_eq( 'folded value WINS over legacy size key',
	array( 'mode' => 'url', 'size' => 'thumbnail' ), t_parse_as_option( array( 'as' => 'url,thumbnail', 'size' => 'large' ) ) );
assert_eq( 'no as at all → url/full default',
	array( 'mode' => 'url', 'size' => 'full' ), t_parse_as_option( array() ) );
assert_eq( 'legacy return_type read',
	array( 'mode' => 'id', 'size' => 'full' ), t_parse_as_option( array( 'return_type' => 'id' ) ) );

echo "\nas+size migration fold\n";

assert_eq( 'url + legacy size → folded',
	'{{image as:url,medium}}', t_fold( '{{image as:url|size:medium}}' ) );
assert_eq( 'nullary alt + dead size → size DROPPED',
	'{{image as:alt}}', t_fold( '{{image as:alt|size:large}}' ) );
assert_eq( 'as absent + size → as:url,size prepended (recovered url default)',
	'{{image key:foo|as:url,medium}}', t_fold( '{{image size:medium|key:foo}}' ) );
assert_eq( 'no size key → unchanged (idempotent no-op)',
	'{{image as:url,full}}', t_fold( '{{image as:url,full}}' ) );
assert_eq( 'already-folded + stray legacy size → legacy dropped, keep folded mode+size',
	'{{image as:url,full}}', t_fold( '{{image as:url,full|size:medium}}' ) );
assert_eq( 'try_ slot 2: 2-size folds into 2-as, slot 1 untouched',
	'{{try_image as:url,full|2-as:url,large}}', t_fold( '{{try_image as:url,full|2-as:url|2-size:large}}' ) );
assert_eq( 'try_ slot 3 nullary: 3-size dropped',
	'{{try_image 3-as:caption}}', t_fold( '{{try_image 3-as:caption|3-size:medium}}' ) );
assert_eq( 'term_image url + size',
	'{{term_image as:url,thumbnail|key:logo}}', t_fold( '{{term_image as:url|size:thumbnail|key:logo}}' ) );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
