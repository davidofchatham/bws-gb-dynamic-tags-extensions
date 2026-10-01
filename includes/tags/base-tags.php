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
 * Resolution (traversal pipeline, NOT source classes):
 *   L1 base source — `bws_resolve_base_source()` (traversal-pipeline.php): a known-shape
 *     query-loop item → ambient term → current post, or explicit `src:site` / registry
 *     source. An item of any OTHER shape ENDS the precedence: the read is refused (that
 *     function's step 2e owns why). `$post` / get_the_ID() is NEVER an ambient fallback.
 *   L1 steps — the compiled chain, run through `bws_run_traversal()`.
 *   L2 read — by resolved-source KIND (post → post cores / bws_read_field, term → term
 *     cores / bws_read_term_field, site → option read).
 *
 * The N×M source classes (RelatedPost etc.) resolve only the deprecated tag wrappers.
 *
 * Term-ambient: a bare base tag on a term archive reads the TERM analog via
 * bws_base_term_analog_read().
 *
 * @package BWS_Dynamic_Tags
 * @since 1.6.0
 * @since 1.14.0 Resolution moved to the traversal pipeline.
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

	// Base tags author their source as a CHAIN (see bws_build_src_chain_option()).
	$source_opt     = bws_build_src_chain_option();
	// Same control with the collapsing capability (ADR 0007), for content/permalink/image:
	// the editor hides the per-step limit their render ignores.
	$source_opt_fu  = bws_build_src_chain_option( array( 'takes_first_usable' => true ) );
	// FANNING ADVISORY for the collapsing tags, at the end of the source group. The
	// control (src-chain-control.js) owns when it shows; the COPY lives here. Not on the
	// field note: fanning is the CHAIN's property, not a field's. Never serialized.
	$fan_advisory = array(
		'srcFanNote' => array(
			'type' => 'bws-fanning-advisory',
			'help' => __( 'This source configuration can match more than one item. Only the first item is read.', 'generateblocks' ),
		),
	);
	$traversal_opts = bws_base_traversal_options();
	// One field-option LEAF per tag with a read axis; consumers compose, never redefine.
	$text_field     = bws_get_text_field_options();
	$content_field  = bws_get_content_field_options();
	$image_field    = bws_get_image_field_options();

	// =========================================================
	// text — ACF/meta field or entity title; supports_list
	// =========================================================

	bws_gb_register_tag( array(
		'title'    => __( 'Text Fields', 'generateblocks' ),
		'tag'      => 'text',
		'type'     => 'cross-source',
		'supports' => array(),
		// Canonical CONTROL order: source → format → link → fallback (no format group).
		// Within source: src → ref → srcTermIn → sep → use → key (sep first: list length
		// is a source property). A stored `limit` ranks before sep (serialization-order.php).
		'options'  => bws_prepare_registration_options( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				// NO TAG-LEVEL `limit` CONTROL: a limit is stated where the source is, so
				// each chain step carries its own. A tag-level one would slice the flattened
				// walk at positions set by fan-out widths the author can't see. Unregistered
				// outright (the mount migrator chains flat wire before the panel paints).
				//
				// The VALUE is still read (`bws_clamp_limit`): unmigrated flat wire and
				// hand-edited wire still render it — removing a control never removes an
				// option (ADR 0004).
				//
				// `sep` STAYS (with `chain_fans`): it joins printed output, whatever the
				// source spelling.
				'sep'      => array(
					'type'        => 'text',
					'label'       => __( 'Result Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => array( 'ref', 'chain_fans' ) ),
				),
				// use/key/fixed from the text FIELD LEAF; show_if is the caller's overlay.
				'use'      => $text_field['use'],
				'key'      => array_merge(
					$text_field['key'],
					array(
						// Hidden for title and fixed. Under src:site, key-mode reads a
						// wp_options key (tagline: GB {{site_tagline}} or key:blogdescription).
						'show_if' => array( 'use' => 'not_in:title,fixed' ),
					)
				),
				'fixed'    => array_merge(
					$text_field['fixed'],
					array( 'show_if' => array( 'use' => 'fixed' ) )
				),
			),
			function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array(),
			array(
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
			)
		) ),
		'return'   => 'bws_base_text_callback',
	) );

	// =========================================================
	// content — post content, excerpt, or WYSIWYG field
	// =========================================================

	bws_gb_register_tag( array(
		'title'    => __( 'Content', 'generateblocks' ),
		'tag'      => 'content',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_prepare_registration_options( array_merge(
			$source_opt_fu,
			$traversal_opts,
			$fan_advisory,
			array(
				// use/key from the content FIELD LEAF; show_if is the caller's overlay.
				'use'      => $content_field['use'],
				'key'      => array_merge(
					$content_field['key'],
					array(
						// Under src:site, use:key reads a wp_options value (rich render);
						// default use:content → '' (site has no content analog).
						'show_if' => array(
							'use' => 'key',
						),
					)
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

	bws_gb_register_tag( array(
		'title'    => __( 'Title/Name', 'generateblocks' ),
		'tag'      => 'title',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_prepare_registration_options( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				// NO TAG-LEVEL `limit` control — see {{text}} above.
				'sep' => array(
					'type'        => 'text',
					'label'       => __( 'Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => array( 'ref', 'chain_fans' ) ),
				),
			),
			function_exists( 'bws_get_link_options' ) ? bws_get_link_options() : array()
		) ),
		'return'   => 'bws_base_title_callback',
	) );

	// =========================================================
	// permalink — post/entity URL; source traversal + srcTerm
	// =========================================================

	bws_gb_register_tag( array(
		'title'    => __( 'Permalink', 'generateblocks' ),
		'tag'      => 'permalink',
		'type'     => 'cross-source',
		'supports' => array(),
		// No `key`: permalink is the entity's own URL. {{permalink src:site}} → home_url();
		// URL-valued options via {{text src:site|key:...}}.
		'options'  => bws_prepare_registration_options( array_merge(
			$source_opt_fu,
			$traversal_opts,
			$fan_advisory
		) ),
		'return'   => 'bws_base_permalink_callback',
	) );

	// =========================================================
	// image — custom field or featured image.
	// `as` = folded return-mode + size (bws-as-size), always serialized; GB's native
	// image-size support is DROPPED (docs/tag-reference.md §`as` serialization opt-out).
	// `fallback`: image-tag-controls.js. `use` hidden under srcTerm (no featured image).
	// =========================================================

	bws_gb_register_tag( array(
		'title'    => __( 'Image', 'generateblocks' ),
		'tag'      => 'image',
		'type'     => 'cross-source',
		'supports' => array(),
		// Canonical CONTROL order: source → format → link(none) → fallback. `as` is FORMAT:
		// control-LATE, serialize-EARLY (the normalizer lifts it to the front).
		'options'  => bws_prepare_registration_options( array_merge(
			$source_opt_fu,
			$traversal_opts,
			$fan_advisory,
			array(
				// use/key from the image FIELD LEAF; both show_if are the caller's overlay.
				'use'      => array_merge(
					$image_field['use'],
					array( 'show_if' => array( 'srcTermIn' => 'empty' ) )
				),
				'key'      => array_merge(
					$image_field['key'],
					array(
						// Hidden for use:featured (under src:site → site logo).
						'show_if' => array( 'use' => 'not:featured' ),
					)
				),
				// `default` IS the always-serialize mechanism — never drop it: GB seeds
				// extraTagParams from non-empty defaults at tag-SELECT time
				// (DynamicTagSelect.jsx `updateDynamicTag`); the composite writes on CHANGE
				// only, and writing on mount would make opening a tag edit it. GB doesn't
				// validate defaults against the rows, so `url,full` seeds fine.
				'as'       => array(
					'type'    => 'bws-as-size',
					'label'   => __( 'Return As', 'generateblocks' ),
					'default' => 'url,full',
					'options' => array(
						array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
						array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
						array( 'value' => 'title',   'label' => __( 'Image Title', 'generateblocks' ) ),
						array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
						array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
					),
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

	bws_gb_register_tag( array(
		'title'    => __( 'Date/Time', 'generateblocks' ),
		'tag'      => 'datetime_single',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_prepare_registration_options( bws_get_base_datetime_single_options() ),
		'return'   => 'bws_base_datetime_single_callback',
	) );

	// =========================================================
	// datetime_range — start/end date/time range with mode switch
	// =========================================================

	bws_gb_register_tag( array(
		'title'    => __( 'Date/Time Range', 'generateblocks' ),
		'tag'      => 'datetime_range',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_prepare_registration_options( bws_get_base_datetime_range_options() ),
		'return'   => 'bws_base_datetime_range_callback',
	) );

	// =========================================================
	// join — standalone COMBINING tag. Up to BWS_JOIN_MAX_SLOTS `text` reads as slots,
	// non-empty values assembled into ONE string (separator or template mode).
	// =========================================================

	bws_gb_register_tag( array(
		'title'    => __( 'Join Fields', 'generateblocks' ),
		'tag'      => 'join',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_prepare_registration_options( bws_get_join_options() ),
		'return'   => 'bws_join_callback',
	) );

	// =========================================================
	// Register the base template descriptors.
	//
	// Stored in TagTemplateRegistry::$modifier_templates; consumed by
	// generate_base_try_tags() and (via get_modifier_templates()) the converter's
	// per-template migration entries.
	//
	// 'leading_options' — Group 1 options (as, size, format, etc.) prepended before slots in try_ tags.
	// 'options'         — template-specific options; for try_ tags, keys matching leading_options are
	//                     stripped so they don't appear twice; remaining keys become Group 3 trailing.
	// 'term_fn'         — fn($term_id, $opts, $inst) for the direct term-entity path.
	// 'post_fn'         — fn($post_id, $opts, $inst) for the ref-traversal path (term → post).
	// 'resolve_fn'      — the base tag's own resolve seam; every try_ attempt reads
	//                     through it, inheriting whatever the base read gains.
	// =========================================================

	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'text',
		'title'                 => __( 'Text Fields', 'generateblocks' ),
		'supports_link_wrap'    => true,
		'options'               => array_merge(
			// Same LEAF as base {{text}}. No LITERAL `show_if`: try_ derives it from
			// try_use_no_key_values below.
			$text_field,
			array(
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
			)
		),
		// Dispatchers, not bare cores: a bare core never reads `use`.
		'term_fn'               => 'bws_try_text_term_dispatch',
		'post_fn'               => 'bws_try_text_post_dispatch',
		'resolve_fn'            => 'bws_base_text_resolve_value',
		'try_allow_site_slot'   => true,
		'supports_try'          => true,
		'try_per_slot_key'      => true,
		'try_per_slot_use'      => true,
		'try_use_no_key_values' => array( 'title', 'fixed' ),
		'try_list_options'      => true,
		'is_image'              => false,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'content',
		'title'                 => __( 'Content', 'generateblocks' ),
		'options'               => array_merge(
			// Same LEAF as base {{content}}; no LITERAL `show_if` (as text).
			$content_field,
			array(
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if content is empty.', 'generateblocks' ),
				),
			)
		),
		// Dispatchers, not bare cores: bare cores read `type`, never `use`.
		'term_fn'               => 'bws_try_content_term_dispatch',
		'post_fn'               => 'bws_try_content_post_dispatch',
		'resolve_fn'            => 'bws_base_content_resolve_value',
		'try_allow_site_slot'   => true,
		'supports_try'          => true,
		'try_per_slot_key'      => true,
		'try_per_slot_use'      => true,
		'try_use_no_key_values' => array( 'content', 'excerpt' ),
		'is_image'              => false,
		'takes_first_usable'    => true,
	) );

	TagTemplateRegistry::register_modifier_template( array(
		'key'                => 'title',
		'title'              => __( 'Title/Name', 'generateblocks' ),
		'supports_link_wrap' => true,
		'options'            => array(),
		'term_fn'      => 'bws_term_title_core',
		'post_fn'      => 'bws_post_title_core',
		'resolve_fn'   => 'bws_base_title_resolve_value',
		'try_allow_site_slot' => true,
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
		'resolve_fn'   => 'bws_base_permalink_resolve_value',
		'try_allow_site_slot' => true,
		'supports_try' => true,
		'is_image'     => false,
		'takes_first_usable' => true,
	) );

	// image: generate_base_try_tags(): 'leading_options' (as, size) → slots → trailing from 'options' minus leading/per-slot keys.
	// 'use' kept in 'options' so generate_base_try_tags() reads its options for per-slot use selectors.
	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'image',
		'title'                 => __( 'Image', 'generateblocks' ),
		'leading_options'       => array(
			// `default` is load-bearing — see the base {{image}} registration.
			'as' => array(
				'type'    => 'bws-as-size',
				'label'   => __( 'Return As', 'generateblocks' ),
				'default' => 'url,full',
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
				'type'    => 'bws-as-size',
				'label'   => __( 'Return As', 'generateblocks' ),
				'default' => 'url,full',
				'options' => array(
					array( 'value' => 'url',     'label' => __( 'URL', 'generateblocks' ) ),
					array( 'value' => 'id',      'label' => __( 'ID', 'generateblocks' ) ),
					array( 'value' => 'title',   'label' => __( 'Image Title', 'generateblocks' ) ),
					array( 'value' => 'alt',     'label' => __( 'Alt Text', 'generateblocks' ) ),
					array( 'value' => 'caption', 'label' => __( 'Caption', 'generateblocks' ) ),
				),
			),
			// Same LEAF as base {{image}}; no literal `show_if` (as text).
			'use'      => $image_field['use'],
			'key'      => $image_field['key'],
			'fallback' => array(
				'type'  => 'bws-media-picker',
				'label' => __( 'Fallback Image', 'generateblocks' ),
			),
		),
		// Bare cores DELIBERATELY, unlike text/content: make_modifier_callback()'s
		// $image_post_dispatch closure already dispatches `use` before post_fn, and
		// `featured` is post-only so term_fn has nothing to dispatch. Do not "fix" this
		// without first removing $image_post_dispatch.
		'term_fn'               => 'bws_term_custom_image_core',
		'post_fn'               => 'bws_custom_image_core',
		'resolve_fn'            => 'bws_base_image_resolve_value',
		'try_allow_site_slot'   => true,
		'supports_try'          => true,
		'try_per_slot_key'      => true,
		'try_per_slot_use'      => true,
		'try_use_no_key_values' => array( 'featured' ),
		'is_image'              => true,
		'takes_first_usable'    => true,
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
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts )
				: $opts;
			return bws_term_datetime_single_core( $term_id, $mapped, $inst );
		},
		'post_fn'      => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts )
				: $opts;
			return bws_datetime_single_core( $post_id, $mapped, $inst );
		},
		'resolve_fn'   => 'bws_base_datetime_single_resolve_value',
		'try_allow_site_slot' => true,
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
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts, true )
				: $opts;
			return bws_term_datetime_range_core( $term_id, $mapped, $inst );
		},
		'post_fn'      => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts, true )
				: $opts;
			return bws_datetime_range_core( $post_id, $mapped, $inst );
		},
		'resolve_fn'   => 'bws_base_datetime_range_resolve_value',
		'try_allow_site_slot' => true,
		'supports_try' => true,
		'is_image'     => false,
	) );

	// Email/phone TEMPLATES register before try_ generation so try_email/try_phone come
	// from the shared machinery. Standalone {{email}}/{{phone}} register separately.
	if ( function_exists( 'bws_register_email_template' ) ) {
		bws_register_email_template();
	}
	if ( function_exists( 'bws_register_phone_template' ) ) {
		bws_register_phone_template();
	}

}

// ===============================================
// CALLBACKS
// ===============================================

/**
 * Resolve the `text` base tag's VALUE — the full read path minus link-wrap
 * and preview fallback.
 *
 * Resolves entity via `source`, applies srcTerm step when set, then
 * dispatches to the appropriate core function based on `use`:
 *
 * srcTerm + use unset   → bws_term_custom_text_core() (per-term; limit/sep applied)
 * srcTerm + use:title   → bws_term_title_core()        (per-term; limit/sep applied)
 * post    + use unset   → bws_post_custom_text_core()
 * post    + use:title   → bws_post_title_core()
 * rows    + use unset   → bws_row_custom_text_core()  (per-row; limit/sep applied)
 * rows    + use:title   → '' (analogs refuse on a row — it is not an entity)
 * any     + use:fixed   → bws_fixed_text_read() once per resolved source (FW-141)
 *
 * Every arm forks `use` through the try_ dispatchers (bws_try_text_*_dispatch): one
 * owner per source kind.
 *
 * ABSORB INVARIANT: the value stays byte-equivalent to {{text}} before link-wrap —
 * src:site arm, list modes (sep/limit), '0' preserved (hooks.php maps it downstream;
 * no emptiness re-decision here). Other tags absorb the text read here, so any text
 * read change lands here, never in a caller's copy.
 *
 * @since 1.14.1 Extracted from bws_base_text_callback().
 * @since 1.16.0 List branches ride bws_collect_value_list.
 * @since 1.21.0 The `meta_row` list branch.
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string} link_id 0 = the
 *                        caller must not link-wrap: either there is no entity, or the
 *                        value came from a list arm that already wrapped per item.
 */
function bws_base_text_resolve_value( array $options, $instance ): array {
	$res = bws_base_src_resolution( $options );

	// Site read — no entity; site value with sentinel link identity (id 1, 'site' type).
	if ( 'site' === $res['kind'] ) {
		return array(
			'value'     => bws_site_resolve_value( 'text', $options, $instance ),
			'link_id'   => 1,
			'link_type' => 'site',
		);
	}

	// L1 — resolve the base source once. Explicit src/loop/id already won inside the factory.
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED — read nothing. The empty triple IS the empty path (the callback then runs
	// preview label or fallback). Covers {{join}} slots too: the field drops out of the
	// composite rather than taking the current entry's value.
	if ( bws_base_read_refused( $res, $base, array( 'meta_row' ) ) ) {
		return array( 'value' => '', 'link_id' => 0, 'link_type' => 'post' );
	}

	// Ambient dispatch (term/author/query-context archive) through the one seam; its
	// triple IS this arm's return shape.
	$ambient = bws_base_ambient_analog( 'text', $base, $options, $instance );
	if ( null !== $ambient ) {
		return $ambient;
	}
	// The singular collapse is deferred into the singular arm: list branches run their
	// own traversal, and computing it here would run the chain twice.
	$link_id   = 0;
	$link_type = 'post';

	// List branches ride bws_collect_value_list (slice/drop/per-item link wrap/join), so
	// they leave link_id 0 — already wrapped. Per-item reads get 'fallback' unset: it
	// fires ONCE in the callback on all-empty output, never per item (else an empty item
	// injects linked fallback text into the list). Same contract as datetime and try_.
	// The singular arm keeps full $options (cores emit their own fallback).
	if ( 'term' === $res['kind'] ) {
		$collected = bws_collect_value_list(
			bws_base_term_ids_from_source( $base, $options ),
			static function ( $tid, array $item_opts ) use ( $instance ) {
				return array(
					'value' => bws_try_text_term_dispatch( (int) $tid, $item_opts, $instance ),
					'link'  => array( 'kind' => 'term', 'id' => (int) $tid ),
				);
			},
			$options
		);
		$value = $collected['value'];
	} elseif ( 'post' === $res['kind'] ) {
		// Post LIST mode: EVERY fanned-out target. Honors `sep` and any stored `limit`.
		$post_ids  = bws_base_post_ids_from_source( $base, $options );
		$collected = bws_collect_value_list(
			$post_ids,
			static function ( $pid, array $item_opts ) use ( $instance ) {
				return array(
					'value' => bws_try_text_post_dispatch( $pid, $item_opts, $instance ),
					'link'  => array( 'kind' => 'post', 'id' => (int) $pid ),
				);
			},
			$options
		);
		$value = $collected['value'];
	} elseif ( 'meta_row' === $res['kind'] ) {
		// REPEATER-ROW LIST: reads SOURCES, not ids (a row has no id).
		//
		// THE KIND TESTED IS THE WIRE'S ($res), NEVER THE RESOLVED BASE'S. `src(rows,…)`
		// asks for rows; a $base of that kind means the query loop put us INSIDE a row,
		// and the read must fall through to the post tail (bws_read_field()'s loop
		// inference). Conflating them deletes a live path (fold-test-matrix.md §F9c).
		//
		// bws_try_text_row_dispatch() owns the `use` fork, including the analog refusal
		// (`use:title` on a row → ''). No link identity for a row (CONTEXT.md I12).
		$collected = bws_collect_value_list(
			bws_base_sources_of_kind( $base, $options, 'meta_row' ),
			static fn( $row_source, array $item_opts ) => bws_try_text_row_dispatch( $row_source, $item_opts, $instance ),
			$options
		);
		$value = $collected['value'];
	} else {
		$post_id   = bws_base_post_id_from_source( $base, $options );
		$value     = bws_try_text_post_dispatch( $post_id, $options, $instance );
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
 * Shell over bws_base_text_resolve_value(): link-wrap singular results (list arms
 * wrap per item, link_id 0), then on empty output the preview label (editor) or the
 * fallback (front end).
 *
 * The fallback fires HERE, once, on all-empty output. Singular cores also emit it,
 * so this path fires for them only when the core produced nothing — inert either way.
 *
 * Preview label outranks the fallback in the editor (author sees the configuration).
 * Matches {{join}} and datetime.
 *
 * @since 1.6.0
 * @since 1.14.1 Value resolution extracted to bws_base_text_resolve_value().
 * @since 1.16.0 All-empty fallback path.
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

	if ( $is_preview && function_exists( 'bws_build_preview_label' ) ) {
		return bws_build_preview_label( $options, 'text' );
	}

	// All slots empty — apply the fallback. Never link-wrapped: it is not a
	// resolved entity's value, so there is no entity to link to.
	return bws_base_stated_fallback( $options, $instance );
}

/**
 * Build the {{join}} option definitions: one FOLDED key per slot (`A`, `B`, …)
 * followed by the tag-level assembly options.
 *
 * Slot definitions come from bws_build_fold_slot_options(); join supplies the
 * container facts. Cardinality is EXPLICIT (repeater add/remove). Legacy flat wire
 * still renders (the callback dual-reads it); the editor folds a slot on first touch.
 *
 * Per-slot `limit` lives on the step it bounds, inside the slot value.
 *
 * No per-slot inner `sep` (ADR 0003): a list-mode slot joins with text's default ', '.
 * Deferred scope, not blocked.
 *
 * @since 1.15.0
 * @since 1.17.0 Folded slot keys replace the flat per-slot keys.
 * @return array Option definitions keyed by option name.
 */
function bws_get_join_options(): array {
	$text_field = function_exists( 'bws_get_text_field_options' )
		? bws_get_text_field_options()
		: array( 'use' => array(), 'key' => array(), 'fixed' => array() );

	// `container`/`combining`/`per_slot_use`/`max`/`tag_level` come from
	// bws_join_fold_container(), which the MIGRATOR also reads — never re-type them here.
	// Everything below is registration-only.
	$options = function_exists( 'bws_build_fold_slot_options' ) && function_exists( 'bws_join_fold_container' )
		? bws_build_fold_slot_options(
			array_merge( bws_join_fold_container(), array(
				'min'             => 2,
				'base_read'       => $text_field['use'],
				'base_key'        => $text_field['key'],
				'base_fixed'      => $text_field['fixed'] ?? array(),
				'allow_site'      => true,
				// No `same` read row until per-slot handlers ship (a hand-written
				// `use(same)` still renders) — see bws_build_slot_read_options().
				'allow_same_read' => false,
				// A slot's source is a base tag's source ([I16]): same offer, asserted
				// equal by control-order-test.php §7.
				'steps'            => array( 'refs', 'terms', 'rows' ),
				// "+ Add field" and header "Field A".
				'noun'            => __( 'field', 'generateblocks' ),
			) )
		)
		: array();

	// Tag-level assembly options.
	$options['mode'] = array(
		'type'           => 'select',
		'label'          => __( 'Assembly Mode', 'generateblocks' ),
		'options'        => array(
			array( 'value' => '',         'label' => __( 'Separator', 'generateblocks' ) ),
			array( 'value' => 'template', 'label' => __( 'Template', 'generateblocks' ) ),
		),
		'_strip_default' => true,
	);
	$options['valueSep'] = array(
		'type'        => 'text',
		'label'       => __( 'Separator', 'generateblocks' ),
		'help'        => __( 'Text placed between non-empty values. Default: ", ".', 'generateblocks' ),
		'placeholder' => ', ',
		'show_if'     => array( 'mode' => 'not:template' ),
	);
	// %A (not {A}): GB's tag parser rejects `}` in options (docs/gb-constraints.md).
	// bws_join_wire_format() translates. The letter IS the slot's option key. Help
	// documents only letters; `%1` still resolves but one alphabet avoids mixing.
	$options['format'] = array(
		'type'        => 'text',
		'label'       => __( 'Format', 'generateblocks' ),
		'help'        => __( 'Format string using %A, %B … as positional tokens, matching the slot letters. Wrap a token and its unit text in tildes (~%E lbs.~) so they disappear together when the field is empty. Use %% for a literal percent sign before a slot letter, ~~ for a literal tilde.', 'generateblocks' ),
		'placeholder' => '%A (%B)',
		'show_if'     => array( 'mode' => 'template' ),
	);
	$options['fallback'] = array(
		'type'  => 'text',
		'label' => __( 'Fallback Text', 'generateblocks' ),
		'help'  => __( 'Text to display when all fields are empty.', 'generateblocks' ),
	);

	return $options;
}

/**
 * Callback for the {{join}} tag — the COLLECT-ALL slot loop.
 *
 * Visits every slot (never short-circuits), resolves each through the text read
 * (bws_join_resolve_slot → bws_base_text_resolve_value; no per-slot link-wrap), then
 * assembles via separator or template mode. All-empty → `fallback` (or '' so GB hides
 * the block).
 *
 * WIRE ERAS are decided per SLOT via the FOLD seam (bws_fold_slot_struct +
 * bws_fold_slot_chain_options): a folded slot can sit between legacy ones, and all feed
 * the ONE carry-forward accumulator.
 *
 * Carry-forward lives in the seam: source resolves `same` ('' / `same` = prior
 * source); the read never does unless `use(same)`; a read-less slot is skipped BEFORE
 * feeding the accumulator; a carried `ref` survives a non-ref source override.
 *
 * Never re-decides emptiness: empty is exactly ''; a stored '0' renders.
 *
 * @since 1.15.0
 * @since 1.17.0 Slots read through the folded-slot seam, dual-reading legacy wire.
 */
function bws_join_callback( $options, $block, $instance ): string {
	$values = array(); // 1-based; $values[$n] = finished slot string or ''.
	// The accumulator's source axis is a CHAIN, not a token — `src(same)` carries over the
	// prior slot's whole chain. The READ seeds the text leaf's stripped default, so a
	// slot stating no read resolves as a bare {{text}}.
	$carry  = bws_fold_empty_carry( bws_use_stripped_default( 'text' ) );

	// Tag-level explicit post id: GB's editor preview REST route injects `id:<postId>`
	// (get_the_ID() is false there). It lives at the JOIN level, so it must be threaded
	// into every post-based slot or they render empty in the editor. Inert on the front
	// end ('' there). CONTEXT.md I11.
	$explicit_id = $options['id'] ?? '';

	for ( $n = 1; $n <= BWS_JOIN_MAX_SLOTS; $n++ ) {
		// Slot configuration, whichever wire era holds it. Null = nothing configured
		// here (or unparsable folded wire) — the slot contributes nothing.
		$slot = function_exists( 'bws_fold_slot_struct' )
			? bws_fold_slot_struct( $n, (array) $options, 'join' )
			: null;
		if ( null === $slot ) {
			continue;
		}

		// Resolve to the absorb seam's option set, threading the ONE accumulator. Source
		// arrives as DEPTH-0 CHAIN WIRE in `src` (CONTEXT.md I16), `ref`/`srcTermIn`
		// emptied so nothing tag-level leaks in. Null = unconfigured or unfinished step:
		// renders nothing, feeds nothing. `valueSep` is NEVER passed through (ADR 0003).
		$skip_reason   = '';
		$limit_default = 1;
		$slot_opts     = bws_fold_slot_chain_options( $slot, $carry, true, $skip_reason, $limit_default );
		if ( null === $slot_opts ) {
			continue;
		}

		// A SLOT'S OWN SOURCE SPELLING DECIDES ITS LIMIT DEFAULT: chain wire → all, flat
		// → 1. `src` is chain wire even for a recovered flat slot, so the seam reports the
		// era and the resolved number is written back. Load-bearing — do not "simplify".
		$slot_opts['limit'] = (string) bws_clamp_limit( $slot_opts['limit'] ?? null, $limit_default );

		// Thread the injected id into every slot except entity-blind site reads. Test the
		// RESOLVED KIND, never the `src` token: chain wire can step off the site root ([I11]).
		if ( '' !== $explicit_id && 'site' !== bws_base_src_resolution( $slot_opts )['kind'] ) {
			$slot_opts['id'] = $explicit_id;
		}

		$values[ $n ] = bws_join_resolve_slot( $slot_opts, $instance );
	}

	$assembled = bws_join_assemble( $values, (array) $options );

	if ( '' === $assembled ) {
		// Editor: configuration preview outranks the fallback (as every base tag).
		$is_preview = ! empty( $instance->context['bwsEditorPreview'] );
		if ( $is_preview && function_exists( 'bws_build_join_preview_label' ) ) {
			return bws_build_join_preview_label( (array) $options );
		}
		return bws_base_stated_fallback( (array) $options, $instance );
	}
	return bws_gb_tag_output( $assembled, $options, $instance );
}

/**
 * Resolve the `content` base tag's VALUE — the full read path minus the preview label.
 *
 * Resolves entity via `source`, applies srcTerm step when set, then
 * dispatches based on `use`:
 *
 * srcTerm + use unset   → bws_term_description_core() (first non-empty term)
 * srcTerm + use:key     → bws_term_custom_text_core()  (term WYSIWYG field)
 * post    + use unset   → bws_post_content_core()
 * post    + use:excerpt → bws_post_excerpt_core()
 * post    + use:key     → bws_post_content_core() with type:custom_field
 * rows    + use:key     → bws_row_custom_text_core()  (FIRST row; collapsing)
 * rows    + use unset   → '' (analogs refuse on a row — it is not an entity)
 * rows    + use:excerpt → '' (same)
 *
 * THE FAMILY'S RESOLVE SEAM: `{{try_content}}` attempts run through it too
 * (`resolve_fn`); the shells on both sides own what the seam leaves out.
 *
 * Reads no `limit`: the one-result rule (`takes_first_usable`, ADR 0007) is enforced
 * above this function.
 *
 * THE PREVIEW QUESTION IS ASKED TWICE, where an arm must STOP:
 *   - REFUSAL arm: the fallback lives inside bws_post_content_core(), which a refusal
 *     must not call, so the arm emits it directly and answers '' in preview (the shell
 *     labels it). Same shape as image's.
 *   - AMBIENT arm: terminates in preview, falls THROUGH on the front end (FW-116).
 * Neither is guarded on function_exists( 'bws_build_preview_label' ): the guard belongs
 * with the emit, in the shell.
 *
 * No list mode, no link identity (`link_id` constant 0; rich markup isn't wrapped). The
 * triple is uniform across families so the attempt walk branches on nothing.
 *
 * @since 1.6.0
 * @since 1.21.0 The `meta_row` branch.
 * @since 1.21.0 Extracted from bws_base_content_callback().
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string}
 */
function bws_base_content_resolve_value( array $options, $instance ): array {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$use  = bws_use_effective( 'content', $options );
	$res  = bws_base_src_resolution( $options );
	// Local copy — the use:key arm sets $opts['type'] below.
	$opts = $options;
	$out  = array(
		'value'     => '',
		'link_id'   => 0,
		'link_type' => 'post',
	);

	// Site read — content option markup via shared pipeline (handled in resolver). No link wrap.
	if ( 'site' === $res['kind'] ) {
		$out['value'] = bws_site_resolve_value( 'content', $options, $instance );
		return $out;
	}

	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED — read nothing; fallback stated here, '' in preview (see PHPDoc).
	if ( bws_base_read_refused( $res, $base, array( 'meta_row' ) ) ) {
		$out['value'] = $is_preview ? '' : bws_base_stated_fallback( $options, $instance );
		return $out;
	}

	// Ambient dispatch. An EMPTY claim does NOT terminate on the front end (unlike other
	// tags): falling through lets bws_post_content_core's own falsy-id fallback emit run.
	// FW-116's per-tag fix — a wrong read here is evidence for FW-116, not a local repair.
	$ambient = bws_base_ambient_analog( 'content', $base, $options, $instance );
	if ( null !== $ambient ) {
		if ( '' !== $ambient['value'] ) {
			$out['value'] = $ambient['value']; // content is not link-wrapped (parity with post path below).
			return $out;
		}
		if ( $is_preview ) {
			return $out; // '' — the shell states the label; the front end falls through.
		}
	}
	if ( 'meta_row' === $res['kind'] ) {
		// REPEATER-ROW READ, collapsing: FIRST row of the unbounded fan.
		//
		// THE KIND TESTED IS THE WIRE'S ($res), NEVER THE BASE'S (see {{text}}; §F9c).
		//
		// Without this branch a `rows` chain falls to the post route and reads the current
		// post. A row has no content/excerpt, so analogs REFUSE and only use:key reads
		// (bws_try_content_row_dispatch owns the fork).
		$found        = bws_read_bounded_sources(
			bws_base_sources_of_kind( $base, $options, 'meta_row', true ),
			static fn( $row_source ) => bws_try_content_row_dispatch( $row_source, $opts, $instance ),
			1
		);
		$out['value'] = $found ? (string) $found[0] : '';
		return $out;
	}

	if ( 'term' === $res['kind'] ) {
		// takes_first_usable (ADR 0007): first read of the unbounded fan.
		$out['value'] = bws_base_term_first_usable(
			$base,
			$options,
			static fn( $tid ) => 'key' === $use
				? bws_term_custom_text_core( (int) $tid, $opts, $instance )
				: bws_term_description_core( (int) $tid, $opts, $instance )
		);
		return $out;
	}

	// POST route: same selector, whole compiled chain (not the wrapper's ref-only run).
	$out['value'] = bws_base_post_first_usable( $base, $options, static function ( $post_id ) use ( $use, $opts, $instance ) {
		if ( 'excerpt' === $use ) {
			return bws_post_excerpt_core( $post_id, $opts, $instance );
		}
		if ( 'key' === $use ) {
			$key_opts         = $opts;
			$key_opts['type'] = 'custom_field';
			return bws_post_content_core( $post_id, $key_opts, $instance );
		}
		return bws_post_content_core( $post_id, $opts, $instance );
	} );
	return $out;
}

/**
 * Callback for the `content` base tag.
 *
 * Shell over bws_base_content_resolve_value(): resolve the value, then on empty output the
 * editor preview label. No link wrap — this family registers no link options.
 *
 * NO STATED FALLBACK HERE, unlike bws_base_text_callback(): the fallback is already in
 * the seam's VALUE (cores and the refusal arm emit it). Hoisting it here would MOVE
 * OUTPUT for site reads, empty term fans and empty `rows` chains, which print nothing.
 *
 * @since 1.6.0
 * @since 1.21.0 Value resolution extracted to bws_base_content_resolve_value().
 */
function bws_base_content_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$value = bws_base_content_resolve_value( (array) $options, $instance )['value'];
	if ( '' !== $value ) {
		return $value;
	}

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'content' ) : '';
}

/**
 * Resolve the `title` base tag's VALUE — the full read path minus link-wrap and
 * the preview label.
 *
 * Resolves entity via `source`, applies srcTerm step when set.
 * srcTerm iterates terms with limit/sep applied.
 *
 * THE FAMILY'S RESOLVE SEAM: `{{try_title}}` attempts run through it too (`resolve_fn`).
 *
 * Link wrap: list arms wrap per item (bws_collect_value_list) and report `link_id` 0; a
 * singular read reports its entity and the shell wraps once.
 *
 * @since 1.6.0
 * @since 1.16.0 List branches ride bws_collect_value_list.
 * @since 1.21.0 Extracted from bws_base_title_callback().
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string} link_id 0 = the caller must
 *                        not link-wrap: either there is no entity, or the value came from
 *                        a list arm that already wrapped per item.
 */
function bws_base_title_resolve_value( array $options, $instance ): array {
	$res = bws_base_src_resolution( $options );

	// Site read — title base tag has no `use`; resolver returns site name. Sentinel
	// link identity (id 1, 'site' type), the same pair the text seam reports.
	if ( 'site' === $res['kind'] ) {
		return array(
			'value'     => bws_site_resolve_value( 'title', $options, $instance ),
			'link_id'   => 1,
			'link_type' => 'site',
		);
	}

	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED (GH #75/#76/#109) — read nothing. The empty triple IS this arm's own empty
	// path: {{title}} registers no `fallback` option, so the shell's whole empty path is
	// the preview label and a refusal takes it exactly as a read that found nothing does.
	if ( bws_base_read_refused( $res, $base ) ) {
		return array( 'value' => '', 'link_id' => 0, 'link_type' => 'post' );
	}

	// Ambient dispatch; the seam's triple IS this arm's return shape (term and user both
	// link-wrap on the derived identity).
	$ambient = bws_base_ambient_analog( 'title', $base, $options, $instance );
	if ( null !== $ambient ) {
		return $ambient;
	}
	// Singular collapse deferred into the singular arm (list branches traverse themselves).
	$link_id   = 0;
	$link_type = 'post';

	// List branches ride bws_collect_value_list. Fallback suppression is inert here
	// (title cores never read 'fallback').
	if ( 'term' === $res['kind'] ) {
		$collected = bws_collect_value_list(
			bws_base_term_ids_from_source( $base, $options ),
			static function ( $tid, array $item_opts ) use ( $instance ) {
				return array(
					'value' => bws_term_title_core( (int) $tid, $item_opts, $instance ),
					'link'  => array( 'kind' => 'term', 'id' => (int) $tid ),
				);
			},
			$options
		);
		$value = $collected['value'];
	} elseif ( 'post' === $res['kind'] ) {
		// Post LIST mode: EVERY fanned-out target, honoring limit/sep.
		$post_ids  = bws_base_post_ids_from_source( $base, $options );
		$collected = bws_collect_value_list(
			$post_ids,
			static function ( $pid, array $item_opts ) use ( $instance ) {
				return array(
					'value' => bws_post_title_core( $pid, $item_opts, $instance ),
					'link'  => array( 'kind' => 'post', 'id' => (int) $pid ),
				);
			},
			$options
		);
		$value = $collected['value'];
	} else {
		$post_id   = bws_base_post_id_from_source( $base, $options );
		$value     = bws_post_title_core( $post_id, $options, $instance );
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
 * Callback for the `title` base tag.
 *
 * Shell over bws_base_title_resolve_value(): link-wrap singular results, then on empty
 * output the editor preview label.
 *
 * NO STATED FALLBACK HERE — {{title}} registers no `fallback` option, so the empty path
 * is the label or nothing at all.
 *
 * @since 1.6.0
 * @since 1.21.0 Value resolution extracted to bws_base_title_resolve_value().
 */
function bws_base_title_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$resolved = bws_base_title_resolve_value( (array) $options, $instance );
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

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'title' ) : '';
}

/**
 * Resolve the `permalink` base tag's VALUE.
 *
 * Resolves entity via `source`, applies srcTerm step when set.
 * srcTerm returns first non-empty term URL.
 *
 * THE FAMILY'S RESOLVE SEAM: `{{try_permalink}}` attempts run through it too
 * (`resolve_fn`). Reads no `limit` (`takes_first_usable`, ADR 0007, enforced above).
 *
 * No link identity (`link_id` constant 0): the value IS a URL.
 *
 * @since 1.21.0 Extracted from bws_base_permalink_callback().
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string}
 */
function bws_base_permalink_resolve_value( array $options, $instance ): array {
	$res = bws_base_src_resolution( $options );
	$out = array(
		'value'     => '',
		'link_id'   => 0,
		'link_type' => 'post',
	);

	// Site read — site_url/home_url/option via resolver. No link wrap (permalink not link-eligible).
	if ( 'site' === $res['kind'] ) {
		$out['value'] = bws_site_resolve_value( 'permalink', $options, $instance );
		return $out;
	}

	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED — read nothing. No fallback and no preview label (a bracketed placeholder
	// would break the href), so '' is the whole empty path.
	if ( bws_base_read_refused( $res, $base ) ) {
		return $out;
	}

	// Ambient dispatch; no tail, so the seam's value is the whole return.
	$ambient = bws_base_ambient_analog( 'permalink', $base, $options, $instance );
	if ( null !== $ambient ) {
		$out['value'] = $ambient['value'];
		return $out;
	}

	if ( 'term' === $res['kind'] ) {
		// takes_first_usable (ADR 0007): search the WHOLE fan — every step limit is
		// stripped at compile — and output the first usable term URL.
		$out['value'] = bws_base_term_first_usable(
			$base,
			$options,
			static fn( $tid ) => bws_term_permalink_core( (int) $tid, $options, $instance )
		);
		return $out;
	}

	// POST route: first read off the whole fan (ADR 0007).
	$out['value'] = bws_base_post_first_usable(
		$base,
		$options,
		static fn( $post_id ) => bws_post_permalink_core( $post_id, $options, $instance )
	);
	return $out;
}

/**
 * Callback for the `permalink` base tag.
 *
 * Shell over bws_base_permalink_resolve_value(); no fallback, no preview label (see the
 * seam's refusal note), so the seam's value is the whole return.
 *
 * @since 1.6.0
 * @since 1.21.0 Value resolution extracted to bws_base_permalink_resolve_value().
 * @param array  $options  Tag options.
 * @param object $block    Block instance (unused).
 * @param object $instance GB tag instance.
 * @return string
 */
function bws_base_permalink_callback( $options, $block, $instance ): string {
	return bws_base_permalink_resolve_value( (array) $options, $instance )['value'];
}

/**
 * Resolve the `image` base tag's VALUE — the full read path minus the preview label.
 *
 * Resolves entity via `source`, applies srcTerm step when set, then
 * dispatches based on `use`:
 *
 * srcTerm              → bws_term_custom_image_core() (first usable term)
 * post + use unset     → bws_custom_image_core()
 * post + use:featured  → bws_featured_image_core()
 *
 * `use:featured` is hidden in the editor when srcTerm is set (terms have no
 * featured image), so that branch is unreachable in normal usage.
 *
 * THE FAMILY'S RESOLVE SEAM: `{{try_image}}` attempts run through it too
 * (`resolve_fn`). Reads no `limit` (`takes_first_usable`, ADR 0007, enforced above).
 *
 * THE STATED FALLBACK STAYS IN THE VALUE (unlike text): the cores emit it
 * (bws_image_stated_fallback) on a falsy id, so a fallback image is a NON-EMPTY read and
 * doesn't yield to the next attempt. Arms reaching no core (`meta_row`, refusal) emit it
 * themselves. Lifting it into the shell would MOVE OUTPUT (site with no logo, empty term
 * fan print nothing today).
 *
 * No list mode, no link identity (`link_id` constant 0).
 *
 * @since 1.21.0 Extracted from bws_base_image_callback().
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string}
 */
function bws_base_image_resolve_value( array $options, $instance ): array {
	$use = bws_use_effective( 'image', $options );
	$res = bws_base_src_resolution( $options );
	$out = array(
		'value'     => '',
		'link_id'   => 0,
		'link_type' => 'post',
	);

	// Site read — logo/option via resolver (logo already routed through the boundary).
	if ( 'site' === $res['kind'] ) {
		$out['value'] = bws_site_resolve_value( 'image', $options, $instance );
		return $out;
	}

	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED — read nothing; emit the fallback directly (no core may be reached).
	// THE ONLY PREVIEW TEST IN THIS SEAM: the one arm where label and fallback image can
	// both apply. '' in preview hands the shell an empty read to label.
	if ( bws_base_read_refused( $res, $base, array( 'meta_row' ) ) ) {
		$out['value'] = empty( $instance->context['bwsEditorPreview'] )
			? bws_image_stated_fallback( $options, $instance )
			: '';
		return $out;
	}

	// Ambient dispatch. User/query_context aren't claimed for image (see the seam's
	// PHPDoc): they fall through to the post route's fallback emit.
	$ambient = bws_base_ambient_analog( 'image', $base, $options, $instance );
	if ( null !== $ambient ) {
		// The term core already tried the fallback; only the shell's label remains.
		$out['value'] = $ambient['value'];
		return $out;
	}
	if ( 'meta_row' === $res['kind'] ) {
		// REPEATER-ROW READ, collapsing: FIRST row of the unbounded fan (as {{content}}).
		//
		// THE KIND TESTED IS THE WIRE'S ($res), NEVER THE BASE'S (see {{text}}; §F9c).
		//
		// RAW seam read: an ACF image sub-field is an ARRAY, which the string seam drops.
		// The analog (`featured`) refuses on a row.
		//
		// FALLBACK EMITTED HERE, not in the core (a row has no id to merge): covers both
		// an imageless row and a repeater with no rows, like the post route.
		$found = bws_read_bounded_sources(
			bws_base_sources_of_kind( $base, $options, 'meta_row', true ),
			static fn( $row_source ) => bws_try_image_row_dispatch( $row_source, $options, $instance ),
			1
		);
		$out['value'] = $found ? (string) $found[0] : bws_image_stated_fallback( $options, $instance );
		return $out;
	}

	if ( 'term' === $res['kind'] ) {
		// takes_first_usable (ADR 0007): search the WHOLE fan — every step limit is
		// stripped at compile — and output the first usable term image. The cores
		// keep their per-read stated-fallback semantics untouched: a stated fallback
		// image is a non-empty read, exactly as it was for this loop's predecessor.
		$out['value'] = bws_base_term_first_usable(
			$base,
			$options,
			static fn( $tid ) => bws_term_custom_image_core( (int) $tid, $options, $instance )
		);
		return $out;
	}

	// POST route: first usable image off the whole fan — same rule as the term
	// route (ADR 0007). The helper keeps today's single falsy-id read on an
	// empty fan.
	$out['value'] = bws_base_post_first_usable(
		$base,
		$options,
		static fn( $post_id ) => 'featured' === $use
			? bws_featured_image_core( $post_id, $options, $instance )
			: bws_custom_image_core( $post_id, $options, $instance )
	);
	return $out;
}

/**
 * Callback for the `image` base tag.
 *
 * Shell over bws_base_image_resolve_value(): resolve the value, then on empty output the
 * editor preview label. No link wrap — this family registers no link options.
 *
 * NO STATED FALLBACK HERE, unlike bws_base_text_callback(). The fallback is part of the
 * seam's VALUE on every arm that has one (the seam's PHPDoc says which, and why hoisting it
 * up here would move output), so a value that arrives empty has already been through
 * whatever fallback there was to try.
 *
 * @since 1.6.0
 * @since 1.21.0 Value resolution extracted to bws_base_image_resolve_value().
 */
function bws_base_image_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$value = bws_base_image_resolve_value( (array) $options, $instance )['value'];
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
 * Dispatch by `use`, UNIFORM with every other source (Model B): the `use` VALUE is the
 * analog-vs-option lever, `use:key` a wp_options read. There is NO `use:option` value.
 *
 * Do NOT branch the analog on `'' === $key` (B5: it made `use` dead under site).
 *
 * @invariant Site option reads (the use:key branch) MUST pass
 * bws_site_allowlist_ok() before GenerateBlocks_Meta_Handler::get_option() (via
 * the canonical bws_site_read_option reader). The allowlist is GB-parity-seeded
 * (NOT empty) — see bws_site_allowlist_ok and
 * docs/adr/0001-site-option-read-allowlist.md.
 *
 * THE B6 REGRESSION HAPPENED HERE (worked instance; the rule is owned by
 * BWS_USE_STRIPPED_DEFAULTS in registration-helpers.php). This dispatcher branched on
 * the literal empty string, silently dropping the option read for every tag whose
 * stripped default IS key-mode: an unset `use` is the FIRST enum value, never a third
 * "no use" state. So `use` is canonicalized before any branch. title/permalink have no
 * `use` enum and canonicalize to ''.
 *
 * Per-tag site dispatch (default = the tag's stripped first enum value):
 *   - title     → site name (get_bloginfo('name'))       [tag has no use enum]
 *   - text      → DEFAULT 'key' → option (key:X); use:title → name; empty key → ''
 *   - content   → no site content analog (B7): DEFAULT 'content' and use:excerpt → ''
 *                 (tagline: GB native {{site_tagline}}). use:key → option (rich render).
 *   - permalink → ALWAYS home_url(); `key` ignored
 *   - image     → DEFAULT 'key' → option attachment-id (no key → ''); site LOGO is the
 *                 EXPLICIT use:featured (respects as/size). Logo-as-default is FW-143.
 *
 * @since 1.9.0
 * @param string $tag      Base tag name: text|title|permalink|image|content.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
 * @return string Resolved value, or '' on miss / disallowed.
 */
function bws_site_resolve_value( string $tag, array $options, $instance ): string {
	$key = (string) ( $options['key'] ?? '' );

	// Canonicalize `use` before any branch (B6; see PHPDoc).
	$use = bws_use_effective( $tag, $options );

	// title base tag (no `use` enum) and text use:title → site name.
	if ( 'title' === $tag || 'title' === $use ) {
		return (string) get_bloginfo( 'name' );
	}

	// text use:fixed → the author's text. Text only: other tags ignore a hand-typed one.
	if ( 'text' === $tag && 'fixed' === $use ) {
		return bws_fixed_text_read( $options, $instance );
	}

	// permalink = the entity's own URL, never an option read; `key` ignored.
	if ( 'permalink' === $tag ) {
		return (string) home_url();
	}

	// use:key → wp_options read via the shared gated reader (allowlist + dot-path + ACF).
	if ( 'key' === $use ) {
		$raw = bws_site_read_option( $key );
		// content: route block/HTML option markup through the shared content
		// pipeline (do_blocks + sanitize + recursion guard), keyed 'option:KEY'.
		if ( 'content' === $tag && function_exists( 'bws_render_block_content' ) ) {
			return bws_render_block_content( $raw, 'option:' . $key );
		}
		return $raw;
	}

	// Analog `use` tokens: the intrinsic site analog per tag.
	switch ( $tag ) {
		case 'content':
			// No site content analog (B7; see PHPDoc).
			return '';

		case 'image':
			// use:featured (default) → site logo (post→featured parallel).
			$logo_id = (int) get_theme_mod( 'custom_logo' );
			if ( ! $logo_id || ! function_exists( 'bws_get_attachment_data' ) ) {
				return '';
			}
			// `as` may carry a `,<size>` arg; legacy `size:` falls back.
			$as     = function_exists( 'bws_parse_as_option' )
				? bws_parse_as_option( $options )
				: array( 'mode' => $options['as'] ?? 'url', 'size' => $options['size'] ?? 'full' );
			$result = bws_get_attachment_data(
				$logo_id,
				$as['mode'],
				$as['size']
			);
			if ( empty( $result ) ) {
				return '';
			}
			// GB output boundary for parity with the image tag. The guard keeps a
			// GB-less install returning the bare value rather than fataling.
			return class_exists( 'GenerateBlocks_Dynamic_Tag_Callbacks' )
				? (string) bws_gb_tag_output( $result, $options, $instance )
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
 *
 * @since 1.6.0
 */
function bws_try_text_post_dispatch( $post_id, $options, $instance ) {
	$use = bws_use_effective( 'text', $options );
	if ( 'title' === $use ) {
		return bws_post_title_core( $post_id, $options, $instance );
	}
	if ( 'fixed' === $use ) {
		return $post_id ? bws_fixed_text_read( $options, $instance ) : '';
	}
	return bws_post_custom_text_core( $post_id, $options, $instance );
}

/**
 * Try-tag repeater-ROW-slot dispatch for `text` template.
 *
 * A row is not an entity, so `use:title` REFUSES (empty). Spell the hop instead:
 * `rows,team_members;refs,lead_ref` then `use:title`.
 *
 * Takes the resolved SOURCE, not an id (a row has none).
 *
 * @since 1.21.0
 */
function bws_try_text_row_dispatch( $source, $options, $instance ) {
	$use = bws_use_effective( 'text', $options );
	if ( 'title' === $use ) {
		return '';
	}
	if ( 'fixed' === $use ) {
		return bws_fixed_text_read( $options, $instance );
	}
	return bws_row_custom_text_core( (array) $source, $options, $instance );
}

/**
 * Try-tag srcTermIn-slot dispatch for `text` template.
 *
 * @since 1.6.0
 */
function bws_try_text_term_dispatch( $term_id, $options, $instance ) {
	$use = bws_use_effective( 'text', $options );
	if ( 'title' === $use ) {
		return bws_term_title_core( $term_id, $options, $instance );
	}
	if ( 'fixed' === $use ) {
		return $term_id ? bws_fixed_text_read( $options, $instance ) : '';
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
	$use = bws_use_effective( 'content', $options );
	if ( 'excerpt' === $use ) {
		return bws_post_excerpt_core( $post_id, $options, $instance );
	}
	if ( 'key' === $use ) {
		$opts         = $options;
		$opts['type'] = 'custom_field';
		return bws_post_content_core( $post_id, $opts, $instance );
	}
	return bws_post_content_core( $post_id, $options, $instance );
}

/**
 * Try-tag repeater-ROW-slot dispatch for `content` template.
 *
 * Both analogs REFUSE (a row has no content/excerpt); only use:key reads. Via
 * bws_row_custom_text_core(): {{content|use:key}} and {{text|use:key}} read a key by
 * one rule, differing only in empty-read fallback, which a row read doesn't emit.
 *
 * Takes the resolved SOURCE, not an id (a row has none).
 *
 * @since 1.21.0
 */
function bws_try_content_row_dispatch( $source, $options, $instance ) {
	if ( 'key' !== bws_use_effective( 'content', $options ) ) {
		return '';
	}
	return bws_row_custom_text_core( (array) $source, $options, $instance );
}

/**
 * Try-tag srcTermIn-slot dispatch for `content` template.
 *
 * @since 1.6.0
 */
function bws_try_content_term_dispatch( $term_id, $options, $instance ) {
	$use = bws_use_effective( 'content', $options );
	if ( 'key' === $use ) {
		return bws_term_custom_text_core( $term_id, $options, $instance );
	}
	// content (default) and excerpt both fall back to term description on terms.
	return bws_term_description_core( $term_id, $options, $instance );
}

/**
 * Try-tag post-slot dispatch for `image` template.
 *
 * Reads $options['use'] to route between featured-image and custom-field modes.
 *
 * @since 1.6.0
 */
function bws_try_image_post_dispatch( $post_id, $options, $instance ) {
	$use = bws_use_effective( 'image', $options );
	if ( 'featured' === $use ) {
		return bws_featured_image_core( $post_id, $options, $instance );
	}
	return bws_custom_image_core( $post_id, $options, $instance );
}

/**
 * Try-tag repeater-ROW-slot dispatch for `image` template.
 *
 * `featured` REFUSES (a row has no featured image; reading one would print the
 * surrounding post's). Spell the hop: `rows,team_members;refs,lead_ref` + `use:featured`.
 *
 * Takes the resolved SOURCE, not an id. Also called by the BASE arm, so both read alike.
 *
 * @since 1.21.0
 */
function bws_try_image_row_dispatch( $source, $options, $instance ) {
	if ( 'featured' === bws_use_effective( 'image', $options ) ) {
		return '';
	}
	return bws_row_custom_image_core( (array) $source, $options, $instance );
}
