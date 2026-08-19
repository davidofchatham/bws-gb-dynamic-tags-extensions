<?php
/**
 * run-converter.php — run the tag converter over a whole site, and record what it did.
 *
 * The middle step of Experiment M, between `replay-A` and `replay-C`. See
 * `tools/harvest-replay/README.md` for how this fits the harvest/replay/diff/convert whole
 * and the two experiment baselines.
 *
 * THIS PLUGIN'S CONVERTER DOES NOT RUN BY ITSELF. It is admin-triggered
 * (`wp_ajax_bws_scan_tags` / `wp_ajax_bws_migrate_tags` on the settings page) — there is no
 * version-gated upgrade routine and nothing fires on a page load. Swapping in a newer build
 * and loading a page migrates NOTHING here, which is the opposite of `bws-portal-system`'s
 * behaviour that `dev-plugin.sh`'s header describes. So the migration step is an explicit
 * invocation, and this is it: `scan()` then `migrate_post()` over everything reported, then the
 * pattern-cache reconcile the batch ends with — exactly what the admin button does.
 *
 * IT ALSO EMITS THE OLD → NEW MAPPING, WHICH IS WHAT MAKES A DIFF POSSIBLE AT ALL.
 * Migration rewrites the wire, so `A-render` and `C-render` hold DIFFERENT tag strings and
 * cannot be keyed against each other. The pairing has to come from the converter itself.
 * It is derived here the way `verify-migration.php` derives it — the same two shipped calls
 * `migrate_post()` makes, in that order (tag rename, then the option cascade) — rather than by
 * pairing before/after tag lists by position, which breaks the moment a page already held some
 * migrated wire.
 *
 * That mirroring is a trip-hazard on its own, so it is not trusted blindly: every derived
 * string is checked for presence in the stored content afterwards, and a mismatch is reported
 * loudly rather than written into the mapping.
 *
 * WHAT THE CONVERTER CANNOT REACH IS PART OF THE RESULT. `scan()` is a wp_posts query, so wire
 * living in options, postmeta or termmeta is neither reported nor rewritten and keeps
 * rendering the old way indefinitely. The census already counted that; this records which
 * mapped tags therefore still exist in their OLD form after the run, because a mapping applied
 * blindly would expect them to have changed.
 *
 * Usage (container paths):
 *   wp eval-file /plugins/bws-gb-dynamic-tags-extensions/tools/harvest-replay/run-converter.php \
 *       <census.jsonl> <outdir>
 *
 * Writes <outdir>/mapping.jsonl and <outdir>/convert-report.json.
 *
 * MUTATES CONTENT. Snapshot first (bin/dev-plugin.sh --dev does).
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run via wp-cli eval-file.\n";
	exit( 1 );
}

$census_path = $args[0] ?? '';
$outdir      = $args[1] ?? '';

if ( '' === $census_path || '' === $outdir ) {
	WP_CLI::error( 'Usage: eval-file run-converter.php <census.jsonl> <outdir>' );
}
if ( ! is_readable( $census_path ) ) {
	WP_CLI::error( "Census not readable: {$census_path}" );
}
if ( ! is_dir( $outdir ) ) {
	WP_CLI::error( "No such directory: {$outdir}" );
}

if ( ! class_exists( '\BWS\DynamicTags\Admin\TagConverter' ) ) {
	WP_CLI::error( 'TagConverter not available — is the plugin active and recent enough?' );
}

$version = defined( 'BWS_DYNAMIC_TAGS_VERSION' ) ? BWS_DYNAMIC_TAGS_VERSION : '?';
WP_CLI::log( "plugin version: {$version}" );

// ---------------------------------------------------------------------------
// 1. Derive old → new for every distinct tag string the census found.
// ---------------------------------------------------------------------------
$tags = array();
$fh   = fopen( $census_path, 'r' );
while ( false !== ( $line = fgets( $fh ) ) ) {
	$row = json_decode( trim( $line ), true );
	if ( is_array( $row ) && ! empty( $row['tag_string'] ) ) {
		$tags[ $row['tag_string'] ] = true;
	}
}
fclose( $fh );
$tags = array_keys( $tags );
sort( $tags, SORT_STRING );

$mapping = array();
foreach ( $tags as $tag ) {
	try {
		list( $name )  = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $tag );
		$renamed       = \BWS\DynamicTags\Admin\TagConverter::resolve_full_chain( $name, $tag );
		list( $now )   = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $renamed );
		$new           = \BWS\DynamicTags\MigrationRegistry::apply_option_migration( $now, $renamed );
	} catch ( Throwable $e ) {
		WP_CLI::warning( "derivation threw for {$tag}: " . $e->getMessage() );
		continue;
	}
	if ( $new !== $tag ) {
		$mapping[ $tag ] = $new;
	}
}

WP_CLI::log( sprintf( '%d distinct tag strings, %d of them change', count( $tags ), count( $mapping ) ) );

// ---------------------------------------------------------------------------
// 2. REPORT — what the converter says it will do, before it does it.
// ---------------------------------------------------------------------------
$scan       = \BWS\DynamicTags\Admin\TagConverter::scan();
$reported   = count( $scan );
$reported_tags = array();
foreach ( $scan as $row ) {
	foreach ( $row['deprecated_tags'] as $d ) {
		$reported_tags[ $d['tag'] ] = ( $reported_tags[ $d['tag'] ] ?? 0 ) + 1;
	}
}
WP_CLI::log( sprintf( 'scan reports %d posts, %d distinct deprecated tag NAMES', $reported, count( $reported_tags ) ) );

// ---------------------------------------------------------------------------
// 3. RUN.
// ---------------------------------------------------------------------------
$migrated = 0;
$tag_count = 0;
$failed    = array();

foreach ( $scan as $row ) {
	$id = (int) $row['post_id'];
	try {
		$result = \BWS\DynamicTags\Admin\TagConverter::migrate_post( $id );
	} catch ( Throwable $e ) {
		$failed[] = array( 'post_id' => $id, 'error' => $e->getMessage() );
		continue;
	}
	if ( ! empty( $result['changed'] ) ) {
		$migrated++;
		$tag_count += (int) ( $result['tag_count'] ?? 0 );
	}
}

WP_CLI::log( sprintf( 'migrated %d posts, %d tag rewrites', $migrated, $tag_count ) );

// THE ADMIN BUTTON RECONCILES THE GB PRO PATTERN CACHE; SO MUST THIS.
// migrate_post() writes with $wpdb->update(), so GB Pro rebuilds nothing and a cached copy of the
// pre-migration content survives in generateblocks_patterns_tree postmeta (#98). ajax_migrate
// repairs that after the batch. Without the same call here, the B-side census reports shadow wire
// the real migration route would have removed — which reads exactly like the bug the repair fixed,
// and did on portals on 2026-08-18, four days after that repair shipped.
//
// THE REPAIR IS NOT SEPARABLE FROM THE UPGRADE, AND AN M RUN MUST EXPECT ITS EFFECT. It also runs
// from `bws_dynamic_tags_rebuild_allowlist_on_upgrade`, which fires on the first request after the
// version moves — BEFORE this script gets to say anything. The repair REMOVES stale shadow wire, so
// the B-side census loses rows the A side rendered, and each one lands in the diff as a pair
// present on only one side. Measured on hargrave 2026-08-18: one wp_block whose post_content
// already held modern datetime_range wire kept two PRE-1.6 strings in its cached tree, from a
// migration predating the clone; `bws_dynamic_tags_pattern_cache_status` named the upgrade trigger
// and one reconciled entry. A skip flag was built for this and DELETED — it isolated nothing,
// because by the time this file runs the upgrade path has already repaired the tree.
$pattern_cache = array();
if ( class_exists( '\BWS\DynamicTags\Admin\PatternCache' ) ) {
	$pattern_cache = \BWS\DynamicTags\Admin\PatternCache::reconcile_site( 'migrate' );
	WP_CLI::log( sprintf(
		'pattern cache: %d entries checked, %d reconciled',
		(int) ( $pattern_cache['checked'] ?? 0 ),
		(int) ( $pattern_cache['reconciled'] ?? 0 )
	) );
}

// REPORT/RUN AGREEMENT — a report that outlives its run means the two halves disagree about
// what a migration is.
$rescan    = \BWS\DynamicTags\Admin\TagConverter::scan();
$remaining = 0;
foreach ( $rescan as $row ) {
	if ( ! empty( $row['deprecated_tags'] ) ) {
		$remaining++;
	}
}
if ( $remaining ) {
	WP_CLI::warning( "a second scan still reports {$remaining} post(s) — report and run disagree" );
}

// ---------------------------------------------------------------------------
// 4. Check the derivation against what is actually stored, and record what the converter
//    could not reach. A mapped tag still present in its OLD form is not an error: it is wire
//    living outside wp_posts, and a diff that applied the mapping blindly would expect it to
//    have moved.
// ---------------------------------------------------------------------------
global $wpdb;

$unreached = array();
$unverified = array();
foreach ( $mapping as $old => $new ) {
	$still_old = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts}
		  WHERE post_content LIKE %s AND post_type != 'revision'
		    AND post_status NOT IN ('auto-draft','trash')",
		'%' . $wpdb->esc_like( $old ) . '%'
	) );
	$has_new = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts}
		  WHERE post_content LIKE %s AND post_type != 'revision'
		    AND post_status NOT IN ('auto-draft','trash')",
		'%' . $wpdb->esc_like( $new ) . '%'
	) );

	if ( $still_old ) {
		// Survived in wp_posts despite being mapped: the derivation and the converter's own
		// order have diverged. That is the trip-hazard this check exists for.
		$unverified[] = array( 'old' => $old, 'new' => $new, 'posts_still_old' => $still_old );
	} elseif ( ! $has_new ) {
		// Neither form is in wp_posts — the tag lived only in options/postmeta/termmeta, which
		// the converter cannot reach. Expected, and recorded.
		$unreached[] = array( 'old' => $old, 'new' => $new );
	}
}

if ( $unverified ) {
	WP_CLI::warning( sprintf( '%d mapped tag(s) still present in post_content in their OLD form — derivation may not mirror migrate_post', count( $unverified ) ) );
}

// ---------------------------------------------------------------------------
// 5. Write the artifacts.
// ---------------------------------------------------------------------------
$map_path = rtrim( $outdir, '/' ) . '/mapping.jsonl';
$mh       = fopen( $map_path, 'w' );
foreach ( $mapping as $old => $new ) {
	fwrite( $mh, wp_json_encode( array( 'old' => $old, 'new' => $new ) ) . "\n" );
}
fclose( $mh );

$report = array(
	'converted_at'      => gmdate( 'c' ),
	'plugin_version'    => $version,
	'distinct_tags'     => count( $tags ),
	'mapped_tags'       => count( $mapping ),
	'posts_reported'    => $reported,
	'posts_migrated'    => $migrated,
	'tag_rewrites'      => $tag_count,
	'posts_still_reported_after' => $remaining,
	'migrate_failures'  => $failed,
	'unreached_by_converter' => $unreached,
	'derivation_unverified'  => $unverified,
	'pattern_cache'     => $pattern_cache,
);

file_put_contents(
	rtrim( $outdir, '/' ) . '/convert-report.json',
	wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
);

WP_CLI::log( sprintf(
	'unreached by converter (options/meta only): %d   derivation unverified: %d',
	count( $unreached ),
	count( $unverified )
) );
WP_CLI::log( 'written: ' . $map_path );
