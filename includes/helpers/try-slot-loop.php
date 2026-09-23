<?php
/**
 * The `try_` ATTEMPT LOOP — the selecting fold, lifted out of the closure.
 *
 * A `try_` tag walks its attempts in order and takes the FIRST one that reads
 * something. That walk is what this file owns; how one attempt reads is
 * somebody else's job, handed in as a callable. The split is FW-136's whole
 * point: until 1.21.0 the walk and the per-attempt read were one ~350-line
 * anonymous closure in TagTemplateRegistry::generate_base_try_tags(), so every
 * rule a base tag's read gained had to be re-inherited by a second renderer, and
 * every base/`try_` parity defect is one that was not (FW-135 is the live
 * instance; the FW-136 row in docs/future-work.md tracks the merge).
 *
 * THE RESOLVE SEAM IS THE PARAMETER, not a name this file switches on — FW-107's
 * stated fix shape. That is what makes the walk callable with no WordPress at all
 * and therefore harnessable: tools/test/try-slot-loop-test.php drives it with a
 * fake resolver and reads the walk's decisions off the call list.
 *
 * WHAT THE LOOP OWNS (and the resolver must not re-decide):
 *   - attempt cardinality (BWS_TRY_MAX_SLOTS) and wire era per slot;
 *   - the ONE carry-forward accumulator threaded through the shared fold seam
 *     (bws_fold_slot_chain_options owns the `same` rules for every container);
 *   - the per-slot read gate (no field key and not in a no-key `use` mode = skip
 *     before the resolver is called at all);
 *   - the limit this attempt resolves with, written back explicitly;
 *   - FIRST NON-EMPTY WINS, where "empty" is exactly `''`.
 *
 * WHAT THE LOOP DOES NOT OWN: link-wrap, the preview label, the stated fallback
 * and the media-block guard all stay in the tag's SHELL, exactly as they do for a
 * base tag (bws_base_text_callback is the shape). The loop hands the shell the
 * winning attempt's triple and says nothing about what to do with an all-empty
 * walk beyond returning null.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.21.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BWS_TRY_MAX_SLOTS' ) ) {
	/**
	 * Fixed attempt maximum, matching what bws_build_fold_slot_options() offers the
	 * editor (`'max' => 5` on the try_ container). Raising it is two edits, not one:
	 * the registration offer and this walk.
	 */
	define( 'BWS_TRY_MAX_SLOTS', 5 );
}

/**
 * Walk a `try_` tag's attempts and return the first one that reads something.
 *
 * @since 1.21.0
 *
 * @param array    $options  Tag options as GB hands them over (fallback keys included —
 *                           this function strips them before they reach an attempt, so a
 *                           per-item core can never emit the tag's fallback mid-list).
 * @param mixed    $instance GB tag instance, passed through to the resolver untouched.
 * @param array    $cfg      The family's facts, all off its template descriptor:
 *                           per_slot_key, per_slot_use, no_key_uses, default_use,
 *                           collapse (takes_first_usable).
 * @param callable $resolve  fn( array $slot_opts, $instance ): array{
 *                               value:string, link_id:int, link_type:string }
 *                           The family's resolve seam — its base tag's own, unchanged.
 * @return array{value:string, link_id:int, link_type:string}|null Null = every attempt
 *                           read nothing; the shell then runs the fallback or the label.
 */
function bws_try_run_attempts( array $options, $instance, array $cfg, callable $resolve ): ?array {
	$per_slot_key = ! empty( $cfg['per_slot_key'] );
	$per_slot_use = ! empty( $cfg['per_slot_use'] );
	$no_key_uses  = $cfg['no_key_uses'] ?? array();
	$collapse     = ! empty( $cfg['collapse'] );

	// The fallback is the SHELL's, fired once on an all-empty walk. An attempt whose
	// core can emit a stated fallback of its own would otherwise stop the walk with
	// the fallback text (GH #51's shape, one layer up) — so it never sees the key.
	$eval_opts = array_diff_key( $options, array( 'fallback' => null, 'fallback_text' => null ) );

	// ONE carry-forward accumulator for the whole chain, threaded through the fold seam
	// (bws_fold_slot_chain_options), which owns the `same` rules for every container.
	// Seeded with what slot 1's ABSENT axes mean: an EMPTY CHAIN is the ambient entity,
	// and the read seeds the template's stripped first `use` value, so an unset slot-1
	// read carries over the same token a bare base tag resolves with — and so does a
	// later `use(same)` reaching back past a slot that never set one.
	//
	// Seeded UNCONDITIONALLY, not only under per_slot_use: a template with no `use` enum
	// derives '' anyway, and the seam writes no read default of its own.
	$carry = bws_fold_empty_carry( (string) ( $cfg['default_use'] ?? '' ) );

	for ( $n = 1; $n <= BWS_TRY_MAX_SLOTS; $n++ ) {
		// Era per SLOT, not per tag: a folded value parses, an absent one is recovered
		// from this slot's legacy keys, and both feed one accumulator (so a
		// half-migrated tag resolves as its author last saw it).
		$slot = bws_fold_slot_struct( $n, $options, 'try', $per_slot_use );
		if ( null === $slot ) {
			continue;   // nothing in either era, or the seam's own skip.
		}

		$skip_reason   = '';
		$limit_default = 1;
		$slot_read     = bws_fold_slot_chain_options( $slot, $carry, false, $skip_reason, $limit_default );
		if ( null === $slot_read ) {
			continue;   // unconfigured, nothing to carry over, or an unfinished step.
		}

		$last_key = $slot_read['key'];
		$last_use = $slot_read['use'];

		// The option set this attempt resolves with. The SOURCE arrives as depth-0 CHAIN
		// WIRE in `src` — the key and the language a base tag states its source in
		// (CONTEXT.md I16) — and the seam supersedes the legacy axes by returning explicit
		// empties for them. Merging that over $eval_opts is what closes the tag-level leak:
		// $eval_opts still carries any bare legacy `srcTermIn` off a half-migrated tag, and
		// bws_fold_chain_from_options() APPENDS a term step for whatever it finds there.
		$slot_opts              = $eval_opts;
		$slot_opts['src']       = $slot_read['src'];
		$slot_opts['ref']       = $slot_read['ref'];
		$slot_opts['srcTermIn'] = $slot_read['srcTermIn'];

		if ( $per_slot_key || $per_slot_use ) {
			$in_no_key_mode = $per_slot_use && in_array( $last_use, $no_key_uses, true );
			if ( ! $in_no_key_mode && '' === $last_key ) {
				continue; // No field key and not in no-key mode — skip the attempt.
			}
			if ( '' !== $last_key ) {
				$slot_opts['key'] = $last_key;
			}
		}

		if ( $per_slot_use ) {
			$slot_opts['use'] = $last_use;
		}

		// THE DEFAULT IS THE SLOT'S OWN, and only the seam can say what it is:
		// $slot_opts['src'] is CHAIN WIRE on every slot now, including one recovered from
		// legacy flat keys, so bws_limit_default() read off it answers UNLIMITED whatever
		// the slot was spelled as.
		//
		// THE TAG-LEVEL `limit` IS RETIRED (#61) AND STILL READ. Migration pushes an
		// author's number into the slots that consumed it and deletes the key, so on
		// migrated wire this fallback resolves to nothing. It stays because the value
		// outlives the key: neither migration path reaches a tag stored in ACF meta, and
		// ADR 0004 makes hand-edited wire mean what it says.
		//
		// Written BACK into $slot_opts, not left implicit: the resolver resolves its own
		// limit through the same flat-blind bws_limit_default(), so an absent key there
		// would re-introduce the 1 this line just decided against.
		$slot_max = bws_clamp_limit( $slot_read['limit'] ?? $options['limit'] ?? null, $limit_default );

		// A collapsing template's attempt wants ONE result, whatever any limit says —
		// slot-stated, tag-level or carried alike (ADR 0007, same rule as its base tag).
		// The stored wire keeps its number.
		if ( $collapse ) {
			$slot_max = 1;
		}
		$slot_opts['limit'] = (string) $slot_max;

		$resolved = $resolve( $slot_opts, $instance );

		// THE ONLY PREDICATE. '' means "this attempt read nothing" — a stored '0' is a
		// real value and STOPS the walk (hooks.php maps it downstream; no emptiness is
		// re-decided here, same contract as the absorb seam's).
		if ( '' === (string) ( $resolved['value'] ?? '' ) ) {
			continue;
		}

		return array(
			'value'     => (string) $resolved['value'],
			'link_id'   => (int) ( $resolved['link_id'] ?? 0 ),
			'link_type' => (string) ( $resolved['link_type'] ?? 'post' ),
		);
	}

	return null;
}
