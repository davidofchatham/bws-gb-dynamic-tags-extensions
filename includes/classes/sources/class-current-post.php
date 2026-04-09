<?php
/**
 * Post source - resolves to a post in context (current loop post or specific post via 'source' support).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.2.0 Renamed source key from 'current_post' to 'post'. Added tag prefix,
 *              related variant, and related effective source methods.
 */

namespace BWS\DynamicTags\Sources;

use BWS\DynamicTags\AbstractSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CurrentPost extends AbstractSource {

	public function get_source_key(): string {
		return 'post';
	}

	public function get_source_label(): string {
		return __( 'Post', 'generateblocks' );
	}

	public function get_tag_prefix(): string {
		return 'post';
	}

	public function get_title_prefix(): string {
		return __( 'Post', 'generateblocks' );
	}

	public function get_gb_type(): string {
		return 'post';
	}

	public function get_effective_source_id(): string {
		return 'post';
	}

	public function resolve_id( array $options, $instance ) {
		if ( ! class_exists( 'GenerateBlocks_Dynamic_Tags' ) ) {
			return false;
		}

		$post_id = \GenerateBlocks_Dynamic_Tags::get_id( $options, 'post', $instance );

		return $post_id ? (int) $post_id : false;
	}

	public function get_source_options(): array {
		return array();
	}
}
