<?php
/**
 * Image dynamic tags for GenerateBlocks.
 *
 * Tags: post_featured_image, related_post_featured_image,
 *       post_custom_image, related_post_custom_image
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWS\DynamicTags\Admin\SettingsPage;

// Image tags are registered via the template system (TagTemplateRegistry::generate_all_tags()).

/**
 * Register image dynamic tag templates.
 *
 * @since 1.2.0
 */
function bws_register_image_tag_templates() {
	// featured_image: post sources only. No 'source' support — for media-type tags, $options['id']
	// is the fallback ATTACHMENT ID (media library selector), not a post ID.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'           => 'featured_image',
		'title'         => 'Featured Image',
		'gb_type'       => 'media',
		'supports'      => array( 'image-size' ),
		'options_fn'    => 'bws_get_image_return_type_options',
		'core_fn'       => 'bws_featured_image_core',
		'context_types' => array( 'post' ),
		'supports_try'  => true,
	) );

	// custom_image: post and term sources.
	// Post context: gb_type='media', fallback attachment from media library selector.
	// Term context: generate_all_tags() uses source gb_type='term', adds 'source' support,
	//   calls term_options_fn for term-specific options (URL fallback, not media library).
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'              => 'custom_image',
		'title'            => 'Custom Image',
		'gb_type'          => 'media',
		'supports'         => array( 'image-size' ),
		'options_fn'       => 'bws_get_meta_and_return_type_options',
		'core_fn'          => 'bws_custom_image_core',
		'context_types'    => array( 'post', 'term' ),
		'term_core_fn'     => 'bws_term_custom_image_core',
		'term_options_fn'  => 'bws_get_term_image_and_return_type_options',
		'supports_try'     => true,
		'try_per_slot_key' => true,
	) );
}

// ===============================================
// MEDIA ID OVERRIDE FOR POST CONTEXT
// ===============================================

/**
 * Override media IDs to provide post context for image tags.
 *
 * @since 1.0.0
 * @param int    $id       Original ID.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
 * @return int Modified ID.
 */
function bws_override_media_ids_for_post_context( $id, $options, $instance ) {
	$tag_name = $options['tag_name'] ?? '';

	$post_context_media_tags = array(
		'image',
		'post_featured_image',
		'related_post_featured_image',
		'post_custom_image',
		'related_post_custom_image',
		// Deprecated names.
		'current_post_featured_image',
		'current_post_meta_image',
		'related_post_meta_image',
	);

	if ( in_array( $tag_name, $post_context_media_tags, true ) ) {
		$current_post_id = get_the_ID();

		if ( $current_post_id ) {
			return $current_post_id;
		}
	}

	return $id;
}
add_filter( 'generateblocks_dynamic_tag_id', 'bws_override_media_ids_for_post_context', 10, 3 );

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
	$return_type       = $options['as'] ?? $options['return_type'] ?? 'url';
	$image_size        = $options['size'] ?? 'full';
	$fallback_media_id = $options['id'] ?? '';

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
	$return_type       = sanitize_text_field( $options['as'] ?? $options['return_type'] ?? 'url' );
	$image_size        = sanitize_text_field( $options['size'] ?? 'full' );
	$fallback_media_id = absint( $options['id'] ?? 0 );

	if ( empty( $field_key ) ) {
		return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
	}

	if ( ! bws_is_valid_meta_key( $field_key ) ) {
		return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
	}

	if ( $post_id ) {
		$result = bws_get_meta_image_data( $post_id, $field_key, $return_type, $image_size );

		if ( ! empty( $result ) ) {
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
		}
	}

	return bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance );
}

