<?php
/**
 * The two developer-only log gates.
 *
 * Neither is a setting: there is no admin control, and an unset filter means off. Turn one on
 * from a site-specific plugin or `functions.php`:
 *
 *     add_filter( 'bws_dynamic_tags_benchmark_logging', '__return_true' );
 *
 * `WP_DEBUG` does not enable either one; the filter is the only gate.
 *
 * `bws_dynamic_tags_show_all_deprecated` is the same kind of switch for the settings page, but
 * its one caller reads it inline (`SettingsPage::render_page()`), so it has no function here.
 * All three are documented in docs/plugin-integration.md §6.
 *
 * NOTHING HERE IS RE-INCLUDE GUARDED, matching gb-output-boundary.php and slot-fold.php.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.22.0
 */

/**
 * Whether post-content processing logs its time and memory to the PHP error log.
 *
 * @since 1.22.0
 * @return bool
 */
function bws_benchmark_logging_enabled(): bool {
	return (bool) apply_filters( 'bws_dynamic_tags_benchmark_logging', false );
}

/**
 * Whether source registration and the `bws_dynamic_tags_register_sources` action log to the
 * PHP error log.
 *
 * @since 1.22.0
 * @return bool
 */
function bws_registration_logging_enabled(): bool {
	return (bool) apply_filters( 'bws_dynamic_tags_registration_logging', false );
}
