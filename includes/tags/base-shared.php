<?php
/**
 * Shared base-tag foundation: source options, traversal sub-options, source
 * dispatch, term-ambient dispatch, and the option-remap helper.
 *
 * Cross-tag primitives every tag family (base, datetime, email, fn, phone) builds on:
 * the `src`/`ref`/`srcTermIn` option definitions, the try_ slot option builder, the
 * post-id source wrapper and the ambient-term analog read. base-tags.php holds only the
 * base tag callbacks, the src:site source and the try_ dispatch wrappers.
 *
 * Load order: required BEFORE base-tags.php and every other tag file.
 *
 * Resolution model (L1 factory → traversal steps → L2 read by kind): CONTEXT.md and
 * docs/tag-reference.md. The per-function PHPDoc below carries the load-bearing invariants.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.14.1 Extracted from base-tags.php (code-move refactor; no behavior change).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
				array( 'value' => 'current', 'label' => __( 'Current Context', 'generateblocks' ) ),
				array( 'value' => 'ref',     'label' => __( 'Posts in Reference/Relational Field', 'generateblocks' ) ),
				array( 'value' => 'site',    'label' => __( 'Site', 'generateblocks' ) ),
			),
			'_strip_default' => true,
		),
	);
}

/**
 * The enum rows for every registered source offered as a chain ROOT (#83).
 *
 * THE ONE APPENDER. Both authoring surfaces call it — the base tag's root enum
 * (bws_build_src_chain_option) and the folded slot's source enum
 * (bws_build_fold_slot_options) — so a root offered on a base tag is never missing from
 * a try_ attempt or a `{{join}}` field. Slots are included and UNGATED: the gate keeping
 * `site` out of a rooting modifier's list is about ENTITY-BLIND sources, and a registered
 * entity root passes it.
 *
 * NEVER attached to bws_base_source_option(): derived families build their surfaces from
 * that builder's rows, so roots there would offer a second root inside a modifier
 * family's Source dropdown, widen `{{call}}`'s allowlist, and reach `{{table}}`'s flat options.
 *
 * APPENDED, never prepended: `defaultRoot` on both surfaces derives from the first row,
 * and an absent `src` means `current` on every tag.
 *
 * Label is the source's own get_source_label(); no second label method.
 *
 * A root that takes an ARGUMENT carries its declaration as `arg` (normalized by
 * bws_root_argument_row(), plus the source's context type as `kind`). Both surfaces pass
 * it to their control untouched, so a root declared through
 * `bws_dynamic_tags_chain_roots` carries one on the same terms.
 *
 * @since 1.17.0
 * @since 1.20.0 Rows carry a declared root `arg` (FW-39).
 * @return array[] `{ value, label }` rows, plus `arg` where the root declares one and
 *                 `integration` where another plugin registered it, in
 *                 registration order. Empty when the registry is absent (a harness
 *                 loading this file alone) or nothing has opted in.
 */
function bws_registered_root_rows(): array {
	if ( ! class_exists( '\BWS\DynamicTags\SourceRegistry' ) ) {
		return array();
	}
	$rows = array();
	foreach ( \BWS\DynamicTags\SourceRegistry::get_selectable_roots() as $key => $source ) {
		$row = array(
			'value' => (string) $key,
			'label' => $source->get_source_label(),
		);
		// Another plugin's root (class route or the filter route's CallbackRoot) is listed
		// under the menu's Integrations heading, apart from our own.
		if ( 0 !== strpos( get_class( $source ), 'BWS\\DynamicTags\\Sources\\' )
			|| $source instanceof \BWS\DynamicTags\Sources\CallbackRoot ) {
			$row['integration'] = true;
		}
		$arg = bws_root_argument_row( $source->get_root_argument() );
		if ( array() !== $arg ) {
			// The entity KIND the argument's control browses, DERIVED from the source's
			// context type, never declared beside the control: a declared kind could drift
			// from what the source resolves (a term picker on a root that answers posts).
			$arg['kind'] = $source->get_context_type();
			$row['arg']  = $arg;
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Normalize a source's root-argument declaration into the row shape (FW-39).
 *
 * THE ONE READER of SourceInterface::get_root_argument()'s raw return (an integrator's
 * array via the filter route), so a malformed declaration is dropped at one site.
 *
 * Missing `label` or `control` → DROPPED WHOLE, not repaired: the source's label would
 * name the root, not the argument, and there is no default control. The row then reads
 * as an argless root, which downstream already handles.
 *
 * Unrecognized `argless` → REFUSE, not dropped: a typo in the policy must not delete the
 * argument. Refuse resolves nothing until the argument is given.
 *
 * @since 1.20.0
 * @param array $decl Raw declaration.
 * @return array{label:string,control:string,argless:string} Empty when there is none.
 */
function bws_root_argument_row( array $decl ): array {
	// SCALAR-CHECKED before the cast: an array casts to 'Array' and passes as a label, and
	// an object without __toString throws, breaking the option build site-wide. Both fall
	// to the missing-key answer.
	$label   = is_scalar( $decl['label'] ?? null ) ? trim( (string) $decl['label'] ) : '';
	$control = is_scalar( $decl['control'] ?? null ) ? trim( (string) $decl['control'] ) : '';
	if ( '' === $label || '' === $control ) {
		return array();
	}
	$argless = is_scalar( $decl['argless'] ?? null ) ? (string) $decl['argless'] : '';
	if ( \BWS\DynamicTags\SourceInterface::ROOT_ARGLESS_OWNER_RESOLVES !== $argless ) {
		$argless = \BWS\DynamicTags\SourceInterface::ROOT_ARGLESS_REFUSE;
	}
	return array(
		'label'   => $label,
		'control' => $control,
		'argless' => $argless,
	);
}

/**
 * A field picker's editor-facing config, trimmed to what it actually declares.
 *
 * Both chain-config builders derive their pickers (`refOption`, `keyOption`,
 * `rowsOption`) from the shipped option definitions. Empty entries are dropped: an empty
 * `label` renders a blank label strip above the control.
 *
 * @since 1.17.0
 * @param array $def A shipped option definition (label/help/placeholder/…).
 * @return array Non-empty picker fields only.
 */
function bws_fold_picker_config( array $def ): array {
	return array_filter(
		array(
			'label'        => $def['label'] ?? '',
			'help'         => $def['help'] ?? '',
			'placeholder'  => $def['placeholder'] ?? '',
			'dynamicLabel' => ! empty( $def['dynamicLabel'] ),
			'typeDefault'  => $def['typeDefault'] ?? '',
		),
		static function ( $v ) {
			return '' !== $v && false !== $v;
		}
	);
}

/**
 * The `rows` step's ARGUMENT picker, shipped to every container that offers the step.
 *
 * The repeater-step sibling of `bws_base_traversal_options()['ref']`. Lives here, not
 * there, because the repeater name exists only inside the chain value — no flat option
 * key to hang a picker on.
 *
 * `typeDefault` pre-scopes to repeater fields but leaves the filter visible, so an author
 * can widen to a plain-meta repeater discovery cannot type (same axis as {{table}}'s `key`).
 *
 * ONE definition, both builders — never copy it.
 *
 * @since 1.21.0
 * @return array A picker definition, bws_fold_picker_config()-shaped.
 */
function bws_fold_rows_picker_def(): array {
	return array(
		'label'       => __( 'Repeater Field Key', 'generateblocks' ),
		'help'        => __( 'ACF repeater (or meta) field key. The tag reads each row of this repeater.', 'generateblocks' ),
		// GENERIC, never a real name (e.g. a fixture field): on a free-text combobox the
		// grey string reads as a value already set.
		'placeholder' => 'repeater_name',
		'typeDefault' => 'repeater',
	);
}

/**
 * The chain-config keys that are properties of the WIRE, not of a container.
 *
 * Base tag, `{{join}}` slot and `try_` attempt differ on most of a fold config, but not
 * on these, because they describe the wire itself:
 *
 *   steps      one record per WIRE slug:
 *                label      — the step's row label, declared HERE only.
 *                limitLabel — its Limit field's label, naming what the step PRODUCES
 *                             ("Limit Posts Read"); `limitOption.label` is the fallback.
 *                arg        — the key the step's argument rides (BWS_FOLD_STEP_TYPES).
 *                             Equal `arg` across a slug switch keeps the field.
 *                accepts    — accepted resolved-source kinds, from the ENGINE's list
 *                             (BWS_TRAVERSAL_STEP_INPUT_KINDS). Display only: a stored step
 *                             always shows; absent list means "offer it".
 *                produces   — the kind the step outputs (BWS_FOLD_STEP_KINDS).
 *   roots      root token → parse-time kind (BWS_FOLD_PARSE_TIME_ROOT_KINDS owns the
 *              axis). A root absent there resolves at render; the editor filters nothing off it.
 *   retiredSrc retired source tokens the mount migrator must DECLINE, from the constant
 *              the converter's guard reads.
 *   limitOption the per-step LIMIT control's vocabulary (label, placeholder, both help
 *              forms). `limit[N]` is wire grammar, so container-invariant. The control
 *              picks one help, never composes them.
 *
 * @since 1.17.0
 * @return array Chain-config fragment to merge into a container's `fold` array.
 */
function bws_fold_wire_vocabulary(): array {
	// Authored per step: ROW LABEL and LIMIT label; the rest derives from the constants
	// below. A slug missing here is not offerable — a new engine step type reaches the
	// editor only once named here.
	//
	// The limit label names what the step PRODUCES (ADR 0007), authored rather than
	// derived from `produces`: kinds like `meta_row` are internals, not author nouns.
	$labels = array(
		'refs'  => array( __( 'Posts in Reference/Relational Field', 'generateblocks' ), __( 'Limit Posts Read', 'generateblocks' ) ),
		'terms' => array( __( 'Terms in Taxonomy', 'generateblocks' ), __( 'Limit Terms Read', 'generateblocks' ) ),
		'rows'  => array( __( 'Rows in Repeater Field', 'generateblocks' ), __( 'Limit Repeater Rows Read', 'generateblocks' ) ),
	);

	$steps = array();
	foreach ( $labels as $slug => $authored ) {
		list( $label, $limit_label ) = $authored;
		$step = array(
			'label'      => $label,
			'limitLabel' => $limit_label,
		);
		if ( defined( 'BWS_FOLD_STEP_TYPES' ) && isset( BWS_FOLD_STEP_TYPES[ $slug ] ) ) {
			$step['arg'] = BWS_FOLD_STEP_TYPES[ $slug ];
		}
		if ( defined( 'BWS_TRAVERSAL_STEP_INPUT_KINDS' ) && isset( BWS_TRAVERSAL_STEP_INPUT_KINDS[ $slug ] ) ) {
			$step['accepts'] = BWS_TRAVERSAL_STEP_INPUT_KINDS[ $slug ];
		}
		if ( defined( 'BWS_FOLD_STEP_KINDS' ) && isset( BWS_FOLD_STEP_KINDS[ $slug ] ) ) {
			$step['produces'] = BWS_FOLD_STEP_KINDS[ $slug ];
		}
		$steps[ $slug ] = $step;
	}

	return array(
		'steps'       => $steps,
		'roots'       => defined( 'BWS_FOLD_PARSE_TIME_ROOT_KINDS' ) ? BWS_FOLD_PARSE_TIME_ROOT_KINDS : array(),
		'retiredSrc'  => defined( 'BWS_FOLD_RETIRED_SRC_TOKENS' ) ? BWS_FOLD_RETIRED_SRC_TOKENS : array(),
		// The number counts ITEMS READ, not results shown (ADR 0007): an item with an
		// empty field keeps its place, so the copy must not promise output. This label is
		// only the FALLBACK; every shipped slug wears its own `limitLabel`. Wording
		// pending user prose review.
		//
		// TWO HELPS, chosen by whether an EARLIER step FANS — never by position. Per-step
		// limits are per-input and multiply; with no upstream fan-out there is one input
		// and the distinction cannot arise. The condition is bws_fold_chain_fanning_steps()
		// (the migrator's and render seam's predicate), reached in JS through its twin.
		'limitOption' => array(
			'label'       => __( 'Limit items read', 'generateblocks' ),
			// Names the VALUE (`0`), matching the tag-level `limit` help: one rule taught.
			'placeholder' => __( '0 (all)', 'generateblocks' ),
			'help'        => __( 'How many items this step reads, in stored order. An item with an empty field keeps its place. Leave blank for all.', 'generateblocks' ),
			'helpFanning' => __( 'How many items this step reads for each previous-step item, in stored order. An item with an empty field keeps its place. Leave blank for all.', 'generateblocks' ),
		),
	);
}

/**
 * A container's step OFFER — the only per-container step fact.
 *
 * Ordered slug list; everything else lives on the shared vocabulary. Filtered against
 * it because a slug with no record has no row text. ONE owner for both builders.
 *
 * @since 1.17.0
 * @param array $steps Requested wire step slugs, in offer order.
 * @param array $vocab bws_fold_wire_vocabulary() result.
 * @return string[] The offer.
 */
function bws_fold_step_offer( array $steps, array $vocab ): array {
	return array_values( array_intersect( $steps, array_keys( $vocab['steps'] ?? array() ) ) );
}

/**
 * Upgrade a base tag's `src` option to the CHAIN control (FW-56).
 *
 * A source is a root plus fanning steps; the flat spelling (`src` + `ref` + `srcTermIn`)
 * caps at one relationship step plus one taxonomy step. This control lets an author
 * write any chain.
 *
 * DERIVED, never re-typed: enum rows, step labels, taxonomy list and pickers come from
 * the builders the folded-slot control reads, so base tag and `{{join}}` slot share one
 * vocabulary.
 *
 * Edits the SOURCE only; the base tag keeps its own `use`/`key` (unlike a folded slot,
 * where source and read share one value).
 *
 * NOT applied to derived families: `bws_base_source_option()` stays a plain select
 * because `try_*` and `{{table}}` build their surfaces from its rows
 * (bws_pick_src_values, bws_build_slot_traversal_options).
 *
 * @since 1.17.0
 * @param array $args {
 *     @type array $source_opt A bws_base_source_option()-shaped array to upgrade.
 *                             Default bws_base_source_option().
 *     @type array $steps       WIRE step slugs offered as steps, in offer order.
 *                             Default ['refs','terms','rows']. Offer only steps some
 *                             arm reads; otherwise the chain renders empty.
 *     @type bool  $takes_first_usable The template's collapsing capability (ADR 0007):
 *                             the step renderer suppresses the limit control
 *                             where it is set. Default false.
 * }
 * @return array Single-entry array keyed 'src'.
 */
function bws_build_src_chain_option( array $args = array() ): array {
	$source_opt = $args['source_opt'] ?? bws_base_source_option();
	$steps       = $args['steps'] ?? array( 'refs', 'terms', 'rows' );

	if ( ! isset( $source_opt['src'] ) ) {
		return $source_opt;
	}

	$base_trav = bws_base_traversal_options();
	$vocab     = bws_fold_wire_vocabulary();
	$offer     = bws_fold_step_offer( $steps, $vocab );

	$tax_rows = array( array( 'value' => '', 'label' => __( 'Select…', 'generateblocks' ) ) );
	if ( function_exists( 'get_taxonomies' ) ) {
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$tax_rows[] = array(
				'value' => $tax->name,
				'label' => $tax->labels->name ?? $tax->name,
			);
		}
	}

	// STEP 0 root rows. `ref` is a step, not a root, so it is dropped.
	$root_rows = array();
	foreach ( (array) ( $source_opt['src']['options'] ?? array() ) as $row ) {
		if ( 'ref' !== ( $row['value'] ?? '' ) ) {
			$root_rows[] = $row;
		}
	}
	// Registered roots append AFTER the built-ins (see bws_registered_root_rows()).
	$root_rows = array_merge( $root_rows, bws_registered_root_rows() );

	$source_opt['src']['type'] = 'bws-src-chain';
	$source_opt['src']['fold'] = array(
		'container'   => 'base',
		'srcRows'     => $root_rows,
		// The root an ABSENT `src` means: shown as the chain's root, stripped back out on
		// commit so the wire is unchanged.
		//
		// Derived from the ROOT rows (what the picker paints), not the unfiltered enum: a
		// value absent from the options paints an unselectable row.
		//
		// Must equal the wire's stripped default; slot-options-build-test.php asserts it.
		// A mismatch is an enum-ordering bug — fix the enum, not this line.
		'defaultRoot' => (string) ( $root_rows[0]['value'] ?? '' ),
		'offer'       => $offer,
		'taxonomies'  => $tax_rows,
		'refOption'   => bws_fold_picker_config( $base_trav['ref'] ),
		'rowsOption'  => bws_fold_picker_config( bws_fold_rows_picker_def() ),
		// The flat keys a commit REPLACES; kept beside the chain they'd store one source
		// two ways.
		'flatAxes'    => array( 'ref', 'srcTermIn' ),
	);
	// takes_first_usable (ADR 0007): set only when true.
	if ( ! empty( $args['takes_first_usable'] ) ) {
		$source_opt['src']['fold']['takesFirstUsable'] = true;
	}
	// Container-invariant wire vocabulary.
	$source_opt['src']['fold'] = array_merge(
		$vocab,
		$source_opt['src']['fold']
	);

	return $source_opt;
}

/**
 * Filter `site` out of a source-option definition.
 *
 * `site` is entity-blind, so on a rooting surface it fails the qualifying gate
 * (CONTEXT.md I4; tag-reference.md §Qualifying test).
 *
 * NO PRODUCTION CALLER — held only by slot-options-build-test.php; deleted with FW-129
 * unless a new rooting surface takes it up.
 *
 * Slot-side twin: bws_build_slot_traversal_options() (opt back in via try_allow_site_slot).
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
 * Use a BLOCKLIST (bws_filter_site_from_src) when a tag should inherit future base
 * sources; use this ALLOWLIST for a CLOSED set that must not — e.g. `{{call}}` takes
 * `current`/`ref` only, since a `$post_id` function can't consume a non-post source.
 * Rows come from bws_base_source_option(), so labels stay canonical.
 *
 * Order follows $keep. A $keep value with no base row is skipped.
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
 * `ref` — shown when src:ref; the relationship field key for the step.
 *
 * A stored `srcTermIn` is still READ (the chain compiler appends a `terms` step; the
 * fold migration converts it) but has no control. See docs/deprecated-tags-options.md.
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
			// The SOURCE-post relationship field. What the sibling KEY picker opens on is
			// owned by `presetKind()` (assets/js/field-combo-control.js); state none of it
			// here. FW-13 will type-filter this to relationship/post_object.
			// FLAT spelling, so `src:ref` only; a site-rooted relationship is a CHAIN
			// (`src:site;refs,x`).
			'show_if'     => array( 'src' => 'ref' ),
		),
	);
}

/**
 * Text-family FIELD leaf: the `use` (read selector) + `key` (field key) pair.
 *
 * GROUP-PURE LEAF: every key belongs to the canonical SOURCE group, so the caller
 * places the pair at that group's position. Returns enum + control shape ONLY; callers
 * overlay `show_if`, label prefixes and context help. Don't bundle format/fallback keys
 * (multi-group returns are COMPOSERS).
 *
 * Consumers: base `{{text}}`, the `text` template (try_), the {{join}} slot loop, the
 * folded-slot control.
 *
 * NEVER RE-INLINE THIS ENUM AT A CONSUMER — copies drift. A container wanting a
 * DIFFERENT read enum takes a PARAMETER on the twin (bws_build_slot_read_options()'s
 * `$allow_same`), never its own literal.
 *
 * ONE LEAF PER TAG WITH A READ AXIS (siblings below: content, image, contact). The first
 * value of each leaf's `use` enum IS the tag's stripped default, stated once in
 * BWS_USE_STRIPPED_DEFAULTS (registration-helpers.php) and pinned by
 * use-stripped-default-test.php — never move it alone.
 *
 * `readTag` names the leaf's row in that map for assets/js/use-read-control.js (GB never
 * hands a control filter the tag name). Stamped on the leaf so enum and row can't be
 * mispaired; bws_build_slot_read_options copies rows, not this key.
 *
 * Carries the FIXED READ: a `fixed` enum row and same-labeled `fixed` input whose value
 * IS the output.
 *
 * @since 1.17.0
 * @since 1.21.0 Carries the `fixed` row and input.
 * @return array { 'use' => array, 'key' => array, 'fixed' => array } — definitions
 *               WITHOUT `show_if` (base overlays `use:not:title`; the template encodes
 *               the same fact declaratively via try_use_no_key_values).
 */
function bws_get_text_field_options(): array {
	$label = __( 'Fixed Text', 'generateblocks' );
	return array(
		'use' => array(
			'type'           => 'select',
			'label'          => __( 'Text Field', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'key',   'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
				array( 'value' => 'title', 'label' => __( 'Title/Name', 'generateblocks' ) ),
				array( 'value' => 'fixed', 'label' => $label ),
			),
			'_strip_default' => true,
			'readTag'        => 'text',
		),
		'key' => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
			'dynamicLabel' => true,
			'help'         => __( 'ACF or meta field key.', 'generateblocks' ),
			'placeholder'  => 'field_name',
		),
		// bws-format-input escapes `:`/`|` so the text survives GB's tag-string round-trip.
		'fixed' => array(
			'type'  => 'bws-format-input',
			'label' => $label,
			'help'  => __( 'Text to show for each result found. Unlike Fallback Text, which only shows when nothing is found, this shows every time.', 'generateblocks' ),
		),
	);
}

/**
 * The content `use` + `key` field-option LEAF — bws_get_text_field_options()'s sibling.
 *
 * Same contract. `show_if` is the caller's overlay (base hides `key` unless `use:key`;
 * try_ via try_use_no_key_values).
 *
 * @since 1.19.0
 * @return array { 'use' => array, 'key' => array } — definitions WITHOUT `show_if`.
 */
function bws_get_content_field_options(): array {
	return array(
		'use' => array(
			'type'           => 'select',
			'label'          => __( 'Content Field', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'content', 'label' => __( 'Post Content/Term Description', 'generateblocks' ) ),
				array( 'value' => 'key',     'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
				array( 'value' => 'excerpt', 'label' => __( 'Post Excerpt', 'generateblocks' ) ),
			),
			'_strip_default' => true,
			'readTag'        => 'content',
		),
		'key' => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
			'dynamicLabel' => true,
			'help'         => __( 'ACF or meta field key. A WYSIWYG or blocks field renders through the content pipeline (shortcodes and blocks execute).', 'generateblocks' ),
			'placeholder'  => 'field_name',
		),
	);
}

/**
 * The image `use` + `key` field-option LEAF — bws_get_text_field_options()'s sibling.
 *
 * Same contract. Base overlays `key` show_if (`use:not:featured`) and gates `use` on
 * `srcTermIn:empty`; try_ derives the `key` overlay from `try_use_no_key_values`.
 *
 * @since 1.19.0
 * @return array { 'use' => array, 'key' => array } — definitions WITHOUT `show_if`.
 */
function bws_get_image_field_options(): array {
	return array(
		'use' => array(
			'type'           => 'select',
			'label'          => __( 'Image Field', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'key',      'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
				array( 'value' => 'featured', 'label' => __( 'Featured Image/Site Logo', 'generateblocks' ) ),
			),
			'_strip_default' => true,
			'readTag'        => 'image',
		),
		'key' => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
			'dynamicLabel' => true,
			'help'         => __( 'ACF or meta field key holding an image (attachment ID or URL).', 'generateblocks' ),
			'placeholder'  => 'image_field',
		),
	);
}

/**
 * The email / phone `use` + `key` + `fixed` field-option LEAF — bws_get_text_field_options()'s
 * sibling for the two contact tags.
 *
 * Key-mode (stripped default) or fixed read; only per-family words differ. `show_if` is
 * the caller's overlay: base hides `key` under fixed and `fixed` outside it.
 *
 * The `fixed` value is finished like the fallback (validated, linked, obfuscated), so
 * its help says only when it shows and what an invalid entry does.
 *
 * @since 1.21.0
 * @param string $tag 'email' or 'phone'.
 * @return array { 'use' => array, 'key' => array, 'fixed' => array } — definitions
 *               WITHOUT `show_if`.
 */
function bws_get_contact_field_options( string $tag ): array {
	$is_email = 'email' === $tag;
	$fixed    = $is_email ? __( 'Fixed Email', 'generateblocks' ) : __( 'Fixed Phone Number', 'generateblocks' );
	return array(
		'use'   => array(
			'type'           => 'select',
			'label'          => $is_email ? __( 'Email Field', 'generateblocks' ) : __( 'Phone Number Field', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'key',   'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
				array( 'value' => 'fixed', 'label' => $fixed ),
			),
			'_strip_default' => true,
			'readTag'        => $tag,
		),
		'key'   => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
			'dynamicLabel' => true,
			'help'         => $is_email
				? __( 'ACF or meta field key holding the email address.', 'generateblocks' )
				: __( 'ACF or meta field key holding the phone number.', 'generateblocks' ),
			'placeholder'  => $is_email ? 'email_field' : 'phone_field',
		),
		'fixed' => array(
			// bws-format-input escapes `:`/`|` so the entry survives GB's tag-string round-trip.
			'type'  => 'bws-format-input',
			'label' => $fixed,
			'help'  => $is_email
				? __( 'Email address to show for each result found. Unlike Fallback Email, which only shows when the field is empty or invalid, this shows every time. Validated as an email; an invalid address shows nothing.', 'generateblocks' )
				: __( 'Phone number to show for each result found. Unlike Fallback Phone Number, which only shows when the field is empty or invalid, this shows every time. Normalized like a stored number; an invalid number shows nothing.', 'generateblocks' ),
		),
	);
}

/**
 * Build the READ (`use`) option definition for one numbered slot, derived from a
 * base read definition. The read-axis twin of bws_build_slot_traversal_options()
 * (source axis) — same derive-don't-copy contract, same `$n` duties.
 *
 * Derivation rules:
 *   - options: base `use.options` verbatim. Slot ≥2 prepends the `same` (carry-over)
 *     row when $allow_same — the read-axis counterpart of "Same as Previous Source".
 *   - label: "N: <noun>", noun from the base definition's label. Never hand-author a
 *     per-container read label.
 *   - `_strip_default` preserved.
 *
 * `$n` serves exactly TWO purposes: the same-row gate and the "N: " prefix. The FOLD
 * uses only the `['options']` ROWS; the prefixed label is for legacy FLAT callers. Do
 * not "clean up" the prefix — join's shipped registration reads it.
 *
 * Pure (no WP beyond __()); harnessed by slot-options-build-test.php.
 *
 * @since 1.17.0
 * @param int   $n           Slot ordinal (1-based).
 * @param array $base_read   Base `use` definition (e.g. bws_get_text_field_options()['use']).
 * @param bool  $allow_same  When true, slot ≥2 gets the `same` row. FALSE for
 *                           COMBINING containers ({{join}}) only because per-slot
 *                           handlers aren't built; flip to true when they ship.
 * @return array One option definition (type/label/options/_strip_default). Empty
 *               array when $base_read carries no options (nothing to select).
 */
function bws_build_slot_read_options( int $n, array $base_read, bool $allow_same = true ): array {
	$read_opts = $base_read['options'] ?? array();
	if ( empty( $read_opts ) ) {
		return array();
	}

	$rows = array_values( $read_opts );
	// Hardcoded `same` row: un-hardcoding it is FW-43's open residue (docs/future-work.md).
	if ( $n >= 2 && $allow_same ) {
		array_unshift(
			$rows,
			array( 'value' => 'same', 'label' => __( 'Same as Previous Field', 'generateblocks' ) )
		);
	}

	return array(
		'type'           => 'select',
		/* translators: 1: read option label (e.g. "Text Field"), 2: slot number */
		'label'          => sprintf( '%2$d: %1$s', $base_read['label'] ?? __( 'Field', 'generateblocks' ), $n ),
		'options'        => $rows,
		'_strip_default' => ! empty( $base_read['_strip_default'] ),
	);
}

/**
 * Build the FOLDED slot option definitions for a multislot container.
 *
 * One option key per slot — `A`, `B`, … — of type `bws-slot-fold`, whose VALUE carries
 * the slot's whole configuration (grammar: includes/helpers/slot-fold.php).
 *
 * THIS IS WHERE THE CONTROL'S VOCABULARY COMES FROM. GB passes an option's whole config
 * to `generateblocks.editor.tagSpecificControls`, so `fold` carries every enum, label
 * and noun the repeater renders, all DERIVED from the shipped builders.
 * assets/js/slot-fold-control.js hand-authors none of it.
 *
 * CONTAINER SENSITIVITY, all explicit in $args:
 *   - `combining` ({{join}}, {{table}}) seeds a slot with READ UNSET (choosing a field is
 *     the configuration act); SELECTING (`try_*`) seeds `use(same)`. The renderer reads
 *     the flag again for what an absent read MEANS (bws_fold_slot_chain_options).
 *   - `allow_same_read` follows the read twin's flag: `same` appears exactly where the
 *     resolver honors it.
 *   - `steps` is a CAPABILITY list: offer only steps some arm consumes, or the chain
 *     renders nothing. A slot offers what a base tag offers ([I16]).
 *
 * @since 1.17.0
 * @since 1.21.0 $base_fixed (FW-141 02).
 * @param array $args {
 *     @type string $container       'join' | 'table' | 'try' (required).
 *     @type array  $base_read       Base read definition (e.g. bws_get_text_field_options()['use']).
 *     @type array  $base_key        Base field-key definition (…['key']).
 *     @type array  $base_fixed      Base fixed-text definition (…['fixed']). Omitted
 *                                   when the container has no fixed read, as $base_key.
 *     @type int    $max             Slot ceiling (required).
 *     @type int    $min             Slots always visible. Default 2.
 *     @type bool   $combining       True for join/table. Default true.
 *     @type bool   $per_slot_use    Container gives each slot its own read axis. Default true.
 *     @type bool   $flat_per_slot_use Whether the LEGACY flat wire had one (the migrators' era fact,
 *                                    TagTemplateRegistry::try_flat_era_per_slot_use()). Default = per_slot_use.
 *     @type bool   $allow_site      Keep `site` in the source enum. Default true.
 *     @type bool   $allow_same_read Offer the read `same` row at slot ≥2. Default false.
 *     @type array  $steps            WIRE step slugs offered as steps, in offer order. Default ['terms'].
 *     @type array  $tag_level       Legacy axes this container owns at TAG level, never per
 *                                   slot (e.g. a try_ template's `limit`). Ships to the
 *                                   editor as the complement, `flatAxes`.
 *     @type string $noun            Slot noun, lower case ("attempt", "field", "column"). Drives
 *                                   BOTH the Add button and the panel header ("Attempt A");
 *                                   deliberately no separate label parameter.
 *     @type string $field_scope     Field-picker scope ('row' for a repeater container).
 *     @type string $scope_state_key Tag-level option whose value scopes the picker.
 *     @type bool   $takes_first_usable Collapsing capability (ADR 0007): the editor hides
 *                                   the step limit control and the field note's
 *                                   several-results clause. Default false.
 * }
 * @return array Option definitions keyed by SLOT ORDINAL — `A`..`bws_slot_ordinal($max)`.
 *               The key IS the wire spelling (`B:`), so nothing downstream translates.
 */
function bws_build_fold_slot_options( array $args ): array {
	$container       = (string) ( $args['container'] ?? 'join' );
	$max             = (int) ( $args['max'] ?? 5 );
	$min             = (int) ( $args['min'] ?? 2 );
	$combining       = isset( $args['combining'] ) ? (bool) $args['combining'] : bws_fold_is_combining( $container );
	$per_slot_use    = ! isset( $args['per_slot_use'] ) || (bool) $args['per_slot_use'];
	$flat_per_slot_use = isset( $args['flat_per_slot_use'] ) ? (bool) $args['flat_per_slot_use'] : $per_slot_use;
	$allow_site      = ! isset( $args['allow_site'] ) || (bool) $args['allow_site'];
	$allow_same_read = ! empty( $args['allow_same_read'] );
	$steps            = $args['steps'] ?? array( 'terms' );
	$base_read       = $args['base_read'] ?? array();
	$base_key        = $args['base_key'] ?? array();
	$base_fixed      = $args['base_fixed'] ?? array();
	$noun            = (string) ( $args['noun'] ?? '' );

	// ONE noun drives the Add button (`+ Add attempt`) and the header (`Attempt A`); the
	// header pattern is DERIVED here. `%s` is the slot ORDINAL — also its option key and
	// `%A` format token, so header, wire and format string name a slot alike.
	$label_pattern = __( 'Slot %s', 'generateblocks' );
	if ( '' !== $noun ) {
		$first         = function_exists( 'mb_substr' ) ? mb_substr( $noun, 0, 1 ) : substr( $noun, 0, 1 );
		$rest          = function_exists( 'mb_substr' ) ? mb_substr( $noun, 1 ) : substr( $noun, 1 );
		$upper         = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
		$label_pattern = $upper . $rest . ' %s';
	}

	$base_src  = bws_base_source_option();
	$base_trav = bws_base_traversal_options();
	$vocab     = bws_fold_wire_vocabulary();
	$offer     = bws_fold_step_offer( $steps, $vocab );

	// Source enum through the SLOT twin (shipped `site` filter and `same` row). Slot 1
	// gets the plain list, slot ≥2 adds `same`.
	//
	// `ref` is RESPELLED to the wire slug `refs` (a fanning STEP at position 0), so the
	// picker value IS the stored slug and nothing translates.
	$respell = static function ( array $rows ): array {
		foreach ( $rows as &$row ) {
			if ( 'ref' === ( $row['value'] ?? '' ) ) {
				$row['value'] = 'refs';
			}
		}
		return $rows;
	};
	$src_rows            = $respell( bws_build_slot_traversal_options( 1, $base_src, $base_trav, $allow_site )['src']['options'] );
	$src_rows_with_same  = $respell( bws_build_slot_traversal_options( 2, $base_src, $base_trav, $allow_site )['src']['options'] );
	// Registered roots (see bws_registered_root_rows()); ungated by `$allow_site`.
	$registered_roots    = bws_registered_root_rows();
	$src_rows            = array_merge( $src_rows, $registered_roots );
	$src_rows_with_same  = array_merge( $src_rows_with_same, $registered_roots );

	// Read enum through the read twin (rows only). COMBINING adds an unset row: absent
	// there means UNCONFIGURED (slot skipped). In a selecting container absent IS the
	// first row's stripped default, so no extra row.
	$read_rows           = bws_build_slot_read_options( 1, $base_read, false )['options'] ?? array();
	$read_rows_with_same = bws_build_slot_read_options( 2, $base_read, $allow_same_read )['options'] ?? array();
	if ( $combining ) {
		$unset_row           = array( 'value' => '', 'label' => __( 'Select…', 'generateblocks' ) );
		$read_rows           = array_merge( array( $unset_row ), $read_rows );
		$read_rows_with_same = array_merge( array( $unset_row ), $read_rows_with_same );
	}

	// Public taxonomies for a `terms` step, read here (not the REST store) so the enum
	// arrives with the definition.
	$tax_rows = array( array( 'value' => '', 'label' => __( 'Select…', 'generateblocks' ) ) );
	if ( function_exists( 'get_taxonomies' ) ) {
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$tax_rows[] = array(
				'value' => $tax->name,
				'label' => $tax->labels->name ?? $tax->name,
			);
		}
	}

	$fold = array(
		'container'        => $container,
		'combining'        => $combining,
		'perSlotUse'       => $per_slot_use,
		'flatPerSlotUse'   => $flat_per_slot_use,
		'min'              => $min,
		'max'              => $max,
		'noun'             => $noun,
		'srcRows'          => $src_rows,
		'srcRowsWithSame'  => $src_rows_with_same,
		// The root an absent chain means on slot 1, from the enum's lead row. Displayed,
		// never left `''`: an unmatched value paints the first row as an unselectable
		// phantom. Slot ≥2's absence is `same` (writeChainAt materializes it).
		'defaultRoot'      => (string) ( $src_rows[0]['value'] ?? '' ),
		'offer'            => $offer,
		'readRows'         => $read_rows,
		'readRowsWithSame' => $read_rows_with_same,
		'readLabel'        => $base_read['label'] ?? '',
		// Read-axis twin of `defaultRoot`; matches the render seam's
		// `bws_fold_empty_carry( $default_use )`. Combining's lead row is the unset row,
		// so this is '' there with no branch.
		'defaultRead'      => (string) ( $read_rows[0]['value'] ?? '' ),
		'taxonomies'       => $tax_rows,
		'refOption'        => bws_fold_picker_config( $base_trav['ref'] ),
		// LEGACY per-slot axes, so mount migrator and control fold/delete exactly the
		// converter's keys. Via bws_fold_slot_flat_axes (owner of the tag-level
		// subtraction); a hand-kept list deletes tag-level `limit`/`key`.
		'flatAxes'         => function_exists( 'bws_fold_slot_flat_axes' )
			? bws_fold_slot_flat_axes( (array) ( $args['tag_level'] ?? array() ) )
			: array(),
	);
	// Container-invariant wire vocabulary.
	$fold = array_merge( $vocab, $fold );

	// OMITTED, not empty, when there's no per-slot key: JS `[]` is TRUTHY and renders an
	// unlabeled key picker.
	if ( ! empty( $base_key ) ) {
		$fold['keyOption'] = bws_fold_picker_config( $base_key );
	}
	if ( ! empty( $base_fixed ) ) {
		$fold['fixedOption'] = bws_fold_picker_config( $base_fixed );
	}
	// `rows` step picker; overridable ({{table}}), but always shipped or the combo is unlabeled.
	$fold['rowsOption'] = bws_fold_picker_config(
		! empty( $args['rows_option'] ) ? (array) $args['rows_option'] : bws_fold_rows_picker_def()
	);
	if ( ! empty( $args['field_scope'] ) ) {
		$fold['fieldScope'] = (string) $args['field_scope'];
	}
	if ( ! empty( $args['scope_state_key'] ) ) {
		$fold['scopeStateKey'] = (string) $args['scope_state_key'];
	}
	// takes_first_usable (ADR 0007): SET ONLY WHEN TRUE, keeping other fold configs unchanged.
	if ( ! empty( $args['takes_first_usable'] ) ) {
		$fold['takesFirstUsable'] = true;
	}

	$options = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		// ORDINAL in the label (not the number): wire `B:…`, header "Slot B", token `%B`.
		$ordinal                = bws_slot_ordinal( $n );
		$options[ $ordinal ] = array(
			'type'  => 'bws-slot-fold',
			/* translators: %s: slot ordinal (A, B, …) */
			'label' => sprintf( $label_pattern, $ordinal ),
			'fold'  => $fold,
		);
	}
	return $options;
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
 * Pure; harnessed by slot-qualify-show-if-test.php.
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
 * Build the source + traversal option definitions for one numbered try_ slot,
 * derived from the base builders. Pure (no $slot_trigger merge — the registry owns
 * visibility); harnessed by slot-options-build-test.php.
 *
 * Derivation rules:
 *   - src: base `src.options`. `site` filtered out by DEFAULT; $allow_site re-allows
 *     it (email/phone, where site is the canonical contact fallback). Slot ≥2 prepends
 *     `same`. `_strip_default` preserved. Label "N: Source".
 *   - ref: base definition verbatim, show_if re-qualified via bws_slot_qualify_show_if,
 *     label prefixed "N: ".
 *
 * @since 1.11.0
 * @param int   $n          Slot ordinal (1-based).
 * @param array $base_src   bws_base_source_option() result.
 * @param array $base_trav  bws_base_traversal_options() result.
 * @param bool  $allow_site When true, keep `site` in the src list (per-template
 *                          opt-in, gated on the resolver site arm). Default false.
 * @return array { 'src' => array, 'ref' => array } — option definitions WITHOUT
 *               $slot_trigger (caller merges show_if_any).
 */
function bws_build_slot_traversal_options( int $n, array $base_src, array $base_trav, bool $allow_site = false ): array {
	$sibling_keys = array( 'src', 'ref' );

	// --- src: filter 'site' unless allowed, prepend 'same' for slot ≥2. ---
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

	// --- ref: base def verbatim, show_if re-qualified, "N: " label prefix. ---
	$ref_def          = $base_trav['ref'];
	$ref_def['label'] = sprintf( /* translators: 1: slot number, 2: base label */ '%1$d: %2$s', $n, $base_trav['ref']['label'] );
	if ( isset( $ref_def['show_if'] ) ) {
		$ref_def['show_if'] = bws_slot_qualify_show_if( $ref_def['show_if'], $n, $sibling_keys );
	}

	return array(
		'src' => $src_def,
		'ref' => $ref_def,
	);
}

// ===============================================
// SOURCE DISPATCH
// ===============================================

/**
 * Resolve the target post ID from the `src` option.
 *
 * THIN WRAPPER over the source factory + traversal engine for POST-SEMANTIC callers
 * (datetime, {{call}}/fn, try_ slots) that want a single POST id | false. The
 * value-list seam (bws_resolve_field_values) does not use it.
 *
 * bws_resolve_base_source (L1: loop → ambient term → current post) + REF-ONLY steps
 * (bws_wrapper_ref_steps) through bws_run_traversal, collapsed to the FIRST post id.
 * A non-post base (term ambient, meta_row) yields false, never a term/row id as a post id.
 *
 * REF-ONLY: NEVER assembles a `srcTermIn` step; callers do post→term themselves via
 * bws_get_srcterm_terms(). Using bws_field_values_assemble_steps() here would collapse
 * to false and empty those callers.
 *
 * @since 1.6.0
 * @since 1.14.0 Rewired to the source factory + traversal engine; ref-only steps.
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
 * Get taxonomy terms for a resolved post via the `srcTerm`/`tax` options.
 *
 * Called by base tag callbacks when `srcTerm` is set. The post is already
 * resolved via bws_resolve_post_by_source(); this function performs the
 * final step from that post to its taxonomy terms.
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
// TERM-AMBIENT DISPATCH
// ===============================================

/**
 * Resolve the base source for a base callback, guarded for load order.
 *
 * Single factory call per callback; the callback then branches on base kind (term →
 * analog read, else first post id). Falls back to post/0 when the engine is unavailable.
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
 * What this tag's source chain RESOLVES TO — the base callbacks' dispatch axis.
 *
 * Shim over bws_fold_src_resolution(). Every base arm asks this instead of comparing
 * `$options['src']` or reading `srcTermIn`, so chain- and flat-spelled sources take the
 * SAME arm.
 *
 * NO FALLBACK, deliberately: slot-fold-compile.php loads before any tag registers, and
 * a compiler-absent branch would be an unexercised copy of the dispatch.
 *
 * @since 1.17.0
 * @param array $options Tag options.
 * @return array{root:string, kind:string, fans:bool} See bws_fold_chain_resolution().
 */
function bws_base_src_resolution( array $options ): array {
	return bws_fold_src_resolution( $options );
}

/**
 * The wire kinds EVERY base family serves, whatever arms it has.
 *
 * `post` is every family's tail; `render_time` is the ambient root whose kind the wire
 * can't know. Refusing either would refuse the bare tag.
 *
 * `term` IS HERE ON A CONDITION: every caller of bws_base_read_refused() is a
 * `cross-source` family (text, content, title, permalink, image, datetime_single,
 * datetime_range), which means the post/term pair by definition (CONTEXT.md I1).
 * **The day a non-cross-source family (e.g. a post-only one like {{call}}) calls
 * bws_base_read_refused(), move `term` out of here and into that family's $serves**,
 * or it reads the ambient post off a `src:terms,…` chain.
 *
 * @since 1.21.0
 */
const BWS_BASE_WIRE_KINDS_ALWAYS_SERVED = array( 'post', 'render_time', 'term' );

/**
 * Whether a base arm must REFUSE this tag rather than read anything (GH #75/#76/#109).
 *
 * THE ARMS' REFUSAL TEST — one call per arm, the only place a base callback compares a
 * refusal. What each refusal MEANS is owned elsewhere (bws_fold_chain_resolution() for
 * the chain kind, BWS_SOURCE_KIND_UNRESOLVED for the factory's).
 *
 * THREE DISJOINT REFUSALS, ONE TEST: the root named a source this render can't use; a
 * later step named unrecognized vocabulary (a root is not a step — [I14]); the chain
 * resolves to a kind this FAMILY has no arm for. All three: the read does not happen.
 *
 * THE UNSERVED-KIND REFUSAL'S AXIS IS HERE: a wire kind is refused unless it is in
 * BWS_BASE_WIRE_KINDS_ALWAYS_SERVED or the call site's $serves. Default-refuse,
 * opt-in-to-serve, so a new wire kind (or a family dropping a branch) renders empty
 * instead of leaking. Serve-by-default leaked: `{{title src:rows,team_members}}`
 * printed the page's own title. `site` is deliberately absent: callbacks return their
 * site read BEFORE the factory runs.
 *
 * NOT at bws_base_post_id_from_source() / bws_base_post_first_usable(): refusing there
 * can only return a falsy id, and a falsy id does not stop (below).
 *
 * Each arm: no read, its own empty path, stated fallback fires. Refusing ≠ reading
 * nothing — [I15] ("an ambient read must be SPELLED, never reached by fallback"). The
 * arms' catch-all does the SINGULAR read, and with no id bws_read_field() falls back to
 * a query-loop item and then the queried TERM — a plausible value from an entity the
 * wire never named, worse than empty.
 *
 * So the test runs AFTER THE FACTORY, above the core: the cores' falsy-id loop-item read
 * is load-bearing for repeater rows, where ABSENT legitimately means the row; the core
 * can't tell absence from refusal.
 *
 * NO defined()/function_exists() GUARD, here or at any call site: traversal-pipeline.php
 * loads first, and a guard would make the refusal vanish silently, returning the wrong
 * entity's value. A fatal is the honest answer (same posture as
 * bws_post_excerpt_core()'s unguarded context swap).
 *
 * @since 1.17.0
 * @since 1.21.0 The unserved-kind refusal and its $serves list.
 * @param array $res    A bws_base_src_resolution() result (the CHAIN's answer).
 * @param array $base   A bws_base_resolve_source_for_callback() result (the FACTORY's).
 * @param array $serves Wire kinds this family branches beyond the always-served set —
 *                      `meta_row` at the two families with a row arm, today's only use.
 * @return bool True when no arm may read this tag.
 */
function bws_base_read_refused( array $res, array $base, array $serves = array() ): bool {
	if ( '' === ( $res['kind'] ?? '' ) ) {
		return true;
	}
	if ( BWS_SOURCE_KIND_UNRESOLVED === ( $base['kind'] ?? '' ) ) {
		return true;
	}
	return ! in_array( (string) $res['kind'], array_merge( BWS_BASE_WIRE_KINDS_ALWAYS_SERVED, $serves ), true );
}

/**
 * The tag's STATED fallback, rendered — or '' when the author stated none.
 *
 * One owner for the text-shaped fallback emit; never re-inline at a call site.
 *
 * NOT the cores' own empty-read fallback (those merge the resolved id first, which a
 * refused source lacks). {{image}}: bws_image_stated_fallback; {{datetime_*}}:
 * bws_handle_date_time_fallback().
 *
 * @since 1.17.0
 * @param array  $options  Tag options.
 * @param object $instance GB tag instance.
 * @return string The rendered fallback, or ''.
 */
function bws_base_stated_fallback( array $options, $instance ): string {
	$fallback = sanitize_text_field( $options['fallback'] ?? '' );
	return '' !== $fallback
		? (string) bws_gb_tag_output( $fallback, $options, $instance )
		: '';
}

/**
 * The FIXED read: the author's `fixed` text, finished as a text read — or ''.
 *
 * Reads nothing. Text arms call it only once they hold a resolved source, so it shows
 * once per source and an unresolved source still renders empty (fallback fires).
 * Sanitized like bws_base_stated_fallback(), then GB's text transforms apply.
 *
 * @since 1.21.0
 * @param array  $options  Tag options.
 * @param object $instance GB tag instance.
 * @return string The rendered text, or ''.
 */
function bws_fixed_text_read( array $options, $instance ): string {
	$text = sanitize_text_field( $options['fixed'] ?? '' );
	return '' !== $text
		? (string) bws_gb_tag_output( $text, $options, $instance )
		: '';
}

/**
 * Collapse a base source to the callback's POST id via ref-only steps.
 *
 * bws_resolve_post_by_source() for an already-resolved base, so the callback pays one
 * factory call. NEVER srcTermIn (the callback's $tax branch owns it).
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
 * The resolved SOURCES a base tag's chain produces, filtered to one KIND.
 *
 * The plural read behind every list arm. Runs the WHOLE compiled chain (not the
 * wrapper's ref-only run): the arm already knows what it resolves to, so a `terms` step
 * after `refs` must not be dropped (fold matrix §F9.3).
 *
 * Document order (engine appends, never sorts). Caller slices to `limit`, joins with `sep`.
 *
 * `$ignore_limits`: the collapsing-tag read (ADR 0007) — unbounded fan, every step
 * limit stripped.
 *
 * SOURCES, NOT IDS: a repeater row has a `row` array and no id, so the row arm reads
 * here; entity arms use bws_base_source_ids_of_kind().
 *
 * @since 1.21.0 Split out of bws_base_source_ids_of_kind(), which maps over it.
 * @param array  $base          Base resolved source.
 * @param array  $options       Tag options.
 * @param string $kind          Resolved-source kind to keep ('post'|'term'|'meta_row'|…).
 * @param bool   $ignore_limits Compile the chain with every step limit stripped.
 * @return array[] Resolved sources in document order (may be empty).
 */
function bws_base_sources_of_kind( array $base, array $options, string $kind, bool $ignore_limits = false ): array {
	if ( ! function_exists( 'bws_run_traversal' ) || ! function_exists( 'bws_field_values_assemble_steps' ) ) {
		return array();
	}
	$sources = bws_run_traversal( array( $base ), bws_field_values_assemble_steps( $options, $ignore_limits ) );
	$kept    = array();
	foreach ( $sources as $src ) {
		if ( is_array( $src ) && $kind === ( $src['kind'] ?? '' ) ) {
			$kept[] = $src;
		}
	}
	return $kept;
}

/**
 * Ids of the resolved sources a base tag's chain produces, filtered to one KIND.
 *
 * bws_base_sources_of_kind() mapped to ids, `id <= 0` dropped.
 *
 * @since 1.14.0
 * @since 1.17.0 Compiles the whole chain and takes a $kind; was ref-only steps.
 * @since 1.18.0 $ignore_limits threaded to the compile (collapsing tags).
 * @since 1.21.0 A map over bws_base_sources_of_kind().
 * @param array  $base          Base resolved source.
 * @param array  $options       Tag options.
 * @param string $kind          Resolved-source kind to keep ('post'|'term'|…).
 * @param bool   $ignore_limits Compile the chain with every step limit stripped.
 * @return int[] Entity ids in document order (may be empty).
 */
function bws_base_source_ids_of_kind( array $base, array $options, string $kind, bool $ignore_limits = false ): array {
	$ids = array();
	foreach ( bws_base_sources_of_kind( $base, $options, $kind, $ignore_limits ) as $src ) {
		$id = (int) ( $src['id'] ?? 0 );
		if ( $id > 0 ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/**
 * Collapse a base source to the FULL post-id LIST — every fanned-out target, for list mode.
 *
 * @since 1.14.0
 * @since 1.18.0 $ignore_limits (collapsing tags — the whole fan, no step limits).
 * @param array $base          Base resolved source.
 * @param array $options       Tag options.
 * @param bool  $ignore_limits Compile the chain with every step limit stripped.
 * @return int[] Post ids in document order (may be empty).
 */
function bws_base_post_ids_from_source( array $base, array $options, bool $ignore_limits = false ): array {
	return bws_base_source_ids_of_kind( $base, $options, 'post', $ignore_limits );
}

/**
 * The TERM ids a base tag's chain resolves to — the term arm's read.
 *
 * Through the engine, so flat `srcTermIn` and chain `terms,<tax>` read the same terms.
 *
 * A relationship step BEFORE the term step FANS (no first-post collapse):
 * `src:ref|ref:x|srcTermIn:y|limit:3` yields terms from every ref'd post. Deliberate;
 * pinned by fold-test-matrix.md §F9.6.
 *
 * @since 1.17.0
 * @since 1.18.0 $ignore_limits (collapsing tags — the whole fan, no step limits).
 * @param array $base          Base resolved source.
 * @param array $options       Tag options.
 * @param bool  $ignore_limits Compile the chain with every step limit stripped.
 * @return int[] Term ids in document order (may be empty).
 */
function bws_base_term_ids_from_source( array $base, array $options, bool $ignore_limits = false ): array {
	return bws_base_source_ids_of_kind( $base, $options, 'term', $ignore_limits );
}

/**
 * The USER ids a base tag's chain resolves to — the user arm's read.
 *
 * Today only a ROOT-ONLY chain (ambient author archive) yields a user. Runs the chain
 * rather than reading $base['id'] so a future user-producing step needs no change.
 *
 * @since 1.17.0
 * @param array $base    Base resolved source.
 * @param array $options Tag options.
 * @return int[] User ids in document order (may be empty).
 */
function bws_base_user_ids_from_source( array $base, array $options ): array {
	return bws_base_source_ids_of_kind( $base, $options, 'user' );
}

/**
 * The FIRST usable post's read, off a base tag's whole chain (ADR 0007, [I19]).
 *
 * The collapsing callbacks' POST route: the unbounded fan arrives gated
 * (bws_source_gate owns the criterion); bws_read_bounded_sources() at n = 1 reads the
 * FIRST source only, even if empty. Does NOT search past an empty field — selection is
 * field-independent (ADR 0007). An EMPTY fan still does the single falsy-id read (the
 * cores' repeater-row semantics), so the reader always runs once.
 *
 * @since 1.18.0
 * @param array    $base    Base resolved source.
 * @param array    $options Tag options.
 * @param callable $read    fn( int|false $post_id ): string — one candidate's read.
 * @return string First usable read, or ''.
 */
function bws_base_post_first_usable( array $base, array $options, callable $read ): string {
	$ids   = bws_base_post_ids_from_source( $base, $options, true );
	$found = $ids
		? bws_read_bounded_sources( $ids, $read, 1 )
		: bws_read_bounded_sources( array( bws_base_post_id_from_source( $base, $options ) ), $read, 1 );
	return $found ? (string) $found[0] : '';
}

/**
 * The FIRST usable term's read — the term-route twin. Reads the first gated term
 * only (WP's own term ordering, passed through); no search past an empty field.
 *
 * @since 1.18.0
 * @param array    $base    Base resolved source.
 * @param array    $options Tag options.
 * @param callable $read    fn( int $term_id ): string — one candidate's read.
 * @return string First usable read, or ''.
 */
function bws_base_term_first_usable( array $base, array $options, callable $read ): string {
	$found = bws_read_bounded_sources( bws_base_term_ids_from_source( $base, $options, true ), $read, 1 );
	return $found ? (string) $found[0] : '';
}

/**
 * Read a base tag's TERM analog on a term archive (CONTEXT.md I1).
 *
 * Each base tag at its DEFAULT `use` yields the term's intrinsic analog; `use:key` (and
 * text's key-default) reads term meta. Same term cores as the explicit term-step branch.
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
	switch ( $tag ) {
		case 'title':
			return bws_term_title_core( $term_id, $options, $instance );

		case 'text':
			return bws_try_text_term_dispatch( $term_id, $options, $instance );

		case 'content':
			$use = bws_use_effective( 'content', $options );
			return 'key' === $use
				? bws_term_custom_text_core( $term_id, $options, $instance )
				: bws_term_description_core( $term_id, $options, $instance );

		case 'permalink':
			return bws_term_permalink_core( $term_id, $options, $instance );

		case 'image':
			// No term image analog (#29). Called unconditionally: the core handles empty
			// key → bws_handle_media_fallback, so no key + fallback still yields the
			// fallback. Same core as a try_image slot.
			return bws_term_custom_image_core( $term_id, $options, $instance );
	}

	return '';
}

// ===============================================
// USER-AMBIENT DISPATCH
// ===============================================

/**
 * Read a base tag's USER analog on an author archive (CONTEXT.md I1).
 *
 *   title   → display name          (get_the_author_meta('display_name'))
 *   content → biographical info      (get_the_author_meta('description'))
 *   text    → use:title = display name; key-mode = user meta field
 *
 * Values route through bws_gb_tag_output() so GB's per-tag transforms apply.
 *
 * Any other tag → '' (empty, not wrong). Deferred author analogs (FW-47):
 *   - permalink: get_author_posts_url() exists (link-wrap uses it), but a bare
 *     {{permalink}} is circular on the author's own archive. Non-circular uses need a
 *     NON-ambient user source.
 *   - image: no clean analog (parity with the #29 term-image gap); the avatar adds a
 *     Gravatar HTTP + privacy surface. use:key user-image reads work today.
 *   - datetime: folds in with FW-9's remaining datetime context work.
 *
 * @since 1.15.0
 * @since 1.16.0 text case.
 * @param string $tag      One of title|content|text (others → '').
 * @param int    $user_id  Ambient user id.
 * @param array  $options  Tag options.
 * @param object $instance GB instance.
 * @return string Rendered analog value ('' on miss/gap/unsupported tag).
 */
function bws_base_user_analog_read( string $tag, int $user_id, array $options, $instance ): string {
	if ( ! $user_id ) {
		return '';
	}
	switch ( $tag ) {
		case 'title':
			$name = get_the_author_meta( 'display_name', $user_id );
			if ( ! is_string( $name ) || '' === $name ) {
				return '';
			}
			return bws_gb_tag_output( $name, $options, $instance );

		case 'text':
			// Mirrors the term text dispatch; key-mode shaped like
			// bws_term_custom_text_core (fallback on miss, '0' preserved).
			$use = bws_use_effective( 'text', $options );
			if ( 'title' === $use ) {
				return bws_base_user_analog_read( 'title', $user_id, $options, $instance );
			}
			if ( 'fixed' === $use ) {
				return bws_fixed_text_read( $options, $instance );
			}
			$fallback = sanitize_text_field( $options['fallback'] ?? '' );
			$key      = sanitize_text_field( $options['key'] ?? '' );
			if ( '' === $key || ( function_exists( 'bws_field_key_disallowed' ) && bws_field_key_disallowed( $key ) ) ) {
				return '';
			}
			$raw   = get_user_meta( $user_id, $key, true );
			$value = ( is_scalar( $raw ) && '' !== (string) $raw ) ? (string) $raw : '';
			if ( '' === $value ) {
				return '' !== $fallback
					? bws_gb_tag_output( $fallback, $options, $instance )
					: '';
			}
			return bws_gb_tag_output( $value, $options, $instance );

		case 'content':
			$bio = get_the_author_meta( 'description', $user_id );
			if ( ! is_string( $bio ) || '' === $bio ) {
				return '';
			}
			return bws_gb_tag_output(
				bws_sanitize_rich_content( $bio ),
				$options,
				$instance
			);
	}

	return '';
}

// ===============================================
// QUERY-CONTEXT DISPATCH
// ===============================================

/**
 * Read a base tag's QUERY-CONTEXT analog (CONTEXT.md I1).
 *
 * Third analog reader; unhandled tag → '' (empty, not wrong). Takes $base, NOT an id:
 * a query-context source is sub-kind + payload with no id (ADR 0002).
 *
 *   title   → the context's canonical heading (per sub-kind below)
 *   text    → use:title reads the title analog (the try_ composition: key-mode
 *             attempt first, canonical title second); key-mode has no entity → ''
 *   content → post_type_archive: post type description; 404: the GP borrow; the rest ''
 *
 * WE FOLLOW WP CORE: each title is wp_get_document_title()'s branch for the context,
 * via the same PRIMITIVES (not that function: it appends site name + page number, and
 * `document_title_parts` runs wp_head listeners). Primitives give authors core's
 * filters for free. 404/search use core msgids with the explicit 'default' domain.
 * Unprefixed, like the term kind (`Sales`, not `Department: Sales`).
 *
 * THE 404 BORROW: GP's `generate_404_title` / `generate_404_text` → GP default →
 * core msgid (title) / '' (content). Gated on GENERATE_VERSION because a non-GP theme
 * could reuse the hook name. GP allows HTML there, hence bws_gb_tag_output().
 *
 * @since 1.19.0
 * @param string $tag      One of text|content|title (others → '').
 * @param array  $base     Query-context resolved source ({kind, sub, payload}).
 * @param array  $options  Tag options.
 * @param object $instance GB tag instance.
 * @return string Rendered analog value ('' on miss/gap/unsupported tag).
 */
function bws_base_query_context_analog_read( string $tag, array $base, array $options, $instance ): string {
	$sub     = (string) ( $base['sub'] ?? '' );
	$payload = is_array( $base['payload'] ?? null ) ? $base['payload'] : array();

	switch ( $tag ) {
		case 'title':
			$value = '';
			switch ( $sub ) {
				case 'post_type_archive':
					// Unprefixed core primitive; fires the `post_type_archive_title` filter.
					$value = (string) post_type_archive_title( '', false );
					break;

				case 'date':
					// Core's day/month/year branch; get_the_date reads the main query's
					// first row, whose date IS the archive's span.
					if ( ! empty( $payload['day'] ) ) {
						$value = (string) get_the_date();
					} elseif ( ! empty( $payload['monthnum'] ) ) {
						$value = (string) get_the_date( _x( 'F Y', 'monthly archives date format', 'default' ) );
					} else {
						$value = (string) get_the_date( _x( 'Y', 'yearly archives date format', 'default' ) );
					}
					break;

				case 'search':
					$value = sprintf( __( 'Search Results for &#8220;%s&#8221;', 'default' ), get_search_query() );
					break;

				case '404':
					$value = defined( 'GENERATE_VERSION' )
						? (string) apply_filters( 'generate_404_title', __( 'Oops! That page can&rsquo;t be found.', 'generatepress' ) )
						: __( 'Page not found', 'default' );
					break;

				case 'latest_home':
					$value = (string) get_bloginfo( 'name', 'display' );
					break;
			}
			return '' !== $value ? bws_gb_tag_output( $value, $options, $instance ) : '';

		case 'text':
			// use:title → title analog; fixed needs no entity; key-mode has none → ''.
			$use = bws_use_effective( 'text', $options );
			if ( 'title' === $use ) {
				return bws_base_query_context_analog_read( 'title', $base, $options, $instance );
			}
			if ( 'fixed' === $use ) {
				return bws_fixed_text_read( $options, $instance );
			}
			return '';

		case 'content':
			// Only `key` branches away (no entity → ''); every other `use` takes the analog.
			if ( 'key' === bws_use_effective( 'content', $options ) ) {
				return '';
			}
			$value = '';
			if ( 'post_type_archive' === $sub ) {
				$value = (string) get_the_post_type_description();
			} elseif ( '404' === $sub && defined( 'GENERATE_VERSION' ) ) {
				$value = (string) apply_filters( 'generate_404_text', __( 'It looks like nothing was found at this location. Maybe try searching?', 'generatepress' ) );
			}
			if ( '' === $value ) {
				return '';
			}
			return bws_gb_tag_output( bws_sanitize_rich_content( $value ), $options, $instance );
	}

	return '';
}

// ===============================================
// AMBIENT-ANALOG SEAM
// ===============================================

/**
 * The ONE place a base callback asks "does an ambient kind answer this tag".
 *
 * Gates ONCE on the chain resolving to `render_time`, then dispatches on the base
 * source's kind to the analog readers above. Every `try_` attempt passes through here too.
 *
 * Only the per-KIND block lives here. Link-wrap, preview-label emission and the empty
 * path are per-TAG in each callback's tail: a non-null triple runs that tail, null falls
 * through to the post/term/list path.
 *
 * Unhandled (tag, kind) pairs render '' through the seam. TWO measured carve-outs, same
 * reason: the user kind is claimed only for title/content/text, and query_context for
 * every tag EXCEPT image — because {{image}} there reaches bws_custom_image_core() via
 * the post route, where a configured Media Library fallback still renders (the seam's
 * '' would drop it). {{permalink}} stays out of the user claim until FW-47 adds the analog.
 *
 * Link identity is DERIVED from bws_source_link_identity() (CONTEXT.md I12); null on an
 * entity kind (id 0) returns null → caller's post path.
 *
 * The empty-triple-vs-null contract is per-tag carve-outs, not a rule (FW-116). Adding
 * a (tag, kind) pair means checking whether it needs the same carve-out.
 *
 * @since 1.19.0
 * @param string $tag      One of text|content|title|permalink|image.
 * @param array  $base     Base resolved source from bws_resolve_base_source().
 * @param array  $options  Tag options.
 * @param object $instance GB tag instance.
 * @return array{value:string, link_id:int, link_type:string}|null Triple when an
 *                        ambient arm applies (value may be ''), null to fall through.
 */
function bws_base_ambient_analog( string $tag, array $base, array $options, $instance ): ?array {
	// Applies only to a ROOT-ONLY chain rooted at the ambient entity. Other kinds own
	// their render: 'term' (explicit term step), 'site' (own gate), 'post' (src:ref on a
	// term archive steps term→post and reads the POST — don't short-circuit to the term).
	// A registry-source root still reads 'render_time'. Explicit sources (loop item,
	// src:current, id) win inside the factory, so they never land an ambient kind here.
	if ( 'render_time' !== bws_base_src_resolution( $options )['kind'] ) {
		return null;
	}

	switch ( $base['kind'] ?? '' ) {
		case 'term':
			$identity = bws_source_link_identity( $base );
			if ( null === $identity ) {
				return null;
			}
			return array(
				'value'     => bws_base_term_analog_read( $tag, $identity['id'], $options, $instance ),
				'link_id'   => $identity['id'],
				'link_type' => $identity['kind'],
			);

		case 'user':
			// The measured image/permalink carve-out — see the PHPDoc above.
			if ( ! in_array( $tag, array( 'title', 'content', 'text' ), true ) ) {
				return null;
			}
			$identity = bws_source_link_identity( $base );
			if ( null === $identity ) {
				return null;
			}
			return array(
				'value'     => bws_base_user_analog_read( $tag, $identity['id'], $options, $instance ),
				'link_id'   => $identity['id'],
				'link_type' => $identity['kind'],
			);

		case 'query_context':
			// Entity-LESS kind: claimed for every tag but `image` (carve-out: see PHPDoc).
			// Every other tag MUST be claimed — falling through hands a query-context base
			// to a falsy-id post read, the leak this kind exists to stop. Identity still
			// derived: the owner returns null for this kind → the no-wrap pair.
			if ( 'image' === $tag ) {
				return null;
			}
			$identity = bws_source_link_identity( $base );
			return array(
				'value'     => bws_base_query_context_analog_read( $tag, $base, $options, $instance ),
				'link_id'   => $identity ? $identity['id'] : 0,
				'link_type' => $identity ? $identity['kind'] : 'post',
			);
	}

	return null;
}

