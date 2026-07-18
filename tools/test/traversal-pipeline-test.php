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
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $k ) ); }
}
// bws_field_values_assemble_steps is defined mid-file in field-helpers.php among
// WP-dependent siblings; copy the pure function inline rather than require the
// whole file (which pulls WP deps). Keep byte-equivalent to the shipped source.
if ( ! function_exists( 'bws_field_values_assemble_steps' ) ) {
	function bws_field_values_assemble_steps( array $options ): array {
		$steps = array();
		$src = $options['src'] ?? $options['source'] ?? '';
		if ( 'ref' === $src ) {
			$ref = $options['ref'] ?? '';
			if ( '' !== $ref ) {
				$steps[] = array( 'type' => 'ref', 'field' => $ref );
			}
		}
		$tax = sanitize_key( $options['srcTermIn'] ?? '' );
		if ( '' !== $tax ) {
			$steps[] = array( 'type' => 'srcTermIn', 'slug' => $tax );
		}
		return $steps;
	}
}

// bws_wrapper_ref_steps + bws_base_ambient_term_id live in base-tags.php among
// WP-dependent siblings; copy the pure functions inline (house pattern, keep
// byte-equivalent to the shipped source) so their §V13/§V7 guards test WP-free.
if ( ! function_exists( 'bws_wrapper_ref_steps' ) ) {
	function bws_wrapper_ref_steps( array $options ): array {
		$src = $options['src'] ?? $options['source'] ?? '';
		if ( 'ref' === $src ) {
			$ref = $options['ref'] ?? '';
			if ( '' !== $ref ) {
				return array( array( 'type' => 'ref', 'field' => $ref ) );
			}
		}
		return array();
	}
}
if ( ! function_exists( 'bws_base_ambient_term_id' ) ) {
	function bws_base_ambient_term_id( array $base, array $options ): int {
		$tax = sanitize_key( $options['srcTermIn'] ?? '' );
		if ( '' !== $tax ) {
			return 0;
		}
		$src = $options['src'] ?? $options['source'] ?? '';
		if ( 'site' === $src || 'ref' === $src ) {
			return 0;
		}
		if ( 'term' !== ( $base['kind'] ?? '' ) ) {
			return 0;
		}
		return (int) ( $base['id'] ?? 0 );
	}
}
// User-ambient gate (#19 author kind, 1.15.0) — same guards as the term gate,
// kind:'user'. House pattern: copy the pure fn inline (mirror base-shared.php).
if ( ! function_exists( 'bws_base_ambient_user_id' ) ) {
	function bws_base_ambient_user_id( array $base, array $options ): int {
		$tax = sanitize_key( $options['srcTermIn'] ?? '' );
		if ( '' !== $tax ) {
			return 0;
		}
		$src = $options['src'] ?? $options['source'] ?? '';
		if ( 'site' === $src || 'ref' === $src ) {
			return 0;
		}
		if ( 'user' !== ( $base['kind'] ?? '' ) ) {
			return 0;
		}
		return (int) ( $base['id'] ?? 0 );
	}
}

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
	bws_run_traversal( array( post_src( 10 ) ), array( array( 'type' => 'ref', 'field' => 'rel' ) ), $reader )
);

// Fan-out preserves document order across multiple input sources.
$reader = make_reader( array( 'post:1' => array( 100, 101 ), 'post:2' => array( 200 ) ) );
eq(
	'V9 order preserved across sources',
	array( post_src( 100 ), post_src( 101 ), post_src( 200 ) ),
	bws_run_traversal( array( post_src( 1 ), post_src( 2 ) ), array( array( 'type' => 'ref', 'field' => 'rel' ) ), $reader )
);

// Chained steps: ref → ref.
$reader = make_reader( array( 'post:1' => array( 5 ), 'post:5' => array( 9, 8 ) ) );
eq(
	'V9 chained ref->ref',
	array( post_src( 9 ), post_src( 8 ) ),
	bws_run_traversal(
		array( post_src( 1 ) ),
		array( array( 'type' => 'ref', 'field' => 'a' ), array( 'type' => 'ref', 'field' => 'b' ) ),
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
		array( array( 'type' => 'ref', 'field' => 'a' ), array( 'type' => 'ref', 'field' => 'b' ) ),
		$spy
	)
);
eq( 'V9 short-circuit skips later step', false, $touched );

// ── §V2 — shape + silent-empty ───────────────────────────────────────────────

// Unknown step type → [].
eq( 'V2 unknown step type', array(), bws_run_step( array( 'type' => 'bogus' ), post_src( 1 ) ) );

// Unknown source kind → [].
eq( 'V2 unknown source kind', array(), bws_run_step( array( 'type' => 'ref', 'field' => 'x' ), array( 'kind' => 'galaxy', 'id' => 1 ) ) );

// Malformed source (no kind) → [].
eq( 'V2 malformed source no kind', array(), bws_run_step( array( 'type' => 'ref', 'field' => 'x' ), array( 'id' => 1 ) ) );

// Malformed step (no type) → [].
eq( 'V2 malformed step no type', array(), bws_run_step( array( 'field' => 'x' ), post_src( 1 ) ) );

// site is terminal — ref off site → [].
eq( 'V2 ref off terminal site', array(), bws_run_step( array( 'type' => 'ref', 'field' => 'x' ), array( 'kind' => 'site' ) ) );

// srcTermIn valid input is post only — term input → [].
eq( 'V2 srcTermIn rejects term input', array(), bws_run_step( array( 'type' => 'srcTermIn', 'slug' => 'category' ), term_src( 3 ), make_reader( array() ) ) );

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
	bws_run_traversal( array( post_src( 50 ) ), array( array( 'type' => 'srcTermIn', 'slug' => 'category' ) ), $reader )
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
			array( 'type' => 'ref', 'field' => 'related' ),
			array( 'type' => 'srcTermIn', 'slug' => 'category' ),
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
	array( array( 'type' => 'srcTermIn', 'slug' => 'category' ) ),
	bws_field_values_assemble_steps( array( 'srcTermIn' => 'category' ) )
);

// src:ref + ref key → ref step (V6 plural fan-out happens at run time).
eq(
	'assemble src:ref -> ref step',
	array( array( 'type' => 'ref', 'field' => 'related' ) ),
	bws_field_values_assemble_steps( array( 'src' => 'ref', 'ref' => 'related' ) )
);

// #44: src:ref + srcTermIn COMPOUND, emitting [ref, srcTermIn] in that order.
// ref hops source -> related posts, then srcTermIn hops those posts -> terms.
// Order is load-bearing: srcTermIn needs the post kind ref produces.
eq(
	'assemble src:ref + srcTermIn -> [ref, srcTermIn] (compound, #44)',
	array(
		array( 'type' => 'ref', 'field' => 'x' ),
		array( 'type' => 'srcTermIn', 'slug' => 'post_tag' ),
	),
	bws_field_values_assemble_steps( array( 'srcTermIn' => 'post_tag', 'src' => 'ref', 'ref' => 'x' ) )
);

// Bare / current / site → NO steps (base source read directly).
eq( 'assemble bare -> no steps', array(), bws_field_values_assemble_steps( array() ) );
eq( 'assemble src:current -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'current' ) ) );
eq( 'assemble src:site -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'site' ) ) );

// src:ref WITHOUT a ref key → no step (nothing to hop; avoids empty-field ref).
eq( 'assemble src:ref no key -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'ref' ) ) );

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
eq( 'V13 wrapper src:ref -> ref step', array( array( 'type' => 'ref', 'field' => 'related' ) ), bws_wrapper_ref_steps( array( 'src' => 'ref', 'ref' => 'related' ) ) );

// srcTermIn set → NO step (wrapper excludes it; caller owns the term hop). The
// load-bearing B2 assertion: seam would emit a srcTermIn step here, wrapper must not.
eq( 'V13 wrapper srcTermIn -> NO step', array(), bws_wrapper_ref_steps( array( 'srcTermIn' => 'category' ) ) );

// srcTermIn + stray src:ref → still no step from the wrapper? src:ref present →
// wrapper emits ITS ref step; srcTermIn is simply ignored by the wrapper (caller
// owns it). Confirms the wrapper only ever cares about ref.
eq( 'V13 wrapper ref beside srcTermIn -> ref step only', array( array( 'type' => 'ref', 'field' => 'x' ) ), bws_wrapper_ref_steps( array( 'src' => 'ref', 'ref' => 'x', 'srcTermIn' => 'category' ) ) );

// Bare / current / site → no wrapper step.
eq( 'V13 wrapper bare -> no step', array(), bws_wrapper_ref_steps( array() ) );
eq( 'V13 wrapper src:current -> no step', array(), bws_wrapper_ref_steps( array( 'src' => 'current' ) ) );
eq( 'V13 wrapper src:site -> no step', array(), bws_wrapper_ref_steps( array( 'src' => 'site' ) ) );
eq( 'V13 wrapper src:ref no key -> no step', array(), bws_wrapper_ref_steps( array( 'src' => 'ref' ) ) );

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
$hopped = bws_run_traversal( array( term_src( 34 ) ), array( array( 'type' => 'ref', 'field' => 'related' ) ), $reader );
eq( 'V5 term modifier ref hop -> post[]', array( post_src( 91 ), post_src( 92 ) ), $hopped );
eq( 'V5 term modifier ref collapses to first', 91, bws_first_post_id_from_sources( $hopped ) );

// post base + ref step → post[] (view_ modifier src:ref: PortalSource post -> rel).
$reader = make_reader( array( 'post:70' => 88 ) );
$hopped = bws_run_traversal( array( post_src( 70 ) ), array( array( 'type' => 'ref', 'field' => 'rel' ) ), $reader );
eq( 'V5 post modifier ref hop -> first post', 88, bws_first_post_id_from_sources( $hopped ) );

// No ref target → empty hop → false (modifier renders empty, not a leak).
$reader = make_reader( array() );
$hopped = bws_run_traversal( array( term_src( 34 ) ), array( array( 'type' => 'ref', 'field' => 'related' ) ), $reader );
eq( 'V5 modifier ref miss -> false', false, bws_first_post_id_from_sources( $hopped ) );

// ── §V14 — base text/title src:ref LIST mode (B3 fix) ────────────────────────
//
// text/title offer limit/sep for src:ref, so the src:ref post branch must read the
// FULL fanned-out ref post set (not collapse-to-first). The fan-out is the same V6
// engine path; the callback keeps ALL post-kind ids, in order, for slice+join.

// A 2-target ref field (the B3 repro: 2 posts in benefit_vendor) yields BOTH ids,
// in document order — NOT just the first.
$reader = make_reader( array( 'post:5' => array( 61, 62 ) ) );
$hopped = bws_run_traversal( array( post_src( 5 ) ), array( array( 'type' => 'ref', 'field' => 'benefit_vendor' ) ), $reader );
eq( 'V14 src:ref keeps BOTH targets (B3 repro)', array( 61, 62 ), ids_post_kind_only( $hopped ) );

// Order preserved across a 3-target field.
$reader = make_reader( array( 'post:1' => array( 30, 31, 32 ) ) );
$hopped = bws_run_traversal( array( post_src( 1 ) ), array( array( 'type' => 'ref', 'field' => 'r' ) ), $reader );
eq( 'V14 src:ref order preserved', array( 30, 31, 32 ), ids_post_kind_only( $hopped ) );

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

// ── report ───────────────────────────────────────────────────────────────────
echo "\n";
echo 'traversal-pipeline: ' . $GLOBALS['pass'] . ' passed, ' . $GLOBALS['fail'] . " failed\n";
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
