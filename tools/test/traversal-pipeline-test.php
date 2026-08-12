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
// loads inert behind the shims below (same approach as try-join-seam-test.php).
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
foreach ( array( 'add_action', 'add_filter', 'do_action', 'apply_filters' ) as $wp_fn ) {
	if ( ! function_exists( $wp_fn ) ) {
		eval( "function {$wp_fn}() { return func_num_args() > 1 ? func_get_arg(1) : null; }" );
	}
}
require __DIR__ . '/../../includes/tags/base-shared.php';

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
			'loop'         => array( 'in_loop' => false, 'row_post_id' => false, 'loop_item' => null ),
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

// V1: loop row WINS over ambient term (bare tag inside a query loop on an
// archive reads the ROW, not the term — the precedence that stops the leak).
eq(
	'V1 loop row wins over ambient term',
	array( 'kind' => 'post', 'id' => 48418 ),
	bws_resolve_base_source(
		array(),
		null,
		array(
			'queried_kind' => 'term',
			'queried_id'   => 34,
			'is_tax'       => true,
			'loop'         => array( 'in_loop' => true, 'row_post_id' => 48418, 'loop_item' => null ),
		)
	)
);

// V7 explicit-wins: src:site beats an ambient term archive.
eq(
	'V7 explicit src:site beats ambient term',
	array( 'kind' => 'site' ),
	bws_resolve_base_source( array( 'src' => 'site' ), null, sig( array( 'queried_kind' => 'term', 'queried_id' => 34, 'is_tax' => true ) ) )
);

// Mode 2b flat repeater row (in loop, no row post id) → meta_row.
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
			'loop'         => array( 'in_loop' => true, 'row_post_id' => false, 'loop_item' => array( 'name' => 'x' ) ),
		)
	)
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

// V17: a loop row still wins over the flag (loop precedes the flag check).
eq(
	'V17 loop row beats unresolved-term flag',
	array( 'kind' => 'post', 'id' => 555 ),
	bws_resolve_base_source(
		array(),
		null,
		array(
			'queried_kind'            => null,
			'queried_id'              => 0,
			'is_tax'                  => false,
			'term_context_unresolved' => true,
			'loop'                    => array( 'in_loop' => true, 'row_post_id' => 555, 'loop_item' => null ),
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

// Mode 2b meta_row base (src:current on a flat row) → false (matches old wrapper).
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

// ── §V7 — ambient-term analog gate (bws_base_ambient_term_id) ─────────────────
//
// Fires ONLY for a bare base tag on a term archive: term base, no srcTermIn, src
// not site/ref. Otherwise 0 (post path runs).

// Bare tag + term base → the term id (analog path).
eq( 'V7 term base bare -> term id', 34, bws_base_ambient_term_id( term_src( 34 ), array() ) );
eq( 'V7 term base src:current -> term id', 34, bws_base_ambient_term_id( term_src( 34 ), array( 'src' => 'current' ) ) );

// Post base → 0 (post path).
eq( 'V7 post base -> 0', 0, bws_base_ambient_term_id( post_src( 10 ), array() ) );

// V11: src:ref on a term base → 0 (post path runs the term->post ref hop, NOT the
// term's own analog). The load-bearing V11 guard.
eq( 'V11 src:ref on term base -> 0 (ref hop owns it)', 0, bws_base_ambient_term_id( term_src( 34 ), array( 'src' => 'ref', 'ref' => 'related' ) ) );

// Explicit srcTermIn → 0 (post->term branch owns it; incoherent from a term base).
eq( 'V7 srcTermIn set -> 0', 0, bws_base_ambient_term_id( term_src( 34 ), array( 'srcTermIn' => 'category' ) ) );

// src:site → 0 (own gate).
eq( 'V7 src:site -> 0', 0, bws_base_ambient_term_id( term_src( 34 ), array( 'src' => 'site' ) ) );

// meta_row base → 0 (only 'term' kind qualifies).
eq( 'V7 meta_row base -> 0', 0, bws_base_ambient_term_id( array( 'kind' => 'meta_row', 'row' => array() ), array() ) );

// user base → 0 on the TERM gate (author archive is not a term archive).
eq( 'V7 user base -> 0 (term gate)', 0, bws_base_ambient_term_id( user_src( 7 ), array() ) );

// FW-63: the gate now asks ONE question — is the chain root-only and rooted at the
// ambient entity — so the CHAIN spelling of each source above answers identically.
// These are the rows that would have caught the arm bug: before the refactor the
// gate saw no `srcTermIn` and no `src:ref` token, fired, and read the ambient
// term's analog on a tag whose source states a hop.
eq( 'FW-63 chain terms hop on term base -> 0', 0, bws_base_ambient_term_id( term_src( 34 ), array( 'src' => 'terms,category' ) ) );
eq( 'FW-63 chain refs hop on term base -> 0', 0, bws_base_ambient_term_id( term_src( 34 ), array( 'src' => 'refs,related' ) ) );
eq( 'FW-63 chain entries hop on term base -> 0', 0, bws_base_ambient_term_id( term_src( 34 ), array( 'src' => 'entries,rows' ) ) );
// A REGISTRY-source root is root-only, so it still reaches the $base['kind'] test —
// exactly as the old "src is not site/ref" test let it through.
eq( 'FW-63 registry root still reaches the kind test', 34, bws_base_ambient_term_id( term_src( 34 ), array( 'src' => 'related_post' ) ) );
// And the user twin, so the pair cannot drift.
eq( 'FW-63 chain hop on user base -> 0', 0, bws_base_ambient_user_id( user_src( 7 ), array( 'src' => 'refs,related' ) ) );

// ── #19 author kind — ambient-user analog gate (bws_base_ambient_user_id) ──────
//
// Symmetric with the term gate: fires ONLY for a bare base tag on an author
// archive (user base, no srcTermIn, src not site/ref). Otherwise 0.

eq( 'author user base bare -> user id', 7, bws_base_ambient_user_id( user_src( 7 ), array() ) );
eq( 'author user base src:current -> user id', 7, bws_base_ambient_user_id( user_src( 7 ), array( 'src' => 'current' ) ) );

// Cross-kind exclusion: term/post base → 0 on the USER gate.
eq( 'author term base -> 0 (user gate)', 0, bws_base_ambient_user_id( term_src( 34 ), array() ) );
eq( 'author post base -> 0 (user gate)', 0, bws_base_ambient_user_id( post_src( 10 ), array() ) );

// Same guards as the term gate: src:ref / src:site / srcTermIn keep their own
// meaning → 0 (post path / site gate / post->term branch owns the render).
eq( 'author src:ref on user base -> 0', 0, bws_base_ambient_user_id( user_src( 7 ), array( 'src' => 'ref', 'ref' => 'related' ) ) );
eq( 'author src:site -> 0', 0, bws_base_ambient_user_id( user_src( 7 ), array( 'src' => 'site' ) ) );
eq( 'author srcTermIn set -> 0', 0, bws_base_ambient_user_id( user_src( 7 ), array( 'srcTermIn' => 'category' ) ) );

// ── §V5 — modifier ref hop off a base source (T7 pipeline assembly) ───────────
//
// The modifier callback (term_/view_) resolves a BASE source via base_source_key
// then hops src:ref through the generic ref step — replacing the retired
// TermRelatedPost / PortalRelatedPost traversal classes. Shape assertion: a term
// base hops term->post[] and collapses to first (single-valued modifier link).

// term base + ref step → post[]; first post id (mirrors term_ modifier src:ref).
$reader = make_reader( array( 'term:34' => array( 91, 92 ) ) );
$stepped = bws_run_traversal( array( term_src( 34 ) ), array( array( 'type' => 'refs', 'field' => 'related' ) ), $reader );
eq( 'V5 term modifier ref hop -> post[]', array( post_src( 91 ), post_src( 92 ) ), $stepped );
eq( 'V5 term modifier ref collapses to first', 91, bws_first_post_id_from_sources( $stepped ) );

// post base + ref step → post[] (view_ modifier src:ref: PortalSource post -> rel).
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
// House pattern: copy the shipped function inline, byte-equivalent.

if ( ! function_exists( 'bws_collect_value_list' ) ) {
	function bws_collect_value_list( array $items, callable $render, array $options ): array {
		$limit = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep   = $options['sep'] ?? ', ';

		$item_opts = $options;
		unset( $item_opts['fallback'] );

		$values = array();
		foreach ( array_slice( $items, 0, $limit ) as $item ) {
			$result = $render( $item, $item_opts );
			if ( is_array( $result ) ) {
				$value = (string) ( $result['value'] ?? '' );
				$link  = $result['link'] ?? null;
			} else {
				$value = (string) $result;
				$link  = null;
			}
			if ( '' === $value ) {
				continue;
			}
			$values[] = array(
				'value' => $value,
				'link'  => is_array( $link ) ? $link : null,
			);
		}

		$count = count( $values );
		return array(
			'value'  => implode( $sep, array_column( $values, 'value' ) ),
			'values' => $values,
			'count'  => $count,
			'link'   => 1 === $count ? $values[0]['link'] : null,
		);
	}
}

// Render stub: items are ['v' => value, 'l' => link|null]; '' value = skip source.
$cv_render = function ( $item, array $item_opts ) {
	return array( 'value' => $item['v'], 'link' => $item['l'] ?? null );
};
$cv = function ( ...$items ) use ( $cv_render ) {
	return function ( array $options ) use ( $items, $cv_render ) {
		return bws_collect_value_list( $items, $cv_render, $options );
	};
};

// Two values join with default sep; multi-result → top-level link null (I12
// corollary: the gate is a JOIN constraint).
$r = $cv( array( 'v' => 'A', 'l' => array( 'kind' => 'post', 'id' => 1 ) ),
          array( 'v' => 'B', 'l' => array( 'kind' => 'post', 'id' => 2 ) ) )( array( 'limit' => 5 ) );
eq( 'CV join default sep', 'A, B', $r['value'] );
eq( 'CV multi-result link gate -> null', null, $r['link'] );
eq( 'CV per-value links survive multi', array( 'kind' => 'post', 'id' => 2 ), $r['values'][1]['link'] );
eq( 'CV count', 2, $r['count'] );

// Single result → link passes the gate.
$r = $cv( array( 'v' => 'A', 'l' => array( 'kind' => 'term', 'id' => 9 ) ) )( array( 'limit' => 3 ) );
eq( 'CV single-result link', array( 'kind' => 'term', 'id' => 9 ), $r['link'] );

// Empty renders drop; a lone survivor still passes the gate (GH #51 shape: the
// dropped item must not block the survivor's link).
$r = $cv( array( 'v' => '' ), array( 'v' => 'B', 'l' => array( 'kind' => 'post', 'id' => 4 ) ) )( array( 'limit' => 5 ) );
eq( 'CV empty dropped, survivor linked', array( 'kind' => 'post', 'id' => 4 ), $r['link'] );
eq( 'CV empty dropped from value', 'B', $r['value'] );

// Value with NO link identity (meta_row-shaped) is normal: collects, single-result
// gate yields null, never a sentinel (I12).
$r = $cv( array( 'v' => 'raw' ) )( array() );
eq( 'CV linkless value collects', 'raw', $r['value'] );
eq( 'CV linkless single -> link null not sentinel', null, $r['link'] );

// limit slices BEFORE render (default 1); sep honored.
$r = $cv( array( 'v' => 'A' ), array( 'v' => 'B' ), array( 'v' => 'C' ) )( array( 'limit' => 2, 'sep' => ' | ' ) );
eq( 'CV limit slice + custom sep', 'A | B', $r['value'] );
$r = $cv( array( 'v' => 'A' ), array( 'v' => 'B' ) )( array() );
eq( 'CV default limit 1', 'A', $r['value'] );

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

// All-empty → empty value, count 0, link null (caller's fallback territory).
$r = $cv( array( 'v' => '' ), array( 'v' => '' ) )( array( 'limit' => 5, 'fallback' => 'NOPE' ) );
eq( 'CV all-empty value', '', $r['value'] );
eq( 'CV all-empty count', 0, $r['count'] );
eq( 'CV all-empty link', null, $r['link'] );

// Plain string return accepted as linkless value.
$r = bws_collect_value_list( array( 'a' ), function ( $i, $o ) { return 'plain'; }, array() );
eq( 'CV string return = linkless value', 'plain', $r['value'] );
eq( 'CV string return link null', null, $r['link'] );

// Malformed link (non-array) coerces to null, not a crash.
$r = bws_collect_value_list( array( 'a' ), function ( $i, $o ) { return array( 'value' => 'x', 'link' => 5 ); }, array() );
eq( 'CV non-array link -> null', null, $r['link'] );

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
function row_src( $row ) { return array( 'kind' => 'meta_row', 'row' => $row ); }

// --- coercer (bws_pipeline_rows_to_sources) ---------------------------------
eq(
	'rows coercer: array-of-rows -> meta_row[]',
	array( row_src( array( 'a' => 1 ) ), row_src( array( 'a' => 2 ) ) ),
	bws_pipeline_rows_to_sources( array( array( 'a' => 1 ), array( 'a' => 2 ) ) )
);
eq( 'rows coercer: non-array -> []', array(), bws_pipeline_rows_to_sources( 'nope' ) );
eq( 'rows coercer: empty array -> []', array(), bws_pipeline_rows_to_sources( array() ) );
eq( 'rows coercer: null -> []', array(), bws_pipeline_rows_to_sources( null ) );
eq(
	'rows coercer: skips non-array rows, keeps blank row',
	array( row_src( array( 'a' => 1 ) ), row_src( array() ) ),
	bws_pipeline_rows_to_sources( array( array( 'a' => 1 ), 'scalar', array() ) )
);
eq(
	'rows coercer: order preserved',
	array( row_src( array( 'n' => 'x' ) ), row_src( array( 'n' => 'y' ) ), row_src( array( 'n' => 'z' ) ) ),
	bws_pipeline_rows_to_sources( array( array( 'n' => 'x' ), array( 'n' => 'y' ), array( 'n' => 'z' ) ) )
);

// --- step input-kind gate (bws_run_step case 'rows') ------------------------
// A stub reader that returns a 2-row repeater regardless of source (gate is what
// we test here, not the read).
$rows_reader = function ( $step, $source ) {
	return array( array( 'c' => 'p' ), array( 'c' => 'q' ) );
};
foreach ( array( 'post' => post_src( 5 ), 'term' => term_src( 5 ), 'user' => user_src( 5 ), 'meta_row' => row_src( array( 'r' => array() ) ), 'site' => array( 'kind' => 'site' ) ) as $kname => $src ) {
	eq(
		"rows step accepts {$kname} input",
		array( row_src( array( 'c' => 'p' ) ), row_src( array( 'c' => 'q' ) ) ),
		bws_run_step( array( 'type' => 'entries', 'field' => 'rep' ), $src, $rows_reader )
	);
}
eq(
	'rows step rejects unknown kind -> []',
	array(),
	bws_run_step( array( 'type' => 'entries', 'field' => 'rep' ), array( 'kind' => 'date' ), $rows_reader )
);

// --- fold: rows then bare column read (meta_row reader arm) ------------------
// rows fans a post to 3 meta_rows; the meta_row source then reads a scalar column.
$rows_then = function ( $step, $source ) {
	if ( 'entries' === $step['type'] ) {
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
$rows_out = bws_run_traversal( array( post_src( 9 ) ), array( array( 'type' => 'entries', 'field' => 'team' ) ), $rows_then );
eq(
	'rows fold: post -> 3 meta_rows',
	array(
		row_src( array( 'name' => 'Ann', 'role' => 'Lead' ) ),
		row_src( array( 'name' => 'Bo',  'role' => 'Dev' ) ),
		row_src( array( 'name' => 'Cy',  'role' => '' ) ),
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
	bws_run_traversal( array( post_src( 1 ) ), array( array( 'type' => 'entries', 'field' => 'team' ) ), $empty_rows )
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

// The settings gate is an OFFERING gate too — it hides a term-context root from the
// dropdown, and a tag already naming one keeps rendering.
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = false;
eq(
	'registry: a DISABLED term root still resolves for a tag that already names it',
	array( 'kind' => 'term', 'id' => 99 ),
	bws_resolve_base_source( array( 'src' => 'testtermroot' ), null, sig() )
);
\BWS\DynamicTags\Admin\SettingsPage::$modifiers_enabled = true;

// A root is the chain's FIRST segment, so a rooted chain reaches the same delegation and
// its steps stay the engine's. This is what "registered roots declare no static kind"
// buys: the factory answers at render, and the compiler's static map is untouched.
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
// An UNREGISTERED token is not a root at all: the delegation returns null and the tag
// falls through to the ambient entity, exactly as before #83.
eq(
	'registry: an unknown token falls through to ambient, unchanged',
	array( 'kind' => 'post', 'id' => 0 ),
	bws_resolve_base_source( array( 'src' => 'nosuchsource' ), null, sig() )
);

// ── report ───────────────────────────────────────────────────────────────────
echo "\n";
echo 'traversal-pipeline: ' . $GLOBALS['pass'] . ' passed, ' . $GLOBALS['fail'] . " failed\n";
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
