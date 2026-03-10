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
		'return_type' => array(
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
 * Get custom image field options for the custom_image template (field_key + return type).
 *
 * Uses 'field_key' (not legacy 'meta_key') as the option key for template-generated tags.
 * The bws_custom_image_core() callback accepts both names for backward compat.
 *
 * @since 1.2.0
 * @return array
 */
if ( ! function_exists( 'bws_get_meta_and_return_type_options' ) ) {
function bws_get_meta_and_return_type_options() {
	return array_merge(
		array(
			'field_key' => array(
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
 * @return string Requested data.
 */
if ( ! function_exists( 'bws_get_meta_image_data' ) ) {
function bws_get_meta_image_data( $post_id, $meta_key, $return_type = 'url', $size = 'full' ) {
	if ( ! $post_id || ! get_post( $post_id ) ) {
		return '';
	}

	$meta_value = null;

	if ( function_exists( 'get_field' ) ) {
		$meta_value = get_field( $meta_key, $post_id );
	}

	if ( ! $meta_value ) {
		$meta_value = get_post_meta( $post_id, $meta_key, true );
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
 * @param int    $fallback_media_id Fallback media ID.
 * @param string $return_type       Type of data to return.
 * @param string $image_size        Image size.
 * @param array  $options           Tag options.
 * @param object $instance          Block instance.
 * @return string
 */
if ( ! function_exists( 'bws_handle_media_fallback' ) ) {
function bws_handle_media_fallback( $fallback_media_id, $return_type, $image_size, $options, $instance ) {
	if ( $fallback_media_id && is_numeric( $fallback_media_id ) ) {
		$result = bws_get_attachment_data( absint( $fallback_media_id ), $return_type, $image_size );

		if ( ! empty( $result ) ) {
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance );
		}
	}

	return GenerateBlocks_Dynamic_Tag_Callbacks::output( '', $options, $instance );
}
}
