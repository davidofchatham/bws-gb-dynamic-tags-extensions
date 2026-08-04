<?php
/**
 * Canonical serialization-order model for BWS dynamic tags (FW-52).
 *
 * The editor JS normalizer (`assets/js/serialization-order-normalizer.js`) rebuilds
 * the whole `extraTagParams` object in a canonical order inside `setState`, so the
 * serialized tag string reads `format → source(per-slot) → link → fallback` no matter
 * what order the author touched the controls. This file is the PURE, PHP-mirrored spec
 * of that ordering algorithm — the JS normalizer is a faithful port, and the harness
 * `tools/test/serialization-order-test.php` asserts THIS function's behavior so the
 * ordering contract has a committed, CI-runnable regression net (the editor JS itself
 * has no test runner).
 *
 * WHY a PHP mirror of JS logic: FW-52's real work runs in editor React; the plugin has
 * no JS test harness and deliberately avoids a build pipeline. Mirroring the transform
 * as a pure PHP function is the highest available seam — it pins the canonical order,
 * slot-contiguity, and the format-front lift without a Node toolchain.
 *
 * CANONICAL SERIALIZATION ORDER (grill outcomes 2, 2026-07-23):
 *   format → source(per-slot, contiguous) → link → fallback
 *   - `format` LEADS (the sole deliberate departure from GB's format-last), for
 *     manual-edit copy-visibility (`as:url` up front on an image, date format up front
 *     on a datetime tag).
 *   - `link` sits AFTER `source` (source-relative: linkTo links the entity, linkKey
 *     reads a field off it — matches GB's own `source → field → link` chain).
 *   - Within a source slot: src → ref → srcTermIn → limit → sep → use → key (then the
 *     datetime field keys). `limit`/`sep` precede the field keys — list length is a
 *     property of the source, not the field read.
 *   - Multi-slot: each `N-`-prefixed slot's keys stay contiguous; slots ascend.
 *   - A FOLDED slot key (`A`, `B`, … — FW-56/57) ranks as its slot's source, so it
 *     falls in the source group at its own slot: after `format` and after any
 *     TAG-level (slot 0) source key, before `link`/`fallback`. See
 *     bws_serialization_order_sort() for why that only became expressible in 1.17.0.
 *
 * This is a TRANSFORM, not a full re-sort: it is a STABLE sort by
 * (group_rank, slot, within_group_rank), so any key NOT in the canonical map keeps its
 * incoming (insertion / registration) order at the tail of its resolved group. Only the
 * annotated movers move.
 *
 * Group / within-group ranks are hardcoded here (grill outcome 2 #3 — all-JS-hardcoded,
 * no PHP→JS localize; this PHP copy is the test mirror of that same hardcoded data).
 *
 * @package BWS_Dynamic_Tags
 * @since   1.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bws_serialization_order_groups' ) ) {

/**
 * Canonical group rank for each group name (lower serializes first).
 *
 * @since 1.16.0
 * @return array<string,int>
 */
function bws_serialization_order_groups(): array {
	return array(
		'format'   => 0,
		'source'   => 1,
		'link'     => 2,
		'fallback' => 3,
	);
}

/**
 * Canonical (group, within-group rank) for each KNOWN base option key.
 *
 * Keyed by the BARE option name (no `N-` prefix). The `N-` slot prefix is parsed
 * separately (see bws_serialization_order_parse_slot()) so a slot-2 `2-src` inherits
 * `src`'s group + within-rank but sorts into slot 2.
 *
 * A key absent from this map is UNKNOWN: it defaults to the `source` group at a
 * high within-rank (so it tails known source keys) and, being handled by the stable
 * sort, keeps its incoming order relative to other unknown keys. Defaulting unknowns
 * to `source` (not `format`/`link`) keeps the format-front and fallback-last guarantees
 * intact — a stray key never jumps ahead of `as` or after `fallback`.
 *
 * @since 1.16.0
 * @return array<string,array{0:string,1:int}> name => [group, within-rank]
 */
function bws_serialization_order_key_map(): array {
	return array(
		// --- format group (leads; global, not per-slot) ---
		'as'              => array( 'format', 0 ),
		'size'            => array( 'format', 1 ), // GB-reserved today; harmless to rank (never in extraTagParams until the as+size fold).
		// join tag-level assembly keys (share the format group): mode → valueSep →
		// format. `valueSep` (renamed from `sep`, 1.16.0/FW-52) is a slot-value
		// joiner — a format concern, distinct from the source-group list `sep`.
		'mode'            => array( 'format', 2 ),
		'valueSep'        => array( 'format', 3 ),
		'format'         => array( 'format', 4 ),
		'rangeSep'        => array( 'format', 5 ),
		'timeSep'         => array( 'format', 6 ),
		'showCurrentYear' => array( 'format', 7 ),
		'showMidnight'    => array( 'format', 8 ),

		// --- source group (per-slot; src → ref → srcTermIn → limit → sep → use → key → datetime keys) ---
		'src'          => array( 'source', 0 ),
		'ref'          => array( 'source', 1 ),
		'srcTermIn'    => array( 'source', 2 ),
		'limit'        => array( 'source', 3 ),
		'sep'          => array( 'source', 4 ),
		'use'          => array( 'source', 5 ),
		'key'          => array( 'source', 6 ),
		// datetime field keys share the source group, after `key`.
		'timeKey'      => array( 'source', 7 ),
		'startKey'     => array( 'source', 8 ),
		'startTimeKey' => array( 'source', 9 ),
		'endKey'       => array( 'source', 10 ),
		'endTimeKey'   => array( 'source', 11 ),

		// --- link group (after source; entity-link OR email/phone own-anchor) ---
		'linkTo'  => array( 'link', 0 ),
		'linkKey' => array( 'link', 1 ),
		'newTab'  => array( 'link', 2 ),
		'subject' => array( 'link', 3 ), // email own-anchor set; canonical subject → noLink.
		'noLink'  => array( 'link', 4 ),

		// --- fallback group (last) ---
		'fallback' => array( 'fallback', 0 ),

		// A FOLDED slot key (`A`, `B`, … — FW-56/57) parses to the EMPTY bare name, so
		// this is its entry. It ranks in the source group ahead of every named source
		// key, because the folded value IS the slot's whole source-and-read: `src`,
		// `use` and `key` are the axes it absorbed. The negative within-rank matters
		// only in the half-migrated shape (a folded value beside a surviving legacy
		// sibling at the same slot) — it makes that order deterministic rather than
		// insertion-dependent.
		''               => array( 'source', -1 ),
	);
}

/**
 * Split an option key into (slot, bare name).
 *
 * A key `N-name` (N ≥ 1) belongs to slot N with bare name `name`. An unprefixed key
 * is slot 0 (base / global). Only a leading `\d+-` counts as a slot prefix — the LEGACY
 * sibling prefix stays DIGITS even though the folded key does not, because that wire was
 * already written with digits and re-spelling its reader would orphan every pre-1.17.0 tag.
 *
 * A FOLDED slot key (`A`, `B`, … — FW-56/57) is decoded through bws_slot_ordinal_num():
 * the slot's whole configuration lives in that key's VALUE, so it belongs to slot N with an
 * EMPTY bare name. Decoding rather than string-comparing is what keeps slot order NUMERIC —
 * `AA` is slot 27 and must sort after `Z`, which a lexical compare gets backwards.
 *
 * DEPENDS on includes/helpers/slot-fold.php, the single owner of the spelling. A local
 * `^[A-Z]+$` here would be the second copy of a mapping that already has one owner, and it
 * is the copy nothing would notice going stale. The plugin loads that file immediately
 * after this one (call-time, so the load order is not circular); harnesses require both.
 *
 * @since 1.16.0
 * @since 1.17.0 Folded slot keys → [N, ''] (all-digit until 2026-08-04, then `A`..`Z`).
 * @param string $key Full option key (possibly `N-`-prefixed).
 * @return array{0:int,1:string} [slot, bare-name]
 */
function bws_serialization_order_parse_slot( string $key ): array {
	if ( preg_match( '/^(\d+)-(.+)$/', $key, $m ) ) {
		return array( (int) $m[1], $m[2] );
	}
	$ordinal = bws_slot_ordinal_num( $key );
	if ( $ordinal > 0 ) {
		return array( $ordinal, '' );
	}
	return array( 0, $key );
}

/**
 * Reorder a list of option keys into canonical serialization order.
 *
 * STABLE sort by (group_rank, slot, within_group_rank). Keys not in the canonical map
 * default to the `source` group at a within-rank past all known source keys, and — being
 * a stable sort — keep their incoming order among themselves.
 *
 * THIS ORDER IS ONLY EXPRESSIBLE BECAUSE FOLDED SLOT KEYS ARE CAPITALS (1.17.0). While
 * they were all digits they were JS ARRAY-INDEX properties, which ECMAScript enumerates
 * FIRST, ascending, before any string key, whatever order the object was built in — and
 * GB serializes with `Object.entries(extraTagParams)`, so every editor save emitted the
 * slots ahead of `format` and neither the JS normalizer nor this function could move
 * them. This file had to STATE that order to stay truthful, which cost `format`-first on
 * join/try_ and tag-level-first on `{{table}}`. Capitals are ordinary string keys, so
 * rank came back here and the canonical order below is a choice again. Both halves must
 * therefore agree by DESIGN, not by accident: a disagreement between the converter and
 * the editor writes the same tag two ways.
 *
 * Pure: input a key list (the `extraTagParams` insertion order), output the reordered
 * key list. No values, no WP/GB symbols. The JS normalizer runs the identical algorithm
 * on `Object.keys(extraTagParams)` then rebuilds the object in that order.
 *
 * @since 1.16.0
 * @since 1.17.0 Folded slot keys rank as their slot's source (see above).
 * @param string[] $keys Option keys in incoming (insertion / registration) order.
 * @return string[] Same keys, reordered canonically.
 */
function bws_serialization_order_sort( array $keys ): array {
	$groups  = bws_serialization_order_groups();
	$key_map = bws_serialization_order_key_map();

	// Unknown keys default to source group, ranked past every known source key.
	$unknown_within = 100;

	$decorated = array();
	foreach ( array_values( $keys ) as $idx => $key ) {
		list( $slot, $bare ) = bws_serialization_order_parse_slot( $key );

		if ( isset( $key_map[ $bare ] ) ) {
			$group  = $key_map[ $bare ][0];
			$within = $key_map[ $bare ][1];
		} else {
			$group  = 'source';
			$within = $unknown_within;
		}

		$decorated[] = array(
			'key'    => $key,
			'grank'  => $groups[ $group ],
			'slot'   => $slot,
			'within' => $within,
			'idx'    => $idx, // stability tiebreaker
		);
	}

	usort(
		$decorated,
		static function ( $a, $b ) {
			return ( $a['grank'] <=> $b['grank'] )
				?: ( $a['slot'] <=> $b['slot'] )
				?: ( $a['within'] <=> $b['within'] )
				?: ( $a['idx'] <=> $b['idx'] );
		}
	);

	return array_map(
		static function ( $d ) {
			return $d['key'];
		},
		$decorated
	);
}

} // function_exists guard.
