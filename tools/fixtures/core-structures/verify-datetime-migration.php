<?php
/**
 * core-structures blueprint — the DATETIME migration, end to end (#90).
 *
 * What no pure harness can reach: the converter reading REAL stored content and rewriting
 * it, and the rewritten wire RENDERING against a real ACF read.
 *
 * ONE PROPERTY IS STRUCTURALLY UNAVAILABLE HERE, unlike verify-migration.php: the pre-1.6
 * datetime renderers were DELETED in the 1.6 consolidation, so the legacy tag cannot be
 * rendered for a byte-identical before/after. The equivalence assertion therefore lives in
 * tools/test/datetime-migration-test.php, which mirrors the deleted `! empty()` read. What
 * this file adds is that the migrated wire, on real fields, actually MOVES the two axes —
 * i.e. the flags the fix injects are doing visible work rather than being inert decoration.
 *
 * SELF-CLEANING, unlike verify-migration.php: it creates its own draft, converts that, and
 * deletes it. The corpus is never touched, so NO RESEED is needed afterwards. It needs no
 * fixture of its own either — it reads `event_midnight` (2030-08-12 00:00:00) and
 * `event_thisyear` (seed-year 04-10) on matrix-post-meta, which the datetime matrix already
 * seeds. Both were chosen because each pins ONE axis: a midnight time and a current year are
 * exactly the two values the flags decide whether to print.
 *
 * The ambient URL is load-bearing. `--url` only sets $_SERVER; wp() below runs the real main
 * query, and without it every read returns EMPTY — indistinguishable, in a green/red count,
 * from a flag that stopped working.
 *
 * Run (after seed.php):
 *   bin/wp.sh <site> eval-file <mounted-repo>/tools/fixtures/core-structures/verify-datetime-migration.php \
 *     --url=https://<site-domain>/matrix-post-meta/
 *
 * Location-independent equivalent (Docker shares one daemon across Windows and WSL):
 *   docker exec wp-litespeed-litespeed-1 sh -c 'cd /var/www/vhosts/testbed/html && \
 *     wp eval-file wp-content/plugins/bws-gb-dynamic-tags-extensions/tools/fixtures/core-structures/verify-datetime-migration.php \
 *     --url=https://testbed.test/matrix-post-meta/ --allow-root'
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$pass = 0;
$fail = 0;

$check = static function ( string $label, $expected, $actual ) use ( &$pass, &$fail ) {
	if ( $expected === $actual ) {
		$pass++;
		WP_CLI::log( "  ok   $label" );
		return;
	}
	$fail++;
	WP_CLI::log( "  FAIL $label" );
	WP_CLI::log( '       expected: ' . var_export( $expected, true ) );
	WP_CLI::log( '       actual:   ' . var_export( $actual, true ) );
};

// The shipped converter always runs as an authenticated administrator, and WP-CLI runs as
// nobody, so this drives migrate_post() in a different capability context unless told
// otherwise (#99). It matters here for the same reason it matters everywhere else: a hook
// gated on a capability behaves differently, and the difference is silent. See
// lib-admin-context.php for the measurement and for why replay-tags.php is excluded.
require_once __DIR__ . '/lib-admin-context.php';
bws_fixture_assume_administrator( static function ( $m ) { WP_CLI::log( '  ' . $m ); } );

// --url only sets $_SERVER; the main query has to be run for ambient context to be real,
// exactly as the render-tag command does it. Without this every read returns EMPTY, which
// is indistinguishable from a broken flag in the assertions below.
wp();

// Same call the render-tag CLI command makes (tools/cli/class-render-tag-command.php:107).
// An UNRENDERED tag string comes back verbatim rather than erroring, so every assertion
// below compares against an EXPECTED VALUE — comparing two renders to each other passes
// whenever the two tag strings differ, which is a green that proves nothing.
$render = static function ( string $tag ): string {
	$instance          = new stdClass();
	$instance->context = array();
	return trim( (string) \GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, array(), $instance ) );
};

// ---------------------------------------------------------------------------
WP_CLI::log( "\nDM1 — the converter rewrites REAL stored content (report + run agree)" );

// Both migration doors, on one post: a pre-1.6 TAG name (era by construction) and a modern
// tag carrying legacy OPTION keys (era proved by datetime_era_options).
$legacy = <<<'HTML'
<!-- wp:paragraph --><p>{{post_custom_datetime_single date_time_field:event_midnight}}</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>{{datetime_single date_time_field:event_thisyear}}</p><!-- /wp:paragraph -->
HTML;

$post_id = wp_insert_post(
	array(
		'post_title'   => 'bws-verify datetime migration probe',
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_content' => $legacy,
	)
);

if ( ! $post_id || is_wp_error( $post_id ) ) {
	WP_CLI::error( 'could not create the probe post' );
}

$scan     = \BWS\DynamicTags\Admin\TagConverter::scan();
$reported = false;
foreach ( $scan as $row ) {
	if ( (int) ( $row['post_id'] ?? $row['ID'] ?? 0 ) === (int) $post_id ) {
		$reported = true;
	}
}
$check( 'DM1.1 scan reports the probe post', true, $reported );

\BWS\DynamicTags\Admin\TagConverter::migrate_post( $post_id );
$after = get_post( $post_id )->post_content;

// The two inverted booleans are ABSENT in the wire above — an author who never touched the
// boxes. Pre-1.6 that rendered midnight SHOWN and year SHOWN, so both flags must appear.
$check(
	'DM1.2 tag door: absent booleans → both flags injected',
	true,
	false !== strpos( $after, '{{datetime_single showCurrentYear|showMidnight|key:event_midnight}}' )
);
$check(
	'DM1.3 option door: same, and the field key is renamed',
	true,
	false !== strpos( $after, '{{datetime_single showCurrentYear|showMidnight|key:event_thisyear}}' )
);

$rescan   = \BWS\DynamicTags\Admin\TagConverter::scan();
$still    = false;
foreach ( $rescan as $row ) {
	if ( (int) ( $row['post_id'] ?? $row['ID'] ?? 0 ) === (int) $post_id ) {
		$still = true;
	}
}
$check( 'DM1.4 nothing left to report after the run', false, $still );

wp_delete_post( $post_id, true );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nDM2 — the injected flags MOVE the rendered axes (real ACF reads)" );

// event_midnight is 2030-08-12 00:00:00; event_thisyear is 04-10 of the seed-time year.
$year = gmdate( 'Y' );

$check( 'DM2.1 showMidnight shows the midnight time',
	'August 12, 2030 12:00 AM', $render( '{{datetime_single key:event_midnight|showMidnight}}' ) );
$check( 'DM2.2 bare hides it',
	'August 12, 2030', $render( '{{datetime_single key:event_midnight}}' ) );

$check( 'DM2.3 showCurrentYear shows the current year',
	"April 10, $year", $render( '{{datetime_single key:event_thisyear|showCurrentYear}}' ) );
$check( 'DM2.4 bare hides it',
	'April 10', $render( '{{datetime_single key:event_thisyear}}' ) );

// THE REGRESSION, IN RENDERED FORM. Both tags are what a converter produced from the SAME
// legacy wire (`date_time_field:event_midnight`, both boxes untouched): the shipped one
// injected nothing, this branch injects both flags. The pre-1.6 renderer showed midnight
// and showed the year, so the second line is the output that moved.
$check( 'DM2.5 this branch renders what the pre-1.6 tag rendered',
	'August 12, 2030 12:00 AM', $render( '{{datetime_single showCurrentYear|showMidnight|key:event_midnight}}' ) );
$check( 'DM2.6 the shipped converter rendered something else',
	'August 12, 2030', $render( '{{datetime_single key:event_midnight}}' ) );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nDM3 — output gate: the entry's `as` wins, and the flag is not emitted" );

$post_id = wp_insert_post(
	array(
		'post_title'   => 'bws-verify datetime gate probe',
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_content' => '<!-- wp:paragraph --><p>{{post_custom_date_single date_time_field:event_midnight|time_only}}</p><!-- /wp:paragraph -->',
	)
);
\BWS\DynamicTags\Admin\TagConverter::migrate_post( $post_id );
$after = get_post( $post_id )->post_content;
$check(
	'DM3.1 date entry + time_only wire: as:date, no showMidnight',
	true,
	false !== strpos( $after, '{{datetime_single as:date|showCurrentYear|key:event_midnight}}' )
);
wp_delete_post( $post_id, true );

// ---------------------------------------------------------------------------
if ( $fail ) {
	WP_CLI::error( sprintf( 'FAILED — %d of %d', $fail, $pass + $fail ) );
}
WP_CLI::success( sprintf( 'PASSED — %d assertions', $pass ) );
