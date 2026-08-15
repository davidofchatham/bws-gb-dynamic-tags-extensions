<?php
/**
 * core-structures blueprint — administrator context for CLI instruments (#99).
 *
 * WP-CLI RUNS WITH NO CURRENT USER, while the shipped Tag Converter always runs as an
 * authenticated administrator. Any instrument that drives converter code from the CLI is
 * therefore exercising a different capability context from the one that ships, and every
 * hook gated on a capability behaves differently inside it.
 *
 * That is not a fidelity nicety. Measured on the testbed 2026-08-14, creating a `wp_block`
 * with identical content:
 *
 *   no user   → NO generateblocks_patterns_tree row whatever
 *   --user=1  → full entry (id, label, pattern, preview, scripts, styles, categories, …)
 *
 * because GenerateBlocks Pro's cache listener bails on ! current_user_can( 'edit_post' ).
 * A capability-less seed produces a pattern with no cache entry at all, and the pattern-cache
 * verification then fails in a way that reads like a code regression.
 *
 * PERTURBATION MEASURED AND CLEARED before adopting this. Across a no-user reseed and two
 * administrator reseeds: row counts identical (posts 253 / revisions 143 / postmeta 1101 /
 * options 467), wp_postmeta MD5 byte-identical, and verify.php clean against baseline.
 * Coverage was posts, postmeta and options — not termmeta, usermeta or transients. These
 * files only ever run on the testbed, so that is conclusive for the question actually at
 * issue; the 33-42 non-core save listeners measured on the production clones are not in play.
 *
 * NOT FOR tools/replay-tags.php, deliberately. That instrument replays RENDERS as a
 * front-end visitor sees them, and a visitor is anonymous — setting an administrator there
 * would make it measure something nobody experiences. The rule is "match the shipped path",
 * not "always be an administrator", and the two instruments have different shipped paths.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.17.0
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! function_exists( 'bws_fixture_assume_administrator' ) ) {
	/**
	 * Set the current user to an administrator, unless one is already set.
	 *
	 * Respects an explicit `--user=` on the command line rather than overriding it: a caller
	 * that named a user meant that user.
	 *
	 * @since 1.17.0
	 * @param callable|null $log Optional logger taking one string.
	 * @return int The user ID now in effect, or 0 if no administrator exists.
	 */
	function bws_fixture_assume_administrator( ?callable $log = null ): int {
		$say = static function ( string $msg ) use ( $log ) {
			if ( $log ) {
				$log( $msg );
			}
		};

		$current = get_current_user_id();
		if ( $current && user_can( $current, 'manage_options' ) ) {
			$say( 'running as user #' . $current . ' (already set)' );
			return $current;
		}

		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);

		if ( empty( $admins ) ) {
			// Degrade LOUDLY. A silent capability-less run is the failure mode this exists to
			// prevent, and it presents as missing fixture state rather than as a bad context.
			$say( 'WARNING: no administrator found — capability-gated listeners (GB Pro pattern cache) will NOT fire' );
			return 0;
		}

		wp_set_current_user( (int) $admins[0] );
		$say( 'running as administrator #' . (int) $admins[0] . ' (capability-gated listeners fire)' );

		return (int) $admins[0];
	}
}
