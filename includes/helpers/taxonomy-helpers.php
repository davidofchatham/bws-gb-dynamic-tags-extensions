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
 * Reliable term context detection with multiple fallback methods.
 *
 * @since 1.1.0
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

	// Tertiary: Direct taxonomy queries (archive pages).
	if ( is_tax() || is_category() || is_tag() ) {
		$queried_object = get_queried_object();
		if ( $queried_object && isset( $queried_object->term_id ) ) {
			return $queried_object->term_id;
		}
	}

	// Quaternary: Archive context.
	if ( is_archive() ) {
		$term_id = get_queried_object_id();
		if ( $term_id && is_numeric( $term_id ) ) {
			return $term_id;
		}
	}

	// Quinary: Check for first term from current post (if taxonomy specified).
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
 * @param string $taxonomy    Taxonomy slug.
 * @param string $field_key   Field key.
 * @param string $return_type Type of data to return.
 * @param string $image_size  Image size.
 * @return string Image data or empty string.
 */
if ( ! function_exists( 'bws_get_term_field_image_data' ) ) {
function bws_get_term_field_image_data( $term_id, $taxonomy, $field_key, $return_type = 'url', $image_size = 'full' ) {
	if ( ! $term_id || ! $taxonomy || ! $field_key ) {
		return '';
	}

	$image_value = '';

	// Try ACF field first.
	if ( function_exists( 'get_field' ) ) {
		$image_value = get_field( $field_key, $taxonomy . '_' . $term_id );
	}

	// Fallback to standard term meta.
	if ( empty( $image_value ) ) {
		$image_value = get_term_meta( $term_id, $field_key, true );
	}

	if ( empty( $image_value ) ) {
		return '';
	}

	// Reuse existing image processing function.
	return bws_process_meta_image_value( $image_value, $return_type, $image_size );
}
}

/**
 * Handle image fallback for term field images using URLs and placeholders.
 *
 * @since 1.1.0
 * @param string $fallback_url Fallback image URL.
 * @param string $return_type  Type of data to return.
 * @param string $image_size   Image size (not used for URLs).
 * @param array  $options      Tag options.
 * @param object $instance     Block instance.
 * @return string
 */
if ( ! function_exists( 'bws_handle_term_image_fallback' ) ) {
function bws_handle_term_image_fallback( $fallback_url, $return_type, $image_size, $options, $instance ) {
	// First try the URL fallback.
	if ( ! empty( $fallback_url ) && filter_var( $fallback_url, FILTER_VALIDATE_URL ) ) {
		$site_url_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$fallback_url_host = wp_parse_url( $fallback_url, PHP_URL_HOST );

		// If it's a local URL, try to get attachment data.
		if ( $site_url_host === $fallback_url_host ) {
			$attachment_id = bws_get_attachment_id_from_url( $fallback_url );

			if ( $attachment_id ) {
				$result = bws_get_attachment_data( $attachment_id, $return_type, $image_size );

				if ( ! empty( $result ) ) {
					return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
				}
			}
		}

		// For external URLs or when we can't find an attachment, return based on return type.
		switch ( $return_type ) {
			case 'url':
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( esc_url( $fallback_url ), $options, $instance );

			case 'id':
				if ( $site_url_host === $fallback_url_host ) {
					$attachment_id = bws_get_attachment_id_from_url( $fallback_url );
					if ( $attachment_id ) {
						return GenerateBlocks_Dynamic_Tag_Callbacks::output( (string) $attachment_id, $options, $instance );
					}
				}
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );

			case 'alt':
			case 'caption':
				if ( $site_url_host === $fallback_url_host ) {
					$attachment_id = bws_get_attachment_id_from_url( $fallback_url );
					if ( $attachment_id ) {
						$result = bws_get_attachment_data( $attachment_id, $return_type, $image_size );
						if ( ! empty( $result ) ) {
							return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
						}
					}
				}
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
		}
	}

	// Editor context: Use placeholders when no fallback URL is available.
	if ( is_admin() || wp_is_json_request() ) {
		switch ( $return_type ) {
			case 'id':
				$placeholder = '0';
				break;
			case 'alt':
				$placeholder = 'Term image alt text placeholder';
				break;
			case 'caption':
				$placeholder = 'Term image caption placeholder';
				break;
			case 'url':
			default:
				$placeholder = bws_get_generateblocks_image_placeholder();
				break;
		}

		return GenerateBlocks_Dynamic_Tag_Callbacks::output( $placeholder, $options, $instance );
	}

	// Frontend: Return empty if no fallback available.
	return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
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
 * @since 1.2.0
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
		'fallback_text' => array(
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

/**
 * Get GenerateBlocks image placeholder URL.
 *
 * @since 1.1.0
 * @return string Placeholder URL.
 */
if ( ! function_exists( 'bws_get_generateblocks_image_placeholder' ) ) {
function bws_get_generateblocks_image_placeholder() {
	if ( defined( 'GENERATEBLOCKS_DIR_URL' ) ) {
		$placeholder_path = GENERATEBLOCKS_DIR_URL . 'assets/images/image-placeholder.png';
		$placeholder_file = str_replace( GENERATEBLOCKS_DIR_URL, GENERATEBLOCKS_DIR, $placeholder_path );
		if ( file_exists( $placeholder_file ) ) {
			return $placeholder_path;
		}
	}

	// Fallback to a simple data URL placeholder.
	return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjFmMWYxIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNHB4IiBmaWxsPSIjNjY2IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zZW0iPkltYWdlIFBsYWNlaG9sZGVyPC90ZXh0Pjwvc3ZnPg==';
}
}
