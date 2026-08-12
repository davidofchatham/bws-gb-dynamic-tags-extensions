<?php
/**
 * PHP↔JS grammar-TWIN agreement harness (FW-56/57).
 *
 * The folded slot wire is parsed by PHP at render/migration time and both parsed AND
 * EMITTED by JS in the editor. Two languages, so one implementation cannot serve
 * both and elimination is unavailable — which makes agreement a TESTED property
 * rather than a discipline. This harness is that test: one shared input corpus
 * (tools/test/slot-fold-corpus.json), two drivers, one diff.
 *
 * WHY IT IS SEPARATE FROM slot-fold-test.php: that harness pins what the PHP owner
 * DOES (expected values, case by case). This one asks only whether the twin does the
 * SAME — so a new case costs one corpus line and no expectation. The failure it
 * catches is also different in kind: a twin divergence does not fail a suite in
 * production, it corrupts author-facing wire, because the editor writes what the
 * renderer then reads differently. The spike carried four copies of the grammar and
 * all four sat on a superseded separator at once, precisely because nothing forced
 * them to agree.
 *
 * Scope note: agreement, not correctness. Both sides being wrong in the same way
 * passes here and fails in slot-fold-test.php. Both are required.
 *
 * Requires `node` on PATH. This is the FIRST harness in tools/test/ that is not pure
 * PHP — a deliberate convention change (CLAUDE.md §Development), because the thing
 * under test is a cross-language property and a PHP-only harness structurally cannot
 * see it. A missing `node` is a FAILURE, not a skip: silently passing would hide the
 * exact drift this file exists to catch.
 *
 * Run:  php tools/test/slot-fold-twin-test.php
 * Exit 0 = the two implementations agree on every corpus case.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$here = __DIR__;
$pass = 0;
$fail = 0;

function check( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		return;
	}
	$fail++;
	echo "FAIL  $label\n";
	if ( '' !== $detail ) {
		echo "      $detail\n";
	}
}

/** Run a driver, returning [ decoded doc | null, raw output ]. */
function twin_run( $cmd ) {
	$output = shell_exec( $cmd . ' 2>&1' );
	$doc    = json_decode( (string) $output, true );
	return array( $doc, (string) $output );
}

list( $php_doc, $php_raw ) = twin_run( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $here . '/slot-fold-twin-driver.php' ) );
check( 'PHP driver produced JSON', is_array( $php_doc ), substr( $php_raw, 0, 800 ) );

list( $js_doc, $js_raw ) = twin_run( 'node ' . escapeshellarg( $here . '/slot-fold-twin-driver.js' ) );
check( 'JS driver produced JSON (node on PATH)', is_array( $js_doc ), substr( $js_raw, 0, 800 ) );

if ( ! is_array( $php_doc ) || ! is_array( $js_doc ) ) {
	echo "\n$pass passed, $fail failed\n";
	exit( 1 );
}

$corpus = json_decode( file_get_contents( $here . '/slot-fold-corpus.json' ), true );

/** Compare one value pair, reporting the JSON of each side on a mismatch. */
function twin_same( $label, $a, $b ) {
	$ja = json_encode( $a, JSON_UNESCAPED_SLASHES );
	$jb = json_encode( $b, JSON_UNESCAPED_SLASHES );
	check( $label, $ja === $jb, "php: $ja\n      js:  $jb" );
}

// ── Grammar constants ───────────────────────────────────────────────────────
// Asserted field by field rather than as one blob: a whole-object diff reports
// "the grammar differs", which is the least useful possible message when the
// answer needed is WHICH separator moved.
foreach ( array_keys( $php_doc['grammar'] ) as $field ) {
	twin_same( "grammar.$field agrees", $php_doc['grammar'][ $field ], $js_doc['grammar'][ $field ] ?? null );
}
$js_only = array_diff( array_keys( $js_doc['grammar'] ), array_keys( $php_doc['grammar'] ) );
check( 'grammar surface has no JS-only fields', array() === $js_only, implode( ', ', $js_only ) );

// ── Slot parse + emit ───────────────────────────────────────────────────────
foreach ( $corpus['slots'] as $i => $case ) {
	$wire  = $case['wire'];
	$label = "slot[$i] {$case['container']}: " . ( '' === $wire ? '(empty)' : $wire );
	twin_same( "$label — parse", $php_doc['slots'][ $i ]['parse'] ?? 'MISSING', $js_doc['slots'][ $i ]['parse'] ?? 'MISSING' );
	twin_same( "$label — emit", $php_doc['slots'][ $i ]['emit'] ?? 'MISSING', $js_doc['slots'][ $i ]['emit'] ?? 'MISSING' );
}

// ── Chain parse + emit (emit carries the depth-alternation rule) ────────────
foreach ( $corpus['chains'] as $i => $case ) {
	$label = "chain[$i] @L{$case['enclosingLevel']}: {$case['chain']}";
	twin_same( "$label — parse", $php_doc['chains'][ $i ]['parse'] ?? 'MISSING', $js_doc['chains'][ $i ]['parse'] ?? 'MISSING' );
	twin_same( "$label — emit", $php_doc['chains'][ $i ]['emit'] ?? 'MISSING', $js_doc['chains'][ $i ]['emit'] ?? 'MISSING' );
}

// ── Emit from STRUCT (the numeric-value path parse cannot reach) ────────────
foreach ( $corpus['emitStructs'] as $i => $case ) {
	$label = "emit[$i] @L{$case['level']}: " . json_encode( $case['slot'], JSON_UNESCAPED_SLASHES );
	twin_same( "$label — emit", $php_doc['emits'][ $i ]['emit'] ?? 'MISSING', $js_doc['emits'][ $i ]['emit'] ?? 'MISSING' );
	twin_same( "$label — reparse", $php_doc['emits'][ $i ]['reparse'] ?? 'MISSING', $js_doc['emits'][ $i ]['reparse'] ?? 'MISSING' );
}
// A pinned `limit[0]` must reach the wire from a NUMERIC struct on BOTH sides — the
// falsy-collision bug class already found three times on this axis.
check( 'numeric limit 0 survives emit (php)', false !== strpos( (string) ( $php_doc['emits'][0]['emit'] ?? '' ), 'limit[0]' ), (string) ( $php_doc['emits'][0]['emit'] ?? '' ) );
check( 'numeric limit 0 survives emit (js)', false !== strpos( (string) ( $js_doc['emits'][0]['emit'] ?? '' ), 'limit[0]' ), (string) ( $js_doc['emits'][0]['emit'] ?? '' ) );

// ── Legacy mapping (the container-sensitive half) ───────────────────────────
foreach ( $corpus['legacy'] as $i => $case ) {
	$label = "legacy[$i] n={$case['n']} " . ( $case['combining'] ? 'combining' : 'selecting' )
		. ( $case['perSlotUse'] ? '' : ' (no psu)' ) . ': ' . json_encode( $case['options'], JSON_UNESCAPED_SLASHES );
	twin_same( "$label — slot", $php_doc['legacy'][ $i ]['slot'] ?? 'MISSING', $js_doc['legacy'][ $i ]['slot'] ?? 'MISSING' );
	twin_same( "$label — emit", $php_doc['legacy'][ $i ]['emit'] ?? 'MISSING', $js_doc['legacy'][ $i ]['emit'] ?? 'MISSING' );
}

// ── Depth-0 OPTION SETS (the base tag's source) ─────────────────────────────
//
// The chain COMPILER has no JS twin by construction — the editor never RUNS a
// chain. But the base-tag chain control does have to READ one, and it writes the
// wire the renderer reads back, so this half of the compiler IS twinned:
// bws_fold_chain_from_options / _resolution / _is_wire against chainFromOptions
// plus the grammar's chainIsWire / chainRoot / chainFans.
//
// The input is an OPTION MAP rather than a chain string, which is the point: the
// legacy flat triple is half the rule, and the site-root guard on `srcTermIn` is
// invisible from a chain string alone.
foreach ( $corpus['srcOptions'] as $i => $case ) {
	$label = "srcOptions[$i]: " . json_encode( $case['options'], JSON_UNESCAPED_SLASHES );
	foreach ( array( 'isWire', 'chain', 'root', 'fans' ) as $axis ) {
		twin_same(
			"$label — $axis",
			$php_doc['srcOptions'][ $i ][ $axis ] ?? 'MISSING',
			$js_doc['srcOptions'][ $i ][ $axis ] ?? 'MISSING'
		);
	}
}

// ── Coverage floor ─────────────────────────────────────────────────────────
// A corpus that quietly shrinks would pass with fewer comparisons and read as
// green. Pin the shape of what MUST be covered, not just the count.
check( 'srcOptions corpus covers both spellings and the site guard',
	count( $corpus['srcOptions'] ) >= 15,
	count( $corpus['srcOptions'] ) . ' option sets' );
check( 'corpus covers ≥ 40 slot cases', count( $corpus['slots'] ) >= 40, count( $corpus['slots'] ) . ' cases' );
check( 'corpus covers both wrapper depths', 2 === count( array_unique( array_column( $corpus['chains'], 'enclosingLevel' ) ) ) || count( array_unique( array_column( $corpus['chains'], 'enclosingLevel' ) ) ) > 2, '' );
$malformed = array_filter( $php_doc['slots'], static function ( $r ) {
	return is_array( $r['parse'] ) && isset( $r['parse'][0] ) && 'error' === $r['parse'][0];
} );
check( 'corpus covers malformed input (both sides must AGREE on rejection)', count( $malformed ) >= 8, count( $malformed ) . ' rejected cases' );
$containers = array_unique( array_column( $corpus['slots'], 'container' ) );
sort( $containers );
twin_same( 'corpus covers all three containers', array( 'join', 'table', 'try' ), $containers );
$combining_legacy = array_filter( $corpus['legacy'], static function ( $c ) {
	return $c['combining'];
} );
check( 'corpus covers combining legacy slots (the container-divergent half)', count( $combining_legacy ) >= 4, count( $combining_legacy ) . ' cases' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
