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
 * row into one of three states. Result cached on $instance->context['bws/loopItemPostId']
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
	// Explicit $post_id (e.g. resolved via src:ref hop) always wins — caller has already
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

/**
 * Assemble traversal steps from tag options (PURE — options → steps[]).
 *
 * The step-assembly half of the seam, extracted so the src:ref / srcTermIn →
 * step mapping is unit-testable without WP.
 *
 * **`src:ref` and `srcTermIn` COMPOUND** (issue #44 regression fix): a tag with
 * both emits `[ref, srcTermIn]` in that order — ref hops the source to its
 * related posts, then srcTermIn hops those posts to their terms. Order is
 * load-bearing: srcTermIn needs a POST input (it reads get_the_terms), which the
 * ref step produces; the reverse order would feed srcTermIn a term and short out.
 * (Pre-1.14.0 this compounded via bws_resolve_post_by_source honoring src:ref
 * before the term hop; the pipeline rewrite briefly dropped it — see #44.)
 * srcTermIn alone (no ref) emits `[srcTermIn]` and hops the base post's terms.
 *
 * @since 1.14.0
 * @since 1.14.0 #44: src:ref + srcTermIn now compound instead of dropping ref.
 * @param array $options Tag options (src, ref, srcTermIn).
 * @return array[] Ordered traversal steps (may be empty).
 */
if ( ! function_exists( 'bws_field_values_assemble_steps' ) ) {
function bws_field_values_assemble_steps( array $options ): array {
	$steps = array();

	// ref first: source (post/term) → related posts. srcTermIn (below) then hops
	// those posts to their terms, so ref must precede it (input-kind order, #44).
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

/**
 * Read one resolved source's field value at L2, dispatched by KIND (SPEC §V12).
 *
 * The factory owns source-SELECTION; this owns the READ. site → option read;
 * term → term meta; post → post meta with an EXPLICIT id (triggers the v1.7.1
 * explicit-wins rule in bws_read_field, bypassing ITS own loop/term inference so
 * the factory's resolved source is authoritative — no double resolution).
 * meta_row → the row's own key. Returns '' on miss (caller drops empties).
 *
 * @since 1.14.0
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
 * Shared L1/L2 source-resolution pipeline: resolve a (source + key) read target
 * to a list of raw candidate field-value strings.
 *
 * The single source-resolution seam (CONTEXT.md §L1/L2/L3, ADR 0002) the
 * value-list tags share. Since 1.14.0 (traversal pipeline Phase 1) the L1 half
 * delegates to the source factory + step engine:
 *   - L1 resolve source: bws_resolve_base_source (ambient/explicit/loop/site,
 *     SPEC §V1) → base resolved source.
 *   - L1 traversal: bws_field_values_assemble_steps (src:ref → ref step,
 *     srcTermIn → term-hop step; both compound as [ref, srcTermIn] when set, #44)
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
 * Returns RAW, UNVALIDATED strings — per-tag validation + L3 composition stay in
 * each tag's callback. The resolver is composition-blind.
 *
 * @since 1.11.0
 * @since 1.14.0 Delegates L1 to the source factory + traversal engine; ref plural.
 * @param array  $options  Tag options (key, src, ref, srcTermIn, limit, …).
 * @param object $instance GB tag instance.
 * @return string[] Raw candidate value strings (unvalidated, empties dropped).
 */
if ( ! function_exists( 'bws_resolve_field_values' ) ) {
function bws_resolve_field_values( array $options, $instance ): array {
	$key = sanitize_text_field( $options['key'] ?? '' );
	if ( '' === $key ) {
		return array();
	}

	// src:site keeps its dot-path affordance (ACF options can be dotted); other
	// sources require a valid flat meta key. Gate BEFORE resolution to preserve
	// the historical early-return on invalid non-site keys.
	$is_site = 'site' === ( $options['src'] ?? '' );
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

	// list mode — slice plural source list to limit (default 1).
	$limit   = max( 1, (int) ( $options['limit'] ?? 1 ) );
	$sources = array_slice( $sources, 0, $limit );

	// L2 — read each resolved source by kind; drop empties.
	$out = array();
	foreach ( $sources as $source ) {
		$value = bws_read_resolved_source( $source, $key, $instance );
		if ( '' !== $value ) {
			$out[] = $value;
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
 *  1. slice to `limit` (default 1);
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
