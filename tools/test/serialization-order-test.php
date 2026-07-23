<?php
/**
 * Standalone unit harness for the FW-52 canonical serialization-order model in
 * includes/helpers/serialization-order.php.
 *
 * Pure array transform — no WordPress required. `serialization-order.php` guards its
 * top level with ABSPATH and defines only pure functions, so we define ABSPATH and
 * require it directly (no WP shims needed).
 *
 * This harness is the committed, CI-runnable spec of the ordering contract the editor
 * JS normalizer (assets/js/serialization-order-normalizer.js) enforces. The JS runs the
 * identical algorithm on Object.keys(extraTagParams); asserting the PHP mirror pins the
 * canonical order, format-front lift, and N-slot contiguity without a JS test runner.
 *
 * SCOPE — bws_serialization_order_sort() (+ its slot parser):
 *   - format group LEADS (as/format/… before source)
 *   - link AFTER source; fallback LAST
 *   - within-slot order src → ref → srcTermIn → limit → sep → use → key → datetime keys
 *   - each N- slot's keys stay contiguous, slots ascend
 *   - the decisive as-reset front-pull (as appended last → pulled to lead)
 *   - unknown keys tail the source group, keep incoming order (stable)
 *   - size (GB-reserved) ranks in format but is a no-op in practice (never present)
 *
 * Run:
 *   php tools/test/serialization-order-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/serialization-order.php';

$failures = 0;
$count    = 0;

/**
 * Assert two ordered key lists are identical (===, order-sensitive).
 */
function assert_order( string $label, array $expected, array $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . implode( ' ', $expected ) . "\n";
	echo "       actual:   " . implode( ' ', $actual ) . "\n";
}

$sort = 'bws_serialization_order_sort';

echo "bws_serialization_order_sort\n";

// --- Canonical order across all four groups (text-shaped tag) ---
assert_order(
	'full text tag: format(none) → source → link → fallback',
	array( 'src', 'ref', 'srcTermIn', 'limit', 'sep', 'use', 'key', 'linkTo', 'linkKey', 'newTab', 'fallback' ),
	$sort( array( 'fallback', 'linkTo', 'src', 'key', 'use', 'ref', 'srcTermIn', 'limit', 'sep', 'linkKey', 'newTab' ) )
);

// --- Single-slot canonicalization within present keys (spike console case) ---
assert_order(
	'as,key,src → as,src,key (image within-group canonical)',
	array( 'as', 'src', 'key' ),
	$sort( array( 'as', 'key', 'src' ) )
);

// --- format group lifted to the front ---
assert_order(
	'format lifted ahead of source (datetime-shaped)',
	array( 'as', 'format', 'src', 'key', 'timeKey', 'fallback' ),
	$sort( array( 'src', 'key', 'timeKey', 'fallback', 'as', 'format' ) )
);

// --- The decisive as-reset front-pull (as appended LAST → pulled to lead) ---
assert_order(
	'as appended last is pulled to the front',
	array( 'as', 'src', 'use', 'key' ),
	$sort( array( 'src', 'use', 'key', 'as' ) )
);

// --- link AFTER source, fallback LAST (link/fallback not interleaved with source) ---
assert_order(
	'link group serializes after source, before fallback',
	array( 'src', 'use', 'key', 'linkTo', 'linkKey', 'newTab', 'fallback' ),
	$sort( array( 'linkTo', 'fallback', 'src', 'newTab', 'use', 'linkKey', 'key' ) )
);

// --- email/phone own-anchor link set: subject → noLink, ranked as link ---
assert_order(
	'email own-anchor set subject → noLink sorts as link group',
	array( 'src', 'key', 'subject', 'noLink', 'fallback' ),
	$sort( array( 'noLink', 'subject', 'fallback', 'key', 'src' ) )
);

// --- Multi-slot contiguity: each N- slot's keys stay together, slots ascend ---
// linkTo is chain-level (link group) → serializes AFTER all source slots, before fallback.
assert_order(
	'try_ 3-slot: each slot contiguous, ascending; chain-level link after slots',
	array( 'src', 'use', 'key', '2-src', '2-use', '2-key', '3-src', '3-use', '3-key', 'linkTo', 'fallback' ),
	$sort( array( '3-src', 'src', 'linkTo', '2-use', 'use', '3-use', '2-src', 'key', 'fallback', '2-key', '3-key' ) )
);

// --- Multi-slot reset-scatter: a key added to an earlier slot LAST rejoins its slot ---
// Author revised slot 1, added 1-key which GB appended globally-last (after 3-src);
// normalizer must pull it back adjacent to its slot-1 siblings.
assert_order(
	'late-added earlier-slot key rejoins its slot (reset-scatter fix)',
	array( 'src', 'use', 'key', '2-src', '2-use', '3-src' ),
	$sort( array( 'src', 'use', '2-src', '2-use', '3-src', 'key' ) )
);

// --- Slot within-order: src → ref → srcTermIn → limit → sep → use → key ---
assert_order(
	'within-slot canonical order incl limit/sep before field keys',
	array( '2-src', '2-ref', '2-srcTermIn', '2-limit', '2-sep', '2-use', '2-key' ),
	$sort( array( '2-key', '2-use', '2-sep', '2-limit', '2-srcTermIn', '2-ref', '2-src' ) )
);

// --- datetime range field keys ordering (all in source group after key) ---
assert_order(
	'datetime range: format leads, range keys in source order, fallback last',
	array( 'as', 'format', 'rangeSep', 'src', 'startKey', 'startTimeKey', 'endKey', 'endTimeKey', 'fallback' ),
	$sort( array( 'endTimeKey', 'src', 'startKey', 'endKey', 'startTimeKey', 'rangeSep', 'format', 'as', 'fallback' ) )
);

// --- join tag-level assembly keys sort into the format group: mode → valueSep →
// format, ahead of the per-slot source keys. valueSep (renamed from sep, FW-52)
// is a format concern, NOT the source-group list sep. ---
assert_order(
	'join format group (mode → valueSep → format) leads source slots',
	array( 'mode', 'valueSep', 'src', 'key', '2-src', '2-key', 'fallback' ),
	$sort( array( '2-key', 'key', 'src', 'valueSep', 'fallback', '2-src', 'mode' ) )
);
// A join slot CAN carry a source-group `sep` (list mode) independently — it stays
// in source, so both separators coexist without collision (the FW-52 point).
assert_order(
	'join valueSep (format) and slot sep (source) coexist, correctly grouped',
	array( 'valueSep', 'src', 'srcTermIn', 'limit', 'sep', 'key', 'fallback' ),
	$sort( array( 'sep', 'key', 'src', 'valueSep', 'srcTermIn', 'limit', 'fallback' ) )
);

// --- Unknown key: tails the source group, keeps incoming order relative to peers ---
assert_order(
	'unknown keys tail source, before link/fallback, stable among themselves',
	array( 'src', 'key', 'zeta', 'alpha', 'linkTo', 'fallback' ),
	$sort( array( 'zeta', 'src', 'linkTo', 'alpha', 'fallback', 'key' ) )
);

// --- Idempotence: canonical input returns unchanged ---
$canonical = array( 'as', 'src', 'ref', 'srcTermIn', 'limit', 'sep', 'use', 'key', 'linkTo', 'linkKey', 'newTab', 'fallback' );
assert_order( 'already-canonical input is unchanged (idempotent)', $canonical, $sort( $canonical ) );
assert_order( 'sort is idempotent (double-apply == single)', $sort( $canonical ), $sort( $sort( $canonical ) ) );

// --- Empty + singletons ---
assert_order( 'empty list → empty', array(), $sort( array() ) );
assert_order( 'single key unchanged', array( 'src' ), $sort( array( 'src' ) ) );

// --- Slot parser directly ---
echo "\nbws_serialization_order_parse_slot\n";
$count++;
if ( array( 0, 'src' ) === bws_serialization_order_parse_slot( 'src' ) ) { echo "  ok   base key → slot 0\n"; } else { $failures++; echo "  FAIL base key → slot 0\n"; }
$count++;
if ( array( 2, 'src' ) === bws_serialization_order_parse_slot( '2-src' ) ) { echo "  ok   2-src → slot 2, bare src\n"; } else { $failures++; echo "  FAIL 2-src → slot 2\n"; }
$count++;
if ( array( 10, 'key' ) === bws_serialization_order_parse_slot( '10-key' ) ) { echo "  ok   10-key → slot 10 (multi-digit)\n"; } else { $failures++; echo "  FAIL 10-key → slot 10\n"; }

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
