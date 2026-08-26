<?php
/**
 * The boundary between this plugin's tag output and GenerateBlocks' output pipeline.
 *
 * Every tag we render finishes by handing its string to
 * GenerateBlocks_Dynamic_Tag_Callbacks::output(). That call is not private: its last act is
 * apply_filters( 'generateblocks_dynamic_tag_output', $output, $options, $raw_output ), so
 * whatever we put in $options is published to every co-resident extension that hooks it.
 * This file owns what we publish there.
 *
 * NOTHING HERE IS RE-INCLUDE GUARDED, AND THE CONSISTENCY IS THE POINT. Wrapping only the
 * function left a second include warning on the two `const` declarations -- the half that
 * a `function_exists` check cannot protect -- while reading as though the file were
 * idempotent. The plugin loads it once, with `require_once`; the shape now matches
 * slot-fold.php and try-slot-arms.php, bare consts beside bare functions.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * THE option keys GB's own output pipeline consumes. Nothing else reaches it from us.
 *
 * AXIS — an option travels to GB iff GB's output pipeline reads it. Not "iff it is
 * harmless", not "iff we have been bitten by it": an allowlist, because the set of
 * readers is open (any plugin may hook the filter) while the set of GB consumers is
 * closed and readable. A blocklist could only ever name collisions already suffered.
 *
 * Read from GenerateBlocks 2.4.1 -- see BWS_GB_TAG_OUTPUT_OPTIONS_READ_FROM. Each key
 * and the method that consumes it, all in class-dynamic-tag-callbacks.php:
 *
 *   trunc    with_trunc()    substr / wp_trim_words
 *   replace  with_replace()  str_replace on a "search,replace" pair
 *   trim     with_trim()     ltrim / rtrim / trim
 *   case     with_case()     lower / upper / title
 *   wpautop  with_wpautop()  wpautop()
 *   link     with_link()     wraps the output in <a>
 *   id       with_link()     indirectly, via GenerateBlocks_Dynamic_Tags::get_id()
 *
 * A future GB release adding a transform makes this set incomplete, and the symptom is
 * silent: a documented GB option would simply stop working on our tags. The version below
 * is recorded so a GB upgrade has something to re-read this set against. GB moving is
 * already a tracked event -- tools/fixtures/core-structures/env-versions.php records the
 * GB version the fixture baseline was captured under, and docs/update-triggers.md's
 * dependency-version-change trigger is where that move is handled.
 *
 * The membership of this set, and the agreement between the version below and the GB
 * version that record holds, are pinned by tools/test/gb-output-boundary-test.php.
 */
const BWS_GB_TAG_OUTPUT_OPTIONS = array(
	'trunc',
	'replace',
	'trim',
	'case',
	'wpautop',
	'link',
	'id',
);

/**
 * The GenerateBlocks version BWS_GB_TAG_OUTPUT_OPTIONS was read from.
 *
 * Recorded, not enforced. The set is re-read against GB's
 * GenerateBlocks_Dynamic_Tag_Callbacks::output() when this no longer matches the GB on
 * the fixture site, and this string moves in the same edit as the set.
 */
const BWS_GB_TAG_OUTPUT_OPTIONS_READ_FROM = '2.4.1';

/**
 * Hand a finished tag string to GB's output pipeline, with only the options GB reads.
 *
 * Call this instead of GenerateBlocks_Dynamic_Tag_Callbacks::output() directly. GB's
 * transforms are unaffected -- every option they consume is in the allowlist -- but the
 * options WE have already consumed (`fallback` above all, on 37 tags) stop travelling into
 * `generateblocks_dynamic_tag_output` FROM THE CALL SITES THAT USE THIS FUNCTION, where a
 * co-resident extension re-applies them to an output that has already had them applied.
 *
 * WHAT THAT IS TRUE OF TODAY IS NARROWER THAN THE PLUGIN, AND THE SCOPE IS MEASURED, NOT
 * ASSUMED. Counted 2026-08-26 over `includes/`: 14 call sites route through here -- the
 * image family and its term sibling, the one path with an observable defect -- and 38 still
 * call GB's output method directly, handing it the full option array. An option we consumed
 * therefore still travels from those 38. Routing them is a deliberately separate change in
 * this same release, kept separate because it moves no output and proving THAT is its whole
 * deliverable; burying it beside the one call site that fixes something would make neither
 * reviewable. Until it lands, read the guarantee above as scoped to the image family.
 *
 * The measured instance: a co-resident query extension re-applies `fallback` whenever the
 * output is empty, so `{{image key:missing|fallback:<missing attachment id>}}` -- which our
 * tag correctly resolves to empty, having already tried and failed to render that
 * attachment -- printed the raw attachment id as the image src. The option was consumed
 * before it left us; publishing it said otherwise. Nothing here is conditional on which
 * extension is installed, because the statement being fixed is about our boundary.
 *
 * $instance is passed through untouched: it is GB's own block instance, not our options.
 *
 * @since 1.19.0
 * @param string $output   The finished tag output.
 * @param array  $options  Full tag options, as GB parsed them.
 * @param object $instance Block instance, passed through to GB.
 * @return string GB's output, after its transforms and its filter.
 */
function bws_gb_tag_output( $output, $options, $instance = null ) {
	$gb_options = is_array( $options )
		? array_intersect_key( $options, array_flip( BWS_GB_TAG_OUTPUT_OPTIONS ) )
		: array();

	return GenerateBlocks_Dynamic_Tag_Callbacks::output( $output, $gb_options, $instance );
}
