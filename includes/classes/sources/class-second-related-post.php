<?php
/**
 * Second Related Post source — REGISTRY-ONLY since 1.17.0 (#56).
 *
 * It used to resolve a post via two hand-rolled ACF relationship hops (`rel`, then
 * `rel_2`). Both the traversal and that option vocabulary are retired: the engine chains
 * arbitrary steps and reads the relationship field from `ref`. resolve_id() is inert and
 * documents why; the registration stays so the entry still carries its source key and
 * context type for the admin surfaces.
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

}
