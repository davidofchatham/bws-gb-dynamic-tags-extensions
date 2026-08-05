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
 * at REGISTRATION time. There is deliberately no base-tag callback here yet: nothing
 * COMPILES a chain into traversal steps. Both wire→steps assemblers read the flat keys
 * and cap out at one relationship step plus one term step (bws_field_values_assemble_steps in
 * field-helpers.php, bws_wrapper_ref_steps in base-shared.php), so a depth-0
 * `src:refs,office` would parse as an unknown source token. Migrating a base tag would
 * write wire the renderer cannot resolve. It lands with the chain→steps compiler.
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

	$folded  = $options;
	$touched = false;

	for ( $n = 1; $n <= (int) $cfg['max']; $n++ ) {
		$prefix  = ( 1 === $n ) ? '' : "{$n}-";
		$present = array();
		foreach ( BWS_FOLD_FLAT_AXES as $axis ) {
			if ( array_key_exists( $prefix . $axis, $slot_src ) ) {
				$present[] = $prefix . $axis;
			}
		}
		if ( empty( $present ) ) {
			continue;
		}

		// A slot naming a RETIRED source token is declined whole — not folded, and its
		// legacy keys not stripped. See BWS_FOLD_RETIRED_SRC_TOKENS for why the fold is
		// structurally the wrong layer to rewrite one, and why leaving the tag untouched
		// is the only answer that cannot store it differently from the converter.
		if ( in_array( trim( (string) ( $slot_src[ $prefix . 'src' ] ?? '' ) ), BWS_FOLD_RETIRED_SRC_TOKENS, true ) ) {
			continue;
		}

		$touched = true;
		foreach ( $present as $legacy_key ) {
			unset( $folded[ $legacy_key ] );
		}

		// An already-folded value for this slot wins outright.
		if ( '' !== trim( (string) ( $options[ bws_slot_ordinal( $n ) ] ?? '' ) ) ) {
			continue;
		}

		$rec = bws_fold_from_flat( $n, $slot_src, ! empty( $cfg['combining'] ), ! empty( $cfg['per_slot_use'] ) );
		if ( null === $rec ) {
			continue;
		}
		$wire = bws_fold_emit_slot( $rec['slot'] );
		if ( '' !== $wire ) {
			$folded[ bws_slot_ordinal( $n ) ] = $wire;
		}
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
 * - **`limit:1` IS SERIALIZED** when the source fans and the author stated none —
 *   and DEPTH-0 ONLY. A folded SLOT needs nothing, which is worth stating because the
 *   symmetry invites the opposite conclusion: `bws_fold_slot_flat_options()` collapses
 *   a slot's chain back to a flat `src`/`ref`/`srcTermIn` triple before any container
 *   arm resolves a limit, so `bws_limit_default()` sees flat wire on a folded slot
 *   exactly as on a legacy one and answers 1 either way. Folding a slot cannot change
 *   its cap; respelling a base tag's source can, because nothing re-flattens it.
 *   Migration changes the SPELLING, the spelling selects the tag-level default
 *   (`bws_limit_default`), so migration must write the default it is leaving behind.
 *   Writing nothing would silently fan out exactly the tags it touched — extra
 *   values, dropped anchors (the link gate is count-based), on live pages, with no
 *   author present to warn. That is what keeps this a pure rewrite with no output
 *   delta, which is the equivalence the harness asserts.
 *
 * @since 1.17.0
 * @param array $options All tag options (GB-parsed).
 * @return array|null Rewritten options, or null when there is nothing to migrate.
 */
function bws_fold_migrate_base_src( array $options ) {
	$src = trim( (string) ( $options['src'] ?? $options['source'] ?? '' ) );
	$tax = trim( (string) ( $options['srcTermIn'] ?? '' ) );

	// Already chain wire — nothing to respell.
	if ( function_exists( 'bws_fold_chain_is_wire' ) && bws_fold_chain_is_wire( $src ) ) {
		return null;
	}
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
	$wire  = bws_fold_emit_chain( $chain, 0 );
	if ( '' === $wire || ! bws_fold_chain_is_wire( $wire ) ) {
		return null;
	}

	$out = $options;
	unset( $out['source'] );
	$out['src'] = $wire;
	unset( $out['ref'], $out['srcTermIn'] );

	if ( ! isset( $out['limit'] ) || '' === trim( (string) $out['limit'] ) ) {
		$out['limit'] = '1';
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
