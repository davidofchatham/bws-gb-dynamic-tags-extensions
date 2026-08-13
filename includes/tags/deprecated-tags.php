<?php
/**
 * Deprecated tag migration data (formerly: deprecated tag wrappers).
 *
 * Historically these tags stayed registered with GB, delegating to their
 * replacements and emitting _doing_it_wrong() notices when WP_DEBUG was
 * enabled. As of 1.14.0 no deprecated tag is registered with GB or renders —
 * this file now provides MigrationRegistry data only, so the admin Tag
 * Converter and settings page can still find and migrate old content.
 *
 * bws_deprecated_tag_notice() and bws_build_deprecation_preview_label() are
 * KEPT BY DESIGN and are currently uncalled — retained for a future
 * deprecated-tag family that needs live rendering again. Do not remove them as
 * dead code: having no callers is their expected steady state. The 1.14.0
 * callback factories (bws_make_deprecated_try_callback() and the per-tag
 * bws_deprecated_*_callback() functions) are gone; only these two remain.
 *
 * Early deprecated tags (pre-1.6.0):
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
 * @since 1.14.0 GB registration + runtime callbacks removed; migration-data-only.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Register deprecated dynamic tags (old names that delegate to new ones).
 *
 * @since 1.0.0
 * @since 1.14.0 Emptied — GB registration + runtime callbacks for all current
 *               deprecated tags removed (SPEC B1/B2, V1, V7). Migration data
 *               stays live via bws_register_v1_deprecated_tag_wrappers() and
 *               bws_register_early_deprecated_tag_migrations() so the admin
 *               Tag Converter and settings page can still find + migrate old
 *               content. No deprecated tag name appears in the GB tag picker.
 * @invariant Body stays empty. Any future deprecated-tag family is registered
 *            here ONLY if it needs live GB rendering again — otherwise its
 *            data belongs solely in the two migration-registration functions
 *            above, matching the removed-tag pattern.
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
 * @internal Uncalled by design since 1.14.0 (no deprecated tag renders). Kept for
 *           a future deprecated-tag family that needs live rendering, and as a
 *           public affordance for external plugins. Not dead code — do not remove
 *           on a zero-callers sweep.
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
 * @internal Uncalled by design since 1.14.0 (no deprecated tag renders, so nothing
 *           builds a deprecated tag's editor preview). Kept for a future
 *           deprecated-tag family that needs live rendering. Not dead code — do not
 *           remove on a zero-callers sweep.
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
 * @invariant Every register() call here carries ONLY migration keys (old_tag,
 *            new_tag, source_inject, option_renames, value_renames, fixed_options,
 *            datetime_transforms, combine_options, required_options, since,
 *            transform_callback, gb_link_remap) — never callback/options/title/
 *            description/supports/gb_type (those were GB-registration fields,
 *            removed 1.14.0). transform_callback is a migration-pipeline hook
 *            (MigrationRegistry::run_transform()), not a GB renderer — keep it.
 * @invariant second_related_post_* (15) and post_term_related_post_* (10) carried
 *            old_tag+since ONLY until 1.17.0, because no current tag reached a
 *            second-hop relationship or a term-then-relationship chain. Source
 *            CHAINS state both shapes, so they now carry a new_tag and a
 *            transform. Do not delete these foreach blocks wholesale when
 *            touching this function; they were mistakenly dropped once already
 *            (SPEC B1) and their loss silently emptied the settings page's "no
 *            migration path" list and starved Tag Converter of ~25 entries —
 *            which is precisely why they were still here to receive targets.
 * @invariant $rel_renames (and every var merged from it) maps BOTH 'key' and
 *            'rel' → 'ref'. 'key' is the legacy pre-'rel' spelling for the same
 *            relationship field; RelatedPost::resolve_id() still fallback-accepts
 *            it. Map 'key' before 'rel' so 'rel' wins if a tag string somehow has
 *            both (SPEC B2).
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
	// 'key' is the legacy pre-'rel' spelling for the same relationship field (matches the
	// RelatedPost source's own key-fallback precedence: 'rel' wins if both are present since
	// it's processed second here). Not used by second_related_post/post_term_related_post,
	// which use rel/rel1/rel2 only (no legacy 'key' alias ever existed for those).
	$rel_renames      = array( 'key' => 'ref', 'rel' => 'ref' );
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
	// SECOND RELATED POST / POST→TERM→RELATED POST — TARGETS AT LAST (1.17.0).
	//
	// These 25 entries carried `old_tag` + `since` and nothing else for four releases,
	// because no current tag could reach a second-hop relationship or a
	// term-then-relationship chain. Source CHAINS state exactly those two shapes:
	//
	//   second_related_post_*     (15)  →  src:refs,<rel>;refs,<rel_2>
	//   post_term_related_post_*  (10)  →  src:terms,<tax>;refs,<rel>
	//
	// `term_related_post_*` is unaffected — those always carried a `new_tag`.
	//
	// THIS IS THE ENDURING RULE PAYING OFF: never delete a register() call just for
	// lacking migration data. The rows were kept in 1.14.0 precisely so a later release
	// could give them targets — after being dropped once by mistake (SPEC B1), which
	// silently emptied the settings page's "no migration path" list and starved the Tag
	// Converter of these ~25 entries.
	//
	// Risk is ONE-DIRECTIONAL. The renderers were stripped in 1.14.0, so these tags
	// produce nothing today: migration moves them from broken to correct, and there is
	// no working output to break. It is also the one VISIBLE change in this release —
	// everything else here is the same output under a different spelling, while this
	// makes a blank spot start printing content. Announce it as a capability restored,
	// not as a fix for a known population: live instances are deliberately unsurveyed.
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
			'old_tag'            => $old_tag,
			'new_tag'            => bws_nxm_chain_target( $old_tag, 'second_related_post_' ),
			'since'              => $since,
			'transform_callback' => 'bws_migrate_second_related_post_chain',
			'gb_link_remap'      => true,
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
			'old_tag'            => $old_tag,
			'new_tag'            => bws_nxm_chain_target( $old_tag, 'post_term_related_post_' ),
			'since'              => $since,
			'transform_callback' => 'bws_migrate_post_term_related_post_chain',
			'gb_link_remap'      => true,
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
 * @since 1.14.0 `callback` key stripped from all 8 entries (GB registration
 *               removed) — migration data (old_tag, new_tag, since, etc.) kept.
 * @invariant MigrationRegistry::get_deprecated_tag_names() returns the same set
 *            of 8 tag names before and after any future edit here; no entry
 *            carries a `callback` key (that was GB-registration wiring for
 *            functions deleted in 1.14.0 — do not re-add).
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
 * Migration transform_callback: fold a legacy separate `size:` option into `as`'s value.
 *
 * The as+size fold (FW-52, v1.16.0) retires GB's native image-size control; size now
 * rides inside the `as` value as a comma second slot (`as:url,<size>`). Old saved tags
 * stored size in a separate `size:` (bare) or `N-size:` (try_ slot) option — an orphan
 * GB keeps verbatim after the fold, diverging string from modal. This rewrites each
 * image slot, value-conditionally:
 *
 *   - url mode (or `as` absent → url default) + size present → `as:url,<size>`
 *   - nullary mode (id/alt/title/caption) + size present     → DROP size (dead at render)
 *   - already folded / no size                               → unchanged
 *
 * Handles slot 1 (bare `as`/`size`) and try_ slots 2..5 (`N-as`/`N-size`) uniformly.
 * Registered per image variant (image / term_image / try_image).
 *
 * @since 1.16.0
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string (unchanged when nothing to fold).
 */
function bws_migrate_image_as_size_fold( string $tag_string ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	[ $tag_name, $options ] = $reg::parse_tag_string( $tag_string );

	// Nullary return modes take no size argument; only `url` (and the url default) does.
	$nullary = array( 'id', 'alt', 'title', 'caption' );

	// Fold each slot: '' = bare (slot 1), '2'..'5' = try_ prefixes.
	foreach ( array( '', '2', '3', '4', '5' ) as $slot ) {
		$prefix   = ( '' === $slot ) ? '' : $slot . '-';
		$as_key   = $prefix . 'as';
		$size_key = $prefix . 'size';

		// Skip slots with no size to fold. (A bare `as:url` with no legacy size is
		// left as-is here; the editor composite writes url,full on open — the migration
		// only needs to rescue orphan `size:` tokens.)
		if ( ! array_key_exists( $size_key, $options ) ) {
			continue;
		}

		$size = trim( (string) $options[ $size_key ] );
		unset( $options[ $size_key ] );

		// Read the raw `as` value; it may already carry a folded size.
		$as_raw   = isset( $options[ $as_key ] ) ? (string) $options[ $as_key ] : 'url';
		$as_bits  = explode( ',', $as_raw, 2 );
		$mode     = ( '' !== $as_bits[0] ) ? $as_bits[0] : 'url';
		$folded   = isset( $as_bits[1] ) && '' !== $as_bits[1]; // `as` already carries a size.

		if ( in_array( $mode, $nullary, true ) ) {
			// Size was dead on a nullary return — drop it, keep the bare mode.
			$options[ $as_key ] = $mode;
		} elseif ( $folded ) {
			// Already folded — the folded value wins; the stray legacy size is stale.
			$options[ $as_key ] = $mode . ',' . $as_bits[1];
		} else {
			// url (or any non-nullary), not yet folded → fold the legacy size in.
			$options[ $as_key ] = 'url,' . ( '' !== $size ? $size : 'full' );
		}
	}

	return $reg::format_tag_string( $tag_name, $options );
}

/**
 * Migration transform_callback: `src:related_post` → `src:ref` + `ref:<field>` (#56).
 *
 * The `related_post` SOURCE TOKEN is the only one of the four related-post source classes
 * that ever appeared in stored wire (as a `try_` slot `src` value; the tag-level `src`
 * dropdown has only ever offered current/ref/site). Its class resolved the hop itself,
 * reading the relationship field from `rel`, falling back to `key` — a vocabulary NOTHING
 * else in the plugin honours, since the chain compiler builds its `refs` step from `ref`
 * alone. 1.17.0 made those classes inert, so this rewrite is what keeps such a tag reading
 * what it read before.
 *
 * **WHICH KEY WINS IS DECIDED BY `src`, NOT BY A RANKING OF THE KEYS.** The two spellings
 * are not competing candidates with a fixed precedence; each is live under exactly one
 * source token and inert under the other:
 *
 *   `src:related_post`  the retired class read `rel`, then `key`. It never read `ref`.
 *   `src:ref`           the compiler reads `ref`. Nothing reads `rel`.
 *
 * So under the token this transform fires on, an existing `ref` is INERT — letting it win
 * would migrate the tag to hop somewhere the old tag never hopped. That is the same defect
 * in miniature that #56 is about: answering one question from the wrong reader.
 *
 * Per slot (bare = tag level / slot 1, `N-` = legacy flat slot ≥2):
 *   - `src:related_post`              → `src:ref`
 *   - `rel:<field>` present           → MOVED to `ref:<field>`, overwriting any inert `ref`
 *   - no `rel`, `key:<field>` present → COPIED to `ref:<field>`, `key` KEPT
 *   - neither present                 → any existing `ref` is LEFT ALONE (see below)
 *
 * **`key` is copied, never moved, and that asymmetry is the whole correctness argument.**
 * Under `src:related_post|key:foo` the source class consumed `foo` as the relationship
 * field, AND the downstream field read consumed the same `foo` as the field key — the
 * options array was never mutated between the two. Moving it would resolve the hop and
 * then read no field, turning a working tag into an empty one. `rel` has no second reader,
 * so moving it is lossless.
 *
 * The last row is the one deliberate departure from strict preservation, named rather than
 * hidden: `src:related_post|ref:A` with no `rel`/`key` resolved to FALSE and fell through
 * to the ambient entity, so preserving output would mean deleting `ref:A`. This transform
 * does not, because its mandate is to move a relationship key OUT of a dead vocabulary,
 * not to delete one already written in the live vocabulary — and that wire can only come
 * from a hand-edit mixing two eras (`ref` postdates the `related_post` token), where the
 * literal current-vocabulary reading is the better guess at intent.
 *
 * Runs BEFORE the fold entry, which is load-bearing: the fold consumes flat `N-src` keys
 * and rewrites them into folded `{N}:` slot values, after which this transform can no
 * longer see the token.
 *
 * @since 1.17.0
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string (unchanged when no slot names the legacy source).
 */
/**
 * The N×M suffix → current base/modifier tag map, and the target for one old tag.
 *
 * The 25 target-less entries are `<family>_<suffix>` where the suffix names the same
 * shapes every other deprecated family already maps. Derived from the suffix rather
 * than listed per tag: 25 hand-written `new_tag` values is 25 chances to write
 * `custom_image` → `text`, and the families differ ONLY in the chain their transform
 * builds, never in which tag renders it.
 *
 * The `*_term_*` suffixes on `second_related_post_*` land on `term_` modifier tags:
 * their last hop was post→term, which the modifier prefix is exactly for. The plain
 * suffixes land on base tags, whose source is now the chain.
 *
 * @since 1.17.0
 * @param string $old_tag Full deprecated tag name.
 * @param string $prefix  The family prefix to strip.
 * @return string Current tag name ('' when the suffix is unknown — the caller then
 *                registers an entry with no target, which is the pre-1.17.0 state
 *                rather than a wrong rewrite).
 */
function bws_nxm_chain_target( string $old_tag, string $prefix ): string {
	$map = array(
		'title'                    => 'title',
		'content'                  => 'content',
		'permalink'                => 'permalink',
		'custom_text'              => 'text',
		'featured_image'           => 'image',
		'custom_image'             => 'image',
		'custom_date_single'       => 'datetime_single',
		'custom_date_range'        => 'datetime_range',
		'custom_datetime_single'   => 'datetime_single',
		'custom_datetime_range'    => 'datetime_range',
		// The last hop is post→term, which is what the modifier prefix names.
		'term_title'               => 'term_title',
		'term_permalink'           => 'term_permalink',
		'term_description'         => 'term_content',
		'term_custom_text'         => 'term_text',
		'term_custom_image'        => 'term_image',
	);
	$suffix = ( 0 === strpos( $old_tag, $prefix ) ) ? substr( $old_tag, strlen( $prefix ) ) : '';
	return $map[ $suffix ] ?? '';
}

/**
 * Build a depth-0 chain from an ordered list of [slug, arg] steps.
 *
 * Returns an empty array when any step lacks its argument — a chain with a hole
 * resolves to nothing, so writing one would convert a broken tag into a permanently
 * broken one. The caller emits through the grammar rather than concatenating: the
 * separators, the escaping and the bracket depth are the wire's rules, and a second
 * hand-rolled emitter here is how a migrated tag comes to be spelled differently from
 * an authored one.
 *
 * @since 1.17.0
 * @param array $steps List of array( slug, arg ).
 * @return array Chain in the grammar's shape, or empty when incomplete.
 */
function bws_nxm_chain_steps( array $steps ): array {
	$chain = array();
	foreach ( $steps as $step ) {
		$arg = trim( (string) ( $step[1] ?? '' ) );
		if ( '' === $arg ) {
			return array();
		}
		$chain[] = array( 'slug' => (string) $step[0], 'arg' => $arg, 'limit' => null, 'extra' => array() );
	}
	return $chain;
}

/**
 * `second_related_post_*` → a chain of TWO relationship steps.
 *
 * The old tag hopped `rel` then `rel_2`, collapsing to the first post at each hop.
 * The chain preserves fan-out, so the migrated tag limits BOTH steps to 1 to keep the
 * single-value output the old tag had — the same rule the base source migration
 * follows, for the same reason, and the reason a limit is written at all rather than
 * left to the spelling: chain wire defaults to unlimited. Bounding only the last step
 * would read one post per referenced post, which is not what either spelling meant.
 *
 * Both relationship keys must be present. Without `rel_2` there is no second hop to
 * state, and inventing a one-hop chain would silently make the tag read the FIRST
 * relationship's target as though that were the author's intent.
 *
 * @since 1.17.0
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string, or the original.
 */
function bws_migrate_second_related_post_chain( string $tag_string ): string {
	return bws_nxm_migrate_chain( $tag_string, 'second_related_post_', static function ( array $options ): array {
		return array(
			array( 'refs', $options['rel'] ?? '' ),
			array( 'refs', $options['rel_2'] ?? $options['rel2'] ?? '' ),
		);
	}, array( 'rel', 'rel_2', 'rel2' ) );
}

/**
 * `post_term_related_post_*` → a `terms` step followed by a `refs` step.
 *
 * The old tag took the FIRST term in `tax` and read a relationship field on it. The
 * chain does not collapse the term hop, so a limit on each step preserves the
 * single-value output for the same reason as the sibling above. `taxonomy` is read as
 * well as `tax`: the option was renamed in 1.4.x and the retired class still accepted
 * both, so stored wire can hold either.
 *
 * @since 1.17.0
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string, or the original.
 */
function bws_migrate_post_term_related_post_chain( string $tag_string ): string {
	return bws_nxm_migrate_chain( $tag_string, 'post_term_related_post_', static function ( array $options ): array {
		return array(
			array( 'terms', $options['tax'] ?? $options['taxonomy'] ?? '' ),
			array( 'refs', $options['rel'] ?? '' ),
		);
	}, array( 'tax', 'taxonomy', 'rel' ) );
}

/**
 * Shared body for the two N×M chain transforms: rename the tag, build the chain, carry
 * the old limit onto its steps, strip the option keys it consumed.
 *
 * IT RENAMES THE TAG ITSELF, and must: MigrationRegistry::transform_tag() returns a
 * `transform_callback`'s result verbatim, so the declarative `new_tag` rename never
 * runs for an entry that has one. The target comes from bws_nxm_chain_target(), the
 * same function the registration uses — one owner, so the entry the converter REPORTS
 * and the tag it WRITES cannot name two different things.
 *
 * The GB link options are remapped here for the same reason (`gb_link_remap` is a
 * declarative key `run_transform()` applies, and it is likewise skipped).
 *
 * @since 1.17.0
 * @param string   $tag_string Raw tag string.
 * @param string   $prefix     The family prefix, for the target lookup.
 * @param callable $steps_fn   fn( array $options ): array — ordered [slug, arg] pairs.
 * @param string[] $consumed   Option keys the chain absorbs.
 * @return string Rewritten tag string, or the original.
 */
function bws_nxm_migrate_chain( string $tag_string, string $prefix, callable $steps_fn, array $consumed ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	if ( ! class_exists( $reg ) ) {
		return $tag_string;
	}
	list( $tag_name, $options ) = $reg::parse_tag_string( $tag_string );

	$new_tag = bws_nxm_chain_target( $tag_name, $prefix );
	if ( '' === $new_tag ) {
		return $tag_string;
	}

	$chain = bws_nxm_chain_steps( (array) call_user_func( $steps_fn, $options ) );
	if ( ! $chain || ! function_exists( 'bws_fold_emit_chain' ) ) {
		return $tag_string;
	}

	// Both families are TWO fanning steps, so the limit has to reach both of them: per-step
	// limits are per-input and multiply, and `1` on the last alone would give one target per
	// parent rather than the one value the old tag rendered. The mapping is shared with the
	// base source migration rather than restated (bws_fold_chain_apply_legacy_limit).
	$bound = bws_fold_chain_apply_legacy_limit( $chain, $options['limit'] ?? null, true );

	// Enclosing level 0 — a base tag's `src:` IS the wrapper.
	$wire = bws_fold_emit_chain( $bound['chain'], 0 );
	if ( '' === $wire ) {
		return $tag_string;
	}

	if ( function_exists( 'bws_map_gb_link_option' ) ) {
		$options = bws_map_gb_link_option( $options );
	}
	$options['src'] = $wire;
	foreach ( $consumed as $key ) {
		unset( $options[ $key ] );
	}
	if ( $bound['consumed'] ) {
		unset( $options['limit'] );
	}

	if ( function_exists( 'bws_serialization_order_sort_map' ) ) {
		$options = bws_serialization_order_sort_map( $options );
	}

	return $reg::format_tag_string( $new_tag, $options );
}

function bws_migrate_related_post_src( string $tag_string ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	[ $tag_name, $options ] = $reg::parse_tag_string( $tag_string );

	$touched = false;

	foreach ( array_keys( $options ) as $key ) {
		// Bare `src` (tag level / slot 1) or a legacy flat slot `N-src`. Folded slot keys
		// are CAPITALS by construction (bws_slot_ordinal), so this cannot catch one.
		if ( ! preg_match( '/^(\d+-)?src$/', (string) $key, $m ) ) {
			continue;
		}
		if ( 'related_post' !== trim( (string) $options[ $key ] ) ) {
			continue;
		}

		$touched = true;
		$prefix  = $m[1] ?? '';
		$ref_key = $prefix . 'ref';
		$rel_key = $prefix . 'rel';
		$fld_key = $prefix . 'key';

		$options[ $key ] = 'ref';

		// `rel` leaves whatever happens — it is dead vocabulary under every src token.
		$rel = isset( $options[ $rel_key ] ) ? trim( (string) $options[ $rel_key ] ) : '';
		unset( $options[ $rel_key ] );

		// Only what THIS src made live may name the hop, and an existing `ref` is not it —
		// under `related_post` the class read `rel`, then `key`, and never `ref`. So `rel`
		// OVERWRITES a stale `ref` rather than losing to it.
		if ( '' !== $rel ) {
			$options[ $ref_key ] = $rel;
			continue;
		}

		$fld = isset( $options[ $fld_key ] ) ? trim( (string) $options[ $fld_key ] ) : '';
		if ( '' !== $fld ) {
			$options[ $ref_key ] = $fld; // COPY — see docblock.
		}

		// Neither present: nothing this src made live. Any `ref` already on the tag stays
		// (the named departure from strict preservation — see docblock).
	}

	// No-op returns the input VERBATIM, not a re-serialization of it: reformatting a tag
	// this entry has nothing to do with would show as a spurious diff on every post the
	// converter touches (the contract apply_option_migration() leans on).
	if ( ! $touched ) {
		return $tag_string;
	}

	// A migrated tag must come out in canonical key order, or the first person to open it
	// sees the editor rewrite the string for no reason they can point at.
	if ( function_exists( 'bws_serialization_order_sort_map' ) ) {
		$options = bws_serialization_order_sort_map( $options );
	}

	return $reg::format_tag_string( $tag_name, $options );
}

/**
 * The BASE tag a modifier tag migrates to — its template key, validated (#84).
 *
 * A modifier tag is `<prefix>_<template key>` by construction (register_modifier), and
 * every template key IS a base tag name, so the target is the suffix. It is still looked
 * UP rather than merely stripped: the registered template list is the only thing that
 * says a given suffix names a tag this plugin renders, and a prefix match on an unrelated
 * tag (`view_something_else` from another plugin) would otherwise be renamed to a tag
 * that does not exist. Derived, never listed — a hand-kept list of nine names is the
 * drift the modifier constructor already removed once.
 *
 * @since 1.17.0
 * @param string $tag_name Stored tag name.
 * @param string $prefix   Modifier prefix, with or without its trailing underscore.
 * @return string Base tag name, or '' when this tag is not that prefix's modifier.
 */
function bws_modifier_base_target( string $tag_name, string $prefix ): string {
	$prefix = rtrim( trim( $prefix ), '_' );
	if ( '' === $prefix || 0 !== strpos( $tag_name, $prefix . '_' ) ) {
		return '';
	}

	$suffix = substr( $tag_name, strlen( $prefix ) + 1 );
	if ( '' === $suffix || ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
		return '';
	}

	foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
		if ( ( $tpl['key'] ?? '' ) === $suffix ) {
			return $suffix;
		}
	}

	return '';
}

/**
 * A modifier tag's options → a BASE tag's options, sourced by an equivalent chain (#84).
 *
 * PURE — options in, options out; null when no chain can be stated. The rewrite is a
 * WHOLE-STRING transform rather than a declarative rename plus an injected `src`, and
 * that is a correctness requirement, not a style choice: on a modifier tag the ROOT came
 * from the TAG, so the flat source token meant "relative to that root". The base-tag
 * reading is different — the chain builder reads `ref` only when the source token IS the
 * relationship token — so renaming the tag and injecting `src:<root>` would leave `ref`
 * unread and SILENTLY ERASE THE HOP. That is the same failure the `rel` → `ref` repairs
 * exist to prevent, arriving through the fix rather than through the bug.
 *
 * Mapping, one row per stored shape:
 *
 *   no `src`            → root alone (`{{view_text key:x}}` → `{{text src:view|key:x}}`)
 *   `src:current`       → root alone. On a MODIFIER tag "current" named ITS entity, not
 *                         the ambient one, so the root is the faithful reading and
 *                         carrying the token through would re-point the tag at the post.
 *   `src:ref` + `ref:f` → root, then a fanning `refs,f` step
 *   `srcTermIn:t`       → root, then a `terms,t` step
 *   both                → root, `refs`, `terms` — wire order is the #44 order (a term
 *                         step needs a post input), which the modifier callback also had.
 *   `src:site`          → the SITE root, with `ref` and `srcTermIn` DROPPED DELIBERATELY.
 *
 * The site row is not tidying. The modifier callback returns on `site` BEFORE reading
 * either sidecar, so neither has ever run; a `refs` step now accepts site input, so a
 * surviving key would compound into a hop that has never once executed.
 *
 * An ORPHAN `src:ref` (no relationship field) keeps its step, spelled bare — the same
 * shape and the same spelling bws_fold_chain_from_options() writes for flat base wire.
 * It compiles away to the root, where the modifier callback resolved entity id 0, so the
 * two do differ on a shape neither editor ever produced; the alternative is inventing a
 * step, and the flat-wire spelling is the one every other reader already agrees on.
 *
 * **THE RELATIONSHIP KEY IS READ IN BOTH SPELLINGS, AND THAT IS NOT OPTIONAL HERE.** The
 * buggy pre-1.6.0 converter wrote `rel` where the live key is `ref`, and the sibling
 * repair (bws_migrate_rel_to_ref) cannot reach a tag this transform renames: the
 * converter runs every TAG rename (step 3) before any OPTION entry (step 4), so by the
 * time a `rel` repair for this family could fire, the tag is already a base tag. Left
 * unread, the key would come out of the cascade as a FLAT `ref` sitting beside chain
 * wire — which no reader consults, since a chain states its own steps. That is the
 * silent-erasure this transform exists to prevent, one spelling over. The rule is the
 * sibling's, unchanged, because two answers to "which spelling won" is how one tag comes
 * to be stored two ways: an existing `ref` WINS (`rel` was never read under any token
 * this transform sees), an absent one TAKES the `rel` as a repair, and a repaired key
 * with no source stated brings `src:ref` with it — a relationship key on a tag that
 * states no source is evidence of a hop whose token the same bug dropped.
 *
 * Under every NON-fanning token both spellings are inert and are consumed with the
 * source axis: `src:current` named the root, so a key beside it never hopped, and
 * carrying one into a step would invent a read rather than preserve one.
 *
 * THE TAG-LEVEL `limit` IS LEFT ALONE. Modifier tags register one, and the base-tag chain
 * entry (bws_migrate_base_src_chain) absorbs it onto the last fanning step in the
 * converter's later pass over the renamed tag. One implementation of that rule, not two.
 *
 * @since 1.17.0
 * @param array  $options Modifier tag options (GB-parsed).
 * @param string $root    Registered source key the modifier rooted at (e.g. 'view').
 * @return array|null Rewritten options, or null when no root was given.
 */
function bws_modifier_base_options( array $options, string $root ) {
	$root = trim( $root );
	if ( '' === $root || ! function_exists( 'bws_fold_emit_chain' ) ) {
		return null;
	}

	$src = trim( (string) ( $options['src'] ?? $options['source'] ?? '' ) );
	$ref = trim( (string) ( $options['ref'] ?? '' ) );
	$rel = trim( (string) ( $options['rel'] ?? '' ) );
	$tax = trim( (string) ( $options['srcTermIn'] ?? '' ) );

	// The dead `rel` spelling, settled by the sibling repair's rule — see docblock. Both
	// keys are consumed below with the rest of the source axis whatever happens here, so
	// neither can survive as a flat key beside chain wire.
	if ( '' !== $rel && 'site' !== $src ) {
		if ( '' === $ref ) {
			$ref = $rel;
			if ( '' === $src ) {
				$src = 'ref';
			}
		}
	}

	// NOT bws_nxm_chain_steps(): that builder returns an EMPTY chain the moment a step
	// lacks its argument, because an N×M family with a missing relationship key has a
	// hole in the middle of its chain. Here an argless step is a legal shape rather than
	// a hole — the root carries no argument at all, and an orphan `refs` is the flat
	// spelling this era already writes — so the two cannot share one builder.
	$step = static function ( string $slug, ?string $arg = null ): array {
		return array( 'slug' => $slug, 'arg' => $arg, 'limit' => null, 'extra' => array() );
	};

	if ( 'site' === $src ) {
		// Both sidecars are inert under this token and are dropped with it — see docblock.
		$chain = array( $step( 'site' ) );
	} else {
		$chain = array( $step( $root ) );
		if ( 'ref' === $src ) {
			$chain[] = $step( 'refs', '' !== $ref ? $ref : null );
		}
		if ( '' !== $tax ) {
			$chain[] = $step( 'terms', $tax );
		}
	}

	$wire = bws_fold_emit_chain( $chain, 0 );
	if ( '' === $wire ) {
		return null;
	}

	$out = $options;
	unset( $out['source'], $out['ref'], $out['rel'], $out['srcTermIn'] );
	$out['src'] = $wire;

	return function_exists( 'bws_serialization_order_sort_map' )
		? bws_serialization_order_sort_map( $out )
		: $out;
}

/**
 * Migration transform_callback body: rewrite one modifier tag into its base tag (#84).
 *
 * Shared by every prefix — the owning plugin binds its own prefix and root through
 * bws_modifier_root_transform(), so one transform serves all of them and a new prefix
 * costs no new rule.
 *
 * IT RENAMES THE TAG ITSELF, and must: MigrationRegistry::transform_tag() returns a
 * `transform_callback`'s result verbatim, so an entry that has one never reaches the
 * declarative `new_tag` rename.
 *
 * CONVERTER-ONLY, AND THAT IS SAFE HERE — do not "fix" the missing mount path. A tag
 * RENAME cannot happen on the editor mount path at all: that path rewrites a tag's
 * OPTIONS, while the tag NAME belongs to the block's parsed tag and is chosen by the
 * picker. The two-path model exists because two writers can store one tag two ways
 * depending on which reached it first (assets/js/slot-fold-migrate.js); with one writer
 * there is no divergence to prevent.
 *
 * Rename chaining is the converter's, not this transform's: TagConverter::resolve_full_chain()
 * re-reads the tag name after each rewrite and follows it under a cycle guard, so an older
 * prefix whose entry targets this one reaches the base tag in a single run.
 *
 * @since 1.17.0
 * @param string $tag_string Raw tag string.
 * @param string $prefix     Modifier prefix (e.g. 'view').
 * @param string $root       Registered source key the modifier rooted at (e.g. 'view').
 * @return string Rewritten tag string, or the original.
 */
function bws_migrate_modifier_root_chain( string $tag_string, string $prefix, string $root ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	if ( ! class_exists( $reg ) ) {
		return $tag_string;
	}

	list( $tag_name, $options ) = $reg::parse_tag_string( $tag_string );

	$new_tag = bws_modifier_base_target( $tag_name, $prefix );
	if ( '' === $new_tag ) {
		return $tag_string;
	}

	$migrated = bws_modifier_base_options( $options, $root );
	if ( null === $migrated ) {
		return $tag_string;
	}

	return $reg::format_tag_string( $new_tag, $migrated );
}

/**
 * Bind a prefix + root to the shared modifier → base transform (#84).
 *
 * A MigrationRegistry entry's `transform_callback` receives only the tag string, so the
 * two facts that vary per family — which prefix, which root — are bound here. Returned as
 * a closure rather than registered per prefix as a named function, so an external plugin
 * owning a retired prefix registers its own entries without this repo naming its family.
 *
 * @since 1.17.0
 * @param string $prefix Modifier prefix (e.g. 'view').
 * @param string $root   Registered source key to root the migrated tag at.
 * @return callable fn( string $tag_string ): string
 */
function bws_modifier_root_transform( string $prefix, string $root ): callable {
	return static function ( string $tag_string ) use ( $prefix, $root ): string {
		return bws_migrate_modifier_root_chain( $tag_string, $prefix, $root );
	};
}

/**
 * Liveness marker for a generated modifier→base entry (#86).
 *
 * NEVER DISPATCHED, and it is not dead code. MigrationRegistry::is_entry_live() reads
 * callback-presence as the interim proxy for "the owning plugin still registers these tag
 * names" (that docblock says so, and names FW-38's explicit `lifecycle` field as the
 * replacement) — which is exactly the state a migrated-but-not-retired modifier family is
 * in: `register_modifier()` goes on minting its nine GB tags, so the entries belong in the
 * settings page's **Deprecated** box, not in **Removed**. An entry generated without one
 * would file a live family under "these tag names no longer register with GenerateBlocks",
 * which is false while the family renders.
 *
 * Retiring the family is the owner's decision on the owner's schedule: pass
 * `prefix_removed => true` then, and is_entry_live() returns false whatever this is.
 *
 * @since 1.17.0
 * @return string Always ''.
 */
function bws_modifier_migration_live_marker(): string {
	return '';
}

/**
 * Generate one migration entry per REGISTERED MODIFIER TEMPLATE, for a retired prefix (#86).
 *
 * The integration seam for a plugin that owns a modifier family and wants its stored tags
 * rewritten into base tags rooted at its registered source. One call, no list of tag names:
 *
 *     add_action( 'init', function () {
 *         bws_register_modifier_root_migrations( 'view', 'view', array( 'since' => '3.4.0' ) );
 *     }, 21 );
 *
 * **ENUMERATING TEMPLATES IS THE POINT.** A hand-kept list of tag names has already drifted
 * once in the wild: the alias table in the external plugin covers seven of the nine
 * templates, because two of them register from elsewhere. The registry is the only thing
 * that knows what a family's tags ARE — it is what `register_modifier()` itself iterates to
 * mint them — so generating from it makes the two lists the same list by construction.
 *
 * **THE PREFIX IS SUPPLIED, NEVER DERIVED.** This matches the standing posture for
 * prefix-owning migrations (bws_migrate_rel_to_ref's `term` hardcode says the same thing):
 * a derived prefix list can only ever name the in-repo family, while implying it covered
 * externals. The owner names its own prefix, so an external family is a first-class caller
 * rather than something this repo has to know about.
 *
 * **CALL IT AFTER TEMPLATES ARE REGISTERED**, i.e. later than the plugin's own init:20
 * pass — init:21 is the natural home, beside the `register_modifier()` call whose tags
 * these entries answer for. That is comfortably before the converter can run (an admin
 * request). Called too early the template list is empty and there is nothing to generate,
 * so this says so out loud rather than registering nothing quietly.
 *
 * **IT NEVER OVERWRITES AN ENTRY YOU ALREADY REGISTERED.** A tag name that already HAS an
 * entry is skipped whole: an owner that hand-wrote one template's entry (a shape with its
 * own quirk) keeps it, and gets the generator for the other eight. The test is entry
 * PRESENCE and not has_migration_path() — see the guard.
 *
 * The entries are `type:'tag'` with the SHARED transform bound to this prefix + root
 * (bws_modifier_root_transform), so every family maps by one rule and a new prefix costs no
 * new rule. `new_tag` is the base tag the transform renames to; a transform_callback's
 * result is returned verbatim by MigrationRegistry::transform_tag(), so the declarative
 * rename never runs and the two cannot name different tags — the harness pins that as
 * report/run agreement.
 *
 * NOT set: `source_inject`. It would sharpen the settings page's target display from
 * `{{text}}` to `{{text src:view}}` and it is display-only here (the transform_callback
 * overrides the declarative pipeline) — but a rename plus an injected root token is exactly
 * the shape #84 exists to refuse, and leaving it on the entry as decoration invites the next
 * reader to drop the callback and "simplify" back into silent hop erasure.
 *
 * REACH: the converter scans the POSTS table only (non-revision, non-trash), which does
 * include reusable blocks, template parts and theme-element post types. Tags stored in the
 * OPTIONS table — block widgets — are out of its reach and keep rendering; the old tags stay
 * registered, so "run the converter" is never a deadline.
 *
 * @since 1.17.0
 * @param string $prefix Modifier prefix, with or without its trailing underscore (e.g. 'view').
 * @param string $root   Registered source key to root migrated tags at (e.g. 'view').
 * @param array  $args {
 *     Optional. Per-family entry fields.
 *
 *     @type string   $since          Version the prefix was deprecated in. Shown in admin.
 *     @type bool     $prefix_removed True once YOU stop registering the family's tags —
 *                                    moves the entries to the Removed box. Default false.
 * }
 * @return string[] The modifier tag names entries were generated for, in template order.
 */
function bws_register_modifier_root_migrations( string $prefix, string $root, array $args = array() ): array {
	$prefix = rtrim( trim( $prefix ), '_' );
	$root   = trim( $root );
	$reg    = 'BWS\DynamicTags\MigrationRegistry';

	if ( '' === $prefix || '' === $root || ! class_exists( $reg ) || ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
		return array();
	}

	$templates = \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates();
	if ( empty( $templates ) && function_exists( '_doing_it_wrong' ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'No modifier templates are registered yet. Call this after tag registration (init priority later than 20).', 'generateblocks' ),
			'1.17.0'
		);
	}

	// PRESENCE, not has_migration_path(). Both finders in the registry stop at the FIRST
	// entry matching a tag name, and this repo keeps registry-only entries by standing
	// policy — a `register()` call is never deleted for lacking migration data. Such an
	// entry carries no `new_tag`, so has_migration_path() answers FALSE for a name that is
	// already spoken for; generating a second entry behind it would be silently DEAD wire,
	// with the tag reporting no path and never migrating and nothing erroring.
	$taken = array();
	foreach ( $reg::get_by_type( 'tag' ) as $entry ) {
		$name = (string) ( $entry['match_tag'] ?? '' );
		if ( '' !== $name ) {
			$taken[ $name ] = true;
		}
	}

	$generated = array();

	foreach ( $templates as $tpl ) {
		$key = (string) ( $tpl['key'] ?? '' );
		if ( '' === $key ) {
			continue;
		}

		$old_tag = $prefix . '_' . $key;
		if ( isset( $taken[ $old_tag ] ) ) {
			continue;
		}

		$reg::register( array(
			'type'               => 'tag',
			'match_tag'          => $old_tag,
			'new_tag'            => $key,
			'transform_callback' => bws_modifier_root_transform( $prefix, $root ),
			'since'              => (string) ( $args['since'] ?? '' ),
			'callback'           => 'bws_modifier_migration_live_marker',
			'prefix_removed'     => ! empty( $args['prefix_removed'] ),
		) );

		$generated[] = $old_tag;
	}

	return $generated;
}

/**
 * Migration transform_callback: `rel` → `ref`, with the matching `src:ref` — per slot on a
 * try_ tag, bare keys on a base or term_ tag (#56, extended to all three families by #57).
 *
 * The declarative pipeline cannot express this, and the reason is worth stating because
 * the obvious spelling looks correct and destroys data:
 *
 *   1. `option_renames` assigns UNCONDITIONALLY, so on a tag carrying both spellings the
 *      inert `rel` overwrites a LIVE `ref` (#57 — the base/term_ families shipped exactly
 *      that from 1.6.0).
 *   2. `option_renames` matches an EXACT key, so `2-rel` needs its own pair — fine.
 *   3. `source_inject` writes the TAG-level `src`, which on a try_ tag is slot 1's. A
 *      `3-rel` must set `3-src`, not `src`.
 *
 * The first draft therefore renamed the keys and injected NOTHING, on the reasoning that
 * a `3-ref` with no `3-src` is inert rather than wrong. **That is false, and the harness
 * caught it:** the fold entry runs in the SAME cascade, and a legacy `ref` with no
 * `src:ref` beside it maps to no step — so the fold folds the slot without it and strips
 * the legacy keys. The relationship is not left inert, it is ERASED, in the one pass that
 * was supposed to rescue it. A slot that named a relationship must come out of this
 * transform already spelling `N-src:ref|N-ref:<field>`, which the fold then folds intact.
 *
 * **WHEN BOTH SPELLINGS ARE PRESENT, THE SLOT'S `src` DECIDES — not a ranking of the keys.**
 * Each spelling is live under exactly one source token and inert under the other, so the
 * winner changes with the token (see bws_migrate_related_post_src() for the same rule
 * stated from the other side):
 *
 *   `N-src:ref`           the compiler reads `ref`; `rel` is inert  → `ref` WINS
 *   `N-src:related_post`  the retired class read `rel`; `ref` inert → DEFERRED, see below
 *   `N-src` absent        NEITHER was read — the slot hopped nowhere. No faithful answer
 *                         exists, so this is a repair: `ref` if set, else `rel`, and
 *                         `src:ref` is injected on the premise a slot naming a
 *                         relationship descends from a relationship hop.
 *
 * A `related_post` slot is DEFERRED WHOLE — skipped byte-identical, `rel` left in place —
 * because bws_migrate_related_post_src(), the sole owner of that token, ranks `rel` above
 * `key` and needs both intact to do it. Settling the slot here (write `ref = rel`, delete
 * `rel`) destroyed exactly that evidence: the later entry, finding no `rel`, fell to its
 * key-COPY branch and overwrote the settled `ref` with the field key (#73). The cascade
 * makes the hand-off safe: this transform no-ops on the slot, the later entry still runs.
 * Same shape as the mount path's decline (BWS_FOLD_RETIRED_SRC_TOKENS skips such slots
 * whole), and the deferred slot always has a downstream owner because the related_post
 * entry registers for every family this one does.
 *
 * An explicit `src` is never overwritten — only an absent one is filled.
 *
 * @since 1.17.0 (as bws_migrate_slot_rel_to_ref; renamed when the base/term_ families
 *               moved onto it, replacing their declarative `$rel_fix` pair)
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string (unchanged when no slot carries a `rel`).
 */
function bws_migrate_rel_to_ref( string $tag_string ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	[ $tag_name, $options ] = $reg::parse_tag_string( $tag_string );

	$touched = false;

	// Slot 1 is bare; 2..5 mirror what generate_base_try_tags() registers.
	foreach ( array( '', '2-', '3-', '4-', '5-' ) as $prefix ) {
		$rel_key = $prefix . 'rel';
		if ( ! isset( $options[ $rel_key ] ) ) {
			continue;
		}

		// A present-but-EMPTY `rel:` names nothing to move, but the dead key is still
		// consumed — leaving it would have the converter report this migration forever
		// while changing nothing (the report/run agreement §R3 pins).
		$rel = trim( (string) $options[ $rel_key ] );
		if ( '' === $rel ) {
			$touched = true;
			unset( $options[ $rel_key ] );
			continue;
		}

		$ref_key = $prefix . 'ref';
		$src_key = $prefix . 'src';
		$src     = trim( (string) ( $options[ $src_key ] ?? '' ) );
		$has_ref = '' !== trim( (string) ( $options[ $ref_key ] ?? '' ) );

		// DEFER-WHOLE (#73): under `related_post` the `rel` is the live spelling, but
		// settling it here destroys the rel-vs-key evidence the token's owner needs —
		// see docblock. Skip the slot byte-identical; bws_migrate_related_post_src()
		// consumes it later in the same cascade.
		if ( 'related_post' === $src ) {
			continue;
		}

		$touched = true;
		unset( $options[ $rel_key ] );

		// Under every remaining token the `rel` was never read, so an existing `ref`
		// wins and the `rel` is dropped; only an absent `ref` takes it (repair).
		if ( ! $has_ref ) {
			$options[ $ref_key ] = $rel;
		}

		if ( '' === $src ) {
			$options[ $src_key ] = 'ref';
		}
	}

	if ( ! $touched ) {
		return $tag_string;
	}

	if ( function_exists( 'bws_serialization_order_sort_map' ) ) {
		$options = bws_serialization_order_sort_map( $options );
	}

	return $reg::format_tag_string( $tag_name, $options );
}

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
	// source:ref is injected.
	//
	// A transform_callback, not the declarative option_renames + source_inject pair it was
	// from 1.6.0 to 1.17.0: option_renames assigns unconditionally, so on a tag carrying
	// BOTH spellings the inert `rel` overwrote a live `ref` (#57). The callback applies the
	// src-decides rule and defers `src:related_post` whole — see bws_migrate_rel_to_ref().
	$rel_fix = array(
		'transform_callback' => 'bws_migrate_rel_to_ref',
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

	// ERA evidence — a NARROWER list than the trigger above, and the two must stay
	// separate. The datetime transforms inject `showMidnight`/`showCurrentYear` on the
	// ABSENCE of the old inverted booleans (see MigrationRegistry::apply_datetime_transforms),
	// so they need to know the tag is genuinely pre-1.6 wire rather than a modern tag that
	// never had those keys. Every key here is spelled only by pre-1.6 datetime wire —
	// `separator` included, since the modern spelling is `rangeSep`.
	//
	// `fallback_text` is the one trigger key that is NOT era evidence: it is renamed on
	// every base tag, so `{{datetime_single key:x|fallback_text:—}}` is a modern tag
	// carrying one stale universal key. It still triggers the entry (the rename must run)
	// but must not license the injection, which would flip that tag's year and midnight
	// rendering. See #90.
	//
	// DERIVED from the two trigger lists, never retyped: the exclusion is the whole
	// decision here, and a third literal would let a new trigger key silently miss era
	// evidence. Anything added to a trigger list is era evidence unless named below.
	$datetime_era_keys = array_values(
		array_diff(
			array_unique( array_merge( $datetime_single_old_keys, $datetime_range_old_keys ) ),
			array( 'fallback_text' )
		)
	);

	$reg::register( array(
		'type'                 => 'option',
		'match_tag'            => 'datetime_single',
		'match_any_options'    => $datetime_single_old_keys,
		'datetime_era_options' => $datetime_era_keys,
		'new_tag'              => 'datetime_single',
		'option_renames'       => $cdts_renames,
		'datetime_transforms'  => true,
		'label'                => __( '{{datetime_single}}: legacy field/format keys → v1.6 names', 'generateblocks' ),
	) );

	$reg::register( array(
		'type'                 => 'option',
		'match_tag'            => 'datetime_range',
		'match_any_options'    => $datetime_range_old_keys,
		'datetime_era_options' => $datetime_era_keys,
		'new_tag'              => 'datetime_range',
		'option_renames'       => $cdtr_renames,
		'datetime_transforms'  => true,
		'label'                => __( '{{datetime_range}}: legacy field/format keys → v1.6 names', 'generateblocks' ),
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

	// image / term_image / try_image: as+size FOLD (FW-52, v1.16.0).
	// GB's native image-size control is retired; size folds INTO the `as` value as a
	// comma second slot (`as:url,<size>`). A legacy separate `size:` (or per-slot
	// `N-size:`) becomes an orphan token GB keeps verbatim — silent string-vs-modal
	// divergence (the stranded-reserved-token trap). This value-conditional fold can't
	// be expressed with combine_options/option_renames, so it uses a transform_callback.
	// Per slot (bare + N- prefixed):
	//   - `as:url` (or `as` absent → url default) + `size` present → `as:url,<size>`
	//   - `as:` nullary (id/alt/title/caption) + `size` present → DROP size (was dead
	//     at render — nullary returns ignore size), emit bare `as:<mode>`
	//   - no `size` → unchanged (already folded, or size never set)
	foreach ( array( 'image', 'term_image', 'try_image' ) as $tag ) {
		$reg::register( array(
			'type'               => 'option',
			'match_tag'          => $tag,
			// `size` triggers the fold; `as` alone also matches so a bare url without a
			// size still normalizes to url,full on conversion (callback no-ops otherwise).
			'match_any_options'  => array( 'size', 'as', '2-size', '3-size', '4-size', '5-size', '2-as', '3-as', '4-as', '5-as' ),
			'new_tag'            => $tag,
			'transform_callback' => 'bws_migrate_image_as_size_fold',
			'label'              => sprintf(
				/* translators: %s: tag name */
				__( '{{%s}}: fold size into as (as:url,size)', 'generateblocks' ),
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

	// ── #56: `rel` → `ref` on the MODIFIER and try_ families ──
	//
	// The $rel_fix foreach near the top of this function has covered the seven base tags
	// since 1.6.0; these two entries close the same gap on the families it skipped. Same
	// premise as that one: a `rel` present AT ALL means the tag descends from a
	// `*_related_post_*` ancestor via a converter run that renamed the tag but left the
	// pre-1.6.0 option spelling. `rel` is a registered option on NO current tag, so there
	// is no live meaning to collide with.
	//
	// POPULATION UNKNOWN BY CONSTRUCTION. Nobody can enumerate what a historic buggy
	// converter wrote; this is insurance against a shape, not a fix for a counted set. Do
	// not read its existence as evidence such wire was found.
	//
	// BEFORE the fold entry, and here the ordering is not merely conventional: `rel` is
	// NOT in BWS_FOLD_FLAT_AXES, so the fold neither folds nor strips it. A `2-rel` that
	// survives into a folded tag is orphaned permanently — no later pass can see it.

	// Modifier (term_) tags. Derived from the registered templates so a template added
	// later is covered without a second list to keep.
	//
	// `term` is HARDCODED rather than derived from the registered modifier prefixes, and
	// that is a real limit rather than an oversight: this function runs at init:20 and an
	// external modifier registers later (bws-portal-system's `view_` is init:21), so a
	// derived prefix list would hold exactly `term` anyway while promising more. An
	// external plugin that needs the same repair registers its own entry — the registry
	// is public API.
	if ( class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
		foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
			if ( empty( $tpl['key'] ) ) {
				continue;
			}
			$modifier_tag = 'term_' . $tpl['key'];
			$reg::register( array_merge( $rel_fix, array(
				'type'          => 'option',
				'match_tag'     => $modifier_tag,
				'match_options' => array( 'rel' ),
				'new_tag'       => $modifier_tag,
				'label'         => sprintf(
					/* translators: %s: tag name */
					__( '{{%s}}: rel → source:ref|ref (broken converter output)', 'generateblocks' ),
					$modifier_tag
				),
			) ) );
		}
	}

	// try_ tags. Same callback; the per-slot keys are why it loops prefixes — see
	// bws_migrate_rel_to_ref().
	$try_rel_keys = array( 'rel' );
	for ( $slot = 2; $slot <= 5; $slot++ ) {
		$try_rel_keys[] = $slot . '-rel';
	}

	if ( function_exists( 'bws_fold_migration_multislot_tags' ) ) {
		foreach ( bws_fold_migration_multislot_tags() as $tag ) {
			if ( 0 !== strpos( $tag, 'try_' ) ) {
				continue; // {{join}} postdates the rename by nine releases — no exposure.
			}
			$reg::register( array(
				'type'               => 'option',
				'match_tag'          => $tag,
				'match_any_options'  => $try_rel_keys,
				'new_tag'            => $tag,
				'transform_callback' => 'bws_migrate_rel_to_ref',
				'label'              => sprintf(
					/* translators: %s: tag name */
					__( '{{%s}}: slot rel → ref (broken converter output)', 'generateblocks' ),
					$tag
				),
			) );
		}
	}

	// ── #56: the `related_post` SOURCE TOKEN → `src:ref` + `ref` ──
	//
	// BEFORE the fold entry (which consumes flat `N-src` and would hide the token), and
	// after the try_ slot-key renames above (which produce `N-src` from `src_N`).
	//
	// VALUE-GATED, not key-gated. Every other entry here matches on a KEY being present,
	// which is fine when the key is itself legacy (`rel`, `size`, `fallback_text`). Here
	// the legacy thing is a VALUE sitting in the live `src` key, so a key gate would match
	// every tag that names a source at all — the converter would list this migration on
	// virtually every post and then change nothing. match_option_values (1.17.0) keeps
	// detection and application saying the same thing.
	//
	// Only `related_post` migrates. `related` — the sibling value the old try_ slot
	// value_renames also mapped — is deliberately NOT here: no source has ever registered
	// under that key, so it already falls through to the ambient entity, and rewriting it
	// to a hop would CHANGE rendered output rather than preserve it.
	$related_post_src_keys = array( 'src' );
	for ( $slot = 2; $slot <= 10; $slot++ ) {
		$related_post_src_keys[] = $slot . '-src';
	}
	$related_post_src_values = array_fill_keys( $related_post_src_keys, array( 'related_post' ) );

	// try_ only among the containers: `{{join}}` (1.15.0) and `{{table}}` (1.17.0) postdate
	// the token by nine releases and more, exactly the argument that excludes them from the
	// `rel` repair above. Registering an entry that can never match is a dead entry the
	// converter still walks and a reader still has to rule out.
	$related_post_src_tags = array(
		'text', 'content', 'title', 'permalink', 'image',
		'datetime_single', 'datetime_range', 'email', 'phone',
	);
	if ( function_exists( 'bws_fold_migration_multislot_tags' ) ) {
		foreach ( bws_fold_migration_multislot_tags() as $multislot_tag ) {
			if ( 0 === strpos( $multislot_tag, 'try_' ) ) {
				$related_post_src_tags[] = $multislot_tag;
			}
		}
	}

	// term_ too (#73): the `rel` repair above DEFERS a `src:related_post` slot whole, so
	// every family it registers for needs this entry downstream — without one the deferred
	// `rel` is orphaned forever and the converter reports a migration that changes nothing.
	// Derived from the templates, same as the `rel` repair; same hardcoded-`term` limit.
	if ( class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
		foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
			if ( ! empty( $tpl['key'] ) ) {
				$related_post_src_tags[] = 'term_' . $tpl['key'];
			}
		}
	}

	foreach ( array_unique( $related_post_src_tags ) as $tag ) {
		$reg::register( array(
			'type'                => 'option',
			'match_tag'           => $tag,
			'match_option_values' => $related_post_src_values,
			'new_tag'             => $tag,
			'transform_callback'  => 'bws_migrate_related_post_src',
			'label'               => sprintf(
				/* translators: %s: tag name */
				__( '{{%s}}: src:related_post → src:ref (relationship key moves to ref)', 'generateblocks' ),
				$tag
			),
		) );
	}

	// ── FW-56: a BASE tag's flat source triple → depth-0 CHAIN wire (1.17.0) ──
	//
	// AFTER every entry that PRODUCES a flat `src:ref` — the `rel` → `ref` repairs and
	// the `related_post` token rewrite — because this consumes one. Registering it
	// earlier would leave those entries to re-introduce a flat key beside the chain
	// value, which is the same ordering constraint the slot fold has for the same
	// reason.
	//
	// GATED, and the gate was met before this registered: migrating flat→chain puts
	// EVERY stored base tag through the chain arms at once, on pages nobody opened, so
	// a broken arm goes from affecting hand-converted tags to affecting the whole
	// corpus on upgrade. FW-63's arm dispatch and its matrix coverage had to be
	// complete first (fold-test-matrix.md §F9/§F9a, measured 2026-08-05).
	//
	// Matches on the KEYS rather than on values: `src:ref` and `srcTermIn` are the two
	// spellings that fan, plus `limit` for the absorb branch below.
	//
	// `limit` IS ON THIS LIST AND MUST STAY (#66). A tag-level limit is legacy by POSITION,
	// not by spelling, so the transform also absorbs one sitting on wire that is ALREADY a
	// chain — and that shape carries neither `ref` nor `srcTermIn`, so without `limit` here
	// it never reaches the transform at all. That shipped: #62 added the branch and left the
	// match list alone, which made the branch dead code on the CONVERTER path while the mount
	// path (which has no entry chain) ran it — one tag stored two ways depending on which
	// path found it first, the divergence both halves exist to prevent. The slot half already
	// carries the same rule for the same reason (bws_fold_migration_match_keys() adds `limit`
	// on a non-combining container).
	//
	// COST, accepted: the entry is no longer reported on EXACTLY the tags it rewrites. A
	// non-numeric `limit`, or a chain that already states its own step limits, matches here
	// and is then declined by the transform. Harmless to the RUN since 1.17.0 — a no-op entry
	// no longer halts the cascade — so the cost is confined to what the converter ADVERTISES,
	// over a set that only shrinks. The alternative was a value-gated match (the `related_post`
	// entry's posture), but `match_option_values` matches literal values and cannot express
	// "numeric, on a chain that fans without stated limits"; that needs a new match_callback
	// capability on the registry, for one entry.
	if ( function_exists( 'bws_fold_migration_base_tags' ) ) {
		foreach ( bws_fold_migration_base_tags() as $base_tag ) {
			$reg::register( array(
				'type'               => 'option',
				'match_tag'          => $base_tag,
				'match_any_options'  => array( 'ref', 'srcTermIn', 'limit' ),
				'new_tag'            => $base_tag,
				'transform_callback' => 'bws_migrate_base_src_chain',
				'label'              => sprintf(
					/* translators: %s: tag name */
					__( '{{%s}}: flat source (src/ref/srcTermIn) → source chain', 'generateblocks' ),
					$base_tag
				),
			) );
		}
	}

	// ── {{join}} format tokens: escape a `%` the slot LETTERS made significant ──
	//
	// BEFORE the fold entry, and that order is load-bearing. Not a token RE-SPELL: `%1`
	// resolves identically to `%A` and always will, so there is nothing to migrate for
	// authors. What has to move is the LITERAL — a `%` before A–J used to pass through
	// untouched, so `Up 10%APR, paid %1` was legal stored wire whose meaning changes the
	// moment letters tokenize. Literal-or-token is undecidable from the format string, so
	// the transform gates on WIRE ERA: no folded slot key means pre-letters wire. The
	// fold entry below ADDS folded keys, so registering this after it would make every
	// tag look post-letters and the entry would never fire.
	if ( function_exists( 'bws_migrate_join_format_escape' ) ) {
		$reg::register( array(
			'type'               => 'option',
			'match_tag'          => 'join',
			'match_any_options'  => array( 'format' ),
			'new_tag'            => 'join',
			'transform_callback' => 'bws_migrate_join_format_escape',
			'label'              => __( '{{join}}: escape a literal % before a slot letter in the format string', 'generateblocks' ),
		) );
	}

	// ── FW-56/57: legacy flat slot keys → folded `{N}:` slot values (1.17.0) ──
	//
	// LAST, deliberately. Every entry above rewrites keys the fold then consumes
	// (`src_2` → `2-src` → `2:src(...)`), and apply_option_migration applies matching
	// entries in registration order — so registering the fold before them would fold a
	// slot, then leave the earlier entry to re-introduce a flat key beside the folded
	// value. This is also the entry that forced the no-op-halts-cascade fix in
	// apply_option_migration: it matches on `src`/`key`, so on an image tag the as+size
	// entry (which no-ops once folded) used to end the cascade before the fold ran.
	//
	// One registration per multislot tag, both list and container parameters DERIVED
	// (bws_fold_migration_container) — the split is by DEPTH, and the base-tag depth-0
	// half registers above, on its own list. Slot grammar + rules:
	// includes/helpers/slot-fold-migrate.php. The match surface is NOT the mapper's
	// surface (bws_fold_migration_match_keys says why): a selecting container's
	// tag-level `limit` is something this entry retires, so its presence means work,
	// but handing it to the mapper would fold it into slot 1.
	if ( function_exists( 'bws_fold_migration_multislot_tags' ) ) {
		foreach ( bws_fold_migration_multislot_tags() as $tag ) {
			$cfg = bws_fold_migration_container( $tag );
			if ( null === $cfg ) {
				continue;
			}
			$reg::register( array(
				'type'               => 'option',
				'match_tag'          => $tag,
				'match_any_options'  => bws_fold_migration_match_keys( $cfg ),
				'new_tag'            => $tag,
				'transform_callback' => 'bws_migrate_src_chain_slots',
				'label'              => sprintf(
					/* translators: %s: tag name */
					__( '{{%s}}: flat slot options → folded slot values', 'generateblocks' ),
					$tag
				),
			) );
		}
	}
}
