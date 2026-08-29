<?php
/**
 * Standalone unit harness for the GenerateBlocks Pro pattern-cache reconcile (#99).
 *
 * Loads the REAL file (includes/classes/admin/class-pattern-cache.php) rather than a
 * transcribed copy — the newer house convention, whose whole point is that a test-local
 * copy of a rule is the drift the extraction removed.
 *
 * WHAT THIS SEAM CAN AND CANNOT SEE. The decision logic is pure: given a decoded cache
 * tree, the post's content and its ID, what comes back. The ESCAPING hazard that motivates
 * half this feature lives inside the platform's meta write (wp_unslash() recurses through
 * arrays), so it is structurally invisible here and is pinned by the integration seam,
 * tools/fixtures/core-structures/verify-pattern-cache.php. Neither seam substitutes for
 * the other.
 *
 * THE AGREEMENT RULE IS A RAW BYTE COMPARE, and §P4 is the case that says so. An earlier
 * draft of the spec treated "byte-identical apart from escaping" as AGREEMENT; that was
 * REVERSED on 2026-08-14. GB Pro writes wp_slash( $post_content ) and update_post_meta()
 * unslashes recursively, so the STORED value is byte-identical to post_content and `===`
 * is the correct comparison rather than an approximation of one. Normalising slashes
 * before comparing would declare a genuinely over-slashed entry (older GB Pro write, bad
 * restore, earlier buggy pass) "agreeing" — permanently unrepairable, and invisible,
 * while the reconcile reports zero. Churn is impossible either way because the write is
 * stable: raw compare converges in ONE pass, which is the idempotence property the
 * integration seam pins.
 *
 * Run:  php tools/test/pattern-cache-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// The WP surface the loaded file touches when the PURE half is CALLED. reconcile_site()
// and record_status() are impure by design and are not exercised here — they are the
// integration seam's subject.
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $n, $d = null ) { return 1 === (int) $n ? $single : $plural; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		// Only the two date/time format options are read by format_status().
		return 'date_format' === $name ? 'F j, Y' : ( 'time_format' === $name ? 'g:i a' : $default );
	}
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $ts = null ) { return gmdate( $format, (int) $ts ); }
}

require __DIR__ . '/../../includes/classes/admin/class-pattern-cache.php';

use BWS\DynamicTags\Admin\PatternCache;

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
	echo '       expected: ' . var_export( $expected, true ) . "\n";
	echo '       actual:   ' . var_export( $actual, true ) . "\n";
}

/**
 * A cache entry in GB Pro's real shape.
 *
 * Every non-content field carries a value with bytes that a slash-stripping bug would
 * visibly damage (quotes inside the preview, a URL, a selector). A stub `'preview' =>
 * '<p>x</p>'` survives such a bug; this does not.
 */
function entry( int $post_id, string $pattern, array $over = array() ): array {
	return array_merge(
		array(
			'id'                   => 'pattern-' . $post_id,
			'label'                => 'Fixture Pattern',
			'pattern'              => $pattern,
			'preview'              => '<div class="gb-element" data-x="{\"a\":1}">it\'s here</div>',
			'scripts'              => array( 'https://example.test/dist/accordion.js' ),
			'styles'               => array( 'https://example.test/dist/accordion-style.css' ),
			'categories'           => array( 12, 34 ),
			'globalStyleSelectors' => array( '.gb-container-abc' ),
			'formRefs'             => array(),
		),
		$over
	);
}

/**
 * The content the converter would have left behind, and the pre-migration wire.
 *
 * BOTH CARRY A BLOCK ATTRIBUTE JSON COMMENT, and that is load-bearing rather than
 * decoration: §P4 slashes this corpus, and a corpus with no quote, backslash or NUL in it
 * comes back from addslashes() UNCHANGED — so the escaping case would pass while
 * exercising nothing. §P4.0 asserts the corpus differs under slashing for that reason.
 */
const NEW_WIRE = '<!-- wp:paragraph {"className":"lead"} --><p>{{text src:ref|ref:vendor|key:body}}</p><!-- /wp:paragraph -->';
const OLD_WIRE = '<!-- wp:paragraph {"className":"lead"} --><p>{{text src:related_post|rel:vendor|key:body}}</p><!-- /wp:paragraph -->';

// ===========================================================================
echo "P1 — divergence and agreement (the whole decision, in two rows)\n";

$tree   = array( entry( 7, OLD_WIRE ) );
$result = PatternCache::reconcile_tree( $tree, NEW_WIRE, 7 );

assert_eq( 'P1.1 diverging content is rewritten to the post content',
	NEW_WIRE, $result[0]['pattern'] ?? null );

// The headline property: the reconcile touches ONE field. Comparing the whole entry with
// `pattern` lifted out catches a read-modify-write that mangles anything else — which is
// exactly what a naive implementation does to `preview` via the meta layer's unslash.
$before = $tree[0];
$after  = $result[0];
unset( $before['pattern'], $after['pattern'] );
assert_eq( 'P1.2 every other field is byte-identical', $before, $after );

assert_eq( 'P1.3 agreeing content returns null — nothing to write',
	null, PatternCache::reconcile_tree( array( entry( 7, NEW_WIRE ) ), NEW_WIRE, 7 ) );

// ===========================================================================
echo "\nP2 — entries are matched by their own identifier, never by position\n";

// The live shape is a one-entry list, so a positional implementation passes every
// single-entry case and fails only here. GB Pro keys the entry 'pattern-<post_id>'.
$mixed = array(
	entry( 99, 'someone else’s content' ),
	entry( 7, OLD_WIRE ),
);
$result = PatternCache::reconcile_tree( $mixed, NEW_WIRE, 7 );

assert_eq( 'P2.1 the non-matching first entry is untouched',
	'someone else’s content', $result[0]['pattern'] ?? null );
assert_eq( 'P2.2 the matching second entry moves',
	NEW_WIRE, $result[1]['pattern'] ?? null );

assert_eq( 'P2.3 no entry for this post → null',
	null, PatternCache::reconcile_tree( array( entry( 99, OLD_WIRE ) ), NEW_WIRE, 7 ) );

// 'pattern-12' must not answer for post 1. A `strpos`-style or numeric-cast match would.
assert_eq( 'P2.4 the id compare is exact, not a prefix',
	null, PatternCache::reconcile_tree( array( entry( 12, OLD_WIRE ) ), NEW_WIRE, 1 ) );

// Keys are preserved rather than renumbered. GB Pro emits a list, but a map-shaped tree
// that still carries a recognisable entry is reconciled in place — array_values() here
// would silently reshape another plugin's data.
$keyed  = array( 'a' => entry( 99, 'other' ), 'b' => entry( 7, OLD_WIRE ) );
$result = PatternCache::reconcile_tree( $keyed, NEW_WIRE, 7 );
assert_eq( 'P2.5 array keys survive the rewrite',
	array( 'a', 'b' ), array_keys( (array) $result ) );

// ===========================================================================
echo "\nP3 — malformed shapes are returned untouched, never repaired\n";

// "Can never make a bad cache worse": every unrecognised shape resolves to null, i.e. no
// write at all. A reconcile that tried to normalise these would be writing a guess over
// another plugin's data.
assert_eq( 'P3.1 not an array', null, PatternCache::reconcile_tree( 'not a tree', NEW_WIRE, 7 ) );
assert_eq( 'P3.2 empty array', null, PatternCache::reconcile_tree( array(), NEW_WIRE, 7 ) );
assert_eq( 'P3.3 null', null, PatternCache::reconcile_tree( null, NEW_WIRE, 7 ) );

$missing = array( entry( 7, OLD_WIRE ) );
unset( $missing[0]['pattern'] );
assert_eq( 'P3.4 matching entry has no content field',
	null, PatternCache::reconcile_tree( $missing, NEW_WIRE, 7 ) );

assert_eq( 'P3.5 matching entry content is not a string',
	null, PatternCache::reconcile_tree( array( entry( 7, OLD_WIRE, array( 'pattern' => array( 'x' ) ) ) ), NEW_WIRE, 7 ) );

// A junk NEIGHBOUR cannot match anything, so it is passed through and the good entry
// still reconciles. Bailing on the whole tree here would let one bad row disable the
// repair for a pattern that is perfectly well-formed.
$with_junk = array( 'junk', entry( 7, OLD_WIRE ) );
$result    = PatternCache::reconcile_tree( $with_junk, NEW_WIRE, 7 );
assert_eq( 'P3.6 a non-array neighbour is passed through verbatim',
	'junk', $result[0] ?? null );
assert_eq( 'P3.7 …and the well-formed entry beside it still moves',
	NEW_WIRE, $result[1]['pattern'] ?? null );

// An entry with no id cannot be matched and must not be adopted by position.
$no_id = array( entry( 7, OLD_WIRE ) );
unset( $no_id[0]['id'] );
assert_eq( 'P3.8 an entry with no identifier is never adopted',
	null, PatternCache::reconcile_tree( $no_id, NEW_WIRE, 7 ) );

// ===========================================================================
echo "\nP4 — ⚠ an escaping-only difference is a DIVERGENCE, not agreement\n";

// This case existed in the spec with the OPPOSITE expectation. See the file header:
// the stored value is unslashed, so `===` is exact, and normalising would make an
// over-slashed entry permanently unrepairable while reporting zero.
// GUARD ON THE CASE ITSELF, in both directions. addslashes() is a no-op on a string with
// no quote, backslash or NUL, so a corpus without one would make every assertion below
// pass while testing nothing — which is exactly what the first draft of this file did.
$slashed = addslashes( NEW_WIRE );
assert_eq( 'P4.0a the corpus is actually changed by slashing', true, $slashed !== NEW_WIRE );
assert_eq( 'P4.0b …and the difference is escaping ONLY',
	NEW_WIRE, stripslashes( $slashed ) );

$pass1 = PatternCache::reconcile_tree( array( entry( 7, $slashed ) ), NEW_WIRE, 7 );
assert_eq( 'P4.1 first pass rewrites it', NEW_WIRE, $pass1[0]['pattern'] ?? null );

// Convergence in one pass IS the idempotence argument. If the write were unstable this
// would diverge again, and the reconcile would churn on every trigger forever.
assert_eq( 'P4.2 second pass reports agreement — converged, no churn',
	null, PatternCache::reconcile_tree( $pass1, NEW_WIRE, 7 ) );

// ===========================================================================
echo "\nP5 — tree_has_entry: the write guard, separate from the change decision\n";

// reconcile_tree() answers null for BOTH "agrees" and "unrecognised", which is right for
// its own caller but cannot gate the duplicate-row convergence: converging means writing
// a value even though nothing changed, and it must never write a shape it does not
// recognise over another plugin's rows.
assert_eq( 'P5.1 well-formed matching entry', true,
	PatternCache::tree_has_entry( array( entry( 7, NEW_WIRE ) ), 7 ) );
assert_eq( 'P5.2 no entry for this post', false,
	PatternCache::tree_has_entry( array( entry( 99, NEW_WIRE ) ), 7 ) );
assert_eq( 'P5.3 matching entry with a non-string content field', false,
	PatternCache::tree_has_entry( array( entry( 7, OLD_WIRE, array( 'pattern' => 3 ) ) ), 7 ) );
assert_eq( 'P5.4 not an array', false, PatternCache::tree_has_entry( 'nope', 7 ) );

// ===========================================================================
echo "\nP6 — the reported line: a zero that can be told apart from an absence\n";

// US 5 asks to distinguish "nothing needed fixing" from "nothing was checked". A bare
// reconciled-count structurally cannot: 0 is identical for a healthy site with 37
// consistent patterns and for a site where the meta key was not found at all. `checked`
// is the denominator that makes the claim falsifiable, and these three branches are where
// that shows up.
$ts = 1755100000; // fixed; wp_date is stubbed to gmdate above.

assert_eq( 'P6.1 never run → no line at all',
	'', PatternCache::format_status( array() ) );

assert_eq( 'P6.2 checked 0 → honest for both "GB Pro absent" and "no patterns yet"',
	'No pattern cache found.',
	PatternCache::format_status( array( 'checked' => 0, 'reconciled' => 0, 'time' => $ts ) ) );

assert_eq( 'P6.3 checked, none diverged',
	'Pattern cache checked August 13, 2025 3:46 pm: 37 patterns, all current.',
	PatternCache::format_status( array( 'checked' => 37, 'reconciled' => 0, 'time' => $ts ) ) );

assert_eq( 'P6.4 …and the singular reads correctly',
	'Pattern cache checked August 13, 2025 3:46 pm: 1 pattern, all current.',
	PatternCache::format_status( array( 'checked' => 1, 'reconciled' => 0, 'time' => $ts ) ) );

assert_eq( 'P6.5 some were repaired — M of N, so the denominator survives',
	'Pattern cache checked August 13, 2025 3:46 pm: 5 of 37 patterns updated.',
	PatternCache::format_status( array( 'checked' => 37, 'reconciled' => 5, 'time' => $ts ) ) );

// trigger/version are STORED but never rendered — retroactivity, not display. The line
// must not leak them, or an untranslated slug reaches a user-facing string.
$line = PatternCache::format_status(
	array( 'checked' => 37, 'reconciled' => 5, 'time' => $ts, 'trigger' => 'upgrade', 'version' => '1.17.0' )
);
assert_eq( 'P6.6 trigger is stored, not rendered', false, false !== strpos( $line, 'upgrade' ) );
assert_eq( 'P6.7 version is stored, not rendered', false, false !== strpos( $line, '1.17.0' ) );

// ===========================================================================
// §P9 — WHICH strings a repair cleared
//
// The reconcile REMOVES stale shadow wire: it overwrites a cached copy of post_content with
// the current one, so any tag string the old copy held and the new content does not is gone
// from the site's renderable wire. Counting those was not enough. A migration replay reads
// each of them as a (url, tag) pair present on only one side — a hard failure that is not a
// render change, 212 of them beside 13,445 identical on one measured run — and only the
// strings themselves can tell a repair from a disappearance.
//
// PURE, AND DELIBERATELY NOT A DIFF OF THE TREE. What matters downstream is wire that STOPPED
// EXISTING, which is a question about two content strings; the tree shape around them is
// reconcile_tree()'s business and nothing here needs to know it.
// ===========================================================================

$before = 'a {{text src:ref|ref:body}} b {{email}} c';
$after  = 'a {{text src:related_post|ref:body}} b {{email}} c';

assert_eq(
	'P9.1 a string the repair removed is reported',
	array( '{{text src:ref|ref:body}}' ),
	PatternCache::cleared_wire( $before, $after )
);
assert_eq(
	'P9.2 a string that survived is NOT reported — it is still renderable wire',
	false,
	in_array( '{{email}}', PatternCache::cleared_wire( $before, $after ), true )
);
// The axis is one-directional: wire the repair ADDED is a different question, and the
// replay already reports a B-only pair as its own finding.
assert_eq(
	'P9.3 a string the repair ADDED is not reported — this axis is removals only',
	array(),
	PatternCache::cleared_wire( 'a b c', 'a {{added}} b c' )
);
assert_eq(
	'P9.4 identical content clears nothing',
	array(),
	PatternCache::cleared_wire( $before, $before )
);
assert_eq(
	'P9.5 content with no wire at all clears nothing, and costs no regex',
	array(),
	PatternCache::cleared_wire( '<p>plain</p>', '<p>also plain</p>' )
);

// EXACT STRINGS, NOT TAG NAMES. The replay keys on the whole tag string, so reporting
// `{{text}}` for a removed `{{text src:ref|ref:body}}` would forgive nothing and look like it
// had. Two configurations of one tag are two different pieces of wire.
assert_eq(
	'P9.6 two configurations of the same tag are distinct strings',
	array( '{{text key:a}}' ),
	PatternCache::cleared_wire( '{{text key:a}} {{text key:b}}', '{{text key:b}}' )
);

// A pattern can hold the same string many times; the artifact is a set of strings, so one row
// per distinct string is the right shape and a count would be a second, unused quantity.
assert_eq(
	'P9.7 a string removed twice is reported once',
	array( '{{title}}' ),
	PatternCache::cleared_wire( '{{title}} x {{title}}', 'x' )
);

// THE REGEX IS THE HARVEST SIDE'S, and it is a parameter for exactly that reason: harvest takes
// it as a CLI argument, so a run that overrode it would produce census strings this could
// never match. A mismatch fails SAFE — the pair stays unexplained, as it is today — but it
// fails silently, so the artifact records which regex produced it.
assert_eq(
	'P9.8 a caller-supplied wire pattern is what decides, so a harvest override can be matched',
	array( '[[gone]]' ),
	PatternCache::cleared_wire( '[[gone]] {{kept}}', '{{kept}}', '/\[\[[a-z]+\]\]/i' )
);

// The default has to BE the harvest default, or every artifact this writes is keyed
// differently from the census it will be compared against.
assert_eq(
	'P9.9 the default pattern is the harvest default, byte for byte',
	'/\{\{[a-z0-9_]+(?:\s[^{}]*)?\}\}/i',
	PatternCache::WIRE_PATTERN
);

// AN UNUSABLE PATTERN THROWS. Returning an empty array would read as "nothing was cleared",
// which is the answer that silently turns every repair back into an unexplained
// disappearance downstream — the exact failure this whole seam exists to end.
$threw = false;
try {
	PatternCache::cleared_wire( '{{a}}', '', '/(/' );
} catch ( InvalidArgumentException $e ) {
	$threw = true;
}
assert_eq( 'P9.10 an invalid wire pattern THROWS rather than reporting an empty removal set', true, $threw );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED — {$failures} of {$count}\n";
	exit( 1 );
}
echo "PASSED — {$count} assertions\n";
exit( 0 );
