<?php
/**
 * Standalone unit harness for QUERY-LOOP ITEM RECOGNITION — bws_classify_loop_item()
 * and the two shape readers beside it (bws_loop_item_term_id / bws_loop_item_user_id),
 * plus the predicate the render cores gate on (bws_loop_item_is_post_or_row). All in
 * includes/helpers/field-helpers.php.
 *
 * THE REAL FILE IS LOADED, NOT COPIED. field-helpers.php defines functions only, so it
 * loads inert with ABSPATH defined; a test-local copy of the recognizer is exactly the
 * drift the single-owner rule exists to prevent. Its WordPress dependencies are three
 * lookups — get_post / get_term / get_userdata — and they are STUBBED against a fixed
 * three-entity world below, because what is under test is which SHAPE resolves to which
 * kind, not what WordPress stores.
 *
 * WHAT THIS HARNESS CAN AND CANNOT SEE. It sees the classification and the predicate.
 * It does NOT see a rendered tag: the damage a mis-classified item does is a wrong READ
 * one layer down, which needs a real site (see §C6 and tools/test/loop-test-matrix.md).
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
 *   §C7  bws_get_loop_row_context() — returned shape, the cache, and `in_loop` derived
 *        from the classification rather than tested a second time
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

const STUB_POSTS = array( 1, 5 );
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
	'in_loop=' . var_export( bws_get_loop_row_context( $term_inst )['in_loop'], true )
		. ' predicate=' . var_export( bws_loop_item_is_post_or_row( $term_inst ), true )
);

echo "\n=== C7 - bws_get_loop_row_context(): shape, cache, and in_loop ===\n";

$ctx_none = bws_get_loop_row_context( inst( null ) );
assert_same( 'C7.1 no item -> in_loop false', false, $ctx_none['in_loop'] );
assert_same( 'C7.2 no item -> item_kind empty', '', $ctx_none['item_kind'] );
assert_same( 'C7.3 no item -> row_post_id false', false, $ctx_none['row_post_id'] );

$ctx_post = bws_get_loop_row_context( inst( new WP_Post( 5 ) ) );
assert_same( 'C7.4 post item -> row_post_id carries it', 5, $ctx_post['row_post_id'] );
assert_same( 'C7.5 post item -> item_kind/item_id agree with it', 'post:5', $ctx_post['item_kind'] . ':' . $ctx_post['item_id'] );

$ctx_term = bws_get_loop_row_context( inst( new WP_Term( 7, 'department' ) ) );
assert_same( 'C7.6 term item -> in_loop true', true, $ctx_term['in_loop'] );
assert_same( 'C7.7 term item -> row_post_id STAYS FALSE (it holds a post and only a post)', false, $ctx_term['row_post_id'] );
assert_same( 'C7.8 term item -> item_kind/item_id carry the entity', 'term:7', $ctx_term['item_kind'] . ':' . $ctx_term['item_id'] );

$ctx_unknown = bws_get_loop_row_context( inst( (object) array( 'id' => 3, 'name' => 'A Product' ) ) );
assert_same( 'C7.9 UNKNOWN is in a loop - "item unreadable" is NOT "no loop"', true, $ctx_unknown['in_loop'] );
assert_same( 'C7.10 UNKNOWN reports its kind so the caller can refuse', 'unknown', $ctx_unknown['item_kind'] );

// The cache is READ, not merely written: poison it and the poisoned answer comes back.
$cached = inst( new WP_Post( 5 ) );
bws_get_loop_row_context( $cached );
assert_same(
	'C7.11 the classification is cached on the context',
	'post:5',
	$cached->context['bws/loopItemEntity']['kind'] . ':' . $cached->context['bws/loopItemEntity']['id']
);
assert_same( 'C7.12 the legacy post-id cache key is written beside it', 5, $cached->context['bws/loopItemPostId'] );
$cached->context['bws/loopItemEntity'] = array( 'kind' => 'term', 'id' => 7 );
$reread                                = bws_get_loop_row_context( $cached );
assert_same( 'C7.13 a second call READS the cache rather than re-classifying', 'term:7', $reread['item_kind'] . ':' . $reread['item_id'] );

// NOTHING is cached when there is no loop — a context with no item keeps the keys it
// arrived with, so a non-loop render is not given loop bookkeeping it never asked for.
$no_loop = inst( null );
bws_get_loop_row_context( $no_loop );
assert_same(
	'C7.14 no item -> no cache keys written',
	false,
	isset( $no_loop->context['bws/loopItemEntity'] ) || isset( $no_loop->context['bws/loopItemPostId'] )
);

echo "\n";
echo $failures
	? "FAILED - {$failures} of {$count} assertions\n"
	: "PASSED - {$count} assertions\n";
exit( $failures ? 1 : 0 );
