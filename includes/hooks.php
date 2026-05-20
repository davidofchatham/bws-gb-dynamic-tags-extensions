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
 * The conditions are self-scoping: no GB-native tag uses as:alt or returns bare '0'.
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
