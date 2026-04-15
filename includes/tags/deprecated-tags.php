<?php
/**
 * Deprecated tag wrappers for backward compatibility.
 *
 * These tags register old names that delegate to their replacements.
 * They emit _doing_it_wrong() notices when WP_DEBUG is enabled.
 *
 * Deprecated tags:
 *   current_post_featured_image  → post_featured_image
 *   current_post_meta_image      → post_custom_image
 *   related_post_meta_image      → related_post_custom_image
 *   related_post_url             → related_post_permalink
 *   post_acf_date_time_single    → post_acf_datetime_single
 *   post_acf_date_time_range     → post_acf_datetime_range
 *   term_name                    → term_title
 *   term_field_image             → term_custom_image
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWS\DynamicTags\Admin\SettingsPage;

/**
 * Register deprecated dynamic tags (old names that delegate to new ones).
 *
 * @since 1.0.0
 */
function bws_register_deprecated_tags() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	// current_post_featured_image → post_featured_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'current_post_featured_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post Featured Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'current_post_featured_image',
				'type'        => 'media',
				'supports'    => array( 'image-size' ),
				'description' => __( 'Deprecated — use "Post Featured Image" instead.', 'generateblocks' ),
				'options'     => bws_get_image_return_type_options(),
				'return'      => 'bws_deprecated_current_post_featured_image_callback',
			)
		);
	}

	// current_post_meta_image → post_custom_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'current_post_meta_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post Custom Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'current_post_meta_image',
				'type'        => 'media',
				'supports'    => array( 'image-size' ),
				'description' => __( 'Deprecated — use "Post Custom Image" instead.', 'generateblocks' ),
				'options'     => array_merge(
					bws_get_meta_image_options(),
					bws_get_image_return_type_options()
				),
				'return'      => 'bws_deprecated_current_post_meta_image_callback',
			)
		);
	}

	// related_post_meta_image → related_post_custom_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'related_post_meta_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Related Post Custom Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'related_post_meta_image',
				'type'        => 'media',
				'supports'    => array( 'meta', 'image-size' ),
				'description' => __( 'Deprecated — use "Related Post Custom Image" instead.', 'generateblocks' ),
				'options'     => array_merge(
					bws_get_meta_image_options(),
					bws_get_image_return_type_options()
				),
				'return'      => 'bws_deprecated_related_post_meta_image_callback',
			)
		);
	}

	// related_post_url → related_post_permalink.
	if ( SettingsPage::is_deprecated_tag_enabled( 'related_post_url' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Related Post Permalink (Deprecated)', 'generateblocks' ),
				'tag'         => 'related_post_url',
				'type'        => 'related',
				'supports'    => array( 'meta' ),
				'description' => __( 'Deprecated — use "Related Post Permalink" instead.', 'generateblocks' ),
				'return'      => 'bws_deprecated_related_post_url_callback',
			)
		);
	}

	// post_acf_date_time_single → post_acf_datetime_single.
	if ( SettingsPage::is_deprecated_tag_enabled( 'post_acf_date_time_single' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post ACF Date/Time (Deprecated)', 'generateblocks' ),
				'tag'         => 'post_acf_date_time_single',
				'type'        => 'post',
				'supports'    => array( 'source' ),
				'description' => __( 'Deprecated — use "Post ACF Date/Time" instead.', 'generateblocks' ),
				'options'     => bws_get_datetime_single_options(),
				'return'      => 'bws_deprecated_post_acf_date_time_single_callback',
			)
		);
	}

	// post_acf_date_time_range → post_acf_datetime_range.
	if ( SettingsPage::is_deprecated_tag_enabled( 'post_acf_date_time_range' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post ACF Date/Time Range (Deprecated)', 'generateblocks' ),
				'tag'         => 'post_acf_date_time_range',
				'type'        => 'post',
				'supports'    => array( 'source' ),
				'description' => __( 'Deprecated — use "Post ACF Date/Time Range" instead.', 'generateblocks' ),
				'options'     => bws_get_datetime_range_options(),
				'return'      => 'bws_deprecated_post_acf_date_time_range_callback',
			)
		);
	}

	// term_name → term_title.
	if ( SettingsPage::is_deprecated_tag_enabled( 'term_name' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Term Name (Deprecated)', 'generateblocks' ),
				'tag'         => 'term_name',
				'type'        => 'term',
				'supports'    => array( 'source' ),
				'description' => __( 'Deprecated — use "Term Title" instead.', 'generateblocks' ),
				'return'      => 'bws_deprecated_term_name_callback',
			)
		);
	}

	// term_field_image → term_custom_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'term_field_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Term Field Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'term_field_image',
				'type'        => 'term',
				'supports'    => array( 'source', 'image-size' ),
				'description' => __( 'Deprecated — use "Term Custom Image" instead.', 'generateblocks' ),
				'options'     => array_merge(
					bws_get_term_image_field_options(),
					bws_get_image_return_type_options()
				),
				'return'      => 'bws_deprecated_term_field_image_callback',
			)
		);
	}

	// External deprecated tag wrappers registered via DeprecatedTagRegistry.
	foreach ( \BWS\DynamicTags\DeprecatedTagRegistry::get_all() as $entry ) {
		$old_tag = $entry['old_tag'] ?? '';
		if ( ! $old_tag || ! SettingsPage::is_deprecated_tag_enabled( $old_tag ) ) {
			continue;
		}
		$gb_args = array(
			'title'       => $entry['title'] ?? $old_tag,
			'tag'         => $old_tag,
			'type'        => $entry['gb_type'] ?? $sk,
			'supports'    => $entry['supports'] ?? array(),
			'description' => $entry['description']
							?? sprintf(
								/* translators: %s: replacement tag name */
								__( 'Deprecated — use "%s" instead.', 'generateblocks' ),
								$entry['new_tag'] ?? ''
							),
			'return'      => $entry['callback'],
		);
		if ( ! empty( $entry['options'] ) ) {
			$gb_args['options'] = $entry['options'];
		}
		new GenerateBlocks_Register_Dynamic_Tag( $gb_args );
	}
}
// Registration is called directly from bws_dynamic_tags_register_all() in the main plugin file.

// ===============================================
// DEPRECATED CALLBACK FUNCTIONS
// ===============================================

/**
 * Emit a deprecation notice for a renamed tag.
 *
 * Only triggers when WP_DEBUG is enabled, using WordPress's _doing_it_wrong().
 * Available for external plugins to call from their own deprecated tag callbacks.
 *
 * @since 1.0.0
 * @param string $old_tag The deprecated tag name.
 * @param string $new_tag The replacement tag name.
 * @param string $since   The plugin version when the tag was deprecated. Default '1.0.0'.
 */
function bws_deprecated_tag_notice( string $old_tag, string $new_tag, string $since = '1.0.0' ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		_doing_it_wrong(
			sprintf( 'Dynamic tag: %s', $old_tag ),
			sprintf(
				/* translators: 1: deprecated tag name, 2: replacement tag name */
				__( 'The "%1$s" dynamic tag is deprecated. Use "%2$s" instead.', 'generateblocks' ),
				$old_tag,
				$new_tag
			),
			$since
		);
	}
}

/**
 * current_post_featured_image → post_featured_image.
 */
function bws_deprecated_current_post_featured_image_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'current_post_featured_image', 'post_featured_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_featured_image_core( $post_id, $options, $instance );
}

/**
 * current_post_meta_image → post_custom_image.
 */
function bws_deprecated_current_post_meta_image_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'current_post_meta_image', 'post_custom_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_custom_image_core( $post_id, $options, $instance );
}

/**
 * related_post_meta_image → related_post_custom_image.
 */
function bws_deprecated_related_post_meta_image_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'related_post_meta_image', 'related_post_custom_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$base_id = $source ? $source->resolve_id( $options, $instance ) : false;
	$rel_key = $options['rel'] ?? $options['key'] ?? '';
	$post_id = false;
	if ( $base_id && $rel_key ) {
		$related = bws_get_related_posts_data( $base_id, $rel_key );
		$post_id = ! empty( $related ) ? bws_extract_post_id( $related[0] ) : false;
	}
	// In this deprecated tag, 'key' was the relationship field; image field was 'meta_key'.
	// Unset 'key' so bws_custom_image_core falls through to 'meta_key' for the image field.
	$image_options = $options;
	unset( $image_options['key'] );
	return bws_custom_image_core( $post_id, $image_options, $instance );
}

/**
 * related_post_url → related_post_permalink.
 */
function bws_deprecated_related_post_url_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'related_post_url', 'related_post_permalink' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$base_id = $source ? $source->resolve_id( $options, $instance ) : false;
	$rel_key = $options['rel'] ?? $options['key'] ?? '';
	$post_id = false;
	if ( $base_id && $rel_key ) {
		$related = bws_get_related_posts_data( $base_id, $rel_key );
		$post_id = ! empty( $related ) ? bws_extract_post_id( $related[0] ) : false;
	}
	return bws_post_permalink_core( $post_id, $options, $instance );
}

/**
 * post_acf_date_time_single → post_acf_datetime_single.
 */
function bws_deprecated_post_acf_date_time_single_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'post_acf_date_time_single', 'post_acf_datetime_single' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_datetime_single_core( $post_id, $options, $instance );
}

/**
 * post_acf_date_time_range → post_acf_datetime_range.
 */
function bws_deprecated_post_acf_date_time_range_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'post_acf_date_time_range', 'post_acf_datetime_range' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_datetime_range_core( $post_id, $options, $instance );
}

/**
 * term_name → term_title.
 */
function bws_deprecated_term_name_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'term_name', 'term_title' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'term' );
	$term_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_term_title_core( $term_id, $options, $instance );
}

/**
 * term_field_image → term_custom_image.
 */
function bws_deprecated_term_field_image_callback( $options, $block, $instance ) {
	bws_deprecated_tag_notice( 'term_field_image', 'term_custom_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'term' );
	$term_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_term_custom_image_core( $term_id, $options, $instance );
}
