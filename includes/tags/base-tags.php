<?php
/**
 * Base (source-agnostic) dynamic tag registrations.
 *
 * Registers one GB tag per content template. Entity traversal is selected at
 * render time via the `source` option rather than at registration time. Unset
 * `source` resolves from the current loop entity; named values dispatch to the
 * appropriate source class.
 *
 * Registered tags: text, content, title, permalink, image, datetime_single, datetime_range
 *
 * Source dispatch table (post context):
 *   ''    → CurrentPost (no traversal; current loop entity)
 *   'ref' → RelatedPost (single ACF relationship/post_object hop; sub-option: ref)
 *
 * srcTerm modifier (applied after source resolution):
 *   When `srcTerm` is set, the resolved entity's first matching taxonomy term
 *   (via the `tax` sub-option) is used as the final entity.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BWS\DynamicTags\SourceRegistry;
use BWS\DynamicTags\TagTemplateRegistry;

/**
 * Register all base dynamic tags.
 *
 * @since 1.6.0
 */
function bws_register_base_tags(): void {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	$source_opt     = bws_base_source_option();
	$traversal_opts = bws_base_traversal_options();

	// =========================================================
	// text — ACF/meta field or entity title; supports_list
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Text Fields', 'generateblocks' ),
		'tag'      => 'text',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				'use'      => array(
					'type'           => 'select',
					'label'          => __( 'Text Field', 'generateblocks' ),
					'options'        => array(
						array( 'value' => 'key',     'label' => __( 'Meta/Custom Field', 'generateblocks' ) ),
						array( 'value' => 'title',   'label' => __( 'Title/Name', 'generateblocks' ) ),
						array( 'value' => 'tagline', 'label' => __( 'Site Tagline', 'generateblocks' ) ),
						array( 'value' => 'option',  'label' => __( 'Site Option (key)', 'generateblocks' ) ),
					),
					'_strip_default' => true,
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key (or wp_options key for src:site, use:option — supports dot-path).', 'generateblocks' ),
					'placeholder' => 'field_name',
					// Show for meta/field key (use empty or 'key') AND for site use:option;
					// hide for title/tagline (no key needed). not_in covers empty-default.
					'show_if'     => array( 'use' => 'not_in:title,tagline' ),
				),
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
			),
			function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array(),
			array(
				'limit'    => array(
					'type'  => 'number',
					'label' => __( 'Result Limit', 'generateblocks' ),
					'help'  => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
				),
				'sep'      => array(
					'type'        => 'text',
					'label'       => __( 'Result Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
				),
			)
		) ),
		'return'   => 'bws_base_text_callback',
	) );

	// =========================================================
	// content — post content, excerpt, or WYSIWYG field
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Content', 'generateblocks' ),
		'tag'      => 'content',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				'use'      => array(
					'type'           => 'select',
					'label'          => __( 'Content Field', 'generateblocks' ),
					'options'        => array(
						array( 'value' => 'content', 'label' => __( 'Post Content/Term Description', 'generateblocks' ) ),
						array( 'value' => 'key',     'label' => __( 'Custom Content Field (WYSIWYG/Blocks)', 'generateblocks' ) ),
						array( 'value' => 'excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ),
						array( 'value' => 'option',  'label' => __( 'Site Option (key)', 'generateblocks' ) ),
					),
					'_strip_default' => true,
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key (or wp_options key for src:site, use:option — supports dot-path).', 'generateblocks' ),
					'placeholder' => 'field_name',
					'show_if'     => array( 'use' => 'in:key,option' ),
				),
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if content is empty or not found.', 'generateblocks' ),
				),
			)
		) ),
		'return'   => 'bws_base_content_callback',
	) );

	// =========================================================
	// title — entity title/name; source traversal + srcTerm
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Title / Name', 'generateblocks' ),
		'tag'      => 'title',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				'limit' => array(
					'type'  => 'number',
					'label' => __( 'Limit', 'generateblocks' ),
					'help'  => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
				),
				'sep'   => array(
					'type'        => 'text',
					'label'       => __( 'Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
				),
			),
			function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array()
		) ),
		'return'   => 'bws_base_title_callback',
	) );

	// =========================================================
	// permalink — post/entity URL; source traversal + srcTerm
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Permalink', 'generateblocks' ),
		'tag'      => 'permalink',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				// Site-only: which site URL (or option key). Whole control hidden off-site.
				'use' => array(
					'type'           => 'select',
					'label'          => __( 'Site URL', 'generateblocks' ),
					'options'        => array(
						array( 'value' => 'site_url', 'label' => __( 'Site URL', 'generateblocks' ) ),
						array( 'value' => 'home_url', 'label' => __( 'Home URL', 'generateblocks' ) ),
						array( 'value' => 'option',   'label' => __( 'Site Option (key)', 'generateblocks' ) ),
					),
					'show_if'        => array( 'src' => 'site' ),
					'_strip_default' => true,
				),
				'key' => array(
					'type'        => 'text',
					'label'       => __( 'Option Key', 'generateblocks' ),
					'help'        => __( 'wp_options key (supports dot-path). Must be allowlisted via generateblocks_dynamic_tags_allowed_options.', 'generateblocks' ),
					'placeholder' => 'option_name',
					'show_if'     => array( 'src' => 'site', 'use' => 'option' ),
				),
			)
		) ),
		'return'   => 'bws_base_permalink_callback',
	) );

	// =========================================================
	// image — custom field or featured image; type 'cross-source'.
	// `as` is first and always serialized (default:'url' intentional).
	// Image size handled by GB native control via 'image-size' support.
	// `fallback` uses custom JS control (image-tag-controls.js).
	// `use:featured` hidden when srcTerm set — terms have no featured image.
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Image', 'generateblocks' ),
		'tag'      => 'image',
		'type'     => 'cross-source',
		'supports' => array( 'image-size' ),
		'options'  => bws_strip_default_select_values( array_merge(
			array(
				'as' => array(
					'type'    => 'select',
					'label'   => __( 'Return type:', 'generateblocks' ),
					'default' => 'url',
					'options' => array(
						array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
						array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
						array( 'value' => 'title',   'label' => __( 'Image Title', 'generateblocks' ) ),
						array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
						array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
					),
				),
			),
			$source_opt,
			$traversal_opts,
			array(
				'use'      => array(
					'type'           => 'select',
					'label'          => __( 'Image Field', 'generateblocks' ),
					'options'        => array(
						array( 'value' => 'key',      'label' => __( 'Meta/Custom Field', 'generateblocks' ) ),
						array( 'value' => 'featured', 'label' => __( 'Featured Image', 'generateblocks' ) ),
						array( 'value' => 'logo',     'label' => __( 'Site Logo', 'generateblocks' ) ),
						array( 'value' => 'option',   'label' => __( 'Site Option (image ID key)', 'generateblocks' ) ),
					),
					'show_if'        => array( 'srcTermIn' => 'empty' ),
					'_strip_default' => true,
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key for the image (or wp_options key for src:site, use:option).', 'generateblocks' ),
					'placeholder' => 'image_field',
					// Hide for featured + logo (no key needed); show for key/option.
					'show_if'     => array( 'use' => 'not_in:featured,logo' ),
				),
				'fallback' => array(
					'type'  => 'bws-media-picker',
					'label' => __( 'Fallback Image', 'generateblocks' ),
				),
			)
		) ),
		'return'   => 'bws_base_image_callback',
	) );

	// =========================================================
	// datetime_single — single date/time field(s) with mode switch
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Date / Time', 'generateblocks' ),
		'tag'      => 'datetime_single',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_get_base_datetime_single_options(),
		'return'   => 'bws_base_datetime_single_callback',
	) );

	// =========================================================
	// datetime_range — start/end date/time range with mode switch
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Date / Time Range', 'generateblocks' ),
		'tag'      => 'datetime_range',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_get_base_datetime_range_options(),
		'return'   => 'bws_base_datetime_range_callback',
	) );

	// =========================================================
	// Register modifier templates for the term_ constructor.
	//
	// Each descriptor is stored in TagTemplateRegistry::$modifier_templates
	// and consumed by both register_modifier() (generates term_* GB tags)
	// and generate_base_try_tags() (generates try_* GB tags).
	//
	// 'leading_options' — Group 1 options (as, size, format, etc.) prepended before slots in try_ tags.
	// 'options'         — template-specific options; for try_ tags, keys matching leading_options are
	//                     stripped so they don't appear twice; remaining keys become Group 3 trailing.
	// 'term_fn'         — fn($term_id, $opts, $inst) for the direct term-entity path.
	// 'post_fn'         — fn($post_id, $opts, $inst) for the ref-traversal path (term → post).
	// 'try_core_fn'     — fn($post_id, $opts, $inst) for try_ post-slot dispatch.
	// 'try_term_fn'     — fn($term_id, $opts, $inst) for try_ srcTerm slot dispatch.
	// =========================================================

	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'text',
		'title'                 => __( 'Text Fields', 'generateblocks' ),
		'supports_link_wrap'    => true,
		'options'               => array(
			'use'      => array(
				'type'           => 'select',
				'label'          => __( 'Text Field', 'generateblocks' ),
				'options'        => array(
					array( 'value' => 'key',   'label' => __( 'Meta/Custom Field', 'generateblocks' ) ),
					array( 'value' => 'title', 'label' => __( 'Title/Name', 'generateblocks' ) ),
				),
				'_strip_default' => true,
			),
			'key'      => array(
				'type'        => 'text',
				'label'       => __( 'Field Key', 'generateblocks' ),
				'help'        => __( 'ACF or meta field key.', 'generateblocks' ),
				'placeholder' => 'field_name',
			),
			'fallback' => array(
				'type'  => 'text',
				'label' => __( 'Fallback Text', 'generateblocks' ),
				'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
			),
		),
		'term_fn'               => 'bws_term_custom_text_core',
		'post_fn'               => 'bws_post_custom_text_core',
		'try_core_fn'           => 'bws_try_text_post_dispatch',
		'try_term_fn'           => 'bws_try_text_term_dispatch',
		'supports_try'          => true,
		'try_per_slot_key'      => true,
		'try_per_slot_use'      => true,
		'try_use_no_key_values' => array( 'title' ),
		'is_image'              => false,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'content',
		'title'                 => __( 'Content', 'generateblocks' ),
		'options'               => array(
			'use'      => array(
				'type'           => 'select',
				'label'          => __( 'Content Field', 'generateblocks' ),
				'options'        => array(
					array( 'value' => 'content', 'label' => __( 'Post Content/Term Description', 'generateblocks' ) ),
					array( 'value' => 'key',     'label' => __( 'Custom Content Field (WYSIWYG/Blocks)', 'generateblocks' ) ),
					array( 'value' => 'excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ),
				),
				'_strip_default' => true,
			),
			'key'      => array(
				'type'        => 'text',
				'label'       => __( 'Field Key', 'generateblocks' ),
				'help'        => __( 'ACF or meta field key.', 'generateblocks' ),
				'placeholder' => 'field_name',
			),
			'fallback' => array(
				'type'  => 'text',
				'label' => __( 'Fallback Text', 'generateblocks' ),
				'help'  => __( 'Text to display if content is empty.', 'generateblocks' ),
			),
		),
		'term_fn'               => 'bws_term_description_core',
		'post_fn'               => 'bws_post_content_core',
		'try_core_fn'           => 'bws_try_content_post_dispatch',
		'try_term_fn'           => 'bws_try_content_term_dispatch',
		'supports_try'          => true,
		'try_per_slot_key'      => true,
		'try_per_slot_use'      => true,
		'try_use_no_key_values' => array( 'content', 'excerpt' ),
		'is_image'              => false,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'                => 'title',
		'title'              => __( 'Title / Name', 'generateblocks' ),
		'supports_link_wrap' => true,
		'options'            => array(),
		'term_fn'      => 'bws_term_title_core',
		'post_fn'      => 'bws_post_title_core',
		'try_core_fn'  => 'bws_post_title_core',
		'try_term_fn'  => 'bws_term_title_core',
		'supports_try' => true,
		'is_image'     => false,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'          => 'permalink',
		'title'        => __( 'Permalink', 'generateblocks' ),
		'options'      => array(),
		'term_fn'      => 'bws_term_permalink_core',
		'post_fn'      => 'bws_post_permalink_core',
		'try_core_fn'  => 'bws_post_permalink_core',
		'try_term_fn'  => 'bws_term_permalink_core',
		'supports_try' => true,
		'is_image'     => false,
	) );

	// image: register_modifier() (is_image=true) builds its own option set and ignores 'options'.
	// generate_base_try_tags(): 'leading_options' (as, size) → slots → trailing from 'options' minus leading/per-slot keys.
	// 'use' kept in 'options' so generate_base_try_tags() reads its options for per-slot use selectors.
	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'image',
		'title'                 => __( 'Image', 'generateblocks' ),
		'leading_options'       => array(
			'as' => array(
				'type'    => 'select',
				'label'   => __( 'Return image as:', 'generateblocks' ),
				'default' => 'url',
				'options' => array(
					array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
					array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
					array( 'value' => 'title',   'label' => __( 'Image Title', 'generateblocks' ) ),
					array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
					array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
				),
			),
		),
		'options'               => array(
			'as'       => array(
				'type'    => 'select',
				'label'   => __( 'Return image as:', 'generateblocks' ),
				'default' => 'url',
				'options' => array(
					array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
					array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
					array( 'value' => 'title',   'label' => __( 'Image Title', 'generateblocks' ) ),
					array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
					array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
				),
			),
			'use'      => array(
				'type'           => 'select',
				'label'          => __( 'Image Field', 'generateblocks' ),
				'options'        => array(
					array( 'value' => 'key',      'label' => __( 'Meta/Custom Field', 'generateblocks' ) ),
					array( 'value' => 'featured', 'label' => __( 'Featured Image', 'generateblocks' ) ),
				),
				'_strip_default' => true,
			),
			'key'      => array(
				'type'        => 'text',
				'label'       => __( 'Field Key', 'generateblocks' ),
				'help'        => __( 'ACF or meta field key for the image.', 'generateblocks' ),
				'placeholder' => 'image_field',
				'show_if'     => array( 'use' => 'not:featured' ),
			),
			'fallback' => array(
				'type'  => 'bws-media-picker',
				'label' => __( 'Fallback Image', 'generateblocks' ),
			),
		),
		'term_fn'               => 'bws_term_custom_image_core',
		'post_fn'               => 'bws_custom_image_core',
		'try_core_fn'           => 'bws_try_image_post_dispatch',
		'try_term_fn'           => 'bws_term_custom_image_core',
		'supports_try'          => true,
		'try_per_slot_key'      => true,
		'try_per_slot_use'      => true,
		'try_use_no_key_values' => array( 'featured' ),
		'is_image'              => true,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'                => 'datetime_single',
		'title'              => __( 'Date / Time', 'generateblocks' ),
		'supports_link_wrap' => true,
		'leading_options'    => function_exists( 'bws_get_datetime_single_leading_options' )
			? bws_get_datetime_single_leading_options()
			: array(),
		'options'         => function_exists( 'bws_get_datetime_single_template_options' )
			? bws_get_datetime_single_template_options()
			: array(),
		'term_fn'      => static function ( $term_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_options' )
				? bws_base_map_datetime_options( $opts )
				: $opts;
			return bws_term_datetime_single_core( $term_id, $mapped, $inst );
		},
		'post_fn'      => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_options' )
				? bws_base_map_datetime_options( $opts )
				: $opts;
			return bws_datetime_single_core( $post_id, $mapped, $inst );
		},
		'try_core_fn'  => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_options' )
				? bws_base_map_datetime_options( $opts )
				: $opts;
			return bws_datetime_single_core( $post_id, $mapped, $inst );
		},
		'try_term_fn'  => static function ( $term_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_options' )
				? bws_base_map_datetime_options( $opts )
				: $opts;
			return bws_term_datetime_single_core( $term_id, $mapped, $inst );
		},
		'supports_try' => true,
		'is_image'     => false,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'                => 'datetime_range',
		'title'              => __( 'Date / Time Range', 'generateblocks' ),
		'supports_link_wrap' => true,
		'leading_options'    => function_exists( 'bws_get_datetime_range_leading_options' )
			? bws_get_datetime_range_leading_options()
			: array(),
		'options'         => function_exists( 'bws_get_datetime_range_template_options' )
			? bws_get_datetime_range_template_options()
			: array(),
		'term_fn'      => static function ( $term_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_range_options' )
				? bws_base_map_datetime_range_options( $opts )
				: $opts;
			return bws_term_datetime_range_core( $term_id, $mapped, $inst );
		},
		'post_fn'      => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_range_options' )
				? bws_base_map_datetime_range_options( $opts )
				: $opts;
			return bws_datetime_range_core( $post_id, $mapped, $inst );
		},
		'try_core_fn'  => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_range_options' )
				? bws_base_map_datetime_range_options( $opts )
				: $opts;
			return bws_datetime_range_core( $post_id, $mapped, $inst );
		},
		'try_term_fn'  => static function ( $term_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_base_map_datetime_range_options' )
				? bws_base_map_datetime_range_options( $opts )
				: $opts;
			return bws_term_datetime_range_core( $term_id, $mapped, $inst );
		},
		'supports_try' => true,
		'is_image'     => false,
	) );

	// =========================================================
	// Generate term_ modifier tags (term_text, term_image, etc.)
	// =========================================================

	TagTemplateRegistry::register_modifier( array(
		'prefix'               => 'term',
		'gb_type'              => 'term',
		'modifier_label'       => 'term-based',
		'traversal_source_key' => 'term_related_post',
		'base_source_key'      => 'term',
		'excluded_supports'    => array(),
	) );
}

// ===============================================
// SOURCE OPTION + TRAVERSAL SUB-OPTIONS
// ===============================================

/**
 * Build the source dropdown option definition.
 *
 * Uses option key 'src' (not 'source') because GB's DynamicTagSelect
 * unconditionally destructures 'source' from parsed tag params before
 * spreading into extraTagParams, so any option named 'source' is silently
 * eaten and never reaches the editor controls.
 *
 * @since 1.6.0
 * @return array Single-entry array keyed 'src'.
 */
function bws_base_source_option(): array {
	return array(
		'src' => array(
			'type'           => 'select',
			'label'          => __( 'Source', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'current', 'label' => __( 'Current', 'generateblocks' ) ),
				array( 'value' => 'ref',     'label' => __( 'In Reference/Relational Field', 'generateblocks' ) ),
				array( 'value' => 'site',    'label' => __( 'Site', 'generateblocks' ) ),
			),
			'_strip_default' => true,
		),
	);
}

/**
 * Build traversal sub-option definitions for the source dispatch.
 *
 * `ref` — shown when src:ref; the relationship field key for the hop.
 * `srcTermIn` — combined control (checkbox + taxonomy ComboboxControl); when a
 *               taxonomy slug is selected, the resolved entity's taxonomy term
 *               is used as the final entity instead of the post itself. Empty =
 *               disabled. Custom JS control (`bws-term-hop`) ensures non-GB-reserved
 *               serialization. Replaces the prior `srcTerm` + `tax` pair.
 *
 * @since 1.6.0
 * @return array Option definitions keyed by option name.
 */
function bws_base_traversal_options(): array {
	return array(
		'ref'     => array(
			'type'        => 'text',
			'label'       => __( 'Relationship Field', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key.', 'generateblocks' ),
			'placeholder' => 'related_posts',
			// src:ref only. src:site suppressed in Stage A — no site→ref wiring yet
			// (not "never applies"; re-expose when a site→ref path ships).
			'show_if'     => array( 'src' => 'ref' ),
		),
		'srcTermIn' => array(
			'type'      => 'bws-term-hop',
			'label'     => __( 'Get from taxonomy term?', 'generateblocks' ),
			'help'      => __( 'Field is in a taxonomy term on this source.', 'generateblocks' ),
			'pickLabel' => __( 'Taxonomy', 'generateblocks' ),
			'pickHelp'  => __( 'Pick the taxonomy.', 'generateblocks' ),
			// Hidden for src:site — no entity to hop terms from. (Term-context tags
			// override this to src:ref in the template registry.)
			'show_if'   => array( 'src' => 'not:site' ),
		),
	);
}

// ===============================================
// SOURCE DISPATCH
// ===============================================

/**
 * Resolve the target post ID from the `src` option.
 *
 * Reads $options['src'] (falls back to $options['source'] for content
 * migrated before the source→src rename) and dispatches to the appropriate
 * source class. `ref` maps the base-tag option key 'ref' to the internal
 * key 'rel' that RelatedPost::resolve_id() expects.
 *
 * @since 1.6.0
 * @param array  $options  Tag options from GenerateBlocks.
 * @param object $instance Block instance.
 * @return int|false Resolved post ID, or false if unresolvable.
 */
function bws_resolve_post_by_source( array $options, $instance ) {
	$src = $options['src'] ?? $options['source'] ?? 'current';
	if ( '' === $src ) {
		$src = 'current';
	}
	$ref = $options['ref'] ?? '';

	$loop = bws_get_loop_row_context( $instance );

	if ( 'ref' === $src ) {
		// Mode 2b: flat repeater row, no row post entity → ref field is a key in the row.
		if ( $loop['in_loop'] && ! $loop['row_post_id'] && is_array( $loop['loop_item'] ) ) {
			return bws_extract_post_id( $loop['loop_item'][ $ref ] ?? null );
		}

		// Mode 2a: resolve ref on row post entity directly.
		if ( $loop['row_post_id'] ) {
			return bws_extract_post_id( get_post_meta( $loop['row_post_id'], $ref, true ) );
		}

		$source = SourceRegistry::get_source( 'related_post' );
		if ( ! $source ) {
			return false;
		}
		$mapped        = $options;
		$mapped['rel'] = $ref;
		return $source->resolve_id( $mapped, $instance );
	}

	// src:'current' (current entity)
	if ( $loop['row_post_id'] ) {
		return $loop['row_post_id']; // Mode 2a: row entity is the post.
	}
	if ( $loop['in_loop'] && ! isset( $options['id'] ) ) {
		return false; // Mode 2b with src:'current' — no post ID for a flat row.
	}

	$source = SourceRegistry::get_source( 'post' );
	return $source ? $source->resolve_id( $options, $instance ) : false;
}

/**
 * Get taxonomy terms for a resolved post via the `srcTerm`/`tax` options.
 *
 * Called by base tag callbacks when `srcTerm` is set. The post is already
 * resolved via bws_resolve_post_by_source(); this function performs the
 * final hop from that post to its taxonomy terms.
 *
 * @since 1.6.0
 * @param int    $post_id Resolved post ID.
 * @param string $tax     Taxonomy slug from $options['tax'].
 * @return WP_Term[]
 */
function bws_get_srcterm_terms( int $post_id, string $tax ): array {
	if ( ! $post_id || '' === $tax ) {
		return [];
	}

	$terms = get_the_terms( $post_id, $tax );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return [];
	}

	return array_values( $terms );
}

// ===============================================
// SHARED OPTION HELPER
// ===============================================

/**
 * Remap base-tag option keys to what the old core functions expect.
 *
 * Base tags use the new naming convention (fallback vs. fallback_text).
 * Existing core functions still read the old keys. This function bridges
 * the gap without requiring changes to the core functions.
 *
 * @since 1.6.0
 * @param array $options Raw tag options from GenerateBlocks.
 * @return array Options with fallback_text populated from fallback when present.
 */
function bws_base_map_options( array $options ): array {
	if ( isset( $options['fallback'] ) && ! isset( $options['fallback_text'] ) ) {
		$options['fallback_text'] = $options['fallback'];
	}
	return $options;
}

// ===============================================
// CALLBACKS
// ===============================================

/**
 * Callback for the `text` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * dispatches to the appropriate core function based on `use`:
 *
 * srcTerm + use unset   → bws_term_custom_text_core() (per-term; limit/sep applied)
 * srcTerm + use:title   → bws_term_title_core()        (per-term; limit/sep applied)
 * post    + use unset   → bws_post_custom_text_core()
 * post    + use:title   → bws_post_title_core()
 *
 * @since 1.6.0
 */
function bws_base_text_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$use     = $options['use'] ?? 'key';
	$tax     = sanitize_key( $options['srcTermIn'] ?? '' );
	$opts    = bws_base_map_options( $options );
	$link_to = $options['linkTo'] ?? 'none';
	$link_key = $options['linkKey'] ?? '';
	$new_tab  = ! empty( $options['newTab'] );

	// src:site — no entity; resolve site value then link-wrap (sentinel id, 'site' type).
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		$value = bws_site_resolve_value( 'text', $options, $instance );
		if ( '' !== $value && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, 1, 'site' );
		}
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'text' ) : '';
	}

	$post_id = bws_resolve_post_by_source( $options, $instance );

	$link_id   = 0;
	$link_type = 'post';

	if ( '' !== $tax ) {
		$terms  = bws_get_srcterm_terms( (int) $post_id, $tax );
		$limit  = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep    = $options['sep'] ?? ', ';
		$out    = [];
		$first_term_id = 0;
		foreach ( array_slice( $terms, 0, $limit ) as $term ) {
			$result = 'title' === $use
				? bws_term_title_core( $term->term_id, $opts, $instance )
				: bws_term_custom_text_core( $term->term_id, $opts, $instance );
			if ( '' !== $result ) {
				$out[] = $result;
				if ( ! $first_term_id ) {
					$first_term_id = $term->term_id;
				}
			}
		}
		$value = implode( $sep, $out );
		// Only wrap single-result output — multi-result list is unwrappable as one link.
		if ( 1 === count( $out ) && $first_term_id ) {
			$link_id   = $first_term_id;
			$link_type = 'term';
		}
	} elseif ( 'title' === $use ) {
		$value     = bws_post_title_core( $post_id, $opts, $instance );
		$link_id   = (int) $post_id;
		$link_type = 'post';
	} else {
		$value     = bws_post_custom_text_core( $post_id, $opts, $instance );
		$link_id   = (int) $post_id;
		$link_type = 'post';
	}

	if ( '' !== $value ) {
		if ( $link_id && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $link_id, $link_type );
		}
		return $value;
	}

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'text' ) : '';
}

/**
 * Callback for the `content` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * dispatches based on `use`:
 *
 * srcTerm + use unset   → bws_term_description_core() (first non-empty term)
 * srcTerm + use:key     → bws_term_custom_text_core()  (term WYSIWYG field)
 * post    + use unset   → bws_post_content_core()
 * post    + use:excerpt → bws_post_excerpt_core()
 * post    + use:key     → bws_post_content_core() with type:custom_field
 *
 * @since 1.6.0
 */
function bws_base_content_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$use  = $options['use'] ?? 'content';
	$tax  = sanitize_key( $options['srcTermIn'] ?? '' );
	$opts = bws_base_map_options( $options );

	// src:site — content option markup via shared pipeline (handled in resolver). No link wrap.
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		$value = bws_site_resolve_value( 'content', $options, $instance );
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'content' ) : '';
	}

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( '' !== $tax ) {
		$terms = bws_get_srcterm_terms( (int) $post_id, $tax );
		foreach ( $terms as $term ) {
			$result = 'key' === $use
				? bws_term_custom_text_core( $term->term_id, $opts, $instance )
				: bws_term_description_core( $term->term_id, $opts, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		$value = '';
	} elseif ( 'excerpt' === $use ) {
		$value = bws_post_excerpt_core( $post_id, $opts, $instance );
	} elseif ( 'key' === $use ) {
		$opts['type'] = 'custom_field';
		$value = bws_post_content_core( $post_id, $opts, $instance );
	} else {
		$value = bws_post_content_core( $post_id, $opts, $instance );
	}

	if ( '' !== $value ) {
		return $value;
	}

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'content' ) : '';
}

/**
 * Callback for the `title` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set.
 * srcTerm iterates terms with limit/sep applied.
 *
 * @since 1.6.0
 */
function bws_base_title_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$tax      = sanitize_key( $options['srcTermIn'] ?? '' );
	$link_to  = $options['linkTo'] ?? 'none';
	$link_key = $options['linkKey'] ?? '';
	$new_tab  = ! empty( $options['newTab'] );

	// src:site — title base tag has no `use`; resolver returns site name. Link-wrap.
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		$value = bws_site_resolve_value( 'title', $options, $instance );
		if ( '' !== $value && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, 1, 'site' );
		}
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'title' ) : '';
	}

	$post_id = bws_resolve_post_by_source( $options, $instance );

	$link_id   = 0;
	$link_type = 'post';

	if ( '' !== $tax ) {
		$terms  = bws_get_srcterm_terms( (int) $post_id, $tax );
		$limit  = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep    = $options['sep'] ?? ', ';
		$out    = [];
		$first_term_id = 0;
		foreach ( array_slice( $terms, 0, $limit ) as $term ) {
			$result = bws_term_title_core( $term->term_id, $options, $instance );
			if ( '' !== $result ) {
				$out[] = $result;
				if ( ! $first_term_id ) {
					$first_term_id = $term->term_id;
				}
			}
		}
		$value = implode( $sep, $out );
		if ( 1 === count( $out ) && $first_term_id ) {
			$link_id   = $first_term_id;
			$link_type = 'term';
		}
	} else {
		$value     = bws_post_title_core( $post_id, $options, $instance );
		$link_id   = (int) $post_id;
		$link_type = 'post';
	}

	if ( '' !== $value ) {
		if ( $link_id && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $link_id, $link_type );
		}
		return $value;
	}

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'title' ) : '';
}

/**
 * Callback for the `permalink` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set.
 * srcTerm returns first non-empty term URL.
 *
 * @since 1.6.0
 */
function bws_base_permalink_callback( $options, $block, $instance ): string {
	$tax = sanitize_key( $options['srcTermIn'] ?? '' );

	// src:site — site_url/home_url/option via resolver. No link wrap (permalink not link-eligible).
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		return bws_site_resolve_value( 'permalink', $options, $instance );
	}

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( '' !== $tax ) {
		$terms = bws_get_srcterm_terms( (int) $post_id, $tax );
		foreach ( $terms as $term ) {
			$result = bws_term_permalink_core( $term->term_id, $options, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	return bws_post_permalink_core( $post_id, $options, $instance );
}

/**
 * Callback for the `image` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * dispatches based on `use`:
 *
 * srcTerm              → bws_term_custom_image_core() (first non-empty term)
 * post + use unset     → bws_custom_image_core()
 * post + use:featured  → bws_featured_image_core()
 *
 * `use:featured` is hidden in the editor when srcTerm is set (terms have no
 * featured image), so that branch is unreachable in normal usage.
 *
 * @since 1.6.0
 */
function bws_base_image_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$use = $options['use'] ?? 'key';
	$tax = sanitize_key( $options['srcTermIn'] ?? '' );

	// src:site — logo/option via resolver (logo already routed through GB ::output()).
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		$value = bws_site_resolve_value( 'image', $options, $instance );
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'image' ) : '';
	}

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( '' !== $tax ) {
		$terms = bws_get_srcterm_terms( (int) $post_id, $tax );
		foreach ( $terms as $term ) {
			$result = bws_term_custom_image_core( $term->term_id, $options, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		$value = '';
	} elseif ( 'featured' === $use ) {
		$value = bws_featured_image_core( $post_id, $options, $instance );
	} else {
		$value = bws_custom_image_core( $post_id, $options, $instance );
	}

	if ( '' !== $value ) {
		return $value;
	}

	// bws_build_preview_label returns '' for as:url and as:id — attribute contexts where a bracket string breaks the element.
	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'image' ) : '';
}

// ===============================================
// SITE SOURCE (src:site) — Stage A
// ===============================================

/**
 * Allowlist gate for site option reads.
 *
 * @invariant Every site option read (use:option, site linkTo:key, datetime
 * get_field($key,'option')) MUST pass through this gate before the read. The
 * GB Pro `generateblocks_dynamic_tags_allowed_options` filter is seeded EMPTY
 * here — a key reads only when a site developer opts it in. GenerateBlocks_Meta_Handler
 * does NOT enforce this filter (blocklist only); calling it directly skips the
 * allowlist, so gating is OUR responsibility, never the handler's.
 * See docs/adr/0001-site-option-read-allowlist.md.
 *
 * Dot-path keys (wp_options): gate the FIRST segment (the actual option name).
 * Flat keys (ACF field keys): the whole $key is the first segment — same call.
 *
 * @since 1.9.0
 * @param string $key Option key (may contain dot-path for wp_options).
 * @return bool True if the option's root key is allowed.
 */
function bws_site_allowlist_ok( string $key ): bool {
	if ( '' === $key ) {
		return false;
	}
	$allowed = apply_filters( 'generateblocks_dynamic_tags_allowed_options', array() );
	$parent  = explode( '.', $key )[0];
	return in_array( $parent, $allowed, true );
}

/**
 * Resolve a site-wide value for src:site (non-datetime tags only).
 *
 * Used by the text/title/permalink/image/content callbacks' early gate. Site
 * has no entity ID, so this bypasses bws_resolve_post_by_source() entirely.
 * Datetime tags do NOT route here — they read ACF options-page fields via
 * bws_datetime_single_core('option', ...) (see datetime callbacks).
 *
 * Dispatch by `use`. The title base tag has no `use` enum, so $tag==='title'
 * (or use:title) maps to the site name.
 *
 * @invariant Option reads (use:option) MUST pass bws_site_allowlist_ok() before
 * GenerateBlocks_Meta_Handler::get_option(). Empty seed = closed by default.
 * See docs/adr/0001-site-option-read-allowlist.md.
 *
 * @since 1.9.0
 * @param string $tag      Base tag name: text|title|permalink|image|content.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
 * @return string Resolved value, or '' on miss / disallowed.
 */
function bws_site_resolve_value( string $tag, array $options, $instance ): string {
	$use = $options['use'] ?? '';

	// title base tag carries no `use` enum → site name.
	if ( 'title' === $tag && '' === $use ) {
		$use = 'title';
	}

	switch ( $use ) {
		case 'tagline':
			return (string) get_bloginfo( 'description' );

		case 'title':
			return (string) get_bloginfo( 'name' );

		case 'site_url':
			return (string) site_url();

		case 'home_url':
			return (string) home_url();

		case 'logo':
			$logo_id = (int) get_theme_mod( 'custom_logo' );
			if ( ! $logo_id || ! function_exists( 'bws_get_attachment_data' ) ) {
				return '';
			}
			$result = bws_get_attachment_data(
				$logo_id,
				$options['as'] ?? 'url',
				$options['size'] ?? 'full'
			);
			if ( empty( $result ) ) {
				return '';
			}
			// Route through GB output for fallback/markup parity with image tag.
			return class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' )
				? (string) GenerateBlocks_Dynamic_Tag_Callbacks::output( $result, $options, $instance )
				: (string) $result;

		case 'option':
			$key = (string) ( $options['key'] ?? '' );
			if ( ! bws_site_allowlist_ok( $key ) || ! class_exists( 'GenerateBlocks_Meta_Handler' ) ) {
				return '';
			}
			$raw = GenerateBlocks_Meta_Handler::get_option( $key, true, '' );
			// content tag: route block/HTML option markup through the shared content
			// pipeline (do_blocks + sanitize + recursion guard), keyed 'option:KEY'.
			if ( 'content' === $tag && function_exists( 'bws_render_block_content' ) ) {
				return (string) bws_render_block_content( (string) $raw, 'option:' . $key );
			}
			return (string) $raw;
	}

	return '';
}

// ===============================================
// TRY DISPATCH WRAPPERS
// ===============================================

/**
 * Try-tag post-slot dispatch for `text` template.
 *
 * Reads $options['use'] to route between title-mode and custom-field-mode.
 * Used as `try_core_fn` so each try slot dispatches by its slot-resolved use value.
 *
 * @since 1.6.0
 */
function bws_try_text_post_dispatch( $post_id, $options, $instance ) {
	$use = $options['use'] ?? 'key';
	if ( 'title' === $use ) {
		return bws_post_title_core( $post_id, $options, $instance );
	}
	return bws_post_custom_text_core( $post_id, $options, $instance );
}

/**
 * Try-tag srcTermIn-slot dispatch for `text` template.
 *
 * @since 1.6.0
 */
function bws_try_text_term_dispatch( $term_id, $options, $instance ) {
	$use = $options['use'] ?? 'key';
	if ( 'title' === $use ) {
		return bws_term_title_core( $term_id, $options, $instance );
	}
	return bws_term_custom_text_core( $term_id, $options, $instance );
}

/**
 * Try-tag post-slot dispatch for `content` template.
 *
 * Reads $options['use'] to route between content/excerpt/key modes.
 *
 * @since 1.6.0
 */
function bws_try_content_post_dispatch( $post_id, $options, $instance ) {
	$use  = $options['use'] ?? 'content';
	$opts = bws_base_map_options( $options );
	if ( 'excerpt' === $use ) {
		return bws_post_excerpt_core( $post_id, $opts, $instance );
	}
	if ( 'key' === $use ) {
		$opts['type'] = 'custom_field';
		return bws_post_content_core( $post_id, $opts, $instance );
	}
	return bws_post_content_core( $post_id, $opts, $instance );
}

/**
 * Try-tag srcTermIn-slot dispatch for `content` template.
 *
 * @since 1.6.0
 */
function bws_try_content_term_dispatch( $term_id, $options, $instance ) {
	$use  = $options['use'] ?? 'content';
	$opts = bws_base_map_options( $options );
	if ( 'key' === $use ) {
		return bws_term_custom_text_core( $term_id, $opts, $instance );
	}
	// content (default) and excerpt both fall back to term description on terms.
	return bws_term_description_core( $term_id, $opts, $instance );
}

/**
 * Try-tag post-slot dispatch for `image` template.
 *
 * Reads $options['use'] to route between featured-image and custom-field modes.
 *
 * @since 1.6.0
 */
function bws_try_image_post_dispatch( $post_id, $options, $instance ) {
	$use = $options['use'] ?? 'key';
	if ( 'featured' === $use ) {
		return bws_featured_image_core( $post_id, $options, $instance );
	}
	return bws_custom_image_core( $post_id, $options, $instance );
}
