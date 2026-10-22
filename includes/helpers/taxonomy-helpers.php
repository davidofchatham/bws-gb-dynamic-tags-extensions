<?php
/**
 * Taxonomy term helper functions.
 *
 * Shared functions for the term-archive criterion, term validation, image
 * retrieval, and fallback handling.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * THE term-archive criterion: is the page WP is rendering a term archive?
 *
 * AXIS OWNER. A term is claimed only when WP actually QUERIED one — `is_tax()`,
 * `is_category()` or `is_tag()`. A bare `get_queried_object() instanceof WP_Term`
 * is NOT the criterion: under a REST request, or any other secondary query, it can
 * hand back a term the rendered page is not about, and a read gated on that serves
 * a foreign entity's meta.
 *
 * Two sites ask, and they legitimately do different things with the answer, which is
 * why this is a predicate rather than a term lookup. `bws_capture_ambient_signals()`
 * needs the archive-claimed-but-no-WP_Term case to stay distinguishable from
 * not-an-archive, because that degenerate case short-circuits the factory to empty
 * instead of leaking the current post (SPEC §V17). `bws_read_field()`'s term-archive
 * branch only needs to bail. A `?WP_Term` return would collapse the two.
 *
 * The second caller is temporary. FW-7 deletes `bws_read_field()`'s branch outright
 * once its callers take a resolved source, leaving the factory as the only asker.
 *
 * @since 1.21.0
 * @return bool True when WP queried a taxonomy, category or tag archive.
 */
if ( ! function_exists( 'bws_wp_is_term_archive' ) ) {
function bws_wp_is_term_archive(): bool {
	return function_exists( 'is_tax' ) && ( is_tax() || is_category() || is_tag() );
}
}

/**
 * Get term object with validation.
 *
 * @since 1.1.0
 * @param int $term_id Term ID.
 * @return WP_Term|false Term object or false.
 */
if ( ! function_exists( 'bws_get_validated_term' ) ) {
function bws_get_validated_term( $term_id ) {
	if ( ! $term_id ) {
		return false;
	}

	$term = get_term( $term_id );

	if ( is_wp_error( $term ) || ! $term ) {
		return false;
	}

	return $term;
}
}

/**
 * Get field image data from term custom field.
 *
 * @since 1.1.0
 * @param int    $term_id     Term ID.
 * @param string $field_key   Field key.
 * @param string $return_type Type of data to return.
 * @param string $image_size  Image size.
 * @return string Image data or empty string.
 */
if ( ! function_exists( 'bws_get_term_field_image_data' ) ) {
function bws_get_term_field_image_data( $term_id, $field_key, $return_type = 'url', $image_size = 'full' ) {
	if ( ! $term_id || ! $field_key ) {
		return '';
	}

	// Scalar or array: an ACF image field's return format decides which.
	$image_value = bws_meta_handler_read_preserving_arrays( (int) $term_id, $field_key, 'get_term_meta' );

	if ( empty( $image_value ) ) {
		return '';
	}

	return bws_process_meta_image_value( $image_value, $return_type, $image_size );
}
}
