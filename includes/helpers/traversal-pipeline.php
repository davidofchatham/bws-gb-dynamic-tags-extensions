<?php
/**
 * Traversal pipeline engine — the L1-full data-driven step runner.
 *
 * Replaces the O(N×M) source-class explosion (RelatedPost, TermRelatedPost,
 * SecondRelatedPost, PostTermRelatedPost, …) with a pure fold over resolved
 * sources. A source factory (bws_resolve_base_source, T2) produces the base
 * resolved source; this engine steps it through data-driven steps.
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
 *   - Per-step limit (1.17.0) → a step carrying `limit` slices ITS OWN output to
 *                               that many sources before the next step runs.
 *
 * A step's `limit` is the author's PER-HOP limit (FW-56), a different quantity from
 * the terminal `limit` option: the step limit bounds how far a fan-out spreads
 * (and how much downstream work it multiplies), while the terminal one bounds the
 * VALUE list the caller renders. So the caller still slices the final source list
 * (list mode originates at the plural resolved source, CONTEXT.md §Target
 * cardinality) — this engine remains composition-blind, and `sep` never reaches it.
 * The limit is applied to the step's WHOLE output rather than per input source: that
 * is the quantity the wire names ("at most N of these"), and it is what the
 * legacy flat `limit` sliced when the fan-out was the last step.
 *
 * `limit` is absent unless it bounds something: `0`/`-1` mean unlimited
 * (bws_fold_slot_flat_options and the chain compiler both normalize them away), so a
 * falsy value here is never read as "limit of zero" — a guard the numeric-0 bug class
 * makes worth stating.
 *
 * @since 1.14.0
 * @since 1.17.0 Per-step `limit` (FW-56).
 * @param array[] $sources Resolved sources (see file header typedef).
 * @param array[] $steps   Ordered steps; each: array( 'type' => 'refs'|'terms'|'entries',
 *                         … , 'limit' => int|null ).
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
		// Per-step limit (FW-56). Only a POSITIVE value bounds; 0/-1/absent = unlimited.
		$step_limit = isset( $step['limit'] ) && is_numeric( $step['limit'] ) ? (int) $step['limit'] : 0;
		$sources    = ( $step_limit > 0 ) ? array_slice( $next, 0, $step_limit ) : $next;
	}
	return $sources;
}
}

/**
 * Which resolved-source KINDS each step type accepts as INPUT.
 *
 * Lifted out of the three inline guards in bws_run_step() (1.17.0) so the editor can
 * derive what to OFFER from the same list the engine refuses by. A step offered off a
 * source it cannot take authors wire that silently renders nothing — a `terms` step
 * after a `site` root was the live case — and a second list in the control would be
 * the drift the derived config exists to prevent.
 *
 * Keyed by the step type, which since the V9 alignment IS the wire's slug
 * (`refs`/`terms`/`entries`) — one vocabulary, both layers. BWS_FOLD_STEP_TYPES stays
 * the seam for the one fact the slug does not carry: which key a step's argument rides.
 *
 * A kind absent from a step's list is a REFUSAL, not an omission: bws_run_step returns
 * empty rather than attempting the read. Widening one is a behaviour change on the
 * render path first and an editor change second, in that order.
 *
 * @since 1.17.0
 */
const BWS_TRAVERSAL_STEP_INPUT_KINDS = array(
	'refs'    => array( 'post', 'term', 'user', 'meta_row', 'site' ),
	'terms'   => array( 'post' ),
	'entries' => array( 'post', 'term', 'user', 'meta_row', 'site' ),
);

/**
 * Execute one traversal step against one resolved source.
 *
 * The step types, spelled as the wire spells them (V9 alignment, 1.17.0):
 *   - refs    : step via an ACF relationship/post_object field → post[]
 *               Valid input kinds: post, term, user, meta_row, site (1.17.0).
 *               Does NOT collapse to the first target — returns EVERY
 *               extracted post id (SPEC §V6 plural fix; contrast the legacy
 *               bws_extract_post_id first-only collapse).
 *   - terms   : step to taxonomy terms via get_the_terms → term[]
 *               Valid input kind: post only.
 *   - entries : step into a repeater field via get_field → meta_row[]
 *               Valid input kinds: post, term, user, meta_row, site.
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

	// ONE gate for every step type, off the map the editor also reads. Each arm below
	// keeps the note explaining WHY its list is what it is; what it no longer keeps is
	// its own copy of the list.
	if ( ! in_array( $kind, BWS_TRAVERSAL_STEP_INPUT_KINDS[ $type ] ?? array(), true ) ) {
		return array(); // Unknown step type, or an input kind this step refuses.
	}

	switch ( $type ) {
		case 'refs':
			// Valid input: entity kinds, PLUS site (1.17.0). An ACF options page holds
			// relationship fields like any other field store, and `case 'entries'` below
			// has always accepted site for exactly that reason — so refusing a
			// relationship out of the same store the engine already reads a repeater
			// from was an asymmetry in this allowlist, not a rule. It stayed
			// unreachable while `src:site` and `src:ref` were alternative values of one
			// flat option; a chain (`src:site;refs,x`) makes it authorable, and it
			// would have failed SILENTLY.
			$raw = call_user_func( $reader, $step, $source );
			return bws_pipeline_ref_to_posts( $raw );

		case 'terms':
			// Valid input: post only (get_the_terms needs a post id).
			$raw = call_user_func( $reader, $step, $source );
			return bws_pipeline_terms_to_sources( $raw );

		case 'entries':
			// Hop into a repeater field → meta_row[] (one row per repeater entry).
			// Valid input: entity kinds that own a repeater (post/term/user via
			// get_field's kind-prefixed selector; meta_row for a nested repeater
			// held in a parent row). site is terminal — a site-option repeater is
			// read directly by the reader's 'option' selector, but a { kind:'site' }
			// still carries no id, so allow it: the reader switches on kind.
			$raw = call_user_func( $reader, $step, $source );
			return bws_pipeline_rows_to_sources( $raw );

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
	if ( null === $raw || '' === $raw || array() === $raw ) {
		return array();
	}

	// Normalize to a list of candidate entries. Precedence (mirrors, then tightens,
	// bws_extract_post_id):
	//   - assoc row WITH 'ID'            → ONE post (an ACF row, not a list).
	//   - LIST-shaped array (int keys)   → a relationship list; fan out to every entry.
	//   - STRING-KEYED assoc without 'ID' → ONE field value (an ACF group/map/row),
	//     NOT a post list. Treating it as a list fabricates a bogus post from every
	//     scalar member (review #2 — e.g. ['post'=>123,'qty'=>2] → posts 123 AND 2).
	//     Pass it through as a SINGLE entry so bws_extract_post_id applies its own
	//     precedence (ID key, else first member) — matching the legacy first-only read
	//     for non-relationship shapes that reach the un-type-guarded term/user reader.
	if ( is_array( $raw ) && ! isset( $raw['ID'] ) && array_keys( $raw ) === range( 0, count( $raw ) - 1 ) ) {
		$entries = $raw; // Sequential list → real relationship fan-out.
	} else {
		$entries = array( $raw ); // WP_Post / id / assoc-with-ID / string-keyed value → one.
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
 * Coerce a raw repeater-field read into a meta_row resolved-source list.
 *
 * The `entries` step core (structural twin of bws_pipeline_terms_to_sources): a
 * repeater field is an array-of-rows, each row an assoc array of sub-field
 * values. Every array row becomes one { kind:'meta_row', row } — preserving
 * document order (append order), NO first-only collapse. A column step then
 * reads sub-fields off each meta_row via the existing meta_row reader arm
 * (bws_pipeline_default_reader case 'meta_row') or steps a ref sub-field to a
 * post (case 'refs' accepts meta_row).
 *
 * Non-array input (miss, scalar, null) → array() (V2 silent-empty, short-circuits
 * the fold). Non-array row entries are skipped (a malformed repeater row is not a
 * meta_row); a row that is an empty array is a legitimate blank row and is kept so
 * cell-level blanks read as empty, not as a dropped row.
 *
 * @since 1.17.0
 * @param mixed $raw Raw repeater value (array-of-rows) or anything else.
 * @return array[] Zero or more { kind:'meta_row', row } sources, order preserved.
 */
if ( ! function_exists( 'bws_pipeline_rows_to_sources' ) ) {
function bws_pipeline_rows_to_sources( $raw ) {
	if ( ! is_array( $raw ) || array() === $raw ) {
		return array();
	}
	$out = array();
	foreach ( $raw as $row ) {
		if ( is_array( $row ) ) {
			$out[] = array( 'kind' => 'meta_row', 'row' => $row );
		}
	}
	return $out;
}
}

/**
 * Source factory — resolve the ambient/explicit BASE resolved source (L1 entry).
 *
 * The single point that interprets "where does this tag read from" before any
 * traversal step runs. Precedence (SPEC §V1, probe-verified 2026-07-06):
 *
 *   1. EXPLICIT src token wins over ambient (SPEC §V7 "explicit always wins").
 *      src:site → { kind:'site' }; src:ref/registry sources → post via the
 *      registry / ref step (delegated by the caller — factory returns the
 *      current-context base they step FROM, unless the token names a distinct
 *      pinned/registry source).
 *   2. loop_ctx.in_loop + row_post_id → { kind:'post', id:row } (loop wins over
 *      ambient — a bare tag inside a query loop reads the ROW, not the archive).
 *   3. ambient queried-object is a TERM (is_tax/category/tag) → { kind:'term' }
 *      (SPEC §V7 term ambient — the first #19 kind).
 *   4. else current post via SourceRegistry 'post' → { kind:'post', id } (or a
 *      registry-delegated external source, SPEC §V5).
 *
 * `$post` / get_the_ID() is NEVER consulted for AMBIENT (SPEC §V1): it carries
 * the main query's first row on every results-bearing non-singular context
 * (term archive, search) — a plausible-but-wrong entity. Only loop_ctx (step 2)
 * or an explicit id option feeds a post source.
 *
 * Signals are INJECTED for testability: pass $signals to unit-test precedence
 * with the probe truth table; $signals=null captures live via
 * bws_capture_ambient_signals() (the sole is_tax/get_queried_object/loop read).
 *
 * @since 1.14.0
 * @param array  $options  Tag options (src, id, srcTermIn, …).
 * @param object $instance GB tag instance (for loop context + registry resolve).
 * @param array|null $signals Ambient signals (see bws_capture_ambient_signals);
 *                            null → live capture.
 * @return array One base resolved source (see file header typedef).
 */
if ( ! function_exists( 'bws_resolve_base_source' ) ) {
function bws_resolve_base_source( array $options, $instance, $signals = null ) {
	if ( null === $signals ) {
		$signals = bws_capture_ambient_signals( $instance );
	}

	// The factory reads a src TOKEN. Since 1.17.0 (FW-56) the option may hold a chain
	// instead (`src:refs,office;terms,category`), whose ROOT is the token and whose steps
	// belong to the step engine — so route through the compiler's root reader, which
	// returns a legacy token unchanged (asserted in fold-chain-compile-test.php) and ''
	// for a chain that leads with a step off the ambient entity.
	$src = function_exists( 'bws_fold_src_root_token' )
		? bws_fold_src_root_token( $options )
		: ( $options['src'] ?? $options['source'] ?? '' );
	if ( 'current' === $src ) {
		$src = '';
	}

	// 1. Explicit src:site → terminal site source, ambient irrelevant.
	if ( 'site' === $src ) {
		return array( 'kind' => 'site' );
	}

	// 1b. Explicit non-current src (ref / registry source) — the factory returns
	// the CURRENT-CONTEXT base the caller steps FROM. ref itself is a STEP (added
	// by the assembler), not a base kind; so an explicit ref still bases on the
	// ambient/current entity below. A registry source that resolves its OWN id
	// (needs_relationship_field false, resolves independently) is honored here.
	if ( '' !== $src && 'ref' !== $src ) {
		$delegated = bws_factory_registry_source( $src, $options, $instance );
		if ( null !== $delegated ) {
			return $delegated;
		}
	}

	// 2. Loop row wins over ambient (bare tag in a query loop reads the row).
	$loop = $signals['loop'] ?? array( 'in_loop' => false, 'row_post_id' => false );
	if ( ! empty( $loop['in_loop'] ) && ! empty( $loop['row_post_id'] ) ) {
		return array( 'kind' => 'post', 'id' => (int) $loop['row_post_id'] );
	}

	// 2b. Mode 2b flat repeater row (in loop, no row post id) → meta_row.
	if ( ! empty( $loop['in_loop'] ) && is_array( $loop['loop_item'] ?? null ) ) {
		return array( 'kind' => 'meta_row', 'row' => $loop['loop_item'] );
	}

	// 3. Ambient term archive → term source (SPEC §V7/§V11). queried_kind captured
	//    from get_queried_object, NOT $post. Applies to src:ref too: the term is
	//    the ambient resolved source, so a ref step steps its relationship field
	//    FROM the term (ref I/O table: term → post[]). This FIXES today's leak —
	//    GB get_id($options,'post') = get_the_ID() = the stale first-loop post on
	//    an archive (probe 48418), so today src:ref reads a relationship field off
	//    an arbitrary leaked post. Ambient-term-as-base is V7 applied to ref, NOT
	//    the deferred parity gap (that is PINNING a specific NON-ambient primary).
	//    Singular pages: queried_kind null → falls through → post base (unchanged).
	if ( 'term' === ( $signals['queried_kind'] ?? '' ) && ! empty( $signals['queried_id'] ) ) {
		return array( 'kind' => 'term', 'id' => (int) $signals['queried_id'] );
	}

	// 3c. Ambient author archive → user source (#19 author kind, 1.15.0). Symmetric
	//     with the term branch: get_queried_object() is a WP_User (probe P7), never
	//     $post — the author archive's $post leaks the first authored row. Entity
	//     kind: carries an id, user-field reads via 'user_' . $id. Explicit src /
	//     loop already returned above; only a bare tag on an author archive reaches
	//     here. src:ref keeps its meaning (falls through — no user relationship step yet).
	if ( 'user' === ( $signals['queried_kind'] ?? '' ) && ! empty( $signals['queried_id'] ) ) {
		return array( 'kind' => 'user', 'id' => (int) $signals['queried_id'] );
	}

	// 3b. Degenerate term context (SPEC §V17): the conditional tags claimed a
	//     taxonomy archive but no WP_Term resolved. A bare tag here must NOT leak
	//     the main query's first post — short-circuit to empty (V2 shape). This is
	//     AFTER explicit src / loop rows (they returned above), so only the bare
	//     ambient read on a broken term context reaches this.
	if ( ! empty( $signals['term_context_unresolved'] ) ) {
		return array();
	}

	// 4. Current post via registry (delegates to external sources too, §V5).
	$post_id = bws_factory_current_post_id( $options, $instance );
	return array( 'kind' => 'post', 'id' => (int) $post_id );
}
}

/**
 * Collapse a resolved-source list to a single first-POST id (SPEC §V4 wrapper rule).
 *
 * The back-compat contract of bws_resolve_post_by_source(): callers want a POST
 * id | false, nothing else. Take the first resolved source and return its id ONLY
 * when its kind is 'post'. A non-post base (term ambient on an archive, meta_row
 * for a Mode-2b flat row, site) yields false — those callers are post-semantic
 * (bws_get_srcterm_terms needs a post, {{call}}/datetime treat the result as a
 * post id / link_type:'post'), so a term/row id must NEVER leak out as a "post id".
 *
 * This deliberately keeps wrapper callers collapse-to-first: plural (SPEC §V6)
 * reaches SEAM consumers only; the wrapper stays single-valued (SPEC §V4/§C4).
 *
 * @since 1.14.0
 * @param array[] $sources Resolved-source list (post/term/meta_row/site …).
 * @return int|false First post id, or false when no post-kind source leads.
 */
if ( ! function_exists( 'bws_first_post_id_from_sources' ) ) {
function bws_first_post_id_from_sources( array $sources ) {
	$first = reset( $sources );
	if ( ! is_array( $first ) || 'post' !== ( $first['kind'] ?? '' ) ) {
		return false;
	}
	$id = (int) ( $first['id'] ?? 0 );
	return $id > 0 ? $id : false;
}
}

/**
 * Capture live ambient signals for the factory. The SOLE place the factory
 * reads is_tax/get_queried_object/loop context — isolated so the dispatch in
 * bws_resolve_base_source() stays pure + unit-testable (SPEC §V1/§V7).
 *
 * queried_kind derives from get_queried_object()'s CLASS (never $post) — the
 * probe-verified stable ambient signal: WP_Term → 'term', WP_User → 'user'.
 *
 * term_context_unresolved (SPEC §V17): the conditional tags claim a taxonomy
 * archive but get_queried_object() is NOT a WP_Term (deleted term, malformed query,
 * pre_get_posts manipulation). The factory uses this to short-circuit a bare tag to
 * EMPTY rather than leak the main query's first post.
 *
 * @since 1.14.0
 * @param object $instance GB tag instance (loop context).
 * @return array { queried_kind:'term'|'post'|'user'|null, queried_id:int,
 *                 is_tax:bool, term_context_unresolved:bool, loop:array }
 */
if ( ! function_exists( 'bws_capture_ambient_signals' ) ) {
function bws_capture_ambient_signals( $instance ) {
	$queried_kind            = null;
	$queried_id              = 0;
	$term_context_unresolved = false;

	// Term archive detection — gate on is_tax/category/tag so we only claim a
	// term when WP actually queried one (mirrors bws_reliable_term_context_detection).
	if ( function_exists( 'is_tax' ) && ( is_tax() || is_category() || is_tag() ) ) {
		$qo = get_queried_object();
		if ( $qo instanceof WP_Term ) {
			$queried_kind = 'term';
			$queried_id   = (int) $qo->term_id;
		} else {
			// Claimed a taxonomy archive but no WP_Term resolved — the degenerate
			// case (SPEC §V17). Flag it so the factory short-circuits to empty
			// instead of falling through to the leaked current post.
			$term_context_unresolved = true;
		}
	} elseif ( function_exists( 'is_author' ) && is_author() ) {
		// Author archive (#19 author kind, 1.15.0) — get_queried_object() is a
		// WP_User (probe P7). Zero-result author archives don't leak $post, so no
		// degenerate guard needed here (unlike term). queried_id feeds user-field
		// reads via 'user_' . $id.
		$qo = get_queried_object();
		if ( $qo instanceof WP_User ) {
			$queried_kind = 'user';
			$queried_id   = (int) $qo->ID;
		}
	}

	$loop = function_exists( 'bws_get_loop_row_context' )
		? bws_get_loop_row_context( $instance )
		: array( 'in_loop' => false, 'row_post_id' => false, 'loop_item' => null );

	return array(
		'queried_kind'            => $queried_kind,
		'queried_id'              => $queried_id,
		'is_tax'                  => (bool) $queried_kind,
		'term_context_unresolved' => $term_context_unresolved,
		'loop'                    => $loop,
	);
}
}

/**
 * Resolve an explicit registry source (src token) to a resolved source, or null
 * if the token is not a self-resolving registry source. Post-yielding sources
 * return { kind:'post', id }; a registry source is honored only when it resolves
 * its own id (SPEC §V5 — external sources like PortalSource route through here).
 *
 * @since 1.14.0
 * @param string $src      The src token.
 * @param array  $options  Tag options.
 * @param object $instance GB instance.
 * @return array|null Resolved source, or null to fall through to ambient/current.
 */
if ( ! function_exists( 'bws_factory_registry_source' ) ) {
function bws_factory_registry_source( $src, array $options, $instance ) {
	if ( ! class_exists( '\BWS\DynamicTags\SourceRegistry' ) ) {
		return null;
	}
	$source = \BWS\DynamicTags\SourceRegistry::get_source( $src );
	if ( ! $source ) {
		return null;
	}
	$id = $source->resolve_id( $options, $instance );
	if ( ! $id ) {
		return null;
	}
	// Term-context sources yield a term; everything else yields a post.
	$kind = ( 'term' === $source->get_context_type() ) ? 'term' : 'post';
	return array( 'kind' => $kind, 'id' => (int) $id );
}
}

/**
 * Current-post id via the 'post' registry source (unchanged resolution path;
 * SPEC §V4 wrapper compat leans on this). Returns 0 when unresolvable.
 *
 * @since 1.14.0
 * @param array  $options  Tag options.
 * @param object $instance GB instance.
 * @return int
 */
if ( ! function_exists( 'bws_factory_current_post_id' ) ) {
function bws_factory_current_post_id( array $options, $instance ) {
	if ( ! class_exists( '\BWS\DynamicTags\SourceRegistry' ) ) {
		return 0;
	}
	$source = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	if ( ! $source ) {
		return 0;
	}
	$id = $source->resolve_id( $options, $instance );
	return $id ? (int) $id : 0;
}
}

/**
 * Default field reader — the real WP/ACF read behind refs/terms/entries steps.
 *
 * Isolated so bws_run_traversal/bws_run_step stay pure and unit-testable: the
 * test harness injects a stub reader, keeping the fold WP-free (SPEC §V9).
 * The ACF id-prefix logic ('term_' . $id, 'user_' . $id) that used to live in
 * source classes moves here — step execution owns it, not a source class.
 *
 * @invariant POST-kind `ref` is ACF-COMPATIBLE, not ACF-mandatory (SPEC §V16):
 * try bws_get_related_posts_data (ACF, type-guarded relationship|post_object,
 * plural) FIRST, then fall back to a raw get_post_meta read so non-ACF sites and
 * plain-meta-post-id fields (Pods/Carbon/core) still resolve src:ref. The raw
 * shape is coerced by bws_pipeline_ref_to_posts (string-keyed-assoc guard, #2).
 *
 * @since 1.14.0
 * @param array $step   The step ( 'type', 'field'/'slug' ).
 * @param array $source The resolved source being stepped from.
 * @return mixed Raw field value (refs) or WP_Term[] (terms); '' / array() on miss.
 */
if ( ! function_exists( 'bws_pipeline_default_reader' ) ) {
function bws_pipeline_default_reader( array $step, array $source ) {
	$type = $step['type'] ?? '';
	$kind = $source['kind'] ?? '';

	if ( 'terms' === $type ) {
		$slug = $step['slug'] ?? '';
		if ( 'post' !== $kind || '' === $slug ) {
			return array();
		}
		$terms = get_the_terms( (int) ( $source['id'] ?? 0 ), $slug );
		return ( is_wp_error( $terms ) || empty( $terms ) ) ? array() : array_values( $terms );
	}

	if ( 'entries' === $type ) {
		// Read a repeater field off the source entity → array-of-rows. The ACF
		// kind→selector prefixes live here (same idiom the ref arm uses): post is
		// a bare id, term/user carry ACF's 'term_'/'user_' prefix, site reads the
		// 'option' store. get_field returns the repeater as an array-of-row-arrays
		// (the meta_row shape) — chosen over have_rows(), whose stateful global
		// cursor would break the pure fold. Non-ACF fallback: get_*_meta returns
		// the serialized repeater array for post/term/user; the coercer skips any
		// non-array rows. meta_row source: a nested repeater held in a parent row.
		$field = $step['field'] ?? '';
		if ( '' === $field ) {
			return array();
		}
		$has_get_field = function_exists( 'get_field' );
		switch ( $kind ) {
			case 'post':
				$post_row_id = (int) ( $source['id'] ?? 0 );
				if ( $has_get_field ) {
					$rows = get_field( $field, $post_row_id );
					if ( ! empty( $rows ) ) {
						return $rows;
					}
				}
				return get_post_meta( $post_row_id, $field, true );
			case 'term':
				$term_row_id = (int) ( $source['id'] ?? 0 );
				if ( $has_get_field ) {
					$rows = get_field( $field, 'term_' . $term_row_id );
					if ( ! empty( $rows ) ) {
						return $rows;
					}
				}
				return get_term_meta( $term_row_id, $field, true );
			case 'user':
				$user_row_id = (int) ( $source['id'] ?? 0 );
				if ( $has_get_field ) {
					$rows = get_field( $field, 'user_' . $user_row_id );
					if ( ! empty( $rows ) ) {
						return $rows;
					}
				}
				return get_user_meta( $user_row_id, $field, true );
			case 'site':
				return $has_get_field ? get_field( $field, 'option' ) : get_option( $field );
			case 'meta_row':
				$row = $source['row'] ?? array();
				return ( is_array( $row ) && isset( $row[ $field ] ) ) ? $row[ $field ] : array();
		}
		return array();
	}

	if ( 'refs' === $type ) {
		$field = $step['field'] ?? '';
		if ( '' === $field ) {
			return '';
		}
		switch ( $kind ) {
			case 'post':
				// ACF-COMPATIBLE, not ACF-mandatory (SPEC §V16). Try the canonical ACF
				// relationship reader FIRST: type-guarded to relationship|post_object,
				// returns the PLURAL array (feeds the §V6 coercer). On empty — ACF
				// absent, the field is not an ACF relationship, or a non-ACF handler
				// (Pods/Carbon/core) stored the id(s) in plain meta — FALL BACK to a raw
				// post-meta read (the OLD Mode-2a path). bws_pipeline_ref_to_posts then
				// coerces whatever shape the raw meta holds (id / list of ids), with its
				// string-keyed-assoc guard preventing per-scalar fabrication (review #2).
				$post_ref_id = (int) ( $source['id'] ?? 0 );
				if ( function_exists( 'bws_get_related_posts_data' ) ) {
					$acf = bws_get_related_posts_data( $post_ref_id, $field );
					if ( ! empty( $acf ) ) {
						return $acf;
					}
				}
				return get_post_meta( $post_ref_id, $field, true );
			case 'term':
				// Canonical term read (SPEC §V5): single_only=false preserves the
				// relationship array — byte-identical to the retired
				// TermRelatedPost::resolve_id (bws_read_term_field($rel,$id,false)).
				return function_exists( 'bws_read_term_field' )
					? bws_read_term_field( $field, (int) ( $source['id'] ?? 0 ), false )
					: ( function_exists( 'get_field' )
						? get_field( $field, 'term_' . (int) ( $source['id'] ?? 0 ) )
						: get_term_meta( (int) ( $source['id'] ?? 0 ), $field, true ) );
			case 'user':
				return function_exists( 'get_field' )
					? get_field( $field, 'user_' . (int) ( $source['id'] ?? 0 ) )
					: get_user_meta( (int) ( $source['id'] ?? 0 ), $field, true );
			case 'site':
				// The options store, via the SAME 'option' selector the `rows` arm
				// uses — a globally configured "featured partner" reads the way a
				// per-post one does. Non-ACF fallback mirrors the other kinds'.
				return function_exists( 'get_field' ) ? get_field( $field, 'option' ) : get_option( $field );
			case 'meta_row':
				$row = $source['row'] ?? array();
				return is_array( $row ) ? ( $row[ $field ] ?? '' ) : '';
		}
	}

	return '';
}
}
