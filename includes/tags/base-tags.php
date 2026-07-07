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
			'type'        => 'bws-field-combo',
			'label'       => __( 'Relationship Field Key', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key.', 'generateblocks' ),
			'placeholder' => 'related_posts',
			// ref names the SOURCE-post relationship field. The control does NOT
			// preset a kind for src:ref (presetKind returns null): the ref-hop target
			// post type is not reliably known, so the key list stays UNSCOPED with the
			// generic "Meta/Option Field" label (SPEC V3). v2 will type-filter this to
			// relationship/post_object.
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
 * THIN BACK-COMPAT WRAPPER (SPEC §T5, §V4) over the source factory + traversal
 * engine. The value-list SEAM (bws_resolve_field_values) no longer calls this —
 * it drives the factory + steps directly and reads plural by kind (SPEC §V6/§V12).
 * This wrapper survives for its ~30 remaining POST-SEMANTIC callers (datetime,
 * {{call}}/fn, try_ slots): they want a single POST id | false, nothing else.
 *
 * Delegates to bws_resolve_base_source (L1 factory: loop → ambient term → current
 * post, SPEC §V1/§V7) + a REF-ONLY step assembly (bws_wrapper_ref_steps, SPEC
 * §V13) run through bws_run_traversal, then collapses to the FIRST post id
 * (bws_first_post_id_from_sources, SPEC §V4). A non-post base — term ambient on an
 * archive (V7) or a Mode-2b meta_row (src:current on a flat repeater row) — yields
 * false, never leaks a term/row id as a post id. That is byte-compatible with the
 * old wrapper for src:current (Mode 2b → false, unchanged); for src:ref it applies
 * the V11 leak-fix (base the ref hop on the ambient term, not on get_the_ID()).
 *
 * REF-ONLY (SPEC §V13): the wrapper NEVER assembles a `srcTermIn` step. srcTermIn
 * (post→term) is owned DOWNSTREAM by the wrapper's callers — datetime/text/title
 * srcTermIn branches call bws_get_srcterm_terms() on the returned POST id. Routing
 * the wrapper through the SEAM's bws_field_values_assemble_steps() (which emits a
 * srcTermIn term-hop) would collapse to false and empty those callers (B2). The
 * seam reads term fields by kind; the wrapper cannot — its contract is a post id.
 *
 * @since 1.6.0
 * @since 1.14.0 Rewired to the source factory + traversal engine (SPEC §T5); ref-only steps (§V13, B2).
 * @param array  $options  Tag options from GenerateBlocks.
 * @param object $instance Block instance.
 * @return int|false Resolved post ID, or false if unresolvable.
 */
function bws_resolve_post_by_source( array $options, $instance ) {
	if ( ! function_exists( 'bws_resolve_base_source' )
		|| ! function_exists( 'bws_run_traversal' )
		|| ! function_exists( 'bws_first_post_id_from_sources' ) ) {
		return false;
	}

	$base    = bws_resolve_base_source( $options, $instance );
	$steps   = bws_wrapper_ref_steps( $options );
	$sources = bws_run_traversal( array( $base ), $steps );

	return bws_first_post_id_from_sources( $sources );
}

/**
 * Assemble the wrapper's REF-ONLY step set (SPEC §V13, B2).
 *
 * Post-semantic: only a `src:ref` hop (post→post[]) is a wrapper step. A
 * `srcTermIn` post→term hop is DELIBERATELY excluded — the wrapper's callers own
 * that downstream on the returned post id (bws_get_srcterm_terms). Contrast the
 * seam's bws_field_values_assemble_steps(), which DOES emit srcTermIn (terminal
 * term-list read) because it reads term fields by kind (§V6/§V12).
 *
 * @since 1.14.0
 * @param array $options Tag options (src, ref).
 * @return array[] Zero or one ref step.
 */
function bws_wrapper_ref_steps( array $options ): array {
	$src = $options['src'] ?? $options['source'] ?? '';
	if ( 'ref' === $src ) {
		$ref = $options['ref'] ?? '';
		if ( '' !== $ref ) {
			return array( array( 'type' => 'ref', 'field' => $ref ) );
		}
	}
	return array();
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
// TERM-AMBIENT DISPATCH (SPEC §T6 / §V7)
// ===============================================

/**
 * Resolve the base source for a base callback, guarded for load order.
 *
 * Single factory call per callback (SPEC §V1): the callback then branches on the
 * base kind — term → analog read (§V7), else collapse to a post id via
 * bws_first_post_id_from_sources (§V4). Falls back to a post/0 source when the
 * engine is unavailable (mirrors the wrapper's guard) so callbacks stay safe.
 *
 * @since 1.14.0
 * @param array  $options  Tag options.
 * @param object $instance GB instance.
 * @return array Base resolved source ({kind,id}|{kind:site}|{kind:meta_row,row}).
 */
function bws_base_resolve_source_for_callback( array $options, $instance ): array {
	return function_exists( 'bws_resolve_base_source' )
		? bws_resolve_base_source( $options, $instance )
		: array( 'kind' => 'post', 'id' => 0 );
}

/**
 * Collapse a base source to the callback's POST id via ref-only steps (SPEC §V13).
 *
 * The post-path counterpart of the ambient-term branch: runs the wrapper's
 * ref-only step set (src:ref → post→post[] hop; NEVER srcTermIn, which the
 * callback's own $tax branch owns) then takes the first post id. Mirrors
 * bws_resolve_post_by_source() for a base source already resolved once, so the
 * callback pays a single factory call (SPEC §V1).
 *
 * @since 1.14.0
 * @param array $base    Base resolved source.
 * @param array $options Tag options.
 * @return int|false First post id, or false.
 */
function bws_base_post_id_from_source( array $base, array $options ) {
	if ( ! function_exists( 'bws_run_traversal' ) || ! function_exists( 'bws_first_post_id_from_sources' ) ) {
		return bws_first_post_id_from_sources( array( $base ) );
	}
	$sources = bws_run_traversal( array( $base ), bws_wrapper_ref_steps( $options ) );
	return bws_first_post_id_from_sources( $sources );
}

/**
 * Collapse a base source to the FULL post-id LIST via ref-only steps (SPEC §V14).
 *
 * The plural counterpart of bws_base_post_id_from_source(): for a tag that offers
 * list mode on `src:ref` (text/title, §V14 offered⟺resolvable), the src:ref post
 * branch reads EVERY fanned-out ref target (bws_run_traversal keeps all, §V6) — not
 * just the first. Order preserved; only post-kind sources contribute. The caller
 * slices to `limit` and joins with `sep`, mirroring the srcTermIn branch.
 *
 * @since 1.14.0
 * @param array $base    Base resolved source.
 * @param array $options Tag options.
 * @return int[] Post ids in document order (may be empty).
 */
function bws_base_post_ids_from_source( array $base, array $options ): array {
	if ( ! function_exists( 'bws_run_traversal' ) ) {
		return array();
	}
	$sources = bws_run_traversal( array( $base ), bws_wrapper_ref_steps( $options ) );
	$ids     = array();
	foreach ( $sources as $src ) {
		if ( is_array( $src ) && 'post' === ( $src['kind'] ?? '' ) ) {
			$id = (int) ( $src['id'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
	}
	return $ids;
}

/**
 * Whether a base callback should read the AMBIENT TERM instead of a post.
 *
 * True iff (a) no explicit `srcTermIn` hop is set (that branch owns its own
 * post→term traversal and is incoherent from a term base), (b) `src` is neither
 * the site source (own early gate) NOR `ref` (SPEC §V11: src:ref on a term archive
 * HOPS the term's relationship field term→post[] via the post path's ref step,
 * then reads the target POST's analog — it must NOT short-circuit to the term's
 * own analog), and (c) the factory's base resolved source is a term — i.e. a bare
 * base tag on a term archive (SPEC §V7). Explicit options (loop row, src:current,
 * id) win inside the factory itself (SPEC §V1), so this returns false whenever the
 * author pinned a non-term source.
 *
 * @since 1.14.0
 * @param array  $base     Base resolved source from bws_resolve_base_source().
 * @param array  $options  Tag options.
 * @return int Term id when the ambient-term analog path applies, else 0.
 */
function bws_base_ambient_term_id( array $base, array $options ): int {
	$tax = sanitize_key( $options['srcTermIn'] ?? '' );
	if ( '' !== $tax ) {
		return 0; // Explicit post→term hop owns this render.
	}
	$src = $options['src'] ?? $options['source'] ?? '';
	if ( 'site' === $src || 'ref' === $src ) {
		return 0; // Site: own gate. ref: hops term→post (V11), post path owns it.
	}
	if ( 'term' !== ( $base['kind'] ?? '' ) ) {
		return 0;
	}
	return (int) ( $base['id'] ?? 0 );
}

/**
 * Read a base tag's TERM analog on a term archive (SPEC §V7, CONTEXT.md I1).
 *
 * The I1 source-analog table applied to an ambient term: each base tag, at its
 * DEFAULT `use`, yields the term's intrinsic analog; `use:key` (and text's
 * key-default) reads a term meta field. Routes through the SAME term core fns the
 * explicit srcTermIn branch uses — full parity, one code home for the term reads.
 *
 *   title   → term name           (bws_term_title_core)
 *   text    → use:title ? name : keyed term field  (title vs custom_text core)
 *   content → use:key  ? keyed term field : term description
 *   permalink → term URL          (bws_term_permalink_core)
 *   image   → HONEST GAP (#29): no intrinsic term image analog. A key reads a
 *             term image field; with no key + no fallback → empty. A configured
 *             Media Library fallback still applies (bws_term_custom_image_core owns
 *             the no-key→fallback path), keeping standalone == try_image slot.
 *
 * @since 1.14.0
 * @param string $tag     One of text|content|title|permalink|image.
 * @param int    $term_id Ambient term id.
 * @param array  $options Tag options (use, key, fallback, …).
 * @param object $instance GB instance.
 * @return string Rendered analog value ('' on miss/gap).
 */
function bws_base_term_analog_read( string $tag, int $term_id, array $options, $instance ): string {
	if ( ! $term_id ) {
		return '';
	}
	$opts = bws_base_map_options( $options );

	switch ( $tag ) {
		case 'title':
			return bws_term_title_core( $term_id, $options, $instance );

		case 'text':
			$use = $options['use'] ?? 'key';
			return 'title' === $use
				? bws_term_title_core( $term_id, $opts, $instance )
				: bws_term_custom_text_core( $term_id, $opts, $instance );

		case 'content':
			$use = $options['use'] ?? 'content';
			return 'key' === $use
				? bws_term_custom_text_core( $term_id, $opts, $instance )
				: bws_term_description_core( $term_id, $opts, $instance );

		case 'permalink':
			return bws_term_permalink_core( $term_id, $options, $instance );

		case 'image':
			// I1 gap #29 — a term has no intrinsic image analog. A key reads a term
			// image field; with no key there is no analog datum, BUT a configured
			// Media Library fallback still applies (fallback = last resort, gap or not).
			// bws_term_custom_image_core handles the no-key case itself: empty key →
			// bws_handle_term_image_fallback → the fallback (or '' when none set). So
			// call it unconditionally — no key + no fallback stays empty (honest gap),
			// no key + fallback yields the fallback. This keeps the standalone tag
			// byte-identical to a try_image slot, which calls the same core (V8/C9).
			return bws_term_custom_image_core( $term_id, $options, $instance );
	}

	return '';
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

	// L1 — resolve the base source once (SPEC §V1); ambient term archive → term
	// analog (SPEC §V7). Explicit src/loop/id already won inside the factory.
	$base    = bws_base_resolve_source_for_callback( $options, $instance );
	$term_id = bws_base_ambient_term_id( $base, $options );
	if ( $term_id ) {
		$value = bws_base_term_analog_read( 'text', $term_id, $options, $instance );
		if ( '' !== $value ) {
			if ( function_exists( 'bws_wrap_with_link' ) ) {
				$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $term_id, 'term' );
			}
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'text' ) : '';
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
