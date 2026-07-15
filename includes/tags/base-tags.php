<?php
/**
 * Base (source-agnostic) dynamic tag registrations.
 *
 * Registers one GB tag per content template. The read target (source + key) is
 * selected at render time via the `src`/`ref`/`srcTermIn` options, not at
 * registration time.
 *
 * Registered tags: text, content, title, permalink, image, datetime_single, datetime_range
 *
 * Resolution (since 1.14.0 — the L1-full traversal pipeline, NOT source classes):
 *   L1 base source — `bws_resolve_base_source()` (includes/helpers/traversal-pipeline.php)
 *     resolves the ambient/explicit base resolved source: loop row → ambient term
 *     (term archive) → current post, or an explicit `src:site` / registry source.
 *     `$post` / get_the_ID() is NEVER an ambient fallback (SPEC §V1).
 *   L1 steps — `src:ref` appends a generic `ref` step (ACF relationship hop,
 *     plural), `srcTermIn` a term-hop step; run through `bws_run_traversal()`.
 *   L2 read — dispatched by resolved-source KIND (post → post cores /
 *     bws_read_field, term → term cores / bws_read_term_field, site → option read).
 *
 * The N×M source classes (RelatedPost / TermRelatedPost / SecondRelatedPost /
 * PostTermRelatedPost) NO LONGER resolve base or modifier tags — the factory +
 * ref step subsume them. They stay registered ONLY for the deprecated tag
 * wrappers that still call their resolve_id() (SPEC §C4 / deprecated-tags.php).
 *
 * Term-ambient: on a term archive a bare base tag resolves the TERM analog
 * (title → name, content → description, permalink → term URL; image = honest gap
 * #29), via bws_base_term_analog_read() (SPEC §V7).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 * @since 1.14.0 Resolution moved to the traversal pipeline; source-class dispatch retired for base/modifier tags.
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
					'help'         => __( 'ACF or meta field key.', 'generateblocks' ),
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
					'type'         => 'bws-field-combo',
					'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
					'dynamicLabel' => true,
					'help'         => __( 'ACF or meta field key. A WYSIWYG or blocks field renders through the content pipeline (shortcodes and blocks execute).', 'generateblocks' ),
					'placeholder'  => 'field_name',
					// Key-mode only (use:key). Under src:site, use:key reads a wp_options
					// value (rich render); use:content default → '' (site has no content
					// analog — B7; tagline has no tag path, use GB {{site_tagline}}).
					'show_if'      => array(
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
					'type'         => 'bws-field-combo',
					'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
					'dynamicLabel' => true,
					'help'         => __( 'ACF or meta field key holding an image (attachment ID or URL).', 'generateblocks' ),
					'placeholder'  => 'image_field',
					// use:key → custom-field (post/term) or wp_options (site) read.
					// Hidden for use:featured, which under src:site → site logo (V9, resolver).
					'show_if'      => array( 'use' => 'not:featured' ),
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
		'options'  => bws_strip_default_select_values( bws_get_base_datetime_single_options() ),
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
		'options'  => bws_strip_default_select_values( bws_get_base_datetime_range_options() ),
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
				'type'         => 'bws-field-combo',
				'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
				'dynamicLabel' => true,
				'help'         => __( 'ACF or meta field key.', 'generateblocks' ),
				'placeholder'  => 'field_name',
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
				'type'         => 'bws-field-combo',
				'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
				'dynamicLabel' => true,
				'help'         => __( 'ACF or meta field key. A WYSIWYG or blocks field renders through the content pipeline (shortcodes and blocks execute).', 'generateblocks' ),
				'placeholder'  => 'field_name',
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
				'type'         => 'bws-field-combo',
				'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
				'dynamicLabel' => true,
				'help'         => __( 'ACF or meta field key holding an image (attachment ID or URL).', 'generateblocks' ),
				'placeholder'  => 'image_field',
				'show_if'      => array( 'use' => 'not:featured' ),
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
// CALLBACKS
// ===============================================

/**
 * Resolve the `text` base tag's VALUE — the full read path minus link-wrap
 * and preview fallback.
 *
 * Resolves entity via `source`, applies srcTerm hop when set, then
 * dispatches to the appropriate core function based on `use`:
 *
 * srcTerm + use unset   → bws_term_custom_text_core() (per-term; limit/sep applied)
 * srcTerm + use:title   → bws_term_title_core()        (per-term; limit/sep applied)
 * post    + use unset   → bws_post_custom_text_core()
 * post    + use:title   → bws_post_title_core()
 *
 * ABSORB INVARIANT: the returned value must stay byte-equivalent to what
 * {{text}} renders before link-wrap — including the src:site arm, the
 * srcTermIn / src:ref list modes (text's own sep/limit), and '0' preservation
 * (hooks.php maps '0' downstream; no emptiness re-decision here). Other tags
 * absorb the text read through this seam (planned: {{join}} per-slot resolve),
 * so any text read change lands here, never in a caller's copy.
 *
 * @since 1.14.1 Extracted from bws_base_text_callback().
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string} link_id 0 =
 *                        multi-result output; caller must not link-wrap.
 */
function bws_base_text_resolve_value( array $options, $instance ): array {
	$use  = $options['use'] ?? 'key';
	$tax  = sanitize_key( $options['srcTermIn'] ?? '' );
	$opts = bws_base_map_options( $options );

	// src:site — no entity; site value with sentinel link identity (id 1, 'site' type).
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		return array(
			'value'     => bws_site_resolve_value( 'text', $options, $instance ),
			'link_id'   => 1,
			'link_type' => 'site',
		);
	}

	// L1 — resolve the base source once (SPEC §V1); ambient term archive → term
	// analog (SPEC §V7). Explicit src/loop/id already won inside the factory.
	$base    = bws_base_resolve_source_for_callback( $options, $instance );
	$term_id = bws_base_ambient_term_id( $base, $options );
	if ( $term_id ) {
		return array(
			'value'     => bws_base_term_analog_read( 'text', $term_id, $options, $instance ),
			'link_id'   => $term_id,
			'link_type' => 'term',
		);
	}
	$is_ref  = 'ref' === ( $options['src'] ?? $options['source'] ?? '' );
	// Skip the single-collapse resolve for the pure src:ref list branch — it runs
	// its own plural traversal (bws_base_post_ids_from_source) below, so computing
	// $post_id here would run the ref hop twice (review #3). The srcTermIn branch
	// still needs $post_id (the ref-hopped post it reads terms from), so only skip
	// when there is no tax hop.
	$post_id = ( $is_ref && '' === $tax ) ? 0 : bws_base_post_id_from_source( $base, $options );

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
	} elseif ( $is_ref ) {
		// src:ref LIST mode (SPEC §V14): read EVERY fanned-out ref target, not just
		// the first. limit/sep are offered for src:ref, so honor them — mirrors the
		// srcTermIn branch (slice-to-limit, join, single-result link-wrap only).
		$post_ids = bws_base_post_ids_from_source( $base, $options );
		$limit    = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep      = $options['sep'] ?? ', ';
		$out      = [];
		$first_id = 0;
		foreach ( array_slice( $post_ids, 0, $limit ) as $pid ) {
			$result = 'title' === $use
				? bws_post_title_core( $pid, $opts, $instance )
				: bws_post_custom_text_core( $pid, $opts, $instance );
			if ( '' !== $result ) {
				$out[] = $result;
				if ( ! $first_id ) {
					$first_id = (int) $pid;
				}
			}
		}
		$value = implode( $sep, $out );
		if ( 1 === count( $out ) && $first_id ) {
			$link_id   = $first_id;
			$link_type = 'post';
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

	return array(
		'value'     => $value,
		'link_id'   => $link_id,
		'link_type' => $link_type,
	);
}

/**
 * Callback for the `text` base tag.
 *
 * Shell over bws_base_text_resolve_value(): resolve the value, link-wrap
 * single-result output, fall back to the editor preview label when empty.
 *
 * @since 1.6.0
 * @since 1.14.1 Value resolution extracted to bws_base_text_resolve_value().
 */
function bws_base_text_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$resolved = bws_base_text_resolve_value( $options, $instance );
	$value    = $resolved['value'];

	if ( '' !== $value ) {
		if ( $resolved['link_id'] && function_exists( 'bws_wrap_with_link' ) ) {
			$value = bws_wrap_with_link(
				$value,
				$options['linkTo'] ?? 'none',
				$options['linkKey'] ?? '',
				! empty( $options['newTab'] ),
				$resolved['link_id'],
				$resolved['link_type']
			);
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

	// L1 base source (SPEC §V1); ambient term archive → description/key analog (§V7).
	$base    = bws_base_resolve_source_for_callback( $options, $instance );
	$term_id = bws_base_ambient_term_id( $base, $options );
	if ( $term_id ) {
		$value = bws_base_term_analog_read( 'content', $term_id, $options, $instance );
		if ( '' !== $value ) {
			return $value; // content is not link-wrapped (parity with post path below).
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'content' ) : '';
	}
	$post_id = bws_base_post_id_from_source( $base, $options );

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

	// L1 base source (SPEC §V1); ambient term archive → term name analog (§V7).
	$base    = bws_base_resolve_source_for_callback( $options, $instance );
	$term_id = bws_base_ambient_term_id( $base, $options );
	if ( $term_id ) {
		$value = bws_base_term_analog_read( 'title', $term_id, $options, $instance );
		if ( '' !== $value ) {
			if ( function_exists( 'bws_wrap_with_link' ) ) {
				$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $term_id, 'term' );
			}
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'title' ) : '';
	}
	$is_ref  = 'ref' === ( $options['src'] ?? $options['source'] ?? '' );
	// Skip the single-collapse resolve for the pure src:ref list branch (review #3):
	// it runs its own plural traversal below. srcTermIn still needs $post_id.
	$post_id = ( $is_ref && '' === $tax ) ? 0 : bws_base_post_id_from_source( $base, $options );

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
	} elseif ( $is_ref ) {
		// src:ref LIST mode (SPEC §V14): read EVERY fanned-out ref target, honoring
		// limit/sep (offered for src:ref) — mirrors the srcTermIn branch above.
		$post_ids = bws_base_post_ids_from_source( $base, $options );
		$limit    = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep      = $options['sep'] ?? ', ';
		$out      = [];
		$first_id = 0;
		foreach ( array_slice( $post_ids, 0, $limit ) as $pid ) {
			$result = bws_post_title_core( $pid, $options, $instance );
			if ( '' !== $result ) {
				$out[] = $result;
				if ( ! $first_id ) {
					$first_id = (int) $pid;
				}
			}
		}
		$value = implode( $sep, $out );
		if ( 1 === count( $out ) && $first_id ) {
			$link_id   = $first_id;
			$link_type = 'post';
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

	// L1 base source (SPEC §V1); ambient term archive → term URL analog (§V7).
	$base    = bws_base_resolve_source_for_callback( $options, $instance );
	$term_id = bws_base_ambient_term_id( $base, $options );
	if ( $term_id ) {
		return bws_base_term_analog_read( 'permalink', $term_id, $options, $instance );
	}
	$post_id = bws_base_post_id_from_source( $base, $options );

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

	// L1 base source (SPEC §V1); ambient term archive → term image field (by key),
	// or the configured Media Library fallback when no key (I1 gap #29: no intrinsic
	// term image analog, but the fallback still applies). §V7.
	$base    = bws_base_resolve_source_for_callback( $options, $instance );
	$term_id = bws_base_ambient_term_id( $base, $options );
	if ( $term_id ) {
		$value = bws_base_term_analog_read( 'image', $term_id, $options, $instance );
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'image' ) : '';
	}
	$post_id = bws_base_post_id_from_source( $base, $options );

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
