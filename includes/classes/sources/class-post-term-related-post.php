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

}
