<?php
/**
 * Related Post source - resolves to the first post in an ACF relationship/post_object field.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RelatedPost extends AbstractSource {

	public function get_source_key(): string {
		return 'related_post';
	}

	public function get_source_label(): string {
		return __( 'Post → Rel. Post', 'generateblocks' );
	}

	public function get_tag_prefix(): string {
		return 'related_post';
	}

	public function get_gb_type(): string {
		return 'related';
	}

	public function get_excluded_supports(): array {
		return array( 'source', 'link' );
	}

	public function get_effective_source_id(): string {
		return 'related';
	}

	public function needs_relationship_field(): bool {
		return true;
	}

	/**
	 * Inert since 1.17.0 — the relationship hop is the `ref` step's job (issue #56).
	 *
	 * This used to answer "which field holds the relationship?" from `rel`, falling back
	 * to a legacy `key`. Nothing else in the plugin reads either spelling: the chain
	 * compiler builds its `refs` step from `ref` alone (bws_fold_chain_from_options), and
	 * so does the modifier callback's traversal arm. Two readers of one question, on
	 * DISJOINT vocabularies, is what made a hand-typed `key:` silently read the ambient
	 * entity under `src:ref` while resolving correctly under `src:related_post`.
	 *
	 * The `key` fallback was the sharper half: `key` is the live FIELD-key option on every
	 * tag, so on a try_ slot spelled `2-src:related_post|2-key:job_title` this read
	 * `job_title` as a relationship field — a wrong read, not an empty one.
	 *
	 * Returning false makes the source non-self-resolving, so bws_factory_registry_source()
	 * falls through to the ambient entity. Stored wire naming this source is rewritten to
	 * `src:ref|ref:<field>` by bws_migrate_related_post_src() (deprecated-tags.php), which
	 * is the ONLY thing that keeps such a tag reading what it read before — a tag the
	 * converter never reaches resolves the ambient entity instead.
	 *
	 * REGISTRATION STAYS. Never delete a register() call just for lacking resolve logic;
	 * the entry still carries the source key + context type, and the Tag Converter and
	 * settings page read the registry.
	 *
	 * @since 1.0.0
	 * @since 1.17.0 Inert; the generic `ref` step owns the hop (#56).
	 * @param array  $options  Unused.
	 * @param object $instance Unused.
	 * @return false Always — see above.
	 */
	public function resolve_id( array $options, $instance ) {
		return false;
	}

	public function get_source_options(): array {
		return array(); // Relationship field key is provided via the 'rel' custom option on each tag.
	}

}
