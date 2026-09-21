<?php
/**
 * Image dynamic tag cores for GenerateBlocks.
 *
 * Shared read cores for the `image` base tag and its try_/term_ arms:
 * bws_featured_image_core() (use:featured) and bws_custom_image_core() (field key).
 * Callers resolve the entity via the L1 factory and pass the id in — these cores
 * never resolve a source themselves (SPEC §V1: no ambient get_the_ID() fallback).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 * @since 1.14.1 GB `generateblocks_dynamic_tag_id` filter removed (dead since
 *               1.14.0 deprecated-tag removal; unreachable for `image`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWS\DynamicTags\Admin\SettingsPage;

// ===============================================
// CORE FUNCTIONS
// ===============================================

/**
 * The tag's STATED fallback image, rendered — {{image}}'s half of the fallback emit.
 *
 * One owner for what both cores already did at three sites: derive the media id and
 * the `as`+size pair off the options, then hand them to bws_handle_media_fallback().
 * The base arm's refusal guard (GH #109) needs the same emit without a core to reach
 * it through, and a fourth copy of the three-line derivation is how `as`/`size` drift
 * starts — see the as+size fold's own history.
 *
 * The derivation is deliberately identical for both cores: the fallback image is a
 * property of the TAG, not of which read missed, so `use:featured` and a field key
 * land on the same picture.
 *
 * @since 1.17.0
 * @param array  $options  Tag options (fallback / id, as, size).
 * @param object $instance Block instance.
 * @return string Rendered fallback image, or '' when none is stated.
 */
function bws_image_stated_fallback( array $options, $instance ): string {
	$as = bws_parse_as_option( $options );
	return (string) bws_handle_media_fallback(
		$options['fallback'] ?? $options['id'] ?? '',
		$as['mode'],
		$as['size'],
		$options,
		$instance
	);
}

/**
 * Featured image core — shared by template-generated and legacy callbacks.
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options (as, size, id=fallback attachment).
 * @param object    $instance Block instance.
 * @return string
 */
function bws_featured_image_core( $post_id, $options, $instance ) {
	// as+size fold (FW-52): `as` may carry a `,<size>` arg; legacy `size:` falls back.
	$as          = bws_parse_as_option( $options );
	$return_type = $as['mode'];
	$image_size  = $as['size'];

	if ( $post_id ) {
		$featured_attachment_id = get_post_thumbnail_id( $post_id );

		if ( $featured_attachment_id ) {
			$result = bws_get_attachment_data( $featured_attachment_id, $return_type, $image_size );

			if ( ! empty( $result ) ) {
				return bws_gb_tag_output( $result, $options, $instance );
			}
		}
	}

	return bws_image_stated_fallback( $options, $instance );
}

/**
 * Custom image core — shared by template-generated and legacy callbacks.
 *
 * Accepts 'key' (template option name) with fallbacks to 'field_key' and 'meta_key' (legacy names).
 *
 * @since 1.2.0
 * @param int|false $post_id  Resolved post ID.
 * @param array     $options  Tag options (key/field_key/meta_key, as, size, id=fallback attachment).
 * @param object    $instance Block instance.
 * @return string
 */
function bws_custom_image_core( $post_id, $options, $instance ) {
	$field_key   = sanitize_text_field( $options['key'] ?? $options['field_key'] ?? $options['meta_key'] ?? '' );
	// as+size fold (FW-52): `as` may carry a `,<size>` arg; legacy `size:` falls back.
	$as          = bws_parse_as_option( $options );
	$return_type = $as['mode'];
	$image_size  = $as['size'];

	if ( empty( $field_key ) ) {
		return bws_image_stated_fallback( $options, $instance );
	}

	if ( ! bws_is_valid_meta_key( $field_key ) ) {
		return bws_gb_tag_output( '', $options, $instance );
	}

	// See bws_loop_item_is_post_or_row(): a post or a repeater row, not `in_loop`.
	$read_may_serve = bws_loop_item_is_post_or_row( $instance );

	if ( $post_id || $read_may_serve ) {
		$result = bws_get_meta_image_data( $post_id, $field_key, $return_type, $image_size, $instance );

		if ( ! empty( $result ) ) {
			return bws_gb_tag_output( $result, $options, $instance );
		}
	}

	return bws_image_stated_fallback( $options, $instance );
}

/**
 * Repeater-ROW custom image core — the `meta_row` sibling of bws_custom_image_core() (FW-74).
 *
 * Takes the whole RESOLVED SOURCE rather than an entity id, for the reason
 * bws_row_custom_text_core() states: a repeater row has no id, it carries its own `row`
 * array. Its text twin is the shape to read this against.
 *
 * THE READ IS THE RAW SEAM, AND THAT IS THE WHOLE POINT OF THIS CORE. An image sub-field
 * arrives formatted by its own ACF return_format — an ARRAY for the default `array`
 * format, an int for `id`, a URL string for `url` — and bws_read_resolved_source()'s
 * string coercion drops the array outright. So this one asks
 * bws_read_resolved_source_value() and hands whatever comes back to
 * bws_process_meta_image_value(), which is already a pure processor over `mixed` and is
 * the SAME function the post route reaches through bws_get_meta_image_data(). One
 * return-format vocabulary, two source kinds.
 *
 * NO FALLBACK IS EMITTED HERE, unlike bws_custom_image_core(). A row is not an entity, so
 * there is no id to merge into $options for the fallback's own render, and the tag's
 * stated fallback is a property of the TAG rather than of which row missed — the base arm
 * emits it ONCE on an empty result (bws_base_image_callback()'s `meta_row` branch), which
 * is what keeps a `rows` chain's fallback behavior identical to the post route's. Under
 * try_ the question does not arise: the dispatcher strips `fallback` from the options it
 * evaluates with and emits it itself.
 *
 * @since 1.21.0
 * @param array  $source   Resolved source of kind `meta_row`.
 * @param array  $options  Tag options. 'key' is the sub-field name; `as` carries mode+size.
 * @param object $instance Block instance.
 * @return string
 */
function bws_row_custom_image_core( array $source, $options, $instance ) {
	$field_key = sanitize_text_field( $options['key'] ?? $options['field_key'] ?? $options['meta_key'] ?? '' );

	if ( '' === $field_key || ! bws_is_valid_meta_key( $field_key ) ) {
		return '';
	}

	$raw = bws_read_resolved_source_value( $source, $field_key, $instance );

	if ( ! $raw ) {
		return '';
	}

	// as+size fold (FW-52): `as` may carry a `,<size>` arg; legacy `size:` falls back.
	$as     = bws_parse_as_option( $options );
	$result = bws_process_meta_image_value( $raw, $as['mode'], $as['size'] );

	return '' === $result ? '' : bws_gb_tag_output( $result, $options, $instance );
}

