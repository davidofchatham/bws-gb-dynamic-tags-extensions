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
 */

namespace BWS\DynamicTags;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractSource implements SourceInterface {

	public function get_tag_prefix(): string {
		return $this->get_source_key();
	}

	public function get_title_prefix(): string {
		return $this->get_source_label();
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
	 * Get sanitized fallback text from options.
	 *
	 * @param array $options Tag options.
	 * @return string
	 */
	protected function get_fallback_text( array $options ): string {
		return sanitize_text_field( $options['fallback_text'] ?? '' );
	}
}
