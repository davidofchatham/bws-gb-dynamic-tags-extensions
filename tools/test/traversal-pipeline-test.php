<?php
/**
 * Standalone unit harness for the traversal-pipeline engine
 * (includes/helpers/traversal-pipeline.php).
 *
 * No WordPress required. bws_run_traversal / bws_run_step are a pure fold; the
 * only WP-touching code is bws_pipeline_default_reader, which the tests bypass
 * by injecting a stub $reader (SPEC §V9 — engine pure/deterministic). The pure
 * coercers bws_pipeline_ref_to_posts / bws_pipeline_terms_to_sources are driven
 * directly with shimmed WP_Post/WP_Term.
 *
 * SCOPE (SPEC §V2 shape/silent-empty, §V9 fold semantics, §V6 ref-plural core):
 *   bws_run_traversal()            fold: passthrough, fan-out, short-circuit, order
 *   bws_run_step()                 dispatch: unknown type/kind → [], input-kind gate
 *   bws_pipeline_ref_to_posts()    plural: EVERY id, no first-only collapse (§V6)
 *   bws_pipeline_terms_to_sources()WP_Term[] → term sources
 *   bws_collect_value_list()       FW-49 L3 combining fold: slice/suppress/
 *                                  render/drop/link-gate/join (CONTEXT.md I12)
 *
 * EXCLUDED — the live reader (bws_pipeline_default_reader: get_post_meta/get_field/
 * get_the_terms) and the factory (T2, its own precedence fixtures). Manual sweep
 * = T10.
 *
 * Run:  php tools/test/traversal-pipeline-test.php
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// Minimal WP shims — the engine references WP_Post/WP_Term in the pure coercers
// and bws_extract_post_id; the live reader (get_post_meta etc.) is never called.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
}
if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term { public $term_id; public function __construct( $id ) { $this->term_id = $id; } }
}

// bws_extract_post_id lives in field-helpers.php (load-safe, but pulls the whole
// file + its own WP deps). Reproduce the single dependency inline — the engine
// calls it from bws_pipeline_ref_to_posts. Keep byte-equivalent to the shipped
// helper so the test exercises real extraction behavior.
if ( ! function_exists( 'bws_extract_post_id' ) ) {
	function bws_extract_post_id( $post_data ) {
		if ( $post_data instanceof WP_Post ) { return $post_data->ID; }
		if ( is_object( $post_data ) && isset( $post_data->ID ) ) { return $post_data->ID; }
		if ( is_numeric( $post_data ) ) { return intval( $post_data ); }
		if ( is_array( $post_data ) ) {
			if ( isset( $post_data['ID'] ) ) { return $post_data['ID']; }
			if ( ! empty( $post_data ) ) { return bws_extract_post_id( reset( $post_data ) ); }
		}
		return false;
	}
}

// SOURCE-GATE STUB, defined BEFORE the require so the real file's function_exists
// guard yields to it (plan §S20 corrected — the harness supplies its own predicate;
// the REAL gate names get_post/current_user_can and is integration-tested on the
// testbed, never here). Controllable: a source carrying '__gated' => true is
// dropped, every other source passes, so pre-gate rows are untouched and the gate
// section below drives budget/stepping-stone behaviour through the same default
// path the shipped callers take.
if ( ! function_exists( 'bws_source_gate' ) ) {
	function bws_source_gate( array $source ) { return empty( $source['__gated'] ); }
}

// LINK-WRAP STUB, same treatment and same reason as the gate stub above: defined
// BEFORE the require so the real file yields to it. The shipped wrapper lives in
// link-helpers.php and resolves its URL through get_permalink / get_term_link /
// get_post_meta, so it cannot run WP-free — and it is not what the FW-85 rows are
// about. What the fold owes it is the CALL: one per value, against that value's own
// identity, with the separator left outside. The stub makes the destination legible
// (<kind>/<id> for a permalink; $GLOBALS['stub_link_urls'] keyed '<kind>:<id>' for a
// linkTo:'key' read) and models an unresolvable URL the way the real one does — an
// empty URL returns the output UNWRAPPED, which is how a value with an empty URL
// field prints plain beside its linked siblings.
if ( ! function_exists( 'bws_wrap_with_link' ) ) {
	function bws_wrap_with_link( string $output, string $link_to, string $link_key, bool $new_tab, int $id, string $entity_type ): string {
		if ( '' === $output || 'none' === $link_to || '' === $link_to ) {
			return $output;
		}
		if ( 'permalink' === $link_to ) {
			$url = '/' . $entity_type . '/' . $id;
		} elseif ( 'key' === $link_to && '' !== $link_key ) {
			$url = (string) ( $GLOBALS['stub_link_urls'][ $entity_type . ':' . $id ] ?? '' );
		} else {
			$url = '';
		}
		if ( '' === $url ) {
			return $output;
		}
		$attrs = ' href="' . $url . '"';
		if ( $new_tab ) {
			$attrs .= ' target="_blank" rel="noopener noreferrer"';
		}
		return '<a' . $attrs . '>' . $output . '</a>';
	}
}
$GLOBALS['stub_link_urls'] = array();

require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';

// sanitize_key shim — the assemble-steps helper (in field-helpers.php) uses it.
// Reproduce just that one pure function so we can test step assembly WP-free.
if ( ! function_exists( 'sanitize_key' ) ) {
	// LOWERCASE FIRST, then strip (WP's order) — the reverse deletes every capital.
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
}
// BOTH step assemblers are now REAL, not copies. Since 1.17.0 (5h) they are thin
// adapters over the chain compiler and live together in slot-fold-compile.php, which is
// pure (one sanitize_key, shimmed above) — so the file loads here and the assemble/§V13
// rows below drive the shipped code. They used to be inline copies of two functions
// buried among WP-dependent siblings in field-helpers.php / base-shared.php; the
// compiler's own cases live in tools/test/fold-chain-compile-test.php, and the rows here
// stay as the equivalence guard on the ENGINE side of the same seam.
require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';

// The ambient TERM/USER gates are loaded from base-shared.php, not copied. They
// used to be inline copies "kept byte-equivalent to the shipped source", and when
// FW-63 replaced their three token tests with one chain query the copies went on
// passing against a rule the plugin no longer had — the exact drift the house
// pattern's own caveat warns about. base-shared.php defines functions only, so it
// loads inert behind the shims below.
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
foreach ( array( 'add_action', 'add_filter', 'do_action', 'apply_filters' ) as $wp_fn ) {
	if ( ! function_exists( $wp_fn ) ) {
		eval( "function {$wp_fn}() { return func_num_args() > 1 ? func_get_arg(1) : null; }" );
	}
}
require __DIR__ . '/../../includes/tags/base-shared.php';

// The ambient-analog SEAM (1.19.0, the twins' successor) derives link identity
// through bws_source_link_identity(), which lives in field-helpers.php — loaded
// REAL, not copied (function definitions only; inert behind the same shims).
require __DIR__ . '/../../includes/helpers/field-helpers.php';

// Value-read stubs for the seam rows. The property under test is the GATE and
// the DISPATCH — which kind claims, off which entity, with what derived
// identity — never a core's rendering: the cores are WP-bound and integration-
// tested on the testbed. Sentinel values make the dispatch legible in the
// expectations, and a mis-wired arm (term reader asked for a user, or the
// reverse) surfaces as the wrong sentinel rather than as a silent ''.
function bws_term_title_core( $term_id, $options, $instance ) { return 'TERM_TITLE_' . (int) $term_id; }
function get_the_author_meta( $field, $user_id ) { return 'USER_' . $field . '_' . (int) $user_id; }
function bws_gb_tag_output( $value, $options = array(), $instance = null ) { return $value; }

// §V14 src:ref list-mode collapse — the post-kind id extraction from a fanned-out
// ref source list. Mirrors bws_base_post_ids_from_source's filter (post-kind only,
// order preserved, id>0); tested as a pure list transform so no WP reader is needed
// (the fan-out itself is covered by the V6 rows via injected readers).
function ids_post_kind_only( array $sources ): array {
	$ids = array();
	foreach ( $sources as $src ) {
		if ( is_array( $src ) && 'post' === ( $src['kind'] ?? '' ) ) {
			$id = (int) ( $src['id'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
	}
	return $ids;
}

// ── tiny assert harness ─────────────────────────────────────────────────────
$GLOBALS['pass'] = 0;
$GLOBALS['fail'] = 0;

function eq( $label, $expected, $actual ) {
	if ( $expected === $actual ) {
		$GLOBALS['pass']++;
		return;
	}
	$GLOBALS['fail']++;
	echo "FAIL: $label\n";
	echo '  expected: ' . json_encode( $expected ) . "\n";
	echo '  actual:   ' . json_encode( $actual ) . "\n";
}

// Convenience: a resolved source.
function post_src( $id ) { return array( 'kind' => 'post', 'id' => $id ); }
function term_src( $id ) { return array( 'kind' => 'term', 'id' => $id ); }
function user_src( $id ) { return array( 'kind' => 'user', 'id' => $id ); }

// A stub reader keyed off the step 'field'/'slug' so tests control fan-out
// without any WP call. Returns whatever the fixture maps a (kind,id) to.
function make_reader( $map ) {
	return function ( $step, $source ) use ( $map ) {
		$k = $source['kind'] . ':' . ( $source['id'] ?? '?' );
		return $map[ $k ] ?? '';
	};
}

// ── §V9 — fold semantics ─────────────────────────────────────────────────────

// Empty steps → passthrough, unchanged, order intact.
eq(
	'V9 empty steps passthrough',
	array( post_src( 1 ), post_src( 2 ) ),
	bws_run_traversal( array( post_src( 1 ), post_src( 2 ) ), array() )
);

// Single ref step, fan-out 1 → N (a relationship field with 3 targets).
$reader = make_reader( array( 'post:10' => array( 21, 22, 23 ) ) );
eq(
	'V9/V6 ref fan-out 1->N',
	array( post_src( 21 ), post_src( 22 ), post_src( 23 ) ),
	bws_run_traversal( array( post_src( 10 ) ), array( array( 'type' => 'refs', 'field' => 'rel' ) ), $reader )
);

// Fan-out preserves document order across multiple input sources.
$reader = make_reader( array( 'post:1' => array( 100, 101 ), 'post:2' => array( 200 ) ) );
eq(
	'V9 order preserved across sources',
	array( post_src( 100 ), post_src( 101 ), post_src( 200 ) ),
	bws_run_traversal( array( post_src( 1 ), post_src( 2 ) ), array( array( 'type' => 'refs', 'field' => 'rel' ) ), $reader )
);

// ── §S48 — the source gate (I19: a gated-out source never spends limit budget) ──
// The DEFAULT path runs this harness's stub gate (drops '__gated' sources); the
// explicit-$gate rows drive the injected parameter the same way shipped consumers
// could. The REAL bws_source_gate body is WP-bound and integration-tested only.

// The INITIAL source list is gated, not just hop produce (depth-0 reads covered).
eq(
	'S48 initial list gated (default gate)',
	array( post_src( 1 ) ),
	bws_run_traversal( array( post_src( 1 ), array( 'kind' => 'post', 'id' => 2, '__gated' => true ) ), array() )
);

// Gate runs BEFORE the per-step limit slice: with limit(1) and the first produced
// source gated out, the SECOND source takes the slot — a gated source spent none.
$reader = make_reader( array( 'post:10' => array( 21, 22, 23 ) ) );
$gate21 = function ( array $s ) { return 21 !== (int) ( $s['id'] ?? 0 ); };
eq(
	'S48 gate before limit slice — budget not consumed',
	array( post_src( 22 ) ),
	bws_run_traversal(
		array( post_src( 10 ) ),
		array( array( 'type' => 'refs', 'field' => 'rel', 'limit' => 1 ) ),
		$reader,
		$gate21
	)
);

// STEPPING-STONE CUT: a gated intermediate's subtree is unreachable — its reads
// never happen. Recording reader proves post:21 is never consulted.
$calls  = array();
$rec    = function ( $step, $source ) use ( &$calls ) {
	$k       = $source['kind'] . ':' . ( $source['id'] ?? '?' );
	$calls[] = $k;
	$map     = array( 'post:1' => array( 21, 22 ), 'post:22' => array( 31 ) );
	return $map[ $k ] ?? '';
};
eq(
	'S48 stepping stone cut — gated hop subtree unread',
	array( post_src( 31 ) ),
	bws_run_traversal(
		array( post_src( 1 ) ),
		array( array( 'type' => 'refs', 'field' => 'a' ), array( 'type' => 'refs', 'field' => 'b' ) ),
		$rec,
		$gate21
	)
);
eq( 'S48 gated intermediate never read', false, in_array( 'post:21', $calls, true ) );

// An explicit permissive $gate OVERRIDES the default — the parameter is the seam.
eq(
	'S48 injected gate overrides default',
	array( array( 'kind' => 'post', 'id' => 2, '__gated' => true ) ),
	bws_run_traversal( array( array( 'kind' => 'post', 'id' => 2, '__gated' => true ) ), array(), null, function () { return true; } )
);

// Chained steps: ref → ref.
$reader = make_reader( array( 'post:1' => array( 5 ), 'post:5' => array( 9, 8 ) ) );
eq(
	'V9 chained ref->ref',
	array( post_src( 9 ), post_src( 8 ) ),
	bws_run_traversal(
		array( post_src( 1 ) ),
		array( array( 'type' => 'refs', 'field' => 'a' ), array( 'type' => 'refs', 'field' => 'b' ) ),
		$reader
	)
);

// Short-circuit: first step empties → [], later step never consulted.
$reader = make_reader( array( 'post:1' => '' /* miss */ ) );
$touched = false;
$spy = function ( $step, $source ) use ( &$touched, $reader ) {
	if ( 'b' === ( $step['field'] ?? '' ) ) { $touched = true; }
	return $reader( $step, $source );
};
eq(
	'V9 short-circuit empties chain',
	array(),
	bws_run_traversal(
		array( post_src( 1 ) ),
		array( array( 'type' => 'refs', 'field' => 'a' ), array( 'type' => 'refs', 'field' => 'b' ) ),
		$spy
	)
);
eq( 'V9 short-circuit skips later step', false, $touched );

// ── §V2 — shape + silent-empty ───────────────────────────────────────────────

// Unknown step type → [].
eq( 'V2 unknown step type', array(), bws_run_step( array( 'type' => 'bogus' ), post_src( 1 ) ) );

// Unknown source kind → [].
eq( 'V2 unknown source kind', array(), bws_run_step( array( 'type' => 'refs', 'field' => 'x' ), array( 'kind' => 'galaxy', 'id' => 1 ) ) );

// Malformed source (no kind) → [].
eq( 'V2 malformed source no kind', array(), bws_run_step( array( 'type' => 'refs', 'field' => 'x' ), array( 'id' => 1 ) ) );

// Malformed step (no type) → [].
eq( 'V2 malformed step no type', array(), bws_run_step( array( 'field' => 'x' ), post_src( 1 ) ) );

// A SITE-ROOTED relationship step resolves (1.17.0). An ACF options page holds
// relationship fields like any other field store, and the `rows` arm has always
// accepted site for exactly that reason — so the old refusal here was an asymmetry
// in one allowlist, not a rule. It was unreachable while `src:site` and `src:ref`
// were alternative values of one flat option; a chain makes it authorable, and it
// would have failed silently (empty chain, no warning).
eq(
	'site-rooted ref hop resolves through the options store',
	array( post_src( 91 ), post_src( 92 ) ),
	bws_run_step(
		array( 'type' => 'refs', 'field' => 'featured_partner' ),
		array( 'kind' => 'site' ),
		make_reader( array( 'site:?' => array( 91, 92 ) ) )
	)
);
// A MISS off the site store is still empty, so the chain short-circuits as ever.
eq(
	'site-rooted ref miss is still empty',
	array(),
	bws_run_step( array( 'type' => 'refs', 'field' => 'nope' ), array( 'kind' => 'site' ), make_reader( array() ) )
);

// srcTermIn valid input is post only — term input → [].
eq( 'V2 srcTermIn rejects term input', array(), bws_run_step( array( 'type' => 'terms', 'slug' => 'category' ), term_src( 3 ), make_reader( array() ) ) );

// ── §V6 — ref plural core (no first-only collapse) ───────────────────────────

// The load-bearing plural assertion: a 3-target field yields 3 sources, NOT 1.
eq(
	'V6 ref keeps ALL targets (WP_Post list)',
	array( post_src( 7 ), post_src( 8 ), post_src( 9 ) ),
	bws_pipeline_ref_to_posts( array( new WP_Post( 7 ), new WP_Post( 8 ), new WP_Post( 9 ) ) )
);

// Mixed ACF shapes in a list all extract.
eq(
	'V6 ref mixed id/object/assoc list',
	array( post_src( 4 ), post_src( 5 ), post_src( 6 ) ),
	bws_pipeline_ref_to_posts( array( 4, new WP_Post( 5 ), array( 'ID' => 6 ) ) )
);

// A single assoc row with 'ID' is ONE post, not a list (precedence vs collapse).
eq(
	'V6 single assoc row is one post',
	array( post_src( 42 ) ),
	bws_pipeline_ref_to_posts( array( 'ID' => 42 ) )
);

// Single scalar id → one post.
eq( 'V6 single scalar id', array( post_src( 3 ) ), bws_pipeline_ref_to_posts( 3 ) );

// Empty / null ref → [].
eq( 'V6 empty ref', array(), bws_pipeline_ref_to_posts( '' ) );
eq( 'V6 null ref', array(), bws_pipeline_ref_to_posts( null ) );
eq( 'V6 empty array ref', array(), bws_pipeline_ref_to_posts( array() ) );

// review #2 — a STRING-KEYED assoc WITHOUT 'ID' is ONE field value (an ACF group/
// map/row), NOT a post list. Must NOT fabricate a bogus post from every scalar
// member. bws_extract_post_id applies its own precedence (no ID key → first member).
eq(
	'#2 string-keyed assoc is NOT a post list',
	array( post_src( 123 ) ), // ['post'=>123,'qty'=>2] → single value → extract_post_id → first member 123, NOT posts 123 AND 2
	bws_pipeline_ref_to_posts( array( 'post' => 123, 'qty' => 2 ) )
);
eq(
	'#2 string-keyed assoc of non-ids → []',
	array(), // ['label'=>'x','note'=>'y'] → single value → no numeric first member → dropped
	bws_pipeline_ref_to_posts( array( 'label' => 'x', 'note' => 'y' ) )
);
// A genuine sequential relationship list still fans out (regression guard for the fix).
eq(
	'#2 sequential id list still fans out',
	array( post_src( 11 ), post_src( 22 ) ),
	bws_pipeline_ref_to_posts( array( 11, 22 ) )
);

// ── srcTermIn coercion ───────────────────────────────────────────────────────

eq(
	'srcTermIn WP_Term[] -> term sources',
	array( term_src( 11 ), term_src( 12 ) ),
	bws_pipeline_terms_to_sources( array( new WP_Term( 11 ), new WP_Term( 12 ) ) )
);
eq( 'srcTermIn non-array -> []', array(), bws_pipeline_terms_to_sources( false ) );

// End-to-end srcTermIn step through the fold with a stub reader.
$reader = make_reader( array( 'post:50' => array( new WP_Term( 60 ), new WP_Term( 61 ) ) ) );
eq(
	'srcTermIn step fan-out via fold',
	array( term_src( 60 ), term_src( 61 ) ),
	bws_run_traversal( array( post_src( 50 ) ), array( array( 'type' => 'terms', 'slug' => 'category' ) ), $reader )
);

// #44: compound [ref, srcTermIn] through the fold — ref off a TERM base yields
// related posts, then srcTermIn hops those posts to their terms. Proves the
// chain the assembler now emits actually resolves end-to-end.
$reader = make_reader( array(
	'term:3'   => array( 100, 101 ),                     // ref off term 3 -> posts 100,101
	'post:100' => array( new WP_Term( 200 ) ),           // srcTermIn off post 100 -> term 200
	'post:101' => array( new WP_Term( 201 ) ),           // srcTermIn off post 101 -> term 201
) );
eq(
	'#44 ref+srcTermIn compound via fold (term -> posts -> terms)',
	array( term_src( 200 ), term_src( 201 ) ),
	bws_run_traversal(
		array( term_src( 3 ) ),
		array(
			array( 'type' => 'refs', 'field' => 'related' ),
			array( 'type' => 'terms', 'slug' => 'category' ),
		),
		$reader
	)
);

// ── §V1/§V7 — factory precedence (injected signals, probe truth table) ───────
//
// Drives bws_resolve_base_source with injected $signals so dispatch is pure.
// Branches touching SourceRegistry (explicit registry src, current-post
// fallback) need the live path — covered by the T10 manual sweep, not here.
// These rows lock the ambient/loop/explicit-site precedence that is pure.

// Signal builders.
function sig( $overrides = array() ) {
	return array_merge(
		array(
			'queried_kind' => null,
			'queried_id'   => 0,
			'is_tax'       => false,
			'loop'         => array( 'in_loop' => false, 'item_post_id' => false, 'loop_item' => null ),
		),
		$overrides
	);
}

// V7: bare tag on a term archive → term source (queried_object=term, no loop).
eq(
	'V7 term archive -> term source',
	array( 'kind' => 'term', 'id' => 34 ),
	bws_resolve_base_source( array(), null, sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) ) )
);

// V1: the loop ITEM WINS over ambient term (bare tag inside a query loop on an
// archive reads the ITEM, not the term — the precedence that stops the leak).
eq(
	'V1 loop item wins over ambient term',
	array( 'kind' => 'post', 'id' => 48418 ),
	bws_resolve_base_source(
		array(),
		null,
		array(
			'queried_kind' => 'term',
			'queried_id'   => 34,
			'is_tax'       => true,
			'loop'         => array( 'in_loop' => true, 'item_post_id' => 48418, 'loop_item' => null ),
		)
	)
);

// V7 explicit-wins: src:site beats an ambient term archive.
eq(
	'V7 explicit src:site beats ambient term',
	array( 'kind' => 'site' ),
	bws_resolve_base_source( array( 'src' => 'site' ), null, sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) ) )
);

// A repeater row (in a loop, no post behind it) → meta_row.
eq(
	'flat repeater row -> meta_row',
	array( 'kind' => 'meta_row', 'row' => array( 'name' => 'x' ) ),
	bws_resolve_base_source(
		array(),
		null,
		array(
			'queried_kind' => null,
			'queried_id'   => 0,
			'is_tax'       => false,
			'loop'         => array( 'in_loop' => true, 'item_post_id' => false, 'loop_item' => array( 'name' => 'x' ) ),
		)
	)
);

// ── #123 — THE LOOP'S ITEM IS THE SOURCE, WHATEVER KIND IT IS ────────────────
//
// A co-resident query extension can loop over TERMS or USERS. GB passes no
// $fallback_type to `generateblocks_dynamic_tag_id`, so such an extension cannot
// tell a post fallback from a term one and hands the item's id to a POST fallback;
// term ids and post ids collide on every install (term 1, post 1). The factory now
// takes the item's own kind from bws_get_loop_item_context() instead, which is why
// none of these rows carries a query-type string: recognition is keyed on the item's
// SHAPE, and the factory only maps the answer.
//
// Signals here are INJECTED, so what these rows pin is the mapping, not the
// recognition. What decides an item's kind is bws_classify_loop_item()'s PHPDoc, and
// the shape rules themselves are pinned in `tools/test/loop-item-classify-test.php`
// (stubbed WP lookups, no site). The rendered proof is `tools/test/loop-test-matrix.md`
// §QL on /matrix-loops/.

function loop_sig( $loop ) {
	return array(
		'queried_kind' => null,
		'queried_id'   => 0,
		'is_tax'       => false,
		'loop'         => $loop,
	);
}

eq(
	'#123 term loop item -> term source',
	array( 'kind' => 'term', 'id' => 7 ),
	bws_resolve_base_source( array(), null, loop_sig( array( 'in_loop' => true, 'item_post_id' => false, 'loop_item' => null, 'item_kind' => 'term', 'item_id' => 7 ) ) )
);

eq(
	'#123 user loop item -> user source',
	array( 'kind' => 'user', 'id' => 4 ),
	bws_resolve_base_source( array(), null, loop_sig( array( 'in_loop' => true, 'item_post_id' => false, 'loop_item' => null, 'item_kind' => 'user', 'item_id' => 4 ) ) )
);

// ── #19 / FW-9 — query-context kinds (injected signals, 1.19.0) ──────────────
//
// Five entity-LESS contexts resolve a query_context source carrying sub-kind +
// payload (ADR 0002 variable payload; no id, no fields, L2 skipped). Signals
// injected — recognition (is_404/is_search/…) is live-WP and integration-
// tested via the C-rows; these rows pin the factory's MAPPING and precedence.

foreach ( array(
	array( 'post_type_archive', array( 'post_type' => 'staff', 'label' => 'Staff' ) ),
	array( 'date', array( 'year' => 2026, 'monthnum' => 7, 'day' => 0 ) ),
	array( 'search', array( 's' => 'searchpin' ) ),
	array( '404', array() ),
	array( 'latest_home', array() ),
) as list( $qc_sub, $qc_payload ) ) {
	eq(
		"FW-9 {$qc_sub} -> query_context source",
		array( 'kind' => 'query_context', 'sub' => $qc_sub, 'payload' => $qc_payload ),
		bws_resolve_base_source( array(), null, sig( array( 'query_context' => $qc_sub, 'query_payload' => $qc_payload ) ) )
	);
	// Explicit src still wins on every one of them (author intent is step 1).
	eq(
		"FW-9 explicit src:site beats {$qc_sub}",
		array( 'kind' => 'site' ),
		bws_resolve_base_source( array( 'src' => 'site' ), null, sig( array( 'query_context' => $qc_sub, 'query_payload' => $qc_payload ) ) )
	);
	// A query-loop item still wins over the ambient context on every one of them.
	eq(
		"FW-9 loop item beats {$qc_sub}",
		array( 'kind' => 'post', 'id' => 48418 ),
		bws_resolve_base_source(
			array(),
			null,
			sig( array(
				'query_context' => $qc_sub,
				'query_payload' => $qc_payload,
				'loop'          => array( 'in_loop' => true, 'item_post_id' => 48418, 'loop_item' => null ),
			) )
		)
	);
}

// Absent payload key degrades to an empty array, never null.
eq(
	'FW-9 payload defaults to empty array',
	array( 'kind' => 'query_context', 'sub' => '404', 'payload' => array() ),
	bws_resolve_base_source( array(), null, sig( array( 'query_context' => '404' ) ) )
);

// A term loop item still LOSES to an explicit src, exactly as a post row does —
// author intent is step 1 and the loop is step 2.
eq(
	'#123 explicit src:site beats a term loop item',
	array( 'kind' => 'site' ),
	bws_resolve_base_source( array( 'src' => 'site' ), null, loop_sig( array( 'in_loop' => true, 'item_post_id' => false, 'loop_item' => null, 'item_kind' => 'term', 'item_id' => 7 ) ) )
);

// And a term loop item BEATS an ambient term archive, the same way a post row does:
// the loop is nearer than the page.
eq(
	'#123 term loop item beats the ambient term archive',
	array( 'kind' => 'term', 'id' => 7 ),
	bws_resolve_base_source(
		array(),
		null,
		array(
			'queried_kind' => 'term',
			'queried_id'   => 34,
			'is_tax'       => true,
			'loop'         => array( 'in_loop' => true, 'item_post_id' => false, 'loop_item' => null, 'item_kind' => 'term', 'item_id' => 7 ),
		)
	)
);

// THE REFUSAL, and it is the half that makes recognising two shapes cheap: the term
// and user arms already existed, any FUTURE shape has none, and falling through
// returns a plausible value from an entity the wire never named ([I15]). An
// unreadable item is NOT "no loop" — that distinction is the whole fix.
eq(
	'#123 unreadable loop item REFUSES rather than falling through',
	array( 'kind' => BWS_SOURCE_KIND_UNRESOLVED ),
	bws_resolve_base_source( array(), null, loop_sig( array( 'in_loop' => true, 'item_post_id' => false, 'loop_item' => null, 'item_kind' => 'unknown', 'item_id' => 0 ) ) )
);

// It refuses over an ambient term archive too — a fallthrough that lands on a REAL
// entity is the dangerous one, since nothing looks broken.
eq(
	'#123 an unreadable item refuses over an ambient term archive',
	array( 'kind' => BWS_SOURCE_KIND_UNRESOLVED ),
	bws_resolve_base_source(
		array(),
		null,
		array(
			'queried_kind' => 'term',
			'queried_id'   => 34,
			'is_tax'       => true,
			'loop'         => array( 'in_loop' => true, 'item_post_id' => false, 'loop_item' => null, 'item_kind' => 'unknown', 'item_id' => 0 ),
		)
	)
);

// A loop item that IS a post keeps its old answer, and the published `item_post_id`
// key is what still decides it — the post arm is read before any item_kind branch.
eq(
	'#123 a post item is unchanged, read from item_post_id',
	array( 'kind' => 'post', 'id' => 48418 ),
	bws_resolve_base_source( array(), null, loop_sig( array( 'in_loop' => true, 'item_post_id' => 48418, 'loop_item' => null, 'item_kind' => 'post', 'item_id' => 48418 ) ) )
);

// V1: NO ambient term + no loop → falls through to current-post path. With no
// SourceRegistry loaded in this harness, current-post id resolves 0 → post/0.
// Confirms $post is never consulted for ambient (there is none here) and the
// fallthrough shape is a post source.
eq(
	'V1 no ambient -> current post fallthrough shape',
	array( 'kind' => 'post', 'id' => 0 ),
	bws_resolve_base_source( array(), null, sig() )
);

// DECISION 1 — OUR REGISTRY NOT LOADING IS A FACT ABOUT THE PLUGIN, NOT A FACT ABOUT
// THE WIRE. bws_factory_registry_source() declines three ways and only two of them
// refuse (#75/#76); this is the third, and it must keep falling through to ambient.
// Refusing here would answer a question about the author's tag with a fact about our
// load state, and its blast radius is unbounded — every tag carrying any source token
// on the entire site.
//
// ASSERTED HERE ON PURPOSE: this is the only point in the run where the registry
// genuinely is absent, since the registry section at the foot of the file requires it
// in. The row below pins that precondition, because without it this assertion would go
// quietly vacuous the day someone hoists the require — and a vacuous pass on THIS row
// reads as "the load-fallthrough is safe" while proving nothing.
eq(
	'V1 registry unavailable -> falls through to ambient, never a refusal',
	array( 'kind' => 'post', 'id' => 0 ),
	bws_resolve_base_source( array( 'src' => 'nosuchsource' ), null, sig() )
);
eq(
	'…and the registry really is absent at this point (else the row above is vacuous)',
	false,
	class_exists( '\BWS\DynamicTags\SourceRegistry' )
);

// V1 leak-guard (search/404 shape): queried_kind null + no loop. The probe
// showed $post leaks the main query's first row on search/404 — the factory
// must NOT consult it. Injected signals carry NO queried entity and NO loop,
// so dispatch reaches the current-post path (post/0 here) — never a stale post.
// (No 'search'/'404' kind yet; those contexts fall through, SPEC §C4.)
eq(
	'V1 search/404 no-entity does NOT read stale post',
	array( 'kind' => 'post', 'id' => 0 ),
	bws_resolve_base_source( array(), null, sig( array( 'queried_kind' => null, 'queried_id' => 0 ) ) )
);

// V11: src:ref on a term archive bases on the AMBIENT TERM (ref is a step; the
// term is the ambient resolved source, ref hops its field term→post). This
// FIXES today's leak (GB get_id('post')=get_the_ID()=stale first-loop post on
// an archive). Ambient-term-as-ref-base = V7 applied to ref, not the deferred
// pin-a-specific-primary parity gap.
eq(
	'V11 src:ref on term archive bases on ambient term',
	array( 'kind' => 'term', 'id' => 34 ),
	bws_resolve_base_source(
		array( 'src' => 'ref', 'ref' => 'related' ),
		null,
		sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) )
	)
);

// ── §V17 — degenerate term context → empty, never the leaked post ────────────
//
// Conditional tags claim a taxonomy archive but no WP_Term resolved
// (term_context_unresolved). A bare tag must short-circuit to empty, NOT fall
// through to the current/leaked post.

// V17: bare tag, term_context_unresolved → array() (empty), NOT post/0.
eq(
	'V17 unresolved term context -> empty',
	array(),
	bws_resolve_base_source( array(), null, sig( array( 'term_context_unresolved' => true ) ) )
);

// V17: explicit src:site still wins over the flag (flag check is AFTER explicit).
eq(
	'V17 explicit src:site beats unresolved-term flag',
	array( 'kind' => 'site' ),
	bws_resolve_base_source( array( 'src' => 'site' ), null, sig( array( 'term_context_unresolved' => true ) ) )
);

// V17: a loop item still wins over the flag (loop precedes the flag check).
eq(
	'V17 loop item beats unresolved-term flag',
	array( 'kind' => 'post', 'id' => 555 ),
	bws_resolve_base_source(
		array(),
		null,
		array(
			'queried_kind'            => null,
			'queried_id'              => 0,
			'is_tax'                  => false,
			'term_context_unresolved' => true,
			'loop'                    => array( 'in_loop' => true, 'item_post_id' => 555, 'loop_item' => null ),
		)
	)
);

// V17: a RESOLVED term (normal archive) is unaffected — still returns the term.
eq(
	'V17 resolved term unaffected',
	array( 'kind' => 'term', 'id' => 34 ),
	bws_resolve_base_source( array(), null, sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) ) )
);

// ── T4 seam step assembly (pure options → steps) ─────────────────────────────

// srcTermIn → single term-hop step, terminal (no ref appended).
eq(
	'assemble srcTermIn -> term-hop step',
	array( array( 'type' => 'terms', 'slug' => 'category' ) ),
	bws_field_values_assemble_steps( array( 'srcTermIn' => 'category' ) )
);

// src:ref + ref key → ref step (V6 plural fan-out happens at run time).
eq(
	'assemble src:ref -> ref step',
	array( array( 'type' => 'refs', 'field' => 'related' ) ),
	bws_field_values_assemble_steps( array( 'src' => 'ref', 'ref' => 'related' ) )
);

// #44: src:ref + srcTermIn COMPOUND, emitting [ref, srcTermIn] in that order.
// ref hops source -> related posts, then srcTermIn hops those posts -> terms.
// Order is load-bearing: srcTermIn needs the post kind ref produces.
eq(
	'assemble src:ref + srcTermIn -> [ref, srcTermIn] (compound, #44)',
	array(
		array( 'type' => 'refs', 'field' => 'x' ),
		array( 'type' => 'terms', 'slug' => 'post_tag' ),
	),
	bws_field_values_assemble_steps( array( 'srcTermIn' => 'post_tag', 'src' => 'ref', 'ref' => 'x' ) )
);

// Bare / current / site → NO steps (base source read directly).
eq( 'assemble bare -> no steps', array(), bws_field_values_assemble_steps( array() ) );
eq( 'assemble src:current -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'current' ) ) );
eq( 'assemble src:site -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'site' ) ) );

// src:ref WITHOUT a ref key → an ARGUMENT-LESS step, never no step (#74). The engine
// answers '' for a field-less refs read, so the chain short-circuits and the tag renders
// nothing. Dropping the step left the chain with no steps at all, which resolves the
// AMBIENT entity — the tag read the post you were standing on.
eq( 'assemble src:ref no key -> argument-less step', array( array( 'type' => 'refs' ) ), bws_field_values_assemble_steps( array( 'src' => 'ref' ) ) );

// ── §V4 — wrapper collapse (bws_first_post_id_from_sources) ──────────────────
//
// The back-compat contract of bws_resolve_post_by_source(): first POST id | false.
// Non-post base (term ambient, meta_row, site) → false, never leak a term/row id
// as a post id. Wrapper callers stay collapse-to-first (plural = SEAM only, §V6).

// First source is a post → its id.
eq( 'V4 first post id', 123, bws_first_post_id_from_sources( array( post_src( 123 ), post_src( 456 ) ) ) );

// Ref-plural collapsed to FIRST for wrapper callers (§V4 vs §V6 seam plural).
eq( 'V4 plural collapses to first', 21, bws_first_post_id_from_sources( array( post_src( 21 ), post_src( 22 ), post_src( 23 ) ) ) );

// Term ambient base (archive) → false, NOT the term id (post-only callers).
eq( 'V4 term base -> false', false, bws_first_post_id_from_sources( array( term_src( 34 ) ) ) );

// meta_row base (src:current on a repeater row) → false (matches old wrapper).
eq( 'V4 meta_row base -> false', false, bws_first_post_id_from_sources( array( array( 'kind' => 'meta_row', 'row' => array( 'x' => 1 ) ) ) ) );

// site base → false.
eq( 'V4 site base -> false', false, bws_first_post_id_from_sources( array( array( 'kind' => 'site' ) ) ) );

// Empty source list (short-circuited traversal / unresolvable) → false.
eq( 'V4 empty sources -> false', false, bws_first_post_id_from_sources( array() ) );

// A post source with id 0 → false (not a usable post id).
eq( 'V4 post id 0 -> false', false, bws_first_post_id_from_sources( array( post_src( 0 ) ) ) );

// ── §V13 — wrapper ref-only step set (B2 fix) ────────────────────────────────
//
// The wrapper NEVER assembles a srcTermIn step (that would hop post->term and
// collapse to false, empty-ing the caller's own srcTermIn branch — B2). Only a
// src:ref hop is a wrapper step. Contrast the seam's assemble-steps, which DOES
// emit srcTermIn (tested above under T4).

// src:ref + key → ref step (same as seam here).
eq( 'V13 wrapper src:ref -> ref step', array( array( 'type' => 'refs', 'field' => 'related' ) ), bws_wrapper_ref_steps( array( 'src' => 'ref', 'ref' => 'related' ) ) );

// srcTermIn set → NO step (wrapper excludes it; caller owns the term hop). The
// load-bearing B2 assertion: seam would emit a srcTermIn step here, wrapper must not.
eq( 'V13 wrapper srcTermIn -> NO step', array(), bws_wrapper_ref_steps( array( 'srcTermIn' => 'category' ) ) );

// srcTermIn + stray src:ref → still no step from the wrapper? src:ref present →
// wrapper emits ITS ref step; srcTermIn is simply ignored by the wrapper (caller
// owns it). Confirms the wrapper only ever cares about ref.
eq( 'V13 wrapper ref beside srcTermIn -> ref step only', array( array( 'type' => 'refs', 'field' => 'x' ) ), bws_wrapper_ref_steps( array( 'src' => 'ref', 'ref' => 'x', 'srcTermIn' => 'category' ) ) );

// Bare / current / site → no wrapper step.
eq( 'V13 wrapper bare -> no step', array(), bws_wrapper_ref_steps( array() ) );
eq( 'V13 wrapper src:current -> no step', array(), bws_wrapper_ref_steps( array( 'src' => 'current' ) ) );
eq( 'V13 wrapper src:site -> no step', array(), bws_wrapper_ref_steps( array( 'src' => 'site' ) ) );
eq( 'V13 wrapper src:ref no key -> argument-less step', array( array( 'type' => 'refs' ) ), bws_wrapper_ref_steps( array( 'src' => 'ref' ) ) );

// ── §V7 — the ambient-analog seam (bws_base_ambient_analog) ───────────────────
//
// One seam replaced the per-kind ambient arm blocks AND the ambient-id twins
// (1.19.0). It claims ONLY for a bare base tag whose chain is root-only
// (render_time) and whose resolved base is an ambient entity kind; every other
// spelling returns NULL and the caller's own post/term/list path runs. These
// rows carry the twins' whole truth table forward — a 0 became null, an id
// became the claim triple with the value from the stubbed reader path and the
// identity DERIVED through bws_source_link_identity().

function seam_title( $base, $options ) { return bws_base_ambient_analog( 'title', $base, $options, null ); }

// Bare tag + term base → the term claim (analog path).
eq(
	'V7 term base bare -> term claim',
	array( 'value' => 'TERM_TITLE_34', 'link_id' => 34, 'link_type' => 'term' ),
	seam_title( term_src( 34 ), array() )
);
eq(
	'V7 term base src:current -> term claim',
	array( 'value' => 'TERM_TITLE_34', 'link_id' => 34, 'link_type' => 'term' ),
	seam_title( term_src( 34 ), array( 'src' => 'current' ) )
);

// Post base → null (post path).
eq( 'V7 post base -> null', null, seam_title( post_src( 10 ), array() ) );

// V11: src:ref on a term base → null (post path runs the term->post ref hop, NOT
// the term's own analog). The load-bearing V11 guard.
eq( 'V11 src:ref on term base -> null (ref hop owns it)', null, seam_title( term_src( 34 ), array( 'src' => 'ref', 'ref' => 'related' ) ) );

// Explicit srcTermIn → null (post->term branch owns it; incoherent from a term base).
eq( 'V7 srcTermIn set -> null', null, seam_title( term_src( 34 ), array( 'srcTermIn' => 'category' ) ) );

// src:site → null (own gate).
eq( 'V7 src:site -> null', null, seam_title( term_src( 34 ), array( 'src' => 'site' ) ) );

// meta_row base → null (only entity kinds claim).
eq( 'V7 meta_row base -> null', null, seam_title( array( 'kind' => 'meta_row', 'row' => array() ), array() ) );

// Entity kind with id 0 → null identity → null (the caller's post path runs,
// exactly as the twins' 0 sent it there).
eq( 'V7 term base id 0 -> null', null, seam_title( term_src( 0 ), array() ) );

// FW-63: the gate asks ONE question — is the chain root-only and rooted at the
// ambient entity — so the CHAIN spelling of each source above answers identically.
// These are the rows that would have caught the arm bug: before the refactor the
// gate saw no `srcTermIn` and no `src:ref` token, fired, and read the ambient
// term's analog on a tag whose source states a hop.
eq( 'FW-63 chain terms hop on term base -> null', null, seam_title( term_src( 34 ), array( 'src' => 'terms,category' ) ) );
eq( 'FW-63 chain refs hop on term base -> null', null, seam_title( term_src( 34 ), array( 'src' => 'refs,related' ) ) );
eq( 'FW-63 chain rows hop on term base -> null', null, seam_title( term_src( 34 ), array( 'src' => 'rows,rows' ) ) );
// A REGISTRY-source root is root-only, so it still reaches the kind switch —
// exactly as the old "src is not site/ref" test let it through.
eq(
	'FW-63 registry root still reaches the kind switch',
	array( 'value' => 'TERM_TITLE_34', 'link_id' => 34, 'link_type' => 'term' ),
	seam_title( term_src( 34 ), array( 'src' => 'related_post' ) )
);
// And on a user base, so the arms cannot drift apart.
eq( 'FW-63 chain hop on user base -> null', null, seam_title( user_src( 7 ), array( 'src' => 'refs,related' ) ) );

// ── #19 author kind — the seam's user arm ─────────────────────────────────────
//
// Symmetric with the term arm: claims ONLY for a bare base tag on an author
// archive (user base, root-only chain). Otherwise null.

eq(
	'author user base bare -> user claim',
	array( 'value' => 'USER_display_name_7', 'link_id' => 7, 'link_type' => 'user' ),
	seam_title( user_src( 7 ), array() )
);
eq(
	'author user base src:current -> user claim',
	array( 'value' => 'USER_display_name_7', 'link_id' => 7, 'link_type' => 'user' ),
	seam_title( user_src( 7 ), array( 'src' => 'current' ) )
);

// Same guards as the term arm: src:ref / src:site / srcTermIn keep their own
// meaning → null (post path / site gate / post->term branch owns the render).
eq( 'author src:ref on user base -> null', null, seam_title( user_src( 7 ), array( 'src' => 'ref', 'ref' => 'related' ) ) );
eq( 'author src:site -> null', null, seam_title( user_src( 7 ), array( 'src' => 'site' ) ) );
eq( 'author srcTermIn set -> null', null, seam_title( user_src( 7 ), array( 'srcTermIn' => 'category' ) ) );

// ── the user carve-out (build-ticket 03 deviation, measured 2026-08-29) ───────
//
// The user arm claims exactly the tags bws_base_user_analog_read() answers
// (title/content/text). {{image}} keeps its post route: the image cores'
// stated-fallback emit still renders a configured Media Library fallback off
// the falsy-id read there, and a seam claim's '' would silently drop it.
// {{permalink}} is byte-equal either way and stays out on the same
// claim-what-you-answer rule. The arm widens when FW-47 gives the reader those
// analogs — at which point these two rows are the ones to flip.
eq( 'user x image -> null (post route keeps the stated-fallback emit)', null, bws_base_ambient_analog( 'image', user_src( 7 ), array(), null ) );
eq( 'user x permalink -> null (claim-what-you-answer)', null, bws_base_ambient_analog( 'permalink', user_src( 7 ), array(), null ) );

// ── #19 / FW-9 — the seam's query-context arm ─────────────────────────────────
//
// Mostly the OPPOSITE claiming rule from the user arm, on purpose: query_context
// is claimed for every tag but `image`, because a fallthrough would hand the
// entity-less base to the post route and a falsy-id core read — the leak class
// the kind exists to stop, one layer down. The reader answers '' for a tag with
// no analog (empty, not wrong). Rows use the 404 sub-kind: without
// GENERATE_VERSION its title is core's msgid through the __() shim, so no
// further WP surface is touched (the other sub-kinds call live primitives and
// are pinned by the C-rows on the testbed).
//
// `image` is the SAME carve-out as the user arm above, for the same reason: a
// query-context archive (post-type/date/search/404/front-page) reaches
// bws_custom_image_core() through the post route today, and a configured Media
// Library fallback still renders there off the falsy-id read — a seam claim's
// '' would silently drop it. Measured live 2026-09-02: a standalone {{image}}
// on a post-type-archive template rendered nothing despite a valid `fallback`,
// because this arm's unconditional claim reached the seam's bare '' before the
// post route's stated-fallback emit ever ran.
$qc_404 = array( 'kind' => 'query_context', 'sub' => '404', 'payload' => array() );
eq(
	'FW-9 query context claims title (404 -> core msgid, no-wrap identity)',
	array( 'value' => 'Page not found', 'link_id' => 0, 'link_type' => 'post' ),
	bws_base_ambient_analog( 'title', $qc_404, array(), null )
);
eq(
	'FW-9 query context claims an analog-less tag with an EMPTY value (permalink stays claimed)',
	array( 'value' => '', 'link_id' => 0, 'link_type' => 'post' ),
	bws_base_ambient_analog( 'permalink', $qc_404, array(), null )
);
eq(
	'FW-9 query context x image -> null (post route keeps the stated-fallback emit)',
	null,
	bws_base_ambient_analog( 'image', $qc_404, array(), null )
);
eq(
	'FW-9 text use:title reads the title analog (the try_ composition)',
	array( 'value' => 'Page not found', 'link_id' => 0, 'link_type' => 'post' ),
	bws_base_ambient_analog( 'text', $qc_404, array( 'use' => 'title' ), null )
);
eq(
	'FW-9 text key-mode has no entity to read',
	array( 'value' => '', 'link_id' => 0, 'link_type' => 'post' ),
	bws_base_ambient_analog( 'text', $qc_404, array(), null )
);
// The FW-63 gate binds this arm too: a chain that states a hop is not ambient.
eq( 'FW-9 chain hop on a query-context base -> null', null, bws_base_ambient_analog( 'title', $qc_404, array( 'src' => 'refs,related' ), null ) );

// ── census guard — every ambient KIND this seam switches on carries an `image`
// carve-out, or a stated reason it does not need one ───────────────────────────
//
// {{image}}'s cores (bws_featured_image_core/bws_custom_image_core) own a
// self-contained stated-fallback emit on a falsy post id — the SAME shape
// bws_post_content_core and the datetime cores use. Twice now (`user` kind,
// pre-existing; `query_context` kind, this session) a kind's unconditional
// claim on `image` reached this seam's own bare '' before that emit ever got a
// turn, silently dropping a configured Media Library fallback. Both are pinned
// above as ordinary assertions, which is exactly what let the second instance
// ship unnoticed for a release cycle — nothing forced a REVIEWER to re-check
// image's carve-out when a kind was touched. This section is the fix for that:
// it reads the seam's OWN switch cases off disk (not a hand-kept copy, which
// would drift the same way a third per-tag copy of the rule would) and fails
// if a kind arrives — or one of the two already listed above is removed —
// without a matching row here.
//
// SCOPE, stated once rather than assumed: this census covers exactly the shape
// where the FIX lives IN THE SEAM (image's `return null` carve-out). The
// content/datetime_single/datetime_range instances found and fixed the same
// session have the identical consequence but a DIFFERENT mechanism — their fix
// is in the CALLBACK's own tail (falling through to the post route / widening
// the outer fallback gate), which this seam's return value cannot distinguish
// from the pre-fix behaviour, so it is not testable by calling this function
// alone. Their regression protection is the page-snapshot baseline instead
// (`tools/test/context-test-matrix.md` C-C2/C-DT1/C-DT2, `tools/test/snapshots/
// ctx-*.html`) — a WP-dependent instrument, because the fix itself is.
// \R (not \n): base-shared.php is CRLF, and a literal \n here silently matches
// nothing rather than failing loud — measured, not assumed, per the file's own
// rule on what a runtime claim may rest on.
$ambient_source = file_get_contents( __DIR__ . '/../../includes/tags/base-shared.php' );
preg_match( '/^function bws_base_ambient_analog\(.*?\{\R(.*?)\R\}\R/ms', $ambient_source, $fn_match );
preg_match_all( "/case '([a-z_]+)':/", $fn_match[1] ?? '', $case_match );
$ambient_kinds = $case_match[1] ?? array();

eq(
	'census: bws_base_ambient_analog() switches on exactly these kinds',
	array( 'term', 'user', 'query_context' ),
	$ambient_kinds
);

// The `image` carve-out, per kind. `term` is deliberately skipped rather than
// asserted either way: {{image}}'s term case never reaches this seam's
// real-analog branch at all (base-tags.php routes term-kind image reads
// through its own bws_term_custom_image_core arm, which owns the identical
// no-key -> fallback shape directly — CONTEXT.md I9's term paragraph states
// it), so there is no seam-level claim here to carve out or pin.
foreach ( array_diff( $ambient_kinds, array( 'term' ) ) as $kind ) {
	$base = 'query_context' === $kind
		? array( 'kind' => 'query_context', 'sub' => 'date', 'payload' => array() )
		: array( 'kind' => $kind, 'id' => 7 );
	eq(
		"census: '{$kind}' kind x image -> null (carve-out present)",
		null,
		bws_base_ambient_analog( 'image', $base, array(), null )
	);
}

// ── §V5 — modifier ref hop off a base source (T7 pipeline assembly) ───────────
//
// The modifier callback (term_/view_) resolves a BASE source via base_source_key
// then hops src:ref through the generic ref step — replacing the retired
// TermRelatedPost and its external twin, the old traversal classes. Shape assertion: a term
// base hops term->post[] and collapses to first (single-valued modifier link).

// term base + ref step → post[]; first post id (mirrors term_ modifier src:ref).
$reader = make_reader( array( 'term:34' => array( 91, 92 ) ) );
$stepped = bws_run_traversal( array( term_src( 34 ) ), array( array( 'type' => 'refs', 'field' => 'related' ) ), $reader );
eq( 'V5 term modifier ref hop -> post[]', array( post_src( 91 ), post_src( 92 ) ), $stepped );
eq( 'V5 term modifier ref collapses to first', 91, bws_first_post_id_from_sources( $stepped ) );

// post base + ref step → post[] (an external modifier src:ref: its post -> rel).
$reader = make_reader( array( 'post:70' => 88 ) );
$stepped = bws_run_traversal( array( post_src( 70 ) ), array( array( 'type' => 'refs', 'field' => 'rel' ) ), $reader );
eq( 'V5 post modifier ref hop -> first post', 88, bws_first_post_id_from_sources( $stepped ) );

// No ref target → empty hop → false (modifier renders empty, not a leak).
$reader = make_reader( array() );
$stepped = bws_run_traversal( array( term_src( 34 ) ), array( array( 'type' => 'refs', 'field' => 'related' ) ), $reader );
eq( 'V5 modifier ref miss -> false', false, bws_first_post_id_from_sources( $stepped ) );

// ── §V14 — base text/title src:ref LIST mode (B3 fix) ────────────────────────
//
// text/title offer limit/sep for src:ref, so the src:ref post branch must read the
// FULL fanned-out ref post set (not collapse-to-first). The fan-out is the same V6
// engine path; the callback keeps ALL post-kind ids, in order, for slice+join.

// A 2-target ref field (the B3 repro: 2 posts in benefit_vendor) yields BOTH ids,
// in document order — NOT just the first.
$reader = make_reader( array( 'post:5' => array( 61, 62 ) ) );
$stepped = bws_run_traversal( array( post_src( 5 ) ), array( array( 'type' => 'refs', 'field' => 'benefit_vendor' ) ), $reader );
eq( 'V14 src:ref keeps BOTH targets (B3 repro)', array( 61, 62 ), ids_post_kind_only( $stepped ) );

// Order preserved across a 3-target field.
$reader = make_reader( array( 'post:1' => array( 30, 31, 32 ) ) );
$stepped = bws_run_traversal( array( post_src( 1 ) ), array( array( 'type' => 'refs', 'field' => 'r' ) ), $reader );
eq( 'V14 src:ref order preserved', array( 30, 31, 32 ), ids_post_kind_only( $stepped ) );

// Post-kind filter: non-post kinds are dropped (defensive — ref yields posts, but
// the extractor must never surface a term/site id as a post id).
eq(
	'V14 post-kind filter drops non-post',
	array( 7, 9 ),
	ids_post_kind_only( array( post_src( 7 ), term_src( 8 ), post_src( 9 ), array( 'kind' => 'site' ) ) )
);

// id 0 dropped.
eq( 'V14 drops id 0', array( 4 ), ids_post_kind_only( array( post_src( 0 ), post_src( 4 ) ) ) );

// Empty ref → empty list (slot renders nothing, not a stray first).
eq( 'V14 empty ref -> empty list', array(), ids_post_kind_only( array() ) );

// ── FW-49 — bws_collect_value_list (shared L3 combining fold) ────────────────
//
// Pure fold (field-helpers.php): slice→suppress→render→drop→link-gate→join.
// REAL, not a copy — the file is already required above for
// bws_source_link_identity, and the fold's own helpers (bws_clamp_limit,
// bws_limit_default) are pure and come with it. The rows below therefore drive
// the shipped function.
//
// There WAS an inline copy here, and the require made it unreachable the moment
// it landed: its function_exists guard never fired again, so the copy could and
// did drift — its own `max( 1, (int) $limit )` in place of the shared clamp, a
// slice with no unlimited arm — while its comment claimed byte-equivalence and
// the section went on passing against the shipped rule. Deleted rather than
// repaired (FW-85 ticket 01); the same lesson already cost this file its two
// assemble-step copies. The limit rows below are what a re-introduced copy
// fails on by name.

// Render stub: items are ['v' => value, 'l' => link|null]; '' value = skip source.
$cv_render = function ( $item, array $item_opts ) {
	return array( 'value' => $item['v'], 'link' => $item['l'] ?? null );
};
$cv = function ( ...$items ) use ( $cv_render ) {
	return function ( array $options ) use ( $items, $cv_render ) {
		return bws_collect_value_list( $items, $cv_render, $options );
	};
};

// Two values join with default sep. No Link To set → no markup at all: the fold's
// default, and the {{join}} slot's case (a slot carries no link options, so the wrap
// step finds nothing to do — by construction, not by a guard).
$r = $cv( array( 'v' => 'A', 'l' => array( 'kind' => 'post', 'id' => 1 ) ),
          array( 'v' => 'B', 'l' => array( 'kind' => 'post', 'id' => 2 ) ) )( array( 'limit' => 5 ) );
eq( 'CV join default sep', 'A, B', $r['value'] );
eq( 'CV no linkTo -> no markup', false, str_contains( $r['value'], '<a' ) );
eq( 'CV per-value links survive multi', array( 'kind' => 'post', 'id' => 2 ), $r['values'][1]['link'] );
eq( 'CV count', 2, $r['count'] );
eq( 'CV return has no top-level link key', false, array_key_exists( 'link', $r ) );

// Empty renders drop; the survivor is the whole value (GH #51 shape).
$r = $cv( array( 'v' => '' ), array( 'v' => 'B', 'l' => array( 'kind' => 'post', 'id' => 4 ) ) )( array( 'limit' => 5 ) );
eq( 'CV empty dropped from value', 'B', $r['value'] );
eq( 'CV empty dropped from count', 1, $r['count'] );
eq( 'CV survivor keeps its identity', array( 'kind' => 'post', 'id' => 4 ), $r['values'][0]['link'] );

// Value with NO link identity (meta_row-shaped) is normal: collects, carries null,
// never a sentinel (I12).
$r = $cv( array( 'v' => 'raw' ) )( array() );
eq( 'CV linkless value collects', 'raw', $r['value'] );
eq( 'CV linkless entry -> link null not sentinel', null, $r['values'][0]['link'] );

// limit slices BEFORE render (default 1); sep honored.
$r = $cv( array( 'v' => 'A' ), array( 'v' => 'B' ), array( 'v' => 'C' ) )( array( 'limit' => 2, 'sep' => ' | ' ) );
eq( 'CV limit slice + custom sep', 'A | B', $r['value'] );
$r = $cv( array( 'v' => 'A' ), array( 'v' => 'B' ) )( array() );
eq( 'CV default limit 1', 'A', $r['value'] );

// What a WRITTEN limit means is bws_clamp_limit's (its own cases are
// limit-clamp-test.php); what the fold owes it is routing every slice through
// it. `0` and `-1` are unlimited, a fractional value truncates, non-numeric
// falls to the default — the four rows an inline `max( 1, (int) $limit )` gets
// wrong on the first two.
$cv3 = $cv( array( 'v' => 'A' ), array( 'v' => 'B' ), array( 'v' => 'C' ) );
eq( 'CV limit 0 = unlimited', 'A, B, C', $cv3( array( 'limit' => 0 ) )['value'] );
eq( 'CV limit -1 = unlimited', 'A, B, C', $cv3( array( 'limit' => -1 ) )['value'] );
eq( 'CV limit 2.7 truncates', 'A, B', $cv3( array( 'limit' => '2.7' ) )['value'] );
eq( 'CV limit non-numeric = default', 'A', $cv3( array( 'limit' => 'abc' ) )['value'] );

// The DEFAULT the clamp gets is bws_limit_default's, read off the `src`
// SPELLING — chain wire unlimited, flat wire 1. A stated limit still wins.
eq( 'CV chain-wire default unlimited', 'A, B, C', $cv3( array( 'src' => 'refs,office' ) )['value'] );
eq( 'CV chain-wire stated limit wins', 'A, B', $cv3( array( 'src' => 'refs,office', 'limit' => 2 ) )['value'] );

// Fallback suppression: $render must NOT see 'fallback' (GH #51 — fires once in
// the caller on all-empty, never per item).
$seen_fallback = 'unset-sentinel';
bws_collect_value_list(
	array( 'x' ),
	function ( $item, array $item_opts ) use ( &$seen_fallback ) {
		$seen_fallback = array_key_exists( 'fallback', $item_opts );
		return 'v';
	},
	array( 'fallback' => 'NOPE', 'limit' => 1 )
);
eq( 'CV fallback suppressed from item opts', false, $seen_fallback );

// All-empty → empty value, count 0 (caller's fallback territory).
$r = $cv( array( 'v' => '' ), array( 'v' => '' ) )( array( 'limit' => 5, 'fallback' => 'NOPE' ) );
eq( 'CV all-empty value', '', $r['value'] );
eq( 'CV all-empty count', 0, $r['count'] );

// Plain string return accepted as linkless value.
$r = bws_collect_value_list( array( 'a' ), function ( $i, $o ) { return 'plain'; }, array() );
eq( 'CV string return = linkless value', 'plain', $r['value'] );
eq( 'CV string return link null', null, $r['values'][0]['link'] );

// Malformed link (non-array) coerces to null, not a crash.
$r = bws_collect_value_list( array( 'a' ), function ( $i, $o ) { return array( 'value' => 'x', 'link' => 5 ); }, array() );
eq( 'CV non-array link -> null', null, $r['values'][0]['link'] );

// ── FW-85 — per-item link wrap (what replaced the single-result count gate) ───
//
// Link To now means what an author setting it already believes: every value the tag
// prints is its own link to its own entity. The wrap happens INSIDE the fold, between
// per-value capture and the join, so the separator joins already-wrapped strings and
// every list arm — term, post, repeater row, both datetime branches — inherits it
// with no change of its own. bws_wrap_with_link is stubbed at the top of this file.

$link3 = $cv(
	array( 'v' => 'Alpha', 'l' => array( 'kind' => 'term', 'id' => 11 ) ),
	array( 'v' => 'Beta',  'l' => array( 'kind' => 'term', 'id' => 22 ) ),
	array( 'v' => 'Gamma', 'l' => array( 'kind' => 'term', 'id' => 33 ) )
);
$r = $link3( array( 'limit' => 5, 'linkTo' => 'permalink' ) );
eq(
	'FW85 three values -> three anchors, each its own href',
	'<a href="/term/11">Alpha</a>, <a href="/term/22">Beta</a>, <a href="/term/33">Gamma</a>',
	$r['value']
);
// The separator sits OUTSIDE the anchors: no sep character inside an href, and none
// between an anchor's opening tag and its text.
eq( 'FW85 separator outside the anchors', 0, preg_match( '/href="[^"]*,|<a[^>]*>[^<]*,/', $r['value'] ) );
eq( 'FW85 per-value entries stay RAW', array( 'Alpha', 'Beta', 'Gamma' ), array_column( $r['values'], 'value' ) );

// A value that addresses nothing prints plain BESIDE its linked siblings — the list
// does not lose its links over one member with no identity.
$r = $cv(
	array( 'v' => 'Alpha', 'l' => array( 'kind' => 'post', 'id' => 1 ) ),
	array( 'v' => 'Row' ),
	array( 'v' => 'Gamma', 'l' => array( 'kind' => 'post', 'id' => 3 ) )
)( array( 'limit' => 5, 'linkTo' => 'permalink' ) );
eq(
	'FW85 null identity plain beside linked siblings',
	'<a href="/post/1">Alpha</a>, Row, <a href="/post/3">Gamma</a>',
	$r['value']
);

// A repeater-row list — every value linkless — renders plain text, unchanged.
eq(
	'FW85 all-linkless list stays plain',
	'r1, r2',
	$cv( array( 'v' => 'r1' ), array( 'v' => 'r2' ) )( array( 'limit' => 5, 'linkTo' => 'permalink' ) )['value']
);

// ONE value renders exactly what the count gate produced: the caller wrapped the whole
// string, which for a single value IS the item.
eq(
	'FW85 single value, permalink, byte-identical to the gate',
	'<a href="/post/7">Solo</a>',
	$cv( array( 'v' => 'Solo', 'l' => array( 'kind' => 'post', 'id' => 7 ) ) )( array( 'linkTo' => 'permalink' ) )['value']
);

// URL Meta/Option Field is read from EACH value's own entity, not from the first.
// Distinct URLs per entity, so a wrong-target link shows up as the wrong href rather
// than being inferred; the entity with no stored URL prints plain.
$GLOBALS['stub_link_urls'] = array(
	'post:1' => 'https://one.example',
	'post:3' => 'https://three.example',
);
$r = $cv(
	array( 'v' => 'Alpha', 'l' => array( 'kind' => 'post', 'id' => 1 ) ),
	array( 'v' => 'Beta',  'l' => array( 'kind' => 'post', 'id' => 2 ) ),
	array( 'v' => 'Gamma', 'l' => array( 'kind' => 'post', 'id' => 3 ) )
)( array( 'limit' => 5, 'linkTo' => 'key', 'linkKey' => 'profile_url' ) );
eq(
	'FW85 key mode: each entity own URL, the empty one plain',
	'<a href="https://one.example">Alpha</a>, Beta, <a href="https://three.example">Gamma</a>',
	$r['value']
);
eq(
	'FW85 single value, key mode, byte-identical to the gate',
	'<a href="https://one.example">Solo</a>',
	$cv( array( 'v' => 'Solo', 'l' => array( 'kind' => 'post', 'id' => 1 ) ) )( array( 'linkTo' => 'key', 'linkKey' => 'profile_url' ) )['value']
);
$GLOBALS['stub_link_urls'] = array();

// newTab applies to EVERY link in the list, not to whichever value won a gate.
$r = $link3( array( 'limit' => 2, 'linkTo' => 'permalink', 'newTab' => true ) );
eq( 'FW85 newTab on every anchor', 2, substr_count( $r['value'], 'target="_blank"' ) );

// linkTo:'none' and an absent linkTo are the same no-markup case (the canonical value
// is stripped at registration, so absence is what ships).
eq( 'FW85 linkTo none -> no markup', 'Alpha, Beta', $link3( array( 'limit' => 2, 'linkTo' => 'none' ) )['value'] );

// ── FW-49 — bws_source_link_identity (resolved source → link identity) ───────
//
// Pure mapper (field-helpers.php): {kind,id} for post|term|user (id>0), site
// sentinel id 1 (matches existing site link-wrap call sites), null otherwise
// (I12: no sentinel for "no link identity"). House pattern: inline copy.

if ( ! function_exists( 'bws_source_link_identity' ) ) {
	function bws_source_link_identity( array $source ): ?array {
		$kind = $source['kind'] ?? '';

		switch ( $kind ) {
			case 'post':
			case 'term':
			case 'user':
				$id = (int) ( $source['id'] ?? 0 );
				return $id > 0 ? array( 'kind' => $kind, 'id' => $id ) : null;

			case 'site':
				return array( 'kind' => 'site', 'id' => 1 );
		}

		return null;
	}
}

eq( 'LI post', array( 'kind' => 'post', 'id' => 7 ), bws_source_link_identity( post_src( 7 ) ) );
eq( 'LI term', array( 'kind' => 'term', 'id' => 3 ), bws_source_link_identity( term_src( 3 ) ) );
eq( 'LI user', array( 'kind' => 'user', 'id' => 2 ), bws_source_link_identity( user_src( 2 ) ) );
eq( 'LI site sentinel 1', array( 'kind' => 'site', 'id' => 1 ), bws_source_link_identity( array( 'kind' => 'site' ) ) );
eq( 'LI post id 0 -> null', null, bws_source_link_identity( post_src( 0 ) ) );
eq( 'LI meta_row -> null', null, bws_source_link_identity( array( 'kind' => 'meta_row', 'row' => array() ) ) );
eq( 'LI unknown kind -> null', null, bws_source_link_identity( array( 'kind' => 'date' ) ) );
eq( 'LI empty source -> null', null, bws_source_link_identity( array() ) );

// ── 1.17.0 — `rows` step: repeater → meta_row[] ({{table}} feedstock) ─────────
//
// Structural twin of srcTermIn. bws_pipeline_rows_to_sources is the pure coercer;
// bws_run_step case 'rows' gates the input kind then coerces. The live reader
// (get_field) is bypassed via a stub — the reader arm's get_field/get_*_meta path
// is manual-swept, same as ref/srcTermIn.

// meta_row convenience + a reader that returns a fixture repeater for the `rows`
// step and a sub-field value for a following ref step off the produced meta_row.
//
// TWO helpers, because a row is two things here: row_src() is an INPUT (a row handed
// to a step, provenance-free — the ambient shape), row_prov() is what the coercer
// PRODUCES. Key order matters: eq() is ===, which compares array key order.
function row_src( $row ) { return array( 'kind' => 'meta_row', 'row' => $row ); }
function row_prov( $row, $parent_kind, $parent_id, $repeater, $index ) {
	return array(
		'kind'        => 'meta_row',
		'row'         => $row,
		'parent_kind' => $parent_kind,
		'parent_id'   => $parent_id,
		'repeater'    => $repeater,
		'index'       => $index,
	);
}

// --- coercer (bws_pipeline_rows_to_sources) ---------------------------------
eq(
	'rows coercer: array-of-rows -> meta_row[]',
	array( row_prov( array( 'a' => 1 ), 'post', 4, 'team', 0 ), row_prov( array( 'a' => 2 ), 'post', 4, 'team', 1 ) ),
	bws_pipeline_rows_to_sources( array( array( 'a' => 1 ), array( 'a' => 2 ) ), post_src( 4 ), 'team' )
);
eq( 'rows coercer: non-array -> []', array(), bws_pipeline_rows_to_sources( 'nope' ) );
eq( 'rows coercer: empty array -> []', array(), bws_pipeline_rows_to_sources( array() ) );
eq( 'rows coercer: null -> []', array(), bws_pipeline_rows_to_sources( null ) );
// The blank row's index is 2, not 1: `index` names the position in the STORE, so a
// skipped entry still consumes one. Verified by MUTATION 2026-09-16 — moving the
// coercer's increment inside its is_array() guard fails THIS row and only this row.
eq(
	'rows coercer: skips non-array rows, keeps blank row',
	array( row_prov( array( 'a' => 1 ), 'post', 4, 'team', 0 ), row_prov( array(), 'post', 4, 'team', 2 ) ),
	bws_pipeline_rows_to_sources( array( array( 'a' => 1 ), 'scalar', array() ), post_src( 4 ), 'team' )
);
eq(
	'rows coercer: order preserved',
	array(
		row_prov( array( 'n' => 'x' ), 'post', 4, 'team', 0 ),
		row_prov( array( 'n' => 'y' ), 'post', 4, 'team', 1 ),
		row_prov( array( 'n' => 'z' ), 'post', 4, 'team', 2 ),
	),
	bws_pipeline_rows_to_sources( array( array( 'n' => 'x' ), array( 'n' => 'y' ), array( 'n' => 'z' ) ), post_src( 4 ), 'team' )
);

// --- PROVENANCE (FW-74 ticket 02) -------------------------------------------
//
// Four keys per row: parent kind, parent id, repeater name, store index. NOTHING
// consumes them yet — FW-3's field-object read is what will — so these rows are the
// only thing holding the shape, which is why they pin each key rather than the set.
//
// The parent is passed in, never derived: this coercer performs NO read and NO entity
// lookup (acceptance criterion 4). It cannot — it has no reader and no WP symbol in
// reach, which is what makes that criterion structural here rather than measured.
$prov = bws_pipeline_rows_to_sources( array( array( 'n' => 'x' ), array( 'n' => 'y' ) ), post_src( 12 ), 'team_members' );
eq( 'prov: parent_kind off a post parent', 'post', $prov[0]['parent_kind'] );
eq( 'prov: parent_id off a post parent', 12, $prov[0]['parent_id'] );
eq( 'prov: repeater name is the step field', 'team_members', $prov[0]['repeater'] );
eq( 'prov: index is zero-based', 0, $prov[0]['index'] );
eq( 'prov: the second row indexes differently', 1, $prov[1]['index'] );

// Every parent kind the `rows` step accepts records ITS OWN kind. site and a parent
// ROW carry no id, so parent_id is 0 and the KIND is what tells them apart.
$prov_parent = function ( $parent ) {
	$row = bws_pipeline_rows_to_sources( array( array() ), $parent, 'r' )[0];
	return array( $row['parent_kind'], $row['parent_id'] );
};
eq( 'prov: term parent', array( 'term', 34 ), $prov_parent( term_src( 34 ) ) );
eq( 'prov: user parent', array( 'user', 7 ), $prov_parent( user_src( 7 ) ) );
eq( 'prov: site parent -> kind site, id 0', array( 'site', 0 ), $prov_parent( array( 'kind' => 'site' ) ) );

// A NESTED repeater records the parent ROW, not the outer entity — the row it stepped
// off is a meta_row, and that is the kind that lands. Driven through bws_run_step so
// the recorded parent is the one the engine actually hands over, not one the test picked.
$nested = bws_run_step(
	array( 'type' => 'rows', 'field' => 'shifts' ),
	row_prov( array( 'shifts' => array( array( 'day' => 'Mon' ) ) ), 'post', 12, 'team_members', 3 ),
	function ( $step, $source ) { return $source['row'][ $step['field'] ] ?? array(); }
);
eq( 'prov: nested repeater parent is the ROW', 'meta_row', $nested[0]['parent_kind'] );
eq( 'prov: nested repeater parent has no id', 0, $nested[0]['parent_id'] );
eq( 'prov: nested repeater names the INNER repeater', 'shifts', $nested[0]['repeater'] );
eq( 'prov: nested row index is its own', 0, $nested[0]['index'] );

// Provenance-free calls stay non-fatal: an absent parent is empty/0, not a warning.
eq(
	'prov: no parent passed -> empty kind, id 0, empty repeater',
	array( '', 0, '' ),
	array_values( array_intersect_key( bws_pipeline_rows_to_sources( array( array() ) )[0], array( 'parent_kind' => 1, 'parent_id' => 1, 'repeater' => 1 ) ) )
);

// --- step input-kind gate (bws_run_step case 'rows') ------------------------
// A stub reader that returns a 2-row repeater regardless of source (gate is what
// we test here, not the read).
$rows_reader = function ( $step, $source ) {
	return array( array( 'c' => 'p' ), array( 'c' => 'q' ) );
};
foreach ( array( 'post' => post_src( 5 ), 'term' => term_src( 5 ), 'user' => user_src( 5 ), 'meta_row' => row_src( array( 'r' => array() ) ), 'site' => array( 'kind' => 'site' ) ) as $kname => $src ) {
	// The produced rows also carry the parent's kind/id — the step is where provenance
	// is stamped, so the gate rows double as the per-parent-kind stamp check.
	$pid = ( 'meta_row' === $kname || 'site' === $kname ) ? 0 : 5;
	eq(
		"rows step accepts {$kname} input",
		array( row_prov( array( 'c' => 'p' ), $kname, $pid, 'rep', 0 ), row_prov( array( 'c' => 'q' ), $kname, $pid, 'rep', 1 ) ),
		bws_run_step( array( 'type' => 'rows', 'field' => 'rep' ), $src, $rows_reader )
	);
}
eq(
	'rows step rejects unknown kind -> []',
	array(),
	bws_run_step( array( 'type' => 'rows', 'field' => 'rep' ), array( 'kind' => 'date' ), $rows_reader )
);

// --- fold: rows then bare column read (meta_row reader arm) ------------------
// rows fans a post to 3 meta_rows; the meta_row source then reads a scalar column.
$rows_then = function ( $step, $source ) {
	if ( 'rows' === $step['type'] ) {
		return array(
			array( 'name' => 'Ann', 'role' => 'Lead' ),
			array( 'name' => 'Bo',  'role' => 'Dev' ),
			array( 'name' => 'Cy',  'role' => '' ),
		);
	}
	// ref off a meta_row -> the sub-field's post id list (column-as-ref case).
	if ( 'refs' === $step['type'] && 'meta_row' === $source['kind'] ) {
		$v = $source['row'][ $step['field'] ] ?? '';
		return '' === $v ? '' : array( $v );
	}
	return '';
};
$rows_out = bws_run_traversal( array( post_src( 9 ) ), array( array( 'type' => 'rows', 'field' => 'team' ) ), $rows_then );
eq(
	'rows fold: post -> 3 meta_rows',
	array(
		row_prov( array( 'name' => 'Ann', 'role' => 'Lead' ), 'post', 9, 'team', 0 ),
		row_prov( array( 'name' => 'Bo',  'role' => 'Dev' ), 'post', 9, 'team', 1 ),
		row_prov( array( 'name' => 'Cy',  'role' => '' ), 'post', 9, 'team', 2 ),
	),
	$rows_out
);
// Bare column read off each produced meta_row (the {{table}} cell read) — the
// default reader's meta_row arm returns $row[field].
eq( 'rows cell: meta_row scalar column via reader', 'Ann', bws_pipeline_default_reader( array( 'type' => 'refs', 'field' => 'name' ), $rows_out[0] ) );
eq( 'rows cell: meta_row scalar column', 'Ann', $rows_out[0]['row']['name'] );
eq( 'rows cell: blank column empty', '', $rows_out[2]['row']['role'] );

// --- column-as-ref mini-traversal off a produced meta_row -------------------
// A repeater row holds a relationship sub-field 'lead' → post; a ref step off the
// meta_row hops it to a post (limit-1 collapse done tag-side; here verify fan).
$mr = row_src( array( 'lead' => 77 ) );
eq(
	'rows column ref: meta_row -> post',
	array( post_src( 77 ) ),
	bws_run_step( array( 'type' => 'refs', 'field' => 'lead' ), $mr, $rows_then )
);
$mr_blank = row_src( array( 'lead' => '' ) );
eq(
	'rows column ref: blank sub-field -> []',
	array(),
	bws_run_step( array( 'type' => 'refs', 'field' => 'lead' ), $mr_blank, $rows_then )
);

// --- short-circuit: empty repeater ends the fold ----------------------------
$empty_rows = function ( $step, $source ) { return array(); };
eq(
	'rows fold: empty repeater short-circuits',
	array(),
	bws_run_traversal( array( post_src( 1 ) ), array( array( 'type' => 'rows', 'field' => 'team' ) ), $empty_rows )
);

// ── Registry delegation — OFFERING IS NOT RESOLVING (#83) ────────────────────
//
// The factory delegates any src token that is not the ambient/relationship/site spelling
// to the registry and resolves through the source's own id. #83 added an opt-in that
// governs the DROPDOWN; these rows pin that it governs nothing else.
//
// The load-bearing case is the SECOND one. A reader meeting a boolean called "selectable
// root" is invited to gate resolution on it, and doing so would blank every stored tag
// naming a source an integrator later stopped offering — on wire that is hand-editable by
// decision (ADR 0004) and that a migration writes the moment it runs. Verified by
// MUTATION: gate bws_factory_registry_source() on is_selectable_root() and this section
// fails.
require_once __DIR__ . '/lib-source-registry.php';
// The factory reads its token through the chain compiler, so a rooted CHAIN reaches the
// same delegation a bare token does. Without these the guard degrades to the raw option
// read and the chain rows would silently assert the legacy path.
require_once __DIR__ . '/../../includes/helpers/slot-fold.php';
require_once __DIR__ . '/../../includes/helpers/slot-fold-compile.php';

\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Offered_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Unoffered_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Term_Root_Source() );

eq(
	'registry: an OFFERED root resolves through the factory delegation',
	array( 'kind' => 'post', 'id' => 4242 ),
	bws_resolve_base_source( array( 'src' => 'testroot' ), null, sig() )
);
eq(
	'registry: a source that never opted in resolves IDENTICALLY (offering ≠ resolving)',
	array( 'kind' => 'post', 'id' => 777 ),
	bws_resolve_base_source( array( 'src' => 'quietsource' ), null, sig() )
);
eq(
	'registry: a term-context root yields a TERM resolved source',
	array( 'kind' => 'term', 'id' => 99 ),
	bws_resolve_base_source( array( 'src' => 'testtermroot' ), null, sig() )
);

// A FILTER-DECLARED root resolves through the same delegation as a class-route one —
// which is the whole reason the filter registers a source rather than adding an enum row:
// a row added at enum-build time would exist for the editor and not for the renderer, and
// the token would fall through to the ambient entity. Registered here directly (the
// adaptation itself is covered in slot-options-build-test.php, where the filter fires);
// what this asserts is that the ADAPTER resolves, including its term-context arm.
\BWS\DynamicTags\SourceRegistry::register_source( new \BWS\DynamicTags\Sources\CallbackRoot(
	'filterroot',
	'Filter Root',
	'post',
	static function ( $options, $instance ) { return 5150; }
) );
\BWS\DynamicTags\SourceRegistry::register_source( new \BWS\DynamicTags\Sources\CallbackRoot(
	'filtertermroot',
	'Filter Term Root',
	'term',
	static function ( $options, $instance ) { return 31; }
) );
eq(
	'registry: a FILTER-declared root resolves through the same factory delegation',
	array( 'kind' => 'post', 'id' => 5150 ),
	bws_resolve_base_source( array( 'src' => 'filterroot' ), null, sig() )
);
eq(
	'…and its term-context arm yields a term',
	array( 'kind' => 'term', 'id' => 31 ),
	bws_resolve_base_source( array( 'src' => 'filtertermroot' ), null, sig() )
);
// A term entity is addressed as `term_<id>` in a field read, so the adapter carries the
// same ACF prefix rule TaxonomyTerm states for itself. A post-context root passes through.
eq(
	'…and formats a term id for ACF the way a term source does',
	array( 'term_31', 5150 ),
	array(
		\BWS\DynamicTags\SourceRegistry::get_source( 'filtertermroot' )->format_id_for_acf( 31 ),
		\BWS\DynamicTags\SourceRegistry::get_source( 'filterroot' )->format_id_for_acf( 5150 ),
	)
);

// The term_ toggle reaches NEITHER offering nor resolution. Until 1.20.0 it reached
// offering (a term-context root was hidden from the dropdown with the family switched
// off); that gate is gone — `slot-options-build-test.php` pins the offering half. This
// pins the half that was never gated: what a tag already names goes on resolving.
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = false;
eq(
	'registry: a term root resolves with the term_ family switched off',
	array( 'kind' => 'term', 'id' => 99 ),
	bws_resolve_base_source( array( 'src' => 'testtermroot' ), null, sig() )
);
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = true;

// A root is the chain's FIRST segment, so a rooted chain reaches the same delegation and
// its steps stay the engine's. This is what "registered roots declare no parse-time kind"
// buys: the factory answers at render, and the compiler's parse-time map is untouched.
eq(
	'registry: a rooted CHAIN delegates on its root token',
	array( 'kind' => 'post', 'id' => 4242 ),
	bws_resolve_base_source( array( 'src' => 'testroot;refs,office' ), null, sig() )
);
eq(
	'...and the rest of that chain compiles to steps, not to source tokens',
	array( array( 'type' => 'refs', 'field' => 'office' ) ),
	bws_field_values_assemble_steps( array( 'src' => 'testroot;refs,office' ) )
);

// ── THE ROOT-ARGUMENT SEAM (D3/D8, FW-39) ─────────────────────────────────────
//
// D3: a PINNED root is a REAL chain root — hops run off it exactly as off any other, and
// step ADMISSION is BWS_TRAVERSAL_STEP_INPUT_KINDS' answer and nothing else. Live
// resolution against WordPress (get_term, the tax round-trip) rides testbed matrix rows
// per this ticket's Testing Decisions; what a pure harness owns is that the PIN reaches
// the factory and that the engine admits/refuses steps by KIND, unaffected by whether
// that kind came from a pin or from ambient context.
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Pinned_Term_Source() );
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Owner_Resolves_Root_Source() );

eq(
	'D3: a pinned root resolves through resolve_root_argument(), not resolve_id()',
	array( 'kind' => 'term', 'id' => 68 ),
	bws_resolve_base_source( array( 'src' => 'pinnedterm,34' ), null, sig() )
);
eq(
	'D3: a `refs` step is admitted off a pinned root (term kind accepts it)',
	array( post_src( 5 ) ),
	bws_run_traversal(
		array( array( 'kind' => 'term', 'id' => 68 ) ),
		array( array( 'type' => 'refs', 'field' => 'lead' ) ),
		function ( $step, $source ) { return array( 5 ); }
	)
);
eq(
	'D3: a `terms` step is REFUSED off a pinned root — no term→term edge',
	array(),
	bws_run_traversal(
		array( array( 'kind' => 'term', 'id' => 68 ) ),
		array( array( 'type' => 'terms', 'slug' => 'category' ) ),
		function ( $step, $source ) { return array(); }
	)
);

// The THIRD step type, and the other side of the refusal (ticket 04). `rows` accepts every
// entity kind, so it runs off either pin; `terms` accepts a POST input, so the SAME step
// the term pin refuses is admitted off the post pin. The pair is what makes the refusal a
// KIND rule rather than a rule about pinned roots — a "pinned roots are terminal" shortcut
// would pass the row above and fail both of these.
eq(
	'D3: a `rows` step is admitted off a pinned TERM root (rows accepts every entity kind)',
	array( row_prov( array( 'name' => 'Alice' ), 'term', 68, 'team_members', 0 ) ),
	bws_run_traversal(
		array( array( 'kind' => 'term', 'id' => 68 ) ),
		array( array( 'type' => 'rows', 'field' => 'team_members' ) ),
		function ( $step, $source ) { return array( array( 'name' => 'Alice' ) ); }
	)
);
eq(
	'D3: a `terms` step IS admitted off a pinned POST root — the refusal above is the KIND, not the pin',
	array( array( 'kind' => 'term', 'id' => 7 ) ),
	bws_run_traversal(
		array( array( 'kind' => 'post', 'id' => 1692 ) ),
		array( array( 'type' => 'terms', 'slug' => 'department' ) ),
		function ( $step, $source ) { return array( new WP_Term( 7 ) ); }
	)
);
// The D3 headline shape end to end through the engine: term → post → term, two hops off a
// pin, each admitted on the kind the previous one produced. Pure here (the reader is
// injected); the live values ride fold-test-matrix.md §F22.
eq(
	'D3: `term,<id>;refs,<rel>;terms,<tax>` runs both hops off the pin',
	array( array( 'kind' => 'term', 'id' => 9 ) ),
	bws_run_traversal(
		array( array( 'kind' => 'term', 'id' => 68 ) ),
		array(
			array( 'type' => 'refs', 'field' => 'dept_lead' ),
			array( 'type' => 'terms', 'slug' => 'portal_visibility' ),
		),
		function ( $step, $source ) {
			return 'refs' === ( $step['type'] ?? '' ) ? array( 5 ) : array( new WP_Term( 9 ) );
		}
	)
);

// D8: an ARGLESS declaring root REFUSES at the factory seam — it never falls back to
// resolve_id()'s ambient read, which is [I15] applied at the root layer. Verified by
// MUTATION: an accidental `?? $source->resolve_id(...)` on the argless branch would pass
// every OTHER row in this file (no ambient signal names 'pinnedterm') and only this row
// would catch it.
eq(
	'D8: an argless declaring root refuses — it does not degrade to resolve_id(), EVEN WHEN resolve_id() would have answered something (the fixture always returns 555)',
	array( 'kind' => BWS_SOURCE_KIND_UNRESOLVED ),
	bws_resolve_base_source( array( 'src' => 'pinnedterm' ), null, sig() )
);
// The QL1.2 discovery, pinned directly: an explicit, argless `src:term` used to reach
// resolve_id() and correctly answer whatever the AMBIENT/LOOP context was — this row
// proves the refusal fires even with a live ambient TERM signal present, not merely when
// there is nothing there to find. See fold-test-matrix.md §F20's own note and
// loop-test-matrix.md §QL1 (the fixture row this measurement changed).
eq(
	'D8: the refusal holds even with an ambient TERM signal present — the QL1.2 regression, pinned',
	array( 'kind' => BWS_SOURCE_KIND_UNRESOLVED ),
	bws_resolve_base_source(
		array( 'src' => 'pinnedterm' ),
		null,
		sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) )
	)
);
// A pin naming NOTHING resolvable is equally terminal — resolve_root_argument() returning
// false does not fall back to resolve_id() either.
eq(
	'D8: a pin resolve_root_argument() refuses on refuses too (non-numeric argument)',
	array( 'kind' => BWS_SOURCE_KIND_UNRESOLVED ),
	bws_resolve_base_source( array( 'src' => 'pinnedterm,abc' ), null, sig() )
);
// The OWNER-RESOLVES policy is the one case an argless token is NOT refused — it runs
// resolve_id() by the owner's own rule, never the ambient fallthrough (that fixture
// resolves a fixed post id regardless of ambient signals, proving nothing here leaked in).
eq(
	'D8: OWNER_RESOLVES is the one argless policy that reaches resolve_id()',
	array( 'kind' => 'post', 'id' => 11 ),
	bws_resolve_base_source( array( 'src' => 'ownerroot' ), null, sig() )
);

// ── A PRESENT-BUT-UNUSABLE SOURCE REFUSES (#75 / #76) ────────────────────────
//
// An ABSENT source legitimately means the ambient entity — that is what a bare tag
// resolves and what an author spells by leaving the source unset. A source that is
// PRESENT BUT UNUSABLE is not absence, and answering it with the ambient entity invents
// a read the wire never asked for ([I15]): the tag renders a real, plausible value taken
// from the entry the visitor is already looking at, which is strictly worse than an empty
// one because an empty one gets reported.
//
// Both shapes refuse UNCONDITIONALLY. There is no per-source opt-out and no
// source-contract predicate — one was designed and dismantled, because `current` is
// normalised to absence before the factory is consulted and so the predicate had zero
// holders. See bws_factory_registry_source()'s own docblock.
//
// VERIFY BY MUTATION: restore either `return null` and this section fails. The rows are
// split by shape rather than merged, so the mutation names which refusal broke.
\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Test_Absent_Source() );

$refusal = array( 'kind' => BWS_SOURCE_KIND_UNRESOLVED );

eq(
	'registry: an UNREGISTERED token refuses — it does not become the ambient entity',
	$refusal,
	bws_resolve_base_source( array( 'src' => 'nosuchsource' ), null, sig() )
);
eq(
	'registry: a registered source that RESOLVES NOTHING here refuses too',
	$refusal,
	bws_resolve_base_source( array( 'src' => 'absentroot' ), null, sig() )
);
// The measured population: this shape on a page that HAS an ambient entity to leak. The
// two rows above run on the empty ambient signal, where a fallthrough and a refusal look
// alike in the id but not in the kind; this one is where the old behaviour handed back a
// real entity, so it is the row that would have shown the defect to a reader.
eq(
	'…and refuses on a term archive rather than reading the term the visitor is on',
	$refusal,
	bws_resolve_base_source(
		array( 'src' => 'absentroot' ),
		null,
		sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) )
	)
);
// A refusal must not be reachable by ABSENCE, or the fix would blank every bare tag.
eq(
	'…while an ABSENT source still means the ambient entity, untouched',
	array( 'kind' => 'term', 'id' => 34 ),
	bws_resolve_base_source( array(), null, sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) ) )
);

// ── The refusal REACHES its consumers (construction refusal, asserted) ───────
//
// Five consumers already refuse an unrecognised kind by construction, which is why the
// composite and email/phone read paths need no case for it. Construction refusal is
// exactly what rotted to produce this whole defect class, though — every leak fixed here
// was one that stopped holding when something downstream grew a catch-all — and the
// forthcoming context kinds will add cases to these same switches. So each row states a
// behaviour of the CONSUMER, not the absence of a switch case.
eq(
	'consumer 1/5: the engine\'s input-kind gate produces nothing from a refusal',
	array(),
	bws_run_traversal( array( $refusal ), array( array( 'type' => 'refs', 'field' => 'x' ) ), $reader )
);
eq(
	'consumer 2/5: the first-post-id collapse yields false, not an id',
	false,
	bws_first_post_id_from_sources( array( $refusal ) )
);
eq(
	'consumer 3/5: the wrapper collapse hands the singular arms no id at all',
	false,
	bws_base_post_id_from_source( $refusal, array() )
);
eq(
	'consumer 4/5: the kind-filtered id reads return nothing, both kinds',
	array( array(), array() ),
	array(
		bws_base_source_ids_of_kind( $refusal, array(), 'post' ),
		bws_base_source_ids_of_kind( $refusal, array(), 'term' ),
	)
);
eq(
	'consumer 5/5: the ambient-analog seam declines a refusal',
	null,
	bws_base_ambient_analog( 'title', $refusal, array(), null )
);

// ── THE SOURCES/IDS SPLIT (FW-74) ────────────────────────────────────────────
//
// bws_base_source_ids_of_kind() is a MAP over bws_base_sources_of_kind() since 1.21.0,
// and the pair below is what says the map is lossy in exactly one direction. A chainless
// base is the whole run here — no steps, so the traversal is the gate plus a passthrough,
// which is all that is needed to state the relationship.
//
// A REPEATER ROW IS WHY THE SPLIT EXISTS: it carries its values and its provenance and
// never an id, so the ids selector drops it entirely while the sources selector hands it
// back whole. An arm reading ids can therefore not reach a row at all, which is the hole
// the text arm's `meta_row` branch fills.
$row_src = array(
	'kind'        => 'meta_row',
	'row'         => array( 'name' => 'Alice Adams' ),
	'parent_kind' => 'post',
	'parent_id'   => 7,
	'repeater'    => 'team_members',
	'index'       => 0,
);
eq(
	'FW-74: the sources selector returns the row WHOLE, provenance included',
	array( $row_src ),
	bws_base_sources_of_kind( $row_src, array(), 'meta_row' )
);
eq(
	'FW-74: …while the ids selector drops it, having no id to keep',
	array(),
	bws_base_source_ids_of_kind( $row_src, array(), 'meta_row' )
);
// And on a kind that HAS ids the two agree, which is the half that must not have moved:
// every entity arm still calls the ids selector and must be byte-identical to before.
eq(
	'FW-74: on an entity kind the ids are the sources\' ids, same order',
	array( array( post_src( 11 ) ), array( 11 ) ),
	array(
		bws_base_sources_of_kind( post_src( 11 ), array(), 'post' ),
		bws_base_source_ids_of_kind( post_src( 11 ), array(), 'post' ),
	)
);
eq(
	'FW-74: …and a kind the chain did not produce is empty on both',
	array( array(), array() ),
	array(
		bws_base_sources_of_kind( post_src( 11 ), array(), 'term' ),
		bws_base_source_ids_of_kind( post_src( 11 ), array(), 'term' ),
	)
);

// ── THE SIXTH CONSUMER IS NOT A CONSTRUCTION REFUSAL, AND ASSUMING IT WAS IS THE
//    MISTAKE THIS ROW EXISTS TO STOP ───────────────────────────────────────────
//
// "The singular cores' falsy-id guard refuses by construction" reads true and is not:
// bws_read_field() does not stop at a falsy id, it falls back to a query-loop item it can
// be served from and then to the queried TERM (bws_read_field()'s own docblock owns which
// shapes those are; a TERM or USER item is not one of them). So consumer 3 handing the arm
// `false` is only half the story — a core called with it still reads an ambient entity,
// which is the very defect, arriving one layer lower.
//
// That is why the refusal is caught ABOVE the core, by bws_base_read_refused(), and why
// absence and refusal have to part company there: the loop-item read beneath that guard is
// load-bearing for the repeater-row path, where an absent source legitimately
// DOES mean the row. The core cannot tell the two apart, so it must not be asked to.
eq(
	'the arms refuse a factory refusal BEFORE any core sees it',
	true,
	bws_base_read_refused( array( 'kind' => 'render_time', 'fans' => false ), $refusal )
);
eq(
	'…and refuse an unknown chain step the same way, for the same reason',
	true,
	bws_base_read_refused( array( 'kind' => '', 'fans' => true ), array( 'kind' => 'post', 'id' => 7 ) )
);
eq(
	'…while a resolvable source on a resolvable chain is NOT refused',
	false,
	bws_base_read_refused( array( 'kind' => 'render_time', 'fans' => false ), array( 'kind' => 'post', 'id' => 7 ) )
);
// The path the guard must not delete: an absent source in a repeater row
// resolves a meta_row, and that is a legitimate read rather than a refusal.
eq(
	'…and a flat repeater row is a READ, not a refusal',
	false,
	bws_base_read_refused( array( 'kind' => 'render_time', 'fans' => false ), array( 'kind' => 'meta_row', 'row' => array( 'name' => 'x' ) ) )
);

// ── THE UNSERVED-KIND REFUSAL (FW-74 ticket 04b) ────────────────────────────
//
// A wire kind no family arm serves must refuse ABOVE the core, for the reason the
// block above states: a falsy id does not stop the read. Before this refusal existed,
// {{title}}/{{permalink}}/{{image}}/{{datetime_*}} on a `rows` chain reached the post
// tail, resolved nothing, and printed the SURROUNDING PAGE.
//
// THE FOUR BELOW PIN THE WIRE-VS-BASE AXIS MECHANICALLY, which is why this comment may
// name it: the last one fails if the predicate is switched to read $base['kind'], and
// the page snapshots are the only other thing that catches that (fold-test-matrix.md
// §F9c does NOT — every row there is {{text}}, which SERVES meta_row).
eq(
	'FW-74: an unserved wire kind refuses (a rows chain on a family with no row arm)',
	true,
	bws_base_read_refused( array( 'kind' => 'meta_row', 'fans' => true ), array( 'kind' => 'post', 'id' => 7 ) )
);
eq(
	'FW-74: …and the SAME wire kind is read once the call site names it in $serves',
	false,
	bws_base_read_refused( array( 'kind' => 'meta_row', 'fans' => true ), array( 'kind' => 'post', 'id' => 7 ), array( 'meta_row' ) )
);
eq(
	'FW-74: `term` is always served, so no call site has to name it',
	false,
	bws_base_read_refused( array( 'kind' => 'term', 'fans' => true ), array( 'kind' => 'post', 'id' => 7 ) )
);
eq(
	'FW-74: a BASE of the unserved kind is NOT refused — the wire decides, not the factory',
	false,
	bws_base_read_refused( array( 'kind' => 'post', 'fans' => true ), array( 'kind' => 'meta_row', 'row' => array( 'name' => 'x' ) ) )
);

// ── report ───────────────────────────────────────────────────────────────────
echo "\n";
echo 'traversal-pipeline: ' . $GLOBALS['pass'] . ' passed, ' . $GLOBALS['fail'] . " failed\n";
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
