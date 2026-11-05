<?php
/**
 * GeneratePress AI exposure check. NOT shipped with the plugin.
 *
 * Measures, against the live registry on the testbed, what the descriptions and
 * `aiSyntax` hints do for GeneratePress AI (GPAI): per tag of ours, a description that
 * survives sanitize_text_field() unchanged, the hint bytes, the editor-shaped registry
 * size, and (when GPAI is active) that its tag list returns the same strings and how many
 * bytes the hints add to it. Rerun when GPAI updates: its behavior is not ours to keep true.
 *
 *   wsl.exe -d Ubuntu -- bash -lc 'cd ~/wp-litespeed && bin/wp.sh testbed eval-file \
 *     /plugins/bws-gb-dynamic-tags-extensions/tools/debug/gpai-exposure-check.php'
 *
 * Run it with GPAI active, then deactivated: inactive, "contains aiSyntax" must read "no".
 * The MCP wire half is gpai-mcp-roundtrip.sh beside this file. Prints a report; asserts nothing.
 */
$reg = GenerateBlocks_Register_Dynamic_Tag::get_tags();
$ours = array();
foreach ( $reg as $k => $d ) {
	$ret = $d['return'] ?? null; $file = '';
	if ( $ret instanceof Closure ) { $file = (new ReflectionFunction( $ret ))->getFileName(); }
	elseif ( is_string( $ret ) && function_exists( $ret ) ) { $file = (new ReflectionFunction( $ret ))->getFileName(); }
	elseif ( is_array( $ret ) && is_object( $ret[0] ?? null ) ) { $file = (new ReflectionMethod( $ret[0], $ret[1] ))->getFileName(); }
	elseif ( is_array( $ret ) && is_string( $ret[0] ?? null ) ) { $file = (new ReflectionMethod( $ret[0], $ret[1] ))->getFileName(); }
	if ( false !== strpos( (string) $file, 'bws-gb-dynamic' ) ) { $ours[ $k ] = true; }
}
echo 'GPAI constant: ' . ( defined( 'GENERATEPRESS_AI_VERSION' ) ? GENERATEPRESS_AI_VERSION : 'undefined' ) . "\n";
echo 'registered total: ' . count( $reg ) . '; ours by file: ' . count( $ours ) . "\n";
$sum_ai = 0; $n_ai = 0; $bad = array();
foreach ( array_keys( $ours ) as $t ) {
	$d = $reg[ $t ]; $desc = (string) ( $d['description'] ?? '' );
	$ok = '' !== $desc && sanitize_text_field( $desc ) === $desc;
	$ai = array();
	foreach ( (array) ( $d['options'] ?? array() ) as $k => $o ) { if ( isset( $o['aiSyntax'] ) ) { $ai[ $k ] = strlen( $o['aiSyntax'] ); } }
	$sum_ai += array_sum( $ai ); $n_ai += count( $ai );
	if ( ! $ok ) { $bad[] = $t; }
	echo str_pad( $t, 22 ) . ' desc=' . ( $ok ? 'ok ' : 'BAD' ) . ' ' . str_pad( (string) strlen( $desc ), 4 ) . ' ai=' . ( $ai ? json_encode( $ai ) : '-' ) . "\n";
}
echo "registered aiSyntax: $n_ai options, $sum_ai bytes; bad descriptions: " . ( $bad ? implode( ',', $bad ) : 'none' ) . "\n";
// Registry-level payload as GB localizes it (minus return).
$strip = function ( $tags ) { foreach ( $tags as &$t ) { unset( $t['return'] ); } return $tags; };
$all = $strip( $reg ); $json = wp_json_encode( $all );
echo 'editor-shaped registry JSON bytes: ' . strlen( $json ) . '; contains aiSyntax: ' . ( false !== strpos( $json, 'aiSyntax' ) ? 'YES' : 'no' ) . "\n";
if ( class_exists( 'GeneratePress_AI_Dynamic_Tags' ) ) {
	wp_set_current_user( 1 );
	$r = ( new GeneratePress_AI_Dynamic_Tags() )->list_dynamic_tags();
	$lj = wp_json_encode( $r );
	echo 'GPAI list: tags=' . count( $r['tags'] ) . ' bytes=' . strlen( $lj ) . "\n";
	$n = 0; $b = 0; $mism = 0; $dmis = array(); $got = array();
	foreach ( $r['tags'] as $t ) {
		$name = $t['tag'];
		if ( empty( $ours[ $name ] ) ) { continue; }
		$got[ $name ] = true;
		if ( ( $t['description'] ?? '' ) !== (string) $reg[ $name ]['description'] || '' === ( $t['description'] ?? '' ) ) { $dmis[] = $name; }
		foreach ( $t['options'] as $k => $o ) {
			if ( isset( $o['aiSyntax'] ) ) { $n++; $b += strlen( $o['aiSyntax'] ); if ( $o['aiSyntax'] !== $reg[ $name ]['options'][ $k ]['aiSyntax'] ) { $mism++; } }
		}
	}
	echo 'ours in list: ' . count( $got ) . '; desc mismatch/empty: ' . ( $dmis ? implode( ',', $dmis ) : 'none' ) . "; aiSyntax in list: $n options, $b bytes, mismatched vs registry: $mism\n";
	// payload without aiSyntax
	$strip_ai = function ( $v ) use ( &$strip_ai ) { if ( is_array( $v ) ) { unset( $v['aiSyntax'] ); foreach ( $v as $k => $x ) { $v[ $k ] = $strip_ai( $x ); } } return $v; };
	echo 'GPAI list bytes without aiSyntax: ' . strlen( wp_json_encode( $strip_ai( $r ) ) ) . "\n";
}
