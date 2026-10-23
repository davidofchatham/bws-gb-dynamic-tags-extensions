<?php
/**
 * The source gate: whether a resolved source may feed reads and consume limit budget.
 *
 * ITS OWN FILE SO IT CAN GO UNGUARDED. Every pure harness that loads the traversal engine
 * stubs this function by defining it first, which only works while the real one is not in
 * a file they require. The engine binds it by name at call time (bws_run_traversal()'s
 * `$gate` default), so traversal-pipeline.php needs nothing from here at load.
 *
 * NOTHING HERE IS RE-INCLUDE GUARDED, DELIBERATELY. A `function_exists()` wrapper would let
 * any plugin that defines the name first replace the whole gate and loosen it, which is the
 * one thing the filter below is built to make impossible. Unguarded, that collision is a
 * fatal at load instead of a silent bypass. The plugin loads this once, with `require_once`.
 *
 * @package BWS_Dynamic_Tags
 * @since Unreleased
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The default source gate — the deciding rule for the EXISTS and VISIBLE levels.
 *
 * @invariant A limit bounds USABLE sources (CONTEXT.md I19; ADR 0007
 * "a limit counts usable sources"). THIS FUNCTION OWNS THE AXIS: a source is
 * usable iff it is resolvable (it reached this gate at all) AND names a live
 * entity (exists — viewer-independent) AND the current viewer may read it
 * (visible — viewer-relative) AND no `bws_dynamic_tags_source_visible` callback
 * refused it. Per kind:
 *
 *   post      exists = get_post() non-null (a TRASHED post exists);
 *             visible = the RESOLVED status (get_post_status(), never the raw
 *             column) is publicly viewable, OR — for a status that is neither
 *             public nor `internal` — the current user has `read_post` on it.
 *             Viewer-relative BY DESIGN there, so an author previewing their
 *             own draft resolves it (record: docs/design-history/
 *             deterministic-source-selection.md §S19). An INTERNAL status is refused
 *             for EVERY viewer, capability or not: `trash` and `auto-draft` are
 *             deletion/scratch states rather than publication ones, and WP's own
 *             front end renders neither to anyone, so honoring `read_post` on
 *             them would show an editor content no visitor can reach.
 *             Resolving first is load-bearing: an attachment stores `inherit`,
 *             which is itself an internal status and would be refused, while
 *             get_post_status() answers it with the parent's status (or
 *             `publish` when unattached).
 *   term      exists = get_term() yields a WP_Term; no publication status, so
 *             visible adds nothing (that record's §S21).
 *   user      exists = get_userdata() truthy; same, existence only.
 *   site      vacuously usable — no entity to test.
 *   meta_row  vacuously usable — carries a row, not an entity id; the direct
 *             reader {{table}} uses for `use:key` rows holds no entity to test,
 *             so it is out of gate reach AND out of gate need (that record's
 *             §S48 records the audit; the note also sits at the reader).
 *   other     passes — the gate restricts only what it can test (S27's
 *             restrict-only posture); `unresolved` is refused by the input-kind
 *             gate, not here.
 *
 * FIELD POPULATION IS DELIBERATELY NO PART OF THIS TEST — selection must be
 * field-independent so adjacent tags on one source path read the same entity
 * (the 2026-08-21 determinism reversal; ADR 0007 §Why the read-based axis was
 * reversed).
 *
 * THE FILTER — `bws_dynamic_tags_source_visible`, `( bool $ok, array $source )`.
 * RESTRICT-ONLY BY CONSTRUCTION: it runs only after the default above passed, so it
 * can refuse a source and can never admit one the default refused, and a callback
 * that returns what it was given is a no-op. It fires PER SOURCE, once per candidate,
 * for every kind (a consumer ignores the kinds it does not gate), and receives the
 * source as resolved — a consumer derives anything past `kind` and `id` from request
 * context itself. Nothing is cached here; the editor preview runs the same gate.
 * Callback-to-callback chaining is WordPress's, not ours: a later callback can undo
 * an earlier one's `false`, which is why plugin-integration.md §3 states the
 * `$ok && yours` convention. The decision that produced this contract:
 * docs/design-history/deterministic-source-selection.md §S22 loosened + §O8 (O8 left
 * per-source vs per-list open; per-source was chosen when the hook shipped).
 *
 * Pure-harness note: this function names WP symbols and is therefore NEVER
 * called by tools/test/traversal-pipeline-test.php, which injects its own
 * predicate — that injectability is what keeps the engine harness pure
 * (that record's §S20 corrected).
 *
 * SUPPORTED INTEGRATION SURFACE (docs/plugin-integration.md §3): outside tag callbacks
 * call it with `array( 'kind' => 'post'|'term'|'user', 'id' => $id )`, so that input
 * shape and the bool return stay stable; other typedef keys are optional to it.
 *
 * @since 1.18.0 As bws_source_gate(), in traversal-pipeline.php.
 * @since Unreleased Renamed bws_is_source_usable(); the visibility filter.
 * @param array $source Resolved source (see traversal-pipeline.php's file header typedef).
 * @return bool True when the source may feed reads and consume limit budget.
 */
function bws_is_source_usable( array $source ) {
	$kind = isset( $source['kind'] ) ? (string) $source['kind'] : '';
	$ok   = true; // site / meta_row / unknown: nothing testable here.

	if ( 'post' === $kind ) {
		$post = get_post( isset( $source['id'] ) ? (int) $source['id'] : 0 );
		if ( ! $post instanceof WP_Post ) {
			$ok = false; // Fails EXISTS.
		} else {
			$status = get_post_status( $post ); // Resolves an attachment's `inherit`.
			if ( ! is_post_status_viewable( $status ) ) {
				$status_obj = get_post_status_object( (string) $status );
				if ( is_object( $status_obj ) && ! empty( $status_obj->internal ) ) {
					$ok = false; // trash / auto-draft: refused for every viewer.
				} else {
					$ok = current_user_can( 'read_post', $post->ID ); // VISIBLE, viewer-relative.
				}
			}
		}
	} elseif ( 'term' === $kind ) {
		$term = get_term( isset( $source['id'] ) ? (int) $source['id'] : 0 );
		$ok   = $term instanceof WP_Term; // EXISTS only (no publication status).
	} elseif ( 'user' === $kind ) {
		$ok = (bool) get_userdata( isset( $source['id'] ) ? (int) $source['id'] : 0 );
	}

	// AND-composed: only a passing source reaches the filter, so it can only restrict.
	if ( $ok ) {
		$ok = (bool) apply_filters( 'bws_dynamic_tags_source_visible', true, $source );
	}
	return (bool) $ok;
}
