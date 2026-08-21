<?php
/**
 * Standalone unit harness for bws_collect_usable() — the bounded source READER
 * (includes/helpers/field-helpers.php) — and its DORMANT opt-in predicate.
 *
 * The real function is loaded, not copied (house pattern: limit-clamp-test.php,
 * slot-fold-test.php): a test-local copy of the rule is the exact drift the
 * extraction out of try_'s emit loop exists to remove. The reader is injected
 * and the function names no WP symbol, which is what makes this possible.
 *
 * WHAT IS PINNED (the function's own PHPDoc is the axis owner; these rows check
 * its observable consequences — the 2026-08-21 determinism reversal, ADR 0007
 * §Why the read-based axis was reversed):
 *
 * DEFAULT PATH (no predicate) — the bound counts SOURCES READ:
 *   §D1 — an empty source list returns an empty set; the reader never runs.
 *   §D2 — at n = 1 only the FIRST source is read; an empty read returns an
 *         empty set WITHOUT the walk moving on. This row is the reversal's pin:
 *         the pre-reversal selector searched to the last candidate here, which
 *         made source selection depend on the field asked for.
 *   §D3 — an empty read keeps its slot: n = 2 over (usable, empty, usable)
 *         reads the first TWO sources and returns one value — `limit:2` can
 *         print one. Sources past the bound are never read.
 *   §D4 — n <= 0 reads every source; empties are dropped from the RETURN only.
 *   §D5 — a multi-item read keeps every non-empty item; n bounds SOURCES, so a
 *         2-item read at n = 1 returns both items (no tail slice).
 *
 * DORMANT PREDICATE PATH (bws_collect_usable_populated, FW-88 — wired to no
 * shipped caller; pinned here so it cannot rot while dormant):
 *   §P1 — the walk searches past failing sources without spending their slots
 *         (usable-last at n = 1 returns the last).
 *   §P2 — once $n surviving values exist THE READER IS NOT CALLED AGAIN
 *         (asserted on the call list — invisible in the return).
 *   §P3 — a multi-item read's overshoot is sliced off the tail; false/null
 *         fail the predicate, string '0' passes.
 *
 * Assertions are on the returned STRUCTURE (whole arrays), never on a count
 * reduced from one (feedback_acceptance_criteria_are_measurements).
 *
 * try_ PARITY IS PART OF THIS SEAM, not a separate one: the emit loop rides the
 * default path, and the try_ matrix rows (tools/test/fold-test-matrix.md) pin
 * the render.
 *
 * Run:  php tools/test/collect-usable-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/field-helpers.php';

$fails = 0;

/**
 * Structural equality assertion.
 *
 * @param string $label    Row label.
 * @param mixed  $expected Expected structure.
 * @param mixed  $actual   Actual structure.
 */
function eq( string $label, $expected, $actual ): void {
	global $fails;
	if ( $expected === $actual ) {
		echo "PASS  {$label}\n";
		return;
	}
	$fails++;
	echo "FAIL  {$label}\n";
	echo '      expected: ' . var_export( $expected, true ) . "\n";
	echo '      actual:   ' . var_export( $actual, true ) . "\n";
}

// A reader over a fixture map, counting its own calls.
function reader( array $map, array &$calls ): callable {
	return function ( $source ) use ( $map, &$calls ) {
		$calls[] = $source;
		return $map[ $source ] ?? '';
	};
}

// ── §D1 — empty source list ─────────────────────────────────────────────────
$calls = array();
eq( 'D1 empty source list → empty set', array(), bws_collect_usable( array(), reader( array(), $calls ), 1 ) );
eq( 'D1 reader never called', array(), $calls );

// ── §D2 — the reversal's pin: no search past an empty first source ──────────
$calls = array();
eq(
	'D2 usable candidate LAST at n=1 → EMPTY (selection is field-independent)',
	array(),
	bws_collect_usable( array( 'a', 'b', 'c' ), reader( array( 'a' => '', 'b' => '', 'c' => 'v3' ), $calls ), 1 )
);
eq( 'D2 only the first source was read — later candidates never consulted', array( 'a' ), $calls );

$calls = array();
eq(
	'D2 usable candidate FIRST at n=1',
	array( 'v1' ),
	bws_collect_usable( array( 'a', 'b' ), reader( array( 'a' => 'v1', 'b' => 'v2' ), $calls ), 1 )
);
eq( 'D2 the bound stopped the reads at one source', array( 'a' ), $calls );

// ── §D3 — an empty read keeps its slot ──────────────────────────────────────
$calls = array();
eq(
	'D3 n=2 over (usable, empty, usable) → ONE value; the empty kept its slot',
	array( 'v1' ),
	bws_collect_usable( array( 'a', 'x', 'b' ), reader( array( 'a' => 'v1', 'x' => '', 'b' => 'v2' ), $calls ), 2 )
);
eq( 'D3 exactly the first two sources were read', array( 'a', 'x' ), $calls );

// ── §D4 — n <= 0 unbounded; empties dropped from the return only ────────────
$calls = array();
eq(
	'D4 n=0 → every source read, empty values dropped from the return',
	array( 'v1', 'v2', 'v3' ),
	bws_collect_usable( array( 'a', 'x', 'b', 'c' ), reader( array( 'a' => 'v1', 'x' => '', 'b' => 'v2', 'c' => 'v3' ), $calls ), 0 )
);
eq( 'D4 n=0 examined everything', array( 'a', 'x', 'b', 'c' ), $calls );

$calls = array();
eq(
	'D4 negative n → unbounded (matches bws_clamp_limit\'s tolerant -1)',
	array( 'v1', 'v2' ),
	bws_collect_usable( array( 'a', 'b' ), reader( array( 'a' => 'v1', 'b' => 'v2' ), $calls ), -1 )
);

// false and null are dropped from the return; string '0' survives.
$calls = array();
eq(
	'D4b false/null dropped from the return; string "0" kept',
	array( '0' ),
	bws_collect_usable( array( 'f', 'n', 'z' ), reader( array( 'f' => false, 'n' => null, 'z' => '0' ), $calls ), 0 )
);

// ── §D5 — multi-item reads: n bounds SOURCES, not items ─────────────────────
$multi = function ( $source ) {
	return array( 'list' => array( 'p', '', 'q' ), 'one' => array( 'r' ) )[ $source ] ?? array();
};
eq(
	'D5 multi-item read at n=1: BOTH usable items return (no tail slice)',
	array( 'p', 'q' ),
	bws_collect_usable( array( 'list', 'one' ), $multi, 1 )
);
eq(
	'D5 unbounded: items flatten in order, empties dropped',
	array( 'p', 'q', 'r' ),
	bws_collect_usable( array( 'list', 'one' ), $multi, 0 )
);

// ── §P — the DORMANT predicate path (FW-88), pinned while wired to nothing ──
$pred = 'bws_collect_usable_populated';

$calls = array();
eq(
	'P1 predicate: usable candidate LAST at n=1 is FOUND (the search survives dormant)',
	array( 'v3' ),
	bws_collect_usable( array( 'a', 'b', 'c' ), reader( array( 'a' => '', 'b' => '', 'c' => 'v3' ), $calls ), 1, $pred )
);
eq( 'P1 the whole list was searched to find it', array( 'a', 'b', 'c' ), $calls );

$calls = array();
eq(
	'P2 predicate: n reached mid-list → bounded result',
	array( 'v1', 'v2' ),
	bws_collect_usable(
		array( 'a', 'x', 'b', 'c', 'd' ),
		reader( array( 'a' => 'v1', 'x' => '', 'b' => 'v2', 'c' => 'v3', 'd' => 'v4' ), $calls ),
		2,
		$pred
	)
);
eq( 'P2 READER NOT CALLED past the fill — survivors counted, not candidates', array( 'a', 'x', 'b' ), $calls );

eq(
	'P3 predicate: overshoot within one read is sliced off the tail',
	array( 'p' ),
	bws_collect_usable( array( 'list', 'one' ), $multi, 1, $pred )
);
eq( 'P3 predicate: false/null fail, string "0" passes', true, bws_collect_usable_populated( '0' ) && ! bws_collect_usable_populated( false ) && ! bws_collect_usable_populated( null ) && ! bws_collect_usable_populated( '' ) );

echo $fails ? "\n{$fails} FAILURE(S)\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );
