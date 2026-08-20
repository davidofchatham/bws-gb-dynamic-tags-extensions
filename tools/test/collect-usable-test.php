<?php
/**
 * Standalone unit harness for bws_collect_usable() — the usable-result SELECTOR
 * (includes/helpers/field-helpers.php).
 *
 * The real function is loaded, not copied (house pattern: limit-clamp-test.php,
 * slot-fold-test.php): a test-local copy of the walk-and-count rule is the exact
 * drift the extraction out of try_'s emit loop exists to remove. The reader is
 * injected and the function names no WP symbol, which is what makes this possible.
 *
 * WHAT IS PINNED (the rule's own PHPDoc is the axis owner; these rows check its
 * observable consequences):
 *   §C1 — an empty source list returns an empty set.
 *   §C2 — every read empty returns an EMPTY set, not a set of empties.
 *   §C3 — the usable candidate first / last both return it at n = 1. The "last"
 *         row is the shape the collapsing base tags returned NOTHING for on the
 *         previous build (the post branch read candidate 1 and gave up).
 *   §C4 — once $n usable results exist THE READER IS NOT CALLED AGAIN. Asserted
 *         on the CALL COUNT, because the difference between counting results and
 *         counting candidates is invisible in the return value.
 *   §C5 — $n of zero or less means unbounded (the codebase's spelling of "no
 *         limit"); $n larger than the usable population returns everything
 *         usable, without padding.
 *   §C6 — a single read may return SEVERAL items; every usable one counts and
 *         the overshoot is sliced off the tail (the try_ emit loop's shape,
 *         kept verbatim through the extraction).
 *
 * Assertions are on the returned STRUCTURE (whole arrays), never on a count
 * reduced from one — a count that happens to match is the failure mode this
 * repo has already been bitten by (feedback_acceptance_criteria_are_measurements).
 *
 * try_ PARITY IS PART OF THIS SEAM, not a separate one: the emit loop's swap
 * onto the selector must be a no-op at render, and the existing try_ matrix rows
 * (tools/test/fold-test-matrix.md) are that pin.
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

// A reader over a fixture map, counting its own calls. '' entries are unusable.
function reader( array $map, array &$calls ): callable {
	return function ( $source ) use ( $map, &$calls ) {
		$calls[] = $source;
		return $map[ $source ] ?? '';
	};
}

// ── §C1 — empty source list ─────────────────────────────────────────────────
$calls = array();
eq( 'C1 empty source list → empty set', array(), bws_collect_usable( array(), reader( array(), $calls ), 1 ) );
eq( 'C1 reader never called', array(), $calls );

// ── §C2 — every read empty ──────────────────────────────────────────────────
$calls = array();
eq(
	'C2 all-empty reads → empty set, not a set of empties',
	array(),
	bws_collect_usable( array( 'a', 'b', 'c' ), reader( array( 'a' => '', 'b' => '', 'c' => '' ), $calls ), 1 )
);
eq( 'C2 every candidate was examined', array( 'a', 'b', 'c' ), $calls );

// false and null reads are unusable too (the donor loop's normalizer predicate).
$calls = array();
eq(
	'C2b false/null reads are unusable; string "0" is usable',
	array( '0' ),
	bws_collect_usable(
		array( 'f', 'n', 'z' ),
		reader( array( 'f' => false, 'n' => null, 'z' => '0' ), $calls ),
		0
	)
);

// ── §C3 — usable first / usable last, n = 1 ─────────────────────────────────
$calls = array();
eq(
	'C3 usable candidate FIRST at n=1',
	array( 'v1' ),
	bws_collect_usable( array( 'a', 'b' ), reader( array( 'a' => 'v1', 'b' => 'v2' ), $calls ), 1 )
);

$calls = array();
eq(
	'C3 usable candidate LAST at n=1 — the shape the previous build rendered nothing for',
	array( 'v3' ),
	bws_collect_usable( array( 'a', 'b', 'c' ), reader( array( 'a' => '', 'b' => '', 'c' => 'v3' ), $calls ), 1 )
);
eq( 'C3 the whole list was searched to find it', array( 'a', 'b', 'c' ), $calls );

// ── §C4 — the reader is not called again once $n is reached ─────────────────
$calls = array();
eq(
	'C4 n reached mid-list → bounded result',
	array( 'v1', 'v2' ),
	bws_collect_usable(
		array( 'a', 'x', 'b', 'c', 'd' ),
		reader( array( 'a' => 'v1', 'x' => '', 'b' => 'v2', 'c' => 'v3', 'd' => 'v4' ), $calls ),
		2
	)
);
eq( 'C4 READER NOT CALLED past the fill — results counted, not candidates', array( 'a', 'x', 'b' ), $calls );

// ── §C5 — n <= 0 unbounded; n past the population returns all, unpadded ─────
$calls = array();
eq(
	'C5 n=0 → unbounded, every usable read returned',
	array( 'v1', 'v2', 'v3' ),
	bws_collect_usable( array( 'a', 'x', 'b', 'c' ), reader( array( 'a' => 'v1', 'x' => '', 'b' => 'v2', 'c' => 'v3' ), $calls ), 0 )
);
eq( 'C5 n=0 examined everything', array( 'a', 'x', 'b', 'c' ), $calls );

$calls = array();
eq(
	'C5 negative n → unbounded (matches bws_clamp_limit\'s tolerant -1)',
	array( 'v1', 'v2' ),
	bws_collect_usable( array( 'a', 'b' ), reader( array( 'a' => 'v1', 'b' => 'v2' ), $calls ), -1 )
);

$calls = array();
eq(
	'C5 n larger than the usable population → everything usable, no padding',
	array( 'v1', 'v2' ),
	bws_collect_usable( array( 'a', 'x', 'b' ), reader( array( 'a' => 'v1', 'x' => '', 'b' => 'v2' ), $calls ), 5 )
);

// ── §C6 — a read returning SEVERAL items (the try_ list-mode slot shape) ────
$multi = function ( $source ) {
	return array( 'list' => array( 'p', '', 'q' ), 'one' => array( 'r' ) )[ $source ] ?? array();
};

eq(
	'C6 multi-item read: usable items flatten in order, empties dropped',
	array( 'p', 'q', 'r' ),
	bws_collect_usable( array( 'list', 'one' ), $multi, 0 )
);

eq(
	'C6 overshoot within one read is sliced off the tail (n=1 over a 2-item read)',
	array( 'p' ),
	bws_collect_usable( array( 'list', 'one' ), $multi, 1 )
);

$calls = array();
$counting_multi = function ( $source ) use ( &$calls ) {
	$calls[] = $source;
	return array( 'p', 'q' );
};
eq(
	'C6 a read that fills $n by itself stops the walk',
	array( 'p', 'q' ),
	bws_collect_usable( array( 'a', 'b' ), $counting_multi, 2 )
);
eq( 'C6 second source never read', array( 'a' ), $calls );

echo $fails ? "\n{$fails} FAILURE(S)\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );
