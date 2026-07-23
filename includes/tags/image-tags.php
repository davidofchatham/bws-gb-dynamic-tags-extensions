<?php
/**
 * Image dynamic tag cores for GenerateBlocks.
 *
 * Shared read cores for the `image` base tag and its try_/term_ arms:
 * bws_featured_image_core() (use:featured) and bws_custom_image_core() (field key).
 * Callers resolve the entity via the L1 factory and pass the id in — these cores
 * never resolve a source themselves (SPEC §V1: no ambient get_the_ID() fallback).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.14.1 GB `generateblocks_dynamic_tag_id` filter removed (dead since
 *               1.14.0 deprecated-tag removal; unreachable for `image`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWS\DynamicTags\Admin\SettingsPage;

// ===============================================
// CORE FUNCTIONS
// ===============================================

/**
 * Featured image core — shared by template-generated and legacy callbacks.
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options (as, size, id=fallback attachment).
 * @param object    $instance Block instance.
 * @return string
 */
function bws_featured_image_core( $post_id, $options, $instance ) {
	// as+size fold (FW-52): `as` may carry a `,<size>` arg; legacy `size:` falls back.
	$as                = bws_parse_as_option( $options );
	$return_type       = $as['mode'];
	$image_size        = $as['size'];
	$fallback_media_id = $options['fallback'] ?? $options['id'] ?? '';

	if ( $post_id ) {
		$featured_attachment_id = get_post_thumbnail_id( $post_id );

		if ( $featured_attachment_id ) {
			$result = bws_get_attachment_data( $featured_attachment_id, $return_type, $image_size );

			if ( ! empty( $result ) ) {
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
			}
		}
	}

	return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
}

/**
 * Custom image core — shared by template-generated and legacy callbacks.
 *
 * Accepts 'key' (template option name) with fallbacks to 'field_key' and 'meta_key' (legacy names).
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options (key/field_key/meta_key, as, size, id=fallback attachment).
 * @param object    $instance Block instance.
 * @return string
 */
function bws_custom_image_core( $post_id, $options, $instance ) {
	$field_key         = sanitize_text_field( $options['key'] ?? $options['field_key'] ?? $options['meta_key'] ?? '' );
	// as+size fold (FW-52): `as` may carry a `,<size>` arg; legacy `size:` falls back.
	$as                = bws_parse_as_option( $options );
	$return_type       = $as['mode'];
	$image_size        = $as['size'];
	$fallback_media_id = $options['fallback'] ?? $options['id'] ?? '';

	if ( empty( $field_key ) ) {
		return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
	}

	if ( ! bws_is_valid_meta_key( $field_key ) ) {
		return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
	}

	$is_loop_row = bws_get_loop_row_context( $instance )['in_loop'];

	if ( $post_id || $is_loop_row ) {
		$result = bws_get_meta_image_data( $post_id, $field_key, $return_type, $image_size, $instance );

		if ( ! empty( $result ) ) {
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
		}
	}

	return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
}

