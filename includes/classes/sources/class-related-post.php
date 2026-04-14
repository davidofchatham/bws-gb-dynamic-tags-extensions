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

	public function resolve_id( array $options, $instance ) {
		if ( ! class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			return false;
		}

		$current_post_id = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );

		// Prefer 'rel' (new dedicated option); fall back to legacy 'key' (old 'meta' support).
		if ( ! empty( $options['rel'] ) ) {
			$rel_field_key = $options['rel'];
		} elseif ( ! empty( $options['key'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				_doing_it_wrong(
					'BWS Related Post source',
					__( 'The "key" option for relationship field resolution is deprecated. Use the "Relationship Field Key" (rel) option instead.', 'generateblocks' ),
					'4.1.0'
				);
			}
			$rel_field_key = $options['key'];
		} else {
			$rel_field_key = '';
		}

		if ( ! $current_post_id || empty( $rel_field_key ) ) {
			return false;
		}

		$related_posts = bws_get_related_posts_data( $current_post_id, $rel_field_key );

		if ( empty( $related_posts ) ) {
			return false;
		}

		return bws_extract_post_id( $related_posts[0] );
	}

	public function get_source_options(): array {
		return array(); // Relationship field key is provided via the 'rel' custom option on each tag.
	}

	/**
	 * Traversal options for the base tag via-dispatch system.
	 *
	 * @since 1.6.0
	 * @return array
	 */
	public function get_traversal_options(): array {
		return array(
			'ref' => array(
				'type'        => 'text',
				'label'       => __( 'Traverse by meta key:', 'generateblocks' ),
				'help'        => __( 'ACF relationship or post object field key that links to the related post.', 'generateblocks' ),
				'placeholder' => 'related_posts',
			),
		);
	}
}
