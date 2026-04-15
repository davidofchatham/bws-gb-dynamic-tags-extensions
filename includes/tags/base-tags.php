<?php
/**
 * Base (source-agnostic) dynamic tag registrations.
 *
 * Registers one GB tag per content template. Entity traversal is selected at
 * render time via the `via` option rather than at registration time. Unset `via`
 * resolves from the current loop entity; named values dispatch to the appropriate
 * source class with option keys remapped for each traversal type.
 *
 * Registered tags: text, content, title, permalink, image, datetime_single, datetime_range
 *
 * Via dispatch table (post context):
 *   ''        → CurrentPost (no traversal; current loop entity)
 *   'ref'     → RelatedPost (single ACF relationship/post_object hop; option key: ref)
 *   'ref_ref' → SecondRelatedPost (two-hop; option keys: ref1, ref2)
 *   'tax_ref' → PostTermRelatedPost (taxonomy → ACF rel hop; option keys: tax, ref)
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

	$via_opt        = bws_base_via_option();
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
			$via_opt,
			$traversal_opts,
			array(
				'from'     => array(
					'type'    => 'select',
					'label'   => __( 'Get text from:', 'generateblocks' ),
					'options' => array(
						array( 'value' => '',      'label' => __( 'Custom field (ACF / meta)', 'generateblocks' ) ),
						array( 'value' => 'title', 'label' => __( 'Title / Name', 'generateblocks' ) ),
					),
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key.', 'generateblocks' ),
					'placeholder' => 'field_name',
					'show_if'     => array( 'from' => 'not:title' ),
				),
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
				'limit'    => array(
					'type'  => 'number',
					'label' => __( 'Limit', 'generateblocks' ),
					'help'  => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
				),
				'sep'      => array(
					'type'        => 'text',
					'label'       => __( 'Separator', 'generateblocks' ),
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
			$via_opt,
			$traversal_opts,
			array(
				'from'     => array(
					'type'    => 'select',
					'label'   => __( 'Get content from:', 'generateblocks' ),
					'options' => array(
						array( 'value' => '',        'label' => __( 'Post Content', 'generateblocks' ) ),
						array( 'value' => 'excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ),
						array( 'value' => 'key',     'label' => __( 'Custom Field (WYSIWYG)', 'generateblocks' ) ),
					),
				),
				'key'      => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key.', 'generateblocks' ),
					'placeholder' => 'field_name',
					'show_if'     => array( 'from' => 'key' ),
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
	// title — entity title/name; via traversal like text/content
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Title / Name', 'generateblocks' ),
		'tag'      => 'title',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => array_merge(
			$via_opt,
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
	// permalink — post/entity URL; via traversal like text/content
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Permalink', 'generateblocks' ),
		'tag'      => 'permalink',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => array_merge(
			$via_opt,
			$traversal_opts
		),
		'return'   => 'bws_base_permalink_callback',
	) );

	// =========================================================
	// image — featured or ACF/meta field image; gb_type 'media'
	// `as` is first and always serialized (default:'url' causes
	// GB to write as:url even when the user has not changed it).
	// `from:featured` is hidden when via is unset — traversal to
	// another post is the only context where featured is useful.
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Image', 'generateblocks' ),
		'tag'      => 'image',
		'type'     => 'media',
		'supports' => array(),
		'options'  => array_merge(
			array(
				'as' => array(
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
			),
			$via_opt,
			$traversal_opts,
			array(
				'from' => array(
					'type'    => 'select',
					'label'   => __( 'Get image from:', 'generateblocks' ),
					'options' => array(
						array( 'value' => '',         'label' => __( 'Custom field (ACF / meta)', 'generateblocks' ) ),
						array( 'value' => 'featured', 'label' => __( 'Featured Image', 'generateblocks' ) ),
					),
					'show_if' => array( 'via' => 'in:ref,ref_ref,tax_ref' ),
				),
				'key'  => array(
					'type'        => 'text',
					'label'       => __( 'Field Key', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key for the image.', 'generateblocks' ),
					'placeholder' => 'image_field',
					'show_if'     => array( 'from' => 'not:featured' ),
				),
				'size' => array(
					'type'        => 'text',
					'label'       => __( 'Image Size', 'generateblocks' ),
					'help'        => __( 'WordPress image size slug. Default: full.', 'generateblocks' ),
					'placeholder' => 'full',
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
	// 'options' — template-specific trailing options (appended after via + traversal
	//             sub-options in non-image modifier tags; used as trailing options in
	//             try_ tags after per-slot sN-* options).
	// 'term_fn'  — fn($term_id, $opts, $inst) for the direct term-entity path.
	// 'post_fn'  — fn($post_id, $opts, $inst) for the ref-traversal path (term → post).
	// 'try_core_fn'  — fn($post_id, $opts, $inst) for try_ post-slot dispatch.
	// 'try_term_fn'  — fn($term_id, $opts, $inst) for try_ via:tax slot dispatch.
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
	// 'options' here is used only by generate_base_try_tags() as trailing options after sN-key slots.
	TagTemplateRegistry::register_modifier_template( array(
		'key'              => 'image',
		'title'            => __( 'Image', 'generateblocks' ),
		'options'          => array(
			'as'   => array(
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
			'size' => array(
				'type'        => 'text',
				'label'       => __( 'Image Size', 'generateblocks' ),
				'help'        => __( 'WordPress image size slug. Default: full.', 'generateblocks' ),
				'placeholder' => 'full',
			),
		),
		'term_fn'          => 'bws_term_custom_image_core',
		'post_fn'          => 'bws_custom_image_core',
		'try_core_fn'      => 'bws_custom_image_core',
		'try_term_fn'      => 'bws_term_custom_image_core',
		'supports_try'     => true,
		'try_per_slot_key' => true,
		'is_image'         => true,
	) );

	// datetime_single and datetime_range: closures needed to remap base tag option keys
	// (key, key2, as, format…) to the legacy keys expected by bws_datetime_*_core().
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
// VIA OPTION + TRAVERSAL SUB-OPTIONS
// ===============================================

/**
 * Build the via dropdown option definition.
 *
 * Labels are pulled from the source registry so they stay in sync with
 * source label changes. Each via value corresponds to one traversal type
 * in bws_resolve_post_by_via().
 *
 * @since 1.6.0
 * @return array Single-entry array keyed 'via'.
 */
function bws_base_via_option(): array {
	$taxonomy_term  = SourceRegistry::get_source( 'term' );
	$related_post   = SourceRegistry::get_source( 'related_post' );
	$second_related = SourceRegistry::get_source( 'second_related_post' );
	$post_term_rel  = SourceRegistry::get_source( 'post_term_related_post' );

	return array(
		'via' => array(
			'type'    => 'select',
			'label'   => __( 'Locate source via:', 'generateblocks' ),
			'options' => array(
				array( 'value' => '',        'label' => __( 'Current (no traversal)', 'generateblocks' ) ),
				array(
					'value' => 'ref',
					'label' => $related_post
						? $related_post->get_source_label()
						: __( 'Ref/Rel Field', 'generateblocks' ),
				),
				array(
					'value' => 'ref_ref',
					'label' => $second_related
						? $second_related->get_source_label()
						: __( 'Ref/Rel Field → 2nd Ref/Rel Field', 'generateblocks' ),
				),
				array(
					'value' => 'tax',
					'label' => $taxonomy_term
						? $taxonomy_term->get_source_label()
						: __( 'Taxonomy Term', 'generateblocks' ),
				),
				array(
					'value' => 'tax_ref',
					'label' => $post_term_rel
						? $post_term_rel->get_source_label()
						: __( 'Term → Ref/Rel Field', 'generateblocks' ),
				),
			),
		),
	);
}

/**
 * Build traversal sub-option definitions for the via dispatch.
 *
 * Each sub-option carries a show_if condition tied to the via value(s) that need it.
 * Keys follow the base tag naming convention (ref, ref1, ref2, tax) and are remapped
 * to source-internal keys (rel, rel_2, taxonomy) inside bws_resolve_post_by_via().
 *
 * Option key   via value(s)     Source used
 * ----------   ------------     -----------
 * ref          ref, tax_ref     RelatedPost (single hop) or PostTermRelatedPost (rel hop on term)
 * ref1         ref_ref          SecondRelatedPost (first hop)
 * ref2         ref_ref          SecondRelatedPost (second hop)
 * tax          tax_ref          PostTermRelatedPost (taxonomy hop)
 *
 * @since 1.6.0
 * @return array Option definitions keyed by option name.
 */
function bws_base_traversal_options(): array {
	return array(
		// Shared by via:ref (RelatedPost) and via:tax_ref (PostTermRelatedPost rel hop).
		'ref'  => array(
			'type'        => 'text',
			'label'       => __( 'Traverse by meta key:', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key.', 'generateblocks' ),
			'placeholder' => 'related_posts',
			'show_if'     => array( 'via' => 'in:ref,tax_ref' ),
		),
		// SecondRelatedPost — first hop (only for via:ref_ref).
		'ref1' => array(
			'type'        => 'text',
			'label'       => __( 'First traverse by meta key:', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key (first hop).', 'generateblocks' ),
			'placeholder' => 'related_posts',
			'show_if'     => array( 'via' => 'ref_ref' ),
		),
		// SecondRelatedPost — second hop (only for via:ref_ref).
		'ref2' => array(
			'type'        => 'text',
			'label'       => __( 'Then traverse by meta key:', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key on the first related post (second hop).', 'generateblocks' ),
			'placeholder' => 'related_posts',
			'show_if'     => array( 'via' => 'ref_ref' ),
		),
		// TaxonomyTerm hop (via:tax) or PostTermRelatedPost taxonomy hop (via:tax_ref).
		'tax'  => array(
			'type'        => 'text',
			'label'       => __( 'Traverse by taxonomy:', 'generateblocks' ),
			'help'        => __( 'Taxonomy slug used to find the post\'s first term (e.g. category, post_tag).', 'generateblocks' ),
			'placeholder' => 'category',
			'show_if'     => array( 'via' => 'in:tax,tax_ref' ),
		),
	);
}

// ===============================================
// VIA DISPATCH
// ===============================================

/**
 * Resolve the target post ID from the `via` option.
 *
 * Reads $options['via'] and dispatches to the appropriate source class,
 * remapping base-tag option keys to the internal keys each source's
 * resolve_id() method expects.
 *
 * @since 1.6.0
 * @param array  $options  Tag options from GenerateBlocks.
 * @param object $instance Block instance.
 * @return int|false Resolved post ID, or false if unresolvable.
 */
function bws_resolve_post_by_via( array $options, $instance ) {
	$via = $options['via'] ?? '';

	switch ( $via ) {

		case 'ref':
			// RelatedPost: one ACF relationship/post_object hop.
			// Base tag key 'ref' → internal key 'rel'.
			$source = SourceRegistry::get_source( 'related_post' );
			if ( ! $source ) {
				return false;
			}
			$mapped        = $options;
			$mapped['rel'] = $options['ref'] ?? '';
			return $source->resolve_id( $mapped, $instance );

		case 'ref_ref':
			// SecondRelatedPost: two ACF relationship hops.
			// Base tag keys 'ref1'/'ref2' → internal keys 'rel'/'rel_2'.
			$source = SourceRegistry::get_source( 'second_related_post' );
			if ( ! $source ) {
				return false;
			}
			$mapped          = $options;
			$mapped['rel']   = $options['ref1'] ?? '';
			$mapped['rel_2'] = $options['ref2'] ?? '';
			return $source->resolve_id( $mapped, $instance );

		case 'tax_ref':
			// PostTermRelatedPost: post → first term in taxonomy → ACF rel field on term.
			// Base tag keys 'tax'/'ref' → internal keys 'taxonomy'/'rel'.
			$source = SourceRegistry::get_source( 'post_term_related_post' );
			if ( ! $source ) {
				return false;
			}
			$mapped             = $options;
			$mapped['taxonomy'] = $options['tax'] ?? '';
			$mapped['rel']      = $options['ref'] ?? '';
			return $source->resolve_id( $mapped, $instance );

		default:
			// Empty via — current loop entity (CurrentPost).
			$source = SourceRegistry::get_source( 'post' );
			return $source ? $source->resolve_id( $options, $instance ) : false;
	}
}

/**
 * Get taxonomy terms for the current post via the `tax` traversal option.
 *
 * Used by base tag callbacks and generate_base_try_tags() when via='tax'. Returns
 * WP_Term objects for the current loop post in the taxonomy specified by $options['tax'].
 *
 * @since 1.6.0
 * @param array  $options  Tag options; reads 'tax' (taxonomy slug).
 * @param object $instance Block instance (unused; reserved for future context detection).
 * @return WP_Term[]
 */
function bws_get_terms_by_via( array $options, $instance ): array {
	$tax = sanitize_key( $options['tax'] ?? '' );
	if ( empty( $tax ) ) {
		return [];
	}

	$post_id = get_the_ID();
	if ( ! $post_id ) {
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
 * Dispatches entity resolution via the `via` option, then calls the
 * appropriate core function based on context and `from`:
 *
 * via:tax  + from unset   → bws_term_custom_text_core() (per-term; limit/sep applied)
 * via:tax  + from:title   → bws_term_title_core()        (per-term; limit/sep applied)
 * via:post + from unset   → bws_post_custom_text_core()
 * via:post + from:title   → bws_post_title_core()
 *
 * @since 1.6.0
 */
function bws_base_text_callback( $options, $block, $instance ): string {
	$via  = $options['via'] ?? '';
	$from = $options['from'] ?? '';
	$opts = bws_base_map_options( $options );

	if ( 'tax' === $via ) {
		$terms  = bws_get_terms_by_via( $options, $instance );
		$limit  = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$sep    = $options['sep'] ?? ', ';
		$out    = [];
		foreach ( array_slice( $terms, 0, $limit ) as $term ) {
			$result = 'title' === $from
				? bws_term_title_core( $term->term_id, $opts, $instance )
				: bws_term_custom_text_core( $term->term_id, $opts, $instance );
			if ( '' !== $result ) {
				$out[] = $result;
			}
		}
		return implode( $sep, $out );
	}

	$post_id = bws_resolve_post_by_via( $options, $instance );

	if ( 'title' === $from ) {
		return bws_post_title_core( $post_id, $opts, $instance );
	}

	// Default: custom field (from unset = key mode).
	return bws_post_custom_text_core( $post_id, $opts, $instance );
}

/**
 * Callback for the `content` base tag.
 *
 * Dispatches entity resolution via the `via` option, then calls the
 * appropriate core function based on context and `from`:
 *
 * via:tax  + from unset   → bws_term_description_core() (term description; first non-empty)
 * via:tax  + from:key     → bws_term_custom_text_core()  (term WYSIWYG field)
 * via:post + from unset   → bws_post_content_core()     (full post content)
 * via:post + from:excerpt → bws_post_excerpt_core()
 * via:post + from:key     → bws_post_content_core() with type:custom_field injected
 *
 * @since 1.6.0
 */
function bws_base_content_callback( $options, $block, $instance ): string {
	$via  = $options['via'] ?? '';
	$from = $options['from'] ?? '';
	$opts = bws_base_map_options( $options );

	if ( 'tax' === $via ) {
		$terms = bws_get_terms_by_via( $options, $instance );
		foreach ( $terms as $term ) {
			$result = 'key' === $from
				? bws_term_custom_text_core( $term->term_id, $opts, $instance )
				: bws_term_description_core( $term->term_id, $opts, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	$post_id = bws_resolve_post_by_via( $options, $instance );

	if ( 'excerpt' === $from ) {
		return bws_post_excerpt_core( $post_id, $opts, $instance );
	}

	if ( 'key' === $from ) {
		// Reuse bws_post_content_core()'s custom field branch (reads type + key options).
		$opts['type'] = 'custom_field';
		return bws_post_content_core( $post_id, $opts, $instance );
	}

	// Default: full post content.
	return bws_post_content_core( $post_id, $opts, $instance );
}

/**
 * Callback for the `title` base tag.
 *
 * Dispatches entity resolution via the `via` option.
 * via:tax resolves taxonomy terms for the current post; limit/sep applied.
 *
 * @since 1.6.0
 */
function bws_base_title_callback( $options, $block, $instance ): string {
	$via = $options['via'] ?? '';

	if ( 'tax' === $via ) {
		$terms  = bws_get_terms_by_via( $options, $instance );
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

	$post_id = bws_resolve_post_by_via( $options, $instance );
	return bws_post_title_core( $post_id, $options, $instance );
}

/**
 * Callback for the `permalink` base tag.
 *
 * Dispatches entity resolution via the `via` option.
 * via:tax resolves taxonomy terms; returns first non-empty term URL.
 *
 * @since 1.6.0
 */
function bws_base_permalink_callback( $options, $block, $instance ): string {
	$via = $options['via'] ?? '';

	if ( 'tax' === $via ) {
		$terms = bws_get_terms_by_via( $options, $instance );
		foreach ( $terms as $term ) {
			$result = bws_term_permalink_core( $term->term_id, $options, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	$post_id = bws_resolve_post_by_via( $options, $instance );
	return bws_post_permalink_core( $post_id, $options, $instance );
}

/**
 * Callback for the `image` base tag.
 *
 * Dispatches entity resolution via the `via` option, then calls the
 * appropriate core function based on context and `from`:
 *
 * via:tax  → bws_term_custom_image_core() (first non-empty; terms have no featured image)
 * via:post + from unset   → bws_custom_image_core()
 * via:post + from:featured → bws_featured_image_core()
 *
 * The `from` option is only shown in the editor when `via` is set to a post traversal
 * (in:ref,ref_ref,tax_ref). For via:tax the image is always a term custom field.
 *
 * @since 1.6.0
 */
function bws_base_image_callback( $options, $block, $instance ): string {
	$via  = $options['via'] ?? '';
	$from = $options['from'] ?? '';

	if ( 'tax' === $via ) {
		$terms = bws_get_terms_by_via( $options, $instance );
		foreach ( $terms as $term ) {
			$result = bws_term_custom_image_core( $term->term_id, $options, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	$post_id = bws_resolve_post_by_via( $options, $instance );

	if ( 'featured' === $from ) {
		return bws_featured_image_core( $post_id, $options, $instance );
	}

	// Default: custom field image (reads $options['key'] and $options['size']).
	return bws_custom_image_core( $post_id, $options, $instance );
}
