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
		$tax = sanitize_key( $options['srcTermIn'] ?? '' );
		if ( '' !== $tax ) {
			$steps[] = array( 'type' => 'srcTermIn', 'slug' => $tax );
			return $steps;
		}
		$src = $options['src'] ?? $options['source'] ?? '';
		if ( 'ref' === $src ) {
			$ref = $options['ref'] ?? '';
			if ( '' !== $ref ) {
				$steps[] = array( 'type' => 'ref', 'field' => $ref );
			}
		}
		return $steps;
	}
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

// srcTermIn wins + is terminal even if a stray ref present (mutually exclusive).
eq(
	'assemble srcTermIn terminal (ref ignored)',
	array( array( 'type' => 'srcTermIn', 'slug' => 'post_tag' ) ),
	bws_field_values_assemble_steps( array( 'srcTermIn' => 'post_tag', 'src' => 'ref', 'ref' => 'x' ) )
);

// Bare / current / site → NO steps (base source read directly).
eq( 'assemble bare -> no steps', array(), bws_field_values_assemble_steps( array() ) );
eq( 'assemble src:current -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'current' ) ) );
eq( 'assemble src:site -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'site' ) ) );

// src:ref WITHOUT a ref key → no step (nothing to hop; avoids empty-field ref).
eq( 'assemble src:ref no key -> no steps', array(), bws_field_values_assemble_steps( array( 'src' => 'ref' ) ) );

// ── report ───────────────────────────────────────────────────────────────────
echo "\n";
echo 'traversal-pipeline: ' . $GLOBALS['pass'] . ' passed, ' . $GLOBALS['fail'] . " failed\n";
exit( $GLOBALS['fail'] > 0 ? 1 : 0 );
