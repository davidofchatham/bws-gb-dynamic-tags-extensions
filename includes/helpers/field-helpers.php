<?php
/**
 * Field/meta extraction helper functions.
 *
 * Shared functions for ACF/meta field reading, loop-row context resolution,
 * ACF object_id resolution, and related-post data extraction. All field
 * reads route through GenerateBlocks_Meta_Handler when available.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a field key is refused by GenerateBlocks' dynamic-tag security gate.
 *
 * THE single authority on the DISALLOWED_KEYS check. The field readers
 * (bws_read_field, bws_read_term_field) refuse these keys, and field discovery
 * (bws_field_discovery_filter_disallowed) filters the offered list through the
 * same predicate, so "offered ⟺ resolvable" (SPEC V6) cannot drift: the offer
 * side and the resolve side share ONE definition of "disallowed".
 *
 * NOTE: this blocks only the explicit DISALLOWED_KEYS credential/auth list.
 * General `_`-prefixed protected meta is allowed on the frontend (matches GB
 * Meta_Handler), so e.g. Pie Calendar `_piecal_*` keys stay readable/offerable.
 *
 * When the GB security class is absent, nothing is blocked (returns false).
 *
 * @since 1.13.0
 * @param string $key Meta/ACF resolution key.
 * @return bool True if the key is on the DISALLOWED_KEYS list.
 */
if ( ! function_exists( 'bws_field_key_disallowed' ) ) {
function bws_field_key_disallowed( string $key ): bool {
	return class_exists( 'GenerateBlocks_Dynamic_Tag_Security' )
		&& in_array( $key, GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS, true );
}
}

/**
 * Canonical gated read of a single site option value (src:site key-mode).
 *
 * THE one reader for every src:site option value. Both the value resolver
 * (bws_site_resolve_value key-mode branch, base-tags.php) and the link path
 * (bws_resolve_link_url, entity_type 'site', link-helpers.php) route through
 * here so the two reads cannot diverge (V2 — the site value read and the site
 * linkTo:key read MUST agree). It enforces the allowlist gate (ADR 0001), then
 * delegates to GenerateBlocks_Meta_Handler::get_option, which supplies dot-path
 * traversal (ACF group subfields, e.g. organization_social.facebook) AND the
 * ACF get_field filter. Raw get_option() reaches neither — never read a site
 * option without going through this function.
 *
 * Lives here (field-helpers, loaded before link-helpers and base-tags) so the
 * shared reader is defined ahead of every caller; bws_site_allowlist_ok is
 * resolved at call time (base-tags), so the load-order gap is harmless.
 *
 * @invariant (SPEC V2, single-reader corollary, B4) The two wp_options site
 * reads — key-mode value read (bws_site_resolve_value) and linkTo:key
 * (bws_resolve_link_url, entity_type 'site') — MUST both route through THIS
 * function. Hand-rolling a second get_option() for either path silently
 * diverges on ACF-group subfields (organization_social.facebook): dot-path
 * traversal + the ACF get_field filter live in Meta_Handler::get_option, not
 * in raw get_option. The datetime path reads ACF FIELDS via
 * get_field($key,'option') — a different datum, separate reader, same gate.
 *
 * @since 1.9.0
 * @param string $key Option key (may contain a dot-path for wp_options arrays).
 * @return string Resolved string value, or '' on disallow / miss / non-string.
 */
if ( ! function_exists( 'bws_site_read_option' ) ) {
function bws_site_read_option( string $key ): string {
	if ( '' === $key
		|| ! function_exists( 'bws_site_allowlist_ok' )
		|| ! bws_site_allowlist_ok( $key )
		|| ! class_exists( 'GenerateBlocks_Meta_Handler' )
	) {
		return '';
	}
	$value = GenerateBlocks_Meta_Handler::get_option( $key, true, '' );
	return is_string( $value ) ? $value : '';
}
}

/**
 * Get related posts from ACF relationship or post object field.
 *
 * @since 1.0.0
 * @param int|string $entity_id ACF-compatible entity ID: post ID (int) or term entity string ("term_N").
 * @param string $field_key ACF field key.
 * @return array Array of related posts.
 */
if ( ! function_exists( 'bws_get_related_posts_data' ) ) {
function bws_get_related_posts_data( $post_id, $field_key ) {
	if ( ! function_exists( 'get_field' ) || ! function_exists( 'get_field_object' ) ) {
		return array();
	}

	// Validate field type for security.
	$field_object = get_field_object( $field_key, $post_id );

	if ( ! $field_object || ! in_array( $field_object['type'], array( 'relationship', 'post_object' ), true ) ) {
		return array();
	}

	$related_posts = get_field( $field_key, $post_id );

	if ( ! $related_posts ) {
		return array();
	}

	if ( ! is_array( $related_posts ) ) {
		$related_posts = array( $related_posts );
	}

	return $related_posts;
}
}

/**
 * Extract post ID from various ACF return formats.
 *
 * Handles ACF single-post return formats (WP_Post object, numeric ID, assoc
 * array with 'ID' key) and list return formats (array of any of the above,
 * such as Relationship/post_object subfield with no max_size limit). For
 * lists, returns the first entry's ID — caller is responsible for iteration
 * if multiple are needed.
 *
 * @since 1.0.0
 * @param mixed $post_data Post data from ACF.
 * @return int|false Post ID or false.
 */
if ( ! function_exists( 'bws_extract_post_id' ) ) {
function bws_extract_post_id( $post_data ) {
	if ( $post_data instanceof WP_Post ) {
		return $post_data->ID;
	}

	if ( is_object( $post_data ) && isset( $post_data->ID ) ) {
		return $post_data->ID;
	}

	if ( is_numeric( $post_data ) ) {
		return intval( $post_data );
	}

	if ( is_array( $post_data ) ) {
		if ( isset( $post_data['ID'] ) ) {
			return $post_data['ID'];
		}
		// List-of-posts (Relationship/post_object subfield): take first entry.
		if ( ! empty( $post_data ) ) {
			return bws_extract_post_id( reset( $post_data ) );
		}
	}

	return false;
}
}

/**
 * Resolve loop-row context from a block instance.
 *
 * Inspects $instance->context for GB Pro post_meta loop data and classifies the
 * row into one of three states.
 *
 * THE ITEM SHAPES ARE AN ASSUMPTION, NOT A CHECK (#123). array | WP_Post | numeric
 * covers everything GB itself loops over, and anything else reports NOT IN A LOOP
 * rather than "in a loop I cannot resolve" - so a caller falls through to the ambient
 * entity and renders a plausible value from an entity the wire never named ([I15]).
 * An extension that loops over TERMS makes that reachable; a term id would be worse
 * than a WP_Term, since is_numeric() passes and the id is read as a POST's. Result cached on $instance->context['bws/loopItemPostId']
 * so callers paying for `get_post()` only do so once per block render.
 *
 * Returned shape:
 *   [
 *     'loop_item'   => mixed   // raw row (WP_Post|array|int|null when not in a loop)
 *     'row_post_id' => int|false // resolved post ID for Mode 2a; false for Mode 2b/none
 *     'in_loop'     => bool    // true when GB Pro loop row context detected
 *   ]
 *
 * @since 1.7.0
 * @param mixed $instance Block instance (WP_Block) or anything else.
 * @return array
 */
if ( ! function_exists( 'bws_get_loop_row_context' ) ) {
function bws_get_loop_row_context( $instance ): array {
	$out = array(
		'loop_item'   => null,
		'row_post_id' => false,
		'in_loop'     => false,
	);

	if ( ! is_object( $instance ) || ! isset( $instance->context ) || ! is_array( $instance->context ) ) {
		return $out;
	}

	$raw_item = $instance->context['generateblocks/loopItem'] ?? null;
	$has_item = is_array( $raw_item )
		|| $raw_item instanceof WP_Post
		|| is_numeric( $raw_item );
	if ( ! $has_item ) {
		return $out;
	}

	$out['in_loop']   = true;
	$out['loop_item'] = $raw_item;

	if ( ! isset( $instance->context['bws/loopItemPostId'] ) ) {
		// Non-array rows (WP_Post / numeric) carry post identity directly under any
		// queryType — covers standard 'WP_Query' post loops and post-meta relationship
		// loops that GB Pro materializes into WP_Post instances. Array rows resolve only
		// under 'post_meta' AND with an explicit 'ID' key, so flat repeater rows
		// (Mode 2b) don't accidentally extract a post id via list-of-posts fallback.
		$query_type = $instance->context['generateblocks/queryType'] ?? '';
		$candidate  = 0;
		if ( ! is_array( $raw_item ) ) {
			$candidate = bws_extract_post_id( $raw_item );
		} elseif ( 'post_meta' === $query_type && isset( $raw_item['ID'] ) ) {
			$candidate = (int) $raw_item['ID'];
		}
		$row_post_id = ( $candidate && get_post( $candidate ) ) ? $candidate : false;
		$instance->context['bws/loopItemPostId'] = $row_post_id !== false ? $row_post_id : 0;
	}

	$cached              = (int) $instance->context['bws/loopItemPostId'];
	$out['row_post_id']  = $cached > 0 ? $cached : false;

	return $out;
}
}

/**
 * Read a meta/ACF field for a post-like context.
 *
 * Routes through GenerateBlocks_Meta_Handler so GB Pro's ACF integration fires
 * via the generateblocks_get_meta_pre_value filter. Falls back to raw WP meta
 * functions if Meta_Handler unavailable.
 *
 * Branching order:
 *  1. $post_id > 0 (explicit caller-resolved target)  → read post meta on that id
 *  2. Mode 2a (loop row resolves to post, no explicit id) → read post meta on row post
 *  3. Mode 2b (flat repeater row, no explicit id)         → read $loop_item[$key] directly
 *  4. Term archive (non-REST, no explicit id)             → read term meta on queried term
 *  5. null
 *
 * INVARIANT: An explicit `$post_id` passed by the caller always wins over loop-row
 * inference. Try-loop `src:ref` slots resolve a target post via `bws_resolve_post_by_source()`
 * and pass that id here; if loop-row inference were allowed to override it, the slot would
 * silently read from the page entity instead of the resolved ref target — breaking
 * fall-through across slots inside any GB query loop. (Bugfix v1.7.1.)
 *
 * @since 1.7.0
 * @param string         $key         Meta/ACF field key.
 * @param mixed          $instance    Block instance (WP_Block) — used for context cache.
 * @param int|false      $post_id     Resolved post ID, or false.
 * @param bool           $single_only When true (default) coerce arrays/objects to ''. Pass false to preserve raw ACF arrays (e.g. image fields).
 * @return mixed Field value, '' on miss from Meta_Handler, or null when no context resolved.
 */
if ( ! function_exists( 'bws_read_field' ) ) {
function bws_read_field( string $key, $instance, $post_id, bool $single_only = true ) {
	// Security guard — block credential/internal-auth fields explicitly.
	// Underscore-prefixed protected meta is allowed on frontend (matches GB Meta_Handler),
	// since plugins like Pie Calendar legitimately store data in _-prefixed keys.
	if ( bws_field_key_disallowed( $key ) ) {
		return null;
	}

	// Mode 2 subtype detection.
	// Explicit $post_id (e.g. resolved via src:relationship step) always wins — caller has already
	// done entity resolution and the row entity is irrelevant to that target.
	$has_explicit_post_id = ( is_int( $post_id ) && $post_id > 0 )
		|| ( is_numeric( $post_id ) && (int) $post_id > 0 );

	$loop = bws_get_loop_row_context( $instance );
	if ( $loop['in_loop'] && ! $has_explicit_post_id ) {
		// Mode 2a — row resolves to a post entity.
		if ( $loop['row_post_id'] ) {
			return bws_meta_handler_read( (int) $loop['row_post_id'], $key, $single_only, 'get_post_meta' );
		}
		// Mode 2b — flat repeater row; read directly from row data.
		if ( is_array( $loop['loop_item'] ) ) {
			return $loop['loop_item'][ $key ] ?? null;
		}
	}

	// Normal post context.
	if ( is_int( $post_id ) && $post_id > 0 ) {
		return bws_meta_handler_read( $post_id, $key, $single_only, 'get_post_meta' );
	}
	if ( is_numeric( $post_id ) && (int) $post_id > 0 ) {
		return bws_meta_handler_read( (int) $post_id, $key, $single_only, 'get_post_meta' );
	}

	// Term archive fallback — non-REST only.
	if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			return bws_meta_handler_read( (int) $queried->term_id, $key, $single_only, 'get_term_meta' );
		}
	}

	// DT-1: src:site datetime — ACF options-page field value read. The 'option'
	// sentinel reaches here only from bws_datetime_single_core('option', ...) (site
	// datetime path); all other callers pass int/loop ids and never hit this branch,
	// so behavior is unchanged for them. Gated through the SAME allowlist as use:option
	// and site linkTo:key (V2). ACF field keys are flat — no dot-path split.
	// See docs/adr/0001-site-option-read-allowlist.md.
	if ( 'option' === $post_id && function_exists( 'get_field' ) ) {
		if ( function_exists( 'bws_site_allowlist_ok' ) && ! bws_site_allowlist_ok( $key ) ) {
			return '';
		}
		return get_field( $key, 'option' );
	}

	return null;
}
}

/**
 * Read a meta/ACF field for a term context.
 *
 * Routes through GenerateBlocks_Meta_Handler. GB Pro builds the "term_{$id}"
 * ACF key internally — no $taxonomy param needed.
 *
 * @since 1.7.0
 * @param string $key         Meta/ACF field key.
 * @param int    $term_id     Term ID.
 * @param bool   $single_only When true (default) coerce arrays/objects to ''. Pass false to preserve raw ACF arrays.
 * @return mixed Field value, '' on miss, or null if blocked by security guard.
 */
if ( ! function_exists( 'bws_read_term_field' ) ) {
function bws_read_term_field( string $key, int $term_id, bool $single_only = true ) {
	if ( bws_field_key_disallowed( $key ) ) {
		return null;
	}
	return bws_meta_handler_read( $term_id, $key, $single_only, 'get_term_meta' );
}
}

// bws_field_values_assemble_steps() — the step-assembly half of this seam — MOVED to
// includes/helpers/slot-fold-compile.php in 1.17.0 (5h). It is now a thin adapter over
// the chain COMPILE, so the flat `src`/`ref`/`srcTermIn` reading and the folded wire's
// chain produce steps through one code path (and a multi-step chain resolves instead of
// stopping at one relationship step plus one term step). #44's compound order lives there too.

/**
 * Read one resolved source's field value at L2, dispatched by KIND (SPEC §V12).
 *
 * The factory owns source-SELECTION; this owns the READ. site → option read;
 * term → term meta; post → post meta with an EXPLICIT id (triggers the v1.7.1
 * explicit-wins rule in bws_read_field, bypassing ITS own loop/term inference so
 * the factory's resolved source is authoritative — no double resolution).
 * meta_row → the row's own key. user → plain user meta (FW-48 seam half).
 * Returns '' on miss (caller drops empties).
 *
 * @since 1.14.0
 * @since 1.16.0 user kind (FW-48 seam half; unreachable until the post→author step).
 * @param array  $source   One resolved source ({kind,id}|{kind:site}|{kind:meta_row,row}).
 * @param string $key      Field key.
 * @param object $instance GB instance (bws_read_field context cache).
 * @return string Raw value, '' on miss.
 */
if ( ! function_exists( 'bws_read_resolved_source' ) ) {
function bws_read_resolved_source( array $source, string $key, $instance ): string {
	$kind = $source['kind'] ?? '';

	switch ( $kind ) {
		case 'site':
			$value = function_exists( 'bws_site_read_option' ) ? bws_site_read_option( $key ) : '';
			return is_scalar( $value ) ? (string) $value : '';

		case 'term':
			$raw = bws_read_term_field( $key, (int) ( $source['id'] ?? 0 ) );
			return ( is_scalar( $raw ) && '' !== (string) $raw ) ? (string) $raw : '';

		case 'meta_row':
			$row = $source['row'] ?? array();
			$raw = is_array( $row ) ? ( $row[ $key ] ?? '' ) : '';
			return ( is_scalar( $raw ) && '' !== (string) $raw ) ? (string) $raw : '';

		case 'user':
			// FW-48 (seam half): plain user-meta read, NOT the analog reader
			// (bws_base_user_analog_read lives in base-shared, loaded AFTER this
			// file — and it reads analogs, not meta; different concern). Currently
			// unreachable at runtime — no traversal step or factory path yields a
			// user-kind source into the seam until the post→author step (FW-48
			// proper) lands — but a user-less kind switch would ship a hole the
			// ABSORB seam converged onto and force re-opening this function.
			$user_id = (int) ( $source['id'] ?? 0 );
			if ( $user_id <= 0 || bws_field_key_disallowed( $key ) ) {
				return '';
			}
			$raw = get_user_meta( $user_id, $key, true );
			return ( is_scalar( $raw ) && '' !== (string) $raw ) ? (string) $raw : '';

		case 'post':
			// Explicit id → v1.7.1 explicit-wins → bypasses bws_read_field's own
			// loop/term inference (SPEC §V12). Factory already resolved the row.
			// GUARD id 0 (SPEC §V18): a {kind:post,id:0} means the factory found NO
			// current post. bws_read_field treats a passed 0 as NOT explicit (guard
			// requires >0), so it would re-run its own loop/term inference and could
			// read a context the factory rejected (the two-layers-fight edge, B7).
			// Reading a field off post 0 is meaningless → return '' directly.
			$post_source_id = (int) ( $source['id'] ?? 0 );
			if ( $post_source_id <= 0 ) {
				return '';
			}
			$raw = bws_read_field( $key, $instance, $post_source_id );
			return ( is_scalar( $raw ) && '' !== (string) $raw ) ? (string) $raw : '';
	}

	return '';
}
}

/**
 * Map a resolved source to its link identity, or null when it has none (PURE).
 *
 * Link identity is the {kind,id} pair bws_resolve_link_url consumes
 * (post|term|user|site). Per CONTEXT.md I12, link-wrappability is a property
 * of the VALUE a source produces, not of the source kind — a source with no
 * link identity (meta_row, future query-context kinds) maps to null, NEVER a
 * sentinel id. site maps to sentinel id 1, matching the existing site
 * link-wrap call sites (bws_wrap_with_link(..., 1, 'site')) — that sentinel
 * is bws_resolve_link_url's own site convention, not an absence marker.
 *
 * @since 1.16.0
 * @param array $source One resolved source ({kind,id}|{kind:site}|{kind:meta_row,row}).
 * @return array{kind:string, id:int}|null Link identity, or null.
 */
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

/**
 * THE single interpreter of a `limit` option value (list mode).
 *
 * One rule, three call sites — the seam (bws_resolve_field_values), the shared
 * list fold (bws_collect_value_list), and try_ slot dispatch
 * (class-tag-template-registry.php). Each carried its own inline copy of
 * `max( 1, (int) $limit )` until 1.17.0; extracting them here is a deliberate
 * PREREQUISITE for changing what `0` means, so that "unset", "0" and "garbage"
 * cannot drift apart between the three paths mid-change.
 *
 * Semantics:
 *   - non-numeric (null, '', 'abc') ⇒ treated as UNSET ⇒ 1 (the default);
 *   - numeric <= 0 (`0`, `-1`, `-3`) ⇒ 0, meaning UNLIMITED;
 *   - numeric >= 1 ⇒ (int) $raw, truncating ('2.7' ⇒ 2).
 *
 * UNLIMITED is encoded as 0. Both `0` and `-1` PARSE as unlimited because both
 * conventions ship in the wild (GB's Posts Per Page uses -1; WP's core Query Loop
 * blocks -1 and documents 0), but 0 is the value this plugin EMITS — parse
 * tolerant, emit exclusive. The number controls deliberately carry no `min`: a
 * control that fights a hand-typed -1 works against ADR 0004, and tolerance
 * already covers it.
 *
 * A pre-1.17.0 `limit:0` / `limit:-1` on saved wire therefore starts fanning out
 * where it used to render one value. That is intentional: the old behavior was a
 * `max( 1, … )` CLAMP silently discarding a written value, not a designed
 * semantic — nobody typing 0 meant 1. Honor the written value.
 *
 * The is_numeric() gate is what keeps the new rule safe: (int)'abc' === 0, so
 * without it a typo would silently fan out a whole relationship. Garbage must
 * resolve to the DEFAULT, never to "no limit".
 *
 * CALLERS: 0 means "no slice". Slice with `array_slice( $x, 0, $limit ?: null )`
 * and guard any early-break on `$limit &&` — a bare `count >= $limit` breaks
 * immediately at 0.
 *
 * Callers pass the RAW option value, not the options array — try_ slot dispatch
 * reads `limit` off the chain options while the other two read it off the tag
 * options, and the raw-value signature serves both without a key convention.
 *
 * @since 1.17.0
 * @param mixed $raw     Raw `limit` option value (unset/null, string or int).
 * @param int   $default Effective limit when $raw states nothing. REQUIRED — see
 *                       bws_limit_default(); omitting it is an ArgumentCountError
 *                       by design, not a fall back to the legacy 1.
 * @return int Effective limit: >= 1, or 0 for UNLIMITED.
 */
if ( ! function_exists( 'bws_clamp_limit' ) ) {
function bws_clamp_limit( $raw, int $default ): int {
	if ( ! is_numeric( $raw ) ) {
		return $default;
	}
	$n = (int) $raw;
	return $n > 0 ? $n : 0;
}
}

/**
 * The tag-level `limit` a tag gets when its wire states none — decided by SPELLING.
 *
 * @invariant THE FLAT SOURCE SPELLING SELECTS THE OLD DEFAULT. Flat wire
 * (`src:ref|ref:x`, `srcTermIn:x`, a bare tag) bounds its resolved-source list at
 * ONE, as it always has. Chain wire (`src:refs,x`) is unlimited. This one rule is
 * the entire compatibility mechanism for base-tag source chains, and it is chosen
 * precisely because it works on wire NO MIGRATION CAN REACH — a draft nobody opens,
 * a block widget the content scanner never sees, a tag stored inside an ACF field.
 * An unmigrated tag gets its default from its own spelling, wherever it lives.
 *
 * Why the default had to become spelling-dependent rather than simply flipping:
 * `bws_clamp_limit`'s default-1 is the single-read defect the plural source model
 * already names (CONTEXT.md §Language: `ref` and `srcTermIn` are PLURAL), sitting
 * at the tag-level position instead of the per-step one. It only ever bites on a
 * plural source — on a singular one the slice is a no-op. But ~110 authored
 * instances across the surveyed databases depend on it, with no author present, so
 * it cannot just be flipped. Naming the spelling that is entitled to it keeps every
 * stored tag rendering exactly as before while new wire gets the honest default.
 *
 * Two costs, both accepted: the same conceptual source is bounded differently by spelling
 * (an ADR-0004 readability cost, paid to avoid touching a stored row), and the
 * link gate is COUNT-BASED, so link-wrapping differs by spelling too — on new wire
 * only, which is why the limit-default matrix needs rows per SPELLING and not just
 * per `limit` value.
 *
 * Resolved ONCE, from the options. No call site is new-or-old — all of them serve
 * both eras — so "new sites pass 0, old sites pass 1" has no referent. A call site
 * growing its own spelling test would re-inline half the rule bws_clamp_limit was
 * extracted to own.
 *
 * @since 1.17.0
 * @param array $options Tag options (reads `src`/`source` only).
 * @return int 0 (unlimited) for chain wire, 1 for flat wire.
 */
if ( ! function_exists( 'bws_limit_default' ) ) {
function bws_limit_default( array $options ): int {
	$src = trim( (string) ( $options['src'] ?? $options['source'] ?? '' ) );
	return ( function_exists( 'bws_fold_chain_is_wire' ) && bws_fold_chain_is_wire( $src ) ) ? 0 : 1;
}
}

/**
 * Shared L1/L2 source-resolution pipeline: resolve a (source + key) read target
 * to a list of raw candidate field-value strings.
 *
 * The single source-resolution seam (CONTEXT.md §L1/L2/L3, ADR 0002) the
 * value-list tags share. Since 1.14.0 (traversal pipeline Phase 1) the L1 half
 * delegates to the source factory + step engine:
 *   - L1 resolve source: bws_resolve_base_source (ambient/explicit/loop/site,
 *     SPEC §V1) → base resolved source.
 *   - L1 traversal: bws_field_values_assemble_steps (src:ref → ref step,
 *     srcTermIn → term-step step; both compound as [ref, srcTermIn] when set, #44)
 *     run through bws_run_traversal — ref now FANS OUT to all targets (SPEC §V6
 *     plural; no first-only collapse), and a term archive bases ref on the
 *     ambient term (SPEC §V11).
 *   - L2 read: per resolved source by KIND (bws_read_resolved_source, SPEC §V12).
 *   - list mode: slice the resolved-source list to `limit` (list mode originates
 *     at the plural source, CONTEXT.md §Target cardinality); `sep` join stays in
 *     the caller's L3.
 *
 * Signature + string[] return are FROZEN (SPEC §V3) — every existing caller
 * (email/phone × 2) renders identically except the limit>1 ref-plural change.
 * The optional $links out-param (FW-49) is ADDITIVE: existing callers omit it
 * and see zero change; callers that pass a variable receive one link identity
 * per RETURNED value (parallel arrays — $links[i] belongs to the returned
 * value [i]), each bws_source_link_identity({kind,id})|null per CONTEXT.md I12.
 * Returns RAW, UNVALIDATED strings — per-tag validation + L3 composition stay in
 * each tag's callback. The resolver is composition-blind.
 *
 * @since 1.11.0
 * @since 1.14.0 Delegates L1 to the source factory + traversal engine; ref plural.
 * @since 1.16.0 Optional $links out-param carries per-value link identity (FW-49).
 * @param array      $options  Tag options (key, src, ref, srcTermIn, limit, …).
 * @param object     $instance GB tag instance.
 * @param array|null $links    Optional out-param: filled with one link identity
 *                             ({kind,id}|null) per returned value, same order.
 * @return string[] Raw candidate value strings (unvalidated, empties dropped).
 */
if ( ! function_exists( 'bws_resolve_field_values' ) ) {
function bws_resolve_field_values( array $options, $instance, ?array &$links = null ): array {
	$key = sanitize_text_field( $options['key'] ?? '' );
	if ( '' === $key ) {
		return array();
	}

	// src:site keeps its dot-path affordance (ACF options can be dotted); other
	// sources require a valid flat meta key. Gate BEFORE resolution to preserve
	// the historical early-return on invalid non-site keys.
	// Read the source ROOT, not the raw value: a depth-0 chain (`src:site;rows,rows`)
	// still ROOTS at the site store, so its dot-path keys must survive the gate below.
	$is_site = 'site' === ( function_exists( 'bws_fold_src_root_token' )
		? bws_fold_src_root_token( $options )
		: ( $options['src'] ?? '' ) );
	if ( ! $is_site
		&& function_exists( 'bws_is_valid_meta_key' )
		&& ! bws_is_valid_meta_key( $key ) ) {
		return array();
	}

	// L1 — resolve the base source, then run assembled traversal steps.
	$base    = function_exists( 'bws_resolve_base_source' )
		? bws_resolve_base_source( $options, $instance )
		: array( 'kind' => 'post', 'id' => 0 );
	$steps   = bws_field_values_assemble_steps( $options );
	$sources = function_exists( 'bws_run_traversal' )
		? bws_run_traversal( array( $base ), $steps )
		: array( $base );

	// list mode — slice plural source list to limit. The DEFAULT is selected by the
	// source SPELLING (bws_limit_default): flat wire bounds at 1, chain wire does not.
	$limit   = bws_clamp_limit( $options['limit'] ?? null, bws_limit_default( $options ) );
	$sources = array_slice( $sources, 0, $limit ?: null );

	// L2 — read each resolved source by kind; drop empties. Link identity is
	// carried out per KEPT value (FW-49) instead of being discarded with the
	// source — $links stays parallel to the returned strings.
	$out   = array();
	$links = array();
	foreach ( $sources as $source ) {
		$value = bws_read_resolved_source( $source, $key, $instance );
		if ( '' !== $value ) {
			$out[]   = $value;
			$links[] = bws_source_link_identity( $source );
		}
	}
	return $out;
}
}

/**
 * Fold a list of read targets into a joined value list carrying link identity (L3).
 *
 * THE shared combining fold for list-mode output (FW-49 convergence). One
 * implementation replaces the hand-written slice/suppress/render/drop/join
 * loops in base text/title (srcTermIn + src:ref branches) and datetime
 * single/range (bws_datetime_collect_list). The seam
 * (bws_resolve_field_values) does NOT route through this — its string[]
 * return is frozen (SPEC §V3); it only carries link identity out per value.
 *
 * Owns, in order:
 *  1. slice to `limit` (bws_clamp_limit — default 1, `0` = unlimited);
 *  2. per-item fallback suppression — $render receives $options with
 *     'fallback' unset, so the fallback fires ONCE in the caller on all-empty
 *     output, never per item (GH #51: a per-item fallback would pollute the
 *     list AND satisfy the single-result link gate as though it were a value);
 *  3. render each item ('' or empty 'value' drops silently);
 *  4. per-value link capture;
 *  5. the single-result link gate (top-level `link` = values[0]['link'] iff
 *     count is exactly 1);
 *  6. `sep` join (default ', ').
 *
 * @invariant (CONTEXT.md I12) Link-wrappability is a property of the VALUE,
 * not of the source kind. Each collected value carries `link` — the {kind,id}
 * pair bws_resolve_link_url consumes (post|term|user|site) — or null. "No link
 * identity" is null, NEVER a sentinel id; kinds with no link identity
 * (meta_row today, the #19 query-context kinds as they land) are normal, not
 * exceptional — they collect fine and simply cannot be link-wrapped. The
 * top-level single-result gate is a JOIN constraint, not a linking one: a
 * multi-value composite string is unwrappable as ONE link, while the
 * per-value links remain available in `values` for future per-item wrapping.
 *
 * The fold never coerces or inspects an item — $render owns the item→value
 * read entirely. Callers keep their raw $options for linkTo/linkKey/newTab
 * and the preview label; only the fold inputs route through here. Datetime
 * callers pass the NORMALIZED ($mapped) options: bws_normalize_datetime_options
 * is purely additive ($mapped ⊇ $options), so one array serves both the
 * slice keys and $render's per-item options.
 *
 * @since 1.16.0
 * @param array    $items   Read targets in document order (terms, post ids, …).
 * @param callable $render  fn( $item, array $item_opts ): array{value:string, link:?array}|string
 *                          Return '' to skip the item. A plain non-empty string
 *                          is accepted as a value with no link identity.
 *                          `link` is array{kind:string, id:int} or null.
 * @param array    $options Tag options (limit / sep / fallback).
 * @return array{
 *   value:  string,
 *   values: array<int, array{value:string, link:?array}>,
 *   count:  int,
 *   link:   ?array,
 * }
 */
if ( ! function_exists( 'bws_collect_value_list' ) ) {
function bws_collect_value_list( array $items, callable $render, array $options ): array {
	$limit = bws_clamp_limit( $options['limit'] ?? null, bws_limit_default( $options ) );
	$sep   = $options['sep'] ?? ', ';

	$item_opts = $options;
	unset( $item_opts['fallback'] );

	$values = array();
	foreach ( array_slice( $items, 0, $limit ?: null ) as $item ) {
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

/**
 * The bounded source READER: read the first $n sources, in order, and return what
 * they render.
 *
 * @invariant SELECTION IS FIELD-INDEPENDENT BY DEFAULT — this PHPDoc is the AXIS
 * OWNER for that rule (CLAUDE.md §Documentation ownership; the 2026-08-21
 * determinism reversal, ADR 0007 §Why the read-based axis was reversed). With no
 * $populated predicate, the bound counts SOURCES READ: the first $n sources are read
 * (0 or less = all), each source consumes its slot whatever its read returns, and
 * only EMPTY VALUES ('' / false / null) are dropped from the RETURN — never from
 * the count. So `limit:3` can print two, a collapsing tag at $n = 1 outputs its
 * first source's read even when that read is empty, and adjacent tags on the same
 * source path always read the same entities. Which sources are ELIGIBLE at all is
 * the engine gate's axis (bws_source_gate, traversal-pipeline.php — resolvable ×
 * exists × visible), decided before this function ever sees them.
 *
 * THE $populated PREDICATE IS A DORMANT SEAM (FW-88), called by nothing shipped.
 * When supplied, the walk becomes collect-then-slice: a source whose reads all
 * fail the predicate is skipped WITHOUT consuming a slot, and the walk stops as
 * soon as $n surviving values exist (the reader is not called again). That is the
 * pre-reversal "search past empty fields" behaviour, preserved for a possible
 * tag-level OPT-IN — bws_value_is_populated() is the predicate it would
 * wire. It must never become a default: the instability it reintroduces is the
 * defect the reversal removed.
 *
 * PURE, and provenance-blind BY CONTRACT. Reader and predicate are injected and
 * no WP symbol is named, which is what lets tools/test/read-bounded-sources-test.php
 * require this real file rather than copy the rule. Extracted FROM the try_ emit
 * loop; the collapsing base tags (content/permalink/image, takes_first_usable)
 * consume it at $n = 1 and try_ consumes it with the slot's own bound.
 *
 * @since 1.18.0
 * @since 1.18.0 $populated — the dormant opt-in predicate (default null = none).
 * @param array         $sources Candidates in document order (resolved sources,
 *                               entity ids — whatever $read consumes; opaque here).
 * @param callable      $read    fn( $source ): string|array — one candidate's read.
 *                               May return one value or a list of finished values.
 * @param int           $n       Sources to read (no predicate) / surviving values
 *                               to stop at (with predicate); 0 or less = unbounded.
 * @param callable|null $populated DORMANT: fn( $value ): bool — keeps a value and
 *                               lets its source consume a slot. Null = no skipping.
 * @return array The non-empty reads, in encounter order.
 */
if ( ! function_exists( 'bws_read_bounded_sources' ) ) {
function bws_read_bounded_sources( array $sources, callable $read, int $n, ?callable $populated = null ): array {
	$out = array();
	if ( null === $populated ) {
		// Default: the bound counts SOURCES READ. Slice first, read what remains,
		// drop empty values from the return only.
		$slice = ( $n > 0 ) ? array_slice( $sources, 0, $n ) : $sources;
		foreach ( $slice as $source ) {
			$reads = $read( $source );
			foreach ( ( is_array( $reads ) ? $reads : array( $reads ) ) as $value ) {
				if ( bws_value_is_populated( $value ) ) {
					$out[] = $value;
				}
			}
		}
		return $out;
	}
	// Dormant opt-in path: collect-then-slice, skipping sources whose reads fail
	// the predicate — the pre-reversal search behaviour (FW-88).
	foreach ( $sources as $source ) {
		if ( $n > 0 && count( $out ) >= $n ) {
			break; // Enough surviving values — the reader is not called again.
		}
		$reads = $read( $source );
		foreach ( ( is_array( $reads ) ? $reads : array( $reads ) ) as $value ) {
			if ( $populated( $value ) ) {
				$out[] = $value;
			}
		}
	}
	// One source may return several items, so the tail can overshoot $n.
	return ( $n > 0 ) ? array_slice( $out, 0, $n ) : $out;
}
}

/**
 * Does this value render anything? The one emptiness test.
 *
 * TWO ROLES, deliberately one function. LIVE: the default path of
 * bws_read_bounded_sources() calls it to drop empty values from its return.
 * DORMANT (FW-88): it is also the predicate a future tag-level "search past
 * empty fields" opt-in would pass as that function's $populated argument —
 * nothing shipped passes it, and it must never become the default.
 *
 * The dormant ROLE is what tools/test/read-bounded-sources-test.php pins, so the
 * behaviour cannot rot while unwired; the test is the donor emit loop's
 * normalizer test, verbatim.
 *
 * @since 1.18.0
 * @param mixed $value One value from a source's read.
 * @return bool True when the value would render something.
 */
if ( ! function_exists( 'bws_value_is_populated' ) ) {
function bws_value_is_populated( $value ): bool {
	return '' !== $value && false !== $value && null !== $value;
}
}

/**
 * Internal: route a meta read through GenerateBlocks_Meta_Handler with raw WP fallback.
 *
 * @since 1.7.0
 * @param int    $object_id   Post or term ID.
 * @param string $key         Meta key.
 * @param bool   $single_only When false, return raw (preserves ACF arrays).
 * @param string $wp_fn       Fallback WP function: get_post_meta or get_term_meta.
 * @return mixed
 */
if ( ! function_exists( 'bws_meta_handler_read' ) ) {
function bws_meta_handler_read( int $object_id, string $key, bool $single_only, string $wp_fn ) {
	if ( class_exists( 'GenerateBlocks_Meta_Handler' ) ) {
		$value = GenerateBlocks_Meta_Handler::get_meta( $object_id, $key, $single_only, $wp_fn );
	} else {
		$value = $wp_fn( $object_id, $key, true );
	}
	if ( $single_only && ( is_array( $value ) || is_object( $value ) ) ) {
		return '';
	}
	return $value;
}
}

/**
 * Resolve the ACF object_id for field-config lookups (`get_field_object`, `get_field`).
 *
 * Some ACF-aware code paths (notably datetime return_format detection in
 * bws_parse_combined_date_time()) need an object id to fetch field metadata even
 * when the caller has no resolved row entity — e.g. flat ACF repeater rows
 * (Mode 2b) under GB Pro's TYPE_OPTION or TYPE_POST_META query loops. This
 * helper consolidates the resolution rules:
 *
 *  1. Explicit caller-resolved $post_id wins (int > 0, numeric string > 0,
 *     non-empty string like ACF term object_id "term_5" or "option").
 *  2. GB Pro TYPE_OPTION repeater rows → 'option' (ACF site-options namespace).
 *  3. GB Pro TYPE_POST_META repeater rows → outer page's postId from context,
 *     since ACF repeater subfields are registered against the parent post's
 *     field group.
 *  4. Otherwise 0 (callers should treat as "no ACF context available" and
 *     fall through to format-agnostic parsing).
 *
 * INVARIANT: tags that read ACF field-config metadata in loop contexts MUST
 * use this resolver rather than passing a bare false/0 to get_field_object();
 * doing so causes datetime return_format misses on TYPE_OPTION and
 * TYPE_POST_META repeater rows (issue #22, bugfix v1.7.2).
 *
 * @since 1.7.2
 * @param mixed     $instance Block instance (WP_Block) — used for queryType/postId context.
 * @param int|string|false $post_id Caller-resolved entity id, or false/0 when none.
 * @return int|string ACF-compatible object_id, or 0 when no context available.
 */
if ( ! function_exists( 'bws_resolve_acf_object_id' ) ) {
function bws_resolve_acf_object_id( $instance, $post_id ) {
	if ( is_int( $post_id ) && $post_id > 0 ) {
		return $post_id;
	}
	if ( is_string( $post_id ) && '' !== $post_id ) {
		return $post_id;
	}
	if ( is_numeric( $post_id ) && (int) $post_id > 0 ) {
		return (int) $post_id;
	}

	if ( ! is_object( $instance ) || ! isset( $instance->context ) || ! is_array( $instance->context ) ) {
		return 0;
	}

	$query_type = $instance->context['generateblocks/queryType'] ?? '';

	if ( 'option' === $query_type ) {
		return 'option';
	}

	if ( 'post_meta' === $query_type ) {
		$parent = (int) ( $instance->context['postId'] ?? 0 );
		if ( $parent > 0 ) {
			return $parent;
		}
	}

	return 0;
}
}

/**
 * Validate meta key format.
 *
 * @since 1.0.0
 * @param string $meta_key Meta key to validate.
 * @return bool True if valid.
 */
if ( ! function_exists( 'bws_is_valid_meta_key' ) ) {
function bws_is_valid_meta_key( $meta_key ) {
	return (bool) preg_match( '/^[a-zA-Z0-9_-]+$/', $meta_key );
}
}
