<?php
/**
 * FW-56/57 fold MIGRATOR — legacy flat slot keys → folded `{N}:` slot values.
 *
 * The converter path. Registered as a `type:'option'` transform_callback per multislot
 * tag (includes/tags/deprecated-tags.php), so the scanner finds stored tag strings in
 * post content and rewrites them in bulk; the editor mount path (5f) is the second,
 * complementary path for content the scanner cannot reach (block widgets).
 *
 * ONE MAPPING, NOT A SECOND ONE. The legacy→folded rules — which absences mean inherit,
 * where a legacy `limit` attaches, which shapes map to nothing — live in
 * bws_fold_from_flat() (slot-fold.php) and are shared with the render dual-read and the
 * editor. This file is only the WIRE-LEVEL adapter around it: pick the container's
 * parameters, strip the keys the mapper consumed, emit the folded values, canonicalize
 * the order.
 *
 * SPLIT BY DEPTH, not by a runtime branch. A base tag's chain rides at depth 0
 * (`src:refs,office`, `limit(2)`) and a slot's at L1 (`2:src(refs,office)`, `limit[2]`),
 * and MigrationRegistry matches `match_tag` by exact string — so container-ness is known
 * at REGISTRATION time. Both halves live here: bws_fold_migrate_slots() rewrites a
 * container's per-slot keys, bws_fold_migrate_base_src() rewrites a base tag's own
 * source. The depth-0 half was held back until the chain→steps compiler existed —
 * before it, both wire→steps assemblers read the flat keys and stopped at one
 * relationship step plus one term step, so a migrated `src:refs,office` would have
 * parsed as an unknown source token and the rewrite would have written wire the
 * renderer could not resolve.
 *
 * Pure enough to harness: no WP or GB symbols (i18n lives with the registration, not
 * here). tools/test/fold-migration-test.php loads this file, not a copy of it.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Container parameters for a multislot tag's fold migration.
 *
 * DERIVED, never hand-listed: the try_ shapes come from the same template descriptors
 * generate_base_try_tags() registers from, so a template whose read shape changes cannot
 * leave a stale copy here. Returns null for anything that is not a folded multislot
 * container — the callback then no-ops, which is also the transform_callback contract.
 *
 * @since 1.17.0
 * @param string $tag_name Live tag name.
 * @return array{container:string,combining:bool,per_slot_use:bool,max:int,tag_level:string[]}|null
 */
function bws_fold_migration_container( string $tag_name ): ?array {
	if ( 'join' === $tag_name ) {
		// Read from the OWNER, not re-listed here: bws_get_join_options() registers from
		// the same array, and two hand-kept copies of `max`/`tag_level` disagree silently
		// (see bws_join_fold_container(), which also records why `tag_level` is empty).
		return function_exists( 'bws_join_fold_container' ) ? bws_join_fold_container() : null;
	}

	if ( 0 !== strpos( $tag_name, 'try_' ) || ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
		return null;
	}

	foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
		if ( empty( $tpl['supports_try'] ) || 'try_' . ( $tpl['key'] ?? '' ) !== $tag_name ) {
			continue;
		}
		return array(
			'container'    => 'try',
			'combining'    => false,
			'per_slot_use' => ! empty( $tpl['try_per_slot_use'] ),
			// Five slots, as generate_base_try_tags() registers.
			'max'          => 5,
			'tag_level'    => \BWS\DynamicTags\TagTemplateRegistry::try_slot_axes( $tpl )['tag_level'],
		);
	}

	return null;
}

/**
 * Every multislot tag that has legacy flat slot wire to migrate.
 *
 * try_ list DERIVED from the template registry (the same source the tags are generated
 * from). `{{table}}` is deliberately absent and always will be: it ships folded, so no
 * stored table tag ever carried flat slot keys.
 *
 * @since 1.17.0
 * @return string[]
 */
function bws_fold_migration_multislot_tags(): array {
	$tags = array( 'join' );

	if ( class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
		foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
			if ( ! empty( $tpl['supports_try'] ) && ! empty( $tpl['key'] ) ) {
				$tags[] = 'try_' . $tpl['key'];
			}
		}
	}

	// A duplicate template key (register_modifier_template is public API) would otherwise
	// register two identical entries; generate_base_try_tags() dedupes its tags the same way.
	return array_values( array_unique( $tags ) );
}

/**
 * Every legacy option key a container owns PER SLOT — the fold's input surface.
 *
 * The full legacy axis set at every slot position, minus the container's `tag_level`
 * axes at EVERY position (see TagTemplateRegistry::try_slot_axes() for why the exclusion
 * is not slot-1-only). Doubles as the migration entry's `match_any_options`: a
 * `try_datetime_single` whose only source-group key is a tag-level `key` then has nothing
 * to fold and does not match, instead of matching forever and no-opping.
 *
 * @since 1.17.0
 * @param array $cfg Container config from bws_fold_migration_container().
 * @return string[] Option keys, slot order.
 */
function bws_fold_migration_slot_keys( array $cfg ): array {
	$axes = bws_fold_slot_flat_axes( (array) ( $cfg['tag_level'] ?? array() ) );
	$keys = array();
	for ( $n = 1; $n <= (int) $cfg['max']; $n++ ) {
		$prefix = ( 1 === $n ) ? '' : "{$n}-";
		foreach ( $axes as $axis ) {
			$keys[] = $prefix . $axis;
		}
	}
	return $keys;
}

/**
 * What the fold entry MATCHES on — the per-slot surface, plus what else it now rewrites.
 *
 * Separate from bws_fold_migration_slot_keys() because the two answer different
 * questions, and conflating them re-creates the bug the filter exists to stop: that
 * function names the keys the MAPPER may see, and a tag-level `limit` handed to the
 * mapper is folded into slot 1 and deleted (TagTemplateRegistry::try_slot_axes). This
 * one names the keys whose PRESENCE means there is work to do.
 *
 * They diverge for exactly one key. A SELECTING container's tag-level `limit` is
 * retired by this entry (#61), so a tag whose slots are ALREADY FOLDED and whose only
 * remaining legacy artefact is that key must still match — otherwise the one shape the
 * ticket names goes unmigrated. The entry no-ops when there is no slot to push the
 * number into, which apply_option_migration() tolerates by design.
 *
 * @since 1.17.0
 * @param array $cfg Container config from bws_fold_migration_container().
 * @return string[]
 */
function bws_fold_migration_match_keys( array $cfg ): array {
	$keys = bws_fold_migration_slot_keys( $cfg );
	if ( empty( $cfg['combining'] ) ) {
		$keys[] = 'limit';
	}
	return array_values( array_unique( $keys ) );
}

/**
 * Fold a multislot tag's LEGACY flat slot keys into folded `{N}` slot values.
 *
 * PURE — options in, options out. The caller owns parse/serialize, which is what lets the
 * harness drive this with arrays and lets the mount migrator reuse the rules through the
 * JS twin.
 *
 * Three rules a reader would otherwise re-derive:
 *   - A TAG-LEVEL key is invisible here, at EVERY slot position. It is removed before the
 *     mapper sees the options, so it is neither read into a slot value nor stripped from
 *     the output.
 *   - AN ALREADY-FOLDED SLOT WINS. Legacy keys beside a folded value are a half-applied
 *     migration or a hand-edit; the folded value is the author's later intent (the render
 *     dual-read resolves it the same way), so the legacy siblings are dropped, never
 *     merged. Merging is what would silently invent a configuration neither side wrote.
 *   - A SLOT THAT MAPS TO NOTHING still has its legacy keys stripped. That is the FW-51
 *     shape (a selecting slot ≥2 whose only content was a `key`): the shipped resolver
 *     discards it before the carry-forward, so it renders nothing today and dropping it
 *     is the output-preserving branch.
 *   - A SLOT NAMING A RETIRED SOURCE TOKEN IS DECLINED WHOLE — not folded, and NOT
 *     stripped, which is the one case where those two come apart. See
 *     BWS_FOLD_RETIRED_SRC_TOKENS.
 *
 * Emitted keys are canonically ordered (bws_serialization_order_sort) so the converter's
 * output matches what the editor would write on next save — otherwise every migrated tag
 * would show a spurious diff the first time it is opened.
 *
 * @since 1.17.0
 * @param array $options All tag options (GB-parsed).
 * @param array $cfg     Container config from bws_fold_migration_container().
 * @return array|null Rewritten options, or null when there is nothing to migrate.
 */
function bws_fold_migrate_slots( array $options, array $cfg ) {
	// The mapper must never see a key the container does not own per slot — at any
	// position, so a dead `3-key` on a chain-only template is left dead.
	$slot_keys = bws_fold_migration_slot_keys( $cfg );
	$slot_src  = array_intersect_key( $options, array_flip( $slot_keys ) );

	// …with ONE exception, and it is a read rather than a fold: a SELECTING container's
	// tag-level `limit` is each attempt's own default (try_slot_axes), so the mapper has
	// to see it or the materialized flat-era default writes a `1` over the author's
	// number. Added to the mapper's VIEW only — never to $present — so the tag-level key
	// is neither stripped by the per-slot loop nor counted as something to migrate; it is
	// retired below, once, as a tag-level decision.
	// The gate is exactly bws_fold_from_flat()'s own — SELECTING container, and never where
	// the slot already owns the key — so the two cannot disagree about which map the mapper
	// is reading. A derived "is `limit` a per-slot axis here" test was tried and dropped: it
	// is a SECOND spelling of one rule, and the reader that matters (from_flat, on the
	// render path) has no access to the axis list anyway.
	$slot_view = $slot_src;
	if ( empty( $cfg['combining'] )
		&& ! array_key_exists( 'limit', $slot_view )
		&& '' !== trim( (string) ( $options['limit'] ?? '' ) ) ) {
		$slot_view['limit'] = $options['limit'];
	}

	// #61 — THE SELECTING CONTAINER'S TAG-LEVEL `limit` STOPS EXISTING. It was never a
	// bound across attempts; it was each attempt's own default, and once an attempt's
	// source is a chain nothing says which step such a number aims at. So it is pushed
	// into the slots that consumed it and the key goes.
	//
	// A LEGACY slot takes it through $slot_view above. This is the other half: a slot that
	// is ALREADY FOLDED — reachable by hand-edit (ADR 0004) and from every tag the pre-#61
	// rule migrated — takes it onto its own chain, through the same three owners the rest
	// of the fold uses, so there is no second mapping to drift.
	//
	// NUMERIC ONLY. An uninterpretable value is not a number to push anywhere, and
	// deleting an author's text on the strength of bws_clamp_limit's is_numeric guard is a
	// bigger move than this rewrite is entitled to (the depth-0 half declines it for the
	// same reason). A slot that fans only by INHERITING takes nothing here and needs
	// nothing: bws_fold_slot_chain_options() carries the bound with the source it inherits.
	$tag_limit = null;
	if ( empty( $cfg['combining'] ) ) {
		$raw = trim( (string) ( $options['limit'] ?? '' ) );
		if ( '' !== $raw && is_numeric( $raw ) ) {
			$tag_limit = $raw;
		}
	}

	$folded   = $options;
	$touched  = false;
	$has_slot = false;

	for ( $n = 1; $n <= (int) $cfg['max']; $n++ ) {
		$prefix     = ( 1 === $n ) ? '' : "{$n}-";
		$folded_key = bws_slot_ordinal( $n );
		$folded_val = trim( (string) ( $options[ $folded_key ] ?? '' ) );
		$present    = array();
		foreach ( BWS_FOLD_FLAT_AXES as $axis ) {
			if ( array_key_exists( $prefix . $axis, $slot_src ) ) {
				$present[] = $prefix . $axis;
			}
		}
		// A slot naming a RETIRED source token does NOT count: it is declined whole below,
		// so the number has nowhere to land in it, and consuming the tag-level key on its
		// account would leave the tag half-treated — the legacy keys still there for the
		// converter to fix later, but the bound they need already deleted. That is the one
		// way this rewrite can move output on a tag it deliberately did not touch.
		$declined = '' === $folded_val
			&& ! empty( $present )
			&& in_array( trim( (string) ( $slot_src[ $prefix . 'src' ] ?? '' ) ), BWS_FOLD_RETIRED_SRC_TOKENS, true );
		if ( ! $declined && ( '' !== $folded_val || ! empty( $present ) ) ) {
			$has_slot = true;
		}

		// An already-folded slot: the retiring number lands on its own last fanning step,
		// by the same positional rule everything else uses. A slot that pins its own limit
		// is left alone — the tag-level number was a DEFAULT, and a default never
		// overwrites a stated value (bws_fold_chain_apply_legacy_limit decides both).
		if ( '' !== $folded_val && null !== $tag_limit ) {
			$parsed = bws_fold_parse_slot( $folded_val, $cfg['container'] ?? 'try' );
			if ( is_array( $parsed ) ) {
				$applied = bws_fold_chain_apply_legacy_limit( (array) ( $parsed['chain'] ?? array() ), $tag_limit );
				if ( $applied['consumed'] ) {
					$parsed['chain']       = $applied['chain'];
					$folded[ $folded_key ] = bws_fold_emit_slot( $parsed );
				}
			}
		}

		if ( empty( $present ) ) {
			continue;
		}

		// A slot naming a RETIRED source token is declined whole — not folded, and its
		// legacy keys not stripped. See BWS_FOLD_RETIRED_SRC_TOKENS for why the fold is
		// structurally the wrong layer to rewrite one, and why leaving the tag untouched
		// is the only answer that cannot store it differently from the converter.
		if ( $declined ) {
			continue;
		}

		$touched = true;
		foreach ( $present as $legacy_key ) {
			unset( $folded[ $legacy_key ] );
		}

		// An already-folded value for this slot wins outright.
		if ( '' !== $folded_val ) {
			continue;
		}

		$rec = bws_fold_from_flat( $n, $slot_view, ! empty( $cfg['combining'] ), ! empty( $cfg['per_slot_use'] ) );
		if ( null === $rec ) {
			continue;
		}
		$wire = bws_fold_emit_slot( $rec['slot'] );
		if ( '' !== $wire ) {
			$folded[ $folded_key ] = $wire;
		}
	}

	// The key goes only where there was a slot to push it into. A tag with no slot at all
	// has nowhere for the number to land, so the rewrite declines rather than emitting a
	// diff that changes nothing an author can see.
	if ( null !== $tag_limit && $has_slot ) {
		unset( $folded['limit'] );
		$touched = true;
	}

	if ( ! $touched ) {
		return null;
	}

	return bws_serialization_order_sort_map( $folded );
}

/**
 * Rewrite a BASE tag's flat source triple into depth-0 CHAIN wire.
 *
 * PURE — options in, options out; null when there is nothing to migrate. The depth-0
 * half of the fold: `bws_fold_migrate_slots()` rewrites a container's per-slot keys,
 * this rewrites the tag's own source. Same posture, different depth.
 *
 * @invariant BOTH MIGRATION PATHS WRITE BYTE-IDENTICAL OUTPUT. A divergence does not
 * surface as one path being wrong — it surfaces as one tag stored two ways depending
 * on which path found it first. The scanner reads `post_content` only, so a block
 * widget is reachable ONLY on tag-modal mount, while a draft nobody opens is
 * reachable ONLY by the scanner; the two are complementary, not redundant. The JS
 * twin is `baseSrcState()` in assets/js/slot-fold-migrate.js and the shared corpus
 * proves they agree, key ORDER included.
 *
 * Four rules, each of which changes what gets stored:
 *
 * - **ONLY A FANNING SOURCE IS REWRITTEN.** `src:current`, `src:site` and a bare tag
 *   are already the chain their token states, so respelling them is churn with no
 *   reader benefit — and a `limit` beside a source that resolves one entity is noise
 *   a reader has to decide is meaningless.
 * - **A SITE ROOT IS LEFT ALONE**, even beside a hand-edited `srcTermIn`. Every arm
 *   has always let the site read win and the compiler agrees, so the honest rewrite
 *   would have to DROP the taxonomy key — which changes what the stored tag says
 *   about itself, on a shape the editor never produced.
 * - **A RETIRED SOURCE TOKEN IS DECLINED WHOLE**, exactly as a slot is: not
 *   rewritten, and its keys not stripped, which leaves the token's own migration
 *   entry able to fix it afterwards (#56).
 * - **THE LIMIT IS CARRIED ONTO THE STEPS**, never written as a tag-level `limit`, and
 *   AT BOTH DEPTHS. The slot half was once exempt, on the reasoning that
 *   the retired `bws_fold_slot_flat_options()` collapsed a slot's chain back to a flat triple before
 *   any container arm resolves a limit, so `bws_limit_default()` saw flat wire on a
 *   folded slot exactly as on a legacy one and answered 1 either way. That is no longer
 *   true: the seam hands its ERA back and a slot's own spelling decides its own default
 *   (#60), so `bws_fold_from_flat()` materializes the same way this does.
 *   Migration changes the SPELLING, the spelling selects the default
 *   (`bws_limit_default`), so migration must carry the default it is leaving behind.
 *   Writing nothing would silently fan out exactly the tags it touched — extra
 *   values, dropped anchors (the link gate is count-based), on live pages, with no
 *   author present to warn. That is what keeps this a pure rewrite with no output
 *   delta, which is the equivalence the harness asserts. The mapping itself —
 *   including why every earlier fanning step is bounded too — belongs to
 *   `bws_fold_chain_apply_legacy_limit()`, shared with the N×M chain migrators and the
 *   author-conversion commit so that three surfaces cannot store one tag three ways.
 *
 * @since 1.17.0
 * @param array $options All tag options (GB-parsed).
 * @return array|null Rewritten options, or null when there is nothing to migrate.
 */
function bws_fold_migrate_base_src( array $options ) {
	$src   = trim( (string) ( $options['src'] ?? $options['source'] ?? '' ) );
	$tax   = trim( (string) ( $options['srcTermIn'] ?? '' ) );
	$chain = null;

	// ALREADY CHAIN WIRE. There is no spelling to respell, but there may still be a
	// TAG-LEVEL LIMIT, and a tag-level limit is legacy by POSITION rather than by
	// spelling: it is the one shape where a bound is INVISIBLE, since the step's own
	// Limit field reads unlimited and #62 left no control that can reach the key. So the
	// number is absorbed onto the step it bounds here too.
	//
	// NUMERIC ONLY, which is the whole difference from the flat branch below. That branch
	// materializes the flat ERA's default (1) when the key is absent or unreadable,
	// because the spelling it is leaving behind meant 1. Chain wire is not changing era,
	// so there is no default to carry — materializing one would bound a tag that renders
	// unlimited today. Everything else about the mapping is shared, so a tag absorbed here
	// and the same tag converted from flat wire land byte-identically.
	if ( function_exists( 'bws_fold_chain_is_wire' ) && bws_fold_chain_is_wire( $src ) ) {
		$raw_limit = trim( (string) ( $options['limit'] ?? '' ) );
		if ( '' === $raw_limit || ! is_numeric( $raw_limit ) ) {
			return null;
		}
		$parsed = bws_fold_parse_chain( $src );
		if ( ! is_array( $parsed ) || isset( $parsed['error'] ) || ! $parsed ) {
			return null;
		}
		$chain = $parsed;
	} else {
		if ( in_array( $src, BWS_FOLD_RETIRED_SRC_TOKENS, true ) ) {
			return null;
		}
		if ( 'site' === $src ) {
			return null;
		}
		// Fanning iff the flat triple states a relationship step or a term step.
		if ( 'ref' !== $src && '' === $tax ) {
			return null;
		}
		$chain = bws_fold_chain_from_options( $options );
	}

	// Depth 0, so an explicit `limit:0`/`-1` is CONSUMED rather than left: chain wire
	// already means unlimited, and since #62 no control can reach the key it would leave.
	$bound  = bws_fold_chain_apply_legacy_limit( $chain, $options['limit'] ?? null, true );
	$wire   = bws_fold_emit_chain( $bound['chain'], 0 );
	if ( '' === $wire || ! bws_fold_chain_is_wire( $wire ) ) {
		return null;
	}

	// On the absorb branch the KEY is the entire point, so a mapping that stood down —
	// the chain states its own step limits, or it does not fan — is no rewrite at all.
	// Returning the map unchanged would re-serialize a tag nobody can improve every time
	// it is opened, and a no-op diff is what the mount path's loop guard exists to avoid.
	if ( ! $bound['consumed'] && $src === $wire ) {
		return null;
	}

	$out = $options;
	unset( $out['source'] );
	$out['src'] = $wire;
	unset( $out['ref'], $out['srcTermIn'] );

	if ( $bound['consumed'] ) {
		unset( $out['limit'] );
	}

	return bws_serialization_order_sort_map( $out );
}

/**
 * MigrationRegistry transform_callback: rewrite one BASE tag's source to chain wire.
 *
 * @since 1.17.0
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string, or the original.
 */
function bws_migrate_base_src_chain( string $tag_string ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	if ( ! class_exists( $reg ) || ! function_exists( 'bws_fold_chain_from_options' ) ) {
		return $tag_string;
	}

	list( $tag_name, $options ) = $reg::parse_tag_string( $tag_string );

	$migrated = bws_fold_migrate_base_src( $options );
	if ( null === $migrated ) {
		return $tag_string;
	}

	return $reg::format_tag_string( $tag_name, $migrated );
}

/**
 * The BASE tags whose source is authored as a chain, and therefore migrated to one.
 *
 * Deliberately not derived from "every registered tag": the migration target is the
 * AUTHORING surface, so this list must track `bws_build_src_chain_option()`'s callers
 * and nothing else. A `term_*` modifier or a `try_` slot keeps the flat select and
 * would be migrated to wire its own control cannot edit.
 *
 * @since 1.17.0
 * @return string[]
 */
function bws_fold_migration_base_tags(): array {
	return array(
		'text', 'content', 'title', 'permalink', 'image',
		'email', 'phone', 'datetime_single', 'datetime_range',
	);
}

/**
 * MigrationRegistry transform_callback: fold one multislot tag string.
 *
 * Returns the input unchanged when there is nothing to do — the no-op contract
 * apply_option_migration() relies on (and the reason that method had to stop treating a
 * no-op as the end of the cascade: this entry matches on `src`/`key`, which nearly every
 * tag has).
 *
 * @since 1.17.0
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string, or the original.
 */
function bws_migrate_src_chain_slots( string $tag_string ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	if ( ! class_exists( $reg ) || ! function_exists( 'bws_fold_from_flat' ) ) {
		return $tag_string;
	}

	list( $tag_name, $options ) = $reg::parse_tag_string( $tag_string );

	$cfg = bws_fold_migration_container( $tag_name );
	if ( null === $cfg ) {
		return $tag_string;
	}

	$migrated = bws_fold_migrate_slots( $options, $cfg );
	if ( null === $migrated ) {
		return $tag_string;
	}

	return $reg::format_tag_string( $tag_name, $migrated );
}
