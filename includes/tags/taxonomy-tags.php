<?php
/**
 * Taxonomy term core functions.
 *
 * Term tags (term_title, term_permalink, term_description, term_custom_text,
 * term_custom_image, term_custom_date_*, term_custom_datetime_*) are registered
 * via the template system (TagTemplateRegistry::generate_all_tags()).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Term tags (term_title, term_permalink, etc.) are registered via the template system.

// ===============================================
// TEMPLATE REGISTRATION — POST-CONTEXT TERM EXTRACTION
// ===============================================

/**
 * Register post-context term-extraction tag templates.
 *
 * These templates produce tags like post_term_title, related_post_term_title, etc.
 * They apply to any post-context source (CurrentPost, RelatedPost, Portal, SecondRelatedPost).
 * The get_entities_fn resolves a post ID to WP_Term[] in the specified taxonomy;
 * the core_fn then extracts the requested data from each term object.
 *
 * Complements (does not replace) the existing term-context tags generated via the
 * TaxonomyTerm source (term_title, term_permalink, etc. for archive/loop pages).
 *
 * @since 1.2.0
 */
function bws_register_taxonomy_term_extraction_templates() {
	// term_title (post-context): first (or list of) term name(s) from a post in a given taxonomy.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'             => 'term_title',
		'title'           => 'Term Title',
		'gb_type'         => null,
		'supports'        => array( 'link', 'source' ),
		'options_fn'      => 'bws_post_term_extraction_options',
		'core_fn'         => 'bws_term_title_core',
		'context_types'   => array( 'post' ),
		'get_entities_fn' => 'bws_get_terms_for_post',
		'supports_list'   => true,
	) );

	// term_permalink (post-context): permalink of the first term from a post in a given taxonomy.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'             => 'term_permalink',
		'title'           => 'Term Permalink',
		'gb_type'         => null,
		'supports'        => array( 'source' ),
		'options_fn'      => 'bws_post_term_extraction_options',
		'core_fn'         => 'bws_term_permalink_core',
		'context_types'   => array( 'post' ),
		'get_entities_fn' => 'bws_get_terms_for_post',
		'supports_list'   => false,
	) );

	// term_description (post-context): description of the first term from a post in a given taxonomy.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'             => 'term_description',
		'title'           => 'Term Description',
		'gb_type'         => null,
		'supports'        => array( 'source' ),
		'options_fn'      => 'bws_post_term_extraction_options',
		'core_fn'         => 'bws_term_description_core',
		'context_types'   => array( 'post' ),
		'get_entities_fn' => 'bws_get_terms_for_post',
		'supports_list'   => false,
	) );

	// term_custom_text (post-context): ACF/meta text field on a term of a post.
	// 'meta' support gives the field key selector in the GB editor.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'             => 'term_custom_text',
		'title'           => 'Term Custom Text',
		'gb_type'         => null,
		'supports'        => array( 'meta', 'source' ),
		'options_fn'      => 'bws_post_term_extraction_options',
		'core_fn'         => 'bws_term_custom_text_core',
		'context_types'   => array( 'post' ),
		'get_entities_fn' => 'bws_get_terms_for_post',
		'supports_list'   => true,
	) );

	// term_custom_image (post-context): ACF/meta image field on a term of a post.
	// gb_type 'media' enables return_type / size / fallback attachment selector in GB editor.
	\BWS\DynamicTags\TagTemplateRegistry::register_template( array(
		'key'             => 'term_custom_image',
		'title'           => 'Term Custom Image',
		'gb_type'         => 'media',
		'supports'        => array( 'image-size', 'source' ),
		'options_fn'      => 'bws_post_term_image_options',
		'core_fn'         => 'bws_term_custom_image_core',
		'context_types'   => array( 'post' ),
		'get_entities_fn' => 'bws_get_terms_for_post',
		'supports_list'   => false,
	) );
}

// ===============================================
// CORE FUNCTIONS
// ===============================================

/**
 * Term title core.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_title_core( $term_id, $options, $instance ) {
	if ( ! $term_id ) {
		return '';
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return '';
	}
	return GenerateBlocks_Dynamic_Tag_Callbacks::output(
		$term->name,
		array_merge( $options, array( 'id' => $term_id ) ),
		$instance
	);
}

/**
 * Term permalink core.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_permalink_core( $term_id, $options, $instance ) {
	if ( ! $term_id ) {
		return '';
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return '';
	}
	$permalink = get_term_link( $term );
	if ( is_wp_error( $permalink ) ) {
		return '';
	}
	return GenerateBlocks_Dynamic_Tag_Callbacks::output( esc_url( $permalink ), $options, $instance );
}

/**
 * Term description core.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_description_core( $term_id, $options, $instance ) {
	if ( ! $term_id ) {
		return '';
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term || empty( $term->description ) ) {
		return '';
	}
	return GenerateBlocks_Dynamic_Tag_Callbacks::output(
		bws_sanitize_rich_content( $term->description ),
		array_merge( $options, array( 'id' => $term_id ) ),
		$instance
	);
}

/**
 * Term custom text core.
 *
 * Reads field key from $options['key'] (set by GB's 'meta' support).
 * Uses ACF taxonomy_termid format (e.g. 'category_5') for term fields.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options. 'key' is the field name.
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_custom_text_core( $term_id, $options, $instance ) {
	$fallback = sanitize_text_field( $options['fallback_text'] ?? '' );

	if ( ! $term_id ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
			: '';
	}
	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
			: '';
	}
	$key = sanitize_text_field( $options['key'] ?? '' );
	if ( empty( $key ) ) {
		return '';
	}
	$acf_object_id = $term->taxonomy . '_' . $term->term_id;
	$value         = '';
	if ( function_exists( 'get_field' ) ) {
		$raw = get_field( $key, $acf_object_id );
		if ( is_scalar( $raw ) && null !== $raw && false !== $raw ) {
			$value = (string) $raw;
		}
	}
	if ( '' === $value ) {
		$meta = get_term_meta( $term->term_id, $key, true );
		if ( is_scalar( $meta ) && '' !== $meta ) {
			$value = (string) $meta;
		}
	}
	if ( '' === $value ) {
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output(
				$fallback,
				array_merge( $options, array( 'id' => $term_id ) ),
				$instance
			)
			: '';
	}
	return GenerateBlocks_Dynamic_Tag_Callbacks::output(
		$value,
		array_merge( $options, array( 'id' => $term_id ) ),
		$instance
	);
}

/**
 * Term custom image core.
 *
 * Reads field key from $options['key'] or $options['field_key'].
 * Uses ACF taxonomy_termid format (e.g. 'category_5') for term fields.
 *
 * @since 1.2.0
 * @param int|false $term_id  Resolved term ID.
 * @param array     $options  Tag options (key/field_key, return_type, size, fallback_url).
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_custom_image_core( $term_id, $options, $instance ) {
	$field_key    = sanitize_text_field( $options['key'] ?? $options['field_key'] ?? '' );
	$return_type  = sanitize_text_field( $options['return_type'] ?? 'url' );
	$image_size   = sanitize_text_field( $options['size'] ?? 'full' );
	$fallback_url = esc_url_raw( $options['fallback_url'] ?? '' );

	if ( empty( $field_key ) ) {
		return bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance );
	}

	if ( ! $term_id ) {
		return bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance );
	}

	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance );
	}

	$image_data = bws_get_term_field_image_data( $term->term_id, $term->taxonomy, $field_key, $return_type, $image_size );

	if ( ! empty( $image_data ) ) {
		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $image_data, $options, $instance );
	}

	return bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance );
}

