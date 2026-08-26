<?php
/**
 * Standalone unit harness for the GB output BOUNDARY —
 * includes/helpers/gb-output-boundary.php: BWS_GB_TAG_OUTPUT_OPTIONS, its recorded
 * GB version, and bws_gb_tag_output().
 *
 * The real file is loaded, not copied. It defines two constants and one function and
 * runs nothing at load, so it is inert with ABSPATH defined — and a test-local copy of
 * the allowlist would be a second place the set lives, which is the exact condition the
 * allowlist exists to prevent (see the constant's own PHPDoc for why an allowlist and
 * not a blocklist; this file does not restate that rule).
 *
 * WHY IT EXISTS. The boundary's whole job is a SET, and a set has two failure modes that
 * look like nothing at all from the render path:
 *
 *   - a key silently LEAVES it — a documented GB option stops working on our tags, with
 *     no error and no changed markup anywhere a reader is looking;
 *   - a key silently JOINS it — an option we have already consumed goes back to being
 *     published to `generateblocks_dynamic_tag_output`, which is the defect the boundary
 *     was built for, restored without a diff that names it.
 *
 * Both are one-line edits. Neither moves a fixture page on its own, so the page
 * snapshots cannot see them: `/matrix-post-meta/`'s boundary row would still render
 * empty if `trunc` were dropped. Enumerating the membership here is what makes either
 * edit fail by name.
 *
 * THE VERSION ROW IS THE OTHER HALF, and it is a cross-file agreement rather than a
 * property of one file. `BWS_GB_TAG_OUTPUT_OPTIONS_READ_FROM` says which GenerateBlocks
 * this set was read out of; `tools/fixtures/core-structures/env-versions.php` says which
 * GenerateBlocks the fixture baseline was captured against. When those disagree, the set
 * was read from a GB nobody is testing on, and nothing else in the tree notices — the
 * dependency-version trigger fires on the env record moving, and would leave this
 * constant behind.
 *
 * NOT IN SCOPE — whether the plugin's remaining direct calls to GB's output method have
 * been routed through this boundary. That is a different question (a census of call
 * sites, not the content of a set), it is deliberately a separate change, and a check
 * for it belongs beside that change rather than here.
 *
 * Run:  php tools/test/gb-output-boundary-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );

/**
 * Capturing stand-in for GB's output method — the same stub shape
 * control-order-test.php uses, with the arguments kept so the boundary's own filtering
 * can be read off them. Nothing here models GB's transforms: what the boundary decides
 * is WHICH OPTIONS ARRIVE, and that is exactly what is captured.
 */
class GenerateBlocks_Dynamic_Tag_Callbacks {
	/** @var array<string,mixed> */
	public static array $received = array();

	public static function output( $value, $options = array(), $instance = null ) {
		self::$received = array(
			'output'   => $value,
			'options'  => $options,
			'instance' => $instance,
		);
		return $value;
	}
}

require $root . '/includes/helpers/gb-output-boundary.php';

$failures = 0;
$count    = 0;

function assert_same( $label, $expected, $actual ): void {
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

/** Run the boundary and hand back the options GB was actually given. */
function boundary_options( $options ): array {
	GenerateBlocks_Dynamic_Tag_Callbacks::$received = array();
	bws_gb_tag_output( 'VALUE', $options, null );
	return GenerateBlocks_Dynamic_Tag_Callbacks::$received['options'];
}

echo "§B1 — the set's EXACT membership\n";

// Written out rather than derived, so widening or narrowing the constant fails here
// instead of agreeing with itself. Order is asserted with it: the constant is read by
// humans against GB's own method, and a reshuffled list is a diff to review, not noise.
assert_same(
	'BWS_GB_TAG_OUTPUT_OPTIONS is exactly the seven keys GB consumes, in the recorded order',
	array( 'trunc', 'replace', 'trim', 'case', 'wpautop', 'link', 'id' ),
	BWS_GB_TAG_OUTPUT_OPTIONS
);
assert_same( 'seven keys, no duplicates', 7, count( array_unique( BWS_GB_TAG_OUTPUT_OPTIONS ) ) );

echo "\n§B2 — every allowlisted key SURVIVES the boundary\n";

// One row per key, so a partial regression names the key it lost. A single all-keys
// round-trip would report "the array differs" and leave the reader to find which.
$all_allowed = array_fill_keys( BWS_GB_TAG_OUTPUT_OPTIONS, 'X' );
foreach ( BWS_GB_TAG_OUTPUT_OPTIONS as $key ) {
	$passed = boundary_options( array( $key => 'X' ) );
	assert_same( "`{$key}` reaches GB", array( $key => 'X' ), $passed );
}
assert_same( 'all seven together survive as one array', $all_allowed, boundary_options( $all_allowed ) );

echo "\n§B3 — options WE consume are DROPPED\n";

// `fallback` is the measured one (the boundary's reason for existing); the other three
// are ordinary options of ours that carry no meaning for GB's output pipeline. Each is
// asserted beside a surviving key, because "the array came back empty" would pass a
// boundary that dropped everything, transforms included.
foreach ( array( 'fallback', 'key', 'as', 'tag_name' ) as $noise ) {
	$passed = boundary_options( array( $noise => 'N', 'trunc' => '20' ) );
	assert_same( "`{$noise}` is dropped, `trunc` is not", array( 'trunc' => '20' ), $passed );
}

// The mixed case a real tag actually produces: our own options far outnumber GB's.
assert_same(
	'a realistic image tag hands GB only its own two options',
	array( 'link' => 'permalink', 'id' => '22' ),
	boundary_options( array(
		'as'       => 'url,full',
		'use'      => 'key',
		'key'      => 'feature_image_missing',
		'fallback' => '999999',
		'link'     => 'permalink',
		'id'       => '22',
	) )
);

echo "\n§B4 — the non-array branch\n";

// The function's only other path. A tag that reaches the boundary with no options at
// all must still reach GB, with an empty array rather than whatever it was handed.
assert_same( 'null options → empty array', array(), boundary_options( null ) );
assert_same( 'a string in the options position → empty array', array(), boundary_options( 'nonsense' ) );
assert_same( 'the output string itself is passed through untouched', 'VALUE', bws_gb_tag_output( 'VALUE', array(), null ) );

echo "\n§B5 — the recorded GB version agrees with the fixture environment\n";

// Cross-file: the set says which GB it was read from, the blueprint's env record says
// which GB the committed snapshot baseline was captured against. Disagreement means the
// allowlist was read from a GenerateBlocks nothing is being tested on.
$env = require $root . '/tools/fixtures/core-structures/env-versions.php';
$gb  = $env['plugins']['generateblocks/plugin.php']['version'] ?? null;

assert_same( 'BWS_GB_TAG_OUTPUT_OPTIONS_READ_FROM is defined', true, defined( 'BWS_GB_TAG_OUTPUT_OPTIONS_READ_FROM' ) );
assert_same( 'env-versions.php records a GenerateBlocks version', true, is_string( $gb ) && '' !== $gb );
assert_same(
	"the set was read from the GB the baseline was captured under ({$gb})",
	$gb,
	BWS_GB_TAG_OUTPUT_OPTIONS_READ_FROM
);

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
