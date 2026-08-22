<?php
/**
 * Standalone unit harness for the `try_*` slot ARM TABLE (FW-71 / #103, retires FW-5):
 *   - BWS_TRY_SLOT_ARMS                    — the kind → arm map
 *   - bws_try_slot_arm( $kind )            — lookup, NULL = refusal
 *   - bws_try_slot_base_branch_kind( $k )  — where a root-only chain lands
 *
 * WHY THIS HARNESS EXISTS. The four arms it replaced sat inside a closure in
 * generate_base_try_tags() with no seam under them, so the three byte-identity risks in
 * the collapse — the term arm's break-early-while-hopping vs a slice, the per-arm
 * link-wrap entity, the single-result gate's position relative to the limit slice — were
 * unassertable except by rendering. The table is the seam that makes the first two
 * statable in a pure test; the third is a property of the shared emit and is pinned by
 * `fold-test-matrix.md` on the testbed.
 *
 * SCOPE — what a good assertion here says. The table is DERIVED-adjacent data, so the
 * valuable properties are the ones a future edit could break silently:
 *   1. every kind the CHAIN COMPILER can answer is either consumed or deliberately
 *      refused — asserted against the compiler's own vocabulary, never a second literal;
 *   2. an unconsumable kind is SKIPPED rather than guessed (null, not a post-arm default);
 *   3. the `user` and `meta_row` rows exist and say what they say — asserted at #103 before
 *      either was reachable, which is what let #108 wire the user leg without touching the
 *      table. `meta_row` is still reached by no wire on the chain axis, by decision;
 *   4. the base branch is total — every base kind lands somewhere, and only the
 *      branchable ones land off the post arm.
 *
 * VERIFIED BY MUTATION, not by a green count — three were run and each failed the suite:
 *   1. `bws_try_slot_arm()` defaults an unknown kind to the post arm instead of null
 *      (§A3.1/§A3.2 — the [I15] guess-the-nearest-arm defect);
 *   2. the `user` row loses `branchable` (§A4.2/§A5.1 — a root-only chain on an author
 *      archive stops reaching the user arm and silently reads the post arm again, i.e. the
 *      I6 defect #108 fixed, restored);
 *   3. `meta_row` is given the post arm's `ids`/`fn` (§A3.3/§A3.5 — a repeater row
 *      silently rendered as the ambient post).
 *
 * Both files load inert (definitions only, no load-time WP calls).
 *
 * Run:
 *   php tools/test/try-slot-arms-test.php
 *
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/try-slot-arms.php';
// The compiler is loaded for its VOCABULARY, not for its behaviour: BWS_FOLD_STEP_KINDS
// and BWS_FOLD_PARSE_TIME_ROOT_KINDS are what the table has to stay total over. Restating
// those kinds here would be the second literal that lets the two drift apart in exactly
// the direction nothing would notice — a new step kind with no arm renders empty.
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
// And the engine, for BWS_SOURCE_KIND_UNRESOLVED — the one BASE kind the branch refuses.
// Required rather than re-`define`d locally for the same reason as the constants above: a
// harness that spells the sentinel itself would agree with itself while the branch guard,
// which is `defined()`-guarded, quietly stopped firing in production.
require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';

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
	echo '       expected: ' . json_encode( $expected ) . "\n";
	echo '       actual:   ' . json_encode( $actual ) . "\n";
}

function assert_true( $label, $actual ): void {
	assert_same( $label, true, (bool) $actual );
}

// ===========================================================================
// §A1 — the table is TOTAL over the compiler's kind vocabulary
// ===========================================================================
echo "\n§A1 — every kind the compiler can answer has a decision\n";

$arms = bws_try_slot_arms();

// bws_fold_chain_resolution() answers exactly: a step kind, a parse-time root kind, 'render_time'
// (root-only, no parse-time root kind), or '' (unknown step slug). Build that set from the
// compiler's own constants.
$compiler_kinds = array_values( BWS_FOLD_STEP_KINDS );
foreach ( BWS_FOLD_PARSE_TIME_ROOT_KINDS as $parse_time_kind ) {
	$compiler_kinds[] = $parse_time_kind;
}
$compiler_kinds[] = 'render_time';
$compiler_kinds   = array_values( array_unique( $compiler_kinds ) );
sort( $compiler_kinds );

assert_same(
	'A1.1 the compiler\'s answerable kinds are the ones this suite reasons about',
	array( 'meta_row', 'post', 'render_time', 'site', 'term' ),
	$compiler_kinds
);

foreach ( $compiler_kinds as $kind ) {
	assert_true( "A1.2 kind '{$kind}' has a row (a decision, not an omission)", isset( $arms[ $kind ] ) );
}

// The table carries ONE key the compiler never answers: `user`. That is deliberate — it
// is a BRANCH target, reachable only once the factory has resolved a `render_time` root, and it
// ships wired (#108). Any OTHER extra key would be a row nothing can reach.
assert_same(
	'A1.3 the only non-compiler key is the base-branch target `user`',
	array( 'user' ),
	array_values( array_diff( array_keys( $arms ), $compiler_kinds ) )
);

// Every row states every column — a row missing one reads as a falsy answer at dispatch.
foreach ( $arms as $kind => $arm ) {
	assert_same(
		"A1.4 row '{$kind}' states every column",
		array( 'branchable', 'fn', 'ids', 'link', 'list' ),
		( static function ( $a ) {
			$k = array_keys( $a );
			sort( $k );
			return $k;
		} )( $arm )
	);
}

// ===========================================================================
// §A2 — id resolution, link entity and list handling, per arm
// ===========================================================================
echo "\n§A2 — the three things an arm varies by\n";

$expected_rows = array(
	// kind        ids       fn        link      list   branchable
	'term'     => array( 'term', 'term', 'term', true, true ),
	'site'     => array( 'none', 'site', 'site', true, false ),
	'post'     => array( 'post', 'core', 'post', true, true ),
	'render_time' => array( 'branch', 'branch', 'branch', true, false ),
	'user'     => array( 'user', 'user', 'user', false, true ),
	'meta_row' => array( '', '', '', false, false ),
);

foreach ( $expected_rows as $kind => $row ) {
	$arm = bws_try_slot_arm( $kind );
	assert_same( "A2.{$kind} ids", $row[0], $arm['ids'] );
	assert_same( "A2.{$kind} fn", $row[1], $arm['fn'] );
	assert_same( "A2.{$kind} link", $row[2], $arm['link'] );
	assert_same( "A2.{$kind} list", $row[3], $arm['list'] );
	assert_same( "A2.{$kind} branchable", $row[4], $arm['branchable'] );
}

// The site arm is the one with no entity — stated positively so a future edit that hands
// it an id has to change this line and say why (ADR 0002: the site source carries a
// namespace, not an id).
assert_same( 'A2.1 only the site arm resolves no entity while still rendering', 'none', bws_try_slot_arm( 'site' )['ids'] );

// The single-result LINK entity differs per arm, and the difference is the whole reason
// the arms could not simply be merged. Pinned as a set so a copy-paste that gives two
// arms the same entity fails by name.
assert_same(
	'A2.2 link entities are distinct per rendering arm',
	array(
		'term' => 'term',
		'site' => 'site',
		'post' => 'post',
		'user' => 'user',
	),
	array(
		'term' => bws_try_slot_arm( 'term' )['link'],
		'site' => bws_try_slot_arm( 'site' )['link'],
		'post' => bws_try_slot_arm( 'post' )['link'],
		'user' => bws_try_slot_arm( 'user' )['link'],
	)
);

// ===========================================================================
// §A3 — an unconsumable kind is SKIPPED, never guessed
// ===========================================================================
echo "\n§A3 — refusal is null, not a default\n";

// '' is what bws_fold_chain_resolution() answers for an UNKNOWN step slug. The engine
// answers empty for such a step, so the chain short-circuits; picking the nearest
// consumable arm would read the ambient entity and return a plausible WRONG value. That
// is the [I15] failure class, and it is why this must be null rather than the post arm.
assert_same( 'A3.1 the unknown-vocabulary kind has no arm', null, bws_try_slot_arm( '' ) );
assert_same( 'A3.2 an invented kind has no arm', null, bws_try_slot_arm( 'wormhole' ) );

// meta_row DOES have a row, and its row is the refusal: every column empty. The two
// spellings of "skip" are not interchangeable — a missing row means "nobody has decided",
// an empty row means "decided: no try_ arm consumes this, {{table}} does".
assert_same( 'A3.3 meta_row is refused by an EMPTY row, not a missing one', '', bws_try_slot_arm( 'meta_row' )['fn'] );
assert_true( 'A3.4 …and the row is present', is_array( bws_try_slot_arm( 'meta_row' ) ) );

// The dispatcher's skip test is `null === $arm || '' === $arm['fn']`. Assert the two
// kinds that test must catch, and that no OTHER row is caught by it — a row with an
// empty `fn` is invisible at dispatch, so an accidental one silently disables an arm.
$refused = array();
foreach ( $arms as $kind => $arm ) {
	if ( '' === $arm['fn'] ) {
		$refused[] = $kind;
	}
}
assert_same( 'A3.5 exactly one row is a refusal', array( 'meta_row' ), $refused );

// ===========================================================================
// §A4 — the base branch is total
// ===========================================================================
echo "\n§A4 — where a root-only chain lands\n";

// The factory answers one of post / term / user / meta_row at render. Every answer must
// land on an arm; only the branchable ones land off the post arm.
assert_same( 'A4.1 a term archive branches to the term arm', 'term', bws_try_slot_base_branch_kind( 'term' ) );
assert_same( 'A4.2 an author archive branches to the user arm', 'user', bws_try_slot_base_branch_kind( 'user' ) );
assert_same( 'A4.3 a singular page branches to the post arm', 'post', bws_try_slot_base_branch_kind( 'post' ) );

// A flat repeater row resolves a meta_row base and MUST reach the post arm — that is
// where mode 2b's loop fallthrough lives, and refusing here would delete it. This is the
// one place `meta_row` is not a refusal, and the two cases are genuinely different: the
// CHAIN kind meta_row means the wire asked for repeater rows, while a meta_row BASE means
// the ambient context happens to be one.
assert_same( 'A4.4 a flat repeater row branches to the post arm (mode 2b survives)', 'post', bws_try_slot_base_branch_kind( 'meta_row' ) );

// `site` is a real kind but not a branch target: a site base cannot arrive from a
// root-only chain that resolved to `render_time` in the first place.
assert_same( 'A4.5 a non-branchable kind falls to the post arm', 'post', bws_try_slot_base_branch_kind( 'site' ) );
assert_same( 'A4.6 `render_time` never branches to itself', 'post', bws_try_slot_base_branch_kind( 'base' ) );
assert_same( 'A4.7 an empty base kind falls to the post arm', 'post', bws_try_slot_base_branch_kind( '' ) );
assert_same( 'A4.8 an unknown base kind falls to the post arm', 'post', bws_try_slot_base_branch_kind( 'wormhole' ) );

// ── THE ONE KIND THE BRANCH REFUSES (#75 / #76) ────────────────────────────────
//
// The factory's refusal kind means it was handed a source it could not use. Defaulting
// THAT to the post arm is the same ambient read one layer down — so the obvious sentinel
// would have reproduced #109 in the act of fixing it, which is why this function became
// nullable at all. Refusing wholesale was never available: A4.4's meta_row default is
// load-bearing, so the refusal is for the sentinel ALONE.
//
// VERIFY BY MUTATION: drop the sentinel guard from bws_try_slot_base_branch_kind() and
// A4.11 fails; widen it to refuse anything non-branchable and A4.4 fails.
assert_same( 'A4.11 the factory\'s refusal kind is refused, not defaulted', null, bws_try_slot_base_branch_kind( BWS_SOURCE_KIND_UNRESOLVED ) );
assert_same( 'A4.12 …and A4.4\'s documented default is untouched by it', 'post', bws_try_slot_base_branch_kind( 'meta_row' ) );

// The two adjacent lookups now give the SAME answer for a kind nothing consumes. They
// gave opposite answers before — one documented "null is a refusal, not a default" while
// its neighbour defaulted — and a reader had to work out which posture applied where.
assert_same(
	'A4.13 both lookups refuse a kind no arm consumes',
	array( null, null ),
	array( bws_try_slot_arm( BWS_SOURCE_KIND_UNRESOLVED ), bws_try_slot_base_branch_kind( BWS_SOURCE_KIND_UNRESOLVED ) )
);

// Totality, stated as a property rather than as the rows above: whatever goes in, what
// comes out is either a refusal or a key that exists and is not itself a branch
// instruction. The refusal arm of this is not decoration — the caller dereferences the
// re-looked-up arm, so a branch that refuses without the caller re-checking trades a
// wrong value for a fatal.
foreach ( array( 'post', 'term', 'user', 'meta_row', 'site', 'render_time', '', 'wormhole', BWS_SOURCE_KIND_UNRESOLVED ) as $probe ) {
	$landed = bws_try_slot_base_branch_kind( $probe );
	if ( null === $landed ) {
		assert_same( "A4.9 branch of '{$probe}' refuses", BWS_SOURCE_KIND_UNRESOLVED, $probe );
		continue;
	}
	assert_true( "A4.9 branch of '{$probe}' lands on a real arm", null !== bws_try_slot_arm( $landed ) );
	assert_true( "A4.10 branch of '{$probe}' does not re-branch", 'branch' !== bws_try_slot_arm( $landed )['fn'] );
}

// ===========================================================================
// §A5 — the two rows asserted ahead of their wire (#103 acceptance)
// ===========================================================================
echo "\n§A5 — user and meta_row\n";

// The user row is BRANCHABLE and names a renderer — both asserted below, and asserting them
// before anything could reach them is what made #108 a wiring change rather than a table
// change: text, title and content carry a try_user_fn and take this arm on an author
// archive. Which templates carry one, and what a template without one does instead, is the
// dispatcher's rule and is NOT pinned here or anywhere else pure — see
// generate_base_try_tags() for it and tools/test/text-test-matrix.md §T8 for its only
// evidence (accepted coverage gap, noted on FW-43's row).
assert_true( 'A5.1 the user row is branchable', bws_try_slot_arm( 'user' )['branchable'] );
assert_same( 'A5.2 the user row names its own renderer', 'user', bws_try_slot_arm( 'user' )['fn'] );

// meta_row is refused on the CHAIN axis and reachable on the BASE axis. Both are asserted
// above; restated together here because reading either alone gets the rule backwards.
assert_same( 'A5.3 meta_row as a chain kind is refused', '', bws_try_slot_arm( 'meta_row' )['fn'] );
assert_same( 'A5.4 meta_row as a base kind reaches the post arm', 'post', bws_try_slot_base_branch_kind( 'meta_row' ) );

// ===========================================================================
// §A6 — the SITE branch is taken by resolved kind, never by token spelling
// ===========================================================================
// The regression this pins (slice A, ADR 0007 era): email/phone selected their site
// branch by comparing the serialized token to the literal 'site'. Chain wire spells
// the same source differently, so a DECORATED root-only site chain fell into the
// post branch and read the AMBIENT entity — a plausible value from the wrong entity,
// which a selecting try_ slot treats as a win, so the fallback chain never ran.
// Pinned AT THE DISPATCH, not at the two tags: the point is that the class cannot
// recur, and any consumer that asks the dispatch gets the right branch for every
// spelling of the source.
echo "\n§A6 — a decorated root-only site chain still resolves to the site arm\n";

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
}
require_once __DIR__ . '/../../includes/helpers/serialization-order.php';
require_once __DIR__ . '/../../includes/helpers/slot-fold.php';

// (Flat `src:site` and a bare chain root are the SAME string — one row covers both.)
// bws_fold_src_resolution() IS the shipped dispatch: email/phone call it through
// bws_base_src_resolution(), a documented no-fallback shim (base-shared.php) that
// returns this function's answer verbatim — so pinning here pins the shipped path.
foreach ( array(
	'A6.1 bare token'                 => 'site',
	'A6.3 decorated root (limit)'     => 'site,limit[2]',
	'A6.4 decorated root (extra tok)' => 'site,x[y]',
) as $label => $wire ) {
	$kind = bws_fold_src_resolution( array( 'src' => $wire ) )['kind'];
	assert_same( "{$label} → kind site", 'site', $kind );
	assert_same( "{$label} → the site arm consumes it", 'site', bws_try_slot_arm( $kind )['fn'] );
}
// The contrast row: a site root with a real step behind it is NOT a site read any
// more — the chain moved on — and must not take the site branch.
assert_same(
	'A6.5 site root + rows step is not the site arm\'s',
	'meta_row',
	bws_fold_src_resolution( array( 'src' => 'site;rows,rows' ) )['kind']
);

// §A7 — the CLASS pin: no shipped code compares a serialized `src` to a literal.
// The two sweeps are S-33's, run mechanically so the third instance of "re-derive
// from the wire what the dispatch already decided" fails here by name instead of
// surviving review. bws_fold_src_root_token()/resolution comparisons are the SEAM
// and do not match these shapes.
echo "\n§A7 — no wire-string source compare survives in includes/\n";

$includes = dirname( __DIR__, 2 ) . '/includes';
$hits     = array();
$iter     = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $includes ) );
foreach ( $iter as $file ) {
	if ( 'php' !== pathinfo( (string) $file, PATHINFO_EXTENSION ) ) {
		continue;
	}
	foreach ( explode( "\n", (string) file_get_contents( (string) $file ) ) as $n => $line ) {
		if ( preg_match( "/=== *\( *\\\$(options|opts|slot_opts)\['src'\]/", $line )
			|| preg_match( "/\['src'\] *(===|==|!==) *'/", $line ) ) {
			$hits[] = basename( (string) $file ) . ':' . ( $n + 1 );
		}
	}
}
assert_same( 'A7.1 both sweep patterns return nothing', array(), $hits );

echo "\n{$count} assertions, {$failures} failure(s)\n";
exit( $failures > 0 ? 1 : 0 );
