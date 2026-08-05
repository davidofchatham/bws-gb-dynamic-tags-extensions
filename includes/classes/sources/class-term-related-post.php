<?php
/**
 * Term → Related Post source.
 *
 * @package BWS\DynamicTags
 * @since   1.5.0
 */

namespace BWS\DynamicTags\Sources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWS\DynamicTags\AbstractSource;

/**
 * Term → Related Post — REGISTRY-ONLY since 1.17.0 (#56).
 *
 * It used to resolve from a term context to a related post via an ACF relationship field
 * on the term, reading that field from `rel`. The hop is now a generic `ref` step off the
 * modifier's base source (1.14.0), and `ref` is the only spelling anything honours;
 * resolve_id() is inert and documents why.
 *
 * - Context type: post (the entity it used to resolve to)
 * - GB type: term (needs term selector in editor)
 * - UI group: term (appears in Term matrix)
 *
 * @since 1.5.0
 * @since 1.17.0 Inert (#56); registration retained.
 */
class TermRelatedPost extends AbstractSource {

	public function get_source_key(): string {
		return 'term_related_post';
	}

	public function get_source_label(): string {
		return __( 'Term → Rel. Post', 'generateblocks' );
	}

	public function get_tag_prefix(): string {
		return 'term_related_post';
	}

	public function get_gb_type(): string {
		return 'term';
	}

	public function get_context_type(): string {
		return 'post';
	}

	public function get_ui_group(): string {
		return 'term';
	}

	public function source_default_enabled(): bool {
		return true;
	}

	public function needs_relationship_field(): bool {
		return true;
	}

	public function get_excluded_supports(): array {
		return array( 'source', 'link' );
	}

	public function get_effective_source_id(): string {
		return 'term_related';
	}

	/**
	 * Inert since 1.17.0 — the term→post relationship hop is the `ref` step's job (#56).
	 *
	 * Superseded twice over: 1.14.0 replaced the per-combination traversal classes with a
	 * generic `ref` step off the modifier's BASE source (make_modifier_callback resolves
	 * TaxonomyTerm, then hops), and `traversal_source_key` has been accepted-but-ignored
	 * since. What remained here was the stale `rel` spelling — no other reader in the
	 * plugin honours it, so keeping it meant one question with two answers (#56).
	 *
	 * Unlike `related_post`, this source key never appeared in stored wire: no release
	 * ever emitted `src:term_related_post`, and the `term_related_post_*` TAG names
	 * migrate to `term_*` with `src:ref`. So there is nothing to migrate here.
	 *
	 * Registration stays — see RelatedPost::resolve_id() for why.
	 *
	 * @since 1.5.0
	 * @since 1.17.0 Inert; the generic `ref` step owns the hop (#56).
	 * @param array  $options  Unused.
	 * @param object $instance Unused.
	 * @return false Always — see above.
	 */
	public function resolve_id( array $options, $instance = null ) {
		return false;
	}

	public function get_source_options(): array {
		return array();
	}

}
