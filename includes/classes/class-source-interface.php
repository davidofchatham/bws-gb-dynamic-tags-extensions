<?php
/**
 * Source Interface for dynamic tag sources.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Renamed resolve_post_id() to resolve_id(); added tag prefix, context type, and related variant methods.
 * @since 1.2.0 Added format_id_for_acf(), source_default_enabled(), related_variant_default_enabled().
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
	 * Get the human-readable title prefix for tag display names.
	 *
	 * @return string e.g. 'Post', 'Term', 'Portal'.
	 */
	public function get_title_prefix(): string;

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
	 * Whether this source supports related-variant tag generation.
	 *
	 * @return bool
	 */
	public function has_related_variant(): bool;

	/**
	 * Get the tag name prefix for related-variant tags.
	 *
	 * @return string e.g. 'related_post', 'portal_related_post'.
	 */
	public function get_related_tag_prefix(): string;

	/**
	 * Get the human-readable title prefix for related-variant tags.
	 *
	 * @return string e.g. 'Related Post', 'Portal Related Post'.
	 */
	public function get_related_title_prefix(): string;

	/**
	 * Get the GenerateBlocks tag type for related-variant tags.
	 *
	 * @return string e.g. 'related'.
	 */
	public function get_related_gb_type(): string;

	/**
	 * Get the effective source identifier for try_ tag src_N option values (direct).
	 *
	 * @return string Single-word identifier, e.g. 'post', 'portal'.
	 */
	public function get_effective_source_id(): string;

	/**
	 * Get the effective source identifier for try_ tag src_N option values (related variant).
	 *
	 * @return string e.g. 'related', 'portal_related'.
	 */
	public function get_related_effective_source_id(): string;

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
	 * Whether related-variant tags from this source are enabled by default in admin settings.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public function related_variant_default_enabled(): bool;
}
