<?php
/**
 * Second Related Post source - resolves to a post via two ACF relationship hops.
 *
 * Hop 1: current post → 'rel'  field → first related post (mid).
 * Hop 2: mid post     → 'rel_2' field → first second-degree related post.
 *
 * The source is enabled by default (source_default_enabled() = true). When the source is
 * enabled, all individual tags are on by default (tag_default_enabled() = true).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.2.0
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecondRelatedPost extends AbstractSource {

	public function get_source_key(): string {
		return 'second_related_post';
	}

	public function get_source_label(): string {
		return __( 'Post → 2nd Rel. Post', 'generateblocks' );
	}

	/**
	 * Source is enabled by default for discoverability.
	 *
	 * @since 1.2.0
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
	 * Exclude 'source' support — traversal always starts from the current post via rel/rel_2,
	 * so the GB post picker has no role here.
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
	 * Inert since 1.17.0 — a second-degree hop is now a two-step chain (#56).
	 *
	 * This hand-rolled `rel` → `rel_2` double hop predates the traversal engine, which
	 * chains arbitrary steps (`bws_run_traversal`) and preserves fan-out where this
	 * collapsed to the first post at each hop. It also carried the stale `rel`/`rel_2`
	 * vocabulary that no other reader in the plugin honours (#56).
	 *
	 * Nothing to migrate: `src:second_related_post` was never emitted as a source token —
	 * `second_related_post_*` were TAG names, and they remain registry-only entries with
	 * no migration target (no current tag reaches a second-hop relationship; the chain
	 * wire that could express it has no authoring surface yet — FW-56).
	 *
	 * Registration stays — see RelatedPost::resolve_id() for why.
	 *
	 * @since 1.0.0
	 * @since 1.17.0 Inert; chained `ref` steps own multi-hop traversal (#56).
	 * @param array  $options  Unused.
	 * @param object $instance Unused.
	 * @return false Always — see above.
	 */
	public function resolve_id( array $options, $instance ) {
		return false;
	}

	/**
	 * Source options: two relationship field keys (first hop and second hop).
	 *
	 * @return array
	 */
	public function get_source_options(): array {
		return array_merge(
			bws_get_relationship_field_options(),        // 'rel'   — first hop
			bws_get_second_relationship_field_options()  // 'rel_2' — second hop
		);
	}

}
