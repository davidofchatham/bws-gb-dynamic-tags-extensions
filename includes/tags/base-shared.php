<?php
/**
 * Shared base-tag foundation: source options, traversal sub-options, source
 * dispatch, term-ambient dispatch, and the option-remap helper.
 *
 * These are the cross-tag primitives the base callbacks AND the other tag
 * families (datetime, email, fn, phone) build on — the `src`/`ref`/`srcTermIn`
 * option definitions, the try_ slot option builder, the post-id source
 * wrapper and the ambient-term analog read. They live
 * here (not in base-tags.php) because their scope is every tag, not just the
 * base renderers; base-tags.php now holds only the actual base tag callbacks,
 * the src:site source, and the try_ dispatch wrappers.
 *
 * Load order: required BEFORE base-tags.php and every other tag file, since
 * those call these builders/wrappers at registration and render time.
 *
 * Resolution model (L1 factory → traversal steps → L2 read by kind) is
 * documented on base-tags.php and in CONTEXT.md / docs/tag-reference.md; the
 * per-function PHPDoc below carries the load-bearing invariants (§V refs).
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
				array( 'value' => 'current', 'label' => __( 'Current', 'generateblocks' ) ),
				array( 'value' => 'ref',     'label' => __( 'In Reference/Relational Field', 'generateblocks' ) ),
				array( 'value' => 'site',    'label' => __( 'Site', 'generateblocks' ) ),
			),
			'_strip_default' => true,
		),
	);
}

/**
 * Upgrade a base tag's `src` option to the CHAIN control (FW-56).
 *
 * A base tag's source has always been a chain — a root plus fanning steps — but the
 * flat option spelling (`src` + `ref` + `srcTermIn`) tops out at one relationship
 * step plus one taxonomy step, so "the office of the staff member this event
 * references" was not awkward, it was inexpressible. The engine has always been able
 * to run an arbitrary chain; nothing let an author write one.
 *
 * DERIVED, never re-typed. The enum rows, the step labels, the taxonomy list, the
 * pickers and the engine↔wire slug map all come from the shipped builders that the
 * folded-slot control already reads, so the base tag and a `{{join}}` slot offer one
 * vocabulary. A second literal here is how the image tag's `Return type:` /
 * `Return image as:` labels drifted.
 *
 * The base tag keeps its own `use`/`key` options — this control edits the SOURCE
 * only. That is the difference from the folded slot, where the source and the read
 * fold into one value.
 *
 * NOT applied to the derived families. `bws_base_source_option()` stays a plain
 * select because `term_*`/`try_*`/`{{table}}` read its `options` rows to build their
 * own surfaces (bws_pick_src_values, bws_filter_site_from_src,
 * bws_build_slot_traversal_options); a slot authors its chain inside its folded
 * value instead.
 *
 * @since 1.17.0
 * @param array $args {
 *     @type array $source_opt A bws_base_source_option()-shaped array to upgrade.
 *                             Default bws_base_source_option().
 *     @type array $steps       Engine step keys offered as steps. Default
 *                             ['ref','srcTermIn'] — `rows` is deliberately absent:
 *                             the step type exists and runs, but no base-tag arm
 *                             consumes a meta_row, so offering it would author a
 *                             chain that renders nothing. It belongs with the table
 *                             authoring pass.
 * }
 * @return array Single-entry array keyed 'src'.
 */
function bws_build_src_chain_option( array $args = array() ): array {
	$source_opt = $args['source_opt'] ?? bws_base_source_option();
	$steps       = $args['steps'] ?? array( 'ref', 'srcTermIn' );

	if ( ! isset( $source_opt['src'] ) ) {
		return $source_opt;
	}

	$base_trav = bws_base_traversal_options();

	// Same step nouns the folded slot uses. A step CONTINUES a chain, so its row needs
	// a step-shaped noun: `srcTermIn`'s own label is a checkbox question ("Get from
	// taxonomy term?"), unusable in a step list.
	$step_labels = array(
		'srcTermIn' => __( 'In Taxonomy Term', 'generateblocks' ),
		'ref'       => __( 'In Reference/Relational Field', 'generateblocks' ),
		'rows'      => __( 'In Repeater Rows', 'generateblocks' ),
	);
	$step_rows   = array();
	foreach ( $steps as $step ) {
		if ( isset( $step_labels[ $step ] ) ) {
			$step_rows[] = array( 'value' => $step, 'label' => $step_labels[ $step ] );
		}
	}

	$tax_rows = array( array( 'value' => '', 'label' => __( 'Select…', 'generateblocks' ) ) );
	if ( function_exists( 'get_taxonomies' ) ) {
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$tax_rows[] = array(
				'value' => $tax->name,
				'label' => $tax->labels->name ?? $tax->name,
			);
		}
	}

	$picker = static function ( array $def ): array {
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
	};

	// The source rows a STEP 0 root may take. `ref` is a step, not a root, so it is
	// dropped from the root enum: spelling it as a root is what the flat option had
	// to do when a tag could hold only one source token.
	$root_rows = array();
	foreach ( (array) ( $source_opt['src']['options'] ?? array() ) as $row ) {
		if ( 'ref' !== ( $row['value'] ?? '' ) ) {
			$root_rows[] = $row;
		}
	}

	$source_opt['src']['type'] = 'bws-src-chain';
	$source_opt['src']['fold'] = array(
		'container'  => 'base',
		'srcRows'    => $root_rows,
		// The root an ABSENT `src` means. Derived from the row `_strip_default` is about
		// to blank, so the two cannot disagree: the stripped default IS what absence
		// spells. The control shows it as the chain's root (an unset tag reads the
		// ambient entity, so displaying nothing was showing the author less than the
		// tag does) and strips it back out on commit, keeping the wire as it was.
		'defaultRoot' => (string) ( $source_opt['src']['options'][0]['value'] ?? '' ),
		'stepRows'    => $step_rows,
		'slugMap'    => array(
			'ref'       => 'refs',
			'srcTermIn' => 'terms',
			'rows'      => 'entries',
		),
		// Which steps are OFFERABLE where — derived from the engine's own refusal list
		// (BWS_TRAVERSAL_STEP_INPUT_KINDS) plus the produced-kind map, so the control
		// never offers a step the engine would answer empty for. Display only; a stored
		// step still renders and still shows in its own picker.
		'stepApplies' => function_exists( 'bws_fold_step_applicability' )
			? bws_fold_step_applicability()
			: array(),
		'taxonomies' => $tax_rows,
		'refOption'  => $picker( $base_trav['ref'] ),
		// The flat keys a commit REPLACES. Their meaning moves into the chain value,
		// so leaving them beside it would store one source two ways — and the flat
		// pair is what the retired arms used to dispatch on.
		'flatAxes' => array( 'ref', 'srcTermIn' ),
		'retiredSrc' => defined( 'BWS_FOLD_RETIRED_SRC_TOKENS' ) ? BWS_FOLD_RETIRED_SRC_TOKENS : array(),
	);

	return $source_opt;
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
 * try_allow_site_slot). A future "ID source + site fallback" (the author-serialized-
 * entity-id source flavor — CONTEXT.md §Language "Source binding") belongs in a
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
 * `ref` — shown when src:ref; the relationship field key for the step.
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
			// preset a kind for src:ref (presetKind returns null): the ref-step target
			// post type is not reliably known, so the key list stays UNSCOPED with the
			// generic "Meta/Option Field" label (SPEC V3). v2 will type-filter this to
			// relationship/post_object.
			// This is the FLAT spelling's relationship key, so it belongs to `src:ref`
			// alone — a flat tag has one `src`, and site and ref are alternative
			// values of it. A site-rooted relationship is a CHAIN (`src:site;refs,x`),
			// which the engine has read since 1.17.0 and the chain control authors.
			'show_if'     => array( 'src' => 'ref' ),
		),
		'srcTermIn' => array(
			// `bws-term-hop` keeps the retired word ON PURPOSE. A control `type` is a
			// registered identifier the JS matches on, so it is interface, not prose —
			// renaming it here alone silently unregisters the control. Whether to rename
			// it (and its file) in lockstep is a decision the vocabulary pass left open.
			'type'      => 'bws-term-hop',
			'label'     => __( 'Get from taxonomy term?', 'generateblocks' ),
			'help'      => __( 'Field is in a taxonomy term on this source.', 'generateblocks' ),
			'pickLabel' => __( 'Taxonomy', 'generateblocks' ),
			'pickHelp'  => __( 'Pick the taxonomy.', 'generateblocks' ),
			// Hidden for src:site — no entity to step terms from. (Term-context tags
			// override this to src:ref in the template registry.)
			'show_if'   => array( 'src' => 'not:site' ),
		),
	);
}

/**
 * Text-family FIELD leaf: the `use` (read selector) + `key` (field key) pair.
 *
 * GROUP-PURE LEAF (FW-52 discipline): every key returned belongs to the canonical
 * SOURCE group, so the caller places the pair at that group's position in its own
 * control order. A leaf returns the enum + control shape ONLY — callers overlay
 * `show_if`, label prefixes and any context-specific help. Multi-group returns are
 * COMPOSERS, not leaves; do not bundle format/fallback keys in here.
 *
 * Single source for what were FOUR byte-identical copies of the text read enum:
 * base `{{text}}` registration, the `text` modifier template (feeding the term_ and
 * try_ families), the {{join}} slot loop, and the folded-slot control. Copies is how the
 * `Return type:` / `Return image as:` label drift on image happened; text is fixed
 * first because the fold consumes it (image is NOT a fold consumer — a table cell
 * needs a full <img>, which the image tag does not emit).
 *
 * Precedent: bws_get_base_datetime_single_options() /
 * bws_get_datetime_single_template_options() have composed from shared leaves since
 * 1.6.0 — same leaves, different composition per context, zero duplication.
 *
 * @since 1.17.0
 * @return array { 'use' => array, 'key' => array } — definitions WITHOUT `show_if`
 *               (base overlays `use:not:title`; the template encodes the same fact
 *               declaratively via try_use_no_key_values).
 */
function bws_get_text_field_options(): array {
	return array(
		'use' => array(
			'type'           => 'select',
			'label'          => __( 'Text Field', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'key',   'label' => __( 'Meta/Option Field', 'generateblocks' ) ),
				array( 'value' => 'title', 'label' => __( 'Title/Name', 'generateblocks' ) ),
			),
			'_strip_default' => true,
		),
		'key' => array(
			'type'         => 'bws-field-combo',
			'label'        => __( 'Meta/Option Field Key', 'generateblocks' ),
			'dynamicLabel' => true,
			'help'         => __( 'ACF or meta field key.', 'generateblocks' ),
			'placeholder'  => 'field_name',
		),
	);
}

/**
 * Build the READ (`use`) option definition for one numbered slot, derived from a
 * base read definition. The read-axis twin of bws_build_slot_traversal_options()
 * (source axis) — same derive-don't-copy contract, same `$n` duties.
 *
 * Derivation rules:
 *   - options: base `use.options` verbatim. Slot ≥2 prepends the `same` (inherit)
 *     row when $allow_same — the read-axis counterpart of "Same as Previous Source".
 *   - label: the NOUN comes off the base definition's own label ("Text Field",
 *     "Image Field", …), emitted as "N: <noun>". Never hand-author a per-container
 *     read label: that is the drift this twin exists to kill.
 *   - `_strip_default` preserved (V5).
 *
 * `$n` serves exactly TWO purposes: the same-row gate and the "N: " label prefix
 * (V10). The FOLD consumes only the returned `['options']` ROWS and supplies its
 * own slot heading; the prefixed label is for the legacy FLAT callers (try_/join),
 * which register one option key per axis per slot. Do not "clean up" the prefix —
 * join's shipped registration reads it.
 *
 * Pure fn of (slot ordinal, base read def, flag) — no WP beyond __(). Locally
 * harnessable (tools/test/slot-options-build-test.php).
 *
 * @since 1.17.0
 * @param int   $n           Slot ordinal (1-based).
 * @param array $base_read   Base `use` definition (e.g. bws_get_text_field_options()['use']).
 * @param bool  $allow_same  When true, slot ≥2 gets the inherit row. FALSE for
 *                           COMBINING containers ({{join}}) because per-slot
 *                           handlers are NOT BUILT YET, not because same-read is
 *                           degenerate there: `use(same)` is legal in combining and
 *                           useful as same field + same source, DIFFERENT handler.
 *                           Flip to true when per-slot handlers ship.
 * @return array One option definition (type/label/options/_strip_default). Empty
 *               array when $base_read carries no options (nothing to select).
 */
function bws_build_slot_read_options( int $n, array $base_read, bool $allow_same = true ): array {
	$read_opts = $base_read['options'] ?? array();
	if ( empty( $read_opts ) ) {
		return array();
	}

	$rows = array_values( $read_opts );
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
 * Build the FOLDED slot option definitions for a multislot container (FW-56/57).
 *
 * One option key per slot — `A`, `B`, … — each of type `bws-slot-fold`, whose VALUE
 * carries that slot's whole configuration (source chain + field read + per-slot
 * options; grammar in includes/helpers/slot-fold.php). Replaces the six flat keys per
 * slot the same container registered before the fold.
 *
 * THIS IS WHERE THE CONTROL'S VOCABULARY COMES FROM. GB passes an option's whole
 * config through to `generateblocks.editor.tagSpecificControls`, so the `fold`
 * sub-array carries every enum, label and noun the repeater renders — all of it
 * DERIVED from the shipped builders (bws_base_source_option, bws_base_traversal_options,
 * bws_build_slot_traversal_options, bws_build_slot_read_options and the caller's field
 * leaf). assets/js/slot-fold-control.js hand-authors none of it: four copies of the
 * text read enum, and image's `Return type:` / `Return image as:` drift, are what
 * re-typing strings at a consumer produces.
 *
 * CONTAINER SENSITIVITY, all of it explicit in $args:
 *   - `combining` ({{join}}, {{table}}) seeds a slot with its READ UNSET, because
 *     choosing a field IS the configuration act there; SELECTING (`try_*`) seeds
 *     `use(same)`. The control reads this flag; the renderer reads it again for what
 *     an absent read MEANS (bws_fold_slot_flat_options).
 *   - `allow_same_read` follows the read twin's flag, so the inherit row appears in
 *     exactly the containers whose resolver honors it.
 *   - `steps` names which traversal steps this container can express. It is a
 *     CAPABILITY list, not decoration: the flat render seam holds one relationship step and one
 *     term step, so offering a step the seam cannot flatten would author unrenderable
 *     wire.
 *
 * @since 1.17.0
 * @param array $args {
 *     @type string $container       'join' | 'table' | 'try' (required).
 *     @type array  $base_read       Base read definition (e.g. bws_get_text_field_options()['use']).
 *     @type array  $base_key        Base field-key definition (…['key']).
 *     @type int    $max             Slot ceiling (required).
 *     @type int    $min             Slots always visible. Default 2.
 *     @type bool   $combining       True for join/table. Default true.
 *     @type bool   $per_slot_use    Container gives each slot its own read axis. Default true.
 *     @type bool   $allow_site      Keep `site` in the source enum. Default true.
 *     @type bool   $allow_same_read Offer the read inherit row at slot ≥2. Default false.
 *     @type array  $steps            Engine step keys offered as steps. Default ['srcTermIn'].
 *     @type array  $tag_level       Legacy axes this container owns at TAG level, never per
 *                                   slot (e.g. a try_ template's `limit`). Ships to the
 *                                   editor as the complement, `flatAxes`.
 *     @type string $noun            Slot noun, lower case ("attempt", "field", "column"). Drives
 *                                   BOTH the Add button and the panel header ("Attempt A") —
 *                                   there is deliberately no separate label parameter, because
 *                                   two registered strings for one unit drift apart.
 *     @type string $field_scope     Field-picker scope ('row' for a repeater container).
 *     @type string $scope_state_key Tag-level option whose value scopes the picker.
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
	$allow_site      = ! isset( $args['allow_site'] ) || (bool) $args['allow_site'];
	$allow_same_read = ! empty( $args['allow_same_read'] );
	$steps            = $args['steps'] ?? array( 'srcTermIn' );
	$base_read       = $args['base_read'] ?? array();
	$base_key        = $args['base_key'] ?? array();
	$noun            = (string) ( $args['noun'] ?? '' );

	// ONE registered noun drives BOTH surfaces — the Add button (`+ Add attempt`) and
	// the panel header (`Attempt A`). Aligned, not verbatim: the button carries the
	// verb, the header the ordinal. Registering the two as independent strings is
	// exactly how they drift, so the header pattern is DERIVED here and containers pass
	// no label. `%s` is the slot ORDINAL (`A`, `B`, …) — its option key and its `%A`
	// format token, so the header, the wire and the format string name a slot alike.
	$label_pattern = __( 'Slot %s', 'generateblocks' );
	if ( '' !== $noun ) {
		$first         = function_exists( 'mb_substr' ) ? mb_substr( $noun, 0, 1 ) : substr( $noun, 0, 1 );
		$rest          = function_exists( 'mb_substr' ) ? mb_substr( $noun, 1 ) : substr( $noun, 1 );
		$upper         = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
		$label_pattern = $upper . $rest . ' %s';
	}

	$base_src  = bws_base_source_option();
	$base_trav = bws_base_traversal_options();

	// Source enum — through the SLOT twin, so the `site` filter and the "Same as
	// Previous Source" inherit row are the shipped ones rather than new copies. Slot 1
	// gets the plain list, slot ≥2 the list with the inherit row.
	$src_rows         = bws_build_slot_traversal_options( 1, $base_src, $base_trav, $allow_site )['src']['options'];
	$src_rows_inherit = bws_build_slot_traversal_options( 2, $base_src, $base_trav, $allow_site )['src']['options'];

	// Hop enum. A step is a step that CONTINUES a chain, so its row needs a step-shaped
	// noun: `srcTermIn`'s own label is a checkbox question ("Get from taxonomy term?"),
	// unusable in a step list. Phrasing parallels the shipped src row it sits beside
	// ("In Reference/Relational Field").
	$step_labels = array(
		'srcTermIn' => __( 'In Taxonomy Term', 'generateblocks' ),
		'ref'       => __( 'In Reference/Relational Field', 'generateblocks' ),
		'rows'      => __( 'In Repeater Rows', 'generateblocks' ),
	);
	$step_rows   = array();
	foreach ( $steps as $step ) {
		if ( isset( $step_labels[ $step ] ) ) {
			$step_rows[] = array( 'value' => $step, 'label' => $step_labels[ $step ] );
		}
	}

	// Read enum — through the read twin (its `['options']` rows only; the fold supplies
	// its own slot heading). A COMBINING container needs an explicit unset row: there,
	// absent means UNCONFIGURED (the slot is skipped), which is not what the first enum
	// row means. In a selecting container absent IS the first row's stripped default,
	// so no extra row — the flat UI's behaviour, unchanged.
	$read_rows         = bws_build_slot_read_options( 1, $base_read, false )['options'] ?? array();
	$read_rows_inherit = bws_build_slot_read_options( 2, $base_read, $allow_same_read )['options'] ?? array();
	if ( $combining ) {
		$unset_row         = array( 'value' => '', 'label' => __( 'Select…', 'generateblocks' ) );
		$read_rows         = array_merge( array( $unset_row ), $read_rows );
		$read_rows_inherit = array_merge( array( $unset_row ), $read_rows_inherit );
	}

	// Taxonomy rows for a `terms` step. Mirrors what the shipped bws-term-hop control
	// lists from the REST store (public taxonomies), read here instead so the whole
	// enum arrives with the definition.
	$tax_rows = array( array( 'value' => '', 'label' => __( 'Select…', 'generateblocks' ) ) );
	if ( function_exists( 'get_taxonomies' ) ) {
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$tax_rows[] = array(
				'value' => $tax->name,
				'label' => $tax->labels->name ?? $tax->name,
			);
		}
	}

	/** Reduce an option definition to the fields the pickers need. */
	$picker = static function ( array $def ): array {
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
	};

	$fold = array(
		'container'      => $container,
		'combining'      => $combining,
		'perSlotUse'     => $per_slot_use,
		'min'            => $min,
		'max'            => $max,
		'noun'           => $noun,
		'srcRows'        => $src_rows,
		'srcRowsInherit' => $src_rows_inherit,
		// The root an absent chain SPELLS on slot 1 — derived from the very row the
		// enum leads with, so the two cannot disagree. The control DISPLAYS it rather
		// than rendering an empty picker: a picker whose value is `''` matches no row,
		// so the browser paints the first one ("Current") while the control believes
		// nothing is selected — the row on screen cannot then be picked, because
		// selecting it fires no change event. Slot ≥2 spells its absence `same`
		// instead, which the control holds (writeChainAt already materializes it).
		'defaultRoot'    => (string) ( $src_rows[0]['value'] ?? '' ),
		'stepRows'        => $step_rows,
		'readRows'       => $read_rows,
		'readRowsInherit' => $read_rows_inherit,
		'readLabel'      => $base_read['label'] ?? '',
		// Engine option value → wire step slug (DECISION 3). The wire names a STEP
		// (`refs` fans to related posts), the engine names an option (`src:ref`); the
		// map is the only place the two vocabularies meet.
		'slugMap'        => array(
			'ref'       => 'refs',
			'srcTermIn' => 'terms',
			'rows'      => 'entries',
		),
		// Which steps are OFFERABLE where — derived from the engine's own refusal list
		// (BWS_TRAVERSAL_STEP_INPUT_KINDS) plus the produced-kind map, so the control
		// never offers a step the engine would answer empty for. Display only; a stored
		// step still renders and still shows in its own picker.
		'stepApplies' => function_exists( 'bws_fold_step_applicability' )
			? bws_fold_step_applicability()
			: array(),
		'taxonomies'     => $tax_rows,
		'refOption'      => $picker( $base_trav['ref'] ),
		// The LEGACY per-slot axes, so the editor's mount migrator and the control fold
		// and delete exactly the keys the converter does. Derived through the single owner
		// of the tag-level subtraction (bws_fold_slot_flat_axes) — a hand-kept list in
		// the control is what deleted a try_ template's TAG-level `limit`/`key` the first
		// time an author touched slot 1.
		'flatAxes'     => function_exists( 'bws_fold_slot_flat_axes' )
			? bws_fold_slot_flat_axes( (array) ( $args['tag_level'] ?? array() ) )
			: array(),
		// The RETIRED source tokens the mount migrator must decline rather than fold
		// (#56). Shipped for the same reason as flatAxes: the converter's own guard
		// reads the constant directly, and a hand-kept copy in JS is how the two paths
		// would come to store one tag two ways.
		'retiredSrc'     => defined( 'BWS_FOLD_RETIRED_SRC_TOKENS' ) ? BWS_FOLD_RETIRED_SRC_TOKENS : array(),
	);
	// OMITTED, not empty, when the container has no per-slot key (try_title and the
	// other read-less templates). An empty array here would reach the control as JS
	// `[]`, which is TRUTHY — enough to render a key picker with no label for a slot
	// whose read is a tag-level option.
	if ( ! empty( $base_key ) ) {
		$fold['keyOption'] = $picker( $base_key );
	}
	if ( ! empty( $args['entries_option'] ) ) {
		$fold['entriesOption'] = $picker( (array) $args['entries_option'] );
	}
	if ( ! empty( $args['field_scope'] ) ) {
		$fold['fieldScope'] = (string) $args['field_scope'];
	}
	if ( ! empty( $args['scope_state_key'] ) ) {
		$fold['scopeStateKey'] = (string) $args['scope_state_key'];
	}

	$options = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		// The label carries the ORDINAL SPELLING, not the number: it is the one place the
		// capital prefix becomes legible. An author reading `B:src(same);key(role)` on the
		// wire finds the panel headed "Slot B", and the {{join}} format token for it is
		// `%B` — one alphabet across the key, the label and the token.
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
 * mode — e.g. a srcTermIn term-step, or the shared L1/L2 resolver's plural
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
 * (bws_post_custom_text_core, content-tags.php) so a try_ slot in list mode
 * joins identically to the same underlying tag used standalone (I6 parity):
 *   - limit — an ALREADY-RESOLVED int from the caller: >= 1 caps, `0` means
 *     UNLIMITED and slices nothing. Not a ceiling: an author setting limit:5 joins
 *     up to 5 items.
 *     THIS IS THE ONE SITE THAT CANNOT ASK. Since 1.17.0 the tag-level default is a
 *     property of the tag's source SPELLING (bws_limit_default), and this function
 *     receives no options — structurally it cannot know the era. So it stopped
 *     re-clamping and takes the resolved value instead. The parameter is REQUIRED
 *     and typed `int` for that reason: a defensive `?? 1` here would be the legacy
 *     default silently applied to chain wire, which renders wrong and looks normal.
 *   - sep   = $sep ?? ', ' — null (absent) → default ', '; an explicit empty
 *     string is honored (matches base `$options['sep'] ?? ', '`, which only
 *     defaults on an absent key — author may deliberately join with no sep).
 *
 * A 1-element list with the default limit returns the single element verbatim
 * (no trailing separator — sep is never applied to a lone item) — the
 * byte-identical backward-compat gate for existing try_text/try_content/try_image.
 * [SPEC §32 V3,V4]
 *
 * Pure — no WP/GB symbols (bws_clamp_limit is itself pure). Locally harnessable
 * (tools/test/try-join-seam-test.php).
 *
 * @since 1.11.0
 * @since 1.17.0 Limit interpretation delegated to bws_clamp_limit; `0` = unlimited.
 * @since 1.17.0 Takes an already-resolved int `limit`; the internal re-clamp is gone.
 * @param array<int,string> $items Finished item strings (already non-empty).
 * @param mixed              $sep   Separator; null → ', '. Explicit '' honored.
 * @param int                $limit Resolved max items to join; 0 = unlimited. REQUIRED.
 * @return string Joined output (or '' if no items).
 */
function bws_try_join_items( array $items, $sep, int $limit ): string {
	if ( empty( $items ) ) {
		return '';
	}
	$s = ( null === $sep ) ? ', ' : $sep;
	return implode( $s, array_slice( $items, 0, $limit ?: null ) );
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
 * the V11 leak-fix (base the relationship step on the ambient term, not on get_the_ID()).
 *
 * REF-ONLY (SPEC §V13): the wrapper NEVER assembles a `srcTermIn` step. srcTermIn
 * (post→term) is owned DOWNSTREAM by the wrapper's callers — datetime/text/title
 * srcTermIn branches call bws_get_srcterm_terms() on the returned POST id. Routing
 * the wrapper through the SEAM's bws_field_values_assemble_steps() (which emits a
 * srcTermIn term-step) would collapse to false and empty those callers (B2). The
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

// bws_wrapper_ref_steps() — the wrapper's REF-ONLY step set (SPEC §V13, B2) — MOVED to
// includes/helpers/slot-fold-compile.php in 1.17.0 (5h). Its rule is now stated against
// the compiled chain (the LEADING RUN of ref steps, stopping at the first step of another
// type), which keeps `srcTermIn` excluded exactly as before and lets a multi-step
// relationship chain step every step instead of just the first.

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
 * What this tag's source chain RESOLVES TO — the base callbacks' dispatch axis (FW-63).
 *
 * Load-order-guarded shim over bws_fold_src_resolution(). Every base arm asks this
 * one question instead of comparing `$options['src']` to `'ref'`/`'site'` or reading
 * `srcTermIn` directly, which is what makes a chain-spelled source and a flat-spelled
 * source take the SAME arm. Before FW-63 they did not: `{{text src:terms,department}}`
 * rendered the ambient post's title, a plausible wrong value rather than an empty one.
 *
 * NO FALLBACK, deliberately. A first draft carried a compiler-absent branch
 * reproducing the pre-1.17.0 token tests — which is a FOURTH copy of the dispatch
 * this refactor exists to remove, and one nothing exercises, so it would rot into a
 * quietly different answer. `slot-fold-compile.php` is required at plugin load, well
 * before any tag registers, so the guard was defending against a state that cannot
 * occur.
 *
 * @since 1.17.0
 * @param array $options Tag options.
 * @return array{root:string, kind:string, fans:bool} See bws_fold_chain_resolution().
 */
function bws_base_src_resolution( array $options ): array {
	return bws_fold_src_resolution( $options );
}

/**
 * Collapse a base source to the callback's POST id via ref-only steps (SPEC §V13).
 *
 * The post-path counterpart of the ambient-term branch: runs the wrapper's
 * ref-only step set (src:ref → post→post[] step; NEVER srcTermIn, which the
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
 * Ids of the resolved sources a base tag's chain produces, filtered to one KIND.
 *
 * The plural read behind both list arms. Runs the tag's WHOLE compiled chain — not
 * the wrapper's leading run of ref steps — because the arm has already established
 * what the chain resolves to (bws_base_src_resolution), so every step in it is one
 * the caller asked for. That is what closes the §F9.3 hole, where a `terms` step
 * after a `refs` step was silently dropped and the tag read the ref'd POST instead.
 *
 * Order is document order (the engine appends, never sorts). Only sources of the
 * requested kind contribute; the caller slices to `limit` and joins with `sep`.
 *
 * @since 1.14.0
 * @since 1.17.0 Compiles the whole chain and takes a $kind; was ref-only steps.
 * @param array  $base    Base resolved source.
 * @param array  $options Tag options.
 * @param string $kind    Resolved-source kind to keep ('post'|'term'|…).
 * @return int[] Entity ids in document order (may be empty).
 */
function bws_base_source_ids_of_kind( array $base, array $options, string $kind ): array {
	if ( ! function_exists( 'bws_run_traversal' ) || ! function_exists( 'bws_field_values_assemble_steps' ) ) {
		return array();
	}
	$sources = bws_run_traversal( array( $base ), bws_field_values_assemble_steps( $options ) );
	$ids     = array();
	foreach ( $sources as $src ) {
		if ( is_array( $src ) && $kind === ( $src['kind'] ?? '' ) ) {
			$id = (int) ( $src['id'] ?? 0 );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
	}
	return $ids;
}

/**
 * Collapse a base source to the FULL post-id LIST (SPEC §V14).
 *
 * For a tag that offers list mode on a post-resolving source (text/title/datetime,
 * §V14 offered⟺resolvable), the post branch reads EVERY fanned-out target
 * (bws_run_traversal keeps all, §V6) — not just the first.
 *
 * @since 1.14.0
 * @param array $base    Base resolved source.
 * @param array $options Tag options.
 * @return int[] Post ids in document order (may be empty).
 */
function bws_base_post_ids_from_source( array $base, array $options ): array {
	return bws_base_source_ids_of_kind( $base, $options, 'post' );
}

/**
 * The TERM ids a base tag's chain resolves to — the term arm's read.
 *
 * Replaces the arms' `bws_get_srcterm_terms( $post_id, $tax )` call, which could
 * only express ONE post→term step off a single collapsed post id. Routing through
 * the engine means the flat `srcTermIn` spelling and the chain `terms,<tax>`
 * spelling read the same terms, which is the equivalence the fold matrix asserts.
 *
 * Two differences from the retired call, both deliberate:
 *
 * - A relationship step BEFORE the term step fans (§V6) instead of collapsing to the
 *   first ref'd post, so `src:ref|ref:x|srcTermIn:y` can now yield terms from every
 *   ref'd post. With `limit` unset the flat spelling still caps the source list at
 *   one, so the first rendered term is unchanged — the difference is reachable only
 *   with an explicit `limit` above one, of which the two-database survey found none.
 *   This is a stated divergence, not an oversight: fold-test-matrix.md §F9.6 pins it,
 *   and the alternative is keeping a first-only collapse the plural source model
 *   already names a defect, in the one arm that was still performing it.
 * - Ids, not WP_Term objects. Every caller only ever read `->term_id`.
 *
 * @since 1.17.0
 * @param array $base    Base resolved source.
 * @param array $options Tag options.
 * @return int[] Term ids in document order (may be empty).
 */
function bws_base_term_ids_from_source( array $base, array $options ): array {
	return bws_base_source_ids_of_kind( $base, $options, 'term' );
}

/**
 * Whether a base callback should read the AMBIENT TERM instead of a post.
 *
 * True iff (a) no explicit `srcTermIn` step is set (that branch owns its own
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
	// One test replaces three (FW-63): the ambient analog applies only when the
	// chain is ROOT-ONLY and roots at the ambient entity. Every other kind names a
	// branch that owns its own render — 'term' is the explicit post→term step (which
	// is incoherent from a term base), 'site' has its own gate, and 'post' steps
	// term→post (§V11) so the post path must not be short-circuited to the term's
	// own analog. A registry-source root still reads 'base' and still reaches the
	// $base['kind'] test below, exactly as the old src test let it.
	if ( 'base' !== bws_base_src_resolution( $options )['kind'] ) {
		return 0;
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
	switch ( $tag ) {
		case 'title':
			return bws_term_title_core( $term_id, $options, $instance );

		case 'text':
			$use = $options['use'] ?? 'key';
			return 'title' === $use
				? bws_term_title_core( $term_id, $options, $instance )
				: bws_term_custom_text_core( $term_id, $options, $instance );

		case 'content':
			$use = $options['use'] ?? 'content';
			return 'key' === $use
				? bws_term_custom_text_core( $term_id, $options, $instance )
				: bws_term_description_core( $term_id, $options, $instance );

		case 'permalink':
			return bws_term_permalink_core( $term_id, $options, $instance );

		case 'image':
			// I1 gap #29 — a term has no intrinsic image analog. A key reads a term
			// image field; with no key there is no analog datum, BUT a configured
			// Media Library fallback still applies (fallback = last resort, gap or not).
			// bws_term_custom_image_core handles the no-key case itself: empty key →
			// the shared bws_handle_media_fallback (id-or-url, SPEC §V19) → the fallback
			// (or '' when none set). So call it unconditionally — no key + no fallback
			// stays empty (honest gap), no key + fallback yields the fallback. Keeps the
			// standalone tag byte-identical to a try_image slot (same core, V8/C9).
			return bws_term_custom_image_core( $term_id, $options, $instance );
	}

	return '';
}

// ===============================================
// USER-AMBIENT DISPATCH (#19 author kind, 1.15.0)
// ===============================================

/**
 * Whether a base callback should read the AMBIENT USER instead of a post.
 *
 * The user-kind counterpart of bws_base_ambient_term_id(): true iff the factory's
 * base resolved source is a user (bare tag on an author archive, #19). Mirrors the
 * term gate's guards — an explicit srcTermIn step, src:site, or src:ref keeps its
 * own meaning (no user relationship step exists yet, so src:ref falls through to the post
 * path), and explicit src/loop/id already won inside the factory (SPEC §V1).
 *
 * @since 1.15.0
 * @param array $base    Base resolved source from bws_resolve_base_source().
 * @param array $options Tag options.
 * @return int User id when the ambient-user analog path applies, else 0.
 */
function bws_base_ambient_user_id( array $base, array $options ): int {
	// Same one-test gate as the term twin (FW-63) — see bws_base_ambient_term_id().
	if ( 'base' !== bws_base_src_resolution( $options )['kind'] ) {
		return 0;
	}
	if ( 'user' !== ( $base['kind'] ?? '' ) ) {
		return 0;
	}
	return (int) ( $base['id'] ?? 0 );
}

/**
 * Read a base tag's USER analog on an author archive (#19, CONTEXT.md I1).
 *
 * The I1 source-analog table applied to an ambient user — each base tag at its
 * DEFAULT `use` yields the user's intrinsic analog:
 *
 *   title   → display name          (get_the_author_meta('display_name'))
 *   content → biographical info      (get_the_author_meta('description'))
 *   text    → use:title = display name; key-mode = user meta field (1.16.0,
 *             FW-48 seam half — closes the ABSORB-seam hole so {{text}},
 *             {{join}} slots and try_text resolve on an author archive)
 *
 * Values route through GenerateBlocks_Dynamic_Tag_Callbacks::output() so GB's
 * per-tag transforms (trunc/replace/trim/case/wpautop/link) apply, matching the
 * term analog readers.
 *
 * Scope: title + content (1.15.0, the plan's author-archive dispatch rows) +
 * text (1.16.0, FW-48 seam half). This returns '' for any other tag, so an
 * unhandled tag renders empty rather than wrong. Deferred author analogs (FW-47):
 *   - permalink: get_author_posts_url() datum EXISTS (bws_resolve_link_url
 *     already resolves it for link-wrap) but a bare {{permalink}} is circular
 *     on the author's own archive (= the page URL). Non-circular uses need a
 *     NON-ambient user source (src:ref->user or the ID source) that doesn't
 *     exist yet — soft-gated on FW-32/FW-39, not a standalone call.
 *   - image: no clean intrinsic analog (parity with the #29 term-image gap);
 *     the avatar is the candidate but adds a Gravatar HTTP + privacy surface.
 *     A use:key ACF user-image read works today (key-mode, not analog).
 *   - datetime: folds in with FW-9's remaining datetime context work.
 *
 * @since 1.15.0
 * @since 1.16.0 text case (FW-48 seam half).
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
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $name, $options, $instance );

		case 'text':
			// Mirror of the term analog's text dispatch: use:title → the intrinsic
			// analog (display name), key-mode → a user meta field read shaped like
			// bws_term_custom_text_core (fallback emit on miss, '0' preserved).
			if ( 'title' === ( $options['use'] ?? 'key' ) ) {
				return bws_base_user_analog_read( 'title', $user_id, $options, $instance );
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
					? GenerateBlocks_Dynamic_Tag_Callbacks::output( $fallback, $options, $instance )
					: '';
			}
			return GenerateBlocks_Dynamic_Tag_Callbacks::output( $value, $options, $instance );

		case 'content':
			$bio = get_the_author_meta( 'description', $user_id );
			if ( ! is_string( $bio ) || '' === $bio ) {
				return '';
			}
			return GenerateBlocks_Dynamic_Tag_Callbacks::output(
				bws_sanitize_rich_content( $bio ),
				$options,
				$instance
			);
	}

	return '';
}

