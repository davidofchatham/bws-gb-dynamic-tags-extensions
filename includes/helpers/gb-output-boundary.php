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
 * `tag_name` IS NOT HERE, AND THE OMISSION IS A DECISION, taken 2026-08-26 after being
 * deferred twice. Nothing on the fixture site reads `$options['tag_name']` -- measured
 * across GB 2.4.1, GB Pro 2.7.0, GB Query Enhancements 1.3.0, GB Query Filter 0.4.0 and
 * every mu-plugin. If something ever wants it, it needs its OWN constant: the axis above
 * is "GB's output pipeline reads it", `tag_name` fails that axis, and adding it here
 * would make the axis statement false rather than the set larger.
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
 * `generateblocks_dynamic_tag_output`, where a co-resident extension re-applies them to an
 * output that has already had them applied.
 *
 * THE SCOPE IS THE WHOLE PLUGIN, and the two halves of that rest on different things.
 * That NOTHING bypasses this function is PINNED: §B6 of
 * tools/test/gb-output-boundary-test.php re-reads every .php file in the tree and fails by
 * file and line if a direct call to GB's output method reappears, so the zero is re-checked
 * on every run. HOW MANY route through here is a MEASUREMENT and nothing observes it -- 44
 * call sites, counted over `includes/` on 2026-08-26. Read that number as of its date; a
 * new tag file moves it without failing anything.
 *
 * One caller LAYERS rather than bypasses: bws_safe_content_output() unsets the transforms
 * that corrupt rich HTML and then ends here. Its own PHPDoc says why the two sets can
 * intersect without the composition becoming order-dependent.
 *
 * ROUTING THE 38 THAT WERE STILL DIRECT MOVED NO EXISTING RENDERED OUTPUT, and proving
 * that was the whole point of doing it as its own change: at each of them the value handed
 * to GB is empty-guarded on the line above, or the tag carries no `fallback` at all -- with
 * one class of exception, below.
 *
 * THE EXCEPTION IS A NON-EMPTY FALSY VALUE, and it is why "empty-guarded" above is not the
 * whole story. The measured extension tests `empty( $output )`, not `'' === $output`, so a
 * tag resolving to the bare string `0` while carrying a `fallback` had its zero replaced by
 * the fallback text. Our tags preserve `'0'` on purpose (the falsy guard in
 * includes/hooks.php exists for it), so that was a real defect on every non-image tag. It
 * closes here, and it is pinned rather than left to a reader: text matrix §T5's T5.1b, on
 * /matrix-post-meta/, is a zero-valued read carrying a fallback and renders the zero.
 * Measured 2026-08-26 against GB Query Enhancements 1.3.0.
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
