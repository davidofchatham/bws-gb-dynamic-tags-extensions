<?php
/**
 * run-converter.php — run the tag converter over a whole site, and record what it did.
 *
 * The middle step of the MIGRATION REPLAY, between `replay-A` and `replay-C`. See
 * `tools/harvest-replay/README.md` for how this fits the harvest/replay/diff/convert whole
 * and the replay baselines.
 *
 * THIS PLUGIN'S CONVERTER DOES NOT RUN BY ITSELF. It is admin-triggered
 * (`wp_ajax_bws_scan_tags` / `wp_ajax_bws_migrate_tags` on the settings page) — there is no
 * version-gated upgrade routine and nothing fires on a page load. Swapping in a newer build
 * and loading a page migrates NOTHING here, which is the opposite of the integrating plugin's
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
 * THE DERIVATION IS DELIBERATELY NOT A FULL MIRROR — IT DOES NOT MODEL THE OWNERSHIP GUARD.
 * `migrate_post()` hands every rewrite to `apply_if_owned()`, twice per string against two
 * different intermediate forms, so mirroring the decision here would mean mirroring that
 * two-pass structure and doubling the hazard the paragraph above describes. Instead the run
 * REPORTS what it refused (`migrate_post()` returns `declined`), and the check below reads that
 * rather than re-deriving it — measured, not mirrored. `bws_replay_classify_mapping_row()` in
 * replay-verdict.php owns which of the four outcomes a derived row lands in and why the
 * guard's refusals are separated from the rest.
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
 * Writes <outdir>/mapping.jsonl, <outdir>/removed-wire.jsonl and <outdir>/convert-report.json.
 *
 * THE REMOVAL ARTIFACT IS THE THIRD ONE, and it is separate from the mapping on purpose: a
 * mapping row means "this became that", and the pattern-cache repair does not rename wire, it
 * REMOVES it. `diff-replays.php --removed=` reads it so a repaired row stops reading as a
 * vanished one; the rule is owned and pinned by `bws_replay_split_missing()` in
 * replay-verdict.php.
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

// REFUSE TO PRODUCE A SILENTLY PARTIAL REMOVAL ARTIFACT.
//
// The plugin's own upgrade trigger reconciles the pattern cache too, and runs before this
// script's body ever gets to (ordering fact + why it matters:
// bws_dynamic_tags_rebuild_allowlist_on_upgrade()'s own PHPDoc; the fatal condition below is
// bws_replay_upgrade_reconcile_consumed() in replay-verdict.php, which owns what counts as
// spent and why a timestamp does not). A run that has already lost the population cannot be
// salvaged in place — the strings are gone with nothing written down. Restore the snapshot and
// start again, with the constant defined BEFORE the restore, because the restore itself boots
// WordPress. GH #117 (FW-78).
require_once __DIR__ . '/replay-verdict.php';

if ( class_exists( '\BWS\DynamicTags\Admin\PatternCache' ) ) {
	$pc_status = \BWS\DynamicTags\Admin\PatternCache::get_status();

	if ( bws_replay_upgrade_reconcile_consumed( $pc_status, (string) $version ) ) {
		WP_CLI::error(
			"The upgrade trigger has already reconciled the pattern cache for this build,\n"
			. "so what it cleared is unrecorded and removed-wire.jsonl would be partial.\n"
			. "Define BWS_DYNAMIC_TAGS_DEFER_UPGRADE_RECONCILE in the clone's wp-config.php,\n"
			. "THEN restore the pre-upgrade snapshot — the restore boots WordPress and fires\n"
			. "the trigger itself — and run again."
		);
	}
	if ( ! \BWS\DynamicTags\Admin\PatternCache::upgrade_reconcile_deferred() ) {
		// Nothing detectable has been spent — the check above cleared it — but the trigger arms
		// on any version change and fires on ANY request, so the next restore or stray wp call
		// would take the population out from under a re-run.
		WP_CLI::warning(
			'BWS_DYNAMIC_TAGS_DEFER_UPGRADE_RECONCILE is not defined. The upgrade trigger has '
			. 'not spent anything for this build, but define it on the clone before the NEXT '
			. 'restore or build swap — not after.'
		);
	}
}

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

WP_CLI::log( sprintf( '%d distinct tag strings, %d derive a rewrite', count( $tags ), count( $mapping ) ) );

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

// WHAT THE GUARD REFUSED, AS THE RUN REPORTED IT. Collected outside the `changed` branch
// because a post can be declined on every tag it holds and therefore not change at all — that
// post is exactly the one whose refusals the mapping check needs. Keyed by tag NAME with the
// reason, which is the shape `migrate_post()` returns; the per-STRING half of the decision is
// recovered below from whether the old wire survived.
$declined_names = array();

foreach ( $scan as $row ) {
	$id = (int) $row['post_id'];
	try {
		$result = \BWS\DynamicTags\Admin\TagConverter::migrate_post( $id );
	} catch ( Throwable $e ) {
		$failed[] = array( 'post_id' => $id, 'error' => $e->getMessage() );
		continue;
	}
	foreach ( (array) ( $result['declined'] ?? array() ) as $declined_tag => $reason ) {
		$declined_names[ (string) $declined_tag ] = (string) $reason;
	}
	if ( ! empty( $result['changed'] ) ) {
		$migrated++;
		$tag_count += (int) ( $result['tag_count'] ?? 0 );
	}
}

WP_CLI::log( sprintf( 'migrated %d posts, %d tag rewrites', $migrated, $tag_count ) );
if ( $declined_names ) {
	WP_CLI::log( sprintf(
		'ownership guard declined %d tag name(s): %s',
		count( $declined_names ),
		implode( ', ', array_map(
			static function ( $name, $reason ) {
				return "{$name} ({$reason})";
			},
			array_keys( $declined_names ),
			$declined_names
		) )
	) );
}

// THE ADMIN BUTTON RECONCILES THE GB PRO PATTERN CACHE; SO MUST THIS.
// migrate_post() writes with $wpdb->update(), so GB Pro rebuilds nothing and a cached copy of the
// pre-migration content survives in generateblocks_patterns_tree postmeta (#98). ajax_migrate
// repairs that after the batch. Without the same call here, the B-side census reports shadow wire
// the real migration route would have removed — which reads exactly like the bug the repair fixed,
// and did on Site P on 2026-08-18, four days after that repair shipped.
//
// THE REPAIR REMOVES STALE SHADOW WIRE, AND A MIGRATION REPLAY MUST EXPECT ITS EFFECT: the B-side census
// loses rows the A side rendered, and each one lands in the diff as a pair present on only one
// side. Measured on Site H 2026-08-18: one wp_block whose post_content already held modern
// datetime_range wire kept two PRE-1.6 strings in its cached tree, from a migration predating
// the clone; `bws_dynamic_tags_pattern_cache_status` named the upgrade trigger and one
// reconciled entry.
//
// THE CALL BELOW ONLY SEES THAT POPULATION IF THE UPGRADE TRIGGER STOOD DOWN, which is what the
// precondition check at the top of this file enforces rather than assumes — an earlier skip flag
// lived HERE, downstream of the trigger, and isolated nothing for that reason. The axis is at
// `bws_dynamic_tags_rebuild_allowlist_on_upgrade()`; this file only reports what it observes.
//
// WHAT THE REPAIR CLEARED IS NOW RECORDED, which is what lets the diff tell a repaired row
// from a vanished one instead of failing on every one of them — how many that was on a real
// corpus is in `tools/harvest-replay/README.md`. Its own artifact, deliberately:
// `mapping.jsonl` means "this became that", and a removal is not a rename.
$pattern_cache = array();
$cleared_wire  = array();
if ( class_exists( '\BWS\DynamicTags\Admin\PatternCache' ) ) {
	$pattern_cache = \BWS\DynamicTags\Admin\PatternCache::reconcile_site( 'migrate' );
	$cleared_wire  = (array) ( $pattern_cache['cleared'] ?? array() );

	// Out of the JSON report and into its own file — the report is a summary a human reads,
	// and this list is bounded only by the size of the pattern library.
	unset( $pattern_cache['cleared'] );
	$pattern_cache['cleared_wire'] = count( $cleared_wire );

	WP_CLI::log( sprintf(
		'pattern cache: %d entries checked, %d reconciled, %d stale tag string(s) cleared',
		(int) ( $pattern_cache['checked'] ?? 0 ),
		(int) ( $pattern_cache['reconciled'] ?? 0 ),
		count( $cleared_wire )
	) );
}

// REPORT/RUN AGREEMENT — a report that outlives its run means the two halves disagree about
// what a migration is.
//
// CONVERTIBLE WORK ONLY. `scan()` classifies every stored string into convert / declined /
// skipped, and the last two are reported BECAUSE they survive — a declined tag still being
// there after the run is the guard working, not the two halves disagreeing. Counting any
// non-empty `deprecated_tags` would fire this warning on every default-guard run from now on,
// and a warning with a loud resting state is one nobody reads.
$rescan    = \BWS\DynamicTags\Admin\TagConverter::scan();
$remaining = 0;
foreach ( $rescan as $row ) {
	foreach ( (array) ( $row['deprecated_tags'] ?? array() ) as $d ) {
		if ( 'convert' === ( $d['status'] ?? 'convert' ) ) {
			$remaining++;
			break;
		}
	}
}
if ( $remaining ) {
	WP_CLI::warning( "a second scan still reports {$remaining} convertible post(s) — report and run disagree" );
}

// ---------------------------------------------------------------------------
// 4. Check the derivation against what is actually stored, and record what the converter
//    could not reach. A mapped tag still present in its OLD form is not an error: it is wire
//    living outside wp_posts, and a diff that applied the mapping blindly would expect it to
//    have moved.
// ---------------------------------------------------------------------------
global $wpdb;

$unreached  = array();
$unverified = array();
$declined   = array();
$moved      = array();
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

	list( $name ) = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $old );

	switch ( bws_replay_classify_mapping_row( (bool) $still_old, (bool) $has_new, isset( $declined_names[ $name ] ) ) ) {
		case 'declined':
			// The guard refused this one, and the run said so. Not a derivation fault, and not
			// a mapping row: nothing became anything.
			$declined[] = array(
				'old'             => $old,
				'would_have_been' => $new,
				'reason'          => $declined_names[ $name ],
				'posts_still_old' => $still_old,
			);
			break;

		case 'unverified':
			// Survived in wp_posts despite being mapped, and the guard did not refuse it: the
			// derivation and the converter's own order have diverged. That is the trip-hazard
			// this check exists for, and its resting state is zero.
			$unverified[] = array( 'old' => $old, 'new' => $new, 'posts_still_old' => $still_old );
			break;

		case 'unreached':
			// Neither form is in wp_posts — the tag lived only in options/postmeta/termmeta,
			// which the converter cannot reach. Expected, and recorded.
			$unreached[] = array( 'old' => $old, 'new' => $new );
			break;

		default:
			$moved[ $old ] = $new;
	}
}

// AN UNREACHED ROW STAYS IN THE MAPPING, A DECLINED ONE DOES NOT. Both left the old wire in
// place, but only one of them is a rewrite the converter still owns: unreached wire lives
// outside wp_posts and the diff needs the pairing if that wire is ever rendered, while a
// declined row names a rewrite that was refused and will stay refused. See
// `bws_replay_classify_mapping_row()` for why the two are told apart at all.
$mapping = array_merge( $moved, array_column( $unreached, 'new', 'old' ) );

if ( $unverified ) {
	WP_CLI::warning( sprintf( '%d mapped tag(s) still present in post_content in their OLD form, unrefused — derivation may not mirror migrate_post', count( $unverified ) ) );
}
if ( $declined ) {
	WP_CLI::log( sprintf( '%d derived rewrite(s) refused by the ownership guard, left out of the mapping', count( $declined ) ) );
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

// THE REMOVAL ARTIFACT. `diff-replays.php --removed=` reads it to tell a pattern-cache repair
// from a genuine disappearance; `bws_replay_split_missing()` in replay-verdict.php owns that
// rule and pins it. Written on EVERY run, including empty ones — an absent file and a file
// naming nothing are the same forgiveness, and only one of them says a run happened.
//
// THE `meta` ROW EXISTS BECAUSE THE KEYS HAVE TO MATCH. Harvest builds every census
// `tag_string` with its own regex, which it takes as a command-line argument, so a harvest run
// that overrode it produces strings these can never equal. That fails SAFE — an unmatched pair
// stays the hard failure it already is — and it fails SILENTLY, so the pattern that produced
// this file is recorded beside it. Threading an override through the reconcile is not done:
// nothing has needed one, and a knob with no caller is a second way to key an artifact wrong.
$removed_path = rtrim( $outdir, '/' ) . '/removed-wire.jsonl';
$rh           = fopen( $removed_path, 'w' );
fwrite(
	$rh,
	wp_json_encode(
		array(
			'kind'           => 'meta',
			'recorded_at'    => gmdate( 'c' ),
			'plugin_version' => $version,
			'wire_pattern'   => class_exists( '\BWS\DynamicTags\Admin\PatternCache' )
				? \BWS\DynamicTags\Admin\PatternCache::WIRE_PATTERN
				: null,
		)
	) . "\n"
);
foreach ( $cleared_wire as $row ) {
	fwrite(
		$rh,
		wp_json_encode(
			array(
				'kind'       => 'removed',
				'post_id'    => (int) ( $row['post_id'] ?? 0 ),
				'tag_string' => (string) ( $row['tag_string'] ?? '' ),
			)
		) . "\n"
	);
}
fclose( $rh );

$report = array(
	'converted_at'      => gmdate( 'c' ),
	'plugin_version'    => $version,
	'distinct_tags'     => count( $tags ),
	'derived_rewrites'  => count( $moved ) + count( $unreached ) + count( $declined ) + count( $unverified ),
	'mapped_tags'       => count( $mapping ),
	'posts_reported'    => $reported,
	'posts_migrated'    => $migrated,
	'tag_rewrites'      => $tag_count,
	'posts_still_reported_after' => $remaining,
	'migrate_failures'  => $failed,
	'unreached_by_converter' => $unreached,
	'ownership_declined'     => $declined,
	'derivation_unverified'  => $unverified,
	'pattern_cache'     => $pattern_cache,
);

file_put_contents(
	rtrim( $outdir, '/' ) . '/convert-report.json',
	wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
);

WP_CLI::log( sprintf(
	'unreached by converter (options/meta only): %d   ownership declined: %d   derivation unverified: %d',
	count( $unreached ),
	count( $declined ),
	count( $unverified )
) );
WP_CLI::log( 'written: ' . $map_path );
WP_CLI::log( 'written: ' . $removed_path );
