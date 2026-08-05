<?php
/**
 * Post → Term → Related Post source.
 *
 * 3-hop traversal from post context:
 *   Hop 1: current post → first term in `taxonomy`
 *   Hop 2: that term    → first related post via `rel` field on the term entity
 *
 * Note: only the first term in the taxonomy is used. If the post has multiple terms,
 * the first returned by get_the_terms() is used.
 *
 * The source toggle is off by default. When enabled, all tags are on by default.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.4.1
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostTermRelatedPost extends AbstractSource {

	public function get_source_key(): string {
		return 'post_term_related_post';
	}

	public function get_source_label(): string {
		return __( 'Post → Term → Rel. Post', 'generateblocks' );
	}

	/**
	 * Source is enabled by default for discoverability.
	 *
	 * @since 1.4.1
	 * @return bool
	 */
	public function source_default_enabled(): bool {
		return true;
	}

	/**
	 * Individual tags are enabled by default when the source is on.
	 * The source itself is opt-in (off by default); once enabled, no per-tag setup needed.
	 *
	 * @since 1.4.1
	 * @return bool
	 */
	public function tag_default_enabled(): bool {
		return true;
	}

	/**
	 * Exclude 'source' support — traversal always starts from the current post.
	 *
	 * @since 1.4.1
	 * @return string[]
	 */
	public function get_excluded_supports(): array {
		return array( 'source', 'link' );
	}

	public function needs_relationship_field(): bool {
		return true;
	}

	/**
	 * Inert since 1.17.0 — post→term→post is now a two-step chain (#56).
	 *
	 * The hand-rolled pair of hops (post → FIRST term in a taxonomy → relationship field
	 * on that term) is what the traversal engine's `srcTermIn` + `ref` steps do
	 * generically, without collapsing the term hop to `reset( $terms )`. What survived
	 * here was the stale `rel` spelling no other reader honours (#56).
	 *
	 * Nothing to migrate: `src:post_term_related_post` was never emitted as a source
	 * token — `post_term_related_post_*` were TAG names, and they remain registry-only
	 * entries with no migration target (no current tag reaches a term-then-relationship
	 * chain; the chain wire that could express it has no authoring surface yet — FW-56).
	 *
	 * Registration stays — see RelatedPost::resolve_id() for why.
	 *
	 * @since 1.0.0
	 * @since 1.17.0 Inert; chained `srcTermIn` + `ref` steps own this traversal (#56).
	 * @param array  $options  Unused.
	 * @param object $instance Unused.
	 * @return false Always — see above.
	 */
	public function resolve_id( array $options, $instance ) {
		return false;
	}

	/**
	 * No options, since 1.17.0 (#56).
	 *
	 * This used to return the taxonomy (hop 1) and the relationship field key on the term
	 * (hop 2). With resolve_id() inert they would be controls advertising a traversal that
	 * cannot fire — an author could fill them in and get nothing, with no way to tell why.
	 * An inert resolver and a live option surface is the worse half of both states.
	 *
	 * @since 1.17.0 Emptied alongside resolve_id() (#56).
	 * @return array
	 */
	public function get_source_options(): array {
		return array();
	}

}
