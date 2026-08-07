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
 * Applied by bws_prepare_registration_options(), i.e. only to OUR registrations — a map
 * the editor JS applied by name would also wrap GB core tags' identically-named options.
 *
 * The FOUR groups mirror the canonical CONTROL order (FW-52: source → format → link →
 * fallback), SPLIT at one place: the field read (`use`/`key`) is its own box even though
 * it serializes inside the source group, because "where do I read from" and "what do I
 * read" are the two questions an author actually asks, and the folded-slot control has
 * boxed them separately since 1.17.0. This is what makes a base tag's panel and a
 * `{{join}}` slot's panel read alike.
 *
 * NOT grouped, on purpose:
 * - `fallback`, and the email/phone own-anchor pair (`subject`/`noLink`): each is a
 *   standalone decision, and a border around one control is noise.
 * - Folded slot keys (`A`, `B`, …): the slot control draws its own boxes inside one value.
 *
 * The LEAD is the member that stays boxed when it is the group's only visible control.
 * Every other lone member renders bare (see assets/js/option-group.js) — the per-group
 * reasoning is on each block below, because the leads were decided one at a time and for
 * different reasons.
 *
 * CAPTIONS BELONG TO CONTROLS, NOT TO GROUPS (v1.17.0). The wrapper renders no caption at
 * all; a control that draws its own captions inside the wrapper's box is what puts one
 * there. So the source group reads "SOURCE" / "SOURCE PATH" on the tags whose source is a
 * chain control, and shows the same box bare on `term_*`, `try_*`, `{{table}}` and
 * `{{call}}`, whose source is a plain select. That asymmetry is accepted for v1 and
 * tracked on FW-64: making it uniform means the wrapper renders the caption, and the
 * chain control's is DYNAMIC (it changes with chain length), so the wrapper would have to
 * read chain state — the coupling FW-64's composite exists to do properly.
 *
 * @since 1.17.0
 * @return array<string,array{group:string,lead:bool}> Option name => group + lead flag.
 */
if ( ! function_exists( 'bws_option_visual_groups' ) ) {
function bws_option_visual_groups(): array {
	return array(
		// Where the value comes from: the source chain, the legacy flat siblings it
		// absorbs, and the list-mode `sep` (list length is a source property, FW-52).
		//
		// `limit` is NOT here, and its absence is a decision (1.17.0). #62 unregistered the
		// tag-level control from every chain-authoring tag; the entry was kept for one
		// release on the grounds that a flat-select family would register it (#63), and that
		// premise was withdrawn — a limit is stated on the step it bounds, so `term_` gets
		// step limits when its arm learns to compile a chain, and step limits live in the
		// fold control, not here. See docs/adr/0005-limits-are-stated-where-the-source-is-stated.md.
		// The SERIALIZATION rank in serialization-order.php is a different matter and stays:
		// stored wire still carries the key and the normalizer has to sort it.
		//
		// If a flat `limit` control ever does return it needs a row here, and what catches a
		// missing one depends entirely on WHICH family registers it (both verified by
		// mutation, 2026-08-07):
		//   - a CHAIN-authoring tag → caught immediately, but by §5 of control-order-test.php
		//     ("registers no tag-level `limit`") rather than by anything about grouping. §5
		//     sweeps by the control a tag authors its source with, so it is the direct net.
		//   - a FLAT-select family (term_*, {{table}}, {{call}}) → NOT CAUGHT AT ALL. §5 does
		//     not sweep them by construction, and §1's contiguity check cannot see it either:
		//     grouped_sequence() filters to options that HAVE a `_group`, so an ungrouped one
		//     spliced into the middle of the source run is dropped before the check and the
		//     run still reads as contiguous. Splicing an ungrouped `limit` between `srcTermIn`
		//     and `sep` on every term_ tag passes 122/122 — while breaking the box in the
		//     panel, since option-group.js joins ADJACENT siblings and an ungrouped control
		//     between two grouped ones ends the run.
		// So for the families this entry was being held open for, the map is its own only
		// safeguard. That asymmetry is a harness gap, not a property worth relying on.
		'src'             => array( 'group' => 'source', 'lead' => true ),
		'ref'             => array( 'group' => 'source', 'lead' => false ),
		'srcTermIn'       => array( 'group' => 'source', 'lead' => false ),
		'sep'             => array( 'group' => 'source', 'lead' => false ),
		// What to read off it. BOTH are leads, and `key` has to be: on `{{email}}`,
		// `{{phone}}` and `{{table}}` the field key is the tag's ENTIRE read — there is no
		// `use` enum to lead the group — so a lone-member opt-out would give exactly the
		// tags whose read is simplest no field group at all, while `{{text}}` beside them
		// has one. The opt-out exists for a control that has no group to show (a lone
		// `linkTo` reading "No Link", a `try_` template's `sep` — whose source is the
		// ungrouped attempt keys), not for a group that happens to have one member.
		'use'             => array( 'group' => 'field', 'lead' => true ),
		'key'             => array( 'group' => 'field', 'lead' => true ),
		// The datetime key family, ALL in the one field box (user, 2026-08-05). A range's
		// four keys are one decision about what the tag reads, and its optional time
		// overrides are part of that decision rather than a separate one — leaving them
		// outside gave `datetime_single` a boxed `key` with a loose `timeKey` under it,
		// which reads as two groups where there is one. Whether a range wants an inner
		// start/end split is still open (FW-65); that is a second box, not a different
		// map, so it belongs to whatever owns group boxes rather than to this list.
		'timeKey'         => array( 'group' => 'field', 'lead' => false ),
		'startKey'        => array( 'group' => 'field', 'lead' => true ),
		'startTimeKey'    => array( 'group' => 'field', 'lead' => false ),
		'endKey'          => array( 'group' => 'field', 'lead' => false ),
		'endTimeKey'      => array( 'group' => 'field', 'lead' => false ),
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
		'as'              => array( 'group' => 'format', 'lead' => true ),
		'rangeSep'        => array( 'group' => 'format', 'lead' => false ),
		'format'          => array( 'group' => 'format', 'lead' => false ),
		// `{{join}}`'s assembly pair. `mode` picks separator-vs-template and the two
		// alternatives sit behind it, exactly one revealed at a time — so the group always
		// has two visible members and needs no lead of its own. (Name-keyed, so a future
		// tag registering an unrelated `mode` would inherit this; join is the only one
		// today, and a second meaning is the signal to scope the map, not to special-case
		// a tag here.)
		'mode'            => array( 'group' => 'format', 'lead' => false ),
		'valueSep'        => array( 'group' => 'format', 'lead' => false ),
		'timeSep'         => array( 'group' => 'format', 'lead' => false ),
		'showCurrentYear' => array( 'group' => 'format', 'lead' => false ),
		'showMidnight'    => array( 'group' => 'format', 'lead' => false ),
		// The entity-link set. NO LEAD, decided by trying both (user, 2026-08-05):
		// `linkKey` and `newTab` are both revealed by `linkTo`, so the box appears exactly
		// when a link is configured, and a tag left on "No Link" keeps a bare select —
		// compact, and less prominent until the feature is actually in use. The link is
		// the one group here that is genuinely OPTIONAL: a source and a field read are
		// what every tag does, so their boxes stand whether or not they are configured.
		'linkTo'          => array( 'group' => 'link', 'lead' => false ),
		'linkKey'         => array( 'group' => 'link', 'lead' => false ),
		'newTab'          => array( 'group' => 'link', 'lead' => false ),
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
 * THE registration pass — every BWS options array goes through this before GB sees it.
 *
 * Three rules ride it, and they ride it together for one reason: this is the single pass
 * our registrations already share, and none of the three may reach GB CORE tags. The
 * group map is keyed by option NAME, and a core `post_meta` tag has a `key` and a
 * `source` too, so a JS-side equivalent would wrap controls that are not ours.
 *
 *   1. Strip default-marked selects (below).
 *   2. Stamp the visual group (bws_option_visual_groups()).
 *   3. Drop the flat source options a chain control absorbed
 *      (bws_drop_chain_flat_options()).
 *
 * RENAMED in 1.17.0, from `bws_strip_default_select_values()`. The old name described
 * rule 1 alone and had stopped describing the function; the new one names the pass, so
 * a fourth rule does not drift it again. No alias — the old spelling is gone, and
 * docs/plugin-integration.md carries the new one.
 *
 * Options array entries flagged `_strip_default => true` get their first option's
 * value flipped to '' so the wire format omits the default token (GB drops empty
 * values from the serialized tag string). Internal canonical token (e.g. 'current',
 * 'key', 'content') is preserved in source files for readability; consumers apply
 * `?? '<canonical>'` to restore it at read time.
 *
 * The `_strip_default` marker itself is removed before passing to GB.
 *
 * `_group` / `_group_lead` are inert to GB — it spreads unknown option keys through to
 * `allOptions`, which is the same route `show_if` and `fold` take.
 *
 * @since 1.7.0
 * @since 1.17.0 Stamps `_group` / `_group_lead`; renamed from bws_strip_default_select_values().
 * @param array $options Options array as registered in PHP.
 * @return array Options with strip + grouping applied.
 */
if ( ! function_exists( 'bws_prepare_registration_options' ) ) {
function bws_prepare_registration_options( array $options ): array {
	$groups  = bws_option_visual_groups();
	$options = bws_drop_chain_flat_options( $options );

	foreach ( $options as $name => &$opt ) {
		if ( ! empty( $opt['_strip_default'] ) && isset( $opt['options'][0]['value'] ) ) {
			$opt['options'][0]['value'] = '';
		}
		unset( $opt['_strip_default'] );

		if ( isset( $groups[ $name ] ) && ! isset( $opt['_group'] ) ) {
			$opt['_group'] = $groups[ $name ]['group'];
			if ( $groups[ $name ]['lead'] ) {
				$opt['_group_lead'] = true;
			}
		}
	}
	return $options;
}
}
