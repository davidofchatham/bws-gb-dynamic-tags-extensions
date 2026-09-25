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
 *     resolves the ambient/explicit base resolved source: a query-loop item of a shape
 *     it knows → ambient term (term archive) → current post, or an explicit `src:site` /
 *     registry source. An item of any OTHER shape ENDS the precedence instead of
 *     continuing down it: the read is refused, not answered from ambient (step 2e in
 *     that function, which owns why).
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
	// The SAME chain control with the collapsing capability set (ADR 0007) — one extra
	// build call shared by content/permalink/image, so the editor suppresses the
	// per-step limit control on exactly the tags whose render ignores it.
	$source_opt_fu  = bws_build_src_chain_option( array( 'takes_first_usable' => true ) );
	// Group-end FANNING ADVISORY for the collapsing tags (ADR 0007 pass two). One line
	// at the end of the source group, shown only when the chain actually fans — the
	// editor control (src-chain-control.js) owns the conditional; the COPY lives here.
	// The field configuration note cannot carry this fact: it is attached to a FIELD,
	// while fanning is the CHAIN's property (a terms step has no field key at all).
	// `srcFanNote` holds no value and is never serialized; the control only displays.
	$fan_advisory = array(
		'srcFanNote' => array(
			'type' => 'bws-fanning-advisory',
			'help' => __( 'This source configuration can match more than one item. Only the first item is read.', 'generateblocks' ),
		),
	);
	$traversal_opts = bws_base_traversal_options();
	// One field-option LEAF per tag with a read axis; the base registration and the
	// modifier template below are two COMPOSITIONS of each, never two definitions.
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
		// Canonical CONTROL order (FW-52): source → format → link → fallback.
		// text has no format group. Within source: src → ref → srcTermIn → sep → use
		// → key (sep before the field keys — list length is a source property). The
		// tag-level `limit` CONTROL retired in 1.17.0 (#62); the KEY still ranks between
		// srcTermIn and sep when stored wire carries one (serialization-order.php).
		'options'  => bws_prepare_registration_options( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				// NO TAG-LEVEL `limit` (#62). A LIMIT IS STATED WHERE THE SOURCE IS STATED:
				// this tag authors its source as a CHAIN, so each fanning step carries its
				// own limit and a tag-level one is never useful — with one fanning step it
				// is the same knob as that step's, and with two it slices the flattened
				// walk at a position set by fan-out widths the author cannot see,
				// parent-major only because bws_run_traversal happens to iterate that way.
				// The flat-select families state one step, so `term_*` keeps the key (#63).
				//
				// UNREGISTERED, not gated on flat wire: the mount migrator rewrites a flat
				// tag to a chain before the panel paints, so a flat-only predicate would be
				// effectively unreachable.
				//
				// The VALUE is still read (`bws_clamp_limit`): unmigrated flat wire has no
				// other bound, and hand-edited chain wire carrying one still renders it —
				// removing a control never removes an option (ADR 0004; GB seeds state
				// from the tag string, not the registry). Migration carries an author's
				// number onto the STEPS, so nothing arrives here needing to be cleared.
				//
				// `sep` STAYS, and keeps `chain_fans`: it joins printed output, which a
				// chain does as much as a flat source, so it has no "which step" question
				// to answer. Ordered before the field keys (list length is a source
				// property, FW-52).
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
						// Key-mode only (use:key). Under src:site, use:key reads a wp_options
						// value (rich render); use:content default → '' (site has no content
						// analog — B7; tagline has no tag path, use GB {{site_tagline}}).
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
				// NO TAG-LEVEL `limit` (#62) — same call as {{text}} above, and the full
				// reasoning is there: a chain states its limits on its STEPS, the value is
				// still read wherever it is written, and `sep` stays because it joins
				// printed output whatever the source spelling.
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
		// No `key` control under src:site — permalink is the source entity's own URL,
		// never an arbitrary option read. Bare {{permalink src:site}} → home_url()
		// (V9 narrowed: URL-valued options reachable via {{text src:site|key:...}}).
		'options'  => bws_prepare_registration_options( array_merge(
			$source_opt_fu,
			$traversal_opts,
			$fan_advisory
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

	bws_gb_register_tag( array(
		'title'    => __( 'Image', 'generateblocks' ),
		'tag'      => 'image',
		'type'     => 'cross-source',
		'supports' => array(),
		// Canonical CONTROL order (FW-52): source → format → link(none) → fallback.
		// `as` is a FORMAT option: control-LATE (after source/field), serialize-EARLY
		// (the normalizer lifts it to the front of the string for copy-visibility — the
		// `as` serialization opt-out means it is always present). Its `size` argument
		// rides inside the `as` value (as+size fold) — no separate size option.
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
						// use:key → custom-field (post/term) or wp_options (site) read.
						// Hidden for use:featured, which under src:site → site logo (V9, resolver).
						'show_if' => array( 'use' => 'not:featured' ),
					)
				),
				// Folded return-mode + size. The bws-as-size composite renders the mode
				// dropdown + a size dropdown (url only) and owns the whole token.
				//
				// `default` IS the always-serialize mechanism, and it is not decorative:
				// GB seeds extraTagParams from every non-empty `default` at tag-SELECT
				// time (DynamicTagSelect.jsx `updateDynamicTag`), which is the only thing
				// that puts an untouched `as` on the wire. The fold dropped it in 1.16.0
				// on the theory that the composite would write on mount; it writes on
				// CHANGE only, so `{{image}}` serialized no `as` at all. Mount-writing
				// instead would mean opening a tag edits it — see the fold control's
				// stripDefaultRoot for why that is the wrong trade.
				//
				// GB does not validate a default against the option rows, so the folded
				// `url,full` seeds fine even though it is not one of them.
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
	// join — standalone COMBINING tag (third structural position: neither a
	// base tag nor a modifier). Absorbs up to BWS_JOIN_MAX_SLOTS base `text`
	// reads as slots and assembles all non-empty values into ONE string
	// (separator or template mode). One GB tag — no prefix fan-out, no
	// per-source variants. Shares the base-tag picker group for UX only.
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
	// Each descriptor is stored in TagTemplateRegistry::$modifier_templates and consumed by
	// generate_base_try_tags() (generates try_* GB tags) and, through
	// get_modifier_templates(), by the converter's per-template migration entries. The
	// term_ constructor was the other consumer until register_modifier() was withdrawn
	// in 1.21.0; the list and the key name outlived it.
	//
	// 'leading_options' — Group 1 options (as, size, format, etc.) prepended before slots in try_ tags.
	// 'options'         — template-specific options; for try_ tags, keys matching leading_options are
	//                     stripped so they don't appear twice; remaining keys become Group 3 trailing.
	// 'term_fn'         — fn($term_id, $opts, $inst) for the direct term-entity path.
	// 'post_fn'         — fn($post_id, $opts, $inst) for the ref-traversal path (term → post).
	// 'resolve_fn'      — the base tag's own resolve seam; every try_ attempt reads
	//                     through it (FW-136).
	// =========================================================

	TagTemplateRegistry::register_modifier_template( array(
		'key'                   => 'text',
		'title'                 => __( 'Text Fields', 'generateblocks' ),
		'supports_link_wrap'    => true,
		'options'               => array_merge(
			// Same LEAF the base {{text}} registration consumes — the template is a
			// different COMPOSITION, not a second definition. No LITERAL `show_if`
			// here: try_'s per-slot picker qualifies on try_use_no_key_values below
			// (#88), so the fact is declared once and derived, never hand-copied.
			$text_field,
			array(
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if the field is empty or not found.', 'generateblocks' ),
				),
			)
		),
		// term_fn/post_fn dispatch on `use` (#88): a bare core never read it, so
		// `use:title` rendered empty on every term_/view_/fixture_ text tag. The
		// try_ family's own per-slot dispatchers already do this correctly —
		// reused here rather than duplicated.
		'term_fn'               => 'bws_try_text_term_dispatch',
		'post_fn'               => 'bws_try_text_post_dispatch',
		// THE RESOLVE SEAM (FW-136). An attempt reads through the same function
		// {{text}} does, so `try_text` inherits whatever the base read gains — per-item
		// link wrap (FW-85/FW-135) being the first.
		'resolve_fn'            => 'bws_base_text_resolve_value',
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
		'options'               => array_merge(
			// Same LEAF the base {{content}} registration consumes; no LITERAL `show_if`
			// overlay here — try_ derives it from try_use_no_key_values below (#88),
			// same as the text template.
			$content_field,
			array(
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Text', 'generateblocks' ),
					'help'  => __( 'Text to display if content is empty.', 'generateblocks' ),
				),
			)
		),
		// term_fn/post_fn dispatch on `use` (#88): bare cores read `type`, never `use`
		// (a different key content's own base registration wires but the modifier
		// template never did), so use:key/use:excerpt silently rendered the post
		// content instead of empty or the right value on every term_/view_/fixture_
		// content tag. Reuses the try_ family's own per-slot dispatchers.
		'term_fn'               => 'bws_try_content_term_dispatch',
		'post_fn'               => 'bws_try_content_post_dispatch',
		// FW-136 — try_content resolves each attempt through the BASE seam, so an attempt
		// reads exactly as {{content}} does: the whole fan searched for its first usable
		// read, the repeater-row branch that refuses the analogs a row cannot answer, and
		// the cores' own stated-fallback emit.
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
		// THE RESOLVE SEAM (FW-136) — try_title resolves each attempt through the BASE
		// seam, so a fanning attempt inherits the fold's per-item link wrap (FW-135) the
		// way {{title}} already does.
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
		// FW-136 — try_permalink resolves each attempt through the BASE seam.
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
			// Folded return-mode + size (bws-as-size, FW-52). `default` carries the
			// always-serialize rule — see the base {{image}} registration above for why
			// it is load-bearing rather than decorative.
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
			// Same LEAF the base {{image}} registration consumes. No literal `show_if`
			// here either (#88): try_ derives it from try_use_no_key_values below,
			// same as text/content.
			'use'      => $image_field['use'],
			'key'      => $image_field['key'],
			'fallback' => array(
				'type'  => 'bws-media-picker',
				'label' => __( 'Fallback Image', 'generateblocks' ),
			),
		),
		// post_fn is deliberately NOT bws_try_image_post_dispatch, unlike text/content
		// (#88): make_modifier_callback() already carries its own `use`-dispatch closure
		// ($image_post_dispatch) ahead of calling post_fn, predating #88, so post_fn
		// staying the bare core is correct here, not a relapse. term_fn has no such
		// closure and needs none — `featured` is a post-only concept, so there is
		// nothing to dispatch. Do not "fix" this to match text/content without first
		// removing $image_post_dispatch.
		'term_fn'               => 'bws_term_custom_image_core',
		'post_fn'               => 'bws_custom_image_core',
		// FW-136 — try_image resolves each attempt through the BASE seam, so an attempt
		// reads exactly as {{image}} does: the whole fan searched for its first usable
		// picture, the repeater-row read that preserves an array return format, and the
		// cores' own stated-fallback emit.
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
		// FW-136 — try_datetime_single resolves each attempt through the BASE seam, so a
		// fanning attempt inherits the fold's per-item link wrap (FW-135) the way
		// {{datetime_single}} already does.
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
		// FW-136 — try_datetime_range resolves each attempt through the BASE seam, so a
		// fanning attempt inherits the fold's per-item link wrap (FW-135) the way
		// {{datetime_range}} already does.
		'resolve_fn'   => 'bws_base_datetime_range_resolve_value',
		'try_allow_site_slot' => true,
		'supports_try' => true,
		'is_image'     => false,
	) );

	// Register the email/phone modifier TEMPLATES (descriptors) before try_ generation,
	// so try_email/try_phone fall out of the shared machinery. The standalone
	// {{email}}/{{phone}} GB tags register separately
	// (bws_register_email_tag/_phone_tag). [SPEC §32]
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
 *
 * The rows arm dispatches the `use` fork through bws_try_text_row_dispatch(), which is
 * the try_ row arm's function too — one owner for the fork, as with the term/post pair.
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
 * @since 1.21.0 The `meta_row` list branch — a `rows` chain reads its rows (FW-74).
 *
 * @param array $options  Tag options.
 * @param mixed $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string} link_id 0 = the
 *                        caller must not link-wrap: either there is no entity, or the
 *                        value came from a list arm that already wrapped per item.
 */
function bws_base_text_resolve_value( array $options, $instance ): array {
	$use = bws_use_effective( 'text', $options );
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
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED (GH #75/#76/#109) — read nothing. The empty triple IS this arm's own empty
	// path: bws_base_text_callback() then runs the preview label or the stated fallback
	// exactly as it does for a read that found nothing. Covers {{join}}'s slots too,
	// which absorb their read through this seam rather than through an arm of their own —
	// a combining tag drops that field from the composite rather than substituting the
	// current entry's value.
	if ( bws_base_read_refused( $res, $base, array( 'meta_row' ) ) ) {
		return array( 'value' => '', 'link_id' => 0, 'link_type' => 'post' );
	}

	// Ambient dispatch (term archive → analog, author archive → user analog/meta,
	// FW-48 seam half) through the one kind-dispatching seam. Closing it HERE closes
	// it for every ABSORB-seam reader — which is {{join}}'s slots, and NOT try_text: a
	// try_ slot runs its own dispatcher, whose arms are wired separately (#108). The
	// seam's triple IS this arm's return shape, tail and all.
	$ambient = bws_base_ambient_analog( 'text', $base, $options, $instance );
	if ( null !== $ambient ) {
		return $ambient;
	}
	// Both list branches run their own plural traversal below, so the collapsing
	// resolve is deferred into the singular arms — computing it here would run the
	// chain twice (review #3).
	$link_id   = 0;
	$link_type = 'post';

	// List branches ride the shared fold (FW-49): slice/suppress/drop/per-item
	// link wrap/join live in bws_collect_value_list, which is why these branches
	// leave link_id at 0 — their values are wrapped already. Per-item reads get
	// $item_opts with 'fallback' unset — it fires ONCE in the callback on all-empty
	// output, never per item (GH #51: else an empty term/post inside the limit window
	// injects the fallback text into the list, and would be linked as though it were
	// a real value it is not). Matches datetime's contract and try_'s
	// (TagTemplateRegistry). The singular arms below keep the full $options: no list
	// to pollute, and the cores' own fallback emit is the shipped behavior there.
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
	} elseif ( 'post' === $res['kind'] ) {
		// Post LIST mode (SPEC §V14): read EVERY fanned-out target, not just the
		// first. `sep` is offered whenever the chain fans and a stored `limit` still
		// bounds the list whether or not a control ever wrote it (#62), so honor both —
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
	} elseif ( 'meta_row' === $res['kind'] ) {
		// REPEATER-ROW LIST (FW-74). The third list branch, and the one that reads
		// SOURCES rather than ids: a row has no entity behind it, so
		// bws_base_source_ids_of_kind() — which drops `id <= 0` — cannot express it.
		//
		// THE KIND TESTED HERE IS THE WIRE'S ($res), NEVER THE RESOLVED BASE'S. The two
		// share the noun and need opposite answers: `src(rows,…)` means the author asked
		// for repeater rows and this branch consumes them, while a $base of the same kind
		// means the query loop positioned us INSIDE a row and the read must keep falling
		// through to the post tail, whose core re-infers the row through bws_read_field()'s
		// own loop inference. Conflating them deletes a live shipped path
		// (fold-test-matrix.md §F9c, mutation-verified).
		//
		// The per-row read is bws_try_text_row_dispatch(), the SAME function the try_ row
		// arm runs — reused rather than duplicated, exactly as the term and post branches
		// reuse their own try_ dispatchers above. It owns the `use` fork, including the
		// analog refusal: a row is not an entity and has no title, so `use:title` renders
		// empty here (an already-supported state) rather than reading some other entity's.
		// Link identity stays 0/'post' — a row has none (CONTEXT.md I12).
		$collected = bws_collect_value_list(
			bws_base_sources_of_kind( $base, $options, 'meta_row' ),
			static fn( $row_source, array $item_opts ) => bws_try_text_row_dispatch( $row_source, $item_opts, $instance ),
			$options
		);
		$value = $collected['value'];
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
 * Shell over bws_base_text_resolve_value(): resolve the value, link-wrap what the
 * singular arms returned (a list arm wrapped its own values per item and reports
 * link_id 0), then on empty output apply the editor preview label
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
	return bws_base_stated_fallback( $options, $instance );
}

/**
 * Build the {{join}} option definitions: one FOLDED key per slot (`A`, `B`, …,
 * FW-56/57) followed by the tag-level assembly options.
 *
 * The slot definitions come from bws_build_fold_slot_options(), which derives every
 * enum and label from the shipped builders and hands them to the `bws-slot-fold`
 * repeater control. Join supplies the container facts: combining, site arm allowed,
 * one term step, no read `same` row, and the slot noun.
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
 * Per-slot `limit` moved INTO the slot value, attached to the step it bounds (a chain
 * can fan more than once, so a slot-level limit has no single meaning). It has no
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
				// No read `same` row yet — per-slot HANDLERS are not built, which is
				// also why bws_build_slot_read_options() is called at $allow_same=false
				// (see its PHPDoc; `use(same)` is legal in combining and the renderer
				// honors a hand-written one, it just has no UI row until handlers ship).
				'allow_same_read' => false,
				// A slot's source is a base tag's source (#104, [I16]), so the offer is the
				// base tag's: the seam hands the whole chain on as depth-0 chain wire and
				// the arms dispatch on what it resolves to, so nothing here truncates it.
				// `rows` joins it in 1.21.0 for that same reason — a slot's read absorbs
				// through the text seam, which consumes a `meta_row` — and it lands here
				// in the same change as the base tag's, since the two lists are asserted
				// equal (control-order-test.php §7).
				'steps'            => array( 'refs', 'terms', 'rows' ),
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
 * (bws_fold_slot_struct + bws_fold_slot_chain_options), which resolves each slot from
 * its folded value when it has one and recovers it from the legacy flat keys when it
 * does not. Era is decided per SLOT, not per tag: a half-applied migration or a
 * hand-edit can leave slot 2 folded between legacy slots 1 and 3, and both feed the
 * ONE carry-forward accumulator this loop holds.
 *
 * Carry-forward semantics are unchanged and now live in the seam: the source resolves `same`
 * ('' / `same` src = prior resolved source), the read never does unless the wire
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
	// The accumulator's source axis is a CHAIN, not a token — `src(same)` carries over the
	// prior slot's whole chain, hops and all (#104). The READ seeds the stripped default
	// of the leaf {{join}}'s slots read through (the text leaf, per bws_get_join_options),
	// so a slot that states no read resolves as the same read a bare {{text}} does: the
	// seam carries the seed forward and writes no default of its own.
	$carry  = bws_fold_empty_carry( bws_use_stripped_default( 'text' ) );

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

		// Resolve to the option set the absorb seam consumes, threading the ONE
		// carry-forward accumulator. The slot's source arrives as DEPTH-0 CHAIN WIRE in
		// `src` — the same key and the same language a base tag states its source in
		// (CONTEXT.md I16) — with `ref`/`srcTermIn` explicitly emptied by the seam's
		// contract, so nothing tag-level can leak a step into a slot's chain. Null = the
		// slot is unconfigured (combining reads an absent field as "not set yet") or holds
		// an unfinished step; either way it renders nothing AND does not feed the
		// accumulator. Join's tag-level `valueSep` (assembly) is NEVER passed through: a
		// list-mode slot joins its own items with text's default ', ' (ADR 0003).
		$skip_reason   = '';
		$limit_default = 1;
		$slot_opts     = bws_fold_slot_chain_options( $slot, $carry, true, $skip_reason, $limit_default );
		if ( null === $slot_opts ) {
			continue;
		}

		// A SLOT'S OWN SOURCE SPELLING DECIDES ITS OWN LIMIT DEFAULT (#60) — chain wire
		// returns everything, flat wire bounds at 1. The seam reports the era because the
		// `src` above cannot: it is CHAIN WIRE on every slot now, including one recovered
		// from legacy flat keys, so bws_base_text_resolve_value() re-resolving the default
		// from it would answer *unlimited* for a slot that has always bounded at 1. Writing
		// the resolved number back is what stops that, and is load-bearing rather than
		// tidy — do not "simplify" it away (#104).
		$slot_opts['limit'] = (string) bws_clamp_limit( $slot_opts['limit'] ?? null, $limit_default );

		// Thread the editor's injected post id into every post-based slot (see
		// $explicit_id note). src:ref bases its step on this id too (the current
		// post is the ref origin), so it must carry. Only src:site is entity-blind
		// — it reads an option, never a post — so the id is left off there.
		// The test is on the RESOLVED KIND, not on the token: `src` is chain wire now, so
		// `'site' === $slot_opts['src']` only happened to work for a root-only chain and
		// would have gone quietly wrong the moment a slot hopped off the site store ([I11]).
		if ( '' !== $explicit_id && 'site' !== bws_base_src_resolution( $slot_opts )['kind'] ) {
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
 * THE FAMILY'S RESOLVE SEAM (FW-136): `{{try_content}}`'s attempts run through this same
 * function, so an attempt reads exactly as the base tag does and inherits whatever the
 * base read gains. Registered as `resolve_fn` on the content modifier template; the shells
 * on both sides own what the seam leaves out.
 *
 * THE COLLAPSE IS NOT RE-STATED HERE, and this seam reads no `limit` of its own. The
 * one-result rule is a fact of the family's template record (`takes_first_usable`,
 * ADR 0007), enforced above this function on both sides.
 *
 * THE PREVIEW QUESTION IS ASKED TWICE, and both times because an arm has to STOP where
 * the tail alone could not tell it to:
 *   - the REFUSAL arm, the one arm where the label and the stated fallback can both
 *     apply — the fallback half lives inside bws_post_content_core(), which a refusal
 *     must not call, so this arm emits it directly and answers '' in preview so the
 *     shell has an empty read to put its label on (image's refusal arm, same shape);
 *   - the AMBIENT arm, whose empty path is FW-116's per-tag fix carried verbatim (see
 *     the comment on that arm). It terminates in preview and falls THROUGH on the front
 *     end, and only the preview flag separates the two.
 * Neither test is guarded on function_exists( 'bws_build_preview_label' ) the way the
 * pre-split callback's were: this seam emits no label at all, and the guard belongs with
 * the emit, in the shell (bws_base_image_resolve_value() set that precedent).
 *
 * NO LIST SEAM, so nothing here reads `sep` and nothing joins.
 *
 * NO LINK IDENTITY TO REPORT — `link_id` is a constant 0 and `link_type` the uniform
 * triple's filler. {{content}} registers no link options: the value is rich markup, which
 * a caller does not wrap. The triple's shape is uniform across all nine families
 * precisely so the attempt walk branches on nothing (includes/helpers/try-slot-loop.php).
 *
 * @since 1.6.0
 * @since 1.21.0 The `meta_row` branch — a `rows` chain reads its rows (FW-74).
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

	// L1 base source (SPEC §V1); ambient term archive → description/key analog (§V7).
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED (GH #75/#76/#109) — read nothing. This arm's empty path is SPLIT: the
	// preview half is the shell's tail, but the fallback half lives inside
	// bws_post_content_core(), which the refusal must not call. So the fallback is
	// stated here, and preview answers '' — the tail's own preview-outranks-fallback
	// order, expressed across the split.
	if ( bws_base_read_refused( $res, $base, array( 'meta_row' ) ) ) {
		$out['value'] = $is_preview ? '' : bws_base_stated_fallback( $options, $instance );
		return $out;
	}

	// Ambient dispatch (term description/key analog §V7; author bio, #19) through
	// the one kind-dispatching seam; this arm's own tail stays here. An EMPTY claim
	// does NOT terminate here on the front end (unlike the other ambient-reading
	// tags): `content`'s cores (bws_post_content_core, both branches) own a
	// self-contained stated-fallback emit on a falsy post id, the same shape image's
	// cores use — falling through to the term/post route below (rather than returning
	// bare '' ) gives a configured `fallback` its chance to run there, on a
	// term-ambient, user-ambient, or query-context-ambient empty read alike. That
	// fallthrough is FW-116's per-tag fix for this family, and it is carried here
	// VERBATIM: the seam's empty-triple-vs-null contract is a fixed input to FW-136,
	// and a wrong read under it is evidence recorded on FW-116, not repaired here.
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
		// REPEATER-ROW READ (FW-74). Collapsing, not listing: {{content}} is
		// takes_first_usable, so the whole fan is compiled with step limits stripped
		// and the FIRST row's read is the output — the same rule the term and post
		// routes below take, applied to rows. {{content}} registers no `limit` and no
		// `sep`; the listing twin of this read is {{text}}'s §F9.5.
		//
		// THE KIND TESTED HERE IS THE WIRE'S ($res), NEVER THE RESOLVED BASE'S — the
		// trap this area sets, stated in full at the {{text}} branch above and pinned
		// by fold-test-matrix.md §F9c.
		//
		// This branch is where use:content and use:excerpt STOP READING THE AMBIENT
		// POST. Before it, a `rows` chain fell into the post route, resolved no row to
		// a post id, and the collapsing selector's empty-fan leg read the current post
		// — so {{content src:rows,…}} printed the whole surrounding page. A row is not
		// an entity and has no content or excerpt of its own, so the analog arms REFUSE
		// here and only use:key reads. bws_try_content_row_dispatch()
		// owns that fork and is the try_ row arm's function too, the same reuse the
		// term and post routes make of their own try_ dispatchers.
		$found        = bws_read_bounded_sources(
			bws_base_sources_of_kind( $base, $options, 'meta_row', true ),
			static fn( $row_source ) => bws_try_content_row_dispatch( $row_source, $opts, $instance ),
			1
		);
		$out['value'] = $found ? (string) $found[0] : '';
		return $out;
	}

	if ( 'term' === $res['kind'] ) {
		// takes_first_usable (ADR 0007): search the WHOLE fan — every step limit is
		// stripped at compile — and output the first usable read.
		$out['value'] = bws_base_term_first_usable(
			$base,
			$options,
			static fn( $tid ) => 'key' === $use
				? bws_term_custom_text_core( (int) $tid, $opts, $instance )
				: bws_term_description_core( (int) $tid, $opts, $instance )
		);
		return $out;
	}

	// The POST route takes the first USABLE read too — same rule as the term
	// route, same selector, whole compiled chain (not the wrapper's leading ref
	// run). Its old shape — first resolved source, read once — was the surviving
	// instance of the single-target collapse CONTEXT.md §Language names a defect.
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
 * NO STATED FALLBACK HERE, unlike bws_base_text_callback(). The fallback is part of the
 * seam's VALUE on every arm that has one — the post route's cores emit it on a falsy id
 * (bws_post_content_core, both branches), and the refusal arm emits it without reaching a
 * core — so a value that arrives empty has already been past whatever fallback there was
 * to try. Hoisting it up here would MOVE OUTPUT: a site read with no content option, an
 * empty term fan and a `rows` chain with no rows all call no core and print nothing today.
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
 * THE FAMILY'S RESOLVE SEAM (FW-136): `{{try_title}}`'s attempts run through this same
 * function, so an attempt reads exactly as the base tag does and inherits whatever the
 * base read gains. Registered as `resolve_fn` on the title modifier template; the shells
 * on both sides own what the seam leaves out.
 *
 * The SECOND of the four families that register link options, so this is the second
 * place FW-135 dissolves: a list arm's values arrive wrapped per item out of
 * bws_collect_value_list() and report `link_id` 0, while a singular read reports its own
 * entity and the shell wraps once. The attempt walk branches on neither — one triple,
 * one contract (includes/helpers/try-slot-loop.php).
 *
 * @since 1.6.0
 * @since 1.16.0 List branches ride the shared bws_collect_value_list fold (FW-49).
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

	// L1 base source (SPEC §V1); ambient term archive → term name analog (§V7).
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED (GH #75/#76/#109) — read nothing. The empty triple IS this arm's own empty
	// path: {{title}} registers no `fallback` option, so the shell's whole empty path is
	// the preview label and a refusal takes it exactly as a read that found nothing does.
	if ( bws_base_read_refused( $res, $base ) ) {
		return array( 'value' => '', 'link_id' => 0, 'link_type' => 'post' );
	}

	// Ambient dispatch (term name §V7; author display name, #19 — user archives
	// have a canonical URL via get_author_posts_url, so both kinds link-wrap on the
	// seam's derived identity) through the one kind-dispatching seam. The seam's triple
	// IS this arm's return shape, tail and all.
	$ambient = bws_base_ambient_analog( 'title', $base, $options, $instance );
	if ( null !== $ambient ) {
		return $ambient;
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
 * Shell over bws_base_title_resolve_value(): resolve the value, link-wrap what the
 * singular arms returned (a list arm wrapped its own values per item and reports
 * link_id 0), then on empty output apply the editor preview label.
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
 * THE FAMILY'S RESOLVE SEAM (FW-136): `{{try_permalink}}`'s attempts run through this
 * same function, so an attempt reads exactly as the base tag does and inherits
 * whatever the base read gains. Registered as `resolve_fn` on the permalink modifier
 * template; the shells on both sides own what the seam leaves out.
 *
 * THE COLLAPSE IS NOT RE-STATED HERE, and this seam reads no `limit` of its own. The
 * one-result rule is a fact of the family's template record (`takes_first_usable`,
 * ADR 0007), enforced above this function on both sides.
 *
 * NO LINK IDENTITY TO REPORT — `link_id` is a constant 0 and `link_type` the uniform
 * triple's filler. {{permalink}} registers no link options: the value IS a URL, so
 * there is nothing for a caller to wrap it in. The triple's shape is uniform across all
 * nine families precisely so the attempt walk branches on nothing (see
 * includes/helpers/try-slot-loop.php).
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

	// L1 base source (SPEC §V1); ambient term archive → term URL analog (§V7).
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED (GH #75/#76/#109) — read nothing. {{permalink}} is the one arm with NO empty
	// path to route into: it registers neither a `fallback` option nor a preview label
	// (a bracketed placeholder would break the href it usually feeds), so '' is the whole
	// of it, and it is what the term branch below already returns on an empty read.
	if ( bws_base_read_refused( $res, $base ) ) {
		return $out;
	}

	// Ambient dispatch (term URL analog §V7) through the one kind-dispatching seam.
	// {{permalink}} has no tail (see the refusal note above), so the seam's value is
	// the whole return, '' included — matching the old bare-return term arm.
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

	// POST route: first usable URL off the whole fan — same rule as the term route
	// (ADR 0007). The helper keeps today's single falsy-id read on an empty fan.
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
 * Shell over bws_base_permalink_resolve_value(), and the thinnest of the nine: this
 * family registers no `fallback` option and emits no preview label (a bracketed
 * placeholder would break the href it usually feeds — see the refusal note in the
 * seam), so '' is the whole of its empty path and the seam's value is the whole return.
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
 * THE FAMILY'S RESOLVE SEAM (FW-136): `{{try_image}}`'s attempts run through this same
 * function, so an attempt reads exactly as the base tag does and inherits whatever the
 * base read gains. Registered as `resolve_fn` on the image modifier template; the shells
 * on both sides own what the seam leaves out.
 *
 * THE COLLAPSE IS NOT RE-STATED HERE, and this seam reads no `limit` of its own. The
 * one-result rule is a fact of the family's template record (`takes_first_usable`,
 * ADR 0007), enforced above this function on both sides.
 *
 * THE STATED FALLBACK STAYS IN THE VALUE, which is where this family's split parts from
 * text's. The cores own that emit (bws_image_stated_fallback, image-tags.php, their shared
 * owner) and fire it on a falsy entity id, so a configured Media Library image is a
 * NON-EMPTY read: it renders rather than yielding to the next attempt. The two arms that
 * reach no core state it themselves — the `meta_row` branch for the reason its own comment
 * gives, the refusal arm because a refusal must not reach a core at all. The shell is left
 * with the preview label alone, and lifting the fallback up to join it would MOVE OUTPUT:
 * a site read with no logo and a term fan with no terms call no core either, and both
 * print nothing today.
 *
 * NO LIST SEAM, so nothing here reads `sep` and nothing joins.
 *
 * NO LINK IDENTITY TO REPORT — `link_id` is a constant 0 and `link_type` the uniform
 * triple's filler. {{image}} registers no link options: its value is a URL, an id or an
 * attachment string, none of which a caller wraps. The triple's shape is uniform across
 * all nine families precisely so the attempt walk branches on nothing (see
 * includes/helpers/try-slot-loop.php).
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

	// L1 base source (SPEC §V1); ambient term archive → term image field (by key),
	// or the configured Media Library fallback when no key (I1 gap #29: no intrinsic
	// term image analog, but the fallback still applies). §V7.
	$base = bws_base_resolve_source_for_callback( $options, $instance );

	// REFUSED (GH #75/#76/#109) — read nothing. The fallback half lives inside the image
	// cores (bws_image_stated_fallback, their shared owner), which the refusal must not
	// reach through a core, so this arm emits it directly.
	//
	// THE PREVIEW QUESTION IS ASKED HERE AND NOWHERE ELSE IN THIS SEAM, and it is the one
	// place in the family where it has to be: preview outranks the fallback image, and this
	// is the ONLY arm where both can apply at once. Every other arm's empty path is the
	// label or nothing, so the shell's tail can order those with no help. Answering ''
	// in preview is what hands the shell an empty read to put its label on.
	if ( bws_base_read_refused( $res, $base, array( 'meta_row' ) ) ) {
		$out['value'] = empty( $instance->context['bwsEditorPreview'] )
			? bws_image_stated_fallback( $options, $instance )
			: '';
		return $out;
	}

	// Ambient dispatch (term image field by key, or the configured Media Library
	// fallback — see the §V7 note above) through the one kind-dispatching seam. The
	// seam does NOT claim the user or query_context kinds for image (its PHPDoc
	// has the measurement): an author archive, or a query-context archive
	// (post-type/date/search/404/front-page), falls through to the post route
	// below, where the image cores' stated-fallback emit still applies.
	$ambient = bws_base_ambient_analog( 'image', $base, $options, $instance );
	if ( null !== $ambient ) {
		// The term analog's core already tried the stated fallback on a no-key read
		// (bws_base_term_analog_read()'s `image` case owns that rule), so an empty value
		// here has been past the fallback and the shell has only its label to add.
		$out['value'] = $ambient['value'];
		return $out;
	}
	if ( 'meta_row' === $res['kind'] ) {
		// REPEATER-ROW READ (FW-74 ticket 05). Collapsing, not listing, exactly as
		// {{content}}'s row branch is: {{image}} is takes_first_usable (ADR 0007), so the
		// whole fan is compiled with step limits stripped and the FIRST row's photo is the
		// output. It registers no `limit` and no `sep`, so there is no list seam to honor
		// here; {{text}}'s §F9.5 is the fanning twin of this read.
		//
		// THE KIND TESTED HERE IS THE WIRE'S ($res), NEVER THE RESOLVED BASE'S — the trap
		// this area sets, stated in full at the {{text}} branch above and pinned by
		// fold-test-matrix.md §F9c.
		//
		// THE READ IS THE RAW SEAM'S, and this family is why that seam was split out:
		// an ACF image sub-field is an ARRAY under the default return_format, and the
		// string seam every other row arm reads through drops arrays. The row core owns
		// that read; the analog refuses, since a row has no featured image of its own.
		//
		// THE FALLBACK IS EMITTED HERE rather than inside the core, which is the one
		// place this branch differs in shape from the post route below. There the cores
		// emit it per read (they have an id to merge, a row has none), and an empty fan
		// still reaches one through the selector's falsy-id leg. Stating it on the empty
		// result gives a `rows` chain the same two fallback occasions the post route has:
		// a row that carries no image, and a repeater with no rows at all.
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
 * Dispatch by `use` — UNIFORM with every other source (Model B, V9). The `use`
 * VALUE is the analog-vs-option lever, NOT key-presence; `use:key` resolves a
 * wp_options key read. `src:site` selects the wp_options namespace the same way
 * `src:current` selects post meta. There is NO `use:option` value (option is a
 * key-read reached by `use:key`, not a distinct field type — V8).
 *
 * STRIP-DEFAULT (B6): an EMPTY wire `use` is the tag's FIRST enum value (stripped
 * at registration), NOT a third "no use" state. This function canonicalizes up front
 * through bws_use_effective(), as every read site does. So `{{text src:site|
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
 * THE B6 REGRESSION HAPPENED HERE, which is why the rule it produced is worth
 * reading beside this function rather than only at its owner. This dispatcher
 * branched on the literal empty string, and for every tag whose stripped default IS
 * key-mode that silently dropped the option read: an unset `use` is the FIRST enum
 * value, never a third "no use" state. So the canonicalization below runs before any
 * branch, and it takes its value from BWS_USE_STRIPPED_DEFAULTS
 * (registration-helpers.php), which owns both that obligation and the rule that the
 * stripped default stays key-mode wherever key-mode and a named analog share an enum.
 * Consequence worth stating here: title and permalink register no `use` enum, so they
 * canonicalize to '' — the ternary this replaced gave them 'key', inertly, because
 * every branch that could read it tests the tag name first.
 *
 * Per-tag site dispatch (V9 Model B; default = the tag's stripped first enum value,
 * per BWS_USE_STRIPPED_DEFAULTS):
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

	// Canonicalize `use` before any branch — an unset `use` is never a third "no use"
	// state (B6). bws_use_effective() owns what it canonicalizes TO; title/permalink
	// register no `use` enum and get '' from it, which the ternary this replaced had
	// been reading as 'key'.
	$use = bws_use_effective( $tag, $options );

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
			// Route through the GB output boundary for fallback/markup parity with
			// the image tag. The class_exists guard stays: it is what keeps a
			// GB-less install returning the bare value rather than fatalling.
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
	return bws_post_custom_text_core( $post_id, $options, $instance );
}

/**
 * Try-tag repeater-ROW-slot dispatch for `text` template (FW-74).
 *
 * The `use` fork's third arm, and the one where `title` has nowhere to go: a row is not
 * an entity, so the ANALOG REFUSES and the slot renders empty — an already-supported
 * state, not a gap. The hop a `use:title` would imply is spellable with no new
 * vocabulary (`rows,team_members;refs,lead_ref` then `use:title`).
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
 * Try-tag repeater-ROW-slot dispatch for `content` template (FW-74).
 *
 * The `use` fork with BOTH analog arms refusing: a row is not an entity, so it has no
 * post content and no excerpt, and only use:key has anywhere to go — the same refusal
 * bws_try_text_row_dispatch() makes of `use:title`, on the family that had been reading
 * the AMBIENT post instead of refusing (see the base branch in
 * bws_base_content_callback()). The keyed read is bws_row_custom_text_core() rather than
 * a content-shaped twin, because {{content|use:key}} and {{text|use:key}} already read one key by one rule —
 * bws_post_content_core()'s custom_field branch is bws_post_custom_text_core() with a
 * different empty-read fallback, and a LIST arm has no per-item fallback to emit (GH #51).
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
 * Try-tag repeater-ROW-slot dispatch for `image` template (FW-74).
 *
 * The `use` fork's row arm, with `featured` REFUSING for the reason
 * bws_try_text_row_dispatch() refuses `title`: a row is not an entity, so it has no
 * featured image, and reading one would print the surrounding post's picture — a
 * plausible wrong value where an empty one is the honest answer. The hop that spelling
 * implies needs no new vocabulary (`rows,team_members;refs,lead_ref` then `use:featured`).
 *
 * Takes the resolved SOURCE, not an id (a row has none). Called by the BASE arm — which
 * is what keeps the base tag and its try_ twin reading one way.
 *
 * @since 1.21.0
 */
function bws_try_image_row_dispatch( $source, $options, $instance ) {
	if ( 'featured' === bws_use_effective( 'image', $options ) ) {
		return '';
	}
	return bws_row_custom_image_core( (array) $source, $options, $instance );
}
