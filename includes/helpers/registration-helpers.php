<?php
/**
 * Registration-time helpers.
 *
 * Utilities called during tag option-array construction and GB registration,
 * before any rendering happens. Keep this file scoped to wire-format /
 * registration concerns — not runtime resolution.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Which VISUAL group each option control belongs to, and which member leads it.
 *
 * The editor renders every option as a flat sibling, so a tag's panel reads as an
 * undifferentiated stack unless something says which controls describe ONE decision.
 * This is that statement, and it is deliberately keyed by option NAME rather than
 * declared per tag: the names are already canonical across every family (the same
 * `src`/`limit`/`use`/`key` appear on text, datetime, email, phone and every derived
 * modifier), so one map keeps the panels consistent and gives a new tag its grouping
 * for free.
 *
 * Applied by bws_strip_default_select_values(), i.e. only to OUR registrations — a map
 * the editor JS applied by name would also wrap GB core tags' identically-named options.
 *
 * The three groups mirror the canonical CONTROL order (FW-52: source → format → link →
 * fallback), SPLIT at one place: the field read (`use`/`key`) is its own box even though
 * it serializes inside the source group, because "where do I read from" and "what do I
 * read" are the two questions an author actually asks, and the folded-slot control has
 * boxed them separately since 1.17.0. This is what makes a base tag's panel and a
 * `{{join}}` slot's panel read alike.
 *
 * NOT grouped, on purpose:
 * - `format`, `fallback`, `as`, and the email/phone own-anchor pair (`subject`/`noLink`):
 *   each is a standalone decision, and a border around one control is noise.
 * - Folded slot keys (`A`, `B`, …): the slot control draws its own boxes inside one value.
 *
 * The LEAD is the member that stays boxed when it is the group's only visible control —
 * a source chain is a group whether or not it currently fans, and its box carries the
 * caption. Every other lone member renders bare (see assets/js/option-group.js).
 *
 * @since 1.17.0
 * @return array[] option name => [ group, is_lead ].
 */
if ( ! function_exists( 'bws_option_visual_groups' ) ) {
function bws_option_visual_groups(): array {
	return array(
		// Where the value comes from: the source chain, the legacy flat siblings it
		// absorbs, and the list-mode pair (list length is a source property, FW-52).
		'src'       => array( 'source', true ),
		'ref'       => array( 'source', false ),
		'srcTermIn' => array( 'source', false ),
		'limit'     => array( 'source', false ),
		'sep'       => array( 'source', false ),
		// What to read off it. BOTH are leads, and `key` has to be: on `{{email}}`,
		// `{{phone}}` and `{{table}}` the field key is the tag's ENTIRE read — there is no
		// `use` enum to lead the group — so a lone-member opt-out would give exactly the
		// tags whose read is simplest no field group at all, while `{{text}}` beside them
		// has one. The opt-out exists for a control that has no group to show (a lone
		// `linkTo` reading "No Link", a `try_` template's tag-level `limit`), not for a
		// group that happens to have one member.
		'use'          => array( 'field', true ),
		'key'          => array( 'field', true ),
		// The datetime key family, ALL in the one field box (user, 2026-08-05). A range's
		// four keys are one decision about what the tag reads, and its optional time
		// overrides are part of that decision rather than a separate one — leaving them
		// outside gave `datetime_single` a boxed `key` with a loose `timeKey` under it,
		// which reads as two groups where there is one. Whether a range wants an inner
		// start/end split is still open; that is a second box, not a different map.
		'timeKey'      => array( 'field', false ),
		'startKey'     => array( 'field', true ),
		'startTimeKey' => array( 'field', false ),
		'endKey'       => array( 'field', false ),
		'endTimeKey'   => array( 'field', false ),
		// How the value is rendered.
		//
		// `as` LEADS, and the reason is that it is not one control: `bws-as-size` renders
		// a return type AND an image size from a single option key, so on `{{image}}` it
		// is already a group of two and the box belongs to it. A box it earns must not
		// then vanish when the size control hides (the return type is not URL) — the
		// wrapper cannot see inside a composite, so the lead flag is what holds it.
		//
		// `format` does NOT lead: alone it is `{{join}}`'s assembly template, one control,
		// where a border would be noise. On the datetime tags it sits in a run with `as`
		// and boxes anyway. So one name-keyed map serves both, decided by how many members
		// are on screen rather than by which tag is being rendered.
		'as'              => array( 'format', true ),
		'rangeSep'        => array( 'format', false ),
		'format'          => array( 'format', false ),
		// `{{join}}`'s assembly pair. `mode` picks separator-vs-template and the two
		// alternatives sit behind it, exactly one revealed at a time — so the group always
		// has two visible members and needs no lead of its own. (Name-keyed, so a future
		// tag registering an unrelated `mode` would inherit this; join is the only one
		// today, and a second meaning is the signal to scope the map, not to special-case
		// a tag here.)
		'mode'            => array( 'format', false ),
		'valueSep'        => array( 'format', false ),
		'timeSep'         => array( 'format', false ),
		'showCurrentYear' => array( 'format', false ),
		'showMidnight'    => array( 'format', false ),
		// The entity-link set. NO LEAD, decided by trying both (user, 2026-08-05):
		// `linkKey` and `newTab` are both revealed by `linkTo`, so the box appears exactly
		// when a link is configured, and a tag left on "No Link" keeps a bare select —
		// compact, and less prominent until the feature is actually in use. The link is
		// the one group here that is genuinely OPTIONAL: a source and a field read are
		// what every tag does, so their boxes stand whether or not they are configured.
		'linkTo'    => array( 'link', false ),
		'linkKey'   => array( 'link', false ),
		'newTab'    => array( 'link', false ),
	);
}
}

/**
 * Drop the flat source options a CHAIN control has taken over.
 *
 * A tag whose `src` is authored as a chain does not also register the flat axes the chain
 * absorbed (`ref`, `srcTermIn`). The chain control reads a stored one on mount, shows it
 * as a step, and deletes it on the first commit — so a second control for the same value
 * is not a fallback, it is a second place to edit one key, and the two write different
 * things (the step, versus the flat key the next commit removes).
 *
 * Neither was hidden by anything before this. `ref` LOOKED hidden because its
 * `show_if src:'ref'` is a literal equality that chain wire fails — a condition written
 * for the pre-chain world happening to miss, so it still rendered on exactly the legacy
 * tags where the duplication costs most. `srcTermIn`'s `not:site` passes for every chain
 * spelling, so it always rendered. (Contrast `limit`/`sep`, which the chain did NOT
 * replace and which were updated deliberately, via `chain_fans`.)
 *
 * DERIVED from the chain option's own `flatAxes` — the same list the control deletes by,
 * so a change to what the chain absorbs cannot leave a stray control behind. Gated on the
 * control TYPE, so `term_*`, `try_*`, `{{table}}` and `{{call}}` (plain `select` sources,
 * which still author the flat pair) are untouched.
 *
 * Removing the OPTION does not remove the VALUE: GB seeds `extraTagParams` from the parsed
 * tag string, not from the registry (`{id, source, key, …, ...rest} = params`), and
 * re-serializes the whole state object — so a stored `srcTermIn` still reaches the chain
 * control on mount and still round-trips on a tag nobody touches. Registration only
 * decides whether a control renders. See docs/gb-constraints.md §Reserved Option Keys.
 *
 * @since 1.17.0
 * @param array $options Options array as registered in PHP.
 * @return array Same array minus the absorbed flat axes, if the source is a chain.
 */
if ( ! function_exists( 'bws_drop_chain_flat_options' ) ) {
function bws_drop_chain_flat_options( array $options ): array {
	if ( ! isset( $options['src']['type'] ) || 'bws-src-chain' !== $options['src']['type'] ) {
		return $options;
	}
	foreach ( (array) ( $options['src']['fold']['flatAxes'] ?? array() ) as $axis ) {
		unset( $options[ $axis ] );
	}
	return $options;
}
}

/**
 * Strip default-marked select options' first-entry value to '' before GB registration,
 * and stamp each option with its visual group.
 *
 * Options array entries flagged `_strip_default => true` get their first option's
 * value flipped to '' so the wire format omits the default token (GB drops empty
 * values from the serialized tag string). Internal canonical token (e.g. 'current',
 * 'key', 'content') is preserved in source files for readability; consumers apply
 * `?? '<canonical>'` to restore it at read time.
 *
 * The `_strip_default` marker itself is removed before passing to GB.
 *
 * GROUPING and the chain flat-axis drop ride along here because this is the one pass every
 * BWS registration already goes through, and neither must reach GB core tags (see
 * bws_option_visual_groups() / bws_drop_chain_flat_options()).
 * `_group` / `_group_lead` are inert to GB — it spreads unknown option keys through to
 * `allOptions`, which is the same route `show_if` and `fold` take.
 *
 * @since 1.7.0
 * @since 1.17.0 Stamps `_group` / `_group_lead`.
 * @param array $options Options array as registered in PHP.
 * @return array Options with strip + grouping applied.
 */
if ( ! function_exists( 'bws_strip_default_select_values' ) ) {
function bws_strip_default_select_values( array $options ): array {
	$groups  = bws_option_visual_groups();
	$options = bws_drop_chain_flat_options( $options );

	foreach ( $options as $name => &$opt ) {
		if ( ! empty( $opt['_strip_default'] ) && isset( $opt['options'][0]['value'] ) ) {
			$opt['options'][0]['value'] = '';
		}
		unset( $opt['_strip_default'] );

		if ( isset( $groups[ $name ] ) && ! isset( $opt['_group'] ) ) {
			$opt['_group'] = $groups[ $name ][0];
			if ( $groups[ $name ][1] ) {
				$opt['_group_lead'] = true;
			}
		}
	}
	return $options;
}
}
