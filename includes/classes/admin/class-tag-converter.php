<?php
/**
 * Converter utility for migrating deprecated tag strings in post content.
 *
 * Given a deprecated tag name, scans wp_posts.post_content for occurrences,
 * applies DeprecatedTagRegistry::transform_options() (following the full
 * deprecated chain in a single pass), updates matched posts, and returns
 * the number of posts changed.
 *
 * Triggered via AJAX from the deprecated tag section of the settings page.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 */

namespace BWS\DynamicTags\Admin;

use BWS\DynamicTags\DeprecatedTagRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TagConverter {

	// ===============================================
	// PUBLIC API
	// ===============================================

	/**
	 * Convert all occurrences of a deprecated tag in post content.
	 *
	 * Searches all non-trashed, non-auto-draft posts whose content contains
	 * the deprecated tag name pattern. For each match, resolves the full
	 * deprecated chain in a single pass and writes the migrated tag string.
	 * Posts whose content does not change after transformation are not updated.
	 *
	 * @since 1.6.0
	 * @param string $old_tag_name Deprecated GB tag name to convert.
	 * @return int Number of posts whose content was updated.
	 */
	public static function convert( string $old_tag_name ): int {
		global $wpdb;

		// Pre-filter: only load posts that plausibly contain the tag.
		$like_pattern = '%' . $wpdb->esc_like( '{{' . $old_tag_name ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts}
				 WHERE post_status NOT IN ('auto-draft', 'trash')
				 AND post_content LIKE %s",
				$like_pattern
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		$pattern       = '/\{\{' . preg_quote( $old_tag_name, '/' ) . '(\s[^}]*)?\}\}/';
		$updated_count = 0;

		foreach ( $posts as $post ) {
			$new_content = preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( $old_tag_name ): string {
					return self::resolve_full_chain( $old_tag_name, $matches[0] );
				},
				$post->post_content
			);

			if ( null !== $new_content && $new_content !== $post->post_content ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->posts,
					array( 'post_content' => $new_content ),
					array( 'ID' => (int) $post->ID ),
					array( '%s' ),
					array( '%d' )
				);
				++$updated_count;
			}
		}

		return $updated_count;
	}

	/**
	 * AJAX handler for the Convert button on the settings page.
	 *
	 * Expects POST fields: nonce, tag_name.
	 * Returns JSON: { success: true, data: { count: N } } on success.
	 *
	 * @since 1.6.0
	 */
	public static function ajax_handler(): void {
		check_ajax_referer( 'bws_convert_tag', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Permission denied.', 'generateblocks' ) ),
				403
			);
		}

		$tag_name = sanitize_key( wp_unslash( $_POST['tag_name'] ?? '' ) );
		if ( '' === $tag_name ) {
			wp_send_json_error(
				array( 'message' => __( 'Invalid tag name.', 'generateblocks' ) ),
				400
			);
		}

		$count = self::convert( $tag_name );
		wp_send_json_success( array( 'count' => $count ) );
	}

	// ===============================================
	// PRIVATE HELPERS
	// ===============================================

	/**
	 * Resolve the full deprecated chain for a single tag match.
	 *
	 * Follows multi-hop chains (A → B → C) in a single pass: after applying
	 * the transform for old_tag_name, checks whether the resulting new tag
	 * name is itself deprecated and continues until a non-deprecated target
	 * is reached or a circular reference is detected.
	 *
	 * @since 1.6.0
	 * @param string $old_tag_name Starting deprecated tag name.
	 * @param string $tag_string   Full raw tag string (e.g. `{{old_tag key:val}}`).
	 * @return string Final migrated tag string.
	 */
	private static function resolve_full_chain( string $old_tag_name, string $tag_string ): string {
		$seen    = array();
		$current = $old_tag_name;
		$string  = $tag_string;
		$max     = 10; // Guard against pathological chains.

		while ( $max-- > 0 ) {
			if ( in_array( $current, $seen, true ) ) {
				break; // Circular reference guard.
			}
			$seen[] = $current;

			$transformed = DeprecatedTagRegistry::transform_options( $current, $string );

			// transform_options() returns the original string when no entry exists.
			if ( $transformed === $string ) {
				break;
			}

			$string = $transformed;

			// Extract the new tag name from the serialized output.
			if ( ! preg_match( '/^\{\{(\S+)/', $string, $m ) ) {
				break;
			}
			$new_tag = $m[1];

			if ( $new_tag === $current ) {
				break;
			}
			$current = $new_tag;

			// Probe whether the new tag is itself in the registry.
			// transform_options() returns the same string when no entry is found.
			$probe = DeprecatedTagRegistry::transform_options( $current, $string );
			if ( $probe === $string ) {
				break; // $current is not deprecated; chain ends here.
			}
			// $current IS also deprecated; the next loop iteration applies its transform.
		}

		return $string;
	}
}
