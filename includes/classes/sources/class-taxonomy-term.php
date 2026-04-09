<?php
/**
 * Taxonomy Term source - resolves to a term in context (archive, source selector, or post assignment).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.2.0
 * @since 1.2.0 Added format_id_for_acf().
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxonomyTerm extends AbstractSource {

	public function get_source_key(): string {
		return 'term';
	}

	public function get_source_label(): string {
		return __( 'Term', 'generateblocks' );
	}

	public function get_tag_prefix(): string {
		return 'term';
	}

	public function get_title_prefix(): string {
		return __( 'Term', 'generateblocks' );
	}

	public function get_gb_type(): string {
		return 'term';
	}

	public function get_context_type(): string {
		return 'term';
	}

	/**
	 * Format term ID as ACF object_id for relationship field traversal.
	 *
	 * ACF expects "term_{$id}" when querying fields on a term entity.
	 *
	 * @since 1.2.0
	 * @param int|string $id Resolved term ID.
	 * @return string
	 */
	public function format_id_for_acf( $id ) {
		return 'term_' . $id;
	}

	/**
	 * Resolve the term ID from tag options and context.
	 *
	 * Uses GB's canonical term resolver first (consistent with GB Pro's term_meta),
	 * then falls back to our multi-method detection for broader context support.
	 *
	 * @param array  $options  Tag options from GenerateBlocks.
	 * @param object $instance Block instance.
	 * @return int|false Term ID or false if unresolvable.
	 */
	public function resolve_id( array $options, $instance ) {
		if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			$id = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'term', $instance );
			if ( $id ) {
				return (int) $id;
			}
		}

		// Fallback: our multi-method detection (handles term_id option, queried object, taxonomy+post).
		if ( function_exists( 'bws_reliable_term_context_detection' ) ) {
			return bws_reliable_term_context_detection( $options );
		}

		return false;
	}

	public function get_source_options(): array {
		return array();
	}
}
