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
 * bws_fold_from_legacy() (slot-fold.php) and are shared with the render dual-read and the
 * editor. This file is only the WIRE-LEVEL adapter around it: pick the container's
 * parameters, strip the keys the mapper consumed, emit the folded values, canonicalize
 * the order.
 *
 * SPLIT BY DEPTH, not by a runtime branch. A base tag's chain rides at depth 0
 * (`src:refs,office`, `limit(2)`) and a slot's at L1 (`2:src(refs,office)`, `limit[2]`),
 * and MigrationRegistry matches `match_tag` by exact string — so container-ness is known
 * at REGISTRATION time. There is deliberately no base-tag callback here yet: nothing
 * COMPILES a chain into traversal steps. Both wire→steps assemblers read the flat keys
 * and cap out at one ref hop plus one term hop (bws_field_values_assemble_steps in
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
		return array(
			'container'    => 'join',
			'combining'    => true,
			'per_slot_use' => true,
			'max'          => defined( 'BWS_JOIN_MAX_SLOTS' ) ? BWS_JOIN_MAX_SLOTS : 10,
			// Nothing excluded: join's tag-level options (mode/valueSep/format/fallback)
			// share no name with a legacy slot axis, and `limit` IS a join slot axis — it
			// registered one per slot (`limit`/`N-limit`) and threaded it into that slot's
			// text resolve. That is the opposite of try_, where a bare `limit` is the
			// tag-level cap for every slot, and it is why `tag_level` is per-container data
			// rather than one shared list.
			'tag_level'    => array(),
		);
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
	$axes = bws_fold_slot_legacy_axes( (array) ( $cfg['tag_level'] ?? array() ) );
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
		foreach ( BWS_FOLD_LEGACY_AXES as $axis ) {
			if ( array_key_exists( $prefix . $axis, $slot_src ) ) {
				$present[] = $prefix . $axis;
			}
		}
		if ( empty( $present ) ) {
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

		$rec = bws_fold_from_legacy( $n, $slot_src, ! empty( $cfg['combining'] ), ! empty( $cfg['per_slot_use'] ) );
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

	$ordered = array();
	foreach ( bws_serialization_order_sort( array_map( 'strval', array_keys( $folded ) ) ) as $key ) {
		$ordered[ $key ] = $folded[ $key ];
	}
	return $ordered;
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
	if ( ! class_exists( $reg ) || ! function_exists( 'bws_fold_from_legacy' ) ) {
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
