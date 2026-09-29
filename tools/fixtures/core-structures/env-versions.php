<?php
/**
 * core-structures blueprint — the dependency record the committed page-snapshot baseline
 * was captured under: which plugins must be there, and at which versions they were.
 *
 * Pure data, like manifest.php. Consumers: `verify.php` (compares live vs recorded on
 * every run) and anyone reading a snapshot diff who needs to know what moved.
 *
 * WHAT THIS RECORD IS FOR. `tools/test/snapshots/` is a claim about rendered output, and
 * rendered output is a joint product of our build and everything co-resident with it. On
 * its own a snapshot diff cannot distinguish "we broke it" from "GenerateBlocks changed
 * under us" — and those want opposite responses. Recording the environment turns the
 * second case into a statement the tooling can make instead of a thing the operator has to
 * remember. `verify.php` reports drift here SEPARATELY from a page diff, so an unexplained
 * diff arriving alongside a version change reads as an attribution, not an accusation.
 *
 * OUR OWN VERSION IS DELIBERATELY EXCLUDED, and that is not an oversight. The record
 * answers "what environment were these results true in"; our plugin version is what the
 * results are ABOUT. Including it would make every release bump report drift, training the
 * operator to ignore the line that exists to be read. (Spec decision D23.)
 *
 * THE TWO AXES ARE ANSWERED DIFFERENTLY, AND THIS FILE OWNS THAT RULE.
 *
 * - A VERSION CHANGE IS A WARNING. Only a human can judge whether moved output is
 *   acceptable, and the instrument that can tell them WHAT moved is the snapshot diff
 *   sitting beside this.
 * - A REQUIRED DEPENDENCY BEING UNUSABLE IS A FAILURE, and not a skip. Every baseline
 *   under `tools/test/snapshots/` was captured with the whole set running, so a comparison
 *   made without one of them is not the comparison the baseline is a claim about. What
 *   counts as unusable is `bws_page_snapshot_env_compare()`'s call, not this file's.
 *   FAILING rather than skipping is the rule the node-dependent harnesses already carry,
 *   for the same reason: a silent pass hides exactly the drift the check exists to catch.
 *
 * `required` DEFAULTS TO TRUE, so silence is the safe answer. Everything recorded here was
 * by construction PRESENT when the baseline was captured, which makes "must still be
 * there" the only defensible reading of an entry that says nothing, and a dependency added
 * to this record without a flag then fails loudly rather than joining it as an optional
 * extra nobody reads. Writing `'required' => true` on every entry anyway is for the
 * reader, not the code. NO ENTRY IS CURRENTLY `false`: that state exists because this
 * record and the requirement are different questions — the fixture site runs many plugins
 * and records four, so one could legitimately be recorded for provenance alone, and the
 * flag is what keeps that from reading as a hard failure.
 *
 * TWO LISTS, TWO QUESTIONS. `plugins` is the DEPENDENCY record — a short set with versions,
 * and the two axes above are its rules. `active` is a PROVENANCE record: every plugin that
 * was running at capture, no versions, no `required` flag, and never blocking. It exists
 * because a co-resident plugin can move rendered output without being a dependency of ours,
 * and reconstructing which one did that after the fact is what the 2026-08-28 note below
 * records someone having to do. A change there is reported in BOTH directions and read as
 * attribution, exactly like a version change.
 *
 * WHEN THIS FILE MOVES. Re-record it in the SAME commit that re-captures the baseline,
 * never separately: a version bump recorded against an un-recaptured baseline silently
 * asserts that the new dependency version produces the old output, which is exactly the
 * claim nobody checked.
 *
 * @package BWS_Dynamic_Tags
 */

return array(
	// The date the baseline under `tools/test/snapshots/` was captured. Prose only —
	// nothing compares it; it is here so a reader can place the record in time.
	//
	// SAME-DAY FOLLOW-UP CAPTURE. `blocks.php` gained one more row on the same
	// "Join - fixed-text slots (FW-141 02)" section: J33, `{{join A:src(refs,missing_rel);
	// fixed(Team)}}`, added to measure (not just assert) that a fixed slot whose own chain
	// resolves nothing still drops empty — the CHANGELOG claim this ticket's code review
	// flagged as unmeasured for the join case. `page-matrix-post-meta` moved by that one row
	// (a blank line, per J33's own empty expected output, packed against the section below
	// it). No other page moved.
	//
	// Also recorded here: `active` swaps `bws-portal-system/bws-portal-system.php` for
	// `site-views/site-views.php` — the live plugin list read for this capture shows the
	// rename the note below already inferred from that plugin's own git history (the
	// F10.2/F10.6b value-change attribution). The swap itself was already in effect on the
	// shared site; this capture is the first to read it off `wp plugin list` and record it.
	//
	// THE PRIOR RE-CAPTURE (SAME DAY) WAS MOSTLY OUR OWN FIXTURE, but not only. `blocks.php`
	// gained a new "Join - fixed-text slots (FW-141 02)" section (J29-J32) on
	// `page-matrix-post-meta` — that IS this ticket's whole change to `blocks.php` as of that
	// capture (confirmed: `git diff 5ddcbb1 -- tools/fixtures/core-structures/blocks.php`
	// showed nothing else). But the two pages' snapshot diffs carried more than that
	// addition, in two distinct shapes:
	//
	// ROW REMOVALS, unrelated to this ticket and predating it: `page-matrix-post-meta` drops
	// L4.11a-c, F8.10, F9.6, F9.6b, F9b.4, F9d.1, F9d.2; `page-matrix-pinned-roots` drops
	// F22.2 and F22.5b. None of these rows exist in `blocks.php` as of `5ddcbb1` either — they
	// were deleted from the blueprint in earlier, already-committed changes (FW-141 01 among
	// them, which added F22.3/F22.4 to `page-matrix-pinned-roots` without re-capturing), so
	// this capture is catching up several commits' worth of uncaptured row deletions, not one.
	//
	// THREE VALUE CHANGES on rows `blocks.php` still carries UNCHANGED, which a row deletion
	// cannot explain: F10.2 (`All Users, All Users` -> `Captain`), F10.6b (`Sales, Support,
	// All Users` -> `Sales, Support`), and F10.6b's legacy twin (`Sales, All Users` ->
	// `Sales`). All three read the `portal_visibility` taxonomy off `bws-portal-system`
	// fixture posts. That plugin's own history (its working tree, a sibling checkout — see
	// `git log --oneline -S portal_visibility` there) shows an in-progress rename of its
	// visibility system ("Portal" -> "Site Views"), with commits restructuring that exact
	// taxonomy's term shape ("audience taxonomy named from the View CPT", "one no-context term
	// per shared taxonomy"). That is the likely cause: a co-resident plugin's own data
	// migration moving what these three rows read, the same class of drift the GB Query
	// Enhancements note below already establishes a precedent for attributing. It is NOT
	// independently measured here — `bws-portal-system` is tracked in `active` below for
	// presence only, not versioned, and a taxonomy-term migration run on the shared site
	// wouldn't show as a plugin version bump even if it were. Auditing that migration's actual
	// effect on this fixture site is out of FW-141 02's scope; flagged for a human decision
	// rather than resolved here, per this repo's CLAUDE.md doc/code-drift rule. Dependency
	// versions and the active set otherwise unchanged (blueprint v28, plugin list read off the
	// live site to confirm).
	//
	// The PREVIOUS capture (2026-09-25) is a DEPENDENCY MOVE. GB Query Enhancements
	// 1.3.0 -> 1.4.0 moved three lines on `page-matrix-products`: each Product Query loop
	// item's `<li>` now carries WooCommerce's `woocommerce/products` Interactivity context
	// and `data-wp-interactive` (`WooCommerce_Query::add_products_interactivity_context()`),
	// wrapper only, every rendered tag byte-identical. WordPress 7.1 -> 7.1.2 and
	// WooCommerce 11.1.0 -> 11.1.2 moved in the same window and are attributable for no
	// line: the pre-capture run showed only those three. Active set unchanged.
	//
	// The PREVIOUS capture is FW-142's §CT8 (1.21.0): six rows added to `page-matrix-content`
	// (a `key` alone implies the keyed read; an explicit `use` beside a stale `key` wins).
	// Pure additions — no existing row moved. `ctx-term` moved by one line only because the
	// Sales archive lists that page's excerpt, which now names the new section heading.
	// Dependency versions and the active set unchanged.
	//
	// The one before that is the §C-TERM/CT REWRITE (FW-129, 1.21.0) — the ticket the
	// capture before it said was coming. Six dead `term_*` rows left the blueprint and
	// one base-spelled row replaced them: C-TERM1/C-TERM2 off the context element (their
	// base spellings were already there as C-X1 and C-CONV11), CT-A/CT-B off
	// `page-matrix-post-meta` (already on `page-matrix-pinned-roots` as F20.2/F20.1), QL1.5
	// off `page-matrix-loops` (its convert-side twin QL1.6 was already under it), and CT-C
	// rewritten in place as `{{text src:terms,department,limit(1)|key:phone}}`, which is the
	// only genuinely new row. Nine pages moved: the seven `ctx-*`, `page-matrix-post-meta`
	// and `page-matrix-loops`. Most of the line count is packing — a removed row displaces
	// everything below it and the rest of the document reports as changed.
	//
	// The `term_*` rows THAT REMAIN are §C-CONV's before-halves, and they stay deliberately:
	// a row rendering its own braces is what unconverted stored wire looks like after the
	// removal, which is the versioning axis's whole premise rather than a row gone stale.
	//
	// The PREVIOUS capture (same date) is the removal itself, where those braces first
	// appeared. ACF Pro moved 6.8.9 -> 6.8.10 there and is recorded below; it is NOT
	// attributable for any line, because the pre-change run passed against the 6.8.9-era
	// baseline with 6.8.10 already installed.
	//
	// The 2026-09-14 capture is WooCommerce joining the fixture site (chrome only, no
	// rendered tag moved); the 2026-09-03 one is where the head-deletion rule arrived — a
	// reader hitting an ~800-line deletion further back in `git log` is looking at that.
	'captured' => '2026-09-29',

	// WordPress core, as `get_bloginfo( 'version' )` reports it. A change is a WARNING like a
	// plugin version change, never a failure. First recorded 2026-09-25, read off the site
	// with a 7.1.2 update pending and the 2026-09-24 baseline not re-captured, so it is the
	// version that baseline was running under unless core moved that one day. The evidence for
	// readme.txt's `Tested up to`: page-snapshot-normalize-test.php fails if that line names a
	// newer major.minor than the one recorded here. 7.1.2 since the 2026-09-25 capture.
	'wordpress' => '7.1.2',

	// EVERY PLUGIN THAT WAS RUNNING, not only the four this record requires. The version
	// list below answers "were the dependencies the same"; this answers "what else was in
	// the room", which is the question a moved baseline actually raises. `bws_page_snapshot_
	// env_compare()` reports a change here in BOTH directions and never blocks on it — the
	// two-axis rule above is about REQUIRED dependencies, and an unexpected co-resident
	// plugin is a thing to attribute, not a thing to forbid on a shared fixture site.
	//
	// THIS PLUGIN IS IN THE LIST. Our VERSION is excluded from the record on purpose (see
	// above); our presence is not the same claim, and omitting it would make the list a
	// curated set that the live comparison then reports as newly active on every run.
	//
	// Re-record it with the baseline, from:
	//   wp plugin list --status=active --field=file
	'active'   => array(
		'acf-extended/acf-extended.php',
		'acf-quickedit-fields/index.php',
		'admin-site-enhancements-pro/admin-site-enhancements.php',
		'advanced-custom-fields-pro/acf.php',
		'block-visibility/block-visibility.php',
		'bws-block-visibility-acf-datetime-extension/bws-block-visibility-acf-datetime-extension.php',
		'bws-gb-dynamic-tags-extensions/bws-gb-dynamic-tags-extensions.php',
		'bws-generate-layout-conditions/bws-generate-layout-conditions.php',
		'bws-pdf-viewer/bws-pdf-viewer.php',
		'bws-user-based-terms/bws-user-based-terms.php',
		'gb-query-enhancements/gb-query-enhancements.php',
		'gb-query-filter/gb-query-filter.php',
		'generateblocks-pro/plugin.php',
		'generateblocks/plugin.php',
		'gp-premium/gp-premium.php',
		'litespeed-cache/litespeed-cache.php',
		'meta-box-lite/meta-box-lite.php',
		'meta-conductor/meta-conductor.php',
		'redirection/redirection.php',
		'site-views/site-views.php',
		'slim-seo/slim-seo.php',
		'woocommerce/woocommerce.php',
		'wpcodebox2-keyed/wpcodebox2.php',
		'ws-form-pro/ws-form.php',
	),

	// Keyed by plugin FILE (the `plugin_basename()` form), because that is the key
	// `get_plugins()` returns and the only identifier that survives a display-name
	// change. `label` is for the human reading a drift line.
	'plugins'  => array(
		'generateblocks/plugin.php' => array(
			'label'    => 'GenerateBlocks',
			'version'  => '2.4.1',
			'required' => true,
		),
		'generateblocks-pro/plugin.php' => array(
			'label'    => 'GenerateBlocks Pro',
			'version'  => '2.7.1',
			'required' => true,
		),
		// Recorded for a reason no fixture row shows: it supplies no row's content, it
		// is a co-resident extension filtering every tag render. docs/testbed.md says
		// what its presence changes.
		'gb-query-enhancements/gb-query-enhancements.php' => array(
			'label'    => 'GB Query Enhancements',
			'version'  => '1.4.0',
			'required' => true,
		),
		'advanced-custom-fields-pro/acf.php' => array(
			'label'    => 'ACF Pro',
			'version'  => '6.8.10',
			'required' => true,
		),
		// Recorded for the same reason as GB Query Enhancements above: it supplies no fixture
		// row's content, but it was running at capture and its chrome is in every baseline.
		// `required` is TRUE because deactivating it invalidates all 19 pages — one line
		// naming WooCommerce is a better failure than 19 page diffs with no stated cause.
		'woocommerce/woocommerce.php' => array(
			'label'    => 'WooCommerce',
			'version'  => '11.1.2',
			'required' => true,
		),
	),
);
