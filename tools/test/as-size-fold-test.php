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
 * and a divergence would store one tag two ways. NEITHER image migration can: `size` is
 * one of GB's reserved keys, destructured out of `parsedTag.params` into GB's private
 * `imageSize` state before `extraTagParams` is formed, and `tagSpecificControls` receives
 * only `{ state: extraTagParams, setState }`. See docs/gb-constraints.md §Reserved keys
 * are destructured into GB-private state (verified against GB's DynamicTagSelect.jsx) and
 * the O4.6 negative row in fw52-order-test-matrix.md, which pins that opening a
 * legacy-split tag leaves its `size:` in place. `srcTerm`+`tax` is the same constraint in
 * its original guise.
 *
 * THE TWO HALVES REACH THAT CONCLUSION BY DIFFERENT ROUTES, which is worth stating because
 * the second one looks reachable and is not. The FOLD is unreachable because it must READ
 * and CLEAR `size`. A4's bare-`as:url` completion touches only `as`, our own option — so
 * the editor could write it, and must not: a legacy split tag is INDISTINGUISHABLE there
 * from a size-less one, so completing `as:url` to `url,full` on mount would pin the render
 * to full on a tag the read seam was resolving at `medium`. The converter has the ordering
 * that avoids this (§A5 — the fold entry runs first and the authored size survives into
 * `as`); the editor has nothing to order against, because the thing to order against is
 * the key it cannot see.
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
// A3 calls the REGISTRATION pass (bws_register_option_migrations), which the fold blocks
// above never touch — these are the WP surface that pass needs.
if ( ! function_exists( '_x' ) ) {
	function _x( $s, $c = '', $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return $s; }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
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

// ===============================================
// A3 — DETECTION: what the converter REPORTS vs what the fold DOES
// ===============================================
//
// THE ENTRY MUST MATCH A SHAPE IF AND ONLY IF THE CALLBACK MOVES IT. Both halves are
// asserted per shape below, so this comment names an axis the block pins. A matched entry
// the callback cannot change is a report the migrator never satisfies — the list re-lists
// the same post on every scan and no author action clears it.
//
// Why the match list is spelled the way it is: the registration in
// `bws_register_option_migrations()`. The wider class this instance belongs to, and the
// candidate structural fix: issue #119.
echo "\nA3 — detection agrees with the rewrite\n";

bws_register_option_migrations();
$mig = 'BWS\\DynamicTags\\MigrationRegistry';

/** Does the as+size entry match this tag string? */
$detects = static function ( string $tag_string ) use ( $mig ): bool {
	[ $tag_name, $options ] = $mig::parse_tag_string( $tag_string );
	foreach ( $mig::find_option_migrations( $tag_name, array_keys( $options ), $options ) as $entry ) {
		if ( 'bws_migrate_image_as_size_fold' === ( $entry['transform_callback'] ?? '' ) ) {
			return true;
		}
	}
	return false;
};

$detection_cases = array(
	// tag string                                                            report expected?
	array( '{{image as:url|size:medium}}',                                    true ),
	array( '{{image as:alt|size:large}}',                                     true ),
	array( '{{try_image as:url,full|2-as:url|2-size:large}}',                 true ),
	// The shape that regressed — an `as` with no size anywhere in the tag.
	array( '{{image as:url|src:refs,benefit_vendor,limit(1)|use:featured}}',  false ),
	array( '{{image as:url,full}}',                                           false ),
	array( '{{image as:alt}}',                                                false ),
	array( '{{try_image as:url,full|2-as:url}}',                              false ),
);

foreach ( $detection_cases as $case ) {
	[ $tag_string, $expected ] = $case;
	assert_eq( "rewrite moves the string: {$tag_string}", $expected, $fold( $tag_string ) !== $tag_string );
	assert_eq( "converter reports it:     {$tag_string}", $expected, $detects( $tag_string ) );
}


// ===============================================
// A4 — BARE `as:url` NORMALIZE (1.17.1)
// ===============================================
//
// SAME AXIS AS A3, applied to the second entry: it must match a shape IF AND ONLY IF its
// callback moves that shape. Both halves are asserted per case below.
//
// The non-matching cases are the load-bearing half. `as:url,full` is already canonical,
// `as:alt` is nullary (no arg slot exists to fill), and an ABSENT `as` is the SEED case
// this migration deliberately declines: GB writes that one from `'default' => 'url,full'`
// at tag-SELECT time, and rescuing it here would put the converter in the editor's job on
// a tag whose wire was never legacy. Only a PRESENT-but-partial `as` is legacy wire.
// Why the token is canonical with its default included at all:
// docs/tag-reference.md §`as` serialization opt-out.
echo "\nA4 — bare `as:url` normalizes to the canonical token\n";

$bare = static function ( string $tag_string ): string {
	return bws_migrate_image_as_bare_url( $tag_string );
};

/** Does the bare-url entry match this tag string? */
$detects_bare = static function ( string $tag_string ) use ( $mig ): bool {
	[ $tag_name, $options ] = $mig::parse_tag_string( $tag_string );
	foreach ( $mig::find_option_migrations( $tag_name, array_keys( $options ), $options ) as $entry ) {
		if ( 'bws_migrate_image_as_bare_url' === ( $entry['transform_callback'] ?? '' ) ) {
			return true;
		}
	}
	return false;
};

$bare_cases = array(
	// tag string                                            expected rewrite (=== input when none)
	array( '{{image as:url}}',                                '{{image as:url,full}}' ),
	array( '{{image as:url,}}',                               '{{image as:url,full}}' ),
	array( '{{image as:url|use:featured|key:logo}}',          '{{image as:url,full|use:featured|key:logo}}' ),
	array( '{{term_image as:url}}',                           '{{term_image as:url,full}}' ),
	array( '{{try_image as:url|A:use(featured)}}',            '{{try_image as:url,full|A:use(featured)}}' ),
	// Already canonical / no arg slot / never on the wire — no match, no rewrite.
	array( '{{image as:url,full}}',                           '{{image as:url,full}}' ),
	array( '{{image as:url,medium}}',                         '{{image as:url,medium}}' ),
	array( '{{image as:alt}}',                                '{{image as:alt}}' ),
	array( '{{image use:featured}}',                          '{{image use:featured}}' ),
);

foreach ( $bare_cases as $case ) {
	[ $tag_string, $expected ] = $case;
	assert_eq( "normalize: {$tag_string}", $expected, $bare( $tag_string ) );
	assert_eq( "converter reports it:     {$tag_string}", $expected !== $tag_string, $detects_bare( $tag_string ) );
}

// Idempotent for the same reason the fold is: the cascade may re-derive and re-run.
assert_eq( 'the normalize is idempotent',
	$bare( '{{image as:url}}' ), $bare( $bare( '{{image as:url}}' ) ) );

// TAG-LEVEL `as` ONLY, and the omission is deliberate. Post-fold a try_ slot has no `as`
// of its own — the tag-level token governs every attempt — so a legacy `N-as` is dead
// wire at render. Canonicalizing it would carry it forward looking live; the fold entry
// above still rewrites one when it is paired with an `N-size`, which is the only case
// where it ever meant anything.
assert_eq( 'a legacy per-slot `2-as` is left alone',
	'{{try_image as:url,full|2-as:url}}', $bare( '{{try_image as:url,full|2-as:url}}' ) );

// ===============================================
// A5 — ENTRY ORDER: the fold runs BEFORE the normalize
// ===============================================
//
// A tag carrying BOTH a legacy `size:` and a bare `as:url` matches both entries, and the
// cascade takes the first that CHANGES — so registration order decides the outcome, not
// just which entries exist. Fold first yields `url,<legacy size>`. Reversed, the
// normalize would write `url,full`, and the fold would then read a tag whose `as` already
// carries a size and drop the legacy `size:` as stale — silently downgrading the image.
// The scan reports the same way for the same reason: its loop `break`s on the first match.
echo "\nA5 — the fold entry precedes the normalize entry\n";

/** transform_callback of the FIRST entry matching this tag string, registration order. */
$first_entry = static function ( string $tag_string ) use ( $mig ): string {
	[ $tag_name, $options ] = $mig::parse_tag_string( $tag_string );
	foreach ( $mig::find_option_migrations( $tag_name, array_keys( $options ), $options ) as $entry ) {
		return (string) ( $entry['transform_callback'] ?? '' );
	}
	return '';
};

assert_eq( 'both entries match a legacy split tag',
	true, $detects( '{{image as:url|size:medium}}' ) && $detects_bare( '{{image as:url|size:medium}}' ) );
assert_eq( 'the fold is the one reported and the one that runs first',
	'bws_migrate_image_as_size_fold', $first_entry( '{{image as:url|size:medium}}' ) );
assert_eq( 'so the cascade keeps the authored size',
	'{{image as:url,medium}}', $mig::apply_option_migration( 'image', '{{image as:url|size:medium}}' ) );
assert_eq( 'and a bare `as` with no size still reaches the normalize',
	'{{image as:url,full}}', $mig::apply_option_migration( 'image', '{{image as:url}}' ) );
echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
