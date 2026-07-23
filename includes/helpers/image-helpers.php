<?php
/**
 * Image processing helper functions.
 *
 * Shared functions for attachment data retrieval, meta image processing,
 * ACF icon picker support, dashicon handling, and media fallbacks.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get image return type options for tag registration.
 *
 * @since 1.0.0
 * @return array
 */
if ( ! function_exists( 'bws_get_image_return_type_options' ) ) {
function bws_get_image_return_type_options() {
	return array(
		'as' => array(
			'type'    => 'select',
			'label'   => __( 'Return Type', 'generateblocks' ),
			'default' => 'url',
			'options' => array(
				array( 'value' => 'url', 'label' => __( 'Image URL', 'generateblocks' ) ),
				array( 'value' => 'alt', 'label' => __( 'Alt Text', 'generateblocks' ) ),
				array( 'value' => 'id', 'label' => __( 'Attachment ID', 'generateblocks' ) ),
				array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
			),
		),
	);
}
}

/**
 * Registered image sizes as {value,label} option rows for the as+size composite.
 *
 * The `bws-as-size` control (assets/js/as-size-control.js) folds size INTO the
 * `as` value (`as:url,<size>`); this feeds its size dropdown, localized to JS.
 *
 * Labels come from WP's own media-modal source — apply_filters(
 * 'image_size_names_choose', ... ), which respects theme/plugin-registered custom
 * size labels (GB's native control ignored that filter and slug-munged instead).
 * Any registered-but-unlabeled size is backfilled from
 * get_intermediate_image_sizes() with title-cased slug. `full` is always present.
 *
 * @since 1.16.0
 * @return array List of array( 'value' => slug, 'label' => human ).
 */
if ( ! function_exists( 'bws_get_image_size_options' ) ) {
function bws_get_image_size_options() {
	$labels = apply_filters(
		'image_size_names_choose',
		array(
			'thumbnail'    => __( 'Thumbnail', 'generateblocks' ),
			'medium'       => __( 'Medium', 'generateblocks' ),
			'medium_large' => __( 'Medium Large', 'generateblocks' ),
			'large'        => __( 'Large', 'generateblocks' ),
			'full'         => __( 'Full Size', 'generateblocks' ),
		)
	);

	// Backfill any registered size with no label (theme/plugin sizes the filter omitted).
	foreach ( get_intermediate_image_sizes() as $slug ) {
		if ( ! isset( $labels[ $slug ] ) ) {
			$labels[ $slug ] = ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
		}
	}

	// `full` is not an intermediate size — guarantee it.
	if ( ! isset( $labels['full'] ) ) {
		$labels['full'] = __( 'Full Size', 'generateblocks' );
	}

	$options = array();
	foreach ( $labels as $value => $label ) {
		$options[] = array(
			'value' => $value,
			'label' => $label,
		);
	}

	return $options;
}
}

/**
 * Parse the folded `as` option into its return-mode and size argument.
 *
 * The as+size fold serializes size as a comma second slot on `as`'s value:
 * `as:url,medium` → url(medium). Nullary return modes (id/alt/title/caption) carry
 * no size argument. Size is consumed ONLY by the `url` mode (bws_get_attachment_data
 * touches $size in the url/default case only), so a size on a nullary mode is inert.
 *
 * Legacy fallback: pre-fold tags stored size in a separate `size:` option (GB's
 * native image-size control). When `as` carries no comma slot, the size falls back
 * to $options['size'] then 'full', so old saved tags still render at their size.
 *
 * @since 1.16.0
 * @param array $options Tag options (reads `as` and legacy `size`).
 * @return array array( 'mode' => string, 'size' => string ) — mode defaults 'url', size 'full'.
 */
if ( ! function_exists( 'bws_parse_as_option' ) ) {
function bws_parse_as_option( $options ) {
	$raw  = $options['as'] ?? $options['return_type'] ?? 'url';
	$raw  = sanitize_text_field( (string) $raw );
	$bits = explode( ',', $raw, 2 );

	$mode = ( '' !== $bits[0] ) ? $bits[0] : 'url';
	$size = isset( $bits[1] ) && '' !== $bits[1]
		? $bits[1]
		: sanitize_text_field( $options['size'] ?? 'full' );

	return array(
		'mode' => $mode,
		'size' => $size,
	);
}
}

/**
 * Get meta image field options for tag registration.
 *
 * @since 1.0.0
 * @return array
 */
if ( ! function_exists( 'bws_get_meta_image_options' ) ) {
function bws_get_meta_image_options() {
	return array(
		'meta_key' => array(
			'type'        => 'text',
			'label'       => __( 'Meta Key', 'generateblocks' ),
			'help'        => __( 'Enter the meta key for the image field (ACF or standard meta).', 'generateblocks' ),
			'placeholder' => __( 'company_logo', 'generateblocks' ),
		),
	);
}
}

/**
 * Get custom image field options for the custom_image template (key + return type).
 *
 * Uses 'key' as the option key for template-generated tags.
 * The bws_custom_image_core() callback accepts 'key', 'field_key', and 'meta_key' for backward compat.
 *
 * @since 1.2.0
 * @return array
 */
if ( ! function_exists( 'bws_get_meta_and_return_type_options' ) ) {
function bws_get_meta_and_return_type_options() {
	return array_merge(
		array(
			'key' => array(
				'type'        => 'text',
				'label'       => __( 'Meta Key', 'generateblocks' ),
				'help'        => __( 'Enter the meta key for the image field (ACF or standard meta).', 'generateblocks' ),
				'placeholder' => __( 'company_logo', 'generateblocks' ),
			),
		),
		bws_get_image_return_type_options()
	);
}
}

/**
 * Get attachment data by type.
 *
 * @since 1.0.0
 * @param int    $attachment_id Attachment ID.
 * @param string $return_type   Type of data to return (url, alt, id, caption).
 * @param string $size          Image size.
 * @return string Requested data.
 */
if ( ! function_exists( 'bws_get_attachment_data' ) ) {
function bws_get_attachment_data( $attachment_id, $return_type = 'url', $size = 'full' ) {
	$attachment = get_post( $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return '';
	}

	switch ( $return_type ) {
		case 'id':
			return (string) $attachment_id;

		case 'alt':
			return get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		case 'caption':
			return wp_get_attachment_caption( $attachment_id );

		case 'url':
		default:
			$image_data = wp_get_attachment_image_src( $attachment_id, $size );

			if ( ! $image_data || ! isset( $image_data[0] ) ) {
				return '';
			}

			return esc_url( $image_data[0] );
	}
}
}

/**
 * Get image data from meta field.
 *
 * @since 1.0.0
 * @param int    $post_id     Post ID.
 * @param string $meta_key    Meta key.
 * @param string $return_type Type of data to return.
 * @param string $size        Image size.
 * @param object $instance    Block instance (for loop-item context detection).
 * @return string Requested data.
 */
if ( ! function_exists( 'bws_get_meta_image_data' ) ) {
function bws_get_meta_image_data( $post_id, $meta_key, $return_type = 'url', $size = 'full', $instance = null ) {
	$is_loop_row = bws_get_loop_row_context( $instance )['in_loop'];

	if ( ! $is_loop_row && ( ! $post_id || ! get_post( $post_id ) ) ) {
		return '';
	}

	// Two-pass meta read to handle all image-meta return formats from ACF and other providers.
	// Pass 1: single_only=true → returns scalar (URL/ID) but coerces array/object to ''.
	// Pass 2: single_only=false → returns array/object preserved, but GB Meta_Handler::get_value
	//   returns fallback ('') for plain scalars when an upstream filter (e.g. ACF pre_value)
	//   populates the value, so we only fall through here when pass 1 yielded nothing.
	$meta_value = bws_read_field( $meta_key, $instance, $post_id, true );

	if ( '' === $meta_value || null === $meta_value ) {
		$meta_value = bws_read_field( $meta_key, $instance, $post_id, false );
	}

	if ( ! $meta_value ) {
		return '';
	}

	return bws_process_meta_image_value( $meta_value, $return_type, $size );
}
}

/**
 * Process meta value to extract image data.
 *
 * Handles diverse ACF/meta image formats: arrays (ACF image, icon picker),
 * numeric IDs, string URLs, and dashicons.
 *
 * @since 1.0.0
 * @param mixed  $meta_value  Meta value.
 * @param string $return_type Type of data to return.
 * @param string $size        Image size.
 * @return string Requested data.
 */
if ( ! function_exists( 'bws_process_meta_image_value' ) ) {
function bws_process_meta_image_value( $meta_value, $return_type = 'url', $size = 'full' ) {
	$attachment_id = null;

	if ( is_array( $meta_value ) ) {
		// ACF Icon Picker format.
		if ( isset( $meta_value['type'] ) && isset( $meta_value['value'] ) ) {
			return bws_process_acf_icon_picker( $meta_value, $return_type, $size );
		}
		// ACF Image field.
		elseif ( isset( $meta_value['ID'] ) && is_numeric( $meta_value['ID'] ) ) {
			$attachment_id = absint( $meta_value['ID'] );
		} elseif ( isset( $meta_value['id'] ) && is_numeric( $meta_value['id'] ) ) {
			$attachment_id = absint( $meta_value['id'] );
		} elseif ( isset( $meta_value['url'] ) && is_string( $meta_value['url'] ) ) {
			$attachment_id = bws_get_attachment_id_from_url( $meta_value['url'] );

			if ( ! $attachment_id && 'url' === $return_type ) {
				return esc_url( $meta_value['url'] );
			}
		}
	} elseif ( is_numeric( $meta_value ) ) {
		$attachment_id = absint( $meta_value );
	} elseif ( is_string( $meta_value ) ) {
		$meta_value = trim( $meta_value );

		if ( strpos( $meta_value, 'dashicons-' ) === 0 ) {
			return bws_handle_dashicon_value( $meta_value, $return_type );
		}

		$meta_value = esc_url_raw( $meta_value );

		if ( filter_var( $meta_value, FILTER_VALIDATE_URL ) ) {
			$attachment_id = bws_get_attachment_id_from_url( $meta_value );

			if ( ! $attachment_id && 'url' === $return_type ) {
				return esc_url( $meta_value );
			}
		}
	}

	if ( ! $attachment_id ) {
		return '';
	}

	return bws_get_attachment_data( $attachment_id, $return_type, $size );
}
}

/**
 * Process ACF Icon Picker field data.
 *
 * @since 1.0.0
 * @param array  $icon_data   Icon picker data.
 * @param string $return_type Type of data to return.
 * @param string $size        Image size.
 * @return string Requested data.
 */
if ( ! function_exists( 'bws_process_acf_icon_picker' ) ) {
function bws_process_acf_icon_picker( $icon_data, $return_type = 'url', $size = 'full' ) {
	$icon_type  = $icon_data['type'] ?? '';
	$icon_value = $icon_data['value'] ?? '';

	switch ( $icon_type ) {
		case 'media_library':
		case 'url':
			$url = '';

			if ( is_array( $icon_value ) ) {
				if ( isset( $icon_value['url'] ) ) {
					$url = $icon_value['url'];
				} elseif ( isset( $icon_value['ID'] ) ) {
					return bws_get_attachment_data( absint( $icon_value['ID'] ), $return_type, $size );
				} elseif ( isset( $icon_value['id'] ) ) {
					return bws_get_attachment_data( absint( $icon_value['id'] ), $return_type, $size );
				}
			} elseif ( is_string( $icon_value ) ) {
				$url = $icon_value;
			}

			if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$attachment_id = bws_get_attachment_id_from_url( $url );

				if ( $attachment_id ) {
					return bws_get_attachment_data( $attachment_id, $return_type, $size );
				} elseif ( 'url' === $return_type ) {
					return esc_url( $url );
				}
			}
			break;

		case 'dashicons':
			if ( is_string( $icon_value ) ) {
				return bws_handle_dashicon_value( $icon_value, $return_type );
			}
			break;
	}

	return '';
}
}

/**
 * Handle dashicon values.
 *
 * @since 1.0.0
 * @param string $dashicon_class Dashicon class.
 * @param string $return_type    Type of data to return.
 * @return string Requested data.
 */
if ( ! function_exists( 'bws_handle_dashicon_value' ) ) {
function bws_handle_dashicon_value( $dashicon_class, $return_type = 'url' ) {
	$dashicon_class = sanitize_html_class( $dashicon_class );

	switch ( $return_type ) {
		case 'alt':
			$alt_text = str_replace( array( 'dashicons-', '-' ), array( '', ' ' ), $dashicon_class );
			return ucwords( trim( $alt_text ) );

		case 'caption':
			$caption = str_replace( array( 'dashicons-', '-' ), array( 'Dashicon: ', ' ' ), $dashicon_class );
			return ucwords( trim( $caption ) );

		case 'url':
		case 'id':
		default:
			return '';
	}
}
}

/**
 * Get attachment ID from URL.
 *
 * @since 1.0.0
 * @param string $url Image URL.
 * @return int|false Attachment ID or false.
 */
if ( ! function_exists( 'bws_get_attachment_id_from_url' ) ) {
function bws_get_attachment_id_from_url( $url ) {
	if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return false;
	}

	// Security: same domain only.
	$site_url       = wp_parse_url( home_url(), PHP_URL_HOST );
	$image_url_host = wp_parse_url( $url, PHP_URL_HOST );

	if ( $site_url !== $image_url_host ) {
		return false;
	}

	// Try WordPress core function.
	$attachment_id = attachment_url_to_postid( $url );

	if ( $attachment_id ) {
		return $attachment_id;
	}

	// Try without size suffix.
	$url_without_size = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif|webp|svg)$)/i', '', $url );

	if ( $url_without_size !== $url ) {
		$attachment_id = attachment_url_to_postid( $url_without_size );

		if ( $attachment_id ) {
			return $attachment_id;
		}
	}

	return false;
}
}

/**
 * Handle media fallback from the media selector UI.
 *
 * @since 1.0.0
 * @param mixed  $fallback Fallback attachment ID (bws-media-picker, v1.7.3+) or URL (legacy, pre-1.7.3).
 * @param string $return_type       Type of data to return.
 * @param string $image_size        Image size.
 * @param array  $options           Tag options.
 * @param object $instance          Block instance.
 * @return string
 */
if ( ! function_exists( 'bws_handle_media_fallback' ) ) {
function bws_handle_media_fallback( $fallback, $return_type, $image_size, $options, $instance ) {
	if ( $fallback ) {
		$id = is_numeric( $fallback )
			? absint( $fallback )
			: bws_get_attachment_id_from_url( $fallback );

		if ( $id ) {
			$result = bws_get_attachment_data( $id, $return_type, $image_size );

			if ( ! empty( $result ) ) {
				return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
			}
		}
	}

	return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
}
}
