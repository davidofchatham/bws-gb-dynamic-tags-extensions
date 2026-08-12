<?php
/**
 * Abstract base class for sources.
 *
 * Provides sensible defaults for all SourceInterface methods. Concrete sources
 * override only what differs from the defaults.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Added defaults for tag prefix, context type, and related variant methods.
 * @since 1.2.0 Added format_id_for_acf(), source_default_enabled(), related_variant_default_enabled().
 * @since 1.4.1 Added tag_default_enabled().
 * @since 1.5.0 Removed related-variant method defaults; added needs_relationship_field(), get_ui_group().
 * @since 1.6.0 Removed get_title_prefix() and get_traversal_options().
 * @since 1.17.0 Added is_selectable_root() default (false) (#83).
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractSource implements SourceInterface {

	public function get_tag_prefix(): string {
		return $this->get_source_key();
	}

	public function get_gb_type(): string {
		return 'post';
	}

	public function get_context_type(): string {
		return 'post';
	}

	public function get_effective_source_id(): string {
		return $this->get_tag_prefix();
	}

	/**
	 * Format the resolved entity ID for ACF. Post-based sources return unchanged.
	 * Override in non-post sources (e.g. TaxonomyTerm returns "term_{$id}").
	 *
	 * @since 1.2.0
	 * @param int|string $id Resolved entity ID.
	 * @return int|string
	 */
	public function format_id_for_acf( $id ) {
		return $id;
	}

	/**
	 * Whether direct tags from this source are enabled by default.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public function source_default_enabled(): bool {
		return true;
	}

	/**
	 * Whether individual tags from this source are enabled by default.
	 * Distinct from source_default_enabled(), which controls the source toggle default.
	 * Delegates to source_default_enabled() so existing sources need no override.
	 *
	 * @since 1.4.1
	 * @return bool
	 */
	public function tag_default_enabled(): bool {
		return $this->source_default_enabled();
	}

	/**
	 * Get supports to exclude when generating post-context direct tags for this source.
	 *
	 * @since 1.3.0
	 * @return string[]
	 */
	public function get_excluded_supports(): array {
		return [];
	}

	/**
	 * @since 1.5.0
	 * @return bool
	 */
	public function needs_relationship_field(): bool {
		return false;
	}

	/**
	 * @since 1.5.0
	 * @return string
	 */
	public function get_ui_group(): string {
		return $this->get_context_type();
	}

	/**
	 * Not offerable as a chain root unless a source says otherwise (#83).
	 *
	 * FALSE is the default because the registry keeps its dead: the four in-repo
	 * traversal-substitute sources are inert by decision, and `post`/`term` would promote
	 * to roots that duplicate Current and collide with the planned pinned-entity spelling.
	 * Opting in is a claim that this source resolves its own id from ambient context —
	 * see SourceInterface::is_selectable_root() for the full precondition.
	 *
	 * @since 1.17.0
	 * @return bool
	 */
	public function is_selectable_root(): bool {
		return false;
	}

}
