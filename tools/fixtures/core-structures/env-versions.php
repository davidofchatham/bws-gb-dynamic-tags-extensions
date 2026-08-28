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
	// THE 2026-08-28 RE-CAPTURE MOVED EVERY PAGE, AND NO RENDERED TAG MOVED WITH THEM.
	// Two co-resident BWS plugins (`bws-sticky-header` and the viewport-height styles
	// beside it) were ACTIVE when the previous baseline was taken and are inactive now,
	// so their stylesheet and inline-CSS blocks left the document head and every line
	// after them shifted. That is the whole of a 252-line deletion; the recapture was
	// verified against a page diff carrying no tag output at all.
	//
	// NEITHER IS RECORDED BELOW, and their absence is not an omission to repair: this
	// record holds what was PRESENT at capture, so an entry for a plugin the baseline
	// was taken WITHOUT would assert the opposite of the truth. The note is the record.
	// What it exposes is that ANY plugin toggling on the fixture site reshuffles every
	// baseline, since the normalizer keeps third-party `<link>` and `<style id>` lines
	// and only blanks their bodies. Teaching it to drop non-fixture assets is tracked
	// rather than done here — that is a page-snapshot INSTRUMENT change with its own
	// trigger, and burying it in a recapture is how an instrument change goes unread.
	'captured' => '2026-08-28',

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
			'version'  => '6.8.8',
			'required' => true,
		),
	),
);
