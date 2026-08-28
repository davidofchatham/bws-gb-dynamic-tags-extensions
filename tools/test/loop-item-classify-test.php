<?php
/**
 * Standalone unit harness for QUERY-LOOP ITEM RECOGNITION — bws_classify_loop_item()
 * and the two shape readers beside it (bws_loop_item_term_id / bws_loop_item_user_id),
 * plus the predicate the render cores gate on (bws_loop_item_is_post_or_row) and the
 * SOURCE GATE applied to a loop item (bws_loop_item_gated_post_id, #122). All in
 * includes/helpers/field-helpers.php.
 *
 * THE REAL FILE IS LOADED, NOT COPIED. field-helpers.php defines functions only, so it
 * loads inert with ABSPATH defined; a test-local copy of the recognizer is exactly the
 * drift the single-owner rule exists to prevent. Its WordPress dependencies are three
 * lookups — get_post / get_term / get_userdata — and they are STUBBED against a fixed
 * three-entity world below, because what is under test is which SHAPE resolves to which
 * kind, not what WordPress stores.
 *
 * WHAT THIS HARNESS CAN AND CANNOT SEE. It sees the classification, the predicate, and
 * — since §C8 — the BRANCH a gate refusal takes, because bws_source_gate() is stubbed
 * by definition the way traversal-pipeline-test.php and fold-chain-compile-test.php
 * already stub it. It does NOT see the gate's own verdicts: whether a trashed post is
 * refused for everyone is WP-bound and measured on the testbed, never here.
 * It does NOT see a rendered tag either: the damage a mis-classified item does is a wrong
 * READ one layer down, which needs a real site (see §C6 and tools/test/loop-test-matrix.md).
 * The complementary pin on the other side of the seam — a signal of each kind mapped
 * onto a source kind — is tools/test/traversal-pipeline-test.php's #123 section.
 *
 * SCOPE:
 *   §C1  the five answers plus the absence, one row per shape
 *   §C2  the duck-typed arms — the co-resident extension's user items are stdClass
 *   §C3  the post/array ASYMMETRY, pinned as a pair
 *   §C4  the term-before-user ORDER, on an item that satisfies both arms
 *   §C5  the taxonomy cross-check
 *   §C6  bws_loop_item_is_post_or_row() — the predicate six render sites gate on
 *   §C7  bws_get_loop_item_context() — returned shape, the cache, and `in_loop` derived
 *        from the classification rather than tested a second time
 *   §C8  the source gate on a loop item — that it is consulted, that a refusal is a
 *        HARD STOP rather than a skipped branch, that mode 2b is untouched, and a
 *        CENSUS of every ungated `item_post_id` consumer in the tree
 *
 * Run:  php tools/test/loop-item-classify-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// ── The stub world ────────────────────────────────────────────────────────────
// Posts 1 and 5 exist. Term 7 is a `department`, term 1 a `category`. Users 2 and 4
// exist. Ids 1, 2 and 7 deliberately OVERLAP across the three entity types — that
// collision is the whole defect (#123), and a harness whose ids were disjoint would
// pass while proving nothing.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
}
if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term {
		public $term_id;
		public $taxonomy;
		public function __construct( $id, $tax = 'category' ) { $this->term_id = $id; $this->taxonomy = $tax; }
	}
}
if ( ! class_exists( 'WP_User' ) ) {
	class WP_User { public $ID; public function __construct( $id ) { $this->ID = $id; } }
}

const STUB_POSTS = array( 1, 5, 6 );
const STUB_TERMS = array( 1 => 'category', 7 => 'department' );
const STUB_USERS = array( 2, 4 );

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $id = null ) {
		return in_array( (int) $id, STUB_POSTS, true ) ? new WP_Post( (int) $id ) : null;
	}
}
if ( ! function_exists( 'get_term' ) ) {
	function get_term( $id, $tax = '' ) {
		return isset( STUB_TERMS[ (int) $id ] ) ? new WP_Term( (int) $id, STUB_TERMS[ (int) $id ] ) : null;
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( $id ) {
		return in_array( (int) $id, STUB_USERS, true ) ? new WP_User( (int) $id ) : false;
	}
}

// The SOURCE GATE, stubbed. The real bws_source_gate() lives in traversal-pipeline.php,
// is WP-bound, and is not loaded here — the same stub-by-definition traversal-pipeline-test.php
// and fold-chain-compile-test.php each declare in their own preamble (grep either for
// `function bws_source_gate`; the technique is the citation, not a line).
// Post 6 exists and is REFUSED, which is the shape of a draft/private/trashed row: it
// classifies perfectly well and then may not be read.
const STUB_GATE_REFUSES = array( 6 );
if ( ! function_exists( 'bws_source_gate' ) ) {
	function bws_source_gate( array $source ) {
		return ! ( 'post' === ( $source['kind'] ?? '' )
			&& in_array( (int) ( $source['id'] ?? 0 ), STUB_GATE_REFUSES, true ) );
	}
}

// The READ side, stubbed to echo what it was asked for, so a row states WHICH entity
// answered rather than merely that something did. bws_meta_handler_read() is the real
// function from field-helpers.php and falls back to these when GB's handler is absent.
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key = '', $single = false ) {
		return "post{$id}:{$key}";
	}
}
if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $id, $key = '', $single = false ) {
		return "term{$id}:{$key}";
	}
}
$GLOBALS['stub_queried_object'] = null;
if ( ! function_exists( 'get_queried_object' ) ) {
	function get_queried_object() {
		return $GLOBALS['stub_queried_object'];
	}
}

require __DIR__ . '/../../includes/helpers/field-helpers.php';

$failures = 0;
$count    = 0;

function assert_same( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo '         expected: ' . var_export( $expected, true ) . "\n";
	echo '         actual:   ' . var_export( $actual, true ) . "\n";
}

/** kind:id, so a row reads as one string instead of two asserts. */
function classify( $item, string $qt = '' ): string {
	$c = bws_classify_loop_item( $item, $qt );
	return $c['kind'] . ':' . $c['id'];
}

function inst( $item, string $qt = '' ) {
	$i          = new stdClass();
	$i->context = array( 'generateblocks/loopItem' => $item, 'generateblocks/queryType' => $qt );
	return $i;
}

echo "\n=== C1 - the five answers, plus the absence ===\n";

assert_same( 'C1.1 null is NO ITEM, not an unreadable one', ':0', classify( null ) );
assert_same( 'C1.2 false is NO ITEM', ':0', classify( false ) );
assert_same( 'C1.3 empty string is NO ITEM', ':0', classify( '' ) );
assert_same( 'C1.4 WP_Post naming a live post', 'post:5', classify( new WP_Post( 5 ) ) );
assert_same( 'C1.5 bare int', 'post:5', classify( 5 ) );
assert_same( 'C1.6 numeric string', 'post:5', classify( '5' ) );
assert_same( 'C1.7 array with ID, under post_meta', 'post:5', classify( array( 'ID' => 5 ), 'post_meta' ) );
assert_same( 'C1.8 the SAME array under any other query type is a ROW - the pre-existing narrowing', 'row:0', classify( array( 'ID' => 5 ), 'WP_Query' ) );
assert_same( 'C1.9 array with no ID is a repeater ROW', 'row:0', classify( array( 'name' => 'Ada', 'qty' => 0 ) ) );
assert_same( 'C1.10 empty array is still a ROW, not an absence', 'row:0', classify( array() ) );
assert_same( 'C1.11 WP_Term', 'term:7', classify( new WP_Term( 7, 'department' ) ) );
assert_same( 'C1.12 WP_User', 'user:2', classify( new WP_User( 2 ) ) );
assert_same( 'C1.13 an object nothing recognizes is UNKNOWN, never a row and never an absence', 'unknown:0', classify( (object) array( 'id' => 3, 'name' => 'A Product' ) ) );
assert_same( 'C1.14 a bool that is not false is UNKNOWN', 'unknown:0', classify( true ) );
assert_same( 'C1.15 a non-numeric string is UNKNOWN', 'unknown:0', classify( 'sales' ) );

echo "\n=== C2 - the duck-typed arms (the item shapes measured on the reference site) ===\n";

// The co-resident extension's USER items are plain stdClass records, NOT WP_User. An
// instanceof-only recognizer leaves every user loop leaking, which is why these rows
// are the load-bearing ones rather than C1.11/C1.12.
$std_user = (object) array(
	'ID'            => 2,
	'user_login'    => 'fixture-author',
	'user_nicename' => 'fixture-author',
	'user_email'    => 'author@example.test',
	'display_name'  => 'Fixture Author',
);
assert_same( 'C2.1 stdClass user record', 'user:2', classify( $std_user ) );
assert_same( 'C2.2 stdClass term record', 'term:7', classify( (object) array( 'term_id' => 7, 'taxonomy' => 'department' ) ) );
assert_same( 'C2.3 user_login ALONE is enough beside ID', 'user:2', classify( (object) array( 'ID' => 2, 'user_login' => 'x' ) ) );
assert_same( 'C2.4 user_email ALONE is enough beside ID', 'user:2', classify( (object) array( 'ID' => 2, 'user_email' => 'x@y.test' ) ) );
assert_same( 'C2.5 ID with NO user marker is UNKNOWN - the weak marker on its own is the collision', 'unknown:0', classify( (object) array( 'ID' => 2 ) ) );
assert_same( 'C2.6 term_id with NO taxonomy is UNKNOWN - same rule, term side', 'unknown:0', classify( (object) array( 'term_id' => 7 ) ) );
assert_same( 'C2.7 a user-shaped record naming a user that is gone', 'unknown:0', classify( (object) array( 'ID' => 99, 'user_login' => 'ghost' ) ) );
assert_same( 'C2.8 a term-shaped record naming a term that is gone', 'unknown:0', classify( (object) array( 'term_id' => 99, 'taxonomy' => 'department' ) ) );

echo "\n=== C3 - the ASYMMETRY: a post that is not there refuses; an array does not ===\n";

// PINNED AS A PAIR, deliberately. Each half reads as arbitrary alone and the two
// together are the rule: a WP_Post/numeric item has SAID what it is and then failed to
// be it, so there is nothing left to read; an array is a repeater row whether or not a
// post id can be prised out of it, so failing to find one takes nothing away. The axis
// is bws_classify_loop_item()'s PHPDoc; these rows hold it.
assert_same( 'C3.1 WP_Post naming a post that is gone -> UNKNOWN (the caller must refuse)', 'unknown:0', classify( new WP_Post( 999 ) ) );
assert_same( 'C3.2 numeric id naming a post that is gone -> UNKNOWN', 'unknown:0', classify( 999 ) );
assert_same( 'C3.3 array whose ID names a post that is gone -> ROW (unchanged meaning)', 'row:0', classify( array( 'ID' => 999 ), 'post_meta' ) );
assert_same( 'C3.4 CONTROL: the same array with a live ID is a POST', 'post:5', classify( array( 'ID' => 5 ), 'post_meta' ) );

echo "\n=== C4 - ORDER: term is asked before user, and an item satisfying BOTH proves it ===\n";

// "Disjoint by construction" is a claim about objects other people build. This one
// satisfies both arms. Swapping the two arms in bws_classify_loop_item() flips this row
// to `user:2` — which is what makes the order behaviour rather than layout.
$hybrid = (object) array(
	'term_id'    => 7,
	'taxonomy'   => 'department',
	'ID'         => 2,
	'user_login' => 'fixture-author',
);
assert_same( 'C4.1 an item answering BOTH arms resolves TERM - the stronger marker pair wins', 'term:7', classify( $hybrid ) );

// The tie-break only decides when both arms would ANSWER. Break the term half and the
// same object falls to the user arm, so C4.1 measures precedence and not merely that
// the term arm runs at all.
$hybrid_bad_term          = clone $hybrid;
$hybrid_bad_term->term_id = 999;
assert_same( 'C4.2 with the term half unresolvable the same item answers USER', 'user:2', classify( $hybrid_bad_term ) );

echo "\n=== C5 - the taxonomy cross-check ===\n";

assert_same( 'C5.1 CONTROL: term 7 declared as its real taxonomy', 'term:7', classify( (object) array( 'term_id' => 7, 'taxonomy' => 'department' ) ) );
assert_same( 'C5.2 a STALE/FOREIGN pairing - term 7 declared as a category - is refused', 'unknown:0', classify( (object) array( 'term_id' => 7, 'taxonomy' => 'category' ) ) );
assert_same( 'C5.3 the mismatch does not fall through to the term it names', 0, bws_loop_item_term_id( (object) array( 'term_id' => 7, 'taxonomy' => 'category' ) ) );
assert_same( 'C5.4 a WP_Term is taken on its class - WordPress built it, so its pair agrees by construction', 7, bws_loop_item_term_id( new WP_Term( 7, 'department' ) ) );

echo "\n=== C6 - bws_loop_item_is_post_or_row(): the predicate six render sites gate on ===\n";

// The two TRUE kinds are bws_read_field()'s loop branch stated as a predicate: it reads
// a post's meta for a POST item and $loop_item[$key] for a ROW, and serves nothing else.
// What a FALSE costs is not visible from here — a term or user item let past reaches
// that function's term-archive fallback and returns the surrounding archive's meta.
// That is a rendered read, measured on the testbed rather than here.
assert_same( 'C6.1 a post item', true, bws_loop_item_is_post_or_row( inst( new WP_Post( 5 ) ) ) );
assert_same( 'C6.2 a numeric post item', true, bws_loop_item_is_post_or_row( inst( 5 ) ) );
assert_same( 'C6.3 a repeater row', true, bws_loop_item_is_post_or_row( inst( array( 'name' => 'Ada' ) ) ) );
assert_same( 'C6.4 a TERM item - false, though in_loop is true', false, bws_loop_item_is_post_or_row( inst( new WP_Term( 7, 'department' ) ) ) );
assert_same( 'C6.5 a USER item - false, though in_loop is true', false, bws_loop_item_is_post_or_row( inst( $std_user ) ) );
assert_same( 'C6.6 an UNKNOWN item - false', false, bws_loop_item_is_post_or_row( inst( (object) array( 'id' => 3, 'name' => 'A Product' ) ) ) );
assert_same( 'C6.7 no loop at all - false', false, bws_loop_item_is_post_or_row( inst( null ) ) );
assert_same( 'C6.8 not a block instance at all - false', false, bws_loop_item_is_post_or_row( null ) );

// The divergence itself, stated as one row: these two answers used to be the same test,
// and the six render sites were reading the wrong one of them.
$term_inst = inst( new WP_Term( 7, 'department' ) );
assert_same(
	'C6.9 in_loop and the predicate DISAGREE on a term item, and that is the point',
	'in_loop=true predicate=false',
	'in_loop=' . var_export( bws_get_loop_item_context( $term_inst )['in_loop'], true )
		. ' predicate=' . var_export( bws_loop_item_is_post_or_row( $term_inst ), true )
);

echo "\n=== C7 - bws_get_loop_item_context(): shape, cache, and in_loop ===\n";

$ctx_none = bws_get_loop_item_context( inst( null ) );
assert_same( 'C7.1 no item -> in_loop false', false, $ctx_none['in_loop'] );
assert_same( 'C7.2 no item -> item_kind empty', '', $ctx_none['item_kind'] );
assert_same( 'C7.3 no item -> item_post_id false', false, $ctx_none['item_post_id'] );

$ctx_post = bws_get_loop_item_context( inst( new WP_Post( 5 ) ) );
assert_same( 'C7.4 post item -> item_post_id carries it', 5, $ctx_post['item_post_id'] );
assert_same( 'C7.5 post item -> item_kind/item_id agree with it', 'post:5', $ctx_post['item_kind'] . ':' . $ctx_post['item_id'] );

$ctx_term = bws_get_loop_item_context( inst( new WP_Term( 7, 'department' ) ) );
assert_same( 'C7.6 term item -> in_loop true', true, $ctx_term['in_loop'] );
assert_same( 'C7.7 term item -> item_post_id STAYS FALSE (it holds a post and only a post)', false, $ctx_term['item_post_id'] );
assert_same( 'C7.8 term item -> item_kind/item_id carry the entity', 'term:7', $ctx_term['item_kind'] . ':' . $ctx_term['item_id'] );

$ctx_unknown = bws_get_loop_item_context( inst( (object) array( 'id' => 3, 'name' => 'A Product' ) ) );
assert_same( 'C7.9 UNKNOWN is in a loop - "item unreadable" is NOT "no loop"', true, $ctx_unknown['in_loop'] );
assert_same( 'C7.10 UNKNOWN reports its kind so the caller can refuse', 'unknown', $ctx_unknown['item_kind'] );

// The cache is READ, not merely written: poison it and the poisoned answer comes back.
$cached = inst( new WP_Post( 5 ) );
bws_get_loop_item_context( $cached );
assert_same(
	'C7.11 the classification is cached on the context',
	'post:5',
	$cached->context['bws/loopItemEntity']['kind'] . ':' . $cached->context['bws/loopItemEntity']['id']
);
assert_same( 'C7.12 the legacy post-id cache key is written beside it', 5, $cached->context['bws/loopItemPostId'] );
$cached->context['bws/loopItemEntity'] = array( 'kind' => 'term', 'id' => 7 );
$reread                                = bws_get_loop_item_context( $cached );
assert_same( 'C7.13 a second call READS the cache rather than re-classifying', 'term:7', $reread['item_kind'] . ':' . $reread['item_id'] );

// NOTHING is cached when there is no loop — a context with no item keeps the keys it
// arrived with, so a non-loop render is not given loop bookkeeping it never asked for.
$no_loop = inst( null );
bws_get_loop_item_context( $no_loop );
assert_same(
	'C7.14 no item -> no cache keys written',
	false,
	isset( $no_loop->context['bws/loopItemEntity'] ) || isset( $no_loop->context['bws/loopItemPostId'] )
);

echo "\n=== C8 - the source gate on a loop item (#122) ===\n";

// The gate itself is bws_source_gate() in traversal-pipeline.php and is WP-bound
// (get_post / is_post_status_viewable / current_user_can); the STUB above is what makes
// this section pure, exactly as traversal-pipeline-test.php and fold-chain-compile-test.php
// already do. So what these rows pin is that the gate is CONSULTED and what a refusal
// COSTS — never the gate's own verdicts, which are measured on the testbed
// (tools/test/fold-test-matrix.md, the /matrix-gate/ loop group).

assert_same( 'C8.1 a loop post the gate passes is read', 'post5:name', bws_read_field( 'name', inst( new WP_Post( 5 ) ), false ) );
assert_same( 'C8.2 a loop post the gate REFUSES reads nothing', null, bws_read_field( 'name', inst( new WP_Post( 6 ) ), false ) );

// THE LOAD-BEARING PAIR. A refusal must RETURN, not skip its branch: a post item's raw
// value is a WP_Post rather than an array, so a skipped branch walks past the repeater
// arm and into the term-archive read, which on an archive page answers with the
// SURROUNDING term's meta — a plausible value from an entity the wire never named.
// C8.4 is what stops C8.3 passing for the wrong reason: without it, a term-archive
// fallback that was simply broken would satisfy C8.3.
$GLOBALS['stub_queried_object'] = new WP_Term( 7, 'department' );
assert_same( 'C8.3 HARD STOP - a refused loop post does NOT fall through to the term archive', null, bws_read_field( 'name', inst( new WP_Post( 6 ) ), false ) );
assert_same( 'C8.4 CONTROL - the same archive DOES answer when there is no loop at all', 'term7:name', bws_read_field( 'name', inst( null ), false ) );
$GLOBALS['stub_queried_object'] = null;

// Mode 2b. A repeater row has no post identity, so this branch never reaches the gate at
// all — which is what the row below holds. What the gate itself does with a `meta_row`
// source is bws_source_gate()'s own PHPDoc (nothing here pins it, and the stub above
// would agree with any answer). It is also the branch the falsy-id read exists for, so a
// fix that refused falsy ids wholesale would blank it.
assert_same( 'C8.5 a repeater ROW is untouched by the gate', 'Ada', bws_read_field( 'name', inst( array( 'name' => 'Ada' ) ), false ) );

// An explicit id is NOT gated here, deliberately: it arrives from a caller that has
// already resolved and gated it (bws_resolve_field_values reads it off a source that
// came through bws_run_traversal), and re-gating would be a second application of one
// criterion. The v1.7.1 explicit-wins invariant is what this row also holds.
assert_same( 'C8.6 an explicit post id still wins over the loop item, and is not re-gated', 'post5:name', bws_read_field( 'name', inst( new WP_Post( 6 ) ), 5 ) );

// ── C8.7 the CENSUS ───────────────────────────────────────────────────────────
// `item_post_id` is the UNGATED identity, and a consumer that reads it and then reads
// meta off it without gating is how #122 was made. Prose cannot fail a suite, so the
// consumer set is measured rather than stated in a comment.
//
// DEFAULT-IN, following gb-output-boundary-test.php §B6: every .php in the repo is
// scanned unless its directory is skipped, so a new consumer ANYWHERE fails this row by
// name. Scoping the scan to includes/ was the first spelling and it was already blind —
// tools/debug/bws-ctx-probe.php reads the key today and went unseen.
//
// A WRITE IS NOT A CONSUMER. bws_get_loop_item_context() assigns the key it publishes;
// counting that would make the owner look like a client of itself, and it was miscounted
// exactly that way before this comment existed.
//
// The three that may hold one, and why each is allowed:
//   field-helpers.php      2 — bws_loop_item_gated_post_id() (which gates) and
//                              bws_read_field()'s loop branch (which calls it).
//   traversal-pipeline.php 2 — bws_resolve_base_source(), the factory route, gated one
//                              layer down by bws_run_traversal. Why those two sites are
//                              deliberately NOT merged is that helper's PHPDoc.
//   bws-ctx-probe.php      1 — a debug probe that REPORTS the identity and never reads a
//                              field off it. The ungated identity is what it wants.
// A FOURTH ENTRY, or a rising count in any of these, is a site to go and look at: gated,
// or a reporter like the probe, or the defect.
$census_skip   = array( 'vendor', 'node_modules', 'libs', '.git' );
$census_exempt = array( 'tools/test/loop-item-classify-test.php' );   // reads the key to ASSERT its contract.
$census_root   = dirname( __DIR__, 2 );
$census        = array();
$census_files  = 0;
$census_walk   = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $census_root, FilesystemIterator::SKIP_DOTS ),
		static function ( $current ) use ( $census_skip ) {
			return ! ( $current->isDir() && in_array( $current->getFilename(), $census_skip, true ) );
		}
	)
);
foreach ( $census_walk as $file ) {
	if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}
	$census_files++;
	$rel = strtr( substr( $file->getPathname(), strlen( $census_root ) + 1 ), DIRECTORY_SEPARATOR, '/' );
	if ( in_array( $rel, $census_exempt, true ) ) {
		continue;
	}
	foreach ( preg_split( '~\R~', (string) file_get_contents( $file->getPathname() ) ) as $line ) {
		$trimmed = ltrim( $line );
		if ( '' === $trimmed || '*' === $trimmed[0] || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '/*' ) ) {
			continue;   // a docblock or comment mention is not a consumer.
		}
		// BOTH quote styles. The repo writes single throughout, so a double-quoted
		// subscript is exactly the shape that would slip past a census nobody thought
		// to widen — and the point of a census is to catch what nobody thought of.
		if ( ! preg_match( '/\[\s*["\']item_post_id["\']\s*\]/', $line ) ) {
			continue;
		}
		if ( preg_match( '/\[\s*["\']item_post_id["\']\s*\]\s*=[^=]/', $line ) ) {
			continue;   // the owner PUBLISHING the key, not a client reading it.
		}
		$census[ basename( $rel ) ] = ( $census[ basename( $rel ) ] ?? 0 ) + 1;
	}
}
ksort( $census );

// NON-VACUITY FIRST. A scan that reached nothing, or a pattern that stopped matching,
// reports a clean tree — the one failure mode a "found exactly these" assertion cannot
// tell apart from success.
assert_same( 'C8.7a NON-VACUITY - the scan reached the plugin source', true, $census_files > 20 );
assert_same( 'C8.7b NON-VACUITY - the pattern still matches real code (the gated helper is found)', true, isset( $census['field-helpers.php'] ) );
foreach ( $census_exempt as $census_path ) {
	// An exemption for a file that no longer exists is a hole nobody can see: the reason
	// it was excused is gone and the row would excuse whatever next takes that path.
	assert_same( "C8.7c the exemption `{$census_path}` still names a real file", true, is_file( $census_root . '/' . $census_path ) );
}
assert_same(
	'C8.7 CENSUS - every file that READS item_post_id, tree-wide; a fourth is one nobody has reviewed',
	'bws-ctx-probe.php=1, field-helpers.php=2, traversal-pipeline.php=2',
	implode( ', ', array_map( static fn( $f, $n ) => "{$f}={$n}", array_keys( $census ), $census ) )
);

echo "\n";
echo $failures
	? "FAILED - {$failures} of {$count} assertions\n"
	: "PASSED - {$count} assertions\n";
exit( $failures ? 1 : 0 );
