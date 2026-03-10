<?php
/**
 * Second Related Post source - resolves to a post via two ACF relationship hops.
 *
 * Hop 1: current post → 'rel'  field → first related post (mid).
 * Hop 2: mid post     → 'rel_2' field → first second-degree related post.
 *
 * All tags from this source are opt-in (source_default_enabled() returns false).
 * No related-variant is available from this source (has_related_variant() = false).
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
		return __( 'Second Related Post (ACF)', 'generateblocks' );
	}

	/**
	 * All second-related-post tags are opt-in — two-hop traversal is an advanced feature.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public function source_default_enabled(): bool {
		return false;
	}

	/**
	 * Resolve to the second-degree related post via two ACF relationship hops.
	 *
	 * @param array  $options  Tag options ('rel' = first hop field, 'rel_2' = second hop field).
	 * @param object $instance Block instance.
	 * @return int|false Post ID or false if unresolvable.
	 */
	public function resolve_id( array $options, $instance ) {
		if ( ! class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			return false;
		}

		$base = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );
		$rel1 = $options['rel'] ?? '';

		if ( ! $base || ! $rel1 ) {
			return false;
		}

		$hop1 = bws_get_related_posts_data( (int) $base, $rel1 );
		$mid  = ! empty( $hop1 ) ? bws_extract_post_id( $hop1[0] ) : false;

		$rel2 = $options['rel_2'] ?? '';

		if ( ! $mid || ! $rel2 ) {
			return false;
		}

		$hop2 = bws_get_related_posts_data( (int) $mid, $rel2 );

		return ! empty( $hop2 ) ? bws_extract_post_id( $hop2[0] ) : false;
	}

	/**
	 * Source options: two relationship field keys (first hop and second hop).
	 *
	 * @return array
	 */
	public function get_source_options(): array {
		return array_merge(
			bws_get_relationship_field_options(),        // 'rel'   — first hop
			bws_get_second_relationship_field_options()  // 'rel_2' — second hop
		);
	}
}
