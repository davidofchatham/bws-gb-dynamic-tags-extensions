<?php
/**
 * FW-56 chain COMPILER — the wire's source chain → the traversal engine's steps.
 *
 * THE MISSING MIDDLE. `slot-fold.php` owns the wire (an ordered chain of steps) and
 * `traversal-pipeline.php` owns execution (an ordered list of engine steps), but until
 * 1.17.0 nothing translated one into the other: two hand-written assemblers read the
 * FLAT `src`/`ref`/`srcTermIn` keys and stopped at one relationship step plus one term
 * step. So the wire could state chains the renderer could not run, and a depth-0
 * `src:refs,office` parsed as an unknown SOURCE token. Both assemblers now live here as
 * thin adapters over one compile, which is what lets an arbitrary chain resolve.
 *
 * NO JS TWIN, BY CONSTRUCTION. Every other fold file has one (`slot-fold-grammar.js`,
 * `slot-fold-migrate.js`) because the editor parses and rewrites the same wire. It never
 * RUNS a chain — engine step types are render-side vocabulary the editor has no use for —
 * so this half is PHP-only and a reader should not go looking for its mirror.
 *
 * The wire slug names the step category and signals cardinality by number
 * (`refs`/`terms`/`rows`, all plural). It is ALSO the engine's step type — one
 * vocabulary across both layers since the V9 alignment, which is stated where it is
 * enforced (`BWS_TRAVERSAL_STEP_INPUT_KINDS` in `traversal-pipeline.php`, keyed by
 * that slug). The map below is not a translation, then: it carries the one fact the
 * slug does not, which is the KEY a step's argument rides.
 *
 * Load-bearing properties:
 *
 * - **The ROOT is not a step.** A chain's first segment is either an entity ROOT
 *   (`site`, `current`, a registry source — SINGULAR by DECISION 3's disjointness rule)
 *   or already a step off the ambient entity (PLURAL). Roots are consumed by the source
 *   FACTORY (bws_resolve_base_source), steps by the engine, so the compile splits them
 *   rather than making the factory understand chains.
 * - **An ARGLESS fanning step KEEPS its step and loses only its argument** (#74). The
 *   engine answers empty for it, so the chain short-circuits and the tag renders nothing.
 *   The retired assemblers dropped it — and so did this compiler through 1.16.x — which
 *   left the chain with NO steps, and a chain with no steps resolves the AMBIENT entity.
 *   A legacy `src:ref` with no `ref` therefore read the post you were standing on rather
 *   than nothing: a plausible value from the wrong entity, worse than an empty one because
 *   nothing looks broken. This is the same principle as the unknown-slug rule below, and
 *   the slot control has always TOLD authors it works this way ("This attempt will be
 *   skipped unless a field is set") while only the slot path did it.
 * - **An UNKNOWN step slug compiles to an unknown engine type, never to nothing.** The
 *   engine's default arm yields an empty list (V2 silent-empty), so the chain
 *   short-circuits and the tag renders nothing. Dropping the step instead would read a
 *   DIFFERENT source than the wire states — the same principle that makes an
 *   inexpressible slot chain skip rather than render its prefix.
 * - **Per-step `limit` is emitted ONLY when it BOUNDS the step.** `0`/`-1` mean
 *   unlimited, and an absent key is how the engine spells no limit, so an unlimited
 *   step compiles to the
 *   byte-identical step array the flat assemblers produced. That exact equality is what
 *   the equivalence cases in tools/test/fold-chain-compile-test.php assert.
 *
 * Pure but for one WP call (`sanitize_key` on a taxonomy slug, as the retired assembler
 * had); the harnesses shim it. tools/test/fold-chain-compile-test.php and
 * tools/test/traversal-pipeline-test.php both load this file rather than copying it.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wire slug → the step key each step type reads its argument from.
 *
 * The FANNING family (plural slugs, BWS_FOLD_FANNING_SLUGS) in full. A slug absent from
 * this map at a step position is unknown vocabulary, not a root — see the header.
 *
 * Still the SEAM — the single owner of "which slugs are steps, and what each one
 * consumes" — but narrowed to the one fact the slug does not carry. It used to also map
 * each slug onto a differently-spelled engine type (`ref`/`srcTermIn`/`rows`); the V9
 * alignment retired that spelling (a serialization token leaked inward as a category
 * name, and a one-letter `ref`/`refs` slip hazard), so the engine now dispatches on the
 * wire's own slugs and an identity column would say nothing.
 *
 * @since 1.17.0
 * @since 1.17.0 V9: narrowed from slug => { type, arg } — engine types align to the slugs.
 */
const BWS_FOLD_STEP_TYPES = array(
	'refs'  => 'field',
	'terms' => 'slug',
	'rows'  => 'field',
);

/**
 * Wire slug → the RESOLVED-SOURCE KIND a step of that type produces.
 *
 * The dispatch axis FW-63 replaced the flat token tests with. Deliberately a
 * separate map from BWS_FOLD_STEP_TYPES: that one names the ARG KEY a step consumes,
 * this one names the KIND that step's output carries (`post`/`term`/`meta_row`, the
 * traversal-pipeline typedef). A render arm asks the second question and never the
 * first.
 *
 * @since 1.17.0
 */
const BWS_FOLD_STEP_KINDS = array(
	'refs'  => 'post',
	'terms' => 'term',
	'rows'  => 'meta_row',
);

/**
 * Root token → the resolved-source KIND it carries, for the roots that answer at PARSE TIME.
 *
 * Only `site` is. Every other root — `current`, a registry source name — is the source
 * FACTORY's to resolve at render, and comes back `render_time` (see bws_fold_chain_resolution's
 * `kind` list), so the wire cannot say more than "ask the factory".
 *
 * A map rather than the inline ternary it replaces, because there are TWO readers and
 * they read it in opposite directions: bws_fold_chain_resolution() answers what a
 * root-only chain resolved to (render authority), and bws_fold_wire_vocabulary()
 * ships it to the editor (the fold config's `roots`) to decide which steps to OFFER
 * off that root. A second copy would let the editor come to disagree with what
 * renders — the class of drift the whole derive exists to prevent.
 *
 * @since 1.17.0
 */
const BWS_FOLD_PARSE_TIME_ROOT_KINDS = array(
	'site' => 'site',
);

/**
 * Trim a CARRIED chain back to the point where another chain can run off it.
 *
 * The `same` merge's whole content (bws_fold_slot_chain_options). `src(same)` copies the
 * prior slot's resolved chain, and this slot's own steps then run off the end of it — but
 * only where the engine can actually make that join. A `terms` step accepts a POST input
 * and produces TERMS, so a carried chain ending in `terms` followed by a slot's own
 * `terms` step states something the wire can spell and the engine can never resolve. The
 * author meant the same source in a different taxonomy, which is what the flat wire they
 * came from said, so the carried tail gives way.
 *
 * TWO CRUDER SHAPES WERE TRIED AND REJECTED BEFORE THIS ONE. Do not reintroduce either:
 * - Append `$own` after the whole of `$carried` with no trim at all. Behind a chain that
 *   already ends where an own step would run again, that repeats a step the engine can't
 *   take twice off the same input and resolves empty, silently dropping the slot.
 * - Displace every carried step that shares a slug with an own step, wherever it sits.
 *   See below for why this reads right on every legacy shape and is wrong the moment a
 *   slot states more than one step.
 *
 * THE TEST IS THE JOIN, NOT THE SLUG, and the difference is not academic. The rejected cut
 * above dropped a carried step wherever this slot stated one with the same slug and the
 * slug could not repeat, which reads the same on every legacy shape and is wrong the moment a
 * slot states more than one step: a carried `terms,department` plus an own
 * `refs,x;terms,y` lost the carried step, even though `refs` ACCEPTS a term input and the
 * chain would have run exactly as written. Asking whether the two ends MEET answers both
 * cases from one rule.
 *
 * Trimming is from the TAIL and stops at the first step that fits, so it gives way by as
 * little as possible. It never touches the ROOT — a root is not a step (see the header) and
 * the source a slot carries over is the source it carries over.
 *
 * DERIVED from the two shipped maps, never a list of slugs: BWS_FOLD_STEP_KINDS says what a
 * step produces, BWS_TRAVERSAL_STEP_INPUT_KINDS what it accepts. Widen what the engine takes
 * and this follows, because the reason a step gave way was that the engine refused it.
 *
 * Declines to trim at all where either end is UNKNOWN vocabulary — an unknown step
 * short-circuits the chain to empty by design ([I14]), and rewriting a source on the
 * strength of a slug nothing recognises would answer a question the wire did not ask.
 *
 * THAT POSTURE IS NOT YET UNIVERSAL, and the gap is worth knowing about from here: the base
 * render ARMS have no case for the `''` kind this same fact produces, so they fall to their
 * catch-all singular read and silently DROP the unknown step, rendering the chain's prefix
 * (GH #109). This function, the compiler's unknown-TYPE rule and #105's preview all agree
 * that unknown vocabulary is a distinct answer; the arm is the one place it collapses into
 * "no chain". A fix there should read exactly like the guard below.
 *
 * @since 1.17.0
 * @param array $carried Parsed chain copied from the carry (may include a leading ROOT).
 * @param array $own       Parsed steps this slot states of its own (never a root).
 * @return array The carried chain, trimmed to where `$own` can run off it.
 */
function bws_fold_chain_join( array $carried, array $own ): array {
	$carried = array_values( $carried );
	$first     = reset( $own );
	if ( ! is_array( $first ) || ! defined( 'BWS_FOLD_STEP_KINDS' ) || ! defined( 'BWS_TRAVERSAL_STEP_INPUT_KINDS' ) ) {
		return $carried;
	}
	$accepts = BWS_TRAVERSAL_STEP_INPUT_KINDS[ (string) ( $first['slug'] ?? '' ) ] ?? array();
	if ( ! $accepts ) {
		return $carried;   // unknown vocabulary: the chain short-circuits, it does not rewrite.
	}

	// The ROOT is never trimmed, so stop while one step is left to be a step rather than a
	// root — position 0 is only a root when its slug is not a step type, which the loop's
	// own `produces` lookup answers for free (a root has no entry and ends the trim).
	while ( $carried ) {
		$last     = end( $carried );
		$produces = BWS_FOLD_STEP_KINDS[ (string) ( $last['slug'] ?? '' ) ] ?? '';
		if ( '' === $produces || in_array( $produces, $accepts, true ) ) {
			break;   // a root, unknown vocabulary, or a tail this chain can run off.
		}
		array_pop( $carried );
	}
	return array_values( $carried );
}

/**
 * What a chain RESOLVES TO — the render path's dispatch axis (FW-63).
 *
 * Roughly nineteen arms used to pick a resolution branch by comparing the flat
 * `src` value to `'ref'`/`'site'` or by reading `srcTermIn` directly. Those tests
 * conflate two axes the compiler already separates: where a chain ROOTS (owned by
 * bws_fold_src_root_token, which the source factory consumes) and what it RESOLVES
 * TO. This answers the second, so an arm dispatches on the same fact whichever way
 * the source is spelled.
 *
 * PURE and PARSE-TIME — a function of the wire alone, with no query and no render. That
 * is a hard requirement, not an optimisation: the editor's field pickers and the
 * `limit` control's reveal predicate both have to scope themselves before anything
 * resolves.
 *
 * `kind` is a property of the CHAIN, not of its last step — a root-only chain has a
 * kind and has no steps:
 *
 *   'site'     the chain roots at the site store and never steps (`src:site`).
 *   'post'     the last step is `refs`.
 *   'term'     the last step is `terms`.
 *   'meta_row' the last step is `rows`.
 *   'render_time' root-only, rooted at the ambient entity or a registry source — the
 *              kind is whatever the FACTORY resolves at render (post on a singular
 *              page, term on a term archive, user on an author archive, meta_row in
 *              a flat repeater row). Parse-time analysis cannot say which, and an arm
 *              that needs to know branches on `$base['kind']` as it always has.
 *   ''         UNRECOGNIZED — the last step is vocabulary this engine does not know.
 *              The engine answers empty for an unknown type, so the chain
 *              short-circuits; the kind is honestly unrecognized rather than guessed
 *              back to the root. DISTINCT FROM `render_time`,
 *              which is the honest answer for a chain with no steps at all — an arm
 *              that treats the two alike reads the chain's prefix instead of nothing
 *              (GH #109; bws_fold_chain_join() below is the posture to copy).
 *
 * `fans` is CAPACITY read from the wire — "this chain may resolve more than one
 * source" — never a claim about a given render. Every step slug fans, so it is true
 * iff the chain steps at all.
 *
 * The chain is read as PARSED, not as COMPILED. The two no longer disagree about argless
 * steps (#74 stopped the compiler dropping them), but the distinction still matters and
 * must not be "simplified" away: position 0 is a ROOT here and is consumed by the factory
 * rather than compiled, so the compiled list cannot answer what the chain's root is. Read
 * the parsed chain, which still holds it.
 *
 * @since 1.17.0
 * @param array $chain Parsed chain (bws_fold_parse_chain shape).
 * @return array{root:string, kind:string, fans:bool}
 */
function bws_fold_chain_resolution( array $chain ): array {
	$root  = bws_fold_chain_root( $chain );
	$last  = '';
	$fans  = false;

	foreach ( array_values( $chain ) as $i => $step ) {
		$slug = (string) ( $step['slug'] ?? '' );
		if ( '' === $slug ) {
			continue;
		}
		// Position 0 may be a ROOT (factory-owned) instead of a step.
		if ( 0 === $i && ! isset( BWS_FOLD_STEP_TYPES[ $slug ] ) ) {
			continue;
		}
		$last = $slug;
		$fans = true;
	}

	if ( ! $fans ) {
		return array(
			'root' => $root,
			'kind' => BWS_FOLD_PARSE_TIME_ROOT_KINDS[ $root ] ?? 'render_time',
			'fans' => false,
		);
	}

	return array(
		'root' => $root,
		'kind' => BWS_FOLD_STEP_KINDS[ $last ] ?? '',
		'fans' => true,
	);
}

/**
 * bws_fold_chain_resolution() for a tag's OPTIONS — the arms' entry point.
 *
 * Reads the depth-0 chain the same way every other consumer does
 * (bws_fold_chain_from_options), so a flat `src:ref|ref:x` and a chain
 * `src:refs,x` answer identically. That identity IS the equivalence property the
 * fold integration matrix asserts per base tag family.
 *
 * @since 1.17.0
 * @param array $options Tag options.
 * @return array{root:string, kind:string, fans:bool}
 */
function bws_fold_src_resolution( array $options ): array {
	return bws_fold_chain_resolution( bws_fold_chain_from_options( $options ) );
}

/**
 * The chain's ROOT token — what the source factory reads as `src`.
 *
 * '' when the chain is empty or LEADS with a step (the step applies to the ambient
 * entity, which is exactly what a bare tag resolves). Otherwise the first step's slug,
 * verbatim: `site`, `current`, a registry source name, or a slot sentinel (`same`) whose
 * meaning the container resolves BEFORE compiling — this function never interprets one.
 *
 * @since 1.17.0
 * @param array $chain Parsed chain (bws_fold_parse_chain shape).
 * @return string Factory src token ('' = ambient).
 */
function bws_fold_chain_root( array $chain ): string {
	$first = reset( $chain );
	if ( ! is_array( $first ) ) {
		return '';
	}
	$slug = (string) ( $first['slug'] ?? '' );
	return isset( BWS_FOLD_STEP_TYPES[ $slug ] ) ? '' : $slug;
}

/**
 * Compile a parsed chain into ordered traversal steps.
 *
 * The leading ROOT step (if any) is dropped — the factory owns it. Everything after is a
 * step and compiles in wire order, so the #44 rule (`refs` before `terms`, because a term
 * step needs a post input) is carried by the WIRE rather than re-imposed here.
 *
 * `$ignore_limits` is the collapsing-tag switch (ADR 0007): a template that takes a
 * single usable result (`takes_first_usable`) resolves its WHOLE chain and selects,
 * so no step limit rides its compiled steps — at any position, stamped or hand-typed.
 * Enforced HERE, at the one point both step assemblers pass through, so neither the
 * engine nor any callback carries a special case. The WIRE is untouched: suppression
 * is an editor concern and serialization is the grammar's, so a saved `limit(N)`
 * stays exactly as authored and starts applying again if the tag type changes.
 *
 * @since 1.17.0
 * @since 1.18.0 $ignore_limits — collapsing tags compile every step limit-free.
 * @param array $chain         Parsed chain (bws_fold_parse_chain shape).
 * @param bool  $ignore_limits Compile every step WITHOUT its limit (collapsing tags).
 * @return array[] Engine steps for bws_run_traversal().
 */
function bws_fold_chain_to_steps( array $chain, bool $ignore_limits = false ): array {
	$steps = array();

	foreach ( array_values( $chain ) as $i => $step ) {
		$slug = (string) ( $step['slug'] ?? '' );
		if ( '' === $slug ) {
			continue;
		}
		$known = BWS_FOLD_STEP_TYPES[ $slug ] ?? null;

		// Position 0 may be a ROOT (factory-owned) instead of a step.
		if ( 0 === $i && null === $known ) {
			continue;
		}

		// TWO STEPS, TWO NAMES, and the distinction is the whole function: `$step` is the
		// CHAIN step (the described structure, read off the wire), `$engine_step` is the
		// PIPELINE step it compiles to. One name for both silently read the ENGINE step's
		// `limit` — always absent — instead of the wire's, dropping every per-step limit.
		$arg = trim( (string) ( $step['arg'] ?? '' ) );
		if ( null === $known ) {
			// Unknown step vocabulary — an unknown engine type, which yields an empty
			// list and short-circuits the chain. Never silently dropped (see header).
			$engine_step = array( 'type' => $slug );
		} else {
			if ( 'terms' === $slug ) {
				$arg = sanitize_key( $arg );
			}
			// An ARGUMENT-LESS fanning step keeps its step and loses only its argument
			// (#74). The engine's reader answers '' for a field-less `refs` and array()
			// for a slug-less `terms`, so the chain short-circuits and the tag renders
			// nothing — which is the point. DROPPING the step instead (what shipped
			// through 1.16.x, matching the retired flat assemblers) leaves the chain with
			// no steps at all, and a chain with no steps resolves the AMBIENT entity: a
			// tag whose wire says "follow a relationship" would read the post you are
			// standing on. That is a plausible value from the wrong entity, which is
			// strictly worse than an empty one because nothing looks broken.
			//
			// A sanitized-to-nothing `terms` arg lands here too: an unusable argument and
			// an absent one are the same state, and the flat spelling could not tell them
			// apart either.
			$engine_step = ( '' === $arg )
				? array( 'type' => $slug )
				: array( 'type' => $slug, $known => $arg );
		}

		// Only a REAL bound rides the step: 0/-1 are unlimited and the engine spells that
		// as an absent key, which also keeps an unlimited step byte-identical to the flat
		// assemblers' output. A collapsing tag rides no bound at all (see the docblock).
		$limit = $ignore_limits ? null : ( $step['limit'] ?? null );
		if ( null !== $limit && '' !== $limit && is_numeric( $limit ) && (int) $limit > 0 ) {
			$engine_step['limit'] = (int) $limit;
		}

		$steps[] = $engine_step;
	}

	return $steps;
}

/**
 * Whether a `src` option VALUE is chain wire rather than a plain legacy token.
 *
 * Conservative by design: every shipped `src` value is a single bare token (`current`,
 * `site`, `ref`, a registry source name) and cannot hold a chain separator or bracket —
 * those chars are grammar. So a value carrying one IS a chain, and a value that IS a
 * fanning slug is a one-step chain (`src:refs` argless, `src:terms,category`). Everything
 * else stays a token and reaches the factory untouched.
 *
 * @since 1.17.0
 * @param string $value Raw `src` value.
 * @return bool
 */
function bws_fold_chain_is_wire( string $value ): bool {
	if ( '' === $value ) {
		return false;
	}
	if ( isset( BWS_FOLD_STEP_TYPES[ $value ] ) ) {
		return true;
	}
	return (bool) preg_match( '/[;,\[\]()]/', $value );
}

/**
 * The DEPTH-0 chain a base/table tag's options describe.
 *
 * One reading for both wire eras, so every consumer of a chain asks one function:
 *   - CHAIN wire in `src` (`src:refs,office;terms,category`) parses as the chain;
 *   - LEGACY flat keys build the same chain the fold's slot mapper builds from them
 *     (`src:ref` + `ref:<field>` → `refs,<field>`; `srcTermIn:<tax>` → `terms,<tax>`).
 *
 * A legacy `srcTermIn` is APPENDED to a chain that has no `terms` step of its own. It is
 * a separate option KEY describing a step, not part of the `src` value, so a hand-edited
 * `src:refs,office|srcTermIn:category` would otherwise lose a configured step; when the
 * chain already states a term step, the chain wins and nothing is duplicated.
 *
 * Malformed chain wire falls back to the legacy reading (the raw value as a root token),
 * which resolves the ambient entity — never a fatal, and never a fabricated step.
 *
 * @since 1.17.0
 * @param array $options Tag options (src|source, ref, srcTermIn).
 * @return array Parsed chain (bws_fold_parse_chain shape).
 */
function bws_fold_chain_from_options( array $options ): array {
	$raw   = trim( (string) ( $options['src'] ?? $options['source'] ?? '' ) );
	$tax   = trim( (string) ( $options['srcTermIn'] ?? '' ) );
	$chain = null;

	if ( function_exists( 'bws_fold_parse_chain' ) && bws_fold_chain_is_wire( $raw ) ) {
		$parsed = bws_fold_parse_chain( $raw );
		if ( ! isset( $parsed['error'] ) ) {
			$chain = $parsed;
		}
	}

	if ( null === $chain ) {
		$chain = array();
		$step  = static function ( string $slug, ?string $arg = null ): array {
			return array( 'slug' => $slug, 'arg' => $arg, 'limit' => null, 'extra' => array() );
		};
		if ( 'ref' === $raw ) {
			// An ORPHAN `src:ref` keeps its step with a NULL arg, not `''`. Both
			// compile identically (the argless drop trims either to empty) and both
			// emit the bare slug — but the JS twin spells absence `null`, and a struct
			// that differs by spelling is a diff the twin harness reports on every
			// case that touches it. `null` is also what bws_fold_from_flat() already
			// writes for the same shape.
			$ref     = trim( (string) ( $options['ref'] ?? '' ) );
			$chain[] = $step( 'refs', '' !== $ref ? $ref : null );
		} elseif ( '' !== $raw ) {
			$chain[] = $step( $raw );
		}
	}

	// A SITE root never takes the legacy term step. `srcTermIn` is registered
	// `show_if src: not:site` (bws_base_traversal_options), so the pair is
	// hand-edit-only — and every arm has always let the site read win over it.
	// Appending the step here would flip that stored tag from "the site value" to
	// "empty" (the engine's `srcTermIn` step needs a POST input), which is a
	// rendered-output change on flat wire the arm refactor must not make.
	if ( '' !== $tax && 'site' !== bws_fold_chain_root( $chain ) ) {
		$has_terms = false;
		foreach ( $chain as $step ) {
			if ( 'terms' === ( $step['slug'] ?? '' ) ) {
				$has_terms = true;
				break;
			}
		}
		if ( ! $has_terms ) {
			$chain[] = array( 'slug' => 'terms', 'arg' => $tax, 'limit' => null, 'extra' => array() );
		}
	}

	return $chain;
}

/**
 * The factory's `src` token for a tag whose source may be chain wire.
 *
 * bws_resolve_base_source() calls this instead of reading `$options['src']` directly, so
 * a chain's root reaches the factory as the token it already understands. For every
 * legacy option set the return is byte-identical to the old read — asserted in
 * tools/test/fold-chain-compile-test.php, because that identity is what keeps the change
 * invisible to every unmigrated tag.
 *
 * @since 1.17.0
 * @param array $options Tag options.
 * @return string Factory src token ('' = ambient).
 */
function bws_fold_src_root_token( array $options ): string {
	return bws_fold_chain_root( bws_fold_chain_from_options( $options ) );
}

/**
 * Assemble traversal steps from tag options (PURE — options → steps[]).
 *
 * The step-assembly half of the value-list SEAM (bws_resolve_field_values). Since 1.17.0
 * a thin adapter over the chain compile: it reads the tag's depth-0 chain — from chain
 * wire or from the legacy flat keys — and compiles the whole thing, so a multi-step chain
 * resolves instead of stopping at `[ref, srcTermIn]`.
 *
 * **`src:ref` and `srcTermIn` still COMPOUND** (issue #44): the legacy reading builds
 * `refs` then `terms`, and the compile preserves wire order, so the emitted steps are
 * `[ref, srcTermIn]` exactly as before. Order is load-bearing — srcTermIn reads
 * get_the_terms and needs a POST input, which the ref step produces; the reverse order
 * would feed it a term and short out. The rule now lives in ONE place
 * (bws_fold_chain_from_options) instead of in each assembler.
 *
 * MOVED from field-helpers.php in 1.17.0 (5h) to sit with the compile it delegates to.
 *
 * @since 1.14.0
 * @since 1.14.0 #44: src:ref + srcTermIn now compound instead of dropping ref.
 * @since 1.17.0 Compiles the depth-0 chain (arbitrary steps); moved here from field-helpers.php.
 * @since 1.18.0 $ignore_limits threaded to the compile (collapsing tags, ADR 0007).
 * @param array $options       Tag options (src, ref, srcTermIn).
 * @param bool  $ignore_limits Compile every step WITHOUT its limit (collapsing tags).
 * @return array[] Ordered traversal steps (may be empty).
 */
if ( ! function_exists( 'bws_field_values_assemble_steps' ) ) {
function bws_field_values_assemble_steps( array $options, bool $ignore_limits = false ): array {
	return bws_fold_chain_to_steps( bws_fold_chain_from_options( $options ), $ignore_limits );
}
}

/**
 * Assemble the wrapper's REF-ONLY step set (SPEC §V13, B2).
 *
 * Post-semantic: bws_resolve_post_by_source() and the base helpers built on it collapse
 * to a POST id, and a `srcTermIn` post→term step is DELIBERATELY excluded — the wrapper's
 * callers own that downstream on the returned post id (bws_get_srcterm_terms). Contrast
 * bws_field_values_assemble_steps(), which compiles the WHOLE chain because it reads
 * fields by kind (§V6/§V12).
 *
 * Since 1.17.0 this is the chain's LEADING RUN of `refs` steps, stopping at the first step
 * of any other type. Two properties come out of that: a legacy `src:ref|srcTermIn:x`
 * still yields the ref step alone (unchanged), and a multi-step relationship chain
 * (`refs,a;refs,b`) now steps BOTH instead of one. Stopping — rather than filtering — is
 * what keeps the result honest: steps after a dropped term/rows step would run against
 * the wrong entity.
 *
 * MOVED from base-shared.php in 1.17.0 (5h).
 *
 * @since 1.14.0
 * @since 1.17.0 Leading run of ref steps off the compiled chain; moved here from base-shared.php.
 * @since 1.18.0 $ignore_limits threaded to the compile (collapsing tags, ADR 0007).
 * @param array $options       Tag options (src, ref).
 * @param bool  $ignore_limits Compile every step WITHOUT its limit (collapsing tags).
 * @return array[] Zero or more consecutive leading ref steps.
 */
if ( ! function_exists( 'bws_wrapper_ref_steps' ) ) {
function bws_wrapper_ref_steps( array $options, bool $ignore_limits = false ): array {
	$leading = array();
	foreach ( bws_field_values_assemble_steps( $options, $ignore_limits ) as $step ) {
		if ( 'refs' !== ( $step['type'] ?? '' ) ) {
			break;
		}
		$leading[] = $step;
	}
	return $leading;
}
}
