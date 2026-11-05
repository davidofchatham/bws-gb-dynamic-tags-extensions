<?php
/**
 * Registry payload bloat. NOT shipped with the plugin.
 *
 * Where the bytes of our options go in GeneratePress AI's tag list: by option key, by type,
 * the large repeated sub-blobs, and any `fold` left on a non-lead slot (should be 0, FW-186).
 * Needs GeneratePress AI active.
 *
 *   wsl.exe -d Ubuntu -- bash -lc 'cd ~/wp-litespeed && bin/wp.sh testbed eval-file \
 *     /plugins/bws-gb-dynamic-tags-extensions/tools/debug/registry-payload-bloat.php'
 */
wp_set_current_user( 1 );
$ai = ( new GeneratePress_AI_Dynamic_Tags() )->list_dynamic_tags();
$reg = GenerateBlocks_Register_Dynamic_Tag::get_tags();
$ours = array();
foreach ( $reg as $k => $d ) {
	$ret = $d['return'] ?? null; $file = '';
	if ( $ret instanceof Closure ) { $file = (new ReflectionFunction( $ret ))->getFileName(); }
	elseif ( is_string( $ret ) && function_exists( $ret ) ) { $file = (new ReflectionFunction( $ret ))->getFileName(); }
	elseif ( is_array( $ret ) && is_object( $ret[0] ?? null ) ) { $file = (new ReflectionMethod( $ret[0], $ret[1] ))->getFileName(); }
	if ( false !== strpos( (string) $file, 'bws-gb-dynamic' ) ) { $ours[ $k ] = true; }
}
$total = 0; $by_key = array(); $by_type = array(); $blobs = array(); $n_opts = 0;
foreach ( $ai['tags'] as $t ) {
	if ( empty( $ours[ $t['tag'] ] ) ) { continue; }
	foreach ( $t['options'] as $name => $o ) {
		$n_opts++;
		$total += strlen( wp_json_encode( $o ) );
		$by_type[ $o['type'] ?? '?' ] = ( $by_type[ $o['type'] ?? '?' ] ?? 0 ) + strlen( wp_json_encode( $o ) );
		foreach ( $o as $k => $v ) {
			$j = wp_json_encode( $v );
			$by_key[ $k ] = ( $by_key[ $k ] ?? 0 ) + strlen( $j );
			if ( strlen( $j ) > 600 ) { $h = md5( $j ); $blobs[ $h ] = $blobs[ $h ] ?? array( 'bytes' => strlen( $j ), 'n' => 0, 'key' => $k ); $blobs[ $h ]['n']++; }
		}
	}
}
arsort( $by_key ); arsort( $by_type );
echo "our options: $n_opts ; bytes: $total\n";
echo 'by option key: ' . wp_json_encode( array_slice( $by_key, 0, 10 ) ) . "\n";
echo 'by option type: ' . wp_json_encode( array_slice( $by_type, 0, 8 ) ) . "\n";
$dup = 0; $uniq = 0;
foreach ( $blobs as $b ) { $uniq += $b['bytes']; $dup += $b['bytes'] * ( $b['n'] - 1 ); }
echo 'big sub-blobs (>600B): distinct=' . count( $blobs ) . ' distinct bytes=' . $uniq . ' redundant repeat bytes=' . $dup . "\n";
uasort( $blobs, fn( $a, $b ) => $b['bytes'] * $b['n'] <=> $a['bytes'] * $a['n'] );
foreach ( array_slice( $blobs, 0, 6 ) as $h => $b ) { echo "  {$b['key']} x{$b['n']} @ {$b['bytes']}B\n"; }
// visibility + top-level non-option keys
$vis = 0; foreach ( $ai['tags'] as $t ) { if ( ! empty( $ours[ $t['tag'] ] ) ) { $vis += strlen( wp_json_encode( $t['visibility'] ) ); } }
echo "visibility bytes (ours): $vis\n";
// Per-tag: which slots carry fold. Only the lead should.
$left = 0; $rows = array();
foreach ( $ai['tags'] as $t ) {
	if ( empty( $ours[ $t['tag'] ] ) ) { continue; }
	$slots = 0; $carrying = 0; $bytes = 0; $lead = true;
	foreach ( $t['options'] as $name => $o ) {
		if ( 'bws-slot-fold' !== ( $o['type'] ?? '' ) ) { continue; }
		$slots++;
		if ( isset( $o['fold'] ) ) {
			$carrying++;
			$b = strlen( wp_json_encode( $o['fold'] ) );
			if ( $lead ) { $bytes = $b; } else { $left += $b; }
		}
		$lead = false;
	}
	if ( $slots ) { $rows[] = $t['tag'] . ':' . $slots . ' slots,' . $carrying . ' carrying,' . $bytes . 'B'; }
}
echo "fold bytes left on non-lead slots: $left B\n" . implode( ' | ', $rows ) . "\n";
