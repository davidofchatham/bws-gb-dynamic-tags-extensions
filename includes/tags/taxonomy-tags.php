<?php
/**
 * Taxonomy term core functions.
 *
 * Term modifier tags (term_text, term_title, term_image, etc.) are registered
 * via TagTemplateRegistry::register_modifier() in base-tags.php.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.6.0 `taxonomy` option renamed to `tax` in post-context term-extraction templates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	$fallback = sanitize_text_field( $options['fallback'] ?? '' );

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
	$raw   = bws_read_term_field( $key, (int) $term->term_id );
	$value = ( is_scalar( $raw ) && null !== $raw && false !== $raw && '' !== $raw ) ? (string) $raw : '';
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
 * @param array     $options  Tag options (key/field_key, as, size, fallback_url).
 * @param object    $instance Block instance.
 * @return string
 */
function bws_term_custom_image_core( $term_id, $options, $instance ) {
	$field_key   = sanitize_text_field( $options['key'] ?? $options['field_key'] ?? '' );
	$return_type = sanitize_text_field( $options['as'] ?? $options['return_type'] ?? 'url' );
	$image_size  = sanitize_text_field( $options['size'] ?? 'full' );
	// Fallback is an attachment ID (bws-media-picker) OR a URL — pass it RAW to the
	// SHARED media-fallback resolver (SPEC §V19, parity with the post image tag).
	// Do NOT esc_url_raw here: it mangles a numeric id like 51687 into "http://51687"
	// (B8). bws_handle_media_fallback does the id-or-url detection + the id-fallback
	// path the term-specific URL-only helper lacked.
	$fallback = $options['fallback'] ?? $options['fallback_url'] ?? '';

	if ( empty( $field_key ) ) {
		return bws_handle_media_fallback( $fallback, $return_type, $image_size, $options, $instance );
	}

	if ( ! $term_id ) {
		return bws_handle_media_fallback( $fallback, $return_type, $image_size, $options, $instance );
	}

	$term = bws_get_validated_term( $term_id );
	if ( ! $term ) {
		return bws_handle_media_fallback( $fallback, $return_type, $image_size, $options, $instance );
	}

	$image_data = bws_get_term_field_image_data( $term->term_id, $field_key, $return_type, $image_size );

	if ( ! empty( $image_data ) ) {
		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $image_data, $options, $instance );
	}

	return bws_handle_media_fallback( $fallback, $return_type, $image_size, $options, $instance );
}

