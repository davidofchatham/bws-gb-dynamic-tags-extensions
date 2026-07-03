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
						array( 'value' => 'key',   'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
						array( 'value' => 'title', 'label' => __( 'Title/Name', 'generateblocks' ) ),
					),
					'_strip_default' => true,
				),
				'key'      => array(
					'type'         => 'bws-field-combo',
					'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
					'dynamicLabel' => true,
					'help'         => __( 'ACF or meta field key. For src:site this is the wp_options / ACF-options key (supports dot-path).', 'generateblocks' ),
					'placeholder'  => 'field_name',
					// Key-mode = empty/'key'. Hidden for named data (title).
					// Under src:site, key-mode reads a wp_options key. Site tagline has
					// NO tag path (B7): GB native {{site_tagline}} or key:blogdescription
					// (nothing unique to add until multislot-feed decouple — see #26).
					'show_if'      => array( 'use' => 'not:title' ),
				),
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
			),
			function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array(),
			array(
				// List mode only applies to the final traversal step: terms (srcTermIn set)
				// or related posts (src:ref). Scalar sources return one value — hide both.
				'limit'    => array(
					'type'        => 'number',
					'label'       => __( 'Result Limit', 'generateblocks' ),
					'help'        => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
				),
				'sep'      => array(
					'type'        => 'text',
					'label'       => __( 'Result Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
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
						array( 'value' => 'key',     'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
						array( 'value' => 'excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ),
					),
					'_strip_default' => true,
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Meta/Option Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key (post/term), or a wp_options / ACF-options key under src:site (supports dot-path). A WYSIWYG / Blocks field renders through the content pipeline (shortcodes + blocks execute).', 'generateblocks' ),
					'placeholder' => 'field_name',
					// Key-mode only (use:key). Under src:site, use:key reads a wp_options
					// value (rich render); use:content default → '' (site has no content
					// analog — B7; tagline has no tag path, use GB {{site_tagline}}).
					'show_if'     => array(
						'use' => 'key',
					),
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
		'title'    => __( 'Title/Name', 'generateblocks' ),
		'tag'      => 'title',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				// List mode only applies to the final traversal step: terms (srcTermIn set)
				// or related posts (src:ref). Scalar sources return one value — hide both.
				'limit' => array(
					'type'        => 'number',
					'label'       => __( 'Limit', 'generateblocks' ),
					'help'        => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
				),
				'sep'   => array(
					'type'        => 'text',
					'label'       => __( 'Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
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
		// No `key` control under src:site — permalink is the source entity's own URL,
		// never an arbitrary option read. Bare {{permalink src:site}} → home_url()
		// (V9 narrowed: URL-valued options reachable via {{text src:site|key:...}}).
		'options'  => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts
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
						array( 'value' => 'key',      'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
						array( 'value' => 'featured', 'label' => __( 'Featured Image/Site Logo', 'generateblocks' ) ),
					),
					'show_if'        => array( 'srcTermIn' => 'empty' ),
					'_strip_default' => true,
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Meta/Option Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key for the image. For src:site, the wp_options / ACF-options key storing an attachment ID (use:key); the Featured Image option reads the site logo.', 'generateblocks' ),
					'placeholder' => 'image_field',
					// use:key → custom-field (post/term) or wp_options (site) read.
					// Hidden for use:featured, which under src:site → site logo (V9, resolver).
					'show_if'     => array( 'use' => 'not:featured' ),
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
		'title'    => __( 'Date/Time', 'generateblocks' ),
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
		'title'    => __( 'Date/Time Range', 'generateblocks' ),
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
					array( 'value' => 'key',   'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
					array( 'value' => 'title', 'label' => __( 'Title/Name', 'generateblocks' ) ),
				),
				'_strip_default' => true,
			),
			'key'      => array(
				'type'        => 'text',
				'label'       => __( 'Meta/Option Field Key', 'generateblocks' ),
				'help'        => __( 'ACF or meta field key (post/term), or a wp_options / ACF-options key under src:site.', 'generateblocks' ),
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
		'try_list_options'      => true,
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
					array( 'value' => 'key',     'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
					array( 'value' => 'excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ),
				),
				'_strip_default' => true,
			),
			'key'      => array(
				'type'        => 'text',
				'label'       => __( 'Meta/Option Field Key', 'generateblocks' ),
				'help'        => __( 'ACF or meta field key (post/term), or a wp_options / ACF-options key under src:site. A WYSIWYG / Blocks field is rendered through the content pipeline (shortcodes + blocks execute).', 'generateblocks' ),
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
		'title'              => __( 'Title/Name', 'generateblocks' ),
		'supports_link_wrap' => true,
		'options'            => array(),
		'term_fn'      => 'bws_term_title_core',
		'post_fn'      => 'bws_post_title_core',
		'try_core_fn'  => 'bws_post_title_core',
		'try_term_fn'  => 'bws_term_title_core',
		'supports_try' => true,
		'try_list_options' => true,
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
					array( 'value' => 'key',      'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
					array( 'value' => 'featured', 'label' => __( 'Featured Image/Site Logo', 'generateblocks' ) ),
				),
				'_strip_default' => true,
			),
			'key'      => array(
				'type'        => 'text',
				'label'       => __( 'Meta/Option Field Key', 'generateblocks' ),
				'help'        => __( 'ACF or meta field key for the image (post/term), or a wp_options / ACF-options key storing an attachment ID under src:site.', 'generateblocks' ),
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
		'title'              => __( 'Date/Time', 'generateblocks' ),
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
		'title'              => __( 'Date/Time Range', 'generateblocks' ),
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

	// Register the email/phone modifier TEMPLATES (descriptors) before the term_
	// modifier pass + try_ generation, so term_email/term_phone and try_email/
	// try_phone fall out of the shared machinery. The standalone {{email}}/{{phone}}
	// GB tags register separately (bws_register_email_tag/_phone_tag). [SPEC §32]
	if ( function_exists( 'bws_register_email_template' ) ) {
		bws_register_email_template();
	}
	if ( function_exists( 'bws_register_phone_template' ) ) {
		bws_register_phone_template();
	}

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
 * Filter `site` out of a source-option definition.
 *
 * A rooting modifier (`term_*`, `view_*`) exists to surface ENTITY-DISTINCT data;
 * a site read is entity-blind, so offering `site` there merely duplicates the
 * unrooted base tag (`{{email src:site}}`) while discarding the rooting — it fails
 * the qualifying gate on both arms (CONTEXT.md I4 source-level application;
 * tag-reference.md §Qualifying test). register_modifier() routes its source dropdown
 * through this before injecting it into every term_/view_ tag.
 *
 * Mirrors the slot-side filter in bws_build_slot_traversal_options() (which omits
 * `site` from derived try_ slot src unless a template opts back in via
 * try_allow_site_slot). A future "pinned-resource + site fallback" belongs in a
 * try_ chain slot, NOT a single-slot rooting modifier. See [#37].
 *
 * @since 1.11.0
 * @param array $source_opt A bws_base_source_option()-shaped array (key 'src').
 * @return array Same shape with the `site` value removed from src options.
 */
function bws_filter_site_from_src( array $source_opt ): array {
	if ( isset( $source_opt['src']['options'] ) && is_array( $source_opt['src']['options'] ) ) {
		$source_opt['src']['options'] = array_values( array_filter(
			$source_opt['src']['options'],
			static function ( $opt ) {
				return 'site' !== ( $opt['value'] ?? '' );
			}
		) );
	}
	return $source_opt;
}

/**
 * Keep ONLY the named source values in a src-option definition (allowlist).
 *
 * The complement of bws_filter_site_from_src() (a blocklist that drops `site`).
 * Use the BLOCKLIST when a tag wants "every base source except X" (term_/view_
 * rooting modifiers, generic try_ slots — they SHOULD inherit a future base
 * source). Use this ALLOWLIST when a tag has a CLOSED source set and must NOT
 * inherit new base values by default — e.g. `{{call}}` offers `current`/`ref`
 * only (both post-yielding; a `$post_id` function can't consume a non-post
 * source), so a future non-post base value must be excluded automatically, not
 * leaked. Pulling the rows from bws_base_source_option() keeps the labels /
 * `_strip_default` canonical instead of hand-copied.
 *
 * Order follows $keep (so the menu order is the caller's, not base's). A $keep
 * value with no matching base row is silently skipped.
 *
 * @since 1.12.0
 * @param array    $source_opt A bws_base_source_option()-shaped array (key 'src').
 * @param string[] $keep       Source values to retain, in display order.
 * @return array Same shape with src options reduced + reordered to $keep.
 */
function bws_pick_src_values( array $source_opt, array $keep ): array {
	if ( ! isset( $source_opt['src']['options'] ) || ! is_array( $source_opt['src']['options'] ) ) {
		return $source_opt;
	}
	$by_value = array();
	foreach ( $source_opt['src']['options'] as $opt ) {
		$by_value[ $opt['value'] ?? '' ] = $opt;
	}
	$picked = array();
	foreach ( $keep as $value ) {
		if ( isset( $by_value[ $value ] ) ) {
			$picked[] = $by_value[ $value ];
		}
	}
	$source_opt['src']['options'] = $picked;
	return $source_opt;
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
			'label'       => __( 'Relationship Field Key', 'generateblocks' ),
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

/**
 * Re-qualify a base option's `show_if` condition keys for a numbered try_ slot.
 *
 * Base traversal options carry bare sibling-key conditions (e.g. `ref` shows when
 * `['src' => 'ref']`). In a try_ slot ≥2 those sibling keys are ordinal-prefixed
 * (`{N}-src`), so the condition key must follow: `src` → `2-src`. Slot 1 keeps the
 * bare key (no prefix). Only keys present in $sibling_keys are rewritten; any other
 * condition key (e.g. a cross-option reference) is left untouched. Condition VALUES
 * (`'ref'`, `'not:site'`) are never altered.
 *
 * Pure array transform — no WP/GB symbols. Locally harnessable
 * (tools/test/slot-qualify-show-if-test.php). [SPEC §26 V2, V8]
 *
 * @since 1.11.0
 * @param array $show_if      Condition map { key => value }. Empty → empty out.
 * @param int   $n            Slot ordinal (1-based). Slot 1 = bare keys.
 * @param array $sibling_keys Keys eligible for `{N}-` prefixing (e.g. ['src','ref','srcTermIn']).
 * @return array Re-keyed condition map, values unchanged.
 */
function bws_slot_qualify_show_if( array $show_if, int $n, array $sibling_keys ): array {
	if ( $n <= 1 || empty( $show_if ) ) {
		return $show_if;
	}
	$out = array();
	foreach ( $show_if as $key => $value ) {
		$qualified         = in_array( $key, $sibling_keys, true ) ? "{$n}-{$key}" : $key;
		$out[ $qualified ] = $value;
	}
	return $out;
}

/**
 * Normalize a try_ slot dispatch return into a list of finished item strings.
 *
 * The try_ machinery is composition-blind (CONTEXT.md I6): a slot's dispatch
 * returns either ONE finished string (today's text/content/image/email/phone
 * single-result path) or an array of finished per-item strings (a slot in list
 * mode — e.g. a srcTermIn term-hop, or the shared L1/L2 resolver's plural
 * `src:ref`). This helper collapses both to a list, dropping empty items, so the
 * machinery can join uniformly without caring which producer it is.
 *
 * The array contract lives at the resolver/L2 layer (ADR 0002), NOT retrofitted
 * into every dispatcher: shipped dispatchers keep returning a single string and
 * still flow through here as a 1-element list. [SPEC §32 V2,V6]
 *
 * Pure — no WP/GB symbols. Locally harnessable (tools/test/try-join-seam-test.php).
 *
 * @since 1.11.0
 * @param mixed $raw Dispatch return: string | array<string> | '' | false.
 * @return array<int,string> Finished item strings, empties removed, re-indexed.
 */
function bws_try_normalize_items( $raw ): array {
	if ( '' === $raw || false === $raw || null === $raw ) {
		return array();
	}
	$items = is_array( $raw ) ? $raw : array( $raw );
	$out   = array();
	foreach ( $items as $item ) {
		if ( '' !== $item && false !== $item && null !== $item ) {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * Join a winning try_ slot's finished item strings — the ONLY composition the
 * try_ machinery itself performs (CONTEXT.md I6).
 *
 * Limit / separator semantics MATCH the base text list-mode core
 * (bws_post_custom_text_core, base-tags.php:884) so a try_ slot in list mode
 * joins identically to the same underlying tag used standalone (I6 parity):
 *   - limit = max( 1, (int) $limit ?: 1 ) — DEFAULT 1, floored at 1 (never 0).
 *     Not a ceiling: an author setting limit:5 joins up to 5 items.
 *   - sep   = $sep ?? ', ' — null (absent) → default ', '; an explicit empty
 *     string is honored (matches base `$options['sep'] ?? ', '`, which only
 *     defaults on an absent key — author may deliberately join with no sep).
 *
 * A 1-element list with the default limit returns the single element verbatim
 * (no trailing separator — sep is never applied to a lone item) — the
 * byte-identical backward-compat gate for existing try_text/try_content/try_image.
 * [SPEC §32 V3,V4]
 *
 * Pure — no WP/GB symbols. Locally harnessable (tools/test/try-join-seam-test.php).
 *
 * @since 1.11.0
 * @param array<int,string> $items Finished item strings (already non-empty).
 * @param mixed              $sep   Separator; null → ', '. Explicit '' honored.
 * @param mixed              $limit Max items to join; falsy → 1. Floored at 1.
 * @return string Joined output (or '' if no items).
 */
function bws_try_join_items( array $items, $sep = null, $limit = null ): string {
	if ( empty( $items ) ) {
		return '';
	}
	$max = max( 1, (int) ( $limit ?: 1 ) );
	$s   = ( null === $sep ) ? ', ' : $sep;
	return implode( $s, array_slice( $items, 0, $max ) );
}

/**
 * Build the source + traversal option definitions for one numbered try_ slot,
 * derived from the base builders. Pure fn of (slot ordinal, base option sets) —
 * no WP/GB symbols, no $slot_trigger merge (that visibility layer is the registry's
 * concern, kept separate per V3). Locally harnessable
 * (tools/test/slot-options-build-test.php). [SPEC §26 V1,V2,V5,V6,V9,V10]
 *
 * Derivation rules:
 *   - src: base `src.options`. `site` is filtered out by DEFAULT (V6 wrong-read
 *     guard — the generic try_ slot resolver had no site arm). Per-template
 *     opt-in via $allow_site=true re-allows it (email/phone — once the slot
 *     resolver site arm landed, SPEC §32 V7/V8): site is the canonical contact
 *     fallback slot. Slot ≥2 prepends the `same` (inherit) row. `_strip_default`
 *     preserved (V5). Label overlaid as "N: Source" (V10).
 *   - ref / srcTermIn: base definitions verbatim (label body / placeholder / help
 *     from base — V10), show_if re-qualified via bws_slot_qualify_show_if, label
 *     (and srcTermIn pickLabel) given the "N: " ordinal prefix (V10).
 *
 * @since 1.11.0
 * @param int   $n          Slot ordinal (1-based).
 * @param array $base_src   bws_base_source_option() result.
 * @param array $base_trav  bws_base_traversal_options() result.
 * @param bool  $allow_site When true, keep `site` in the src list (per-template
 *                          opt-in, gated on the resolver site arm). Default false.
 * @return array { 'src' => array, 'ref' => array, 'srcTermIn' => array } — option
 *               definitions WITHOUT $slot_trigger (caller merges show_if_any).
 */
function bws_build_slot_traversal_options( int $n, array $base_src, array $base_trav, bool $allow_site = false ): array {
	$sibling_keys = array( 'src', 'ref', 'srcTermIn' );

	// --- src: filter 'site' unless per-template allowed (V6 guard / V8 opt-in),
	// prepend 'same' for slot ≥2, keep _strip_default (V5). ---
	$base_src_opts = $base_src['src']['options'] ?? array();
	$src_opts      = $allow_site
		? array_values( $base_src_opts )
		: array_values( array_filter(
			$base_src_opts,
			static function ( $o ) {
				return 'site' !== ( $o['value'] ?? '' );
			}
		) );
	if ( $n >= 2 ) {
		array_unshift(
			$src_opts,
			array( 'value' => 'same', 'label' => __( 'Same as Previous Source', 'generateblocks' ) )
		);
	}
	$src_def = array(
		'type'           => 'select',
		/* translators: %d: slot number */
		'label'          => sprintf( __( '%d: Source', 'generateblocks' ), $n ),
		'options'        => $src_opts,
		'_strip_default' => true,
	);

	// --- ref: base def verbatim (V10), show_if re-qualified, "N: " label prefix. ---
	$ref_def          = $base_trav['ref'];
	$ref_def['label'] = sprintf( /* translators: 1: slot number, 2: base label */ '%1$d: %2$s', $n, $base_trav['ref']['label'] );
	if ( isset( $ref_def['show_if'] ) ) {
		$ref_def['show_if'] = bws_slot_qualify_show_if( $ref_def['show_if'], $n, $sibling_keys );
	}

	// --- srcTermIn: base def verbatim (V10), show_if re-qualified, "N: " label + pickLabel prefix. ---
	$stm_def          = $base_trav['srcTermIn'];
	$stm_def['label'] = sprintf( '%1$d: %2$s', $n, $base_trav['srcTermIn']['label'] );
	if ( isset( $stm_def['pickLabel'] ) ) {
		$stm_def['pickLabel'] = sprintf( '%1$d: %2$s', $n, $base_trav['srcTermIn']['pickLabel'] );
	}
	if ( isset( $stm_def['show_if'] ) ) {
		$stm_def['show_if'] = bws_slot_qualify_show_if( $stm_def['show_if'], $n, $sibling_keys );
	}

	return array(
		'src'       => $src_def,
		'ref'       => $ref_def,
		'srcTermIn' => $stm_def,
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
 * @invariant Every site option read (site option key-mode, site linkTo:key,
 * datetime get_field($key,'option')) MUST pass through this gate before the read.
 * GenerateBlocks_Meta_Handler does NOT enforce the allowlist (blocklist only);
 * calling it directly skips the gate, so gating is OUR responsibility, never the
 * handler's. The seed MIRRORS GB Pro's `get_option` callback exactly
 * (class-register.php:268-291): 6 WP defaults PLUS every registered ACF
 * options-page field (registration IS the opt-in — ACF option fields auto-allow,
 * no manual filter needed), then the shared filter. Do NOT revert to an empty
 * seed — that blocks ACF option fields and diverges from GB Pro.
 * See docs/adr/0001-site-option-read-allowlist.md.
 *
 * Dot-path keys (wp_options arrays): gate the FIRST segment (the actual option
 * name). Flat keys (ACF field keys): the whole $key is the first segment.
 *
 * @since 1.9.0
 * @param string $key Option key (may contain dot-path for wp_options).
 * @return bool True if the option's root key is allowed.
 */
function bws_site_allowlist_ok( string $key ): bool {
	if ( '' === $key ) {
		return false;
	}

	// GB Pro's default wp_options allowlist (class-register.php).
	$seed = array(
		'siteurl',
		'blogname',
		'blogdescription',
		'home',
		'time_format',
		'user_count',
	);

	// GB Pro auto-allows every registered ACF options-page field — registration
	// is the opt-in. Mirror that so ACF option fields read without a manual filter.
	if ( class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_ACF' )
		&& method_exists( 'GenerateBlocks_Pro_Dynamic_Tags_ACF', 'get_instance' )
	) {
		$acf = GenerateBlocks_Pro_Dynamic_Tags_ACF::get_instance();
		if ( $acf && method_exists( $acf, 'get_acf_option_fields' ) ) {
			$seed = array_merge( $seed, array_keys( (array) $acf->get_acf_option_fields() ) );
		}
	}

	$allowed = apply_filters( 'generateblocks_dynamic_tags_allowed_options', $seed );
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
 * Dispatch by `use` — UNIFORM with every other source (Model B, V9). The `use`
 * VALUE is the analog-vs-option lever, NOT key-presence; `use:key` resolves a
 * wp_options key read. `src:site` selects the wp_options namespace the same way
 * `src:current` selects post meta. There is NO `use:option` value (option is a
 * key-read reached by `use:key`, not a distinct field type — V8).
 *
 * STRIP-DEFAULT (B6): an EMPTY wire `use` is the tag's FIRST enum value (stripped
 * at registration), NOT a third "no use" state. This function canonicalizes empty
 * → first-enum-value up front (text/image → 'key', content → 'content'), mirroring
 * the per-callback `?? 'key'` / `?? 'content'` defaults. So `{{text src:site|
 * key:blogname}}` (no explicit `use`) reads the option, because text's stripped
 * default IS key-mode.
 *
 * Do NOT branch the analog on `'' === $key` (that was B5 — a misapplied future
 * custom-control principle that made `use` dead under site and rendered an enum of
 * ignored post/term values).
 *
 * @invariant Site option reads (the use:key branch) MUST pass
 * bws_site_allowlist_ok() before GenerateBlocks_Meta_Handler::get_option() (via
 * the canonical bws_site_read_option reader). The allowlist is GB-parity-seeded
 * (NOT empty) — see bws_site_allowlist_ok and
 * docs/adr/0001-site-option-read-allowlist.md.
 *
 * @invariant (V11/B6) Empty wire `use` MUST be canonicalized to the tag's FIRST
 * enum value before dispatch (content → 'content', text/image → 'key'), never
 * treated as a distinct "no use" state. Dispatching on the literal empty string
 * drops the option read for key-mode-default tags (the B6 regression). The
 * stripped default MUST stay key-mode for text/image — the site logo is the
 * EXPLICIT use:featured value, not the implicit-mode tag — so the empty wire is an
 * unambiguous key-mode signal (no stale-key vs intended-analog ambiguity until
 * custom-control token authority exists; see SPEC §B6).
 *
 * Per-tag site dispatch (V9 Model B; default = stripped first enum value):
 *   - title     → site name (get_bloginfo('name'))       [tag has no use enum]
 *   - text      → DEFAULT 'key' → option (key:X); use:title → name; empty key → ''
 *   - content   → no site content analog (B7): DEFAULT 'content' and use:excerpt
 *                 both → ''. Site's only long-text datum is the tagline — a SHORT
 *                 string with no unique value over GB native {{site_tagline}}, so
 *                 no tag path this release. use:key → option (rich render).
 *   - permalink → ALWAYS home_url() (source's own URL; `key` ignored — no option read)
 *   - image     → DEFAULT 'key' → option attachment-id (bare/no-key → ''); the site
 *                 LOGO is the EXPLICIT use:featured value (get_theme_mod('custom_logo'),
 *                 respects as/size). Logo is NOT the stripped default — `featured` is
 *                 always serialized so the empty wire stays an unambiguous key-mode
 *                 signal (no stale-key ambiguity until token authority via custom
 *                 controls; deferred — see SPEC §B6 note).
 * Parallels post→{title,content,permalink,featured} / term→{name,description,URL,—},
 * EXCEPT image's site analog (logo) is reached by explicit use:featured, not bare.
 *
 * @since 1.9.0
 * @param string $tag      Base tag name: text|title|permalink|image|content.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
 * @return string Resolved value, or '' on miss / disallowed.
 */
function bws_site_resolve_value( string $tag, array $options, $instance ): string {
	$key = (string) ( $options['key'] ?? '' );

	// Canonicalize `use` to the tag's stripped default (its FIRST enum value) when
	// the wire value is empty — strip-default means an unset `use` IS the first
	// option, NOT a third "no use" state (B6). Mirrors the per-callback defaults
	// (text/image → 'key', content → 'content'); title/permalink have no enum.
	$use_default = ( 'content' === $tag ) ? 'content' : 'key';
	$use         = (string) ( $options['use'] ?? '' );
	if ( '' === $use ) {
		$use = $use_default;
	}

	// title base tag (no `use` enum) and text use:title → site name.
	if ( 'title' === $tag || 'title' === $use ) {
		return (string) get_bloginfo( 'name' );
	}

	// permalink = the source entity's own URL, never an option read (V9 narrowed).
	// Always home_url(); any `key` is ignored (control suppressed under site too).
	// URL-valued options are reachable via {{text src:site|key:...}}.
	if ( 'permalink' === $tag ) {
		return (string) home_url();
	}

	// use:key → wp_options key read (Model B, V9: `use` is the lever, not key
	// emptiness). The shared gated reader (allowlist + dot-path + ACF filter).
	if ( 'key' === $use ) {
		$raw = bws_site_read_option( $key );
		// content: route block/HTML option markup through the shared content
		// pipeline (do_blocks + sanitize + recursion guard), keyed 'option:KEY'.
		if ( 'content' === $tag && function_exists( 'bws_render_block_content' ) ) {
			return bws_render_block_content( $raw, 'option:' . $key );
		}
		return $raw;
	}

	// Analog `use` tokens (and each tag's empty/default). Dispatch the intrinsic
	// site analog per tag (V9 Model B).
	switch ( $tag ) {
		case 'content':
			// Site has NO content analog (B7): the only site long-text datum is the
			// tagline, which is a SHORT string (not body text) AND has no unique value
			// to add over GB native {{site_tagline}} — so no tag path this release.
			// use:content (default) and use:excerpt both → '' under site. content is
			// only meaningful with use:key (wp_options rich-render, handled above).
			return '';

		case 'image':
			// use:featured (default) → site logo (post→featured parallel).
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

		// text: keyed by nature — empty/bare `use` has no analog default → ''.
		default:
			return '';
	}
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
