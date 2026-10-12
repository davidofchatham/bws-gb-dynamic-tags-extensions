<?php
/**
 * Taxonomy term helper functions.
 *
 * Shared functions for term context detection, validation, image retrieval,
 * and fallback handling.
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
 * Reliable term context detection with multiple fallback methods.
 *
 * TIER NAMES ARE STABLE, AND THE GAP BELOW IS DELIBERATE. The quaternary tier (a bare
 * archive read, gated on the queried object's type) went with the `term_*` family in
 * 1.21.0 — see the Quinary comment. Renumbering the survivors would silently restate
 * every citation that names a tier by number; leave the numbering alone.
 *
 * Folding this into bws_capture_ambient_signals() is FW-8 (docs/future-work.md).
 *
 * @since 1.1.0
 * @since 1.21.0 Tier 4 removed with the `term_*` family (FW-129).
 * @param array $options Tag options that may contain specific term ID.
 * @return int|false Term ID or false if not found.
 */
if ( ! function_exists( 'bws_reliable_term_context_detection' ) ) {
function bws_reliable_term_context_detection( $options = array() ) {
	// Primary: Check for specific term ID in options.
	if ( isset( $options['term_id'] ) && $options['term_id'] ) {
		$term_id = absint( $options['term_id'] );
		if ( $term_id && term_exists( $term_id ) ) {
			return $term_id;
		}
	}

	// Secondary: Check for GenerateBlocks ID override.
	if ( isset( $options['id'] ) && $options['id'] ) {
		$term_id = absint( $options['id'] );
		if ( $term_id && term_exists( $term_id ) ) {
			return $term_id;
		}
	}

	// Tertiary: Direct taxonomy queries (archive pages). A STATED `tax` IS DISCARDED HERE,
	// and the order is deliberate rather than an oversight: on a term archive "the term this
	// page is about" outranks a taxonomy hint, so the queried term answers even when it
	// belongs to another taxonomy and tier 5 never runs. Reachable only from wire no editor
	// offers (`tax` is not registered on the `term_` family), and the family is on a removal
	// path — measured 2026-09-10, left alone deliberately (FW-39).
	if ( is_tax() || is_category() || is_tag() ) {
		$queried_object = get_queried_object();
		if ( $queried_object && isset( $queried_object->term_id ) ) {
			return $queried_object->term_id;
		}
	}

	// Quinary: Check for first term from current post (if taxonomy specified). The
	// quaternary tier that used to sit above it read a bare `get_queried_object_id()` on any
	// archive, which is why it needed a type gate at all; it went in 1.21.0 with its last
	// consumer (FW-129). Its removal moved no rendered output — the seven `ctx-*` page
	// snapshots, a term archive among them, were byte-unchanged by it.
	//
	// The tertiary tier above gates on `is_tax() || is_category() || is_tag()`, which is the
	// same predicate bws_capture_ambient_signals() claims a term archive by; what that
	// predicate covers is that function's question, not this one's.
	$taxonomy = $options['tax'] ?? $options['taxonomy'] ?? '';
	if ( $taxonomy && ! is_admin() ) {
		$post_id = get_the_ID();
		if ( $post_id ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$first_term = reset( $terms );
				return $first_term->term_id;
			}
		}
	}

	return false;
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
