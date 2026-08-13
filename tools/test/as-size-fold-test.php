<?php
/**
 * Standalone unit harness for the image as+size fold (FW-52 Phase 3).
 *
 * Loads the REAL files rather than transcribing them (1.17.0 — the two inline copies this
 * file carried through 1.16.x are gone; a test-local copy of a migration rule is the exact
 * drift the harness exists to catch):
 *
 *   A1 READ SEAM — bws_parse_as_option() from includes/helpers/image-helpers.php: split
 *                  `as:<mode>[,<size>]`, legacy separate `size:` fallback.
 *   A2 FOLD      — bws_migrate_image_as_size_fold() from includes/tags/deprecated-tags.php:
 *                  the value-conditional rewrite folding a legacy `size:` (bare + N- try_
 *                  slots) into `as`'s value, dropping a dead size on a nullary return.
 *
 * THERE IS DELIBERATELY NO EDITOR-MOUNT TWIN BLOCK HERE, and the absence is a finding
 * rather than a gap. The FW-56/57 slot fold has one (fold-migration-test.php §M7) because
 * that migration runs on BOTH paths — the post_content converter and a tag-modal mount —
 * and a divergence would store one tag two ways. as+size can only ever run on the
 * converter: `size` is one of GB's reserved keys, destructured out of `parsedTag.params`
 * into GB's private `imageSize` state before `extraTagParams` is formed, and
 * `tagSpecificControls` receives only `extraTagParams`. A mount migrator could neither
 * read nor clear it, and GB re-serializes it from private state regardless. See
 * docs/gb-constraints.md §Reserved keys are destructured into GB-private state (verified
 * against GB's DynamicTagSelect.jsx) and the O4.6 negative row in fw52-order-test-matrix.md,
 * which pins that opening a legacy-split tag leaves its `size:` in place. `srcTerm`+`tax`
 * is the same constraint in its original guise.
 *
 * Run:  php tools/test/as-size-fold-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// The WP surface the loaded files touch when CALLED (neither runs anything at load time).
// Defined before the requires so the guarded definitions inside them see it complete.
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'get_intermediate_image_sizes' ) ) {
	function get_intermediate_image_sizes() { return array( 'thumbnail', 'medium', 'large' ); }
}

require __DIR__ . '/../../includes/helpers/image-helpers.php';
// Loaded because run_transform()'s final step canonicalizes key order through it — a
// harness without it would exercise a serialization order the plugin never emits.
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/classes/class-migration-registry.php';
require __DIR__ . '/../../includes/tags/deprecated-tags.php';

$failures = 0;
$count    = 0;

function assert_eq( string $label, $expected, $actual ): void {
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

// ===========================================================================
echo "A1 — bws_parse_as_option (read seam)\n";

assert_eq( 'folded url,medium → mode url size medium',
	array( 'mode' => 'url', 'size' => 'medium' ), bws_parse_as_option( array( 'as' => 'url,medium' ) ) );
assert_eq( 'bare url → size defaults full',
	array( 'mode' => 'url', 'size' => 'full' ), bws_parse_as_option( array( 'as' => 'url' ) ) );
assert_eq( 'nullary alt → mode alt, size full (ignored downstream)',
	array( 'mode' => 'alt', 'size' => 'full' ), bws_parse_as_option( array( 'as' => 'alt' ) ) );
assert_eq( 'legacy split (as:url + size:large) → url,large',
	array( 'mode' => 'url', 'size' => 'large' ), bws_parse_as_option( array( 'as' => 'url', 'size' => 'large' ) ) );
assert_eq( 'folded value WINS over legacy size key',
	array( 'mode' => 'url', 'size' => 'thumbnail' ), bws_parse_as_option( array( 'as' => 'url,thumbnail', 'size' => 'large' ) ) );
assert_eq( 'no as at all → url/full default',
	array( 'mode' => 'url', 'size' => 'full' ), bws_parse_as_option( array() ) );
assert_eq( 'legacy return_type read',
	array( 'mode' => 'id', 'size' => 'full' ), bws_parse_as_option( array( 'return_type' => 'id' ) ) );

// This seam is LOAD-BEARING in a way the sibling renames' shims are not. Because `size` is
// GB-reserved, an unmigrated tag can survive indefinitely in the editor — GB round-trips
// the token invisibly and no mount migration can reach it — so the read fallback is the
// only thing keeping that tag rendering at its authored size. If it is ever removed, the
// Tag Converter stops being a tidy-up and becomes mandatory.
assert_eq( 'legacy split still resolves — the ONLY safety net for a tag the converter never reaches',
	array( 'mode' => 'url', 'size' => 'medium' ), bws_parse_as_option( array( 'as' => 'url', 'size' => 'medium' ) ) );

echo "\nA2 — bws_migrate_image_as_size_fold (the converter's rewrite)\n";

$fold = 'bws_migrate_image_as_size_fold';

assert_eq( 'url + legacy size → folded',
	'{{image as:url,medium}}', $fold( '{{image as:url|size:medium}}' ) );
assert_eq( 'nullary alt + dead size → size DROPPED',
	'{{image as:alt}}', $fold( '{{image as:alt|size:large}}' ) );
assert_eq( 'as absent + size → as:url,size appended (recovered url default)',
	'{{image key:foo|as:url,medium}}', $fold( '{{image size:medium|key:foo}}' ) );
assert_eq( 'no size key → unchanged (idempotent no-op)',
	'{{image as:url,full}}', $fold( '{{image as:url,full}}' ) );
assert_eq( 'already-folded + stray legacy size → legacy dropped, keep folded mode+size',
	'{{image as:url,full}}', $fold( '{{image as:url,full|size:medium}}' ) );
assert_eq( 'try_ slot 2: 2-size folds into 2-as, slot 1 untouched',
	'{{try_image as:url,full|2-as:url,large}}', $fold( '{{try_image as:url,full|2-as:url|2-size:large}}' ) );
assert_eq( 'try_ slot 3 nullary: 3-size dropped',
	'{{try_image 3-as:caption}}', $fold( '{{try_image 3-as:caption|3-size:medium}}' ) );
assert_eq( 'term_image url + size',
	'{{term_image as:url,thumbnail|key:logo}}', $fold( '{{term_image as:url|size:thumbnail|key:logo}}' ) );

// An UNRECOGNIZED mode is UNARY, not nullary — `$nullary` is an allowlist, so anything
// else falls to the else-branch and is coerced to `url`. That RESCUES the size rather than
// stranding it, which is the entry's whole purpose; reading the branch as "nullary unless
// url" would invert it and throw the size away.
assert_eq( 'unrecognized mode is UNARY → coerced to url, size rescued',
	'{{image as:url,large}}', $fold( '{{image as:bogus|size:large}}' ) );

// The split is explode(',', $raw, 2) — the FIRST comma only. Where that shows is the MODE,
// not the reassembly: a folded value round-trips whichever comma you split on, so a case
// with a unary mode proves nothing. Two commas on a NULLARY mode is the discriminating
// shape (`alt` is nullary, `alt,medium` is not).
assert_eq( 'two commas on a nullary mode: split on the FIRST comma, so the mode is `alt`',
	'{{image as:alt}}', $fold( '{{image as:alt,medium,stray|size:large}}' ) );
assert_eq( 'trailing comma is an EMPTY arg, not a folded size → the legacy size folds in',
	'{{image as:url,medium}}', $fold( '{{image as:url,|size:medium}}' ) );
assert_eq( 'empty size value → falls back to full, not to an empty arg',
	'{{image as:url,full}}', $fold( '{{image as:url|size:}}' ) );

// The converter may run twice over one post (a re-scan, or an overlapping entry in the
// apply_option_migration cascade), so the fold has to be a fixed point after one pass.
assert_eq( 'the fold is idempotent',
	$fold( '{{image as:url|size:medium}}' ), $fold( $fold( '{{image as:url|size:medium}}' ) ) );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
