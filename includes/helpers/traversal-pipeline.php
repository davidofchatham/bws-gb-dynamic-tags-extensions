<?php
/**
 * Traversal pipeline engine — the L1-full data-driven step runner.
 *
 * Replaces the O(N×M) source-class explosion (RelatedPost, TermRelatedPost,
 * SecondRelatedPost, PostTermRelatedPost, …) with a pure fold over resolved
 * sources. A source factory (bws_resolve_base_source, T2) produces the base
 * resolved source; this engine hops it through data-driven steps.
 *
 * SPEC Phase 1: this file is the engine only (T1). Factory + call-site rewiring
 * land in later tasks. No load-time side effects — every WP/GB symbol touched
 * lives inside a function that runs at/after the init tag pass.
 *
 * ── Resolved source (SPEC §V2, ADR 0002) ────────────────────────────────────
 * A resolved source is a FLAT associative array: a `kind` plus kind-specific
 * keys. NO class, NO nested payload envelope. Payload varies by kind:
 *
 *   array( 'kind' => 'post',     'id'  => 123 )        // entity kind — traversable
 *   array( 'kind' => 'term',     'id'  => 34 )         // entity kind — traversable
 *   array( 'kind' => 'user',     'id'  => 7 )          // entity kind — traversable
 *   array( 'kind' => 'meta_row', 'row' => array(...) ) // entity kind — traversable
 *   array( 'kind' => 'site' )                          // terminal — namespace implicit
 *
 * Entity kinds (post/term/user/meta_row) carry an id/row and are traversable.
 * site + future query-context kinds are terminal (steps produce nothing from
 * them). An unknown/malformed kind yields an empty step result — silent-empty,
 * never a fatal (SPEC §V2).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.14.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run a resolved-source list through an ordered list of traversal steps.
 *
 * Pure fold, no side effects (SPEC §V9): each step consumes the current source
 * list and produces the next, fanning out (one source → zero or more). The
 * result is a flat list whose kind = the last step's output kind (or the input
 * kind when $steps is empty).
 *
 * Semantics (SPEC §V9):
 *   - Empty $steps            → returns $sources unchanged (passthrough).
 *   - Fan-out                 → multiple results from one step feed all later
 *                               steps; document order preserved (append order).
 *   - Short-circuit           → the FIRST step producing an empty list returns
 *                               array() immediately; later steps never run.
 *
 * `limit`/`sep` are NOT applied here — the caller slices the final source list
 * (list mode originates at the plural resolved source, CONTEXT.md §Target
 * cardinality). This engine is composition-blind.
 *
 * @since 1.14.0
 * @param array[] $sources Resolved sources (see file header typedef).
 * @param array[] $steps   Ordered steps; each: array( 'type' => 'ref'|'srcTermIn', … ).
 * @param callable|null $reader Optional field reader injected for testing —
 *                              signature ( array $step, array $source ): array
 *                              returning raw ref-field / term data. Defaults to
 *                              the real WP/ACF read (bws_pipeline_default_reader).
 * @return array[] Flat resolved-source list; array() if any step empties.
 */
if ( ! function_exists( 'bws_run_traversal' ) ) {
function bws_run_traversal( array $sources, array $steps, $reader = null ) {
	foreach ( $steps as $step ) {
		$next = array();
		foreach ( $sources as $source ) {
			foreach ( bws_run_step( $step, $source, $reader ) as $produced ) {
				$next[] = $produced;
			}
		}
		if ( empty( $next ) ) {
			return array(); // Short-circuit: an emptied step ends the chain.
		}
		$sources = $next;
	}
	return $sources;
}
}

/**
 * Execute one traversal step against one resolved source.
 *
 * Two built-in step types (SPEC §I.engine):
 *   - ref       : hop via an ACF relationship/post_object field → post[]
 *                 Valid input kinds: post, term, user, meta_row.
 *                 Does NOT collapse to the first target — returns EVERY
 *                 extracted post id (SPEC §V6 plural fix; contrast the legacy
 *                 bws_extract_post_id first-only collapse).
 *   - srcTermIn : hop to taxonomy terms via get_the_terms → term[]
 *                 Valid input kind: post only.
 *
 * Unknown step type, unknown/terminal source kind, or an input kind invalid for
 * the step → array() (SPEC §V2 silent-empty, never fatal).
 *
 * @since 1.14.0
 * @param array $step    array( 'type' => …, … step-specific keys … ).
 * @param array $source  One resolved source (see file header typedef).
 * @param callable|null $reader Injected field reader (test seam); null → real read.
 * @return array[] Zero or more resolved sources of the step's output kind.
 */
if ( ! function_exists( 'bws_run_step' ) ) {
function bws_run_step( array $step, array $source, $reader = null ) {
	$type = $step['type'] ?? '';
	$kind = $source['kind'] ?? '';

	if ( '' === $type || '' === $kind ) {
		return array(); // Malformed step or source.
	}

	if ( null === $reader ) {
		$reader = 'bws_pipeline_default_reader';
	}

	switch ( $type ) {
		case 'ref':
			// Valid input: entity kinds only. site/query-context are terminal.
			if ( ! in_array( $kind, array( 'post', 'term', 'user', 'meta_row' ), true ) ) {
				return array();
			}
			$raw = call_user_func( $reader, $step, $source );
			return bws_pipeline_ref_to_posts( $raw );

		case 'srcTermIn':
			// Valid input: post only (get_the_terms needs a post id).
			if ( 'post' !== $kind ) {
				return array();
			}
			$raw = call_user_func( $reader, $step, $source );
			return bws_pipeline_terms_to_sources( $raw );

		default:
			return array(); // Unknown step type.
	}
}
}

/**
 * Coerce a raw ref-field read into a plural post resolved-source list.
 *
 * The SPEC §V6 plural core: every extractable post id becomes its own
 * { kind:'post', id } — NO first-only collapse. Accepts the ACF return shapes
 * bws_extract_post_id handles (WP_Post, id, assoc with 'ID', or a list of any),
 * but keeps ALL entries instead of reset()-ing to the first.
 *
 * @since 1.14.0
 * @param mixed $raw Raw ref field value (single or list of ACF post shapes).
 * @return array[] Zero or more { kind:'post', id } sources, order preserved.
 */
if ( ! function_exists( 'bws_pipeline_ref_to_posts' ) ) {
function bws_pipeline_ref_to_posts( $raw ) {
	if ( null === $raw || '' === $raw ) {
		return array();
	}

	// Normalize to a list of candidate entries. A single assoc row with 'ID'
	// is ONE post, not a list — mirror bws_extract_post_id's precedence.
	if ( is_array( $raw ) && ! isset( $raw['ID'] ) ) {
		$entries = $raw;
	} else {
		$entries = array( $raw );
	}

	$out = array();
	foreach ( $entries as $entry ) {
		$id = bws_extract_post_id( $entry );
		if ( $id ) {
			$out[] = array( 'kind' => 'post', 'id' => (int) $id );
		}
	}
	return $out;
}
}

/**
 * Coerce a WP_Term[] read into a term resolved-source list.
 *
 * @since 1.14.0
 * @param mixed $raw WP_Term[] (from get_the_terms) or anything else.
 * @return array[] Zero or more { kind:'term', id } sources.
 */
if ( ! function_exists( 'bws_pipeline_terms_to_sources' ) ) {
function bws_pipeline_terms_to_sources( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $term ) {
		if ( $term instanceof WP_Term ) {
			$out[] = array( 'kind' => 'term', 'id' => (int) $term->term_id );
		} elseif ( is_array( $term ) && isset( $term['term_id'] ) ) {
			$out[] = array( 'kind' => 'term', 'id' => (int) $term['term_id'] );
		}
	}
	return $out;
}
}

/**
 * Default field reader — the real WP/ACF read behind ref/srcTermIn steps.
 *
 * Isolated so bws_run_traversal/bws_run_step stay pure and unit-testable: the
 * test harness injects a stub reader, keeping the fold WP-free (SPEC §V9).
 * The ACF id-prefix logic ('term_' . $id, 'user_' . $id) that used to live in
 * source classes moves here — step execution owns it, not a source class.
 *
 * @since 1.14.0
 * @param array $step   The step ( 'type', 'field'/'slug' ).
 * @param array $source The resolved source being hopped from.
 * @return mixed Raw field value (ref) or WP_Term[] (srcTermIn); '' / array() on miss.
 */
if ( ! function_exists( 'bws_pipeline_default_reader' ) ) {
function bws_pipeline_default_reader( array $step, array $source ) {
	$type = $step['type'] ?? '';
	$kind = $source['kind'] ?? '';

	if ( 'srcTermIn' === $type ) {
		$slug = $step['slug'] ?? '';
		if ( 'post' !== $kind || '' === $slug ) {
			return array();
		}
		$terms = get_the_terms( (int) ( $source['id'] ?? 0 ), $slug );
		return ( is_wp_error( $terms ) || empty( $terms ) ) ? array() : array_values( $terms );
	}

	if ( 'ref' === $type ) {
		$field = $step['field'] ?? '';
		if ( '' === $field ) {
			return '';
		}
		switch ( $kind ) {
			case 'post':
				return get_post_meta( (int) ( $source['id'] ?? 0 ), $field, true );
			case 'term':
				// ACF stores term fields under the 'term_{id}' object id.
				return function_exists( 'get_field' )
					? get_field( $field, 'term_' . (int) ( $source['id'] ?? 0 ) )
					: get_term_meta( (int) ( $source['id'] ?? 0 ), $field, true );
			case 'user':
				return function_exists( 'get_field' )
					? get_field( $field, 'user_' . (int) ( $source['id'] ?? 0 ) )
					: get_user_meta( (int) ( $source['id'] ?? 0 ), $field, true );
			case 'meta_row':
				$row = $source['row'] ?? array();
				return is_array( $row ) ? ( $row[ $field ] ?? '' ) : '';
		}
	}

	return '';
}
}
