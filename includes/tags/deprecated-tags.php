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

use BWS\DynamicTags\Admin\SettingsPage;

/**
 * Register deprecated dynamic tags (old names that delegate to new ones).
 *
 * @since 1.0.0
 */
function bws_register_deprecated_tags() {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	// current_post_featured_image → post_featured_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'current_post_featured_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post Featured Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'current_post_featured_image',
				'type'        => 'deprecated',
				'supports'    => array( 'image-size' ),
				'description' => __( 'Deprecated — use "Post Featured Image" instead.', 'generateblocks' ),
				'options'     => bws_get_image_return_type_options(),
				'return'      => 'bws_deprecated_current_post_featured_image_callback',
			)
		);
	}

	// current_post_meta_image → post_custom_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'current_post_meta_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post Custom Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'current_post_meta_image',
				'type'        => 'deprecated',
				'supports'    => array( 'image-size' ),
				'description' => __( 'Deprecated — use "Post Custom Image" instead.', 'generateblocks' ),
				'options'     => array_merge(
					bws_get_meta_image_options(),
					bws_get_image_return_type_options()
				),
				'return'      => 'bws_deprecated_current_post_meta_image_callback',
			)
		);
	}

	// related_post_meta_image → related_post_custom_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'related_post_meta_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Related Post Custom Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'related_post_meta_image',
				'type'        => 'deprecated',
				'supports'    => array( 'meta', 'image-size' ),
				'description' => __( 'Deprecated — use "Related Post Custom Image" instead.', 'generateblocks' ),
				'options'     => array_merge(
					bws_get_meta_image_options(),
					bws_get_image_return_type_options()
				),
				'return'      => 'bws_deprecated_related_post_meta_image_callback',
			)
		);
	}

	// related_post_url → related_post_permalink.
	if ( SettingsPage::is_deprecated_tag_enabled( 'related_post_url' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Related Post Permalink (Deprecated)', 'generateblocks' ),
				'tag'         => 'related_post_url',
				'type'        => 'deprecated',
				'supports'    => array( 'meta' ),
				'description' => __( 'Deprecated — use "Related Post Permalink" instead.', 'generateblocks' ),
				'return'      => 'bws_deprecated_related_post_url_callback',
			)
		);
	}

	// post_acf_date_time_single → post_acf_datetime_single.
	if ( SettingsPage::is_deprecated_tag_enabled( 'post_acf_date_time_single' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post ACF Date/Time (Deprecated)', 'generateblocks' ),
				'tag'         => 'post_acf_date_time_single',
				'type'        => 'deprecated',
				'supports'    => array( 'source' ),
				'description' => __( 'Deprecated — use "Post ACF Date/Time" instead.', 'generateblocks' ),
				'options'     => bws_get_datetime_single_options(),
				'return'      => 'bws_deprecated_post_acf_date_time_single_callback',
			)
		);
	}

	// post_acf_date_time_range → post_acf_datetime_range.
	if ( SettingsPage::is_deprecated_tag_enabled( 'post_acf_date_time_range' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Post ACF Date/Time Range (Deprecated)', 'generateblocks' ),
				'tag'         => 'post_acf_date_time_range',
				'type'        => 'deprecated',
				'supports'    => array( 'source' ),
				'description' => __( 'Deprecated — use "Post ACF Date/Time Range" instead.', 'generateblocks' ),
				'options'     => bws_get_datetime_range_options(),
				'return'      => 'bws_deprecated_post_acf_date_time_range_callback',
			)
		);
	}

	// term_name → term_title.
	if ( SettingsPage::is_deprecated_tag_enabled( 'term_name' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Term Name (Deprecated)', 'generateblocks' ),
				'tag'         => 'term_name',
				'type'        => 'deprecated',
				'supports'    => array( 'source' ),
				'description' => __( 'Deprecated — use "Term Title" instead.', 'generateblocks' ),
				'return'      => 'bws_deprecated_term_name_callback',
			)
		);
	}

	// term_field_image → term_custom_image.
	if ( SettingsPage::is_deprecated_tag_enabled( 'term_field_image' ) ) {
		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'       => __( 'Term Field Image (Deprecated)', 'generateblocks' ),
				'tag'         => 'term_field_image',
				'type'        => 'deprecated',
				'supports'    => array( 'source', 'image-size' ),
				'description' => __( 'Deprecated — use "Term Custom Image" instead.', 'generateblocks' ),
				'options'     => array_merge(
					bws_get_term_image_field_options(),
					bws_get_image_return_type_options()
				),
				'return'      => 'bws_deprecated_term_field_image_callback',
			)
		);
	}

	// External deprecated tag wrappers registered via DeprecatedTagRegistry.
	// Snapshot GB's registry to skip any tag name already registered.
	$existing_gb_tags = array_keys( \GenerateBlocks_Register_Dynamic_Tag::get_tags() ?? array() );
	foreach ( \BWS\DynamicTags\DeprecatedTagRegistry::get_all() as $entry ) {
		$old_tag = $entry['old_tag'] ?? '';
		if ( ! $old_tag || in_array( $old_tag, $existing_gb_tags, true ) ) {
			continue;
		}
		if ( ! SettingsPage::is_deprecated_tag_enabled( $old_tag ) ) {
			continue;
		}
		$gb_args = array(
			'title'       => $entry['title'] ?? $old_tag,
			'tag'         => $old_tag,
			'type'        => $entry['gb_type'] ?? 'deprecated',
			'supports'    => $entry['supports'] ?? array(),
			'description' => $entry['description']
							?? sprintf(
								/* translators: %s: replacement tag name */
								__( 'Deprecated — use "%s" instead.', 'generateblocks' ),
								$entry['new_tag'] ?? ''
							),
			'return'      => $entry['callback'],
		);
		if ( ! empty( $entry['options'] ) ) {
			$gb_args['options'] = $entry['options'];
		}
		new GenerateBlocks_Register_Dynamic_Tag( $gb_args );
	}
}
// Registration is called directly from bws_dynamic_tags_register_all() in the main plugin file.

// ===============================================
// DEPRECATED CALLBACK FUNCTIONS
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

/**
 * current_post_featured_image → post_featured_image.
 */
function bws_deprecated_current_post_featured_image_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'current_post_featured_image', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'current_post_featured_image' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'current_post_featured_image', 'post_featured_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_featured_image_core( $post_id, $options, $instance );
}

/**
 * current_post_meta_image → post_custom_image.
 */
function bws_deprecated_current_post_meta_image_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'current_post_meta_image', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'current_post_meta_image' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'current_post_meta_image', 'post_custom_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_custom_image_core( $post_id, $options, $instance );
}

/**
 * related_post_meta_image → related_post_custom_image.
 */
function bws_deprecated_related_post_meta_image_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'related_post_meta_image', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'related_post_meta_image' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'related_post_meta_image', 'related_post_custom_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$base_id = $source ? $source->resolve_id( $options, $instance ) : false;
	$rel_key = $options['rel'] ?? $options['key'] ?? '';
	$post_id = false;
	if ( $base_id && $rel_key ) {
		$related = bws_get_related_posts_data( $base_id, $rel_key );
		$post_id = ! empty( $related ) ? bws_extract_post_id( $related[0] ) : false;
	}
	// In this deprecated tag, 'key' was the relationship field; image field was 'meta_key'.
	// Unset 'key' so bws_custom_image_core falls through to 'meta_key' for the image field.
	$image_options = $options;
	unset( $image_options['key'] );
	return bws_custom_image_core( $post_id, $image_options, $instance );
}

/**
 * related_post_url → related_post_permalink.
 */
function bws_deprecated_related_post_url_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'related_post_url', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'related_post_url' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'related_post_url', 'related_post_permalink' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$base_id = $source ? $source->resolve_id( $options, $instance ) : false;
	$rel_key = $options['rel'] ?? $options['key'] ?? '';
	$post_id = false;
	if ( $base_id && $rel_key ) {
		$related = bws_get_related_posts_data( $base_id, $rel_key );
		$post_id = ! empty( $related ) ? bws_extract_post_id( $related[0] ) : false;
	}
	return bws_post_permalink_core( $post_id, $options, $instance );
}

/**
 * post_acf_date_time_single → post_acf_datetime_single.
 */
function bws_deprecated_post_acf_date_time_single_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'post_acf_date_time_single', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'post_acf_date_time_single' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'post_acf_date_time_single', 'post_acf_datetime_single' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_datetime_single_core( $post_id, $options, $instance );
}

/**
 * post_acf_date_time_range → post_acf_datetime_range.
 */
function bws_deprecated_post_acf_date_time_range_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'post_acf_date_time_range', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'post_acf_date_time_range' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'post_acf_date_time_range', 'post_acf_datetime_range' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'post' );
	$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_datetime_range_core( $post_id, $options, $instance );
}

/**
 * term_name → term_title.
 */
function bws_deprecated_term_name_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'term_name', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'term_name' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'term_name', 'term_title' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'term' );
	$term_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_term_title_core( $term_id, $options, $instance );
}

/**
 * term_field_image → term_custom_image.
 */
function bws_deprecated_term_field_image_callback( $options, $block, $instance ) {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return bws_build_deprecation_preview_label( 'term_field_image', $options );
	}
	if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( 'term_field_image' ) ) {
		return '';
	}
	bws_deprecated_tag_notice( 'term_field_image', 'term_image' );
	$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'term' );
	$term_id = $source ? $source->resolve_id( $options, $instance ) : false;
	return bws_term_custom_image_core( $term_id, $options, $instance );
}

// ===============================================
// CALLBACK FACTORIES — N×M DEPRECATED WRAPPERS
// ===============================================

/**
 * Build a callback for deprecated try_ tags (old N×M slot format: src_N/key_N/rel_N).
 *
 * Old try_ tags used src_N (source key), key_N (per-slot field key when $slot_key_opt set),
 * and rel_N (relationship field for related sources). Iterates slots 1–5, returns the first
 * non-empty result from the resolved entity, or the fallback_text/fallback option.
 *
 * @since 1.6.0
 * @param string      $old_tag      Deprecated tag name (for notice).
 * @param string      $since        Version deprecated.
 * @param callable    $core_fn      Core function that accepts (entity_id, options, instance).
 * @param string      $slot_key_opt Option key the core fn reads for field key (e.g. 'key').
 *                                  Empty string means no per-slot key injection.
 * @return callable
 */
function bws_make_deprecated_try_callback( string $old_tag, string $since, callable $core_fn, string $slot_key_opt = '' ): callable {
	return static function ( $options, $block, $instance ) use ( $old_tag, $since, $core_fn, $slot_key_opt ) {
		if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
			return bws_build_deprecation_preview_label( $old_tag, $options );
		}
		if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( $old_tag ) ) {
			return '';
		}
		bws_deprecated_tag_notice( $old_tag, '', $since );
		$fallback = sanitize_text_field( $options['fallback_text'] ?? $options['fallback'] ?? '' );

		for ( $n = 1; $n <= 5; $n++ ) {
			$src_val = $options[ "src_{$n}" ] ?? ( 1 === $n ? 'post' : '' );
			if ( '' === $src_val && $n > 1 ) {
				break;
			}
			$src_val = $src_val ?: 'post';

			$slot_opts = $options;

			// Inject rel_N as 'rel' so related-source resolve_id() finds the relationship field.
			if ( ! empty( $options[ "rel_{$n}" ] ) ) {
				$slot_opts['rel'] = $options[ "rel_{$n}" ];
			}

			$source = \BWS\DynamicTags\SourceRegistry::get_source( $src_val );
			if ( ! $source ) {
				continue;
			}

			$entity_id = $source->resolve_id( $slot_opts, $instance );
			if ( ! $entity_id ) {
				continue;
			}

			// Inject per-slot key into the option the core fn expects.
			if ( '' !== $slot_key_opt && isset( $options[ "key_{$n}" ] ) ) {
				$slot_opts[ $slot_key_opt ] = $options[ "key_{$n}" ];
			}

			$result = $core_fn( $entity_id, $slot_opts, $instance );
			if ( '' !== (string) $result ) {
				return $result;
			}
		}

		return $fallback;
	};
}

/**
 * Build a callback for N×M post-context deprecated tags.
 *
 * @since 1.6.0
 * @param string   $old_tag    Deprecated tag name (for notice).
 * @param string   $new_tag    Replacement tag name (for notice; '' when no migration path).
 * @param string   $since      Version deprecated.
 * @param string   $source_key Source registry key (e.g. 'post', 'related_post').
 * @param callable $core_fn    Core function that accepts (post_id, options, instance).
 * @return callable
 */
function bws_make_deprecated_post_callback( string $old_tag, string $new_tag, string $since, string $source_key, callable $core_fn ): callable {
	return static function ( $options, $block, $instance ) use ( $old_tag, $new_tag, $since, $source_key, $core_fn ) {
		if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
			return bws_build_deprecation_preview_label( $old_tag, $options );
		}
		if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( $old_tag ) ) {
			return '';
		}
		bws_deprecated_tag_notice( $old_tag, $new_tag, $since );
		$source  = \BWS\DynamicTags\SourceRegistry::get_source( $source_key );
		$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
		return $core_fn( $post_id, $options, $instance );
	};
}

/**
 * Build a callback for N×M term-context deprecated tags.
 *
 * @since 1.6.0
 * @param string   $old_tag  Deprecated tag name.
 * @param string   $new_tag  Replacement tag name ('' when no migration path).
 * @param string   $since    Version deprecated.
 * @param callable $core_fn  Core function that accepts (term_id, options, instance).
 * @return callable
 */
function bws_make_deprecated_term_callback( string $old_tag, string $new_tag, string $since, callable $core_fn ): callable {
	return static function ( $options, $block, $instance ) use ( $old_tag, $new_tag, $since, $core_fn ) {
		if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
			return bws_build_deprecation_preview_label( $old_tag, $options );
		}
		if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( $old_tag ) ) {
			return '';
		}
		bws_deprecated_tag_notice( $old_tag, $new_tag, $since );
		$source  = \BWS\DynamicTags\SourceRegistry::get_source( 'term' );
		$term_id = $source ? $source->resolve_id( $options, $instance ) : false;
		return $core_fn( $term_id, $options, $instance );
	};
}

/**
 * Build a callback for N×M post-context term-extraction deprecated tags.
 *
 * Resolves a post ID via $source_key, retrieves its terms via bws_get_terms_for_post(),
 * and delegates the first term to $term_core_fn.
 *
 * @since 1.6.0
 * @param string   $old_tag       Deprecated tag name.
 * @param string   $new_tag       Replacement tag name ('' when no migration path).
 * @param string   $since         Version deprecated.
 * @param string   $source_key    Source registry key for post resolution.
 * @param callable $term_core_fn  Core function that accepts (term_id, options, instance).
 * @return callable
 */
function bws_make_deprecated_term_extraction_callback( string $old_tag, string $new_tag, string $since, string $source_key, callable $term_core_fn ): callable {
	return static function ( $options, $block, $instance ) use ( $old_tag, $new_tag, $since, $source_key, $term_core_fn ) {
		if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
			return bws_build_deprecation_preview_label( $old_tag, $options );
		}
		if ( \BWS\DynamicTags\Admin\SettingsPage::is_deprecated_tag_suppressed( $old_tag ) ) {
			return '';
		}
		bws_deprecated_tag_notice( $old_tag, $new_tag, $since );
		$source  = \BWS\DynamicTags\SourceRegistry::get_source( $source_key );
		$post_id = $source ? $source->resolve_id( $options, $instance ) : false;
		if ( ! $post_id ) {
			return '';
		}
		$terms = bws_get_terms_for_post( (int) $post_id, $options );
		if ( empty( $terms ) ) {
			return '';
		}
		return $term_core_fn( reset( $terms )->term_id, $options, $instance );
	};
}

// ===============================================
// V1 DEPRECATED TAG WRAPPERS (N×M → BASE TAGS)
// ===============================================

/**
 * Register deprecated wrappers for all N×M source × template generated tags.
 *
 * Called before bws_register_deprecated_tags(). Each entry includes runtime callback
 * data and optional migration fields for the converter (source_inject, option_renames,
 * fixed_options, etc.).
 *
 * The bws_register_deprecated_tags() loop skips entries whose old_tag is already
 * registered in GB (dup-check guard).
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

	// Template option arrays (reused across sources).
	$content_opts = bws_get_content_options();
	$ct_opts      = bws_get_custom_text_options();
	$fi_opts      = bws_get_image_return_type_options();
	$ci_opts      = bws_get_meta_and_return_type_options();
	$cds_opts     = bws_get_date_single_options();
	$cdr_opts     = bws_get_date_range_options();
	$cdts_opts    = bws_get_datetime_single_options();
	$cdtr_opts    = bws_get_datetime_range_options();
	$te_opts      = bws_post_term_extraction_options();  // {tax, fallback_text}
	$ti_opts      = bws_post_term_image_options();       // {tax, key}

	// Source-specific traversal options.
	$rel_opts       = bws_get_relationship_field_options();
	$rel2_opts      = bws_get_second_relationship_field_options();
	$srp_src_opts   = array_merge( $rel_opts, $rel2_opts );  // second_related_post: rel + rel_2
	$ptrp_src_opts  = array(                                  // post_term_related_post: tax + rel
		'tax' => array(
			'type'        => 'text',
			'label'       => __( 'Taxonomy', 'generateblocks' ),
			'placeholder' => 'category',
		),
		'rel' => array(
			'type'        => 'text',
			'label'       => __( 'Relationship Field Key', 'generateblocks' ),
			'placeholder' => 'related_post',
		),
	);

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
		'title'          => __( 'Post Content (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => $content_renames,
		'value_renames'  => $content_values,
		'options'        => $content_opts,
		'callback'       => bws_make_deprecated_post_callback( 'post_content', 'content', $since, 'post', 'bws_post_content_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'post_custom_text',
		'new_tag'        => 'text',
		'title'          => __( 'Post Custom Text (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => $ct_renames,
		'options'        => $ct_opts,
		'callback'       => bws_make_deprecated_post_callback( 'post_custom_text', 'text', $since, 'post', 'bws_post_custom_text_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'post_featured_image',
		'new_tag'        => 'image',
		'title'          => __( 'Post Featured Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'supports'       => array( 'image-size' ),
		'source_inject'  => '',
		'option_renames' => $fi_renames,
		'fixed_options'  => $fi_fixed,
		'options'        => $fi_opts,
		'callback'       => bws_make_deprecated_post_callback( 'post_featured_image', 'image', $since, 'post', 'bws_featured_image_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'post_custom_image',
		'new_tag'        => 'image',
		'title'          => __( 'Post Custom Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'supports'       => array( 'image-size' ),
		'source_inject'  => '',
		'option_renames' => $ci_renames,
		'options'        => $ci_opts,
		'callback'       => bws_make_deprecated_post_callback( 'post_custom_image', 'image', $since, 'post', 'bws_custom_image_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_date_single',
		'new_tag'             => 'datetime_single',
		'title'               => __( 'Post Custom Date (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => $cds_opts,
		'callback'            => bws_make_deprecated_post_callback( 'post_custom_date_single', 'datetime_single', $since, 'post', 'bws_date_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_date_range',
		'new_tag'             => 'datetime_range',
		'title'               => __( 'Post Custom Date Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => $cdr_opts,
		'callback'            => bws_make_deprecated_post_callback( 'post_custom_date_range', 'datetime_range', $since, 'post', 'bws_date_range_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_datetime_single',
		'new_tag'             => 'datetime_single',
		'title'               => __( 'Post Custom Date/Time (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cdts_renames,
		'datetime_transforms' => true,
		'options'             => $cdts_opts,
		'callback'            => bws_make_deprecated_post_callback( 'post_custom_datetime_single', 'datetime_single', $since, 'post', 'bws_datetime_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'post_custom_datetime_range',
		'new_tag'             => 'datetime_range',
		'title'               => __( 'Post Custom Date/Time Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => $cdtr_renames,
		'datetime_transforms' => true,
		'options'             => $cdtr_opts,
		'callback'            => bws_make_deprecated_post_callback( 'post_custom_datetime_range', 'datetime_range', $since, 'post', 'bws_datetime_range_core' ),
	) );

	// Post → Term extraction.
	$reg::register( array(
		'old_tag'          => 'post_term_title',
		'new_tag'          => 'title',
		'title'            => __( 'Post Term Title (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => $srcterm_renames,
		'gb_link_remap'    => true,
		'required_options' => array( 'srcTermIn' ),
		'options'        => $te_opts,
		'callback'       => bws_make_deprecated_term_extraction_callback( 'post_term_title', 'title', $since, 'post', 'bws_term_title_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_permalink',
		'new_tag'          => 'permalink',
		'title'            => __( 'Post Term Permalink (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => $srcterm_renames,
		'required_options' => array( 'srcTermIn' ),
		'options'        => $te_opts,
		'callback'       => bws_make_deprecated_term_extraction_callback( 'post_term_permalink', 'permalink', $since, 'post', 'bws_term_permalink_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_description',
		'new_tag'          => 'content',
		'title'            => __( 'Post Term Description (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => $srcterm_renames,
		'required_options' => array( 'srcTermIn' ),
		'options'        => $te_opts,
		'callback'       => bws_make_deprecated_term_extraction_callback( 'post_term_description', 'content', $since, 'post', 'bws_term_description_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_custom_text',
		'new_tag'          => 'text',
		'title'            => __( 'Post Term Custom Text (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => '',
		'option_renames'   => array_merge( $ct_renames, $srcterm_renames ),
		'gb_link_remap'    => true,
		'required_options' => array( 'srcTermIn' ),
		'options'        => $te_opts,
		'callback'       => bws_make_deprecated_term_extraction_callback( 'post_term_custom_text', 'text', $since, 'post', 'bws_term_custom_text_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'post_term_custom_image',
		'new_tag'          => 'image',
		'title'            => __( 'Post Term Custom Image (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'supports'         => array( 'image-size' ),
		'source_inject'    => '',
		'option_renames'   => array_merge( $ci_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => $ti_opts,
		'callback'       => bws_make_deprecated_term_extraction_callback( 'post_term_custom_image', 'image', $since, 'post', 'bws_term_custom_image_core' ),
	) );

	// ==========================================
	// RELATED POST SOURCE (source_inject: 'ref')
	// ==========================================

	$reg::register( array(
		'old_tag'        => 'related_post_title',
		'new_tag'        => 'title',
		'title'          => __( 'Related Post Title (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
		'gb_link_remap'  => true,
		'options'        => $rel_opts,
		'callback'       => bws_make_deprecated_post_callback( 'related_post_title', 'title', $since, 'related_post', 'bws_post_title_core' ),
	) );

	$reg::register( array(
		'old_tag'            => 'related_post_content',
		'new_tag'            => 'title',
		'title'              => __( 'Related Post Content (Deprecated)', 'generateblocks' ),
		'since'              => $since,
		'options'            => array_merge( $rel_opts, $content_opts ),
		'callback'           => bws_make_deprecated_post_callback( 'related_post_content', 'content', $since, 'related_post', 'bws_post_content_core' ),
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
		'title'          => __( 'Related Post Permalink (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
		'options'        => $rel_opts,
		'callback'       => bws_make_deprecated_post_callback( 'related_post_permalink', 'permalink', $since, 'related_post', 'bws_post_permalink_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'related_post_custom_text',
		'new_tag'        => 'text',
		'title'          => __( 'Related Post Custom Text (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_ct_renames,
		'gb_link_remap'  => true,
		'options'        => array_merge( $rel_opts, $ct_opts ),
		'callback'       => bws_make_deprecated_post_callback( 'related_post_custom_text', 'text', $since, 'related_post', 'bws_post_custom_text_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'related_post_featured_image',
		'new_tag'        => 'image',
		'title'          => __( 'Related Post Featured Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'supports'       => array( 'image-size' ),
		'source_inject'  => 'ref',
		'option_renames' => $rel_fi_renames,
		'fixed_options'  => $fi_fixed,
		'options'        => array_merge( $rel_opts, $fi_opts ),
		'callback'       => bws_make_deprecated_post_callback( 'related_post_featured_image', 'image', $since, 'related_post', 'bws_featured_image_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'related_post_custom_image',
		'new_tag'        => 'image',
		'title'          => __( 'Related Post Custom Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'supports'       => array( 'image-size' ),
		'source_inject'  => 'ref',
		'option_renames' => $rel_ci_renames,
		'options'        => array_merge( $rel_opts, $ci_opts ),
		'callback'       => bws_make_deprecated_post_callback( 'related_post_custom_image', 'image', $since, 'related_post', 'bws_custom_image_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_date_single',
		'new_tag'             => 'datetime_single',
		'title'               => __( 'Related Post Custom Date (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cds_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'related_post_custom_date_single', 'datetime_single', $since, 'related_post', 'bws_date_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_date_range',
		'new_tag'             => 'datetime_range',
		'title'               => __( 'Related Post Custom Date Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cdr_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'related_post_custom_date_range', 'datetime_range', $since, 'related_post', 'bws_date_range_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_datetime_single',
		'new_tag'             => 'datetime_single',
		'title'               => __( 'Related Post Custom Date/Time (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdts_renames,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cdts_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'related_post_custom_datetime_single', 'datetime_single', $since, 'related_post', 'bws_datetime_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'related_post_custom_datetime_range',
		'new_tag'             => 'datetime_range',
		'title'               => __( 'Related Post Custom Date/Time Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdtr_renames,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cdtr_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'related_post_custom_datetime_range', 'datetime_range', $since, 'related_post', 'bws_datetime_range_core' ),
	) );

	// Related Post → Term extraction.
	$reg::register( array(
		'old_tag'          => 'related_post_term_title',
		'new_tag'          => 'title',
		'title'            => __( 'Related Post Term Title (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'related_post_term_title', 'title', $since, 'related_post', 'bws_term_title_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_permalink',
		'new_tag'          => 'permalink',
		'title'            => __( 'Related Post Term Permalink (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'related_post_term_permalink', 'permalink', $since, 'related_post', 'bws_term_permalink_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_description',
		'new_tag'          => 'content',
		'title'            => __( 'Related Post Term Description (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'related_post_term_description', 'content', $since, 'related_post', 'bws_term_description_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_custom_text',
		'new_tag'          => 'text',
		'title'            => __( 'Related Post Term Custom Text (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ct_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'related_post_term_custom_text', 'text', $since, 'related_post', 'bws_term_custom_text_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'related_post_term_custom_image',
		'new_tag'          => 'image',
		'title'            => __( 'Related Post Term Custom Image (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'supports'         => array( 'image-size' ),
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ci_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $ti_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'related_post_term_custom_image', 'image', $since, 'related_post', 'bws_term_custom_image_core' ),
	) );

	// ==========================================
	// SECOND RELATED POST SOURCE (no migration path)
	// ==========================================

	// Direct tags.
	foreach ( array(
		array( 'second_related_post_title',              __( 'Second Related Post Title (Deprecated)', 'generateblocks' ),              'bws_post_title_core',       array(), array() ),
		array( 'second_related_post_content',            __( 'Second Related Post Content (Deprecated)', 'generateblocks' ),            'bws_post_content_core',     $content_opts, array() ),
		array( 'second_related_post_permalink',          __( 'Second Related Post Permalink (Deprecated)', 'generateblocks' ),          'bws_post_permalink_core',   array(), array() ),
		array( 'second_related_post_custom_text',        __( 'Second Related Post Custom Text (Deprecated)', 'generateblocks' ),        'bws_post_custom_text_core', $ct_opts, array() ),
		array( 'second_related_post_featured_image',     __( 'Second Related Post Featured Image (Deprecated)', 'generateblocks' ),     'bws_featured_image_core',   $fi_opts, array( 'image-size' ) ),
		array( 'second_related_post_custom_image',       __( 'Second Related Post Custom Image (Deprecated)', 'generateblocks' ),       'bws_custom_image_core',     $ci_opts, array( 'image-size' ) ),
		array( 'second_related_post_custom_date_single', __( 'Second Related Post Custom Date (Deprecated)', 'generateblocks' ),        'bws_date_single_core',      $cds_opts, array() ),
		array( 'second_related_post_custom_date_range',  __( 'Second Related Post Custom Date Range (Deprecated)', 'generateblocks' ),  'bws_date_range_core',       $cdr_opts, array() ),
		array( 'second_related_post_custom_datetime_single', __( 'Second Related Post Custom Date/Time (Deprecated)', 'generateblocks' ),       'bws_datetime_single_core', $cdts_opts, array() ),
		array( 'second_related_post_custom_datetime_range',  __( 'Second Related Post Custom Date/Time Range (Deprecated)', 'generateblocks' ), 'bws_datetime_range_core',  $cdtr_opts, array() ),
	) as [ $old_tag, $title, $core_fn, $tpl_opts, $supports ] ) {
		$entry = array(
			'old_tag'  => $old_tag,
			'title'    => $title,
			'since'    => $since,
			'options'  => empty( $tpl_opts ) ? $srp_src_opts : array_merge( $srp_src_opts, $tpl_opts ),
			'callback' => bws_make_deprecated_post_callback( $old_tag, '', $since, 'second_related_post', $core_fn ),
		);
		if ( ! empty( $supports ) ) {
			$entry['supports'] = $supports;
		}
		$reg::register( $entry );
	}

	// Second Related Post → Term extraction.
	foreach ( array(
		array( 'second_related_post_term_title',        __( 'Second Related Post Term Title (Deprecated)', 'generateblocks' ),        'bws_term_title_core',       $te_opts, array() ),
		array( 'second_related_post_term_permalink',    __( 'Second Related Post Term Permalink (Deprecated)', 'generateblocks' ),    'bws_term_permalink_core',   $te_opts, array() ),
		array( 'second_related_post_term_description',  __( 'Second Related Post Term Description (Deprecated)', 'generateblocks' ),  'bws_term_description_core', $te_opts, array() ),
		array( 'second_related_post_term_custom_text',  __( 'Second Related Post Term Custom Text (Deprecated)', 'generateblocks' ),  'bws_term_custom_text_core', $te_opts, array() ),
		array( 'second_related_post_term_custom_image', __( 'Second Related Post Term Custom Image (Deprecated)', 'generateblocks' ), 'bws_term_custom_image_core', $ti_opts, array( 'image-size' ) ),
	) as [ $old_tag, $title, $term_core_fn, $tpl_opts, $supports ] ) {
		$entry = array(
			'old_tag'  => $old_tag,
			'title'    => $title,
			'since'    => $since,
			'options'  => array_merge( $srp_src_opts, $tpl_opts ),
			'callback' => bws_make_deprecated_term_extraction_callback( $old_tag, '', $since, 'second_related_post', $term_core_fn ),
		);
		if ( ! empty( $supports ) ) {
			$entry['supports'] = $supports;
		}
		$reg::register( $entry );
	}

	// ==========================================
	// POST TERM RELATED POST SOURCE (no migration path, excludes term extraction templates)
	// ==========================================

	foreach ( array(
		array( 'post_term_related_post_title',             __( 'Post→Term→Rel. Post Title (Deprecated)', 'generateblocks' ),             'bws_post_title_core',       array(), array() ),
		array( 'post_term_related_post_content',           __( 'Post→Term→Rel. Post Content (Deprecated)', 'generateblocks' ),           'bws_post_content_core',     $content_opts, array() ),
		array( 'post_term_related_post_permalink',         __( 'Post→Term→Rel. Post Permalink (Deprecated)', 'generateblocks' ),         'bws_post_permalink_core',   array(), array() ),
		array( 'post_term_related_post_custom_text',       __( 'Post→Term→Rel. Post Custom Text (Deprecated)', 'generateblocks' ),       'bws_post_custom_text_core', $ct_opts, array() ),
		array( 'post_term_related_post_featured_image',    __( 'Post→Term→Rel. Post Featured Image (Deprecated)', 'generateblocks' ),    'bws_featured_image_core',   $fi_opts, array( 'image-size' ) ),
		array( 'post_term_related_post_custom_image',      __( 'Post→Term→Rel. Post Custom Image (Deprecated)', 'generateblocks' ),      'bws_custom_image_core',     $ci_opts, array( 'image-size' ) ),
		array( 'post_term_related_post_custom_date_single', __( 'Post→Term→Rel. Post Custom Date (Deprecated)', 'generateblocks' ),       'bws_date_single_core',      $cds_opts, array() ),
		array( 'post_term_related_post_custom_date_range',  __( 'Post→Term→Rel. Post Custom Date Range (Deprecated)', 'generateblocks' ), 'bws_date_range_core',       $cdr_opts, array() ),
		array( 'post_term_related_post_custom_datetime_single', __( 'Post→Term→Rel. Post Custom Date/Time (Deprecated)', 'generateblocks' ),       'bws_datetime_single_core', $cdts_opts, array() ),
		array( 'post_term_related_post_custom_datetime_range',  __( 'Post→Term→Rel. Post Custom Date/Time Range (Deprecated)', 'generateblocks' ), 'bws_datetime_range_core',  $cdtr_opts, array() ),
	) as [ $old_tag, $title, $core_fn, $tpl_opts, $supports ] ) {
		$entry = array(
			'old_tag'  => $old_tag,
			'title'    => $title,
			'since'    => $since,
			'options'  => empty( $tpl_opts ) ? $ptrp_src_opts : array_merge( $ptrp_src_opts, $tpl_opts ),
			'callback' => bws_make_deprecated_post_callback( $old_tag, '', $since, 'post_term_related_post', $core_fn ),
		);
		if ( ! empty( $supports ) ) {
			$entry['supports'] = $supports;
		}
		$reg::register( $entry );
	}

	// ==========================================
	// TERM RELATED POST SOURCE (source_inject: 'ref')
	// ==========================================

	$reg::register( array(
		'old_tag'        => 'term_related_post_title',
		'new_tag'        => 'term_title',
		'title'          => __( 'Term→Rel. Post Title (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
		'gb_link_remap'  => true,
		'options'        => $rel_opts,
		'callback'       => bws_make_deprecated_post_callback( 'term_related_post_title', 'title', $since, 'term_related_post', 'bws_post_title_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_content',
		'new_tag'        => 'term_content',
		'title'          => __( 'Term→Rel. Post Content (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_content_renames,
		'value_renames'  => $content_values,
		'options'        => array_merge( $rel_opts, $content_opts ),
		'callback'       => bws_make_deprecated_post_callback( 'term_related_post_content', 'content', $since, 'term_related_post', 'bws_post_content_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_permalink',
		'new_tag'        => 'term_permalink',
		'title'          => __( 'Term→Rel. Post Permalink (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_renames,
		'options'        => $rel_opts,
		'callback'       => bws_make_deprecated_post_callback( 'term_related_post_permalink', 'permalink', $since, 'term_related_post', 'bws_post_permalink_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_custom_text',
		'new_tag'        => 'term_text',
		'title'          => __( 'Term→Rel. Post Custom Text (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => $rel_ct_renames,
		'gb_link_remap'  => true,
		'options'        => array_merge( $rel_opts, $ct_opts ),
		'callback'       => bws_make_deprecated_post_callback( 'term_related_post_custom_text', 'text', $since, 'term_related_post', 'bws_post_custom_text_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_featured_image',
		'new_tag'        => 'term_image',
		'title'          => __( 'Term→Rel. Post Featured Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'supports'       => array( 'image-size' ),
		'source_inject'  => 'ref',
		'option_renames' => $rel_fi_renames,
		'fixed_options'  => $fi_fixed,
		'options'        => array_merge( $rel_opts, $fi_opts ),
		'callback'       => bws_make_deprecated_post_callback( 'term_related_post_featured_image', 'image', $since, 'term_related_post', 'bws_featured_image_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'term_related_post_custom_image',
		'new_tag'        => 'term_image',
		'title'          => __( 'Term→Rel. Post Custom Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'supports'       => array( 'image-size' ),
		'source_inject'  => 'ref',
		'option_renames' => $rel_ci_renames,
		'options'        => array_merge( $rel_opts, $ci_opts ),
		'callback'       => bws_make_deprecated_post_callback( 'term_related_post_custom_image', 'image', $since, 'term_related_post', 'bws_custom_image_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_date_single',
		'new_tag'             => 'term_datetime_single',
		'title'               => __( 'Term→Rel. Post Custom Date (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cds_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'term_related_post_custom_date_single', 'datetime_single', $since, 'term_related_post', 'bws_date_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_date_range',
		'new_tag'             => 'term_datetime_range',
		'title'               => __( 'Term→Rel. Post Custom Date Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cdr_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'term_related_post_custom_date_range', 'datetime_range', $since, 'term_related_post', 'bws_date_range_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_datetime_single',
		'new_tag'             => 'term_datetime_single',
		'title'               => __( 'Term→Rel. Post Custom Date/Time (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdts_renames,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cdts_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'term_related_post_custom_datetime_single', 'datetime_single', $since, 'term_related_post', 'bws_datetime_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_related_post_custom_datetime_range',
		'new_tag'             => 'term_datetime_range',
		'title'               => __( 'Term→Rel. Post Custom Date/Time Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => 'ref',
		'option_renames'      => $rel_cdtr_renames,
		'datetime_transforms' => true,
		'options'             => array_merge( $rel_opts, $cdtr_opts ),
		'callback'            => bws_make_deprecated_post_callback( 'term_related_post_custom_datetime_range', 'datetime_range', $since, 'term_related_post', 'bws_datetime_range_core' ),
	) );

	// Term→Rel. Post → Term extraction.
	$reg::register( array(
		'old_tag'          => 'term_related_post_term_title',
		'new_tag'          => 'term_title',
		'title'            => __( 'Term→Rel. Post Term Title (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'term_related_post_term_title', 'title', $since, 'term_related_post', 'bws_term_title_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_permalink',
		'new_tag'          => 'term_permalink',
		'title'            => __( 'Term→Rel. Post Term Permalink (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'term_related_post_term_permalink', 'permalink', $since, 'term_related_post', 'bws_term_permalink_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_description',
		'new_tag'          => 'term_content',
		'title'            => __( 'Term→Rel. Post Term Description (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'term_related_post_term_description', 'content', $since, 'term_related_post', 'bws_term_description_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_custom_text',
		'new_tag'          => 'term_text',
		'title'            => __( 'Term→Rel. Post Term Custom Text (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ct_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $te_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'term_related_post_term_custom_text', 'text', $since, 'term_related_post', 'bws_term_custom_text_core' ),
	) );

	$reg::register( array(
		'old_tag'          => 'term_related_post_term_custom_image',
		'new_tag'          => 'term_image',
		'title'            => __( 'Term→Rel. Post Term Custom Image (Deprecated)', 'generateblocks' ),
		'since'            => $since,
		'supports'         => array( 'image-size' ),
		'source_inject'    => 'ref',
		'option_renames'   => array_merge( $rel_ci_renames, $srcterm_renames ),
		'required_options' => array( 'srcTermIn' ),
		'options'        => array_merge( $rel_opts, $ti_opts ),
		'callback'       => bws_make_deprecated_term_extraction_callback( 'term_related_post_term_custom_image', 'image', $since, 'term_related_post', 'bws_term_custom_image_core' ),
	) );

	// ==========================================
	// TERM CONTEXT N×M TAGS (description template + term-context custom field templates)
	// ==========================================

	$reg::register( array(
		'old_tag'  => 'term_description',
		'new_tag'  => 'term_content',
		'title'    => __( 'Term Description (Deprecated)', 'generateblocks' ),
		'since'    => $since,
		'callback' => bws_make_deprecated_term_callback( 'term_description', 'term_content', $since, 'bws_term_description_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'term_custom_text',
		'new_tag'        => 'term_text',
		'title'          => __( 'Term Custom Text (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'option_renames' => $ct_renames,
		'options'        => $ct_opts,
		'callback'       => bws_make_deprecated_term_callback( 'term_custom_text', 'term_text', $since, 'bws_term_custom_text_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'term_custom_image',
		'new_tag'        => 'term_image',
		'title'          => __( 'Term Custom Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'supports'       => array( 'image-size' ),
		'option_renames' => $ci_renames,
		'options'        => bws_get_term_image_and_return_type_options(),
		'callback'       => bws_make_deprecated_term_callback( 'term_custom_image', 'term_image', $since, 'bws_term_custom_image_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_date_single',
		'new_tag'             => 'term_datetime_single',
		'title'               => __( 'Term Custom Date (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $cds_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => $cds_opts,
		'callback'            => bws_make_deprecated_term_callback( 'term_custom_date_single', 'term_datetime_single', $since, 'bws_term_date_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_date_range',
		'new_tag'             => 'term_datetime_range',
		'title'               => __( 'Term Custom Date Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $cdr_renames,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => $cdr_opts,
		'callback'            => bws_make_deprecated_term_callback( 'term_custom_date_range', 'term_datetime_range', $since, 'bws_term_date_range_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_datetime_single',
		'new_tag'             => 'term_datetime_single',
		'title'               => __( 'Term Custom Date/Time (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $cdts_renames,
		'datetime_transforms' => true,
		'options'             => $cdts_opts,
		'callback'            => bws_make_deprecated_term_callback( 'term_custom_datetime_single', 'term_datetime_single', $since, 'bws_term_datetime_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'term_custom_datetime_range',
		'new_tag'             => 'term_datetime_range',
		'title'               => __( 'Term Custom Date/Time Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $cdtr_renames,
		'datetime_transforms' => true,
		'options'             => $cdtr_opts,
		'callback'            => bws_make_deprecated_term_callback( 'term_custom_datetime_range', 'term_datetime_range', $since, 'bws_term_datetime_range_core' ),
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
		'title'          => __( 'Try Custom Text (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'option_renames' => $try_ct_renames,
		'value_renames'  => $try_src_values,
		'options'        => array(),
		'callback'       => bws_make_deprecated_try_callback( 'try_custom_text', $since, 'bws_post_custom_text_core', 'key' ),
	) );

	$reg::register( array(
		'old_tag'        => 'try_featured_image',
		'new_tag'        => 'try_image',
		'title'          => __( 'Try Featured Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'option_renames' => $try_src_renames,
		'value_renames'  => $try_src_values,
		'fixed_options'  => $fi_fixed,
		'options'        => array(),
		'callback'       => bws_make_deprecated_try_callback( 'try_featured_image', $since, 'bws_featured_image_core' ),
	) );

	$reg::register( array(
		'old_tag'        => 'try_custom_image',
		'new_tag'        => 'try_image',
		'title'          => __( 'Try Custom Image (Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'option_renames' => $try_ci_renames,
		'value_renames'  => $try_src_values,
		'options'        => array(),
		'callback'       => bws_make_deprecated_try_callback( 'try_custom_image', $since, 'bws_custom_image_core', 'key' ),
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_date_single',
		'new_tag'             => 'try_datetime_single',
		'title'               => __( 'Try Custom Date (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $try_cds_renames,
		'value_renames'       => $try_src_values,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => array(),
		'callback'            => bws_make_deprecated_try_callback( 'try_custom_date_single', $since, 'bws_date_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_date_range',
		'new_tag'             => 'try_datetime_range',
		'title'               => __( 'Try Custom Date Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $try_cdr_renames,
		'value_renames'       => $try_src_values,
		'fixed_options'       => $date_fixed,
		'datetime_transforms' => true,
		'options'             => array(),
		'callback'            => bws_make_deprecated_try_callback( 'try_custom_date_range', $since, 'bws_date_range_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_datetime_single',
		'new_tag'             => 'try_datetime_single',
		'title'               => __( 'Try Custom Date/Time (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $try_cdts_renames,
		'value_renames'       => $try_src_values,
		'datetime_transforms' => true,
		'options'             => array(),
		'callback'            => bws_make_deprecated_try_callback( 'try_custom_datetime_single', $since, 'bws_datetime_single_core' ),
	) );

	$reg::register( array(
		'old_tag'             => 'try_custom_datetime_range',
		'new_tag'             => 'try_datetime_range',
		'title'               => __( 'Try Custom Date/Time Range (Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'option_renames'      => $try_cdtr_renames,
		'value_renames'       => $try_src_values,
		'datetime_transforms' => true,
		'options'             => array(),
		'callback'            => bws_make_deprecated_try_callback( 'try_custom_datetime_range', $since, 'bws_datetime_range_core' ),
	) );
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
		'title'          => __( 'Post Featured Image (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => array( 'return_type' => 'as', 'id' => 'fallback' ),
		'fixed_options'  => array( 'use' => 'featured' ),
		'callback'       => 'bws_deprecated_current_post_featured_image_callback',
	) );

	// current_post_meta_image → image.
	// Old options: meta_key (→ key), return_type (→ as), id (→ fallback).
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'current_post_meta_image',
		'new_tag'        => 'image',
		'title'          => __( 'Post Custom Image (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => '',
		'option_renames' => array( 'meta_key' => 'key', 'field_key' => 'key', 'return_type' => 'as', 'fallback_url' => 'fallback', 'id' => 'fallback' ),
		'callback'       => 'bws_deprecated_current_post_meta_image_callback',
	) );

	// related_post_meta_image → image (src:ref).
	// Old options: rel (→ ref, relationship field), meta_key (→ key, image field), return_type (→ as), id (→ fallback).
	// Note: 'key' in old tag was the relationship field (removed in callback); image field was 'meta_key'.
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'related_post_meta_image',
		'new_tag'        => 'image',
		'title'          => __( 'Related Post Custom Image (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => array( 'rel' => 'ref', 'key' => 'ref', 'meta_key' => 'key', 'field_key' => 'key', 'return_type' => 'as', 'fallback_url' => 'fallback', 'id' => 'fallback' ),
		'callback'       => 'bws_deprecated_related_post_meta_image_callback',
	) );

	// related_post_url → permalink (src:ref).
	// Old options: rel or key (→ ref, relationship field).
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'related_post_url',
		'new_tag'        => 'permalink',
		'title'          => __( 'Related Post Permalink (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'source_inject'  => 'ref',
		'option_renames' => array( 'rel' => 'ref', 'key' => 'ref' ),
		'callback'       => 'bws_deprecated_related_post_url_callback',
	) );

	// post_acf_date_time_single → datetime_single.
	// Old options: date_time_field (→ key), time_field (→ timeKey), fallback_text (→ fallback), datetime booleans.
	$reg::register( array(
		'type'                => 'tag',
		'old_tag'             => 'post_acf_date_time_single',
		'new_tag'             => 'datetime_single',
		'title'               => __( 'Post ACF Date/Time (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => array( 'date_time_field' => 'key', 'time_field' => 'timeKey', 'fallback_text' => 'fallback' ),
		'datetime_transforms' => true,
		'callback'            => 'bws_deprecated_post_acf_date_time_single_callback',
	) );

	// post_acf_date_time_range → datetime_range.
	// Old options: start_field (→ startKey), end_field (→ endKey), separator (→ rangeSep), fallback_text (→ fallback), datetime booleans.
	$reg::register( array(
		'type'                => 'tag',
		'old_tag'             => 'post_acf_date_time_range',
		'new_tag'             => 'datetime_range',
		'title'               => __( 'Post ACF Date/Time Range (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'               => $since,
		'source_inject'       => '',
		'option_renames'      => array( 'start_field' => 'startKey', 'end_field' => 'endKey', 'separator' => 'rangeSep', 'fallback_text' => 'fallback' ),
		'datetime_transforms' => true,
		'callback'            => 'bws_deprecated_post_acf_date_time_range_callback',
	) );

	// term_name → term_title (standalone term modifier tag; no source inject).
	$reg::register( array(
		'type'     => 'tag',
		'old_tag'  => 'term_name',
		'new_tag'  => 'term_title',
		'title'    => __( 'Term Name (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'    => $since,
		'callback' => 'bws_deprecated_term_name_callback',
	) );

	// term_field_image → term_image (standalone term modifier tag; no source inject).
	// Old options: meta_key (→ key), return_type (→ as), id (→ fallback).
	$reg::register( array(
		'type'           => 'tag',
		'old_tag'        => 'term_field_image',
		'new_tag'        => 'term_image',
		'title'          => __( 'Term Custom Image (Pre-v1.6 Deprecated)', 'generateblocks' ),
		'since'          => $since,
		'option_renames' => array( 'meta_key' => 'key', 'field_key' => 'key', 'return_type' => 'as', 'fallback_url' => 'fallback', 'id' => 'fallback' ),
		'callback'       => 'bws_deprecated_term_field_image_callback',
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
