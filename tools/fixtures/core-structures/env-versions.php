<?php
/**
 * core-structures blueprint — the dependency versions the committed page-snapshot
 * baseline was captured under.
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
 * A VERSION CHANGE IS A WARNING, NEVER A FAILURE. Only a human can judge whether moved
 * output is acceptable, and the instrument that can tell them WHAT moved is the snapshot
 * diff sitting beside this. Absence of a REQUIRED dependency is the different case and is
 * a hard failure — that rule lives with the dependency declaration, not here.
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
	'captured' => '2026-08-24',

	// Keyed by plugin FILE (the `plugin_basename()` form), because that is the key
	// `get_plugins()` returns and the only identifier that survives a display-name
	// change. `label` is for the human reading a drift line.
	'plugins'  => array(
		'generateblocks/plugin.php' => array(
			'label'   => 'GenerateBlocks',
			'version' => '2.4.1',
		),
		'generateblocks-pro/plugin.php' => array(
			'label'   => 'GenerateBlocks Pro',
			'version' => '2.7.0',
		),
		'gb-query-enhancements/gb-query-enhancements.php' => array(
			'label'   => 'GB Query Enhancements',
			'version' => '1.3.0',
		),
		'advanced-custom-fields-pro/acf.php' => array(
			'label'   => 'ACF Pro',
			'version' => '6.8.8',
		),
	),
);
