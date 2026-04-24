<?php
/**
 * Source Interface for dynamic tag sources.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Renamed resolve_post_id() to resolve_id(); added tag prefix, context type, and related variant methods.
 * @since 1.2.0 Added format_id_for_acf(), source_default_enabled(), related_variant_default_enabled().
 * @since 1.4.1 Added tag_default_enabled().
 * @since 1.5.0 Removed related-variant methods; added needs_relationship_field(), get_ui_group().
 * @since 1.6.0 Removed get_title_prefix() and get_traversal_options().
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SourceInterface {

	/**
	 * Get the unique source key (e.g. 'post', 'term', 'portal').
	 *
	 * @return string
	 */
	public function get_source_key(): string;

	/**
	 * Get the human-readable label for admin display.
	 *
	 * @return string
	 */
	public function get_source_label(): string;

	/**
	 * Resolve the target entity ID from tag options and block instance.
	 *
	 * @param array  $options  Tag options from GenerateBlocks.
	 * @param object $instance Block instance.
	 * @return int|string|false Entity ID or false if unresolvable.
	 */
	public function resolve_id( array $options, $instance );

	/**
	 * Get extra options needed by this source for the tag editor UI.
	 *
	 * @return array Options array in GenerateBlocks format.
	 */
	public function get_source_options(): array;

	/**
	 * Get the tag name prefix for tags generated from this source.
	 *
	 * @return string e.g. 'post', 'term', 'portal'.
	 */
	public function get_tag_prefix(): string;

	/**
	 * Get the GenerateBlocks tag type for direct tags from this source.
	 *
	 * @return string e.g. 'post', 'term'.
	 */
	public function get_gb_type(): string;

	/**
	 * Get the context type — determines which templates apply to this source.
	 *
	 * @return string 'post' or 'term'.
	 */
	public function get_context_type(): string;

	/**
	 * Get the effective source identifier for try_ tag src_N option values (direct).
	 *
	 * @return string Single-word identifier, e.g. 'post', 'portal'.
	 */
	public function get_effective_source_id(): string;

	/**
	 * Format the resolved entity ID for use as an ACF object_id parameter.
	 *
	 * Post-based sources return the ID unchanged. Non-post sources override this:
	 * TaxonomyTerm returns "term_{$id}"; future User source would return "user_{$id}".
	 *
	 * @since 1.2.0
	 * @param int|string $id Resolved entity ID.
	 * @return int|string ACF-compatible object_id.
	 */
	public function format_id_for_acf( $id );

	/**
	 * Whether direct tags from this source are enabled by default in admin settings.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public function source_default_enabled(): bool;

	/**
	 * Whether individual tags from this source are enabled by default in admin settings
	 * (when the source toggle itself is on and no per-tag setting has been saved).
	 *
	 * Distinct from source_default_enabled(), which controls the source toggle default.
	 * Defaults to source_default_enabled() so existing sources require no override.
	 *
	 * @since 1.4.1
	 * @return bool
	 */
	public function tag_default_enabled(): bool;

	/**
	 * Get supports to exclude when generating tags for this source.
	 *
	 * Allows a source to strip supports that are not applicable to its ID resolution
	 * strategy. For example, a source that resolves its own post ID (rather than using
	 * GB's post selector) can return ['source'] to hide the post picker UI.
	 *
	 * Only applied to post-context direct tags.
	 *
	 * @since 1.3.0
	 * @return string[] Supports to strip from template supports arrays.
	 */
	public function get_excluded_supports(): array;

	/**
	 * Whether this source requires a relationship field option to resolve.
	 * Traversal sources (RelatedPost, TermRelatedPost, etc.) return true.
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public function needs_relationship_field(): bool;

	/**
	 * Returns the UI group key this source belongs to in the admin matrix.
	 * Defaults to get_context_type(). Override if the source should appear in a different group.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	public function get_ui_group(): string;
}
