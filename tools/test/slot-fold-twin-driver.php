<?php
/**
 * PHP side of the grammar-twin check: run tools/test/slot-fold-corpus.json through
 * includes/helpers/slot-fold.php and print a CANONICAL JSON result document.
 *
 * Paired with tools/test/slot-fold-twin-driver.js, which does the same through
 * assets/js/slot-fold-grammar.js. tools/test/slot-fold-twin-test.php runs both and
 * diffs the documents; a difference is a twin divergence, i.e. author-facing wire
 * that the editor writes and the renderer reads differently.
 *
 * "Canonical" matters: PHP maps and JS objects do not agree on key order or on how
 * an empty map encodes (`[]` vs `{}`), so every struct is flattened to ORDERED
 * ARRAYS with map keys sorted. Nothing about the comparison may depend on a
 * language's own serialization choices.
 *
 * Not a harness — prints, never asserts. Run directly only when debugging a diff:
 *   php tools/test/slot-fold-twin-driver.php
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// One WP call in the compiled path (sanitize_key on a taxonomy slug). LOWERCASE
// FIRST, then strip — WP's order; the inverse deletes every capital.
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
}
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';

/** Flatten a chain step list to ordered arrays. */
function twin_canon_chain( $steps ) {
	if ( isset( $steps['error'] ) ) {
		return array( 'error', $steps['error'] );
	}
	$out = array();
	foreach ( $steps as $step ) {
		$out[] = array(
			$step['slug'],
			$step['arg'] ?? null,
			$step['limit'] ?? null,
			array_values( $step['extra'] ?? array() ),
		);
	}
	return $out;
}

/** Flatten a slot struct: fixed field order, map keys sorted, no assoc arrays. */
function twin_canon_slot( $slot ) {
	if ( null === $slot ) {
		return null;
	}
	if ( isset( $slot['error'] ) ) {
		return array( 'error', $slot['error'] );
	}
	$read = $slot['read'] ?? null;
	if ( null !== $read ) {
		$read = array( $read['kind'], $read['slug'] ?? ( $read['field'] ?? '' ) );
	}
	$opts = $slot['opts'] ?? array();
	ksort( $opts );
	$opt_pairs = array();
	foreach ( $opts as $name => $val ) {
		$opt_pairs[] = array( $name, true === $val ? true : (string) $val );
	}
	return array(
		'label' => $slot['label'] ?? null,
		'type'  => $slot['type'] ?? null,
		'chain' => twin_canon_chain( $slot['chain'] ?? array() ),
		'read'  => $read,
		'opts'  => $opt_pairs,
		'extra' => array_values( $slot['extra'] ?? array() ),
	);
}

$corpus = json_decode( file_get_contents( __DIR__ . '/slot-fold-corpus.json' ), true );

$doc = array(
	'grammar' => array(
		'optSep'       => BWS_FOLD_OPT_SEP,
		'optClass'     => BWS_FOLD_OPT_CLASS,
		'hopSep'       => BWS_FOLD_HOP_SEP,
		'hopClass'     => BWS_FOLD_HOP_CLASS,
		'stepSep'      => BWS_FOLD_STEP_SEP,
		'stepClass'    => BWS_FOLD_STEP_CLASS,
		'brPairs'      => array_map( null, array_keys( BWS_FOLD_BR_PAIRS ), array_values( BWS_FOLD_BR_PAIRS ) ),
		'reserved'     => BWS_FOLD_RESERVED,
		'types'        => BWS_FOLD_TYPES,
		'flags'        => BWS_FOLD_FLAGS,
		'freeform'     => BWS_FOLD_FREEFORM,
		'fanningSlugs' => BWS_FOLD_FANNING_SLUGS,
		'brackets'     => array( bws_fold_bracket_pair( 1 ), bws_fold_bracket_pair( 2 ), bws_fold_bracket_pair( 3 ) ),
	),
	'slots'   => array(),
	'chains'  => array(),
	'emits'   => array(),
	'legacy'  => array(),
);

foreach ( $corpus['slots'] as $case ) {
	$parsed = bws_fold_parse_slot( $case['wire'], $case['container'] );
	$doc['slots'][] = array(
		'parse' => twin_canon_slot( $parsed ),
		'emit'  => isset( $parsed['error'] ) ? null : bws_fold_emit_slot( $parsed ),
	);
}

foreach ( $corpus['chains'] as $case ) {
	$parsed = bws_fold_parse_chain( $case['chain'] );
	$doc['chains'][] = array(
		'parse' => twin_canon_chain( $parsed ),
		'emit'  => isset( $parsed['error'] ) ? null : bws_fold_emit_chain( $parsed, (int) $case['enclosingLevel'] ),
	);
}

// Emit-from-STRUCT: the only path that can hold a NUMERIC value (parse normalizes
// everything to strings), which is where a truthiness guard on `limit` drops a
// pinned 0 = unlimited.
foreach ( $corpus['emitStructs'] as $case ) {
	$wire           = bws_fold_emit_slot( $case['slot'], (int) $case['level'] );
	$doc['emits'][] = array(
		'emit'    => $wire,
		'reparse' => twin_canon_slot( bws_fold_parse_slot( $wire, 'table' ) ),
	);
}

foreach ( $corpus['legacy'] as $case ) {
	$rec = bws_fold_from_legacy(
		(int) $case['n'],
		$case['options'],
		(bool) $case['combining'],
		(bool) $case['perSlotUse']
	);
	$doc['legacy'][] = array(
		'slot' => null === $rec ? null : twin_canon_slot( $rec['slot'] ),
		'emit' => null === $rec ? null : bws_fold_emit_slot( $rec['slot'] ),
	);
}

// Depth-0 OPTION SETS. The JS half reads the same rules off the grammar, because the
// base-tag chain control has to write wire the renderer reads identically -- and the
// legacy flat triple plus the site-root guard are only visible from an option MAP.
foreach ( $corpus['srcOptions'] as $case ) {
	$chain = bws_fold_chain_from_options( $case['options'] );
	$res   = bws_fold_chain_resolution( $chain );
	$doc['srcOptions'][] = array(
		'isWire' => bws_fold_chain_is_wire( trim( (string) ( $case['options']['src'] ?? $case['options']['source'] ?? '' ) ) ),
		'chain'  => twin_canon_chain( $chain ),
		'root'   => $res['root'],
		'fans'   => $res['fans'],
	);
}

echo wp_json_encode_compat( $doc ), "\n";

/** json_encode with stable, readable output (no WP available here). */
function wp_json_encode_compat( $data ) {
	return json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
}
