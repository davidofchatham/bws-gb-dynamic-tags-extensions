<?php
/**
 * Term → Related Post source.
 *
 * @package BWS\DynamicTags
 * @since   1.5.0
 */

namespace BWS\DynamicTags\Sources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWS\DynamicTags\AbstractSource;

/**
 * Resolves from a term context to a related post via an ACF relationship field on the term.
 *
 * - Context type: post (resolves to a post)
 * - GB type: term (needs term selector in editor)
 * - UI group: term (appears in Term matrix)
 * - Requires a relationship field option (needs_relationship_field = true)
 *
 * @since 1.5.0
 */
class TermRelatedPost extends AbstractSource {

	public function get_source_key(): string {
		return 'term_related_post';
	}

	public function get_source_label(): string {
		return __( 'Term → Rel. Post', 'generateblocks' );
	}

	public function get_tag_prefix(): string {
		return 'term_related_post';
	}

	public function get_title_prefix(): string {
		return __( 'Term → Rel. Post', 'generateblocks' );
	}

	public function get_gb_type(): string {
		return 'term';
	}

	public function get_context_type(): string {
		return 'post';
	}

	public function get_ui_group(): string {
		return 'term';
	}

	public function source_default_enabled(): bool {
		return true;
	}

	public function needs_relationship_field(): bool {
		return true;
	}

	public function get_excluded_supports(): array {
		return array( 'source', 'link' );
	}

	public function get_effective_source_id(): string {
		return 'term_related';
	}

	public function resolve_id( array $options, $instance = null ) {
		// 1. Resolve term from context (same logic as TaxonomyTerm).
		$term_id = false;
		if ( class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			$term_id = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'term', $instance );
		}
		if ( ! $term_id && function_exists( 'bws_reliable_term_context_detection' ) ) {
			$term_id = bws_reliable_term_context_detection( $options );
		}
		if ( ! $term_id ) {
			return false;
		}

		// 2. Traverse ACF relationship field on the term to get a post ID.
		$rel = $options['rel'] ?? '';
		if ( ! $rel ) {
			return false;
		}

		$acf_id = 'term_' . $term_id;
		$value  = function_exists( 'get_field' ) ? get_field( $rel, $acf_id ) : null;
		if ( ! $value ) {
			return false;
		}

		return function_exists( 'bws_extract_post_id' ) ? bws_extract_post_id( $value ) : false;
	}

	public function get_source_options(): array {
		return array();
	}
}
