<?php
/**
 * Post content core functions and tag template registration.
 *
 * Post content tags (title, content, excerpt, permalink, description, custom_text)
 * are registered via the template system (TagTemplateRegistry::generate_all_tags()).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.1.0 Added bws_post_content_core().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register post content dynamic tag templates.
 *
 * Registration order determines GB editor display order within each gb_type group.
 * Covers: title, content, excerpt, permalink, description (term-only), custom_text.
 *
 * @since 1.2.0
 */
function bws_register_post_content_tag_templates() {
	// title: post and term sources. Post source skipped by GB dup-check ('post_title' is built-in).
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'           => 'title',
		'title'         => 'Title',
		'gb_type'       => null,
		'supports'      => array( 'link', 'source' ),
		'options_fn'    => null,
		'core_fn'       => 'bws_post_title_core',
		'context_types' => array( 'post', 'term' ),
		'term_core_fn'  => 'bws_term_title_core',
		'supports_try'  => true,
	) );

	// content: post sources only for direct tags (excluded_direct_source_keys suppresses
	// term_content, which would be a confusing alias for term_description).
	// term_core_fn is set so try_content can dispatch term-context slots to description.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'                         => 'content',
		'title'                       => 'Content',
		'gb_type'                     => null,
		'supports'                    => array( 'source' ),
		'options_fn'                  => 'bws_get_content_options',
		'core_fn'                     => 'bws_post_content_core',
		'context_types'               => array( 'post', 'term' ),
		'term_core_fn'                => 'bws_term_description_core',
		'excluded_direct_source_keys' => array( 'term' ),
		'supports_try'                => true,
		'try_per_slot_type'           => true,
	) );

	// excerpt: post sources only. Term descriptions are handled by the 'description' template.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'           => 'excerpt',
		'title'         => 'Excerpt',
		'gb_type'       => null,
		'supports'      => array( 'source' ),
		'options_fn'    => null,
		'core_fn'       => 'bws_post_excerpt_core',
		'context_types' => array( 'post' ),
	) );

	// permalink: post and term sources.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'           => 'permalink',
		'title'         => 'Permalink',
		'gb_type'       => null,
		'supports'      => array( 'source' ),
		'options_fn'    => null,
		'core_fn'       => 'bws_post_permalink_core',
		'context_types' => array( 'post', 'term' ),
		'term_core_fn'  => 'bws_term_permalink_core',
		'supports_try'  => true,
	) );

	// description: term sources only. Placed here (after permalink) for correct term tag ordering:
	// term_title → term_permalink → term_description → term_custom_text → term_custom_image.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'           => 'description',
		'title'         => 'Description',
		'gb_type'       => null,
		'supports'      => array(),
		'options_fn'    => null,
		'core_fn'       => null,
		'context_types' => array( 'term' ),
		'term_core_fn'  => 'bws_term_description_core',
	) );

	// custom_text: post and term sources. Unlike GB's built-in post_meta, correctly returns
	// '0' for zero-value fields. Term variant requires GB Pro.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'                  => 'custom_text',
		'title'                => 'Custom Text',
		'gb_type'              => null,
		'supports'             => array( 'meta', 'link', 'source' ),
		'options_fn'           => 'bws_get_custom_text_options',
		'core_fn'              => 'bws_post_custom_text_core',
		'context_types'        => array( 'post', 'term' ),
		'term_core_fn'         => 'bws_term_custom_text_core',
		'term_requires_gb_pro' => true,
		'supports_try'         => true,
		'try_per_slot_key'     => true,
	) );
}

// ===============================================
// OPTIONS FUNCTIONS
// ===============================================

/**
 * Options for the content tag template.
 *
 * @since 1.4.0
 * @return array
 */
function bws_get_content_options() {
	return array(
		'type' => array(
			'type'    => 'select',
			'label'   => __( 'Content Type', 'generateblocks' ),
			// No 'default' key — '' is the visual default; nothing serialized when unchanged.
			'options' => array(
				array( 'value' => '',             'label' => __( 'Content / Description', 'generateblocks' ) ),
				array( 'value' => 'custom_field', 'label' => __( 'Custom Field', 'generateblocks' ) ),
			),
		),
		'key' => array(
			'type'        => 'text',
			'label'       => __( 'Meta Key', 'generateblocks' ),
			'help'        => __( 'ACF or meta field key.', 'generateblocks' ),
			'placeholder' => 'field_name',
			'show_if'     => array( 'type' => 'custom_field' ),
		),
		'fallback_text' => array(
			'type'  => 'text',
			'label' => __( 'Fallback Text', 'generateblocks' ),
			'help'  => __( 'Text to display if content is empty or not found.', 'generateblocks' ),
		),
	);
}

/**
 * Options for the custom_text tag template.
 *
 * @since 1.3.0
 * @return array
 */
function bws_get_custom_text_options() {
	return array(
		'fallback_text' => array(
			'type'  => 'text',
			'label' => __( 'Fallback Text', 'generateblocks' ),
			'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
		),
	);
}

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

	// REST placeholder — show type-appropriate hint in editor preview.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return 'custom_field' === $type ? '[Custom Field]' : '[Post Content Placeholder]';
	}

	$fallback = sanitize_text_field( $options['fallback_text'] ?? '' );

	// --- Custom field branch ---
	if ( 'custom_field' === $type ) {
		if ( ! $post_id ) {
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

		$value = null;

		if ( function_exists( 'get_field' ) ) {
			$raw = get_field( $key, $post_id );
			if ( is_scalar( $raw ) && null !== $raw && false !== $raw ) {
				$value = (string) $raw;
			}
		}

		if ( null === $value ) {
			$meta = get_post_meta( $post_id, $key, true );
			if ( is_scalar( $meta ) && '' !== $meta ) {
				$value = (string) $meta;
			}
		}

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
	// Return placeholder in admin/REST context (editor preview) regardless of post_id.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return '[Title]';
	}

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
	// Return placeholder in admin/REST context (editor preview) regardless of post_id.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return '[Excerpt]';
	}

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
	// Return placeholder in admin/REST context (editor preview) regardless of post_id.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return '[Custom Field]';
	}

	$fallback = sanitize_text_field( $options['fallback_text'] ?? '' );

	if ( ! $post_id ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
			: '';
	}

	$key = sanitize_text_field( $options['key'] ?? '' );

	if ( empty( $key ) || ! bws_is_valid_meta_key( $key ) ) {
		return '';
	}

	$value = null;

	if ( function_exists( 'get_field' ) ) {
		$raw = get_field( $key, $post_id );
		// Accept any scalar value, including integer 0 and string '0'.
		// Reject null/false (field not found), arrays, and objects.
		if ( is_scalar( $raw ) && null !== $raw && false !== $raw ) {
			$value = (string) $raw;
		}
	}

	if ( null === $value ) {
		// Fallback to standard post meta.
		$meta = get_post_meta( $post_id, $key, true );
		if ( is_scalar( $meta ) && '' !== $meta ) {
			$value = (string) $meta;
		}
	}

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
