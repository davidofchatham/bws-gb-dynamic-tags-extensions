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
 * Get term image field options for tag registration.
 *
 * @since 1.1.0
 * @return array
 */
if ( ! function_exists( 'bws_get_term_image_field_options' ) ) {
function bws_get_term_image_field_options() {
	return array(
		'key' => array(
			'type'        => 'text',
			'label'       => __( 'Meta Field Key', 'generateblocks' ),
			'help'        => __( 'Enter the meta field key for the image field (ACF or standard term meta).', 'generateblocks' ),
			'placeholder' => __( 'image', 'generateblocks' ),
		),
		'fallback_url' => array(
			'type'        => 'url',
			'label'       => __( 'Fallback Image URL', 'generateblocks' ),
			'help'        => __( 'Enter a fallback image URL to use when no custom image is found.', 'generateblocks' ),
			'placeholder' => __( 'https://example.com/default-image.jpg', 'generateblocks' ),
		),
	);
}
}

/**
 * Get term custom image options combined with return type for the custom_image template.
 *
 * Merges field_key + fallback_url options with return type. Used as term_options_fn
 * for the custom_image template, providing URL-based fallback (not media library picker,
 * which requires gb_type='media' — incompatible with the 'term' type needed here).
 *
 * @since 1.2.0
 * @return array
 */
if ( ! function_exists( 'bws_get_term_image_and_return_type_options' ) ) {
function bws_get_term_image_and_return_type_options() {
	return array_merge(
		bws_get_term_image_field_options(),
		bws_get_image_return_type_options()
	);
}
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

	$image_value = bws_read_term_field( $field_key, (int) $term_id, false );

	if ( empty( $image_value ) ) {
		return '';
	}

	return bws_process_meta_image_value( $image_value, $return_type, $image_size );
}
}

/**
 * Get WP_Term objects for a post in a given taxonomy.
 *
 * Used as get_entities_fn for post-referenced term-extraction templates.
 * Returns an array of WP_Term objects (never WP_Error or false).
 *
 * @since 1.2.0
 * @param int   $post_id Post ID.
 * @param array $options Tag options (reads 'taxonomy').
 * @return WP_Term[]
 */
if ( ! function_exists( 'bws_get_terms_for_post' ) ) {
function bws_get_terms_for_post( int $post_id, array $options ): array {
	$taxonomy = $options['tax'] ?? $options['taxonomy'] ?? '';
	if ( ! $post_id || ! $taxonomy ) {
		return array();
	}
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}
	return array_values( $terms );
}
}

/**
 * Get shared options for post-referenced term-extraction templates.
 *
 * Provides the taxonomy selector used by post_term_*, related_post_term_*, etc.
 *
 * No internal callers remain (the post-context term-extraction templates it fed
 * were retired); kept as a published integration affordance —
 * docs/plugin-integration.md lists it for external plugins registering their own
 * post-context term templates.
 *
 * @since 1.2.0
 * @since 1.16.0 `fallback_text` key renamed to `fallback`, matching every live
 *               tag and the documented contract.
 * @return array
 */
if ( ! function_exists( 'bws_post_term_extraction_options' ) ) {
function bws_post_term_extraction_options(): array {
	return array(
		'tax' => array(
			'type'        => 'text',
			'label'       => __( 'Taxonomy', 'generateblocks' ),
			'help'        => __( 'Enter the taxonomy slug to retrieve terms from (e.g. category, post_tag, or a custom taxonomy slug).', 'generateblocks' ),
			'placeholder' => __( 'category', 'generateblocks' ),
		),
		'fallback' => array(
			'type'  => 'text',
			'label' => __( 'Fallback Text', 'generateblocks' ),
		),
	);
}
}

/**
 * Get options for post-context term image extraction templates.
 *
 * Combines taxonomy selection with an image field key for the term.
 * Used as options_fn for the 'term_custom_image' post-context template.
 * GB's 'media' type adds return_type / size / fallback attachment separately.
 *
 * @since 1.2.0
 * @return array
 */
if ( ! function_exists( 'bws_post_term_image_options' ) ) {
function bws_post_term_image_options(): array {
	return array(
		'tax' => array(
			'type'        => 'text',
			'label'       => __( 'Taxonomy', 'generateblocks' ),
			'help'        => __( 'Enter the taxonomy slug to retrieve terms from (e.g. category, post_tag, or a custom taxonomy slug).', 'generateblocks' ),
			'placeholder' => __( 'category', 'generateblocks' ),
		),
		'key' => array(
			'type'        => 'text',
			'label'       => __( 'Image Field Key', 'generateblocks' ),
			'help'        => __( 'ACF or term meta field key for the image field on the term.', 'generateblocks' ),
			'placeholder' => 'thumbnail',
		),
	);
}
}
