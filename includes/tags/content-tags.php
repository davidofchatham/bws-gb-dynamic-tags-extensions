<?php
/**
 * Post content core functions.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.1.0 Added bws_post_content_core().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ===============================================
// OPTIONS FUNCTIONS
// ===============================================

// ===============================================
// CORE FUNCTIONS
// ===============================================

/**
 * Post Content core logic — shared by template-generated post_content and legacy callback.
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_post_content_core( $post_id, $options, $instance ) {
	$type = $options['type'] ?? '';

	$fallback = sanitize_text_field( $options['fallback'] ?? '' );

	// --- Custom field branch ---
	if ( 'custom_field' === $type ) {
		$is_loop_row = bws_get_loop_row_context( $instance )['in_loop'];

		if ( ! $post_id && ! $is_loop_row ) {
			return '' !== $fallback
				? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
				: '';
		}

		$key = sanitize_text_field( $options['key'] ?? '' );

		if ( empty( $key ) || ! bws_is_valid_meta_key( $key ) ) {
			return '' !== $fallback
				? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
				: '';
		}

		$raw   = bws_read_field( $key, $instance, $post_id );
		$value = ( is_scalar( $raw ) && null !== $raw && false !== $raw && '' !== $raw ) ? (string) $raw : null;

		if ( null === $value || '' === $value ) {
			return '' !== $fallback
				? GenerateBlocks_Dynamic_Tag_Callbacks::output(
					$fallback,
					array_merge( $options, array( 'id' => $post_id ) ),
					$instance
				)
				: '';
		}

		return GenerateBlocks_Dynamic_Tag_Callbacks::output(
			$value,
			array_merge( $options, array( 'id' => $post_id ) ),
			$instance
		);
	}

	// --- Default content branch ---
	if ( ! $post_id ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
			: '';
	}

	// Skip during GB query loop setup phase (no real iteration yet).
	if ( bws_is_query_loop_setup_phase( $instance ) ) {
		return '';
	}

	$content = bws_process_post_content( $post_id );

	if ( empty( $content ) ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
			: '';
	}

	return bws_safe_content_output( $content, $options, $instance );
}

/**
 * Post title core.
 *
 * Merges resolved $post_id into options before calling output() so GB's with_link()
 * uses the correct post for link generation (not the base/source post).
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_post_title_core( $post_id, $options, $instance ) {
	if ( ! $post_id ) {
		return '';
	}
	$title = get_the_title( $post_id );
	return GenerateBlocks_Dynamic_Tag_Callbacks::output(
		$title,
		array_merge( $options, array( 'id' => $post_id ) ),
		$instance
	);
}

/**
 * Post excerpt core.
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_post_excerpt_core( $post_id, $options, $instance ) {
	if ( ! $post_id ) {
		return '';
	}
	$excerpt = get_the_excerpt( $post_id );
	return GenerateBlocks_Dynamic_Tag_Callbacks::output(
		$excerpt,
		array_merge( $options, array( 'id' => $post_id ) ),
		$instance
	);
}

/**
 * Post permalink core.
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_post_permalink_core( $post_id, $options, $instance ) {
	if ( ! $post_id ) {
		return '';
	}
	$permalink = get_permalink( $post_id );
	return GenerateBlocks_Dynamic_Tag_Callbacks::output(
		esc_url( $permalink ),
		array_merge( $options, array( 'id' => $post_id ) ),
		$instance
	);
}

/**
 * Post custom text core.
 *
 * Reads the field key from $options['key'] (set by GB's 'meta' support).
 * Unlike GB's built-in post_meta, correctly returns '0' when the field value
 * is the integer or string zero (uses is_scalar() rather than empty()).
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options. 'key' is the field name.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_post_custom_text_core( $post_id, $options, $instance ) {
	$fallback = sanitize_text_field( $options['fallback'] ?? '' );

	$is_loop_row = bws_get_loop_row_context( $instance )['in_loop'];

	if ( ! $post_id && ! $is_loop_row ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
			: '';
	}

	$key = sanitize_text_field( $options['key'] ?? '' );

	if ( empty( $key ) || ! bws_is_valid_meta_key( $key ) ) {
		return '';
	}

	$raw   = bws_read_field( $key, $instance, $post_id );
	$value = ( is_scalar( $raw ) && null !== $raw && false !== $raw && '' !== $raw ) ? (string) $raw : null;

	if ( null === $value || '' === $value ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output(
				$fallback,
				array_merge( $options, array( 'id' => $post_id ) ),
				$instance
			)
			: '';
	}

	return GenerateBlocks_Dynamic_Tag_Callbacks::output(
		$value,
		array_merge( $options, array( 'id' => $post_id ) ),
		$instance
	);
}
