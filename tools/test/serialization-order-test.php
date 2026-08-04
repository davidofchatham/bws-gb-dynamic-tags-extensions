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

// --- Folded slot keys (FW-56/57): all-digit keys LEAD the whole string, slot order ---
// NOT a preference (1.17.0). An all-digit key is a JS array-index property, which
// ECMAScript enumerates before every string key whatever order the object was built in;
// GB serializes with `Object.entries(extraTagParams)`, so the editor CANNOT emit a named
// option ahead of a folded slot. The canonical order states what the editor is forced to
// write, so the converter and the editor stop writing one tag two ways. The JS-port block
// below is what holds the two sides together — this expectation and that one cannot
// disagree silently.
assert_order(
	'folded slot keys LEAD, ahead of the format group, and sort by slot',
	array( '1', '2', '3', 'mode', 'valueSep', 'format', 'fallback' ),
	$sort( array( '3', 'fallback', '1', 'format', 'mode', '2', 'valueSep' ) )
);
// Mixed era on ONE slot (a half-applied migration or hand-edit): folded leads flat,
// because the fold dimension precedes slot — not because of any same-slot rule.
assert_order(
	'a folded slot key leads the flat keys of the same slot',
	array( '2', '3', '2-zeta' ),
	$sort( array( '2-zeta', '3', '2' ) )
);
assert_order(
	'a folded slot key leads its slot\'s unknown flat key',
	array( '1', '1-zeta' ),
	$sort( array( '1-zeta', '1' ) )
);
// Mixed era across slots: every folded key first (by slot), then the flat keys in their
// own canonical order.
assert_order(
	'mixed-era wire puts folded slots first, then the flat keys',
	array( '1', '3', '2-src', '2-key' ),
	$sort( array( '3', '2-key', '1', '2-src' ) )
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
$count++;
if ( array( 2, '' ) === bws_serialization_order_parse_slot( '2' ) ) { echo "  ok   folded slot key 2 → slot 2, empty bare name\n"; } else { $failures++; echo "  FAIL folded slot key 2 → [2, '']\n"; }
$count++;
if ( array( 12, '' ) === bws_serialization_order_parse_slot( '12' ) ) { echo "  ok   folded slot key 12 → slot 12 (multi-digit)\n"; } else { $failures++; echo "  FAIL folded slot key 12\n"; }

// --- JS PORT AGREEMENT (assets/js/serialization-order-normalizer.js) ---
//
// The header used to claim asserting the PHP mirror pins what the JS enforces. It
// pins the CONTRACT, not the port: two files, so agreement is a tested property, not
// a discipline (the same reasoning as tools/test/slot-fold-twin-test.php, which is
// where this convention landed first). The editor's normalizer is the side that
// WRITES author-facing wire, so a divergence here is not a failing test in
// production — it is churn in saved tags.
//
// `node` is a REAL dependency: a missing node FAILS rather than skips, because a
// silent pass would hide exactly the drift this block exists to catch.
echo "\nJS port agreement (node)\n";
$twin_lists = array(
	array( 'fallback', 'linkTo', 'src', 'key', 'use', 'ref', 'srcTermIn', 'limit', 'sep', 'linkKey', 'newTab' ),
	array( 'as', 'key', 'src' ),
	array( 'src', 'key', 'timeKey', 'fallback', 'as', 'format' ),
	array( 'sep', 'key', 'src', 'valueSep', 'srcTermIn', 'limit', 'fallback' ),
	array( 'zeta', 'src', 'linkTo', 'alpha', 'fallback', 'key' ),
	array( '2-key', 'src', '3-src', 'key', '2-src', '3-key' ),
	// Folded slot keys (FW-56/57) — the parseSlot branch added in 1.17.0.
	array( '3', 'fallback', '1', 'format', 'mode', '2', 'valueSep' ),
	array( '2-zeta', '3', '2' ),
	array( '3', '2-key', '1', '2-src' ),
	array( '12', '2', '1' ),
);
$expected_twin = array_map( $sort, $twin_lists );
// Hand the corpus over as a FILE, not an argv string: Windows' escapeshellarg strips
// the double quotes out of JSON, so an inline argument arrives as unparsable garbage.
$twin_file     = tempnam( sys_get_temp_dir(), 'bws-order-' );
file_put_contents( $twin_file, json_encode( $twin_lists ) );
$cmd           = 'node ' . escapeshellarg( __DIR__ . '/serialization-order-driver.js' )
	. ' ' . escapeshellarg( $twin_file ) . ' 2>&1';
$raw           = (string) shell_exec( $cmd );
unlink( $twin_file );
$doc           = json_decode( $raw, true );
$count++;
if ( ! is_array( $doc ) || ! isset( $doc['reordered'] ) ) {
	$failures++;
	echo "  FAIL JS driver produced no result (node on PATH?)\n";
	echo '       ' . substr( trim( $raw ), 0, 300 ) . "\n";
} else {
	echo "  ok   JS driver ran (node present, normalizer exported)\n";
	foreach ( $expected_twin as $i => $php_order ) {
		$count++;
		$js_order = $doc['reordered'][ $i ] ?? null;
		if ( $php_order === $js_order ) {
			echo '  ok   twin agrees: ' . implode( ' ', $twin_lists[ $i ] ) . "\n";
			continue;
		}
		$failures++;
		echo '  FAIL twin diverges on: ' . implode( ' ', $twin_lists[ $i ] ) . "\n";
		echo '       php: ' . implode( ' ', $php_order ) . "\n";
		echo '       js:  ' . ( is_array( $js_order ) ? implode( ' ', $js_order ) : var_export( $js_order, true ) ) . "\n";
	}
}

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
