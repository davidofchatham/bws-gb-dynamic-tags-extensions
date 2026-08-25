<?php
/**
 * GB constraint workaround filters.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.7.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevent GB's falsy-replacement block-kill for two legitimate cases:
 *
 * 1. as:alt with no alt text — return ' ' (space); semantically equivalent to
 *    empty alt, truthy to GB. Only safe for HTML attribute contexts.
 *
 * 2. Text field value of '0' — return '0 ' (trailing space); collapses in HTML
 *    rendering, truthy to GB. Not safe for URL/attribute value contexts, but
 *    text tags are HTML body content only.
 *
 * GB checks `! $replacement` after calling the tag callback (class-register-dynamic-tag.php).
 * Both '' and '0' are falsy in PHP, so GB kills the block even when the callback
 * returned a real value. The generateblocks_dynamic_tag_replacement filter fires
 * between the callback return and the required check — the only available hook.
 *
 * THE RULE, AND THIS IS THE ONLY SITE THAT STATES IT: the filter rewrites the
 * replacement of ANY tag rendered on the site that returns a bare '0', or that returns
 * '' while carrying as:alt — ours, GenerateBlocks' own, GB Pro's, a third party's. It
 * carries no tag-name condition and is not getting one. What it defeats is GB's own
 * block-kill, which applies to every registered tag equally; a zero that survives on one
 * tag and takes the block down on the next is a worse contract than either uniform
 * answer, and narrowing this to our own tags would take working first-party output off
 * pages that render it today (see the enumeration below). docs/gb-constraints.md,
 * docs/tag-reference.md and README.md describe what a reader observes and point here for
 * the rule; the reasoning lives here and nowhere else.
 *
 * OBSERVATION, NOT AN INVARIANT — measured 2026-08-24 against GenerateBlocks 2.4.1 and
 * GB Pro 2.7.0. Tags NOT ours seen returning a bare '0', i.e. reaching this filter:
 *
 *   {{loop_index zeroBased:1}}   row 1 of any loop        GB Pro    PINNED, text matrix T5.4
 *   {{comments_count none:0}}    a post with 0 comments   GB core   PINNED, text matrix T5.2
 *   {{loop_item key:<key>}}      a row value of 0         GB Pro    PINNED, text matrix T5.5
 *   {{term_count}}               an empty term            —         PINNED, loop matrix QL3.2
 *
 * Four measured, four pinned. The list is what was looked at on that date, not what
 * exists — a tag added tomorrow can join it with nothing here changing — and the rule
 * above does not rest on the list.
 */
add_filter( 'generateblocks_dynamic_tag_replacement', function ( $replacement, $context ) {
	$options = $context['options'] ?? [];

	if ( '' === $replacement && ( $options['as'] ?? '' ) === 'alt' ) {
		return ' ';
	}

	if ( '0' === $replacement ) {
		return '0 ';
	}

	return $replacement;
}, 10, 2 );
