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
 *   L1 steps — `src:ref` appends a generic `ref` step (ACF relationship step,
 *     plural), `srcTermIn` a term-step step; run through `bws_run_traversal()`.
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

	// Base tags author their source as a CHAIN (FW-56): a root plus ordered fanning
	// steps. The derived families keep the plain select — see bws_build_src_chain_option().
	$source_opt     = bws_build_src_chain_option();
	$traversal_opts = bws_base_traversal_options();
	$text_field     = bws_get_text_field_options();

	// =========================================================
	// text — ACF/meta field or entity title; supports_list
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Text Fields', 'generateblocks' ),
		'tag'      => 'text',
		'type'     => 'cross-source',
		'supports' => array(),
		// Canonical CONTROL order (FW-52): source → format → link → fallback.
		// text has no format group. Within source: src → ref → srcTermIn → limit → sep
		// → use → key (limit/sep before field keys — list length is a source property).
		'options'  => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				// List mode applies when the SOURCE FANS. The two tokens named below were
				// the only fanning spellings before source chains; `chain_fans` asks the
				// question they stood in for, so a chain-spelled source reveals the same
				// pair. Not cosmetic since 1.17.0: chain wire defaults its cap to
				// unlimited and a migrated tag carries an explicit `limit:1`, so a
				// control the author cannot see is a cap the author cannot clear.
				// Ordered before the field keys (list length is a source property, FW-52).
				'limit'    => array(
					'type'        => 'number',
					'label'       => __( 'Result Limit', 'generateblocks' ),
					'help'        => __( 'Maximum number of results to return. Enter 0 for no limit. Left blank: one result, unless the source is a path, which returns all of them.', 'generateblocks' ),
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => array( 'ref', 'chain_fans' ) ),
				),
				'sep'      => array(
					'type'        => 'text',
					'label'       => __( 'Result Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => array( 'ref', 'chain_fans' ) ),
				),
				// use/key from the text FIELD LEAF (single source; the template, join
				// and the folded control consume the same builder). show_if is the
				// caller's overlay by leaf contract.
				'use'      => $text_field['use'],
				'key'      => array_merge(
					$text_field['key'],
					array(
						// Key-mode = empty/'key'. Hidden for named data (title).
						// Under src:site, key-mode reads a wp_options key. Site tagline has
						// NO tag path (B7): GB native {{site_tagline}} or key:blogdescription
						// (nothing unique to add until multislot-feed decouple — see #26).
						'show_if' => array( 'use' => 'not:title' ),
					)
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
				// List mode applies when the SOURCE FANS. The two tokens named below were
				// the only fanning spellings before source chains; `chain_fans` asks the
				// question they stood in for, so a chain-spelled source reveals the same
				// pair. Not cosmetic since 1.17.0: chain wire defaults its cap to
				// unlimited and a migrated tag carries an explicit `limit:1`, so a
				// control the author cannot see is a cap the author cannot clear.
				'limit' => array(
					'type'        => 'number',
					'label'       => __( 'Limit', 'generateblocks' ),
					'help'        => __( 'Maximum number of results to return. Enter 0 for no limit. Left blank: one result, unless the source is a path, which returns all of them.', 'generateblocks' ),
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => array( 'ref', 'chain_fans' ) ),
				),
				'sep'   => array(
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
	// `as` is the folded return-mode + size token (bws-as-size, FW-52), always
	// serialized (`as:url,<size>` for url; bare mode for nullary returns). The
	// composite owns the whole `as` widget; GB's native image-size support is DROPPED
	// (size folds into `as`'s value — see docs/tag-reference.md §`as` serialization
	// opt-out + assets/js/as-size-control.js).
	// `fallback` uses custom JS control (image-tag-controls.js).
	// `use:featured` hidden when srcTerm set — terms have no featured image.
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Image', 'generateblocks' ),
		'tag'      => 'image',
		'type'     => 'cross-source',
		'supports' => array(),
		// Canonical CONTROL order (FW-52): source → format → link(none) → fallback.
		// `as` is a FORMAT option: control-LATE (after source/field), serialize-EARLY
		// (the normalizer lifts it to the front of the string for copy-visibility — the
		// `as` serialization opt-out means it is always present). Its `size` argument
		// rides inside the `as` value (as+size fold) — no separate size option.
		'options'  => bws_strip_default_select_values( array_merge(
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
				// Folded return-mode + size. The bws-as-size composite renders the mode
				// dropdown + a size dropdown (url only) and owns the whole token. No
				// `default` (always-serialized; the composite writes url,full on open).
				'as'       => array(
					'type'    => 'bws-as-size',
					'label'   => __( 'Return type:', 'generateblocks' ),
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
	// join — standalone COMBINING tag (third structural position: neither a
	// base tag nor a modifier). Absorbs up to BWS_JOIN_MAX_SLOTS base `text`
	// reads as slots and assembles all non-empty values into ONE string
	// (separator or template mode). One GB tag — no prefix fan-out, no
	// per-source variants. Shares the base-tag picker group for UX only.
	// =========================================================

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Join Fields', 'generateblocks' ),
		'tag'      => 'join',
		'type'     => 'cross-source',
		'supports' => array(),
		'options'  => bws_strip_default_select_values( bws_get_join_options() ),
		'return'   => 'bws_join_callback',
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
	// 'try_site_fn'     — fn($opts, $inst) for try_ src:site slot dispatch (FW-4): thin
	//                     closure over bws_site_resolve_value('<tag>') for templates whose
	//                     try_core_fn is site-blind. email/phone omit it (their core rides
	//                     the seam, which reads site) — registry falls back to $cf(0,…).
	// =========================================================

	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'text',
		'title'                 => __( 'Text Fields', 'generateblocks' ),
		'supports_link_wrap'    => true,
		'options'               => array_merge(
			// Same LEAF the base {{text}} registration consumes — the template is a
			// different COMPOSITION, not a second definition. No `show_if` overlay
			// here: try_ encodes the identical fact declaratively below
			// (try_use_no_key_values) and re-qualifies it per slot.
			$text_field,
			array(
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
			)
		),
		'term_fn'               => 'bws_term_custom_text_core',
		'post_fn'               => 'bws_post_custom_text_core',
		'try_core_fn'           => 'bws_try_text_post_dispatch',
		'try_term_fn'           => 'bws_try_text_term_dispatch',
		'try_site_fn'           => static fn( $opts, $inst ) => bws_site_resolve_value( 'text', (array) $opts, $inst ),
		'try_allow_site_slot'   => true,
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
		'try_site_fn'           => static fn( $opts, $inst ) => bws_site_resolve_value( 'content', (array) $opts, $inst ),
		'try_allow_site_slot'   => true,
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
		'try_site_fn'  => static fn( $opts, $inst ) => bws_site_resolve_value( 'title', (array) $opts, $inst ),
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
		'try_core_fn'  => 'bws_post_permalink_core',
		'try_term_fn'  => 'bws_term_permalink_core',
		'try_site_fn'  => static fn( $opts, $inst ) => bws_site_resolve_value( 'permalink', (array) $opts, $inst ),
		'try_allow_site_slot' => true,
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
			// Folded return-mode + size (bws-as-size, FW-52). No `default` — always
			// serialized; the composite writes url,full on open.
			'as' => array(
				'type'    => 'bws-as-size',
				'label'   => __( 'Return image as:', 'generateblocks' ),
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
				'label'   => __( 'Return image as:', 'generateblocks' ),
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
		'try_site_fn'           => static fn( $opts, $inst ) => bws_site_resolve_value( 'image', (array) $opts, $inst ),
		'try_allow_site_slot'   => true,
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
		'try_core_fn'  => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts )
				: $opts;
			return bws_datetime_single_core( $post_id, $mapped, $inst );
		},
		'try_term_fn'  => static function ( $term_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts )
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
		'try_core_fn'  => static function ( $post_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts, true )
				: $opts;
			return bws_datetime_range_core( $post_id, $mapped, $inst );
		},
		'try_term_fn'  => static function ( $term_id, $opts, $inst ) {
			$mapped = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $opts, true )
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
 * Resolves entity via `source`, applies srcTerm step when set, then
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
 * @since 1.16.0 List branches ride the shared bws_collect_value_list fold (FW-49).
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string} link_id 0 =
 *                        multi-result output; caller must not link-wrap.
 */
function bws_base_text_resolve_value( array $options, $instance ): array {
	$use = $options['use'] ?? 'key';
	$res = bws_base_src_resolution( $options );

	// Site read — no entity; site value with sentinel link identity (id 1, 'site' type).
	if ( 'site' === $res['kind'] ) {
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
	// Ambient author archive → user analog/meta read (FW-48 seam half, 1.16.0).
	// Mirrors title/content's user arms; closing it HERE closes it for every
	// ABSORB-seam reader ({{join}} slots, try_text) at once.
	$user_id = function_exists( 'bws_base_ambient_user_id' ) ? bws_base_ambient_user_id( $base, $options ) : 0;
	if ( $user_id ) {
		return array(
			'value'     => bws_base_user_analog_read( 'text', $user_id, $options, $instance ),
			'link_id'   => $user_id,
			'link_type' => 'user',
		);
	}
	// Both list branches run their own plural traversal below, so the collapsing
	// resolve is deferred into the singular arms — computing it here would run the
	// chain twice (review #3).
	$link_id   = 0;
	$link_type = 'post';

	// List branches ride the shared fold (FW-49): slice/suppress/drop/link-gate/
	// join live in bws_collect_value_list. Per-item reads get $item_opts with
	// 'fallback' unset — it fires ONCE in the callback on all-empty output,
	// never per item (GH #51: else an empty term/post inside the limit window
	// injects the fallback text into the list, and a lone fallback would pass
	// the single-result link gate as though it were a real value). Matches
	// datetime's contract and try_'s (TagTemplateRegistry). The singular arms
	// below keep the full $options: no list to pollute, and the cores' own
	// fallback emit is the shipped behavior there.
	if ( 'term' === $res['kind'] ) {
		$collected = bws_collect_value_list(
			bws_base_term_ids_from_source( $base, $options ),
			static function ( $tid, array $item_opts ) use ( $use, $instance ) {
				$result = 'title' === $use
					? bws_term_title_core( (int) $tid, $item_opts, $instance )
					: bws_term_custom_text_core( (int) $tid, $item_opts, $instance );
				return array(
					'value' => $result,
					'link'  => array( 'kind' => 'term', 'id' => (int) $tid ),
				);
			},
			$options
		);
		$value = $collected['value'];
		if ( $collected['link'] ) {
			$link_id   = (int) $collected['link']['id'];
			$link_type = $collected['link']['kind'];
		}
	} elseif ( 'post' === $res['kind'] ) {
		// Post LIST mode (SPEC §V14): read EVERY fanned-out target, not just the
		// first. limit/sep are offered whenever the chain fans, so honor them —
		// mirrors the term branch.
		$post_ids  = bws_base_post_ids_from_source( $base, $options );
		$collected = bws_collect_value_list(
			$post_ids,
			static function ( $pid, array $item_opts ) use ( $use, $instance ) {
				$result = 'title' === $use
					? bws_post_title_core( $pid, $item_opts, $instance )
					: bws_post_custom_text_core( $pid, $item_opts, $instance );
				return array(
					'value' => $result,
					'link'  => array( 'kind' => 'post', 'id' => (int) $pid ),
				);
			},
			$options
		);
		$value = $collected['value'];
		if ( $collected['link'] ) {
			$link_id   = (int) $collected['link']['id'];
			$link_type = $collected['link']['kind'];
		}
	} elseif ( 'title' === $use ) {
		$post_id   = bws_base_post_id_from_source( $base, $options );
		$value     = bws_post_title_core( $post_id, $options, $instance );
		$link_id   = (int) $post_id;
		$link_type = 'post';
	} else {
		$post_id   = bws_base_post_id_from_source( $base, $options );
		$value     = bws_post_custom_text_core( $post_id, $options, $instance );
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
 * single-result output, then on empty output apply the editor preview label
 * (editor) or the fallback (front end).
 *
 * The fallback fires HERE, once, on all-empty output — the list loops in
 * bws_base_text_resolve_value() suppress it per item (GH #51). Singular reads
 * still emit it from inside the core, so this path only fires for them when the
 * core produced nothing at all; `''` either way, so the double route is inert.
 *
 * Preview label outranks the fallback in the editor: the author needs to see the
 * tag's configuration, not the masked-empty output. Matches {{join}} and
 * datetime.
 *
 * @since 1.6.0
 * @since 1.14.1 Value resolution extracted to bws_base_text_resolve_value().
 * @since 1.16.0 All-empty fallback path (GH #51) — list mode no longer emits the
 *               fallback per item.
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
	$fallback = sanitize_text_field( $options['fallback'] ?? '' );
	return '' !== $fallback
		? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
		: '';
}

/**
 * Build the {{join}} option definitions: one FOLDED key per slot (`A`, `B`, …,
 * FW-56/57) followed by the tag-level assembly options.
 *
 * The slot definitions come from bws_build_fold_slot_options(), which derives every
 * enum and label from the shipped builders and hands them to the `bws-slot-fold`
 * repeater control. Join supplies the container facts: combining, site arm allowed,
 * one term step, no read-inherit row, and the slot noun.
 *
 * WHAT THE FOLD REPLACED, and why the reveal machinery went with it: through 1.16.x
 * this registered SIX flat keys per slot (`{N}-src`/`ref`/`srcTermIn`/`use`/`key`/
 * `limit`, slot 1 bare) plus a combining-shaped `show_if_any` reveal that armed slot
 * N+1 once slot N had a key or a non-default use. Cardinality is now EXPLICIT
 * (add/remove in the repeater) rather than inferred from how far configuration got, so
 * the reveal predicates have nothing left to express. Legacy wire still renders — the
 * callback dual-reads it — and the editor rewrites a slot to folded form on first
 * touch.
 *
 * Per-slot `limit` moved INTO the slot value, attached to the step it caps (a chain
 * can fan more than once, so a slot-level cap has no single meaning). It has no
 * control surface yet; a migrated or hand-written one round-trips untouched.
 *
 * No per-slot inner `sep` (ADR 0003): a list-mode slot joins its own items with
 * text's default ', '. The original blocker — a slot-1 bare `sep` colliding with the
 * tag-level assembly `sep` on GB's flat option map — dissolved twice over, first when
 * the assembly key was renamed to `valueSep` (1.16.0, FW-52) and again under the fold,
 * where a slot's options live inside its own value. Still deferred scope.
 *
 * @since 1.15.0
 * @since 1.17.0 Folded slot keys replace the six flat per-slot keys (FW-56/57).
 * @return array Option definitions keyed by option name.
 */
function bws_get_join_options(): array {
	$text_field = function_exists( 'bws_get_text_field_options' )
		? bws_get_text_field_options()
		: array( 'use' => array(), 'key' => array() );

	// FOLDED slot keys (`A`, `B`, …) — one option per slot, the whole slot in its
	// value. Replaces the six flat keys per slot join registered through 1.16.x; the
	// renderer dual-reads the old wire, and the editor rewrites a slot to folded form
	// the first time it is touched.
	// `container`/`combining`/`per_slot_use`/`max`/`tag_level` come from
	// bws_join_fold_container() — the MIGRATOR reads the same array, and a hand-kept
	// second copy of `max` or `tag_level` disagrees with it silently. Everything below is
	// registration-only (control shape, labels, enums), which the migrator has no use for.
	$options = function_exists( 'bws_build_fold_slot_options' ) && function_exists( 'bws_join_fold_container' )
		? bws_build_fold_slot_options(
			array_merge( bws_join_fold_container(), array(
				'min'             => 2,
				'base_read'       => $text_field['use'],
				'base_key'        => $text_field['key'],
				// Site arm allowed: join is standalone, so the base source list passes
				// through whole (the try_ site filter is a modifier-only concern).
				'allow_site'      => true,
				// No read inherit row yet — per-slot HANDLERS are not built, which is
				// also why bws_build_slot_read_options() is called at $allow_same=false
				// (see its PHPDoc; `use(same)` is legal in combining and the renderer
				// honors a hand-written one, it just has no UI row until handlers ship).
				'allow_same_read' => false,
				// The flat render seam expresses one term step; a second relationship step
				// is FW-32 work, so it is not offered.
				'steps'            => array( 'srcTermIn' ),
				// One noun, both surfaces: "+ Add field" and the header "Field A"
				// (bws_build_fold_slot_options derives the header — no label parameter).
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
	// %A (not {A}) — GB's tag parser rejects `}` anywhere in a tag's options
	// (find_matches captures options as [^}]+; docs/gb-constraints.md), so the
	// wire token syntax is brace-free. bws_join_wire_format() translates.
	//
	// The token letter IS the slot's option key, which is the whole reason it is a
	// letter: `A:key(x)|format:%A` reads as one statement. The help names only the
	// canonical spelling — `%1` still resolves, but documenting two alphabets would
	// invite authors to mix them in one string.
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
 * Visits every slot (never short-circuits — the combining counterpart to
 * try_'s selecting fold), resolves each through the absorbed text read
 * (bws_join_resolve_slot → bws_base_text_resolve_value; link identity
 * ignored, no per-slot link-wrap), then assembles via separator or template
 * mode. All-empty output falls back to `fallback` (or '' so GB's
 * empty-render handling hides the block).
 *
 * WIRE ERAS. Slot configuration reads through the FOLD seam
 * (bws_fold_slot_struct + bws_fold_slot_flat_options), which resolves each slot from
 * its folded value when it has one and recovers it from the legacy flat keys when it
 * does not. Era is decided per SLOT, not per tag: a half-applied migration or a
 * hand-edit can leave slot 2 folded between legacy slots 1 and 3, and both feed the
 * ONE carry-forward accumulator this loop holds.
 *
 * Carry-forward semantics are unchanged and now live in the seam: source inherits
 * ('' / `same` src = prior resolved source), the read never inherits unless the wire
 * says `use(same)`, a read-less slot is unconfigured and is skipped BEFORE it can feed
 * the accumulator, and a carried `ref` survives a non-ref source override (inert
 * there, but a later slot stepping back to the same relationship needs it).
 *
 * Join never re-decides value emptiness: "empty" is exactly '' everywhere,
 * and a stored '0' renders (base text's shipped falsy-guard, absorbed).
 *
 * @since 1.15.0
 * @since 1.17.0 Slots read through the folded-slot seam, dual-reading legacy wire.
 */
function bws_join_callback( $options, $block, $instance ): string {
	$values = array(); // 1-based; $values[$n] = finished slot string or ''.
	$carry  = array( 'src' => '', 'ref' => '', 'use' => '', 'key' => '' );

	// Tag-level explicit post id — GB's editor preview REST route injects
	// `id:<postId>` into the tag string so `get_id()` (whose post fallback is
	// get_the_ID(), false in the REST context) resolves the edited post. That id
	// lives at the JOIN level; each slot builds its own option set, so it must be
	// threaded into every post-based slot below or the current/ref slots resolve
	// empty in the editor (showing only the preview label, unlike the sibling
	// {{text}}). Inert on the front end — GB injects `id` only in the editor, so
	// there $explicit_id is '' and the loop/ambient context (I9) resolves instead.
	// This is CONTEXT.md I11 (composing-tag id-threading); see also the join
	// $slot_opts['id'] assignment for the src:site exclusion.
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

		// Flatten to the option set the absorb seam consumes, threading the ONE
		// carry-forward accumulator. Null = the slot is unconfigured (combining reads
		// an absent field as "not set yet") or states a chain with no flat spelling —
		// either way it renders nothing AND does not feed the accumulator. (The 1.17.0
		// chain compiler does not change that; see bws_fold_slot_flat_options().)
		// Join's tag-level `valueSep` (assembly) is NEVER passed through: a list-mode
		// slot joins its own items with text's default ', ' (ADR 0003).
		$slot_opts = bws_fold_slot_flat_options( $slot, $carry, true );
		if ( null === $slot_opts ) {
			continue;
		}

		// Thread the editor's injected post id into every post-based slot (see
		// $explicit_id note). src:ref bases its step on this id too (the current
		// post is the ref origin), so it must carry. Only src:site is entity-blind
		// — it reads an option, never a post — so the id is left off there.
		if ( '' !== $explicit_id && 'site' !== ( $slot_opts['src'] ?? '' ) ) {
			$slot_opts['id'] = $explicit_id;
		}

		$values[ $n ] = bws_join_resolve_slot( $slot_opts, $instance );
	}

	$assembled = bws_join_assemble( $values, (array) $options );

	if ( '' === $assembled ) {
		// Editor-time: the target fields rarely exist on the editing context, so
		// show the configuration preview (target fields + assembly, with the
		// fallback annotated) rather than the literal fallback — the author needs
		// to see the config, not the masked-empty output. Front end below shows
		// the real fallback. Matches every other base tag's preview ordering.
		$is_preview = ! empty( $instance->context['bwsEditorPreview'] );
		if ( $is_preview && function_exists( 'bws_build_join_preview_label' ) ) {
			return bws_build_join_preview_label( (array) $options );
		}
		$fallback = sanitize_text_field( $options['fallback'] ?? '' );
		return '' !== $fallback
			? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
			: '';
	}
	return GenerateBlocks_Dynamic_Tag_Callbacks::output( $assembled, $options, $instance );
}

/**
 * Callback for the `content` base tag.
 *
 * Resolves entity via `source`, applies srcTerm step when set, then
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
	$res  = bws_base_src_resolution( $options );
	// Local copy — the use:key arm sets $opts['type'] below.
	$opts = $options;

	// Site read — content option markup via shared pipeline (handled in resolver). No link wrap.
	if ( 'site' === $res['kind'] ) {
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
	// Ambient author archive → biographical info analog (#19 author kind, 1.15.0).
	$user_id = function_exists( 'bws_base_ambient_user_id' ) ? bws_base_ambient_user_id( $base, $options ) : 0;
	if ( $user_id ) {
		$value = bws_base_user_analog_read( 'content', $user_id, $options, $instance );
		if ( '' !== $value ) {
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'content' ) : '';
	}
	if ( 'term' === $res['kind'] ) {
		// content has no list mode — first non-empty term wins (unchanged).
		foreach ( bws_base_term_ids_from_source( $base, $options ) as $tid ) {
			$result = 'key' === $use
				? bws_term_custom_text_core( (int) $tid, $opts, $instance )
				: bws_term_description_core( (int) $tid, $opts, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		$value = '';
	} else {
		$post_id = bws_base_post_id_from_source( $base, $options );
		if ( 'excerpt' === $use ) {
			$value = bws_post_excerpt_core( $post_id, $opts, $instance );
		} elseif ( 'key' === $use ) {
			$opts['type'] = 'custom_field';
			$value = bws_post_content_core( $post_id, $opts, $instance );
		} else {
			$value = bws_post_content_core( $post_id, $opts, $instance );
		}
	}

	if ( '' !== $value ) {
		return $value;
	}

	return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'content' ) : '';
}

/**
 * Callback for the `title` base tag.
 *
 * Resolves entity via `source`, applies srcTerm step when set.
 * srcTerm iterates terms with limit/sep applied.
 *
 * @since 1.6.0
 * @since 1.16.0 List branches ride the shared bws_collect_value_list fold (FW-49).
 */
function bws_base_title_callback( $options, $block, $instance ): string {
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$res      = bws_base_src_resolution( $options );
	$link_to  = $options['linkTo'] ?? 'none';
	$link_key = $options['linkKey'] ?? '';
	$new_tab  = ! empty( $options['newTab'] );

	// Site read — title base tag has no `use`; resolver returns site name. Link-wrap.
	if ( 'site' === $res['kind'] ) {
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
	// Ambient author archive → display name analog (#19 author kind, 1.15.0).
	$user_id = function_exists( 'bws_base_ambient_user_id' ) ? bws_base_ambient_user_id( $base, $options ) : 0;
	if ( $user_id ) {
		$value = bws_base_user_analog_read( 'title', $user_id, $options, $instance );
		if ( '' !== $value ) {
			// User archives have a canonical URL (get_author_posts_url); wrap when
			// the author asked for a link, mirroring the term branch.
			if ( function_exists( 'bws_wrap_with_link' ) ) {
				$value = bws_wrap_with_link( $value, $link_to, $link_key, $new_tab, $user_id, 'user' );
			}
			return $value;
		}
		return $is_preview && function_exists( 'bws_build_preview_label' ) ? bws_build_preview_label( $options, 'title' ) : '';
	}
	// Both list branches run their own plural traversal, so the collapsing resolve
	// is deferred into the singular arm (review #3).
	$link_id   = 0;
	$link_type = 'post';

	// List branches ride the shared fold (FW-49). Fallback suppression is inert
	// here — the title cores never read 'fallback' (unlike the text cores).
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
		if ( $collected['link'] ) {
			$link_id   = (int) $collected['link']['id'];
			$link_type = $collected['link']['kind'];
		}
	} elseif ( 'post' === $res['kind'] ) {
		// Post LIST mode (SPEC §V14): read EVERY fanned-out target, honoring
		// limit/sep — mirrors the term branch above.
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
		if ( $collected['link'] ) {
			$link_id   = (int) $collected['link']['id'];
			$link_type = $collected['link']['kind'];
		}
	} else {
		$post_id   = bws_base_post_id_from_source( $base, $options );
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
 * Resolves entity via `source`, applies srcTerm step when set.
 * srcTerm returns first non-empty term URL.
 *
 * @since 1.6.0
 */
function bws_base_permalink_callback( $options, $block, $instance ): string {
	$res = bws_base_src_resolution( $options );

	// Site read — site_url/home_url/option via resolver. No link wrap (permalink not link-eligible).
	if ( 'site' === $res['kind'] ) {
		return bws_site_resolve_value( 'permalink', $options, $instance );
	}

	// L1 base source (SPEC §V1); ambient term archive → term URL analog (§V7).
	$base    = bws_base_resolve_source_for_callback( $options, $instance );
	$term_id = bws_base_ambient_term_id( $base, $options );
	if ( $term_id ) {
		return bws_base_term_analog_read( 'permalink', $term_id, $options, $instance );
	}

	if ( 'term' === $res['kind'] ) {
		// permalink has no list mode — first non-empty term URL wins (unchanged).
		foreach ( bws_base_term_ids_from_source( $base, $options ) as $tid ) {
			$result = bws_term_permalink_core( (int) $tid, $options, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		return '';
	}

	return bws_post_permalink_core( bws_base_post_id_from_source( $base, $options ), $options, $instance );
}

/**
 * Callback for the `image` base tag.
 *
 * Resolves entity via `source`, applies srcTerm step when set, then
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
	$res = bws_base_src_resolution( $options );

	// Site read — logo/option via resolver (logo already routed through GB ::output()).
	if ( 'site' === $res['kind'] ) {
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
	if ( 'term' === $res['kind'] ) {
		// image has no list mode — first non-empty term image wins (unchanged).
		foreach ( bws_base_term_ids_from_source( $base, $options ) as $tid ) {
			$result = bws_term_custom_image_core( (int) $tid, $options, $instance );
			if ( '' !== $result ) {
				return $result;
			}
		}
		$value = '';
	} else {
		$post_id = bws_base_post_id_from_source( $base, $options );
		$value   = 'featured' === $use
			? bws_featured_image_core( $post_id, $options, $instance )
			: bws_custom_image_core( $post_id, $options, $instance );
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
			// as+size fold (FW-52): `as` may carry a `,<size>` arg; legacy `size:` falls back.
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
	$use = $options['use'] ?? 'content';
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
 * Try-tag srcTermIn-slot dispatch for `content` template.
 *
 * @since 1.6.0
 */
function bws_try_content_term_dispatch( $term_id, $options, $instance ) {
	$use = $options['use'] ?? 'content';
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
	$use = $options['use'] ?? 'key';
	if ( 'featured' === $use ) {
		return bws_featured_image_core( $post_id, $options, $instance );
	}
	return bws_custom_image_core( $post_id, $options, $instance );
}
