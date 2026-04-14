<?php
/**
 * Post → Term → Related Post source.
 *
 * 3-hop traversal from post context:
 *   Hop 1: current post → first term in `taxonomy`
 *   Hop 2: that term    → first related post via `rel` field on the term entity
 *
 * Note: only the first term in the taxonomy is used. If the post has multiple terms,
 * the first returned by get_the_terms() is used.
 *
 * The source toggle is off by default. When enabled, all tags are on by default.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.4.1
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostTermRelatedPost extends AbstractSource {

	public function get_source_key(): string {
		return 'post_term_related_post';
	}

	public function get_source_label(): string {
		return __( 'Term → Ref/Rel Field', 'generateblocks' );
	}

	/**
	 * Source is enabled by default for discoverability.
	 *
	 * @since 1.4.1
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
	 * Exclude 'source' support — traversal always starts from the current post.
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
	 * Resolve to the related post via: current post → first term → relationship field on term.
	 *
	 * @param array  $options  Tag options ('taxonomy' = taxonomy slug, 'rel' = field key on term).
	 * @param object $instance Block instance.
	 * @return int|false Post ID or false if unresolvable.
	 */
	public function resolve_id( array $options, $instance ) {
		if ( ! class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			return false;
		}

		$post_id  = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
		$taxonomy = $options['taxonomy'] ?? '';
		$rel      = $options['rel'] ?? '';

		if ( ! $post_id || ! $taxonomy || ! $rel ) {
			return false;
		}

		// Hop 1: post → first term in taxonomy.
		$terms = get_the_terms( (int) $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return false;
		}
		$term_id = reset( $terms )->term_id;

		// Hop 2: term → related post via relationship field on term entity.
		$related = bws_get_related_posts_data( 'term_' . $term_id, $rel );
		if ( empty( $related ) ) {
			return false;
		}

		return bws_extract_post_id( $related[0] );
	}

	/**
	 * Source options: taxonomy (hop 1) then relationship field key (hop 2, on the term entity).
	 *
	 * @return array
	 */
	public function get_source_options(): array {
		return array(
			'taxonomy' => array(
				'type'        => 'text',
				'label'       => __( 'Taxonomy', 'generateblocks' ),
				'help'        => __( 'Taxonomy slug used to find the post\'s term (e.g. category, post_tag). The first term is used.', 'generateblocks' ),
				'placeholder' => 'category',
			),
			'rel'      => array(
				'type'        => 'text',
				'label'       => __( 'Relationship Field Key', 'generateblocks' ),
				'help'        => __( 'Relationship or post object field key on the term that links to the related post.', 'generateblocks' ),
				'placeholder' => 'related_post',
			),
		);
	}

	/**
	 * Traversal options for the base tag via-dispatch system (tax → ref chain).
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public function get_traversal_options(): array {
		return array(
			'tax' => array(
				'type'        => 'text',
				'label'       => __( 'First traverse by taxonomy:', 'generateblocks' ),
				'help'        => __( 'Taxonomy slug used to find the post\'s term (e.g. category, post_tag). The first term is used.', 'generateblocks' ),
				'placeholder' => 'category',
			),
			'ref' => array(
				'type'        => 'text',
				'label'       => __( 'Then traverse by meta key:', 'generateblocks' ),
				'help'        => __( 'ACF relationship or post object field key on the term that links to the related post.', 'generateblocks' ),
				'placeholder' => 'related_posts',
			),
		);
	}
}
