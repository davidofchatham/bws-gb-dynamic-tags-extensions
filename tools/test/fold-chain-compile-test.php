<?php
/**
 * Standalone unit harness for the FW-56 chain COMPILER
 * (includes/helpers/slot-fold-compile.php) and the engine's per-step limit.
 *
 * The real files are loaded, never copied — the compile is pure but for one
 * sanitize_key (shimmed below), and a test-local copy of a mapping rule is the drift
 * the compiler exists to remove. traversal-pipeline.php comes along for §C6: the
 * per-hop `limit` is applied in bws_run_traversal(), and the fold stays pure because
 * the harness injects a stub reader.
 *
 * SCOPE:
 *   §C1  bws_fold_chain_is_wire()        chain-vs-token detection (conservative)
 *   §C2  bws_fold_chain_root()           the factory token; ROOT is not a step
 *   §C3  bws_fold_chain_to_steps()       slug→type map, argless drop, unknown slug
 *   §C4  per-step `limit`                emitted only when it BOUNDS (0/-1 = unlimited)
 *   §C5  bws_fold_chain_from_options()   depth-0 chain: chain wire OR legacy triple
 *   §C6  bws_field_values_assemble_steps / bws_wrapper_ref_steps
 *   §C7  bws_run_traversal()             per-hop limit, incl. an INTERMEDIATE step
 *
 * THE LOAD-BEARING PROPERTY IS EQUIVALENCE, not the new capability. §C5/§C6 pin the
 * pre-1.17.0 assembler outputs byte-for-byte for every legacy option shape, and §C2
 * pins that a legacy `src` value reaches the factory unchanged. The compiler replaced
 * two hand-written assemblers on paths every tag renders through; a chain that hops
 * twice is worth nothing if `src:ref|ref:x` moved a millimetre.
 *
 * Run:  php tools/test/fold-chain-compile-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// One WP call in the compiled path: the taxonomy slug of a `terms` hop, as the
// retired assembler had it. Same shim the traversal harness uses.
if ( ! function_exists( 'sanitize_key' ) ) {
	// LOWERCASE FIRST, then strip — WP's order. Stripping first deletes every capital
	// (they are not in `a-z`), so `My Tax!` came out `yax` instead of `mytax`. The same
	// inverted shim sat in traversal-pipeline-test.php, invisible because every slug
	// there was already lowercase.
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
}
// traversal-pipeline.php references these in its pure coercers.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
}
if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term { public $term_id; public function __construct( $id ) { $this->term_id = $id; } }
}
if ( ! function_exists( 'bws_extract_post_id' ) ) {
	function bws_extract_post_id( $post_data ) {
		if ( is_numeric( $post_data ) ) { return intval( $post_data ); }
		if ( $post_data instanceof WP_Post ) { return $post_data->ID; }
		if ( is_array( $post_data ) && isset( $post_data['ID'] ) ) { return $post_data['ID']; }
		return false;
	}
}

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
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
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

/** Build a chain link the way bws_fold_parse_chain() would. */
function link_step( string $slug, ?string $arg = null, ?string $limit = null ): array {
	return array( 'slug' => $slug, 'arg' => $arg, 'limit' => $limit, 'extra' => array() );
}

/** Parse a chain value, failing the case rather than the run on a grammar error. */
function chain_of( string $wire ): array {
	$parsed = bws_fold_parse_chain( $wire );
	return isset( $parsed['error'] ) ? array() : $parsed;
}

echo "§C1 bws_fold_chain_is_wire — a legacy token is NEVER a chain\n";

// Detection has to be conservative in ONE direction: mistaking a token for a chain
// changes what every unmigrated tag resolves. Shipped src values are bare words.
assert_same( "'' → not wire", false, bws_fold_chain_is_wire( '' ) );
assert_same( "'current' → token", false, bws_fold_chain_is_wire( 'current' ) );
assert_same( "'site' → token", false, bws_fold_chain_is_wire( 'site' ) );
assert_same( "'ref' → token (the LEGACY spelling, singular)", false, bws_fold_chain_is_wire( 'ref' ) );
assert_same( "'portal_resource' → registry token", false, bws_fold_chain_is_wire( 'portal_resource' ) );
assert_same( "'refs' → one-hop chain (plural)", true, bws_fold_chain_is_wire( 'refs' ) );
assert_same( "'terms' → one-hop chain", true, bws_fold_chain_is_wire( 'terms' ) );
assert_same( "'entries' → one-hop chain", true, bws_fold_chain_is_wire( 'entries' ) );
assert_same( 'step separator → chain', true, bws_fold_chain_is_wire( 'refs,office' ) );
assert_same( 'hop separator → chain', true, bws_fold_chain_is_wire( 'site;terms,category' ) );
assert_same( 'bracket → chain', true, bws_fold_chain_is_wire( 'refs,office,limit(2)' ) );

echo "\n§C2 bws_fold_chain_root — the ROOT is not a step\n";

assert_same( 'empty chain → ambient', '', bws_fold_chain_root( array() ) );
assert_same( 'leading refs hop → ambient root', '', bws_fold_chain_root( chain_of( 'refs,office' ) ) );
assert_same( 'leading terms hop → ambient root', '', bws_fold_chain_root( chain_of( 'terms,category' ) ) );
assert_same( 'leading entries hop → ambient root', '', bws_fold_chain_root( chain_of( 'entries,rows' ) ) );
assert_same( 'site root', 'site', bws_fold_chain_root( chain_of( 'site' ) ) );
assert_same( 'site root before a hop', 'site', bws_fold_chain_root( chain_of( 'site;entries,rows' ) ) );
assert_same( 'explicit current root', 'current', bws_fold_chain_root( chain_of( 'current' ) ) );
// A registry source name is opaque to the compiler — it goes to the factory verbatim.
assert_same( 'registry root passes through', 'portal_resource', bws_fold_chain_root( chain_of( 'portal_resource' ) ) );
// Slot sentinels are resolved by the container BEFORE compile; the root reader must
// not interpret one (it would have to know the accumulator to do so).
assert_same( 'same sentinel is returned verbatim', 'same', bws_fold_chain_root( chain_of( 'same' ) ) );

echo "\n§C2b bws_fold_src_root_token — the token every UNMIGRATED tag still hands the factory\n";

// Identity for every legacy shape whose `src` names a ROOT…
$legacy_roots = array(
	array(),
	array( 'src' => '' ),
	array( 'src' => 'current' ),
	array( 'src' => 'site' ),
	array( 'srcTermIn' => 'category' ),
	array( 'source' => 'site' ),
	array( 'src' => 'portal_resource' ),
);
foreach ( $legacy_roots as $i => $opts ) {
	$was = $opts['src'] ?? $opts['source'] ?? '';
	assert_same(
		"legacy shape #{$i} root === the old raw read (" . var_export( $was, true ) . ')',
		$was,
		bws_fold_src_root_token( $opts )
	);
}
// …and ONE deliberate difference: `ref` is a HOP, not a root, so the root reads ''.
// Inert at the factory by construction — its dispatch already excluded `ref` from the
// registry lookup (`'' !== $src && 'ref' !== $src`) precisely because a ref hop bases on
// the ambient entity. §C2c asserts that equivalence against the real factory rather than
// leaving it as an argument.
assert_same( "src:ref roots at '' — the hop is a STEP", '', bws_fold_src_root_token( array( 'src' => 'ref', 'ref' => 'office' ) ) );
assert_same( "src:ref with no field also roots at ''", '', bws_fold_src_root_token( array( 'src' => 'ref' ) ) );
assert_same(
	'chain wire root is the ROOT, not the whole value',
	'site',
	bws_fold_src_root_token( array( 'src' => 'site;entries,rows' ) )
);
assert_same(
	'chain leading with a hop roots at ambient',
	'',
	bws_fold_src_root_token( array( 'src' => 'refs,office;terms,category' ) )
);

echo "\n§C2c bws_resolve_base_source — the factory resolves legacy and chain wire ALIKE\n";

// The factory is drivable WP-free: signals are injected, and its registry/current-post
// helpers guard on class_exists (absent here → they fall through). This is the assertion
// the token-identity rows above cannot make — that the ONE token difference changes
// nothing about which base source comes out.
$signals_term = array(
	'queried_kind'            => 'term',
	'queried_id'              => 34,
	'is_tax'                  => true,
	'term_context_unresolved' => false,
	'loop'                    => array( 'in_loop' => false, 'row_post_id' => false, 'loop_item' => null ),
);
$signals_loop = array(
	'queried_kind'            => null,
	'queried_id'              => 0,
	'is_tax'                  => false,
	'term_context_unresolved' => false,
	'loop'                    => array( 'in_loop' => true, 'row_post_id' => 77, 'loop_item' => null ),
);
$pairs = array(
	'src:ref vs a refs chain, term archive'  => array(
		array( 'src' => 'ref', 'ref' => 'office' ),
		array( 'src' => 'refs,office' ),
		$signals_term,
		array( 'kind' => 'term', 'id' => 34 ),
	),
	'src:ref vs a refs chain, in a loop row' => array(
		array( 'src' => 'ref', 'ref' => 'office' ),
		array( 'src' => 'refs,office' ),
		$signals_loop,
		array( 'kind' => 'post', 'id' => 77 ),
	),
	'srcTermIn vs a terms chain'             => array(
		array( 'srcTermIn' => 'category' ),
		array( 'src' => 'terms,category' ),
		$signals_term,
		array( 'kind' => 'term', 'id' => 34 ),
	),
	'src:site vs a site-rooted chain'        => array(
		array( 'src' => 'site' ),
		array( 'src' => 'site;entries,rows' ),
		$signals_term,
		array( 'kind' => 'site' ),
	),
);
foreach ( $pairs as $why => $pair ) {
	list( $legacy, $chain_opts, $signals, $expected ) = $pair;
	assert_same( "factory, legacy wire ({$why})", $expected, bws_resolve_base_source( $legacy, null, $signals ) );
	assert_same( "factory, chain wire ({$why})", $expected, bws_resolve_base_source( $chain_opts, null, $signals ) );
}

echo "\n§C3 bws_fold_chain_to_steps — slug→type, argless drop, unknown slug\n";

assert_same( 'empty chain → no steps', array(), bws_fold_chain_to_steps( array() ) );
assert_same(
	'refs → ref/field',
	array( array( 'type' => 'ref', 'field' => 'office' ) ),
	bws_fold_chain_to_steps( chain_of( 'refs,office' ) )
);
assert_same(
	'terms → srcTermIn/slug',
	array( array( 'type' => 'srcTermIn', 'slug' => 'category' ) ),
	bws_fold_chain_to_steps( chain_of( 'terms,category' ) )
);
assert_same(
	'entries → rows/field (the repeater step, author-facing since 1.17.0)',
	array( array( 'type' => 'rows', 'field' => 'staff_rows' ) ),
	bws_fold_chain_to_steps( chain_of( 'entries,staff_rows' ) )
);
assert_same(
	'root is dropped, hops are kept in WIRE order',
	array(
		array( 'type' => 'ref', 'field' => 'office' ),
		array( 'type' => 'srcTermIn', 'slug' => 'category' ),
	),
	bws_fold_chain_to_steps( chain_of( 'current;refs,office;terms,category' ) )
);
assert_same(
	'TWO ref hops — the chain the flat assemblers could not express',
	array(
		array( 'type' => 'ref', 'field' => 'related_staff' ),
		array( 'type' => 'ref', 'field' => 'office' ),
	),
	bws_fold_chain_to_steps( chain_of( 'refs,related_staff;refs,office' ) )
);
assert_same(
	'terms slug is sanitized (as the retired assembler did)',
	array( array( 'type' => 'srcTermIn', 'slug' => 'mytax' ) ),
	bws_fold_chain_to_steps( chain_of( 'terms,My Tax!' ) )
);
// Argless fanning step: NO step, so the tag reads the ambient entity — byte-identical
// to legacy `src:ref` with no `ref` field. A field-less ref step would short-circuit
// the fold to empty and change what a stored (garbage) wire renders.
assert_same( 'argless refs → no step', array(), bws_fold_chain_to_steps( chain_of( 'refs' ) ) );
assert_same( 'argless terms → no step', array(), bws_fold_chain_to_steps( chain_of( 'terms' ) ) );
assert_same( 'argless entries → no step', array(), bws_fold_chain_to_steps( chain_of( 'entries' ) ) );
assert_same(
	'a slug that sanitizes to nothing → no step',
	array(),
	bws_fold_chain_to_steps( chain_of( 'terms,!!!' ) )
);
// Unknown vocabulary at a HOP position compiles to an unknown engine type, which the
// engine answers with an empty list — the chain short-circuits and the tag renders
// nothing. Dropping the step would read a DIFFERENT source than the wire states.
assert_same(
	'unknown hop slug → unknown engine type (short-circuits, never dropped)',
	array( array( 'type' => 'authors' ) ),
	bws_fold_chain_to_steps( chain_of( 'current;authors' ) )
);
assert_same(
	'a ROOT slug in a HOP position is unknown vocabulary there',
	array(
		array( 'type' => 'ref', 'field' => 'office' ),
		array( 'type' => 'site' ),
	),
	bws_fold_chain_to_steps( chain_of( 'refs,office;site' ) )
);
assert_same(
	'the engine yields nothing for an unknown type (so the chain empties)',
	array(),
	bws_run_traversal( array( array( 'kind' => 'post', 'id' => 1 ) ), array( array( 'type' => 'authors' ) ) )
);

echo "\n§C4 per-step limit — emitted ONLY when it bounds the step\n";

assert_same(
	'limit(2) rides the step',
	array( array( 'type' => 'ref', 'field' => 'office', 'limit' => 2 ) ),
	bws_fold_chain_to_steps( chain_of( 'refs,office,limit(2)' ) )
);
assert_same(
	'limit[2] — the same construct one level in (slot depth)',
	array( array( 'type' => 'ref', 'field' => 'office', 'limit' => 2 ) ),
	bws_fold_chain_to_steps( chain_of( 'refs,office,limit[2]' ) )
);
// 0 = unlimited and the engine spells that as an ABSENT key. Emitting `limit => 0`
// would work today but hands every reader a falsy value to mis-guard, and it breaks
// the byte-equality with the flat assemblers' output that §C5/§C6 rest on.
assert_same(
	'limit(0) = unlimited → no limit key at all',
	array( array( 'type' => 'ref', 'field' => 'office' ) ),
	bws_fold_chain_to_steps( chain_of( 'refs,office,limit(0)' ) )
);
assert_same(
	'limit(-1) parses to 0 → no limit key',
	array( array( 'type' => 'ref', 'field' => 'office' ) ),
	bws_fold_chain_to_steps( chain_of( 'refs,office,limit(-1)' ) )
);
assert_same(
	'a non-numeric limit never reaches compile (grammar error → empty chain)',
	array(),
	bws_fold_chain_to_steps( chain_of( 'refs,office,limit(lots)' ) )
);
// Struct-level guard: a hand-built chain (the migrator's, the control's) may carry a
// limit the grammar never validated.
assert_same(
	"struct limit '3' bounds as an int",
	array( array( 'type' => 'ref', 'field' => 'o', 'limit' => 3 ) ),
	bws_fold_chain_to_steps( array( link_step( 'refs', 'o', '3' ) ) )
);
assert_same(
	'struct limit garbage is ignored, not read as 0',
	array( array( 'type' => 'ref', 'field' => 'o' ) ),
	bws_fold_chain_to_steps( array( link_step( 'refs', 'o', 'abc' ) ) )
);
assert_same(
	'limit on an UNKNOWN hop still rides it',
	array( array( 'type' => 'authors', 'limit' => 2 ) ),
	bws_fold_chain_to_steps( array( link_step( 'current' ), link_step( 'authors', null, '2' ) ) )
);

echo "\n§C5 bws_fold_chain_from_options — depth-0: chain wire OR the legacy triple\n";

assert_same( 'bare options → empty chain', array(), bws_fold_chain_from_options( array() ) );
assert_same(
	'legacy src:ref + ref → refs step',
	array( link_step( 'refs', 'office' ) ),
	bws_fold_chain_from_options( array( 'src' => 'ref', 'ref' => 'office' ) )
);
assert_same(
	'legacy srcTermIn alone → terms step, ambient root',
	array( link_step( 'terms', 'category' ) ),
	bws_fold_chain_from_options( array( 'srcTermIn' => 'category' ) )
);
assert_same(
	'legacy src:ref + srcTermIn COMPOUND in #44 order',
	array( link_step( 'refs', 'x' ), link_step( 'terms', 'category' ) ),
	bws_fold_chain_from_options( array( 'src' => 'ref', 'ref' => 'x', 'srcTermIn' => 'category' ) )
);
assert_same(
	'legacy src:site → a ROOT step, no hop',
	array( link_step( 'site' ) ),
	bws_fold_chain_from_options( array( 'src' => 'site' ) )
);
assert_same(
	'chain wire is read as itself',
	chain_of( 'refs,office;terms,category' ),
	bws_fold_chain_from_options( array( 'src' => 'refs,office;terms,category' ) )
);
// A legacy `srcTermIn` beside chain wire is a hand-edit. It is a separate option KEY
// describing a hop, so dropping it would lose a configured hop; appending it keeps
// #44's order. When the chain already states a term hop, the chain wins.
assert_same(
	'srcTermIn beside chain wire APPENDS (no term hop in the chain)',
	array_merge( chain_of( 'refs,office' ), array( link_step( 'terms', 'category' ) ) ),
	bws_fold_chain_from_options( array( 'src' => 'refs,office', 'srcTermIn' => 'category' ) )
);
assert_same(
	'srcTermIn beside a chain that ALREADY hops terms → chain wins, no duplicate',
	chain_of( 'refs,office;terms,post_tag' ),
	bws_fold_chain_from_options( array( 'src' => 'refs,office;terms,post_tag', 'srcTermIn' => 'category' ) )
);
// Malformed wire must not fatal and must not fabricate a hop: it falls back to the
// legacy reading, i.e. the raw value as a root token (which resolves the ambient
// entity, since no registry source is named that).
assert_same(
	'unbalanced bracket → legacy reading (root token), never a fatal',
	array( link_step( 'refs,office(' ) ),
	bws_fold_chain_from_options( array( 'src' => 'refs,office(' ) )
);
assert_same(
	'`source` is honored as the legacy alias',
	array( link_step( 'site' ) ),
	bws_fold_chain_from_options( array( 'source' => 'site' ) )
);

echo "\n§C6 the two retired assemblers — EQUIVALENCE with their pre-1.17.0 output\n";

// These expectations are copied from the assertions that guarded the hand-written
// assemblers (traversal-pipeline-test.php §V13 / assemble rows), which is the point:
// the compiler is a refactor for every wire that exists today.
assert_same( 'assemble bare → no steps', array(), bws_field_values_assemble_steps( array() ) );
assert_same( 'assemble src:current → no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'current' ) ) );
assert_same( 'assemble src:site → no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'site' ) ) );
assert_same( 'assemble src:ref no field → no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'ref' ) ) );
assert_same(
	'assemble srcTermIn → [srcTermIn]',
	array( array( 'type' => 'srcTermIn', 'slug' => 'category' ) ),
	bws_field_values_assemble_steps( array( 'srcTermIn' => 'category' ) )
);
assert_same(
	'assemble src:ref → [ref]',
	array( array( 'type' => 'ref', 'field' => 'related' ) ),
	bws_field_values_assemble_steps( array( 'src' => 'ref', 'ref' => 'related' ) )
);
assert_same(
	'assemble #44 compound → [ref, srcTermIn] in that order',
	array(
		array( 'type' => 'ref', 'field' => 'x' ),
		array( 'type' => 'srcTermIn', 'slug' => 'post_tag' ),
	),
	bws_field_values_assemble_steps( array( 'srcTermIn' => 'post_tag', 'src' => 'ref', 'ref' => 'x' ) )
);
// NEW capability, same entry point: a depth-0 chain compiles in full.
assert_same(
	'assemble a depth-0 CHAIN → every hop',
	array(
		array( 'type' => 'ref', 'field' => 'a' ),
		array( 'type' => 'ref', 'field' => 'b' ),
		array( 'type' => 'srcTermIn', 'slug' => 'category' ),
	),
	bws_field_values_assemble_steps( array( 'src' => 'refs,a;refs,b;terms,category' ) )
);

echo "\n§C6b bws_wrapper_ref_steps — the LEADING RUN of ref steps (§V13, B2)\n";

assert_same( 'wrapper bare → no step', array(), bws_wrapper_ref_steps( array() ) );
assert_same( 'wrapper src:current → no step', array(), bws_wrapper_ref_steps( array( 'src' => 'current' ) ) );
assert_same( 'wrapper src:site → no step', array(), bws_wrapper_ref_steps( array( 'src' => 'site' ) ) );
assert_same( 'wrapper src:ref no field → no step', array(), bws_wrapper_ref_steps( array( 'src' => 'ref' ) ) );
assert_same(
	'wrapper src:ref → ref step',
	array( array( 'type' => 'ref', 'field' => 'related' ) ),
	bws_wrapper_ref_steps( array( 'src' => 'ref', 'ref' => 'related' ) )
);
// §V13/B2: the term hop is the CALLERS' job on the returned post id. Routing it
// through here would collapse the wrapper to false and empty those callers.
assert_same( 'wrapper srcTermIn → NO step', array(), bws_wrapper_ref_steps( array( 'srcTermIn' => 'category' ) ) );
assert_same(
	'wrapper ref beside srcTermIn → ref step only',
	array( array( 'type' => 'ref', 'field' => 'x' ) ),
	bws_wrapper_ref_steps( array( 'src' => 'ref', 'ref' => 'x', 'srcTermIn' => 'category' ) )
);
assert_same(
	'wrapper hops a MULTI-ref chain (new in 1.17.0)',
	array(
		array( 'type' => 'ref', 'field' => 'a' ),
		array( 'type' => 'ref', 'field' => 'b' ),
	),
	bws_wrapper_ref_steps( array( 'src' => 'refs,a;refs,b' ) )
);
// STOPS at the first non-ref hop rather than filtering: a ref step AFTER a dropped
// term hop would run against the wrong entity, which is the truncated-prefix hazard.
assert_same(
	'wrapper STOPS at a term hop, it does not filter past it',
	array( array( 'type' => 'ref', 'field' => 'a' ) ),
	bws_wrapper_ref_steps( array( 'src' => 'refs,a;terms,category;refs,b' ) )
);
assert_same(
	'wrapper stops at an entries hop too (a meta_row is not a post)',
	array(),
	bws_wrapper_ref_steps( array( 'src' => 'entries,rows;refs,a' ) )
);
assert_same(
	'wrapper carries a per-hop limit',
	array( array( 'type' => 'ref', 'field' => 'a', 'limit' => 2 ) ),
	bws_wrapper_ref_steps( array( 'src' => 'refs,a,limit(2)' ) )
);

echo "\n§C7 bws_run_traversal — the per-hop limit\n";

$post = static function ( int $id ): array {
	return array( 'kind' => 'post', 'id' => $id );
};
// Stub reader: 'a' fans one post into three, 'b' fans each into one derived id.
$reader = static function ( array $step, array $source ) {
	$field = $step['field'] ?? '';
	$id    = (int) ( $source['id'] ?? 0 );
	if ( 'a' === $field ) {
		return array( $id * 10 + 1, $id * 10 + 2, $id * 10 + 3 );
	}
	if ( 'b' === $field ) {
		return array( $id * 100 );
	}
	return array();
};

assert_same(
	'no limit → full fan-out',
	array( $post( 11 ), $post( 12 ), $post( 13 ) ),
	bws_run_traversal( array( $post( 1 ) ), array( array( 'type' => 'ref', 'field' => 'a' ) ), $reader )
);
assert_same(
	'limit 2 slices the step OUTPUT',
	array( $post( 11 ), $post( 12 ) ),
	bws_run_traversal( array( $post( 1 ) ), array( array( 'type' => 'ref', 'field' => 'a', 'limit' => 2 ) ), $reader )
);
assert_same(
	'a limit larger than the fan-out is inert',
	array( $post( 11 ), $post( 12 ), $post( 13 ) ),
	bws_run_traversal( array( $post( 1 ) ), array( array( 'type' => 'ref', 'field' => 'a', 'limit' => 9 ) ), $reader )
);
// The falsy-zero class, on the engine side this time.
assert_same(
	'limit 0 = UNLIMITED, never "bound at zero"',
	array( $post( 11 ), $post( 12 ), $post( 13 ) ),
	bws_run_traversal( array( $post( 1 ) ), array( array( 'type' => 'ref', 'field' => 'a', 'limit' => 0 ) ), $reader )
);
assert_same(
	'a non-numeric limit is ignored (not read as 0)',
	array( $post( 11 ), $post( 12 ), $post( 13 ) ),
	bws_run_traversal( array( $post( 1 ) ), array( array( 'type' => 'ref', 'field' => 'a', 'limit' => 'lots' ) ), $reader )
);
// An INTERMEDIATE limit is the reason the quantity exists: it bounds how much work the
// rest of the chain multiplies, not just the visible row count.
assert_same(
	'an INTERMEDIATE limit bounds downstream fan-out',
	array( $post( 1100 ) ),
	bws_run_traversal(
		array( $post( 1 ) ),
		array(
			array( 'type' => 'ref', 'field' => 'a', 'limit' => 1 ),
			array( 'type' => 'ref', 'field' => 'b' ),
		),
		$reader
	)
);
assert_same(
	'without the intermediate limit the same chain yields three',
	array( $post( 1100 ), $post( 1200 ), $post( 1300 ) ),
	bws_run_traversal(
		array( $post( 1 ) ),
		array(
			array( 'type' => 'ref', 'field' => 'a' ),
			array( 'type' => 'ref', 'field' => 'b' ),
		),
		$reader
	)
);
// The limit applies to the step's WHOLE output, not per input source — that is the
// quantity the wire names ("at most N of these").
assert_same(
	'the limit is on the step, not per input source',
	array( $post( 11 ), $post( 12 ) ),
	bws_run_traversal(
		array( $post( 1 ), $post( 2 ) ),
		array( array( 'type' => 'ref', 'field' => 'a', 'limit' => 2 ) ),
		$reader
	)
);

// ─────────────────────────────────────────────────────────────────────────────
// §C8 — bws_fold_src_resolution(): the arm-dispatch axis (FW-63)
//
// THE HIGHEST SEAM AVAILABLE. Arm dispatch reduces to this one question, so the
// ~19 render-path call sites need no individual tests — they need this to be right
// and the fold integration matrix to confirm the wiring. Every case below states
// the FLAT spelling and the CHAIN spelling of the same source and asserts they
// answer identically; that identity is the whole deliverable.
// ─────────────────────────────────────────────────────────────────────────────
echo "\n§C8 — resolved-source kind + fan, from the wire alone\n";

$res = static function ( array $options ): string {
	$r = bws_fold_src_resolution( $options );
	return $r['kind'] . '/' . ( $r['fans'] ? 'fans' : 'one' ) . '/' . $r['root'];
};

// Root-only chains. The case the previous framing could not express: a chain with
// NO steps still has a kind.
assert_same( 'bare tag → base kind, no fan', 'base/one/', $res( array() ) );
assert_same( 'src:current → base kind (root token kept)', 'base/one/current', $res( array( 'src' => 'current' ) ) );
assert_same( 'src:site → site kind, no fan', 'site/one/site', $res( array( 'src' => 'site' ) ) );
assert_same(
	'a registry source root reads as base — the factory decides its kind',
	'base/one/related_post',
	$res( array( 'src' => 'related_post' ) )
);

// Flat vs chain, per arm.
assert_same( 'FLAT  src:ref + ref → post, fans', 'post/fans/', $res( array( 'src' => 'ref', 'ref' => 'office' ) ) );
assert_same( 'CHAIN src:refs,office → post, fans', 'post/fans/', $res( array( 'src' => 'refs,office' ) ) );
assert_same( 'FLAT  srcTermIn → term, fans', 'term/fans/', $res( array( 'srcTermIn' => 'department' ) ) );
assert_same( 'CHAIN src:terms,department → term, fans', 'term/fans/', $res( array( 'src' => 'terms,department' ) ) );
assert_same(
	'FLAT  src:ref + ref + srcTermIn → term (the LAST step decides)',
	'term/fans/',
	$res( array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'department' ) )
);
assert_same(
	'CHAIN refs;terms → term, same answer',
	'term/fans/',
	$res( array( 'src' => 'refs,office;terms,department' ) )
);
assert_same( 'CHAIN src:entries,rows → meta_row', 'meta_row/fans/', $res( array( 'src' => 'entries,team' ) ) );
assert_same(
	'CHAIN site;entries,rows → meta_row, ROOTED at site',
	'meta_row/fans/site',
	$res( array( 'src' => 'site;entries,team' ) )
);

// An ARGLESS fanning step is dropped by the COMPILER but is still a step on the
// WIRE. Dispatching off the compiled list would send `src:ref` with no `ref` down
// the ambient arm, when the flat spelling has always sent it down the post arm.
assert_same(
	'src:ref with NO ref field → still post kind (parsed, not compiled)',
	'post/fans/',
	$res( array( 'src' => 'ref' ) )
);
assert_same( 'and the compiler still drops the step', array(), bws_field_values_assemble_steps( array( 'src' => 'ref' ) ) );

// Unknown vocabulary is honestly unknown, never guessed back to the root — the
// engine answers empty for an unknown type, so the chain short-circuits.
assert_same( 'unknown last slug → kind unknown, still fans', '/fans/', $res( array( 'src' => 'refs,a;bogus,b' ) ) );

// A SITE root never takes the legacy term hop: `srcTermIn` is registered
// `show_if src: not:site`, so the pair is hand-edit-only, and every arm has always
// let the site read win. Folding it in would flip a stored tag to empty.
assert_same(
	'src:site + srcTermIn → still the SITE read, hop not folded in',
	'site/one/site',
	$res( array( 'src' => 'site', 'srcTermIn' => 'department' ) )
);
assert_same(
	'and no term step is compiled for it either',
	array(),
	bws_field_values_assemble_steps( array( 'src' => 'site', 'srcTermIn' => 'department' ) )
);

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
