<?php
/**
 * Shared base-tag foundation: source options, traversal sub-options, source
 * dispatch, term-ambient dispatch, and the option-remap helper.
 *
 * These are the cross-tag primitives the base callbacks AND the other tag
 * families (datetime, email, fn, phone) build on — the `src`/`ref`/`srcTermIn`
 * option definitions, the try_ slot option builder, the post-id source
 * wrapper, the ambient-term analog read, and bws_base_map_options(). They live
 * here (not in base-tags.php) because their scope is every tag, not just the
 * base renderers; base-tags.php now holds only the actual base tag callbacks,
 * the src:site source, and the try_ dispatch wrappers.
 *
 * Load order: required BEFORE base-tags.php and every other tag file, since
 * those call these builders/wrappers at registration and render time.
 *
 * Resolution model (L1 factory → traversal steps → L2 read by kind) is
 * documented on base-tags.php and in CONTEXT.md / docs/tag-reference.md; the
 * per-function PHPDoc below carries the load-bearing invariants (§V refs).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.14.1 Extracted from base-tags.php (code-move refactor; no behavior change).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===============================================
// SOURCE OPTION + TRAVERSAL SUB-OPTIONS
// ===============================================

/**
 * Build the source dropdown option definition.
 *
 * Uses option key 'src' (not 'source') because GB's DynamicTagSelect
 * unconditionally destructures 'source' from parsed tag params before
 * spreading into extraTagParams, so any option named 'source' is silently
 * eaten and never reaches the editor controls.
 *
 * @since 1.6.0
 * @return array Single-entry array keyed 'src'.
 */
function bws_base_source_option(): array {
	return array(
		'src' => array(
			'type'           => 'select',
			'label'          => __( 'Source', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'current', 'label' => __( 'Current', 'generateblocks' ) ),
				array( 'value' => 'ref',     'label' => __( 'In Reference/Relational Field', 'generateblocks' ) ),
				array( 'value' => 'site',    'label' => __( 'Site', 'generateblocks' ) ),
			),
			'_strip_default' => true,
		),
	);
}

/**
 * Filter `site` out of a source-option definition.
 *
 * A rooting modifier (`term_*`, `view_*`) exists to surface ENTITY-DISTINCT data;
 * a site read is entity-blind, so offering `site` there merely duplicates the
 * unrooted base tag (`{{email src:site}}`) while discarding the rooting — it fails
 * the qualifying gate on both arms (CONTEXT.md I4 source-level application;
 * tag-reference.md §Qualifying test). register_modifier() routes its source dropdown
 * through this before injecting it into every term_/view_ tag.
 *
 * Mirrors the slot-side filter in bws_build_slot_traversal_options() (which omits
 * `site` from derived try_ slot src unless a template opts back in via
 * try_allow_site_slot). A future "ID source + site fallback" (the author-serialized-
 * entity-id source flavor — CONTEXT.md §Language "Source binding") belongs in a
 * try_ chain slot, NOT a single-slot rooting modifier. See [#37].
 *
 * @since 1.11.0
 * @param array $source_opt A bws_base_source_option()-shaped array (key 'src').
 * @return array Same shape with the `site` value removed from src options.
 */
function bws_filter_site_from_src( array $source_opt ): array {
	if ( isset( $source_opt['src']['options'] ) && is_array( $source_opt['src']['options'] ) ) {
		$source_opt['src']['options'] = array_values( array_filter(
			$source_opt['src']['options'],
			static function ( $opt ) {
				return 'site' !== ( $opt['value'] ?? '' );
			}
		) );
	}
	return $source_opt;
}

/**
 * Keep ONLY the named source values in a src-option definition (allowlist).
 *
 * The complement of bws_filter_site_from_src() (a blocklist that drops `site`).
 * Use the BLOCKLIST when a tag wants "every base source except X" (term_/view_
 * rooting modifiers, generic try_ slots — they SHOULD inherit a future base
 * source). Use this ALLOWLIST when a tag has a CLOSED source set and must NOT
 * inherit new base values by default — e.g. `{{call}}` offers `current`/`ref`
 * only (both post-yielding; a `$post_id` function can't consume a non-post
 * source), so a future non-post base value must be excluded automatically, not
 * leaked. Pulling the rows from bws_base_source_option() keeps the labels /
 * `_strip_default` canonical instead of hand-copied.
 *
 * Order follows $keep (so the menu order is the caller's, not base's). A $keep
 * value with no matching base row is silently skipped.
 *
 * @since 1.12.0
 * @param array    $source_opt A bws_base_source_option()-shaped array (key 'src').
 * @param string[] $keep       Source values to retain, in display order.
 * @return array Same shape with src options reduced + reordered to $keep.
 */
function bws_pick_src_values( array $source_opt, array $keep ): array {
	if ( ! isset( $source_opt['src']['options'] ) || ! is_array( $source_opt['src']['options'] ) ) {
		return $source_opt;
	}
	$by_value = array();
	foreach ( $source_opt['src']['options'] as $opt ) {
		$by_value[ $opt['value'] ?? '' ] = $opt;
	}
	$picked = array();
	foreach ( $keep as $value ) {
		if ( isset( $by_value[ $value ] ) ) {
			$picked[] = $by_value[ $value ];
		}
	}
	$source_opt['src']['options'] = $picked;
	return $source_opt;
}

/**
 * Build traversal sub-option definitions for the source dispatch.
 *
 * `ref` — shown when src:ref; the relationship field key for the hop.
 * `srcTermIn` — combined control (checkbox + taxonomy ComboboxControl); when a
 *               taxonomy slug is selected, the resolved entity's taxonomy term
 *               is used as the final entity instead of the post itself. Empty =
 *               disabled. Custom JS control (`bws-term-hop`) ensures non-GB-reserved
 *               serialization. Replaces the prior `srcTerm` + `tax` pair.
 *
 * @since 1.6.0
 * @return array Option definitions keyed by option name.
 */
function bws_base_traversal_options(): array {
	return array(
		'ref'     => array(
			'type'        => 'bws-field-combo',
			'label'       => __( 'Relationship Field Key', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key.', 'generateblocks' ),
			'placeholder' => 'related_posts',
			// ref names the SOURCE-post relationship field. The control does NOT
			// preset a kind for src:ref (presetKind returns null): the ref-hop target
			// post type is not reliably known, so the key list stays UNSCOPED with the
			// generic "Meta/Option Field" label (SPEC V3). v2 will type-filter this to
			// relationship/post_object.
			// src:ref only. src:site suppressed in Stage A — no site→ref wiring yet
			// (not "never applies"; re-expose when a site→ref path ships).
			'show_if'     => array( 'src' => 'ref' ),
		),
		'srcTermIn' => array(
			'type'      => 'bws-term-hop',
			'label'     => __( 'Get from taxonomy term?', 'generateblocks' ),
			'help'      => __( 'Field is in a taxonomy term on this source.', 'generateblocks' ),
			'pickLabel' => __( 'Taxonomy', 'generateblocks' ),
			'pickHelp'  => __( 'Pick the taxonomy.', 'generateblocks' ),
			// Hidden for src:site — no entity to hop terms from. (Term-context tags
			// override this to src:ref in the template registry.)
			'show_if'   => array( 'src' => 'not:site' ),
		),
	);
}

/**
 * Re-qualify a base option's `show_if` condition keys for a numbered try_ slot.
 *
 * Base traversal options carry bare sibling-key conditions (e.g. `ref` shows when
 * `['src' => 'ref']`). In a try_ slot ≥2 those sibling keys are ordinal-prefixed
 * (`{N}-src`), so the condition key must follow: `src` → `2-src`. Slot 1 keeps the
 * bare key (no prefix). Only keys present in $sibling_keys are rewritten; any other
 * condition key (e.g. a cross-option reference) is left untouched. Condition VALUES
 * (`'ref'`, `'not:site'`) are never altered.
 *
 * Pure array transform — no WP/GB symbols. Locally harnessable
 * (tools/test/slot-qualify-show-if-test.php). [SPEC §26 V2, V8]
 *
 * @since 1.11.0
 * @param array $show_if      Condition map { key => value }. Empty → empty out.
 * @param int   $n            Slot ordinal (1-based). Slot 1 = bare keys.
 * @param array $sibling_keys Keys eligible for `{N}-` prefixing (e.g. ['src','ref','srcTermIn']).
 * @return array Re-keyed condition map, values unchanged.
 */
function bws_slot_qualify_show_if( array $show_if, int $n, array $sibling_keys ): array {
	if ( $n <= 1 || empty( $show_if ) ) {
		return $show_if;
	}
	$out = array();
	foreach ( $show_if as $key => $value ) {
		$qualified         = in_array( $key, $sibling_keys, true ) ? "{$n}-{$key}" : $key;
		$out[ $qualified ] = $value;
	}
	return $out;
}

/**
 * Normalize a try_ slot dispatch return into a list of finished item strings.
 *
 * The try_ machinery is composition-blind (CONTEXT.md I6): a slot's dispatch
 * returns either ONE finished string (today's text/content/image/email/phone
 * single-result path) or an array of finished per-item strings (a slot in list
 * mode — e.g. a srcTermIn term-hop, or the shared L1/L2 resolver's plural
 * `src:ref`). This helper collapses both to a list, dropping empty items, so the
 * machinery can join uniformly without caring which producer it is.
 *
 * The array contract lives at the resolver/L2 layer (ADR 0002), NOT retrofitted
 * into every dispatcher: shipped dispatchers keep returning a single string and
 * still flow through here as a 1-element list. [SPEC §32 V2,V6]
 *
 * Pure — no WP/GB symbols. Locally harnessable (tools/test/try-join-seam-test.php).
 *
 * @since 1.11.0
 * @param mixed $raw Dispatch return: string | array<string> | '' | false.
 * @return array<int,string> Finished item strings, empties removed, re-indexed.
 */
function bws_try_normalize_items( $raw ): array {
	if ( '' === $raw || false === $raw || null === $raw ) {
		return array();
	}
	$items = is_array( $raw ) ? $raw : array( $raw );
	$out   = array();
	foreach ( $items as $item ) {
		if ( '' !== $item && false !== $item && null !== $item ) {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * Join a winning try_ slot's finished item strings — the ONLY composition the
 * try_ machinery itself performs (CONTEXT.md I6).
 *
 * Limit / separator semantics MATCH the base text list-mode core
 * (bws_post_custom_text_core, content-tags.php) so a try_ slot in list mode
 * joins identically to the same underlying tag used standalone (I6 parity):
 *   - limit = max( 1, (int) $limit ?: 1 ) — DEFAULT 1, floored at 1 (never 0).
 *     Not a ceiling: an author setting limit:5 joins up to 5 items.
 *   - sep   = $sep ?? ', ' — null (absent) → default ', '; an explicit empty
 *     string is honored (matches base `$options['sep'] ?? ', '`, which only
 *     defaults on an absent key — author may deliberately join with no sep).
 *
 * A 1-element list with the default limit returns the single element verbatim
 * (no trailing separator — sep is never applied to a lone item) — the
 * byte-identical backward-compat gate for existing try_text/try_content/try_image.
 * [SPEC §32 V3,V4]
 *
 * Pure — no WP/GB symbols. Locally harnessable (tools/test/try-join-seam-test.php).
 *
 * @since 1.11.0
 * @param array<int,string> $items Finished item strings (already non-empty).
 * @param mixed              $sep   Separator; null → ', '. Explicit '' honored.
 * @param mixed              $limit Max items to join; falsy → 1. Floored at 1.
 * @return string Joined output (or '' if no items).
 */
function bws_try_join_items( array $items, $sep = null, $limit = null ): string {
	if ( empty( $items ) ) {
		return '';
	}
	$max = max( 1, (int) ( $limit ?: 1 ) );
	$s   = ( null === $sep ) ? ', ' : $sep;
	return implode( $s, array_slice( $items, 0, $max ) );
}

/**
 * Build the source + traversal option definitions for one numbered try_ slot,
 * derived from the base builders. Pure fn of (slot ordinal, base option sets) —
 * no WP/GB symbols, no $slot_trigger merge (that visibility layer is the registry's
 * concern, kept separate per V3). Locally harnessable
 * (tools/test/slot-options-build-test.php). [SPEC §26 V1,V2,V5,V6,V9,V10]
 *
 * Derivation rules:
 *   - src: base `src.options`. `site` is filtered out by DEFAULT (V6 wrong-read
 *     guard — the generic try_ slot resolver had no site arm). Per-template
 *     opt-in via $allow_site=true re-allows it (email/phone — once the slot
 *     resolver site arm landed, SPEC §32 V7/V8): site is the canonical contact
 *     fallback slot. Slot ≥2 prepends the `same` (inherit) row. `_strip_default`
 *     preserved (V5). Label overlaid as "N: Source" (V10).
 *   - ref / srcTermIn: base definitions verbatim (label body / placeholder / help
 *     from base — V10), show_if re-qualified via bws_slot_qualify_show_if, label
 *     (and srcTermIn pickLabel) given the "N: " ordinal prefix (V10).
 *
 * @since 1.11.0
 * @param int   $n          Slot ordinal (1-based).
 * @param array $base_src   bws_base_source_option() result.
 * @param array $base_trav  bws_base_traversal_options() result.
 * @param bool  $allow_site When true, keep `site` in the src list (per-template
 *                          opt-in, gated on the resolver site arm). Default false.
 * @return array { 'src' => array, 'ref' => array, 'srcTermIn' => array } — option
 *               definitions WITHOUT $slot_trigger (caller merges show_if_any).
 */
function bws_build_slot_traversal_options( int $n, array $base_src, array $base_trav, bool $allow_site = false ): array {
	$sibling_keys = array( 'src', 'ref', 'srcTermIn' );

	// --- src: filter 'site' unless per-template allowed (V6 guard / V8 opt-in),
	// prepend 'same' for slot ≥2, keep _strip_default (V5). ---
	$base_src_opts = $base_src['src']['options'] ?? array();
	$src_opts      = $allow_site
		? array_values( $base_src_opts )
		: array_values( array_filter(
			$base_src_opts,
			static function ( $o ) {
				return 'site' !== ( $o['value'] ?? '' );
			}
		) );
	if ( $n >= 2 ) {
		array_unshift(
			$src_opts,
			array( 'value' => 'same', 'label' => __( 'Same as Previous Source', 'generateblocks' ) )
		);
	}
	$src_def = array(
		'type'           => 'select',
		/* translators: %d: slot number */
		'label'          => sprintf( __( '%d: Source', 'generateblocks' ), $n ),
		'options'        => $src_opts,
		'_strip_default' => true,
	);

	// --- ref: base def verbatim (V10), show_if re-qualified, "N: " label prefix. ---
	$ref_def          = $base_trav['ref'];
	$ref_def['label'] = sprintf( /* translators: 1: slot number, 2: base label */ '%1$d: %2$s', $n, $base_trav['ref']['label'] );
	if ( isset( $ref_def['show_if'] ) ) {
		$ref_def['show_if'] = bws_slot_qualify_show_if( $ref_def['show_if'], $n, $sibling_keys );
	}

	// --- srcTermIn: base def verbatim (V10), show_if re-qualified, "N: " label + pickLabel prefix. ---
	$stm_def          = $base_trav['srcTermIn'];
	$stm_def['label'] = sprintf( '%1$d: %2$s', $n, $base_trav['srcTermIn']['label'] );
	if ( isset( $stm_def['pickLabel'] ) ) {
		$stm_def['pickLabel'] = sprintf( '%1$d: %2$s', $n, $base_trav['srcTermIn']['pickLabel'] );
	}
	if ( isset( $stm_def['show_if'] ) ) {
		$stm_def['show_if'] = bws_slot_qualify_show_if( $stm_def['show_if'], $n, $sibling_keys );
	}

	return array(
		'src'       => $src_def,
		'ref'       => $ref_def,
		'srcTermIn' => $stm_def,
	);
}

// ===============================================
// SOURCE DISPATCH
// ===============================================

/**
 * Resolve the target post ID from the `src` option.
 *
 * THIN BACK-COMPAT WRAPPER (SPEC §T5, §V4) over the source factory + traversal
 * engine. The value-list SEAM (bws_resolve_field_values) no longer calls this —
 * it drives the factory + steps directly and reads plural by kind (SPEC §V6/§V12).
 * This wrapper survives for its ~30 remaining POST-SEMANTIC callers (datetime,
 * {{call}}/fn, try_ slots): they want a single POST id | false, nothing else.
 *
 * Delegates to bws_resolve_base_source (L1 factory: loop → ambient term → current
 * post, SPEC §V1/§V7) + a REF-ONLY step assembly (bws_wrapper_ref_steps, SPEC
 * §V13) run through bws_run_traversal, then collapses to the FIRST post id
 * (bws_first_post_id_from_sources, SPEC §V4). A non-post base — term ambient on an
 * archive (V7) or a Mode-2b meta_row (src:current on a flat repeater row) — yields
 * false, never leaks a term/row id as a post id. That is byte-compatible with the
 * old wrapper for src:current (Mode 2b → false, unchanged); for src:ref it applies
 * the V11 leak-fix (base the ref hop on the ambient term, not on get_the_ID()).
 *
 * REF-ONLY (SPEC §V13): the wrapper NEVER assembles a `srcTermIn` step. srcTermIn
 * (post→term) is owned DOWNSTREAM by the wrapper's callers — datetime/text/title
 * srcTermIn branches call bws_get_srcterm_terms() on the returned POST id. Routing
 * the wrapper through the SEAM's bws_field_values_assemble_steps() (which emits a
 * srcTermIn term-hop) would collapse to false and empty those callers (B2). The
 * seam reads term fields by kind; the wrapper cannot — its contract is a post id.
 *
 * @since 1.6.0
 * @since 1.14.0 Rewired to the source factory + traversal engine (SPEC §T5); ref-only steps (§V13, B2).
 * @param array  $options  Tag options from GenerateBlocks.
 * @param object $instance Block instance.
 * @return int|false Resolved post ID, or false if unresolvable.
 */
function bws_resolve_post_by_source( array $options, $instance ) {
	if ( ! function_exists( 'bws_resolve_base_source' )
		|| ! function_exists( 'bws_run_traversal' )
		|| ! function_exists( 'bws_first_post_id_from_sources' ) ) {
		return false;
	}

	$base    = bws_resolve_base_source( $options, $instance );
	$steps   = bws_wrapper_ref_steps( $options );
	$sources = bws_run_traversal( array( $base ), $steps );

	return bws_first_post_id_from_sources( $sources );
}

/**
 * Assemble the wrapper's REF-ONLY step set (SPEC §V13, B2).
 *
 * Post-semantic: only a `src:ref` hop (post→post[]) is a wrapper step. A
 * `srcTermIn` post→term hop is DELIBERATELY excluded — the wrapper's callers own
 * that downstream on the returned post id (bws_get_srcterm_terms). Contrast the
 * seam's bws_field_values_assemble_steps(), which DOES emit a srcTermIn term-hop
 * (and compounds it after a ref step when both are set, #44) because it reads
 * term fields by kind (§V6/§V12).
 *
 * @since 1.14.0
 * @param array $options Tag options (src, ref).
 * @return array[] Zero or one ref step.
 */
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

/**
 * Get taxonomy terms for a resolved post via the `srcTerm`/`tax` options.
 *
 * Called by base tag callbacks when `srcTerm` is set. The post is already
 * resolved via bws_resolve_post_by_source(); this function performs the
 * final hop from that post to its taxonomy terms.
 *
 * @since 1.6.0
 * @param int    $post_id Resolved post ID.
 * @param string $tax     Taxonomy slug from $options['tax'].
 * @return WP_Term[]
 */
function bws_get_srcterm_terms( int $post_id, string $tax ): array {
	if ( ! $post_id || '' === $tax ) {
		return [];
	}

	$terms = get_the_terms( $post_id, $tax );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return [];
	}

	return array_values( $terms );
}

// ===============================================
// TERM-AMBIENT DISPATCH (SPEC §T6 / §V7)
// ===============================================

/**
 * Resolve the base source for a base callback, guarded for load order.
 *
 * Single factory call per callback (SPEC §V1): the callback then branches on the
 * base kind — term → analog read (§V7), else collapse to a post id via
 * bws_first_post_id_from_sources (§V4). Falls back to a post/0 source when the
 * engine is unavailable (mirrors the wrapper's guard) so callbacks stay safe.
 *
 * @since 1.14.0
 * @param array  $options  Tag options.
 * @param object $instance GB instance.
 * @return array Base resolved source ({kind,id}|{kind:site}|{kind:meta_row,row}).
 */
function bws_base_resolve_source_for_callback( array $options, $instance ): array {
	return function_exists( 'bws_resolve_base_source' )
		? bws_resolve_base_source( $options, $instance )
		: array( 'kind' => 'post', 'id' => 0 );
}

/**
 * Collapse a base source to the callback's POST id via ref-only steps (SPEC §V13).
 *
 * The post-path counterpart of the ambient-term branch: runs the wrapper's
 * ref-only step set (src:ref → post→post[] hop; NEVER srcTermIn, which the
 * callback's own $tax branch owns) then takes the first post id. Mirrors
 * bws_resolve_post_by_source() for a base source already resolved once, so the
 * callback pays a single factory call (SPEC §V1).
 *
 * @since 1.14.0
 * @param array $base    Base resolved source.
 * @param array $options Tag options.
 * @return int|false First post id, or false.
 */
function bws_base_post_id_from_source( array $base, array $options ) {
	if ( ! function_exists( 'bws_run_traversal' ) || ! function_exists( 'bws_first_post_id_from_sources' ) ) {
		return bws_first_post_id_from_sources( array( $base ) );
	}
	$sources = bws_run_traversal( array( $base ), bws_wrapper_ref_steps( $options ) );
	return bws_first_post_id_from_sources( $sources );
}

/**
 * Collapse a base source to the FULL post-id LIST via ref-only steps (SPEC §V14).
 *
 * The plural counterpart of bws_base_post_id_from_source(): for a tag that offers
 * list mode on `src:ref` (text/title, §V14 offered⟺resolvable), the src:ref post
 * branch reads EVERY fanned-out ref target (bws_run_traversal keeps all, §V6) — not
 * just the first. Order preserved; only post-kind sources contribute. The caller
 * slices to `limit` and joins with `sep`, mirroring the srcTermIn branch.
 *
 * @since 1.14.0
 * @param array $base    Base resolved source.
 * @param array $options Tag options.
 * @return int[] Post ids in document order (may be empty).
 */
function bws_base_post_ids_from_source( array $base, array $options ): array {
	if ( ! function_exists( 'bws_run_traversal' ) ) {
		return array();
	}
	$sources = bws_run_traversal( array( $base ), bws_wrapper_ref_steps( $options ) );
	$ids     = array();
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

/**
 * Whether a base callback should read the AMBIENT TERM instead of a post.
 *
 * True iff (a) no explicit `srcTermIn` hop is set (that branch owns its own
 * post→term traversal and is incoherent from a term base), (b) `src` is neither
 * the site source (own early gate) NOR `ref` (SPEC §V11: src:ref on a term archive
 * HOPS the term's relationship field term→post[] via the post path's ref step,
 * then reads the target POST's analog — it must NOT short-circuit to the term's
 * own analog), and (c) the factory's base resolved source is a term — i.e. a bare
 * base tag on a term archive (SPEC §V7). Explicit options (loop row, src:current,
 * id) win inside the factory itself (SPEC §V1), so this returns false whenever the
 * author pinned a non-term source.
 *
 * @since 1.14.0
 * @param array  $base     Base resolved source from bws_resolve_base_source().
 * @param array  $options  Tag options.
 * @return int Term id when the ambient-term analog path applies, else 0.
 */
function bws_base_ambient_term_id( array $base, array $options ): int {
	$tax = sanitize_key( $options['srcTermIn'] ?? '' );
	if ( '' !== $tax ) {
		return 0; // Explicit post→term hop owns this render.
	}
	$src = $options['src'] ?? $options['source'] ?? '';
	if ( 'site' === $src || 'ref' === $src ) {
		return 0; // Site: own gate. ref: hops term→post (V11), post path owns it.
	}
	if ( 'term' !== ( $base['kind'] ?? '' ) ) {
		return 0;
	}
	return (int) ( $base['id'] ?? 0 );
}

/**
 * Read a base tag's TERM analog on a term archive (SPEC §V7, CONTEXT.md I1).
 *
 * The I1 source-analog table applied to an ambient term: each base tag, at its
 * DEFAULT `use`, yields the term's intrinsic analog; `use:key` (and text's
 * key-default) reads a term meta field. Routes through the SAME term core fns the
 * explicit srcTermIn branch uses — full parity, one code home for the term reads.
 *
 *   title   → term name           (bws_term_title_core)
 *   text    → use:title ? name : keyed term field  (title vs custom_text core)
 *   content → use:key  ? keyed term field : term description
 *   permalink → term URL          (bws_term_permalink_core)
 *   image   → HONEST GAP (#29): no intrinsic term image analog. A key reads a
 *             term image field; with no key + no fallback → empty. A configured
 *             Media Library fallback still applies (bws_term_custom_image_core owns
 *             the no-key→fallback path), keeping standalone == try_image slot.
 *
 * @since 1.14.0
 * @param string $tag     One of text|content|title|permalink|image.
 * @param int    $term_id Ambient term id.
 * @param array  $options Tag options (use, key, fallback, …).
 * @param object $instance GB instance.
 * @return string Rendered analog value ('' on miss/gap).
 */
function bws_base_term_analog_read( string $tag, int $term_id, array $options, $instance ): string {
	if ( ! $term_id ) {
		return '';
	}
	$opts = bws_base_map_options( $options );

	switch ( $tag ) {
		case 'title':
			return bws_term_title_core( $term_id, $options, $instance );

		case 'text':
			$use = $options['use'] ?? 'key';
			return 'title' === $use
				? bws_term_title_core( $term_id, $opts, $instance )
				: bws_term_custom_text_core( $term_id, $opts, $instance );

		case 'content':
			$use = $options['use'] ?? 'content';
			return 'key' === $use
				? bws_term_custom_text_core( $term_id, $opts, $instance )
				: bws_term_description_core( $term_id, $opts, $instance );

		case 'permalink':
			return bws_term_permalink_core( $term_id, $options, $instance );

		case 'image':
			// I1 gap #29 — a term has no intrinsic image analog. A key reads a term
			// image field; with no key there is no analog datum, BUT a configured
			// Media Library fallback still applies (fallback = last resort, gap or not).
			// bws_term_custom_image_core handles the no-key case itself: empty key →
			// the shared bws_handle_media_fallback (id-or-url, SPEC §V19) → the fallback
			// (or '' when none set). So call it unconditionally — no key + no fallback
			// stays empty (honest gap), no key + fallback yields the fallback. Keeps the
			// standalone tag byte-identical to a try_image slot (same core, V8/C9).
			return bws_term_custom_image_core( $term_id, $options, $instance );
	}

	return '';
}

// ===============================================
// USER-AMBIENT DISPATCH (#19 author kind, 1.15.0)
// ===============================================

/**
 * Whether a base callback should read the AMBIENT USER instead of a post.
 *
 * The user-kind counterpart of bws_base_ambient_term_id(): true iff the factory's
 * base resolved source is a user (bare tag on an author archive, #19). Mirrors the
 * term gate's guards — an explicit srcTermIn hop, src:site, or src:ref keeps its
 * own meaning (no user ref hop exists yet, so src:ref falls through to the post
 * path), and explicit src/loop/id already won inside the factory (SPEC §V1).
 *
 * @since 1.15.0
 * @param array $base    Base resolved source from bws_resolve_base_source().
 * @param array $options Tag options.
 * @return int User id when the ambient-user analog path applies, else 0.
 */
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

/**
 * Read a base tag's USER analog on an author archive (#19, CONTEXT.md I1).
 *
 * The I1 source-analog table applied to an ambient user — each base tag at its
 * DEFAULT `use` yields the user's intrinsic analog:
 *
 *   title   → display name          (get_the_author_meta('display_name'))
 *   content → biographical info      (get_the_author_meta('description'))
 *
 * Values route through GenerateBlocks_Dynamic_Tag_Callbacks::output() so GB's
 * per-tag transforms (trunc/replace/trim/case/wpautop/link) apply, matching the
 * term analog readers.
 *
 * Scope for 1.15.0 is title + content only (the plan's author-archive dispatch
 * rows). text/permalink/image/datetime author analogs are future work — this
 * returns '' for any other tag, so an unhandled tag renders empty rather than
 * wrong. A `use:key` user-meta read is deferred with them.
 *
 * @since 1.15.0
 * @param string $tag      One of title|content (others → '').
 * @param int    $user_id  Ambient user id.
 * @param array  $options  Tag options.
 * @param object $instance GB instance.
 * @return string Rendered analog value ('' on miss/gap/unsupported tag).
 */
function bws_base_user_analog_read( string $tag, int $user_id, array $options, $instance ): string {
	if ( ! $user_id ) {
		return '';
	}
	switch ( $tag ) {
		case 'title':
			$name = get_the_author_meta( 'display_name', $user_id );
			if ( ! is_string( $name ) || '' === $name ) {
				return '';
			}
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $name, $options, $instance );

		case 'content':
			$bio = get_the_author_meta( 'description', $user_id );
			if ( ! is_string( $bio ) || '' === $bio ) {
				return '';
			}
			return GenerateBlocks_Dynamic_Tag_Callbacks::output(
				bws_sanitize_rich_content( $bio ),
				$options,
				$instance
			);
	}

	return '';
}

// ===============================================
// SHARED OPTION HELPER
// ===============================================

/**
 * Remap base-tag option keys to what the old core functions expect.
 *
 * Base tags use the new naming convention (fallback vs. fallback_text).
 * Existing core functions still read the old keys. This function bridges
 * the gap without requiring changes to the core functions.
 *
 * @since 1.6.0
 * @param array $options Raw tag options from GenerateBlocks.
 * @return array Options with fallback_text populated from fallback when present.
 */
function bws_base_map_options( array $options ): array {
	if ( isset( $options['fallback'] ) && ! isset( $options['fallback_text'] ) ) {
		$options['fallback_text'] = $options['fallback'];
	}
	return $options;
}
