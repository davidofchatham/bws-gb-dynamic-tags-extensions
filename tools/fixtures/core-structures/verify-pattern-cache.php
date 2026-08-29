<?php
/**
 * core-structures blueprint — the GB Pro pattern-cache reconcile, end to end (#99).
 *
 * THIS SEAM IS NOT OPTIONAL AND IS NOT SUBSTITUTABLE BY THE PURE HARNESS. The escaping
 * hazard the reconcile has to defend against lives inside WordPress's meta write:
 * update_post_meta() calls wp_unslash(), which strips slashes RECURSIVELY through arrays,
 * so a read-modify-write that does not slash the whole structure back strips one level of
 * backslashes from every string in the entry on every pass — silent, cumulative, and
 * invisible until a preview containing escaped content breaks. tools/test/pattern-cache-test.php
 * owns the decision logic and structurally cannot observe any of that.
 *
 * SELF-CLEANING, like verify-datetime-migration.php: the destructive work happens on a
 * throwaway wp_block this file creates and deletes, so NO RESEED is needed afterwards. The
 * SEEDED fixture (bws-fixture-legacy-wire) is only read here — it is the browsable
 * demonstration and the proof that seed.php ran with an administrator.
 *
 * WHY THE THROWAWAY IS BUILT BY GB PRO RATHER THAN HAND-WRITTEN: a hand-written entry
 * encodes our belief about GB Pro's array shape, and makes §PC3's headline assertion
 * near-vacuous. "Every non-content field byte-identical" only bites when those fields hold
 * real bytes — a stub preview survives a slash-stripping bug that a real rendered one does
 * not. So this file inserts a post, lets after_save build the tree, and then uses the
 * SHIPPED converter to create the divergence.
 *
 * ADMINISTRATOR CONTEXT IS A PREREQUISITE, not a nicety. GB Pro's listener bails on
 * ! current_user_can( 'edit_post' ), and WP-CLI has no current user, so without --user this
 * file's fixtures get no cache entry at all and every assertion below fails in a way that
 * reads like a code regression. §PC0 refuses to continue rather than let that happen.
 *
 * Run (after seed.php):
 *   bin/wp.sh <site> eval-file <mounted-repo>/tools/fixtures/core-structures/verify-pattern-cache.php --user=1
 *
 * Location-independent equivalent (Docker shares one daemon across Windows and WSL):
 *   docker exec wp-litespeed-litespeed-1 sh -c 'cd /var/www/vhosts/testbed/html && \
 *     wp eval-file wp-content/plugins/bws-gb-dynamic-tags-extensions/tools/fixtures/core-structures/verify-pattern-cache.php \
 *     --user=1 --allow-root'
 *
 * No --url is needed: nothing here renders a tag, so there is no ambient context to be
 * wrong about. The reconcile is deliberately context-free — that is the property that made
 * it preferable to rebuilding the entry.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

use BWS\DynamicTags\Admin\PatternCache;
use BWS\DynamicTags\Admin\TagConverter;

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

/** Read the raw meta ROWS (not the first one) — duplicate rows are a case under test. */
$rows = static function ( int $post_id ): array {
	return (array) get_post_meta( $post_id, PatternCache::META_KEY, false );
};

/** The cache entry for a pattern, or null. */
$entry_of = static function ( int $post_id ) {
	$tree = get_post_meta( $post_id, PatternCache::META_KEY, true );
	if ( ! is_array( $tree ) ) {
		return null;
	}
	foreach ( $tree as $entry ) {
		if ( is_array( $entry ) && ( $entry['id'] ?? null ) === PatternCache::entry_id( $post_id ) ) {
			return $entry;
		}
	}
	return null;
};

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC0 — preconditions (a wrong one here makes every later FAIL a lie)" );

if ( ! post_type_exists( 'wp_block' ) ) {
	WP_CLI::error( 'wp_block post type is not registered — nothing to verify.' );
}
if ( ! class_exists( 'GenerateBlocks_Pro_Pattern_Library' ) ) {
	WP_CLI::error( 'GenerateBlocks Pro pattern library is not active — this file has no subject.' );
}
// SET the administrator rather than merely demanding one, so this file matches the shipped
// path without the caller having to remember --user. It still refuses outright when the site
// has no administrator at all: GB Pro's cache listener is capability-gated, so a
// capability-less run builds NO cache entry and every assertion below would fail for the
// wrong reason.
require_once __DIR__ . '/lib-admin-context.php';
if ( ! bws_fixture_assume_administrator( static function ( $m ) { WP_CLI::log( '  ' . $m ); } ) ) {
	WP_CLI::error( 'No administrator on this site — cannot exercise the shipped capability context.' );
}

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC1 — the SEEDED fixture (proves seed.php ran as an administrator)" );

$seeded = get_posts(
	array(
		'name'        => 'bws-fixture-legacy-wire',
		'post_type'   => 'wp_block',
		'post_status' => 'any',
		'numberposts' => 1,
	)
);
$seeded_id = $seeded ? (int) $seeded[0]->ID : 0;

$check( 'PC1.1 the seeded pattern exists', true, $seeded_id > 0 );

if ( $seeded_id ) {
	// If this fails, seed.php ran with no current user: the listener bailed and the wp_block
	// has no row at all. That is the exact failure the administrator context exists to stop.
	$check( 'PC1.2 …and GB Pro built a cache entry for it', true, null !== $entry_of( $seeded_id ) );

	// GUARD ON THE CORPUS ITSELF. Everything below rests on the meta layer having
	// backslashes to strip; a later edit that flattens the fixture would leave the escaping
	// assertions green while testing nothing.
	$seeded_content = get_post( $seeded_id )->post_content;
	$check( 'PC1.3 the fixture carries literal backslashes (else the escaping cases are vacuous)',
		true, false !== strpos( $seeded_content, '\\' ) );
}

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC2 — the DEFECT, reproduced: a direct write leaves the cache behind" );

// Its own throwaway, so the seeded fixture keeps its pre-migration wire for browsing and
// this file stays repeatable without a reseed.
$content = <<<'HTML'
<!-- wp:paragraph {"metadata":{"name":"He said \"hello\""}} -->
<p>{{post_custom_datetime_single date_time_field:event_midnight}}</p>
<!-- /wp:paragraph -->

<!-- wp:code -->
<pre class="wp-block-code"><code>preg_match( '/^\d+\s\w+/', $subject )</code></pre>
<!-- /wp:code -->
HTML;

$probe_id = wp_insert_post(
	array(
		'post_type'    => 'wp_block',
		'post_title'   => 'bws-verify pattern-cache probe',
		'post_status'  => 'publish',
		'post_content' => wp_slash( $content ), // wp_insert_post expects slashed data.
	)
);

if ( ! $probe_id || is_wp_error( $probe_id ) ) {
	WP_CLI::error( 'could not create the probe pattern' );
}

$probe_id = (int) $probe_id;
$check( 'PC2.1 the probe content round-tripped byte-identically',
	$content, get_post( $probe_id )->post_content );

$original = $entry_of( $probe_id );
$check( 'PC2.2 GB Pro built a real cache entry for it', true, null !== $original );

if ( null === $original ) {
	wp_delete_post( $probe_id, true );
	WP_CLI::error( 'no cache entry to verify against — aborting rather than reporting vacuous passes' );
}

// The stored entry equals post_content EXACTLY: GB Pro writes wp_slash( $post_content ) and
// update_post_meta() unslashes it back. This is the measurement the raw-byte-compare
// agreement rule rests on — if it ever stops holding, reconcile_tree()'s `===` is wrong.
$check( 'PC2.3 GB Pro\'s stored copy is byte-identical to post_content (the basis of the `===` rule)',
	$content, $original['pattern'] );

// Now the defect. migrate_post() writes straight to the posts table and fires no save
// hooks, so the cache is never told.
$result = TagConverter::migrate_post( $probe_id );
$check( 'PC2.4 the converter rewrote the post content', true, (bool) $result['changed'] );

$migrated = get_post( $probe_id )->post_content;
$check( 'PC2.5 …and the cached copy still holds the PRE-migration wire — the bug, in one line',
	$content, $entry_of( $probe_id )['pattern'] );
$check( 'PC2.6 …i.e. cache and content now disagree', false, $migrated === $content );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC3 — the reconcile repairs it, and touches exactly one field" );

$run = PatternCache::reconcile_site( 'scan' );

$check( 'PC3.1 the run reports at least the probe as reconciled', true, $run['reconciled'] >= 1 );
// `checked` is the denominator that makes a zero falsifiable. The seeded fixture and the
// probe are both cached patterns, so it can never legitimately be below 2 here.
$check( 'PC3.2 …and `checked` counts every cached pattern, not just the repaired ones',
	true, $run['checked'] >= 2 );

$after = $entry_of( $probe_id );
$check( 'PC3.3 the cached content now equals post content BYTE FOR BYTE',
	$migrated, $after['pattern'] );

// PC3.3 IS THE ASSERTION THAT FAILS ON A NAIVE IMPLEMENTATION, and PC3.9 below is why it
// is that one rather than the field-preservation rows. Drop the wp_slash() on write and the
// meta layer's recursive unslash eats one level of backslashes out of the value just
// assigned from post_content, so the cached content stops matching it.
$before_rest = $original;
$after_rest  = $after;
unset( $before_rest['pattern'], $after_rest['pattern'] );
$check( 'PC3.4 EVERY other field is byte-identical (preview, scripts, styles, categories, …)',
	$before_rest, $after_rest );

// Named separately because these are the user-visible ones: US 9 (previews not degraded)
// and US 10 (accordion/tab/menu/form patterns keep their behaviour in the inserter).
$check( 'PC3.5 the preview specifically is untouched', $original['preview'], $after['preview'] );
$check( 'PC3.6 the script list is untouched', $original['scripts'], $after['scripts'] );
$check( 'PC3.7 the style list is untouched', $original['styles'], $after['styles'] );

// The field the escaping assertion actually rests on. A corpus without a backslash here
// would leave PC3.3 green under a naive implementation.
$check( 'PC3.8 the cached CONTENT carries a backslash (else PC3.3 is vacuous)',
	true, false !== strpos( (string) $after['pattern'], '\\' ) );

// A GB PRO FACT, MEASURED, AND NOT WHAT AN EARLIER DRAFT OF THIS FILE ASSUMED. GB Pro
// slashes ONLY the content field before update_post_meta(), so the platform's recursive
// unslash strips a level from every OTHER string on GB Pro's own write. The stored preview
// therefore cannot carry a backslash: this fixture renders `/^\d+\s\w+/` and the entry
// holds `/^d+sw+/`.
//
// The consequence is worth stating rather than leaving implicit: PC3.4/PC3.5 are real
// preservation assertions, but they CANNOT catch a missing wp_slash() — there is nothing
// left in those fields for a second unslash to remove. PC3.3 is the row that catches it,
// and PC3.8 is what keeps PC3.3 honest. Pin the fact so a future GB Pro change (slashing
// the whole tree, as this plugin does) shows up here as a failure to re-reason about
// rather than as silently weaker coverage.
$check( 'PC3.9 GB Pro stores non-content fields ALREADY unslashed (so PC3.4/3.5 cannot see the hazard)',
	false, false !== strpos( (string) $original['preview'], '\\' ) );

// WHICH STRINGS LEFT, not how many. A migration replay reads every string this repair clears
// as a (url, tag) pair present on only one side, and only the strings themselves tell that
// apart from a genuine disappearance — `diff-replays.php --removed=` is what consumes them.
// Counting was the shape that could not.
//
// ASSERTED AS A PROPERTY, NOT AGAINST AN EXPECTED LIST. Building the expected list here would
// mean either calling cleared_wire() to check cleared_wire(), or keeping a second copy of its
// regex in this file — the drift the extraction removed. What holds instead is that every
// reported string was in the pre-migration content and is not in the migrated content, which
// is the whole claim the artifact makes, plus a non-vacuity check so an empty list cannot
// satisfy it.
$probe_cleared = array();
foreach ( (array) ( $run['cleared'] ?? array() ) as $row ) {
	if ( (int) ( $row['post_id'] ?? 0 ) === $probe_id ) {
		$probe_cleared[] = (string) $row['tag_string'];
	}
}

$check( 'PC3.10 the run reports WHICH wire it cleared from the probe, not merely that it did',
	true, count( $probe_cleared ) > 0 );

$not_in_before = array_values( array_filter( $probe_cleared, static fn( $t ) => false === strpos( $content, $t ) ) );
$still_present = array_values( array_filter( $probe_cleared, static fn( $t ) => false !== strpos( $migrated, $t ) ) );

$check( 'PC3.11 every string it names was in the PRE-migration cached copy', array(), $not_in_before );
$check( 'PC3.12 …and none of them survives in the migrated content — they really did stop existing',
	array(), $still_present );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC4 — idempotence: a second pass writes nothing at all" );

// Counted at the meta layer rather than inferred from a returned zero: "reported no work"
// and "did no work" are different claims, and the one that matters for US 16 is the second.
$writes = 0;
$spy    = static function ( $value, $object_id, $meta_key ) use ( &$writes, $probe_id ) {
	if ( (int) $object_id === $probe_id && PatternCache::META_KEY === $meta_key ) {
		$writes++;
	}
	return $value;
};
add_filter( 'update_post_metadata', $spy, 10, 3 );

$second = PatternCache::reconcile_post( $probe_id, $migrated );

remove_filter( 'update_post_metadata', $spy, 10 );

$check( 'PC4.1 the second pass reports nothing to do', false, $second );
$check( 'PC4.2 …and made no meta write whatsoever', 0, $writes );

$after2 = $entry_of( $probe_id );
$check( 'PC4.3 the entry is byte-identical to the reconciled one (no cumulative stripping)',
	$after, $after2 );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC5 — duplicate meta rows converge onto one value" );

// Real shape from a production clone: 16 patterns carrying three divergent copies each.
// update_post_meta() has no previous-value constraint, so it writes every row — an accepted
// side effect rather than a goal, and the reason a run can write when nothing "changed".
$stale_tree               = $after;
$stale_tree['pattern']    = $content; // the pre-migration wire, again
add_post_meta( $probe_id, PatternCache::META_KEY, wp_slash( array( $stale_tree ) ) );

$check( 'PC5.1 the probe now carries two divergent rows', 2, count( $rows( $probe_id ) ) );

$converged = PatternCache::reconcile_post( $probe_id, $migrated );
$check( 'PC5.2 the reconcile reports a write', true, $converged );

$all = $rows( $probe_id );
$check( 'PC5.3 both rows survive (de-duplication is explicitly NOT the goal)', 2, count( $all ) );
$check( 'PC5.4 …and now hold the same value', true, $all[0] === $all[1] );
$check( 'PC5.5 …which is the reconciled one', $migrated, $all[0][0]['pattern'] );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC6 — an unrecognised shape is left alone, never repaired" );

// "Can never make a bad cache worse." A tree with no entry for this post is another
// plugin's data as far as the reconcile is concerned.
delete_post_meta( $probe_id, PatternCache::META_KEY );
$foreign = array( array( 'id' => 'pattern-999999', 'pattern' => 'not ours' ) );
add_post_meta( $probe_id, PatternCache::META_KEY, wp_slash( $foreign ) );

$writes = 0;
add_filter( 'update_post_metadata', $spy, 10, 3 );
$touched = PatternCache::reconcile_post( $probe_id, $migrated );
remove_filter( 'update_post_metadata', $spy, 10 );

$check( 'PC6.1 reports no work', false, $touched );
$check( 'PC6.2 made no write', 0, $writes );
$check( 'PC6.3 the foreign value is byte-identical', $foreign, get_post_meta( $probe_id, PatternCache::META_KEY, true ) );

// THE CASE THE tree_has_entry() WRITE GUARD ACTUALLY EXISTS FOR, and it is NOT the three
// rows above: with a single row, "nothing changed and no copies differ" already returns
// early, so the guard is unreachable and removing it passes every assertion. Confirmed by
// mutation — deleting the guard left PC6.1-6.3 green.
//
// The guard is reached only when the DUPLICATE-ROW convergence wants to write despite
// nothing having changed. Two divergent rows of a shape we do not recognise is exactly
// that: converging them would spread one guess across another plugin's rows, and would
// destroy whichever copy the guess did not come from.
delete_post_meta( $probe_id, PatternCache::META_KEY );
$foreign_a = array( array( 'id' => 'pattern-999999', 'pattern' => 'copy A' ) );
$foreign_b = array( array( 'id' => 'pattern-999999', 'pattern' => 'copy B' ) );
add_post_meta( $probe_id, PatternCache::META_KEY, wp_slash( $foreign_a ) );
add_post_meta( $probe_id, PatternCache::META_KEY, wp_slash( $foreign_b ) );

$writes = 0;
add_filter( 'update_post_metadata', $spy, 10, 3 );
$touched = PatternCache::reconcile_post( $probe_id, $migrated );
remove_filter( 'update_post_metadata', $spy, 10 );

$divergent = $rows( $probe_id );
$check( 'PC6.4 two divergent UNRECOGNISED rows: reports no work', false, $touched );
$check( 'PC6.5 …makes no write, so convergence never reaches an unknown shape', 0, $writes );
$check( 'PC6.6 …and both distinct values survive', array( $foreign_a, $foreign_b ), $divergent );

// ROW 0 IS WHAT DECIDES, and this pins it rather than leaving it to be rediscovered. A
// well-formed entry sitting behind an unrecognised row 0 is skipped whole. That is
// deliberate: get_post_meta( …, true ) returns the first row, so the first row is what GB
// Pro's REST layer actually reads, and promoting a row nobody reads over the one everybody
// does would repair the wrong copy. The skip is not permanent — it resolves as soon as the
// unreadable row goes.
delete_post_meta( $probe_id, PatternCache::META_KEY );
$stale_ours          = $after;
$stale_ours['pattern'] = $content; // ours, well-formed, and diverged
add_post_meta( $probe_id, PatternCache::META_KEY, wp_slash( $foreign_a ) );  // row 0: unrecognised
add_post_meta( $probe_id, PatternCache::META_KEY, wp_slash( array( $stale_ours ) ) ); // row 1: ours

$writes = 0;
add_filter( 'update_post_metadata', $spy, 10, 3 );
$touched = PatternCache::reconcile_post( $probe_id, $migrated );
remove_filter( 'update_post_metadata', $spy, 10 );

$check( 'PC6.7 an unrecognised row 0 skips the pattern, even with a good row behind it', false, $touched );
$check( 'PC6.8 …and writes nothing, so the unreadable row is not overwritten either', 0, $writes );
$check( 'PC6.9 …the row a reader actually gets is unchanged', $foreign_a,
	get_post_meta( $probe_id, PatternCache::META_KEY, true ) );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC7 — the persisted summary (US 5's falsifiable zero)" );

$run    = PatternCache::reconcile_site( 'migrate' );
$status = PatternCache::get_status();

$check( 'PC7.1 checked was recorded', $run['checked'], $status['checked'] ?? null );
$check( 'PC7.2 reconciled was recorded', $run['reconciled'], $status['reconciled'] ?? null );
// STORED but never rendered — retroactivity, not display. The upgrade trigger fires once
// per version change and cannot be replayed, so these two fields are the only way to answer
// "did that path ever actually run" after the fact.
$check( 'PC7.3 the trigger slug was recorded', 'migrate', $status['trigger'] ?? null );
$check( 'PC7.4 the version was recorded', BWS_DYNAMIC_TAGS_VERSION, $status['version'] ?? null );

$line = PatternCache::format_status( $status );
$check( 'PC7.5 the rendered line is non-empty', true, '' !== $line );
$check( 'PC7.6 …and leaks neither slug', false,
	false !== strpos( $line, 'migrate' ) || false !== strpos( $line, BWS_DYNAMIC_TAGS_VERSION ) );

WP_CLI::log( '       line: ' . $line );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC8 — the integration action carries the FRESH content, not the stale post" );

$seen = array();
$listener = static function ( $post_id, $new_content ) use ( &$seen ) {
	$seen[] = array(
		'post_id'      => $post_id,
		'content'      => $new_content,
		// The trap the signature exists to close: at this point the WP_Post the converter
		// holds is the PRE-migration one and clean_post_cache() has already run, so a
		// listener reaching for the obvious $post->post_content gets the stale wire.
		're_read'      => get_post( $post_id )->post_content,
	);
};
add_action( 'bws_dynamic_tags_content_written', $listener, 10, 2 );

$probe2_id = (int) wp_insert_post(
	array(
		'post_type'    => 'wp_block',
		'post_title'   => 'bws-verify content-written probe',
		'post_status'  => 'publish',
		'post_content' => wp_slash( $content ),
	)
);
$pre = get_post( $probe2_id )->post_content;
TagConverter::migrate_post( $probe2_id );

remove_action( 'bws_dynamic_tags_content_written', $listener, 10 );

$check( 'PC8.1 the action fired exactly once', 1, count( $seen ) );
if ( $seen ) {
	$check( 'PC8.2 it carries the post ID as an int', $probe2_id, $seen[0]['post_id'] );
	$check( 'PC8.3 …and the NEW content, not the pre-migration wire', false, $seen[0]['content'] === $pre );
	$check( 'PC8.4 …which matches what a fresh read returns', $seen[0]['re_read'], $seen[0]['content'] );
}

// ---------------------------------------------------------------------------
// Cleanup. Both probes are throwaway; the seeded fixture was only read. Done here rather
// than at the end because §PC9 needs a site with nothing left to repair.
wp_delete_post( $probe_id, true );
wp_delete_post( $probe2_id, true );

// ---------------------------------------------------------------------------
WP_CLI::log( "\nPC9 — a ZERO-count run is recorded too (this bug, in miniature)" );

// Writing only on non-zero would make absence ambiguous again: a site that has never run
// and a site whose last run found nothing would look identical, which is precisely the
// "correct behaviour that cannot be falsified from the interface" property that let the
// original defect hide. Both probes are gone, so this run repairs nothing.
$quiet = PatternCache::reconcile_site( 'upgrade' );
$check( 'PC9.1 the run genuinely found nothing to repair', 0, $quiet['reconciled'] );
$check( 'PC9.2 …but still checked the seeded pattern', true, $quiet['checked'] >= 1 );

$quiet_status = PatternCache::get_status();
$check( 'PC9.3 the zero-count run overwrote the stored summary', 'upgrade', $quiet_status['trigger'] ?? null );
$check( 'PC9.4 …with its own numbers, not the previous run\'s', 0, $quiet_status['reconciled'] ?? null );
$check( 'PC9.5 …and the line still states the denominator', true,
	false !== strpos( PatternCache::format_status( $quiet_status ), 'all current' ) );

WP_CLI::log( '       line: ' . PatternCache::format_status( $quiet_status ) );

if ( $fail ) {
	WP_CLI::error( sprintf( 'FAILED — %d of %d', $fail, $pass + $fail ) );
}
WP_CLI::success( sprintf( 'PASSED — %d assertions', $pass ) );
