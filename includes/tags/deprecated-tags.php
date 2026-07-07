<?php
/**
 * Deprecated tag wrappers for backward compatibility.
 *
 * These tags register old names that delegate to their replacements.
 * They emit _doing_it_wrong() notices when WP_DEBUG is enabled.
 *
 * Deprecated tags:
 *   current_post_featured_image  → post_featured_image
 *   current_post_meta_image      → post_custom_image
 *   related_post_meta_image      → related_post_custom_image
 *   related_post_url             → related_post_permalink
 *   post_acf_date_time_single    → post_acf_datetime_single
 *   post_acf_date_time_range     → post_acf_datetime_range
 *   term_name                    → term_title
 *   term_field_image             → term_image
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Register deprecated dynamic tags (old names that delegate to new ones).
 *
 * @since 1.0.0
 */
function bws_register_deprecated_tags() {
	// Deprecated tags are no longer registered with GenerateBlocks.
	// Migration data is preserved in MigrationRegistry via bws_register_v1_deprecated_tag_wrappers()
	// and bws_register_early_deprecated_tag_migrations() for the Tag Converter tool.
}
// Registration is called directly from bws_dynamic_tags_register_all() in the main plugin file.

// ===============================================
// DEPRECATED TAG HELPERS
// ===============================================

/**
 * Emit a deprecation notice for a renamed tag.
 *
 * Only triggers when WP_DEBUG is enabled, using WordPress's _doing_it_wrong().
 * Available for external plugins to call from their own deprecated tag callbacks.
 *
 * @since 1.0.0
 * @param string $old_tag The deprecated tag name.
 * @param string $new_tag The replacement tag name.
 * @param string $since   The plugin version when the tag was deprecated. Default '1.0.0'.
 */
function bws_deprecated_tag_notice( string $old_tag, string $new_tag, string $since = '1.0.0' ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		_doing_it_wrong(
			sprintf( 'Dynamic tag: %s', $old_tag ),
			sprintf(
				/* translators: 1: deprecated tag name, 2: replacement tag name */
				__( 'The "%1$s" dynamic tag is deprecated. Use "%2$s" instead.', 'generateblocks' ),
				$old_tag,
				$new_tag
			),
			$since
		);
	}
}

/**
 * Build an editor preview label for a deprecated tag.
 *
 * Returns a bracket-format warning string consistent with bws_build_preview_label().
 * When a MigrationRegistry entry exists, shows the actual migrated tag string using
 * the current option values. When no migration path exists, shows the old tag name
 * with a "no replacement" notice.
 *
 * @since 1.6.0
 * @param string      $old_tag          Deprecated tag name.
 * @param array       $old_options      Parsed options from the old tag (old key format).
 * @param string|null $new_tag_override When set, display this literal string as the replacement
 *                                      (used for early deprecated tags not in MigrationRegistry).
 * @return string Bracket-format preview label.
 */
function bws_build_deprecation_preview_label( string $old_tag, array $old_options, ?string $new_tag_override = null ): string {
	$old_display = '{{' . $old_tag . '}}';

	if ( null !== $new_tag_override ) {
		return '[⚠ ' . $old_display . ' deprecated — use ' . $new_tag_override . ']';
	}

	if ( ! \BWS\DynamicTags\MigrationRegistry::has_migration_path( $old_tag ) ) {
		return '[⚠ ' . $old_display . ' deprecated — no replacement]';
	}

	// GB injects tag_name into every $options array — strip it before reconstructing the tag string.
	$clean_options = array_diff_key( $old_options, array( 'tag_name' => true ) );
	$old_str = \BWS\DynamicTags\MigrationRegistry::format_tag_string( $old_tag, $clean_options );
	$new_str = \BWS\DynamicTags\MigrationRegistry::transform_tag( $old_tag, $old_str );

	return '[⚠ ' . $old_display . ' deprecated — use ' . $new_str . ']';
}


// ===============================================
// V1 DEPRECATED TAG WRAPPERS (N×M → BASE TAGS) — migration data only
// ===============================================

/**
 * Register deprecated wrappers for all N×M source × template generated tags.
 *
 * Migration data only. Each entry provides source_inject, option_renames, value_renames,
 * fixed_options, datetime_transforms, combine_options, and transform_callback for the
 * Tag Converter pipeline. No runtime callbacks or GB registration data.
 *
 * MIGRATION TARGET RULES — new_tag must be a real registered tag name:
 *
 *   Post-context old tags  → bare base tag: image, text, title, permalink,
 *                            content, datetime_single, datetime_range.
 *
 *   Term-context old tags  → term_ modifier tag: term_image, term_text,
 *                            term_title, term_content, term_permalink,
 *                            term_datetime_single, term_datetime_range.
 *
 * 'src:term' is NOT a valid src value. term_ modifier tags are a separate GB
 * tag family (gb_type='term') — they do not accept a 'src' option at all.
 * Never use new_tag:'image' + source_inject:'term'; use new_tag:'term_image'.
 *
 * @since 1.6.0
 */
function bws_register_v1_deprecated_tag_wrappers() {
	$since = '1.6.0';
	$reg   = 'BWS\DynamicTags\DeprecatedTagRegistry';

	// Shared migration option_renames maps (old key → new key).
	$content_renames = array( 'fallback_text' => 'fallback', 'type' => 'use' );
	$content_values  = array( 'use' => array( 'custom_field' => 'key' ) );
	$ct_renames      = array( 'fallback_text' => 'fallback' );
	$fi_renames      = array( 'return_type' => 'as', 'id' => 'fallback' );
	$ci_renames      = array( 'return_type' => 'as', 'fallback_url' => 'fallback', 'id' => 'fallback', 'field_key' => 'key' );
	$cds_renames     = array( 'date_time_field' => 'key', 'fallback_text' => 'fallback' );
	$cdr_renames     = array( 'start_field' => 'startKey', 'end_field' => 'endKey', 'separator' => 'rangeSep', 'fallback_text' => 'fallback' );
	$cdts_renames    = array( 'date_time_field' => 'key', 'time_field' => 'timeKey', 'fallback_text' => 'fallback' );
	$cdtr_renames    = array( 'start_field' => 'startKey', 'start_time_field' => 'startTimeKey', 'end_field' => 'endKey', 'end_time_field' => 'endTimeKey', 'separator' => 'rangeSep', 'date_time_separator' => 'timeSep', 'fallback_text' => 'fallback' );

	// Related-source renames: adds 'rel' → 'ref' (old relationship field key → new).
	// Used for all related_post and term_related_post entries which have source_inject:'ref'.
	$rel_renames      = array( 'rel' => 'ref' );
	$rel_content_renames = array_merge( $rel_renames, $content_renames );
	$rel_ct_renames      = array_merge( $rel_renames, $ct_renames );
	$rel_fi_renames      = array_merge( $rel_renames, $fi_renames );
	$rel_ci_renames      = array_merge( $rel_renames, $ci_renames );
	$rel_cds_renames     = array_merge( $rel_renames, $cds_renames );
	$rel_cdr_renames     = array_merge( $rel_renames, $cdr_renames );
	$rel_cdts_renames    = array_merge( $rel_renames, $cdts_renames );
	$rel_cdtr_renames    = array_merge( $rel_renames, $cdtr_renames );

	// try_ slot renames: src_1 dropped (post default); slots 2-5 renamed.
	// Empty-string new_key = drop the option (handled by MigrationRegistry::run_transform).
	$try_src_renames  = array(
		'src_1' => '',      'rel_1' => 'ref',
		'src_2' => '2-src', 'rel_2' => '2-ref',
		'src_3' => '3-src', 'rel_3' => '3-ref',
		'src_4' => '4-src', 'rel_4' => '4-ref',
		'src_5' => '5-src', 'rel_5' => '5-ref',
	);
	$try_src_values   = array(
		'2-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
		'3-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
		'4-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
		'5-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
	);
	$try_key_renames  = array( 'key_1' => 'key', 'key_2' => '2-key', 'key_3' => '3-key', 'key_4' => '4-key', 'key_5' => '5-key' );
	$try_ct_renames   = array_merge( $try_key_renames, $try_src_renames );
	$try_ci_renames   = array_merge( array( 'return_type' => 'as' ), $try_key_renames, $try_src_renames );
	$try_cds_renames  = array_merge( $cds_renames,  $try_src_renames );
	$try_cdr_renames  = array_merge( $cdr_renames,  $try_src_renames );
	$try_cdts_renames = array_merge( $cdts_renames, $try_src_renames );
	$try_cdtr_renames = array_merge( $cdtr_renames, $try_src_renames );

	// Shared fixed_options.
	$fi_fixed      = array( 'use' => 'featured' );
	$date_fixed    = array( 'as' => 'date' );

	// Term-extraction migration: old `tax:<slug>` → new `srcTermIn:<slug>`.
	// Term-extraction deprecated tags always carry a `tax` value, so a plain rename
	// suffices; the new key both signals the term hop and supplies the slug.
	$srcterm_renames = array( 'tax' => 'srcTermIn' );

	// ==========================================
	// POST SOURCE (source_inject: '')
	// ==========================================

	$reg::register( array(
		'old_tag'        => 'post_content',
		'new_tag'        => 'content',
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => $content_renames,
		'value_renames'  => $content_values,
	) );

	$reg::register( array(
		'old_tag'        => 'post_custom_text',
		'new_tag'        => 'text',
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => $ct_renames,
	) );

	$reg::register( array(
		'old_tag'        => 'post_featured_image',
		'new_tag'        => 'image',
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => $fi_renames,
		'fixed_options'  => $fi_fixed,
	) );

	$reg::register( array(
		'old_tag'        => 'post_custom_image',
		'new_tag'        => 'image',
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => $ci_renames,
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_date_single',
		'new_tag'             => 'datetime_single',
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_date_range',
		'new_tag'             => 'datetime_range',
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_datetime_single',
		'new_tag'             => 'datetime_single',
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cdts_renames,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_datetime_range',
		'new_tag'             => 'datetime_range',
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cdtr_renames,
		'datetime_transforms' => true,
	) );

	// Post → Term extraction.
	$reg::register( array(
		'old_tag'          => 'post_term_title',
		'new_tag'          => 'title',
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => $srcterm_renames,
		'gb_link_remap'    => true,
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_permalink',
		'new_tag'          => 'permalink',
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => $srcterm_renames,
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_description',
		'new_tag'          => 'content',
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => $srcterm_renames,
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_custom_text',
		'new_tag'          => 'text',
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => array_merge( $ct_renames, $srcterm_renames ),
		'gb_link_remap'    => true,
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_custom_image',
		'new_tag'          => 'image',
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => array_merge( $ci_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	// ==========================================
	// RELATED POST SOURCE (source_inject: 'ref')
	// ==========================================

	$reg::register( array(
		'old_tag'        => 'related_post_title',
		'new_tag'        => 'title',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
		'gb_link_remap'  => true,
	) );

	$reg::register( array(
		'old_tag'            => 'related_post_content',
		'new_tag'            => 'content',
		'since'              => $since,
		// target_field-aware transform: branches new_tag on target_field value.
		// Old tag used 'key' (not 'rel') for the relationship field.
		'transform_callback' => static function ( string $tag_string ): string {
			[ , $options ] = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $tag_string );

			$target_field = $options['target_field'] ?? 'post_title';
			$rel_key      = $options['key'] ?? $options['rel'] ?? '';
			$custom_field = $options['custom_field'] ?? '';

			// Map link_to/link_field/new_window → linkTo/linkKey/newTab (V10).
			$old_link_to    = $options['link_to']    ?? '';
			$old_link_field = $options['link_field']  ?? '';
			$old_new_window = array_key_exists( 'new_window', $options );
			$link_extra = array();
			if ( 'post' === $old_link_to ) {
				$link_extra['linkTo'] = 'permalink';
			} elseif ( 'custom' === $old_link_to ) {
				$link_extra['linkTo'] = 'key';
				if ( '' !== $old_link_field ) {
					$link_extra['linkKey'] = $old_link_field;
				}
			}
			if ( $old_new_window && ! empty( $link_extra ) ) {
				$link_extra['newTab'] = true;
			}

			// Drop all old-tag-specific keys that have no current-tag equivalent.
			$drop = array( 'target_field', 'custom_field', 'link_to', 'link_field', 'new_window', 'separator', 'limit', 'id', 'fallback_text', 'type', 'key', 'rel' );
			foreach ( $drop as $k ) {
				unset( $options[ $k ] );
			}

			switch ( $target_field ) {
				case 'post_content':
					$new_tag    = 'content';
					$extra      = array();
					$link_extra = array(); // content tag excluded from link wrap.
					break;
				case 'post_excerpt':
					$new_tag    = 'content';
					$extra      = array( 'use' => 'excerpt' );
					$link_extra = array(); // content tag excluded from link wrap.
					break;
				case 'custom':
					$new_tag = 'text';
					$extra   = '' !== $custom_field ? array( 'key' => $custom_field ) : array();
					break;
				default: // 'post_title' and absent (default was post_title).
					$new_tag = 'title';
					$extra   = array();
					break;
			}

			$new_options = array_merge( array( 'src' => 'ref' ), '' !== $rel_key ? array( 'ref' => $rel_key ) : array(), $link_extra, $extra, $options );

			return \BWS\DynamicTags\MigrationRegistry::format_tag_string( $new_tag, $new_options );
		},
	) );

	$reg::register( array(
		'old_tag'        => 'related_post_permalink',
		'new_tag'        => 'permalink',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
	) );

	$reg::register( array(
		'old_tag'        => 'related_post_custom_text',
		'new_tag'        => 'text',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_ct_renames,
		'gb_link_remap'  => true,
	) );

	$reg::register( array(
		'old_tag'        => 'related_post_featured_image',
		'new_tag'        => 'image',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_fi_renames,
		'fixed_options'  => $fi_fixed,
	) );

	$reg::register( array(
		'old_tag'        => 'related_post_custom_image',
		'new_tag'        => 'image',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_ci_renames,
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_date_single',
		'new_tag'             => 'datetime_single',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_date_range',
		'new_tag'             => 'datetime_range',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_datetime_single',
		'new_tag'             => 'datetime_single',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdts_renames,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_datetime_range',
		'new_tag'             => 'datetime_range',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdtr_renames,
		'datetime_transforms' => true,
	) );

	// Related Post → Term extraction.
	$reg::register( array(
		'old_tag'          => 'related_post_term_title',
		'new_tag'          => 'title',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_permalink',
		'new_tag'          => 'permalink',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_description',
		'new_tag'          => 'content',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_custom_text',
		'new_tag'          => 'text',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ct_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_custom_image',
		'new_tag'          => 'image',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ci_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	// ==========================================
	// TERM RELATED POST SOURCE (source_inject: 'ref')
	// ==========================================

	$reg::register( array(
		'old_tag'        => 'term_related_post_title',
		'new_tag'        => 'term_title',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
		'gb_link_remap'  => true,
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_content',
		'new_tag'        => 'term_content',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_content_renames,
		'value_renames'  => $content_values,
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_permalink',
		'new_tag'        => 'term_permalink',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_custom_text',
		'new_tag'        => 'term_text',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_ct_renames,
		'gb_link_remap'  => true,
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_featured_image',
		'new_tag'        => 'term_image',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_fi_renames,
		'fixed_options'  => $fi_fixed,
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_custom_image',
		'new_tag'        => 'term_image',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_ci_renames,
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_date_single',
		'new_tag'             => 'term_datetime_single',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_date_range',
		'new_tag'             => 'term_datetime_range',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_datetime_single',
		'new_tag'             => 'term_datetime_single',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdts_renames,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_datetime_range',
		'new_tag'             => 'term_datetime_range',
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdtr_renames,
		'datetime_transforms' => true,
	) );

	// Term→Rel. Post → Term extraction.
	$reg::register( array(
		'old_tag'          => 'term_related_post_term_title',
		'new_tag'          => 'term_title',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_permalink',
		'new_tag'          => 'term_permalink',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_description',
		'new_tag'          => 'term_content',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_custom_text',
		'new_tag'          => 'term_text',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ct_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_custom_image',
		'new_tag'          => 'term_image',
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ci_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
	) );

	// ==========================================
	// TERM CONTEXT N×M TAGS (description template + term-context custom field templates)
	// ==========================================

	$reg::register( array(
		'old_tag'  => 'term_description',
		'new_tag'  => 'term_content',
		'since'    => $since,
	) );

	$reg::register( array(
		'old_tag'        => 'term_custom_text',
		'new_tag'        => 'term_text',
		'since'          => $since,
		'option_renames' => $ct_renames,
	) );

	$reg::register( array(
		'old_tag'        => 'term_custom_image',
		'new_tag'        => 'term_image',
		'since'          => $since,
		'option_renames' => $ci_renames,
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_date_single',
		'new_tag'             => 'term_datetime_single',
		'since'               => $since,
		'option_renames'      => $cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_date_range',
		'new_tag'             => 'term_datetime_range',
		'since'               => $since,
		'option_renames'      => $cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_datetime_single',
		'new_tag'             => 'term_datetime_single',
		'since'               => $since,
		'option_renames'      => $cdts_renames,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_datetime_range',
		'new_tag'             => 'term_datetime_range',
		'since'               => $since,
		'option_renames'      => $cdtr_renames,
		'datetime_transforms' => true,
	) );

	// ==========================================
	// TRY_ DEPRECATED WRAPPERS
	// Old N×M try_ tags used src_N/key_N/rel_N slot options.
	// Migration: src_1 dropped (post default); slots 2-5 renamed to N-src/N-ref/N-key.
	// Value rename: 'related'/'related_post' → 'ref' on N-src keys.
	// ==========================================

	$reg::register( array(
		'old_tag'        => 'try_custom_text',
		'new_tag'        => 'try_text',
		'since'          => $since,
		'option_renames' => $try_ct_renames,
		'value_renames'  => $try_src_values,
	) );

	$reg::register( array(
		'old_tag'        => 'try_featured_image',
		'new_tag'        => 'try_image',
		'since'          => $since,
		'option_renames' => $try_src_renames,
		'value_renames'  => $try_src_values,
		'fixed_options'  => $fi_fixed,
	) );

	$reg::register( array(
		'old_tag'        => 'try_custom_image',
		'new_tag'        => 'try_image',
		'since'          => $since,
		'option_renames' => $try_ci_renames,
		'value_renames'  => $try_src_values,
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_date_single',
		'new_tag'             => 'try_datetime_single',
		'since'               => $since,
		'option_renames'      => $try_cds_renames,
		'value_renames'       => $try_src_values,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_date_range',
		'new_tag'             => 'try_datetime_range',
		'since'               => $since,
		'option_renames'      => $try_cdr_renames,
		'value_renames'       => $try_src_values,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_datetime_single',
		'new_tag'             => 'try_datetime_single',
		'since'               => $since,
		'option_renames'      => $try_cdts_renames,
		'value_renames'       => $try_src_values,
		'datetime_transforms' => true,
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_datetime_range',
		'new_tag'             => 'try_datetime_range',
		'since'               => $since,
		'option_renames'      => $try_cdtr_renames,
		'value_renames'       => $try_src_values,
		'datetime_transforms' => true,
	) );

	// ==========================================
	// SECOND RELATED POST / POST→TERM→RELATED POST — no migration path.
	// No current tag reaches a *second-hop* relationship or a term-then-relationship
	// chain, so these have no new_tag. Kept so MigrationRegistry/Tag Converter/Settings
	// page can still find and report them; entry shape omits GB registration fields
	// (title/options/callback) since nothing registers these with GB anymore.
	// ==========================================

	foreach ( array(
		'second_related_post_title',
		'second_related_post_content',
		'second_related_post_permalink',
		'second_related_post_custom_text',
		'second_related_post_featured_image',
		'second_related_post_custom_image',
		'second_related_post_custom_date_single',
		'second_related_post_custom_date_range',
		'second_related_post_custom_datetime_single',
		'second_related_post_custom_datetime_range',
		'second_related_post_term_title',
		'second_related_post_term_permalink',
		'second_related_post_term_description',
		'second_related_post_term_custom_text',
		'second_related_post_term_custom_image',
	) as $old_tag ) {
		$reg::register( array(
			'old_tag' => $old_tag,
			'since'   => $since,
		) );
	}

	foreach ( array(
		'post_term_related_post_title',
		'post_term_related_post_content',
		'post_term_related_post_permalink',
		'post_term_related_post_custom_text',
		'post_term_related_post_featured_image',
		'post_term_related_post_custom_image',
		'post_term_related_post_custom_date_single',
		'post_term_related_post_custom_date_range',
		'post_term_related_post_custom_datetime_single',
		'post_term_related_post_custom_datetime_range',
	) as $old_tag ) {
		$reg::register( array(
			'old_tag' => $old_tag,
			'since'   => $since,
		) );
	}
}

// ===============================================
// EARLY DEPRECATED TAG MIGRATIONS (pre-v1.6 tags)
// ===============================================

/**
 * Register MigrationRegistry entries for the eight early deprecated tags.
 *
 * These tags predate bws_register_v1_deprecated_tag_wrappers() and were originally
 * hardcoded in bws_register_deprecated_tags() without migration paths. Adding entries
 * here enables the admin converter and live preview labels for all eight.
 *
 * Called from bws_dynamic_tags_register_all() after bws_register_v1_deprecated_tag_wrappers().
 *
 * @since 1.6.0
 */
function bws_register_early_deprecated_tag_migrations(): void {
	$reg   = 'BWS\DynamicTags\MigrationRegistry';
	$since = '1.0.0';

	// current_post_featured_image → image (use:featured).
	// Old options: return_type (→ as). No field key.
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'current_post_featured_image',
		'new_tag'        => 'image',
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => array( 'return_type' => 'as', 'id' => 'fallback' ),
		'fixed_options'  => array( 'use' => 'featured' ),
	) );

	// current_post_meta_image → image.
	// Old options: meta_key (→ key), return_type (→ as), id (→ fallback).
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'current_post_meta_image',
		'new_tag'        => 'image',
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => array( 'meta_key' => 'key', 'field_key' => 'key', 'return_type' => 'as', 'fallback_url' => 'fallback', 'id' => 'fallback' ),
	) );

	// related_post_meta_image → image (src:ref).
	// Old options: rel (→ ref, relationship field), meta_key (→ key, image field), return_type (→ as), id (→ fallback).
	// Note: 'key' in old tag was the relationship field; image field was 'meta_key'.
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'related_post_meta_image',
		'new_tag'        => 'image',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => array( 'rel' => 'ref', 'key' => 'ref', 'meta_key' => 'key', 'field_key' => 'key', 'return_type' => 'as', 'fallback_url' => 'fallback', 'id' => 'fallback' ),
	) );

	// related_post_url → permalink (src:ref).
	// Old options: rel or key (→ ref, relationship field).
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'related_post_url',
		'new_tag'        => 'permalink',
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => array( 'rel' => 'ref', 'key' => 'ref' ),
	) );

	// post_acf_date_time_single → datetime_single.
	// Old options: date_time_field (→ key), time_field (→ timeKey), fallback_text (→ fallback), datetime booleans.
	$reg::register( array(
		'type'                => 'tag',
		'old_tag'             => 'post_acf_date_time_single',
		'new_tag'             => 'datetime_single',
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => array( 'date_time_field' => 'key', 'time_field' => 'timeKey', 'fallback_text' => 'fallback' ),
		'datetime_transforms' => true,
	) );

	// post_acf_date_time_range → datetime_range.
	// Old options: start_field (→ startKey), end_field (→ endKey), separator (→ rangeSep), fallback_text (→ fallback), datetime booleans.
	$reg::register( array(
		'type'                => 'tag',
		'old_tag'             => 'post_acf_date_time_range',
		'new_tag'             => 'datetime_range',
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => array( 'start_field' => 'startKey', 'end_field' => 'endKey', 'separator' => 'rangeSep', 'fallback_text' => 'fallback' ),
		'datetime_transforms' => true,
	) );

	// term_name → term_title (standalone term modifier tag; no source inject).
	$reg::register( array(
		'type'     => 'tag',
		'old_tag'  => 'term_name',
		'new_tag'  => 'term_title',
		'since'    => $since,
	) );

	// term_field_image → term_image (standalone term modifier tag; no source inject).
	// Old options: meta_key (→ key), return_type (→ as), id (→ fallback).
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'term_field_image',
		'new_tag'        => 'term_image',
		'since'          => $since,
		'option_renames' => array( 'meta_key' => 'key', 'field_key' => 'key', 'return_type' => 'as', 'fallback_url' => 'fallback', 'id' => 'fallback' ),
	) );
}

// ===============================================
// OPTION MIGRATIONS (type:'option' registry entries)
// ===============================================

/**
 * Register option-key migration entries for base tags with deprecated option names.
 *
 * These entries fix posts that were partially migrated by a buggy converter run that
 * renamed the tag but left old option keys in place (e.g. `rel` instead of `ref`).
 * Unlike type:'tag' entries, these match on a live base tag name + presence of specific
 * option keys. The tag name is unchanged; only options are transformed.
 *
 * Called from bws_dynamic_tags_register_all() after bws_register_v1_deprecated_tag_wrappers().
 *
 * @since 1.6.0
 */
function bws_register_option_migrations(): void {
	$reg = 'BWS\DynamicTags\MigrationRegistry';

	// Base tags that carry a 'ref' relationship option when source:ref — if 'rel' is present
	// instead, the tag was converted by the buggy pre-fix converter. Rename rel→ref and ensure
	// source:ref is injected first.
	$rel_fix = array(
		'option_renames' => array( 'rel' => 'ref' ),
		'source_inject'  => 'ref',
	);

	foreach ( array( 'text', 'content', 'title', 'permalink', 'image', 'datetime_single', 'datetime_range' ) as $base_tag ) {
		$reg::register( array_merge( $rel_fix, array(
			'type'          => 'option',
			'match_tag'     => $base_tag,
			'match_options' => array( 'rel' ),
			'new_tag'       => $base_tag,
			'label'         => sprintf(
				/* translators: %s: base tag name */
				__( '{{%s}}: rel → source:ref|ref (broken converter output)', 'generateblocks' ),
				$base_tag
			),
		) ) );
	}

	// image, term_image, try_image existed in v1.5.x with type:'media' — GB stored the
	// attachment ID in the 'id' option. Rename to 'fallback' (v1.6.0 option name).
	$id_to_fallback = array(
		'option_renames' => array( 'id' => 'fallback' ),
	);

	foreach ( array( 'image', 'term_image', 'try_image' ) as $tag ) {
		$reg::register( array_merge( $id_to_fallback, array(
			'type'          => 'option',
			'match_tag'     => $tag,
			'match_options' => array( 'id' ),
			'new_tag'       => $tag,
			'label'         => sprintf(
				/* translators: %s: tag name */
				__( '{{%s}}: id → fallback (v1.5 media picker → v1.6 custom picker)', 'generateblocks' ),
				$tag
			),
		) ) );
	}

	// C7: 'source' option key renamed to 'src' (v1.6.x). GB unconditionally destructures
	// 'source' from parsed tag params before spreading into extraTagParams, so any option
	// named 'source' is silently eaten — the editor control never receives the value.
	// Matches tags where 'source' is present (e.g. source:ref from prior saves or C5/C6
	// migration output that used source_inject before it was updated to emit 'src').
	$source_to_src = array(
		'option_renames' => array( 'source' => 'src' ),
	);

	foreach ( array( 'text', 'content', 'title', 'permalink', 'image', 'datetime_single', 'datetime_range' ) as $base_tag ) {
		$reg::register( array_merge( $source_to_src, array(
			'type'          => 'option',
			'match_tag'     => $base_tag,
			'match_options' => array( 'source' ),
			'new_tag'       => $base_tag,
			'label'         => sprintf(
				/* translators: %s: base tag name */
				__( '{{%s}}: source → src (GB reserved key conflict fix)', 'generateblocks' ),
				$base_tag
			),
		) ) );
	}

	// srcTerm + tax → srcTermIn (combined). GB-reserved 'tax' is silently dropped on cross-source
	// base tags; `srcTerm` boolean was a separate gate. Both retired in favor of single `srcTermIn`
	// (slug presence = enabled). Matched when `tax` is present (the data carrier); `srcTerm` alone
	// is a no-op and not worth flagging.
	$srcterm_combine = array(
		'combine_options' => array(
			'srcTermIn' => array(
				'when_present' => 'srcTerm',
				'value_from'   => 'tax',
			),
		),
	);

	foreach ( array( 'text', 'content', 'title', 'permalink', 'image', 'datetime_single', 'datetime_range' ) as $base_tag ) {
		$reg::register( array_merge( $srcterm_combine, array(
			'type'          => 'option',
			'match_tag'     => $base_tag,
			'match_options' => array( 'tax' ),
			'new_tag'       => $base_tag,
			'label'         => sprintf(
				/* translators: %s: base tag name */
				__( '{{%s}}: srcTerm + tax → srcTermIn (GB reserved key fix)', 'generateblocks' ),
				$base_tag
			),
		) ) );
	}

	// Live datetime tags carrying old (pre-v1.6) option keys. Tag name was renamed by a
	// prior migration pass but datetime field/separator/format/boolean keys were left in
	// the old form. Same rename maps and datetime_transforms used for type:'tag' entries.
	$cdts_renames = array( 'date_time_field' => 'key', 'time_field' => 'timeKey', 'fallback_text' => 'fallback' );
	$cdtr_renames = array(
		'start_field'         => 'startKey',
		'start_time_field'    => 'startTimeKey',
		'end_field'           => 'endKey',
		'end_time_field'      => 'endTimeKey',
		'separator'           => 'rangeSep',
		'date_time_separator' => 'timeSep',
		'fallback_text'       => 'fallback',
	);

	// Old key set (any one of these means the tag predates the rename and the datetime
	// transforms (format_type, date_only, time_only, smart_time, omit_current_year) need
	// to run too.
	$datetime_single_old_keys = array(
		'date_time_field', 'time_field', 'fallback_text',
		'format_type', 'custom_format', 'date_only', 'time_only',
		'smart_time', 'omit_current_year',
	);
	$datetime_range_old_keys = array(
		'start_field', 'start_time_field', 'end_field', 'end_time_field',
		'separator', 'date_time_separator', 'fallback_text',
		'format_type', 'custom_format', 'date_only', 'time_only',
		'smart_time', 'omit_current_year',
	);

	$reg::register( array(
		'type'                => 'option',
		'match_tag'           => 'datetime_single',
		'match_any_options'   => $datetime_single_old_keys,
		'new_tag'             => 'datetime_single',
		'option_renames'      => $cdts_renames,
		'datetime_transforms' => true,
		'label'               => __( '{{datetime_single}}: legacy field/format keys → v1.6 names', 'generateblocks' ),
	) );

	$reg::register( array(
		'type'                => 'option',
		'match_tag'           => 'datetime_range',
		'match_any_options'   => $datetime_range_old_keys,
		'new_tag'             => 'datetime_range',
		'option_renames'      => $cdtr_renames,
		'datetime_transforms' => true,
		'label'               => __( '{{datetime_range}}: legacy field/format keys → v1.6 names', 'generateblocks' ),
	) );

	// fallback_text → fallback on every base tag (universal rename). Single-key gate;
	// safe to register as a narrow entry alongside other base-tag entries because the
	// apply_option_migration loop cascades.
	foreach ( array( 'text', 'content', 'title', 'permalink', 'image' ) as $base_tag ) {
		$reg::register( array(
			'type'           => 'option',
			'match_tag'      => $base_tag,
			'match_options'  => array( 'fallback_text' ),
			'new_tag'        => $base_tag,
			'option_renames' => array( 'fallback_text' => 'fallback' ),
			'label'          => sprintf(
				/* translators: %s: base tag name */
				__( '{{%s}}: fallback_text → fallback', 'generateblocks' ),
				$base_tag
			),
		) );
	}

	// via / from → src on base tags. Pre-`source` rename predates `source` → `src` chain
	// and was not covered by the type:'option' entry that handles `source`. Use match_any
	// so either key triggers; both rename to `src`.
	foreach ( array( 'text', 'content', 'title', 'permalink', 'image', 'datetime_single', 'datetime_range' ) as $base_tag ) {
		$reg::register( array(
			'type'              => 'option',
			'match_tag'         => $base_tag,
			'match_any_options' => array( 'via', 'from' ),
			'new_tag'           => $base_tag,
			'option_renames'    => array( 'via' => 'src', 'from' => 'src' ),
			'label'             => sprintf(
				/* translators: %s: base tag name */
				__( '{{%s}}: via/from → src', 'generateblocks' ),
				$base_tag
			),
		) );
	}

	// content tag: legacy `type:custom_field` + `key:<slug>` → `use:key|key:<slug>`.
	// The matching type:'tag' migration (post_content → content) applied $content_values
	// to map value `custom_field` → `key` after renaming `type` → `use`. Replicate for
	// live `content` tags that already had the tag name but kept old option keys.
	$reg::register( array(
		'type'           => 'option',
		'match_tag'      => 'content',
		'match_options'  => array( 'type' ),
		'new_tag'        => 'content',
		'option_renames' => array( 'type' => 'use' ),
		'value_renames'  => array( 'use' => array( 'custom_field' => 'key' ) ),
		'label'          => __( '{{content}}: type → use (value custom_field → key)', 'generateblocks' ),
	) );

	// image / term_image / try_image: pre-v1.6 keys beyond `id` (already handled above).
	// `return_type` → `as`, `fallback_url` → `fallback`, `field_key` → `key`.
	$image_renames    = array( 'return_type' => 'as', 'fallback_url' => 'fallback', 'field_key' => 'key' );
	$image_match_any  = array( 'return_type', 'fallback_url', 'field_key' );
	foreach ( array( 'image', 'term_image', 'try_image' ) as $tag ) {
		$reg::register( array(
			'type'              => 'option',
			'match_tag'         => $tag,
			'match_any_options' => $image_match_any,
			'new_tag'           => $tag,
			'option_renames'    => $image_renames,
			'label'             => sprintf(
				/* translators: %s: tag name */
				__( '{{%s}}: return_type/fallback_url/field_key → as/fallback/key', 'generateblocks' ),
				$tag
			),
		) );
	}

	// try_* slot-key renames: src_1 dropped (default), src_2..5 → 2-src..5-src,
	// rel_N → N-ref, key_N → N-key (slot 1 → bare `key`). Matches any slot key.
	$try_slot_renames = array(
		'src_1' => '',      'rel_1' => 'ref',  'key_1' => 'key',
		'src_2' => '2-src', 'rel_2' => '2-ref', 'key_2' => '2-key',
		'src_3' => '3-src', 'rel_3' => '3-ref', 'key_3' => '3-key',
		'src_4' => '4-src', 'rel_4' => '4-ref', 'key_4' => '4-key',
		'src_5' => '5-src', 'rel_5' => '5-ref', 'key_5' => '5-key',
	);
	$try_slot_values  = array(
		'2-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
		'3-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
		'4-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
		'5-src' => array( 'related' => 'ref', 'related_post' => 'ref' ),
	);
	$try_slot_match   = array_keys( $try_slot_renames );

	foreach ( array( 'try_text', 'try_content', 'try_title', 'try_permalink', 'try_image', 'try_datetime_single', 'try_datetime_range' ) as $tag ) {
		$reg::register( array(
			'type'              => 'option',
			'match_tag'         => $tag,
			'match_any_options' => $try_slot_match,
			'new_tag'           => $tag,
			'option_renames'    => $try_slot_renames,
			'value_renames'     => $try_slot_values,
			'label'             => sprintf(
				/* translators: %s: tag name */
				__( '{{%s}}: legacy slot keys (src_N/rel_N/key_N) → v1.6 slot syntax', 'generateblocks' ),
				$tag
			),
		) );
	}
}
