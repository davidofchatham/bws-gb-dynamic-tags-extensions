<?php
/**
 * replay-tags.php — render a harvested tag corpus against ONE real URL.
 *
 * The replay half of the harvest/replay instrument (`.claude/plans/multi-step-slot-sources.md`
 * §Verification). Harvest (env repo) answers "what wire exists and where can it be seen";
 * this answers "what does that wire render, with genuine ambient context".
 *
 * MECHANISM — the same three lines `tools/cli/class-render-tag-command.php` proved, carried
 * here rather than called:
 *   1. --url is a WP-CLI GLOBAL param. It sets $_SERVER's request keys but does NOT run the
 *      main query, so is_tax()/get_queried_object() are empty until we call wp().
 *   2. wp() runs parse_request()/query_posts()/register_globals() → a genuine $wp_query.
 *   3. The plugin touches the block instance ONLY through ->context, so a bare stdClass with
 *      an empty context array is a sufficient instance, and leaving bwsEditorPreview unset
 *      yields real output rather than preview text.
 *
 * WHY NOT JUST CALL `wp bws render-tag`. That command lives under `tools/`, which is
 * `.distignore`d — a clone running the site's RELEASED build has no such command. Experiment
 * M's A-side runs against exactly that build. This file is reachable anyway because the dev
 * repo is bind-mounted read-only into the wp-cli container at
 * /plugins/bws-gb-dynamic-tags-extensions, independent of what the site has installed. That
 * also means ONE replay implementation renders both sides of every diff, which is the point:
 * two implementations would produce a clean diff for the wrong reason.
 *
 * ONE PROCESS PER URL, BY CONSTRUCTION. wp() runs the main query once; ambient context is a
 * property of the process. The driver (env repo `bin/replay-tags.sh`) loops URLs inside a
 * single container so the cost is a WP bootstrap per URL, not a container per URL.
 *
 * THE OPCACHE TRIPWIRE (§Verification: "the most dangerous failure mode this instrument
 * has, because it looks like success"). Swapping plugin files under LSPHP can serve stale
 * bytecode, and a stale-bytecode replay produces a clean diff. So the run asserts the RUNTIME
 * constant against the version header read FRESH FROM DISK, and aborts on mismatch. A file
 * hash cannot do this: the bytes on disk are the new ones — it is the executed code that is
 * old, so only a runtime observation can see it.
 *
 * VOLATILITY. A and C are rendered at different wall-clock times, so a tag reading "now"
 * differs for reasons that are not the migration. Each tag is therefore rendered TWICE in
 * process and flagged `volatile` when the two disagree, so the diff can separate "changed"
 * from "never stood still". This catches in-process variance, not a day boundary crossed
 * between runs — the run header records the timestamp so the reviewer can see one.
 *
 * Usage (paths are CONTAINER paths):
 *   wp eval-file /plugins/bws-gb-dynamic-tags-extensions/tools/replay-tags.php \
 *       <census.jsonl> <out.jsonl> <context_kind> [<volatility:0|1>] \
 *       --url=https://portals.test/some-post/
 *
 * Output is JSONL, appended: one `run` header object per URL, then one `render` object per
 * tag. Append rather than overwrite so the driver's loop accumulates a single artifact.
 *
 * Dev/testing only. Never loaded by the plugin.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run via wp-cli eval-file.\n";
	exit( 1 );
}

/** @var array $args Positional args from `wp eval-file <file> <a> <b> ...`. */
$census_path = $args[0] ?? '';
$out_path    = $args[1] ?? '';
$context_kind = (string) ( $args[2] ?? 'unknown' );
$check_volatility = '0' !== (string) ( $args[3] ?? '1' );

if ( '' === $census_path || '' === $out_path ) {
	WP_CLI::error( 'Usage: eval-file replay-tags.php <census.jsonl> <out.jsonl> <context_kind> [volatility:0|1]' );
}
if ( ! is_readable( $census_path ) ) {
	WP_CLI::error( "Census not readable: {$census_path}" );
}

// ---------------------------------------------------------------------------
// 0. The opcache tripwire. Runtime constant vs the header on disk.
// ---------------------------------------------------------------------------
if ( ! defined( 'BWS_DYNAMIC_TAGS_VERSION' ) || ! defined( 'BWS_DYNAMIC_TAGS_FILE' ) ) {
	WP_CLI::error( 'BWS dynamic tags plugin is not loaded on this site (version constants absent).' );
}

$runtime_version = BWS_DYNAMIC_TAGS_VERSION;
$plugin_file     = BWS_DYNAMIC_TAGS_FILE;
$disk_version    = '';

// Read the header ourselves rather than via get_plugin_data(): that goes through the same
// file the opcache may be lying about only for the *code*, but we want the raw bytes, and we
// want this to work identically on a build old enough to predate any helper we might reach for.
$header_bytes = is_readable( $plugin_file ) ? (string) file_get_contents( $plugin_file, false, null, 0, 8192 ) : '';
if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $header_bytes, $m ) ) {
	$disk_version = trim( $m[1] );
}

if ( '' === $disk_version ) {
	WP_CLI::error( "Could not read a Version header from {$plugin_file} — refusing to replay blind." );
}
if ( $disk_version !== $runtime_version ) {
	WP_CLI::error( sprintf(
		"STALE BYTECODE. Runtime BWS_DYNAMIC_TAGS_VERSION=%s but %s declares %s.\n"
		. 'A replay in this state produces a clean diff for the wrong reason. Flush opcache and re-run.',
		$runtime_version,
		$plugin_file,
		$disk_version
	) );
}

if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
	WP_CLI::error( 'GenerateBlocks dynamic tags API not available (GB Pro active?).' );
}

// ---------------------------------------------------------------------------
// 1. Real ambient context. --url only set $_SERVER; wp() is what makes it genuine.
// ---------------------------------------------------------------------------
$url = (string) ( WP_CLI::get_runner()->config['url'] ?? '' );
if ( '' !== $url ) {
	// WP-CLI's --url sets HTTP_HOST / REQUEST_URI / QUERY_STRING but does NOT populate $_GET,
	// and WP::parse_request() reads public query vars from $_GET. Without this, EVERY
	// query-string URL silently collapses to the front page: a post type registered with
	// rewrite=false is reachable only as ?<type>=<slug>, and so is the search stratum (?s=).
	// Nothing errors and every tag renders, so the stratum looks covered on both sides of a
	// diff while testing the home page twice.
	$query = (string) parse_url( $url, PHP_URL_QUERY );
	if ( '' !== $query ) {
		parse_str( $query, $parsed );
		$_GET     = $parsed;
		$_REQUEST = array_merge( $_REQUEST, $parsed );
	}

	// AN UNSTABLE SORT IS NOT A RENDER DIFFERENCE, and it is invisible to the volatility check:
	// that renders twice inside ONE process, where the query result is already fixed. Across two
	// processes it is not. Real case on hargrave — eight event posts sharing one identical
	// post_date, so `ORDER BY post_date DESC` is a total tie and MySQL may return any of them
	// first. The archive's first row changed between the A run and the C run, and five GB CORE
	// tags (post_title, post_permalink, post_excerpt) faithfully reported a different post.
	// Nothing about the plugin or the migration was involved.
	//
	// A stable tiebreak is applied to EVERY query, identically on both sides of a diff. It makes
	// the instrument deterministic; it does not claim the site is — a front end with tied sort
	// keys genuinely varies, and that is the site's data to fix, not this tool's to hide.
	add_filter(
		'posts_orderby',
		static function ( $orderby ) {
			global $wpdb;
			$tiebreak = "{$wpdb->posts}.ID ASC";
			return ( is_string( $orderby ) && '' !== trim( $orderby ) ) ? $orderby . ', ' . $tiebreak : $tiebreak;
		},
		PHP_INT_MAX
	);

	wp();
}

$qo         = get_queried_object();
$queried_id = get_queried_object_id();

if ( $qo instanceof WP_Term ) {
	$observed = "term:{$qo->taxonomy}:{$qo->slug}";
} elseif ( $qo instanceof WP_Post ) {
	$observed = "post:{$qo->post_type}:{$qo->post_name}";
} elseif ( $qo instanceof WP_User ) {
	$observed = "author:{$qo->user_nicename}";
} elseif ( null === $qo ) {
	$observed = is_404() ? 'none:404' : 'none';
} else {
	$observed = 'other:' . get_class( $qo );
}

// A URL that does not resolve to the KIND the inventory promised is a finding about the
// inventory, not a render result. Recorded on the run header so a whole stratum going missing
// is visible rather than showing up as N renders of the wrong context.
//
// 404 is not the only way this happens, and assuming it was is a mistake this made once: a
// taxonomy registered `public` but not publicly queryable has no archive, so WP serves the
// FRONT PAGE at that URL. Nothing errors, every tag renders, and the whole tax: stratum is
// quietly testing `home` on both sides of the diff — agreement that means nothing. So the
// check compares the observed kind against the promised one rather than testing for 404.
$expected_kind = static function ( string $kind, string $seen ): bool {
	if ( 0 === strpos( $kind, 'singular:' ) ) {
		return 0 === strpos( $seen, 'post:' . substr( $kind, 9 ) . ':' );
	}
	if ( 0 === strpos( $kind, 'tax:' ) ) {
		return 0 === strpos( $seen, 'term:' . substr( $kind, 4 ) . ':' );
	}
	if ( 'author' === $kind ) {
		return 0 === strpos( $seen, 'author:' );
	}
	if ( '404' === $kind ) {
		return 'none:404' === $seen;
	}
	// home / search / date have no queried object of their own (a static front page does, and
	// either answer is correct there) — they are only wrong when they 404.
	return 'none:404' !== $seen;
};

$mismatch = ! $expected_kind( $context_kind, $observed );

// ---------------------------------------------------------------------------
// 2. The corpus. Distinct tag strings, deterministically ordered.
// ---------------------------------------------------------------------------
$tags = array();
$fh   = fopen( $census_path, 'r' );
while ( false !== ( $line = fgets( $fh ) ) ) {
	$line = trim( $line );
	if ( '' === $line ) {
		continue;
	}
	$row = json_decode( $line, true );
	if ( ! is_array( $row ) || empty( $row['tag_string'] ) ) {
		continue;
	}
	$tags[ $row['tag_string'] ] = true;
}
fclose( $fh );

$tags = array_keys( $tags );
sort( $tags, SORT_STRING );

// ---------------------------------------------------------------------------
// 3. Render.
// ---------------------------------------------------------------------------
$instance          = new stdClass();
$instance->context = array();

$render = static function ( string $tag ) use ( $instance ) {
	try {
		return array( 'out' => (string) GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, array(), $instance ), 'err' => null );
	} catch ( Throwable $e ) {
		// A throwing tag is a result, not a crash: swallowing the run would lose every
		// other tag on this URL, and "it threw" is itself a diffable observation.
		return array( 'out' => null, 'err' => get_class( $e ) . ': ' . $e->getMessage() );
	}
};

$out = fopen( $out_path, 'a' );
if ( ! $out ) {
	WP_CLI::error( "Cannot append to {$out_path}" );
}

$write = static function ( array $row ) use ( $out ) {
	fwrite( $out, wp_json_encode( $row ) . "\n" );
};

$write( array(
	'kind'            => 'run',
	'url'             => $url,
	'context_kind'    => $context_kind,
	'observed'        => $observed,
	'queried_id'      => $queried_id,
	'context_mismatch' => $mismatch,
	'plugin_version'  => $runtime_version,
	'tags'            => count( $tags ),
	'volatility_check' => $check_volatility,
	// Wall clock, so a reviewer can see a day boundary between the two sides of a diff.
	'rendered_at'     => gmdate( 'c' ),
) );

$volatile = 0;
$errors   = 0;

foreach ( $tags as $tag ) {
	$first = $render( $tag );
	$is_volatile = false;

	if ( $check_volatility && null === $first['err'] ) {
		$second      = $render( $tag );
		$is_volatile = ( $second['out'] !== $first['out'] );
	}

	if ( $is_volatile ) {
		$volatile++;
	}
	if ( null !== $first['err'] ) {
		$errors++;
	}

	$write( array(
		'kind'         => 'render',
		'url'          => $url,
		'context_kind' => $context_kind,
		'tag_string'   => $tag,
		'output'       => $first['out'],
		'error'        => $first['err'],
		'volatile'     => $is_volatile,
	) );
}

fclose( $out );

WP_CLI::log( sprintf(
	'%s  [%s → %s]  tags=%d volatile=%d errors=%d%s',
	$url,
	$context_kind,
	$observed,
	count( $tags ),
	$volatile,
	$errors,
	$mismatch ? "  ** CONTEXT MISMATCH — promised {$context_kind}, got {$observed} **" : ''
) );
