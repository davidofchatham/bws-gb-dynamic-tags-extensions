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
		'options'  => array_merge(
			$source_opt,
			$traversal_opts,
			array(
				'use'      => array(
					'type'    => 'select',
					'label'   => __( 'Get text from:', 'generateblocks' ),
					'options' => array(
						array( 'value' => '',      'label' => __( 'Meta/Custom Field', 'generateblocks' ) ),
						array( 'value' => 'title', 'label' => __( 'Title/Name', 'generateblocks' ) ),
					),
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key.', 'generateblocks' ),
					'placeholder' => 'field_name',
					'show_if'     => array( 'use' => 'not:title' ),
				),
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
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
		),
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
		'options'  => array_merge(
			$source_opt,
			$traversal_opts,
			array(
				'use'      => array(
					'type'    => 'select',
					'label'   => __( 'Get content from:', 'generateblocks' ),
					'options' => array(
						array( 'value' => '',        'label' => __( 'Post Content/Term Description', 'generateblocks' ) ),
						array( 'value' => 'excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ),
						array( 'value' => 'key',     'label' => __( 'Custom Content Field (WYSIWYG/Blocks)', 'generateblocks' ) ),
					),
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key.', 'generateblocks' ),
					'placeholder' => 'field_name',
					'show_if'     => array( 'use' => 'key' ),
				),
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if content is empty or not found.', 'generateblocks' ),
				),
			)
		),
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
		'options'  => array_merge(
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
			)
		),
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
		'options'  => array_merge(
			$source_opt,
			$traversal_opts
		),
		'return'   => 'bws_base_permalink_callback',
	) );

	// =========================================================
	// image — custom field or featured image; type 'cross-source'.
	// `as` is first and always serialized (default:'url' intentional).
	// `size` and `fallback` use custom JS controls (image-tag-controls.js).
	// `use:featured` hidden when srcTerm set — terms have no featured image.
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Image', 'generateblocks' ),
		'tag'      => 'image',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => array_merge(
			array(
				'as'   => array(
					'type'    => 'select',
					'label'   => __( 'Return type:', 'generateblocks' ),
					'default' => 'url',
					'options' => array(
						array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
						array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
						array( 'value' => 'title',   'label' => __( 'Title', 'generateblocks' ) ),
						array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
						array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
					),
				),
				'size' => array(
					'type'  => 'bws-img-size',
					'label' => __( 'Image Size', 'generateblocks' ),
				),
			),
			$source_opt,
			$traversal_opts,
			array(
				'use'      => array(
					'type'    => 'select',
					'label'   => __( 'Get image from:', 'generateblocks' ),
					'options' => array(
						array( 'value' => '',         'label' => __( 'Meta/Custom Field', 'generateblocks' ) ),
						array( 'value' => 'featured', 'label' => __( 'Featured Image', 'generateblocks' ) ),
					),
					'show_if' => array( 'srcTerm' => 'empty' ),
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
			)
		),
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
	// 'options'      — template-specific trailing options (appended after source +
	//                  traversal sub-options in non-image modifier tags; used as
	//                  trailing options in try_ tags after per-slot N-* options).
	// 'term_fn'      — fn($term_id, $opts, $inst) for the direct term-entity path.
	// 'post_fn'      — fn($post_id, $opts, $inst) for the ref-traversal path (term → post).
	// 'try_core_fn'  — fn($post_id, $opts, $inst) for try_ post-slot dispatch.
	// 'try_term_fn'  — fn($term_id, $opts, $inst) for try_ srcTerm slot dispatch.
	// =========================================================

	TagTemplateRegistry::register_modifier_template( array(
		'key'              => 'text',
		'title'            => __( 'Text Fields', 'generateblocks' ),
		'options'          => array(
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
		'term_fn'          => 'bws_term_custom_text_core',
		'post_fn'          => 'bws_post_custom_text_core',
		'try_core_fn'      => 'bws_post_custom_text_core',
		'try_term_fn'      => 'bws_term_custom_text_core',
		'supports_try'     => true,
		'try_per_slot_key' => true,
		'is_image'         => false,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'          => 'content',
		'title'        => __( 'Content', 'generateblocks' ),
		'options'      => array(
			'fallback' => array(
				'type'  => 'text',
				'label' => __( 'Fallback Text', 'generateblocks' ),
				'help'  => __( 'Text to display if content is empty.', 'generateblocks' ),
			),
		),
		'term_fn'      => 'bws_term_description_core',
		'post_fn'      => 'bws_post_content_core',
		'try_core_fn'  => 'bws_post_content_core',
		'try_term_fn'  => 'bws_term_description_core',
		'supports_try' => true,
		'is_image'     => false,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'          => 'title',
		'title'        => __( 'Title / Name', 'generateblocks' ),
		'options'      => array(),
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
	// 'options' here used by generate_base_try_tags() as trailing options after N-* slots.
	// 'use' included so generate_base_try_tags() reads its options for per-slot use selectors.
	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'image',
		'title'                 => __( 'Image', 'generateblocks' ),
		'options'               => array(
			'as'       => array(
				'type'    => 'select',
				'label'   => __( 'Return image as:', 'generateblocks' ),
				'default' => 'url',
				'options' => array(
					array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
					array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
					array( 'value' => 'title',   'label' => __( 'Title', 'generateblocks' ) ),
					array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
					array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
				),
			),
			'size'     => array(
				'type'  => 'bws-img-size',
				'label' => __( 'Image Size', 'generateblocks' ),
			),
			'use'      => array(
				'type'    => 'select',
				'label'   => __( 'Get image from:', 'generateblocks' ),
				'options' => array(
					array( 'value' => '',         'label' => __( 'Meta/Custom Field', 'generateblocks' ) ),
					array( 'value' => 'featured', 'label' => __( 'Featured Image', 'generateblocks' ) ),
				),
			),
			'fallback' => array(
				'type'  => 'bws-media-picker',
				'label' => __( 'Fallback Image', 'generateblocks' ),
			),
		),
		'term_fn'               => 'bws_term_custom_image_core',
		'post_fn'               => 'bws_custom_image_core',
		'try_core_fn'           => 'bws_custom_image_core',
		'try_term_fn'           => 'bws_term_custom_image_core',
		'supports_try'          => true,
		'try_per_slot_key'      => true,
		'try_per_slot_use'      => true,
		'try_use_no_key_values' => array( 'featured' ),
		'is_image'              => true,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'          => 'datetime_single',
		'title'        => __( 'Date / Time', 'generateblocks' ),
		'options'      => function_exists( 'bws_get_datetime_single_template_options' )
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
		'key'          => 'datetime_range',
		'title'        => __( 'Date / Time Range', 'generateblocks' ),
		'options'      => function_exists( 'bws_get_datetime_range_template_options' )
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
		'modifier_label'       => '',
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
 * Labels are pulled from the source registry so they stay in sync with
 * source label changes. The selected value dispatches entity resolution
 * in bws_resolve_post_by_source().
 *
 * @since 1.6.0
 * @return array Single-entry array keyed 'source'.
 */
function bws_base_source_option(): array {
	$related_post = SourceRegistry::get_source( 'related_post' );

	return array(
		'source' => array(
			'type'    => 'select',
			'label'   => __( 'Source:', 'generateblocks' ),
			'options' => array(
				array( 'value' => '',    'label' => __( 'Current (no traversal)', 'generateblocks' ) ),
				array(
					'value' => 'ref',
					'label' => $related_post
						? $related_post->get_source_label()
						: __( 'Ref/Rel Field', 'generateblocks' ),
				),
			),
		),
	);
}

/**
 * Build traversal sub-option definitions for the source dispatch.
 *
 * `ref` — shown when source:ref; the relationship field key for the hop.
 * `srcTerm` — checkbox; when set, the resolved entity's taxonomy term is used
 *             as the final entity instead of the post itself.
 * `tax` — shown when srcTerm is set; the taxonomy slug to get the term from.
 *
 * @since 1.6.0
 * @return array Option definitions keyed by option name.
 */
function bws_base_traversal_options(): array {
	return array(
		'ref'     => array(
			'type'        => 'text',
			'label'       => __( 'Traverse by meta key:', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key.', 'generateblocks' ),
			'placeholder' => 'related_posts',
			'show_if'     => array( 'source' => 'ref' ),
		),
		'srcTerm' => array(
			'type'  => 'checkbox',
			'label' => __( 'Get from taxonomy term?', 'generateblocks' ),
			'help'  => __( 'Field is in a taxonomy term on this source.', 'generateblocks' ),
		),
		'tax'     => array(
			'type'        => 'text',
			'label'       => __( 'Taxonomy:', 'generateblocks' ),
			'help'        => __( 'Taxonomy slug (e.g. category, post_tag).', 'generateblocks' ),
			'placeholder' => 'category',
			'show_if'     => array( 'srcTerm' => 'not_empty' ),
		),
	);
}

// ===============================================
// SOURCE DISPATCH
// ===============================================

/**
 * Resolve the target post ID from the `source` option.
 *
 * Reads $options['source'] and dispatches to the appropriate source class.
 * `ref` maps the base-tag option key 'ref' to the internal key 'rel' that
 * RelatedPost::resolve_id() expects.
 *
 * @since 1.6.0
 * @param array  $options  Tag options from GenerateBlocks.
 * @param object $instance Block instance.
 * @return int|false Resolved post ID, or false if unresolvable.
 */
function bws_resolve_post_by_source( array $options, $instance ) {
	switch ( $options['source'] ?? '' ) {

		case 'ref':
			$source = SourceRegistry::get_source( 'related_post' );
			if ( ! $source ) {
				return false;
			}
			$mapped        = $options;
			$mapped['rel'] = $options['ref'] ?? '';
			return $source->resolve_id( $mapped, $instance );

		default:
			$source = SourceRegistry::get_source( 'post' );
			return $source ? $source->resolve_id( $options, $instance ) : false;
	}
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
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'text' ) : '';
	}

	$use      = $options['use'] ?? '';
	$src_term = ! empty( $options['srcTerm'] );
	$opts     = bws_base_map_options( $options );

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( $src_term ) {
		$tax    = sanitize_key( $options['tax'] ?? '' );
		$terms  = bws_get_srcterm_terms( (int) $post_id, $tax );
		$limit  = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep    = $options['sep'] ?? ', ';
		$out    = [];
		foreach ( array_slice( $terms, 0, $limit ) as $term ) {
			$result = 'title' === $use
				? bws_term_title_core( $term->term_id, $opts, $instance )
				: bws_term_custom_text_core( $term->term_id, $opts, $instance );
			if ( '' !== $result ) {
				$out[] = $result;
			}
		}
		return implode( $sep, $out );
	}

	if ( 'title' === $use ) {
		return bws_post_title_core( $post_id, $opts, $instance );
	}

	return bws_post_custom_text_core( $post_id, $opts, $instance );
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
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'content' ) : '';
	}

	$use      = $options['use'] ?? '';
	$src_term = ! empty( $options['srcTerm'] );
	$opts     = bws_base_map_options( $options );

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( $src_term ) {
		$tax   = sanitize_key( $options['tax'] ?? '' );
		$terms = bws_get_srcterm_terms( (int) $post_id, $tax );
		foreach ( $terms as $term ) {
			$result = 'key' === $use
				? bws_term_custom_text_core( $term->term_id, $opts, $instance )
				: bws_term_description_core( $term->term_id, $opts, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

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
 * Callback for the `title` base tag.
 *
 * Resolves entity via `source`, applies srcTerm hop when set.
 * srcTerm iterates terms with limit/sep applied.
 *
 * @since 1.6.0
 */
function bws_base_title_callback( $options, $block, $instance ): string {
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		return function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'title' ) : '';
	}

	$src_term = ! empty( $options['srcTerm'] );

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( $src_term ) {
		$tax    = sanitize_key( $options['tax'] ?? '' );
		$terms  = bws_get_srcterm_terms( (int) $post_id, $tax );
		$limit  = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep    = $options['sep'] ?? ', ';
		$out    = [];
		foreach ( array_slice( $terms, 0, $limit ) as $term ) {
			$result = bws_term_title_core( $term->term_id, $options, $instance );
			if ( '' !== $result ) {
				$out[] = $result;
			}
		}
		return implode( $sep, $out );
	}

	return bws_post_title_core( $post_id, $options, $instance );
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
	$src_term = ! empty( $options['srcTerm'] );

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( $src_term ) {
		$tax   = sanitize_key( $options['tax'] ?? '' );
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
	if ( ! empty( $instance->context['bwsEditorPreview'] ) ) {
		// Returns '' for as:url and as:id — attribute values where bracket string breaks the element.
		return function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'image' ) : '';
	}

	$use      = $options['use'] ?? '';
	$src_term = ! empty( $options['srcTerm'] );

	$post_id = bws_resolve_post_by_source( $options, $instance );

	if ( $src_term ) {
		$tax   = sanitize_key( $options['tax'] ?? '' );
		$terms = bws_get_srcterm_terms( (int) $post_id, $tax );
		foreach ( $terms as $term ) {
			$result = bws_term_custom_image_core( $term->term_id, $options, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	if ( 'featured' === $use ) {
		return bws_featured_image_core( $post_id, $options, $instance );
	}

	return bws_custom_image_core( $post_id, $options, $instance );
}
