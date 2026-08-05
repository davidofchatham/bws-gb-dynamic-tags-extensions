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
	 * No options, since 1.17.0 (#56).
	 *
	 * This used to return the two relationship field keys the hops read (`rel`, `rel_2`).
	 * With resolve_id() inert they would be controls advertising a traversal that cannot
	 * fire — an author could fill them in and get nothing, with no way to tell why. An
	 * inert resolver and a live option surface is the worse half of both states.
	 *
	 * @since 1.17.0 Emptied alongside resolve_id() (#56).
	 * @return array
	 */
	public function get_source_options(): array {
		return array();
	}

}
