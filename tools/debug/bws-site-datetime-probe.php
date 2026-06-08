<?php
/**
 * T9 build-time probe — src:site datetime ACF-options read verification.
 *
 * NOT shipped with the plugin. Drop into wp-content/mu-plugins/ on a TEST
 * instance only (per the runtime-debug workflow: instrument + pull to test,
 * never probe a live/cached site). Confirms the two facts T6/DT-1 depend on:
 *
 *   1. get_field( $key, 'option' )         returns the stored value
 *   2. get_field_object( $key, 'option' )  returns a 'return_format'
 *
 * ...for a real ACF options-page date field, evaluated OUTSIDE any loop/admin
 * context (front-end `init`, no queried entity, no repeater row).
 *
 * Setup on the test instance:
 *   1. Create an ACF options page (acf_add_options_page) + a Date Picker field
 *      on it. Note its field key/name and set BWS_PROBE_KEY below (or define
 *      BWS_PROBE_KEY in wp-config.php).
 *   2. Add to wp-config.php:  define( 'BWS_DEBUG_SITE', true );
 *   3. Load any front-end URL once. Read wp-content/debug.log (WP_DEBUG_LOG on).
 *   4. Confirm both lines log non-empty value + a return_format string.
 *   5. ALSO add the key to the allowlist so the gated read path matches render:
 *        add_filter( 'generateblocks_dynamic_tags_allowed_options',
 *            fn( $a ) => array_merge( $a, [ BWS_PROBE_KEY ] ) );
 *      then verify {{datetime_single src:site|key:BWS_PROBE_KEY}} renders on a page.
 *
 * Remove this file when verification is done.
 *
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BWS_DEBUG_SITE' ) || ! BWS_DEBUG_SITE ) {
	return;
}

if ( ! defined( 'BWS_PROBE_KEY' ) ) {
	// Set to your ACF options-page date field's key/name.
	define( 'BWS_PROBE_KEY', 'acf_options_event_date' );
}

add_action(
	'init',
	static function () {
		// Front-end only; skip admin/REST/cron so we mirror a real render context.
		if ( is_admin()
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			|| ( defined( 'DOING_CRON' ) && DOING_CRON )
			|| ( defined( 'WP_CLI' ) && WP_CLI )
		) {
			return;
		}

		if ( ! function_exists( 'get_field' ) || ! function_exists( 'get_field_object' ) ) {
			error_log( '[BWS_SITE_PROBE] ACF not active — get_field/get_field_object unavailable.' );
			return;
		}

		$key = BWS_PROBE_KEY;

		// 1. Value read (the DT-1 / bws_read_field('option') path).
		$value = get_field( $key, 'option' );
		error_log( sprintf(
			'[BWS_SITE_PROBE] get_field(%s, option) => %s',
			$key,
			is_scalar( $value ) ? var_export( $value, true ) : '(' . gettype( $value ) . ') ' . wp_json_encode( $value )
		) );

		// 2. Field-config read (the bws_get_acf_return_format path → format chain tier 2).
		$obj = get_field_object( $key, 'option' );
		if ( is_array( $obj ) ) {
			error_log( sprintf(
				'[BWS_SITE_PROBE] get_field_object(%s, option) return_format => %s ; type => %s',
				$key,
				isset( $obj['return_format'] ) ? var_export( $obj['return_format'], true ) : '(missing)',
				$obj['type'] ?? '(missing)'
			) );
		} else {
			error_log( sprintf(
				'[BWS_SITE_PROBE] get_field_object(%s, option) returned non-array (%s) — field key wrong or not on options store.',
				$key,
				gettype( $obj )
			) );
		}

		// 3. Allowlist-gate parity check — confirms render path (gated) sees the key.
		if ( function_exists( 'bws_site_allowlist_ok' ) ) {
			error_log( sprintf(
				'[BWS_SITE_PROBE] bws_site_allowlist_ok(%s) => %s (false = add it to generateblocks_dynamic_tags_allowed_options or render returns empty)',
				$key,
				bws_site_allowlist_ok( $key ) ? 'true' : 'false'
			) );
		}
	},
	1
);
