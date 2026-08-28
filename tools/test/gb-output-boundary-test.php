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
 * ONE DERIVED CLAIM IS HELD TOO, at the end of §B3. bws_safe_content_output() unsets the
 * four transforms that corrupt rich HTML and then ends here, and its PHPDoc states what a
 * content tag consequently hands GB. That residue is a function of two sets rather than a
 * property of either, so it can go stale without either side being edited — a GB release
 * adding a transform is enough. The real function is loaded and run for it, since a
 * test-local copy of its four keys would be the same second home the paragraph above
 * refuses.
 *
 * THE THIRD THING HELD HERE IS A CENSUS RATHER THAN A SET — §B6, added 2026-08-26 when
 * the last direct call site was routed. From that point the set's guarantee stopped being
 * a statement about the image family and became one about the whole plugin, and a
 * statement of that shape decays by ADDITION, not by edit: a new tag file that calls GB's
 * output method directly changes nothing in this file, and changes no fixture page either,
 * since the option array GB receives is visible only to whatever hooks its output filter.
 * So §B6 re-reads the tree. It lives in this file rather than a harness of its own because
 * what it protects is this file's subject; a separate one would be a second place to
 * remember, for one assertion.
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

// THE COMPOSED CONTENT PATH. bws_safe_content_output() is the one caller that layers
// its own unsets on top of the boundary, and its PHPDoc states what a content tag then
// hands GB. That sentence is DERIVED from two sets neither of which it owns — its four
// content-unsafe keys and the allowlist above — so a GB release adding a transform would
// falsify it with nothing failing. Asserted through the REAL function, never against a
// copy of its four keys: a local copy would agree with itself while the shipped rule
// moved. content-helpers.php is inert at load (every function in it is
// function_exists-wrapped) and this path calls no WordPress function.
require $root . '/includes/helpers/content-helpers.php';

GenerateBlocks_Dynamic_Tag_Callbacks::$received = array();
bws_safe_content_output(
	'CONTENT',
	array_merge(
		array_fill_keys( BWS_GB_TAG_OUTPUT_OPTIONS, 'X' ),
		array( 'fallback' => 'F', 'key' => 'charter' )
	),
	null
);
assert_same(
	'a content tag hands GB replace, trim and id — nothing else, GB transform or ours',
	array( 'replace' => 'X', 'trim' => 'X', 'id' => 'X' ),
	GenerateBlocks_Dynamic_Tag_Callbacks::$received['options']
);
assert_same(
	'the content string survives the composed path untouched',
	'CONTENT',
	bws_safe_content_output( 'CONTENT', array( 'wpautop' => 'true', 'fallback' => 'F' ), null )
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

echo "\n§B6 — NOTHING the plugin ships calls GB's output method directly\n";

// The set checks (§B1-§B5) hold what the boundary DOES. This one holds that the
// boundary is still the only door: a call site that goes straight to
// GenerateBlocks_Dynamic_Tag_Callbacks::output() hands GB the whole option array
// again, and the options we already consumed go back to being published to
// `generateblocks_dynamic_tag_output`. That regression is one line, renders
// identically on every fixture page, and no set assertion above can see it.
//
// IT READS THE TREE RATHER THAN COUNTING. A count would pass a change that routed
// one site and un-routed another, and its failure message could only say a number
// moved. This one names the file, the line and the code.
//
// COMMENTS COUNT, DELIBERATELY. The regex cannot tell a call from prose, and
// tightening it to exclude comment lines would be a second, subtler thing to keep
// right. Naming GB's method belongs to the boundary file, which is where the
// reason to name it lives; anywhere else, a mention is either a call or a sentence
// that has outlived the call it described. If a future comment genuinely needs to
// say it, the fix is a new row in $exempt below with the reason written out, not a
// looser pattern.
//
// SCOPE IS DEFAULT-IN. Every .php file in the repo is scanned unless its directory
// is excluded below, so a new shipped directory is covered without anyone
// remembering to add it here.

$skip_dirs = array(
	'.git',
	'.scratch',
	'.claude',
	'deprecated-files',
	'docs',          // prose and design history; records what WAS true, by design.
	'libs',          // vendored third party (PUC); not ours to route.
	'node_modules',
	'tools',         // not shipped, and a harness legitimately STUBS GB's class —
	                 // this very file defines one.
);

/** Files that may name GB's output method, with the reason. */
$exempt = array(
	// The boundary itself: the one site that is supposed to call GB, and the one
	// PHPDoc that explains why the others must not.
	'includes/helpers/gb-output-boundary.php',
);

$scan = static function ( string $dir ) use ( $skip_dirs ): array {
	$hits  = array();
	$files = 0;
	$it    = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			static function ( $current ) use ( $skip_dirs ) {
				return ! ( $current->isDir() && in_array( $current->getFilename(), $skip_dirs, true ) );
			}
		)
	);
	foreach ( $it as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$files++;
		$rel   = strtr( substr( $file->getPathname(), strlen( $dir ) + 1 ), DIRECTORY_SEPARATOR, '/' );
		$lines = preg_split( '~\R~', (string) file_get_contents( $file->getPathname() ) );
		foreach ( $lines as $n => $line ) {
			if ( preg_match( '~Dynamic_Tag_Callbacks\s*::\s*output~', $line ) ) {
				$hits[] = array( $rel, $n + 1, trim( $line ) );
			}
		}
	}
	return array( $hits, $files );
};

list( $all_hits, $files_scanned ) = $scan( $root );

// NON-VACUITY FIRST. A scanner that read nothing, or a pattern that stopped
// matching, would otherwise report a clean tree — the one failure mode a
// "found nothing" assertion cannot distinguish from success. Both are pinned
// against the boundary file, which is guaranteed to contain a real call.
assert_same( 'the scan reached the plugin source', true, $files_scanned > 20 );
assert_same(
	'the pattern still matches real code (the boundary file is found)',
	true,
	(bool) array_filter( $all_hits, static fn( $h ) => 'includes/helpers/gb-output-boundary.php' === $h[0] )
);

// An exemption for a file that no longer exists is a hole nobody can see: the
// pattern it excused is gone, but the row stays and would excuse a NEW file that
// happened to take the same path.
foreach ( $exempt as $path ) {
	assert_same( "the exemption `{$path}` still names a real file", true, is_file( $root . '/' . $path ) );
}

$offenders = array_values( array_filter( $all_hits, static fn( $h ) => ! in_array( $h[0], $exempt, true ) ) );

// Asserted on the COUNT, so the pass line stays one line and the failure detail is
// printed below it rather than inside a var_export of nested arrays.
assert_same(
	'no file outside the boundary names GB\'s output method',
	0,
	count( $offenders )
);

foreach ( $offenders as $h ) {
	echo "       {$h[0]}:{$h[1]}  {$h[2]}\n";
}
if ( $offenders ) {
	echo "       route each of these through bws_gb_tag_output(), or add the file to\n";
	echo "       \$exempt above WITH the reason written out.\n";
	echo "       ({$files_scanned} php files scanned.)\n";
}

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
