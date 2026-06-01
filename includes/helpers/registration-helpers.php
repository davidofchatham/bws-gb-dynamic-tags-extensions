<?php
/**
 * Registration-time helpers.
 *
 * Utilities called during tag option-array construction and GB registration,
 * before any rendering happens. Keep this file scoped to wire-format /
 * registration concerns — not runtime resolution.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Strip default-marked select options' first-entry value to '' before GB registration.
 *
 * Options array entries flagged `_strip_default => true` get their first option's
 * value flipped to '' so the wire format omits the default token (GB drops empty
 * values from the serialized tag string). Internal canonical token (e.g. 'current',
 * 'key', 'content') is preserved in source files for readability; consumers apply
 * `?? '<canonical>'` to restore it at read time.
 *
 * The `_strip_default` marker itself is removed before passing to GB.
 *
 * @since 1.7.0
 * @param array $options Options array as registered in PHP.
 * @return array Options with strip applied.
 */
if ( ! function_exists( 'bws_strip_default_select_values' ) ) {
function bws_strip_default_select_values( array $options ): array {
	foreach ( $options as &$opt ) {
		if ( ! empty( $opt['_strip_default'] ) && isset( $opt['options'][0]['value'] ) ) {
			$opt['options'][0]['value'] = '';
		}
		unset( $opt['_strip_default'] );
	}
	return $options;
}
}
