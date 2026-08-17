<?php
/**
 * The `try_*` slot ARM TABLE — one dispatch keyed by resolved source kind (FW-71, retires FW-5).
 *
 * `generate_base_try_tags()`'s callback used to carry FOUR hand-written arms that each
 * tested the flat source token directly (`'' !== $stm_raw`, `'site' === $last_src`,
 * `'current' === $last_src`, else post). They are one dispatch now: ask
 * bws_base_src_resolution() what the slot's source RESOLVES TO, look the answer up here,
 * and run one shared emit. A chain-spelled slot and a flat-spelled one therefore take the
 * same arm, which is the identity FW-71 exists to establish (CONTEXT.md I16).
 *
 * PURE and DATA-ONLY, on purpose. Every byte-identity risk in the FW-71 refactor
 * concentrates in this collapse (the term arm's break-early-while-hopping vs a slice, the
 * per-arm link-wrap entity, the single-result gate's position relative to the limit
 * slice), and the arms sat inside a closure with no seam under them. Extracting the table
 * puts those risks OVER a seam a pure harness can assert (tools/test/try-slot-arms-test.php)
 * instead of under one.
 *
 * SCOPED TO `try_`, deliberately. Generalising this into a shared base-side map was
 * considered during the FW-71 seam review and rejected: it moves the boundary past what
 * the harvest/replay instrument was built to bound. `{{join}}` needs no entry at all — its
 * slot resolver delegates to the text absorb seam, which FW-63 already converted to kind
 * dispatch.
 *
 * `try_` KEEPS its per-template function table (`try_core_fn` / `try_term_fn` /
 * `try_site_fn`). Only `text` has an absorb-seam resolver; dispatching the other eight
 * families to base resolvers would need eight more, which is FW-60's work.
 *
 * @package BWS_Dynamic_Tags
 * @since   1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolved source kind → how a `try_` slot resolves, renders, wraps and lists.
 *
 * Keys are bws_fold_chain_resolution()'s `kind` vocabulary, verbatim — plus the two
 * kinds a `base` root can BRANCH to at render (see `branchable`). A kind with no row
 * here is SKIPPED, never guessed at: the `''` kind means the chain's last step is
 * unknown vocabulary, the engine answers empty for it, and picking the nearest
 * consumable arm would read a different source than the wire states.
 *
 * Columns:
 *
 *   `ids`        Which entity ids the arm reads off the slot's source.
 *                'term' → bws_base_term_ids_from_source()
 *                'post' → bws_base_post_ids_from_source()
 *                'user' → bws_base_user_ids_from_source()
 *                'none' → no entity at all (the site store carries a namespace, not an
 *                         id — ADR 0002)
 *                ''     → nothing to read; the slot is skipped
 *   `fn`         Which of the template's three functions renders one entity.
 *                'term' → try_term_fn, 'core' → try_core_fn, 'site' → try_site_fn (with
 *                try_core_fn( 0, … ) as the documented fallback leg, FW-4), 'user' →
 *                try_user_fn, a thin closure over the base user analog. '' → no consumer.
 *   `link`       The entity type bws_wrap_with_link() is handed for a SINGLE-result
 *                output. '' → this arm never link-wraps.
 *   `list`       Whether the slot's limit slice + `sep` join seam applies.
 *   `branchable` Whether a root-only (`base`) chain may land on this arm once the factory
 *                has resolved. `base` itself is not branchable — that would loop.
 *
 * WHERE THIS TABLE DIFFERS FROM THE TICKET'S SKETCH (#103), and why: the sketch marks
 * `site` list handling "n/a". The shipped site arm does normalize, slice and join exactly
 * as the others do — only its LINK identity is special (a sentinel, and only on the
 * try_site_fn leg). Byte-identity is the property under test, so the table states what the
 * code does. The sketch also gives the `user` row a `post` link entity; the base absorb
 * seam wraps a user read as `user`, and that seam is the owner of the user analog this row
 * consumes, so the row follows it. #108 settled that disagreement in the row's favour and
 * wired the leg WITHOUT touching this table.
 *
 * @since 1.17.0
 */
const BWS_TRY_SLOT_ARMS = array(
	// An explicit term step (`srcTermIn:x`, or a chain ending `terms,x`).
	'term'     => array(
		'ids'        => 'term',
		'fn'         => 'term',
		'link'       => 'term',
		'list'       => true,
		'branchable' => true,
	),
	// `src:site` — no entity; the read is the site store itself.
	'site'     => array(
		'ids'        => 'none',
		'fn'         => 'site',
		'link'       => 'site',
		'list'       => true,
		'branchable' => false,
	),
	// A relationship step (`src:ref|ref:x`, or a chain ending `refs,x`).
	'post'     => array(
		'ids'        => 'post',
		'fn'         => 'core',
		'link'       => 'post',
		'list'       => true,
		'branchable' => true,
	),
	// Root-only: the factory decides at render. Resolve it ONCE, then branch on the
	// resolved source's own kind through bws_try_slot_base_branch_kind().
	'base'     => array(
		'ids'        => 'branch',
		'fn'         => 'branch',
		'link'       => 'branch',
		'list'       => true,
		'branchable' => false,
	),
	// Ambient author archive. LIVE since #108 on the three templates the user analog covers
	// (text/title/content, via try_user_fn); the other six render empty there, as their base
	// tags do. Asserting the row before anything could reach it is what made #108 a wiring
	// change rather than a table one. What a template with no user fn does instead is the
	// dispatcher's rule, stated where it is enforced (generate_base_try_tags()).
	'user'     => array(
		'ids'        => 'user',
		'fn'         => 'user',
		'link'       => 'user',
		'list'       => false,
		'branchable' => true,
	),
	// A repeater `entries` step. NO `try_` arm consumes a meta_row and none should — that
	// is `{{table}}`'s assembly, not a fallback attempt's. Skipped, with every column
	// empty, so the skip is a property of the table rather than of a branch somewhere.
	'meta_row' => array(
		'ids'        => '',
		'fn'         => '',
		'link'       => '',
		'list'       => false,
		'branchable' => false,
	),
);

/**
 * The whole arm table.
 *
 * A function rather than direct const reads at the call sites, so the harness and the
 * dispatcher ask one thing and a future filter has one place to land.
 *
 * @since 1.17.0
 * @return array<string, array{ids:string, fn:string, link:string, list:bool, branchable:bool}>
 */
function bws_try_slot_arms(): array {
	return BWS_TRY_SLOT_ARMS;
}

/**
 * The arm for a resolved source kind, or null when no `try_` arm consumes it.
 *
 * NULL IS A REFUSAL, NOT A DEFAULT. The caller skips the slot and tries the next attempt;
 * it must never fall back to the post arm on an unknown kind, because the post arm would
 * read the ambient entity and hand the author a plausible wrong value instead of an
 * obvious empty one — the [I15] failure class this whole area keeps producing.
 *
 * @since 1.17.0
 * @param string $kind Resolved source kind (bws_fold_chain_resolution()'s `kind`).
 * @return array{ids:string, fn:string, link:string, list:bool, branchable:bool}|null
 */
function bws_try_slot_arm( string $kind ): ?array {
	return BWS_TRY_SLOT_ARMS[ $kind ] ?? null;
}

/**
 * Which arm a ROOT-ONLY chain lands on, once the factory has resolved its base source.
 *
 * The `base` kind means "static analysis cannot say" — the factory answers `post` on a
 * singular page, `term` on a term archive, `user` on an author archive, `meta_row` in a
 * flat repeater row. This maps that answer onto an arm.
 *
 * ANYTHING NOT BRANCHABLE FALLS TO THE POST ARM, which is not a guess: it is what ships.
 * A `meta_row` base is the flat-repeater-row case, where the post arm resolves no id and
 * the loop fallthrough (mode 2b) hands the row to the core function directly. Refusing it
 * here would delete that path.
 *
 * THE ONE EXCEPTION IS THE REFUSAL KIND (BWS_SOURCE_KIND_UNRESOLVED, GH #75 / #76), and
 * it is why this returns null at all. That kind means the factory was handed a source it
 * could not use, so defaulting it to the post arm is the SAME ambient read one layer
 * down — the obvious sentinel would otherwise have reproduced GH #109 in the act of
 * fixing it. Refusing WHOLESALE was not available: the documented `meta_row` default
 * above is load-bearing, so the refusal is for the sentinel alone and every other kind
 * keeps the default it has always had.
 *
 * NULL IS A REFUSAL, NOT A DEFAULT — the posture its neighbour bws_try_slot_arm()
 * already documents. The two adjacent lookups now give the same answer for a kind no arm
 * consumes, which they did not before.
 *
 * This reproduces bws_base_ambient_term_id() / bws_base_ambient_user_id() rather than
 * competing with them: both gate on the chain resolving to `base` (established before this
 * is called) and then on the base source's own kind, which is what this tests. Kept pure
 * and separate so the branch is assertable without a WordPress query.
 *
 * @since 1.17.0
 * @since 1.17.0 Nullable — refuses BWS_SOURCE_KIND_UNRESOLVED.
 * @param string $base_kind The resolved base source's `kind`.
 * @return string|null A branchable arm key, 'post', or null when the base source is a
 *                     refusal and the caller must skip the attempt.
 */
function bws_try_slot_base_branch_kind( string $base_kind ): ?string {
	// Unguarded on purpose — see bws_base_read_refused(). A defined() check here defends a
	// state that cannot occur, and would switch the refusal off silently if it ever could.
	if ( BWS_SOURCE_KIND_UNRESOLVED === $base_kind ) {
		return null;
	}
	return ! empty( BWS_TRY_SLOT_ARMS[ $base_kind ]['branchable'] ) ? $base_kind : 'post';
}
