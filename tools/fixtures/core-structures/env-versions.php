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
	// THIS RE-CAPTURE IS WOOCOMMERCE JOINING THE FIXTURE SITE, and every one of the 297 added
	// and 50 removed lines is its chrome: the `sourcebuster` and `wc-order-attribution` footer
	// scripts, the `woocommerce-no-js` body class with the inline script that swaps it, and an
	// `aria-label="Page N"` on every paginated archive. That last one comes from
	// `wc_add_aria_label_to_pagination_numbers()` on `paginate_links_output`, which is a
	// SITE-WIDE filter and not scoped to Woo's own pages. The remainder is line-packing
	// shifting around the inserted scripts.
	//
	// NO RENDERED TAG MOVED, and that was measured rather than assumed: every changed line was
	// bucketed by category with none left unclassified, and the slim-seo schema pair on each of
	// the 19 pages was compared byte-for-byte — the JSON is identical, only its adjacency to the
	// next `<script` changed. Woo's footprint on our output is nil. That is why this capture is
	// a commit of its own: a later change that does move a tag gets a readable diff.
	//
	// The previous capture (2026-09-03) is where the head-deletion rule arrived — a reader
	// hitting an ~800-line deletion further back in `git log` is looking at that, not at this.
	'captured' => '2026-09-14',

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
		'bws-portal-system/bws-portal-system.php',
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
			'version'  => '1.3.0',
			'required' => true,
		),
		'advanced-custom-fields-pro/acf.php' => array(
			'label'    => 'ACF Pro',
			'version'  => '6.8.9',
			'required' => true,
		),
		// Recorded for the same reason as GB Query Enhancements above: it supplies no fixture
		// row's content, but it was running at capture and its chrome is in every baseline.
		// `required` is TRUE because deactivating it invalidates all 19 pages — one line
		// naming WooCommerce is a better failure than 19 page diffs with no stated cause.
		'woocommerce/woocommerce.php' => array(
			'label'    => 'WooCommerce',
			'version'  => '11.1.0',
			'required' => true,
		),
	),
);
