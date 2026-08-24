<?php
/**
 * Page snapshots — capture every fixture page's rendered HTML, normalize away per-render
 * churn, and diff it against a committed baseline.
 *
 * THIS FILE NEEDS A SERVED FIXTURE SITE, which nothing else under `tools/test/` does.
 * (`control-order-test.php` is already documented as "not pure and cannot be", but it still
 * runs against no world at all — its impurity is that it sees all three registration
 * constructors at once. This one is impure in a larger way: no site, no run.) That
 * dependency is the entire point of the file, for the reason below. The pure half —
 * normalization, diffing, and deriving the page set — is callable with no WordPress and no
 * network, and `page-snapshot-normalize-test.php` is what exercises it.
 *
 * WHY IT EXISTS: THE FIXTURE PAGES ARE THE ONLY CORPUS RENDERED THE WAY A VISITOR GETS IT.
 * `wp bws render-tag` and `tools/harvest-replay/replay-tags.php` both call
 * GenerateBlocks_Register_Dynamic_Tag::replace_tags() directly. That DOES reach
 * `generateblocks_dynamic_tag_replacement` — the filter is applied inside replace_tags()
 * itself. An earlier version of this header said the opposite, on a measurement that did
 * not reproduce: re-measured 2026-08-24, `{{text key:bws_zero_probe}}` through render-tag
 * returns three bytes, the third being the trailing space includes/hooks.php's guard adds
 * to a bare '0'. `verify.php` now pins that rather than leaving it to prose.
 *
 * What a direct call cannot produce is the RENDER AROUND the tag: `$block` arrives as
 * array(), so anything keyed on real block markup or GB's block-name gate is unreachable;
 * `--loop-item` fakes only queryType 'WP_Query', so a TERM or USER query loop cannot be
 * produced at any flag combination; and the_content filters, wptexturize included, never
 * run. The loop gap is the load-bearing one — it is the exact shape of the leak this
 * instrument was built to measure, so acceptance evidence for it cannot come from
 * render-tag. That is also what keeps the mandatory-visible-blocks rule load-bearing
 * rather than hygiene: a matrix row that never became a block is outside this instrument.
 *
 * THE PAGE SET COMES FROM THE MANIFEST, NOT FROM A LIST KEPT HERE. Every `posts` entry
 * carrying a `content_builder` is a page built to be looked at, so it is a page worth
 * pinning; a new fixture page enters the snapshot set by existing. A hand-kept URL list
 * would be a second source of truth over the blueprint, which is the same objection that
 * rejected a matrix-row runner (spec D24).
 *
 * PERMALINKS ARE DERIVED, AND THE DERIVATION IS PINNED. With no WordPress there is no
 * `get_permalink()`, so the URL is built from the manifest: a `page` is `/<slug>/` and any
 * other post type is `/<post_type>/<slug>/`. That rule can rot (a rewrite slug, a changed
 * permastruct) and would rot SILENTLY, capturing 404s that normalize to a stable
 * diff-clean baseline. So when WordPress IS loaded — which is the case verify.php runs in —
 * every derived URL is compared against `get_permalink()` and a divergence is reported as a
 * failure. The cheap path stays cheap and cannot drift unobserved.
 *
 * CAPTURE IS HOST-SIDE BY NECESSITY. The repo is bind-mounted READ-ONLY into the container,
 * so nothing running under `wp eval-file` can write `tools/test/snapshots/`. Comparison
 * only reads, so it runs happily in either place; capture runs on the host.
 *
 * WHAT A CLEAN DIFF PROVES, AND WHAT IT DOES NOT: `docs/update-triggers.md` owns that rule.
 * `docs/testbed.md` owns operating this. Neither is restated here.
 *
 * Run (host, from the repo root):
 *   php tools/test/page-snapshots.php --capture    # write/refresh the baseline
 *   php tools/test/page-snapshots.php              # compare against it (exit 1 on diff)
 *   php tools/test/page-snapshots.php --base-url=https://other.test
 *
 * As a library (verify.php does this):
 *   require .../page-snapshots.php;  bws_page_snapshot_compare_all( $opts );
 *
 * @package BWS_Dynamic_Tags
 */

/** The fixture site this instrument points at unless told otherwise. */
define( 'BWS_PAGE_SNAPSHOT_DEFAULT_BASE_URL', 'https://testbed.test' );

/** Where the committed baseline lives. */
define( 'BWS_PAGE_SNAPSHOT_DIR', __DIR__ . '/snapshots' );

/** The blueprint whose manifest supplies the page set. */
define( 'BWS_PAGE_SNAPSHOT_MANIFEST', dirname( __DIR__ ) . '/fixtures/core-structures/manifest.php' );

/** The dependency-version record a snapshot diff is read against. */
define( 'BWS_PAGE_SNAPSHOT_ENV_RECORD', dirname( __DIR__ ) . '/fixtures/core-structures/env-versions.php' );

/* -------------------------------------------------------------------------
 * The page set
 * ---------------------------------------------------------------------- */

/**
 * Every fixture page worth snapshotting, as key => array( post_type, slug, path ).
 *
 * The `content_builder` key is the selector: it marks an entry whose content was generated
 * to be READ, which is exactly the population a rendered-output baseline is about. Fixture
 * entries with no builder (relationship targets carrying only field values, and the
 * wp_block pattern, which has no permalink at all) render none of our tags and would
 * contribute a page of theme chrome to every diff.
 */
function bws_page_snapshot_pages( $manifest = null ) {
	if ( null === $manifest ) {
		$manifest = require BWS_PAGE_SNAPSHOT_MANIFEST;
	}

	$out = array();

	foreach ( (array) ( isset( $manifest['posts'] ) ? $manifest['posts'] : array() ) as $key => $entry ) {
		if ( empty( $entry['content_builder'] ) || empty( $entry['post_name'] ) ) {
			continue;
		}

		$type = isset( $entry['post_type'] ) ? $entry['post_type'] : 'post';
		$slug = $entry['post_name'];

		$out[ $key ] = array(
			'key'       => $key,
			'post_type' => $type,
			'slug'      => $slug,
			// The derivation the docblock describes, and the thing
			// bws_page_snapshot_assert_permalinks() checks when WP is around.
			'path'      => 'page' === $type ? "/{$slug}/" : "/{$type}/{$slug}/",
		);
	}

	ksort( $out );

	return $out;
}

/**
 * Compare every derived path against WordPress's own permalink.
 *
 * Returns human-readable mismatch strings (empty array = agreement). This is what keeps the
 * WordPress-free derivation above from rotting silently: a stale rule would otherwise
 * capture 404 pages, which normalize perfectly well and diff clean forever.
 *
 * Needs WordPress; a no-op without it.
 */
function bws_page_snapshot_assert_permalinks( array $pages, $base_url ) {
	$bad = array();

	if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_permalink' ) ) {
		return $bad;
	}

	foreach ( $pages as $page ) {
		$found = get_posts(
			array(
				'name'        => $page['slug'],
				'post_type'   => $page['post_type'],
				'post_status' => 'publish',
				'numberposts' => 1,
			)
		);

		if ( ! $found ) {
			$bad[] = sprintf(
				'%s: no published %s with slug "%s" — is the site seeded?',
				$page['key'],
				$page['post_type'],
				$page['slug']
			);
			continue;
		}

		$actual  = (string) get_permalink( $found[0] );
		$derived = rtrim( $base_url, '/' ) . $page['path'];

		if ( $actual !== $derived ) {
			$bad[] = sprintf(
				'%s: derived %s but WordPress says %s — the permalink rule in page-snapshots.php is stale.',
				$page['key'],
				$derived,
				$actual
			);
		}
	}

	return $bad;
}

/* -------------------------------------------------------------------------
 * Fetching
 * ---------------------------------------------------------------------- */

/**
 * Fetch one URL, cache-busted.
 *
 * THE BUST IS NOT OPTIONAL. Front-end pages are LiteSpeed-cached, so a plain request can
 * return a page rendered before the change under test — which reads as "no diff", the pass
 * condition, from an instrument that never saw the new code. `docs/testbed.md` §The page
 * cache has the rest, including why `$RANDOM` cannot be used to generate the value.
 *
 * The value is unique per CALL rather than per run: a retry of one URL inside a single run
 * must not be served the entry the first attempt just created.
 *
 * Returns array( 'body' => string, 'error' => string|null, 'status' => int ).
 */
function bws_page_snapshot_fetch( $url ) {
	static $seq = 0;

	// UNIQUE ACROSS PROCESSES, not merely within one. `time()` alone collides between two
	// runs started in the same second, and a collision means the second run is served the
	// entry the first one created — a page rendered BEFORE the change under test. That reads
	// as "no diff", the pass condition, from an instrument that never saw the new code:
	// precisely the silent failure the bust exists to prevent.
	$seq++;
	$bust = sprintf( '%d%05d%05d%04d', time(), getmypid() % 100000, mt_rand( 0, 99999 ), $seq % 10000 );
	$sep  = false === strpos( $url, '?' ) ? '?' : '&';
	$url  = $url . $sep . 'nocache=' . $bust;

	if ( function_exists( 'wp_remote_get' ) ) {
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => 60,
				'redirection' => 3,
				// The testbed serves a self-signed certificate. This is a local
				// fixture site by construction; no transport claim is being made.
				'sslverify'   => false,
			)
		);

		if ( is_wp_error( $res ) ) {
			return array(
				'body'   => '',
				'error'  => $res->get_error_message(),
				'status' => 0,
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $res );

		return array(
			'body'   => (string) wp_remote_retrieve_body( $res ),
			'error'  => 200 === $code ? null : "HTTP {$code}",
			'status' => $code,
		);
	}

	$ctx = stream_context_create(
		array(
			'http' => array(
				'timeout'         => 60,
				'ignore_errors'   => true,
				'follow_location' => 1,
			),
			'ssl'  => array(
				'verify_peer'      => false,
				'verify_peer_name' => false,
			),
		)
	);

	$http_response_header = array();
	$body                 = @file_get_contents( $url, false, $ctx );
	$code                 = 0;

	foreach ( (array) $http_response_header as $header ) {
		if ( preg_match( '#^HTTP/\S+\s+(\d{3})#', $header, $m ) ) {
			$code = (int) $m[1];
		}
	}

	if ( false === $body ) {
		return array(
			'body'   => '',
			'error'  => 'request failed (is the testbed up?)',
			'status' => $code,
		);
	}

	return array(
		'body'   => $body,
		'error'  => 200 === $code ? null : "HTTP {$code}",
		'status' => $code,
	);
}

/* -------------------------------------------------------------------------
 * Normalization — the pure half
 * ---------------------------------------------------------------------- */

/**
 * Strip everything that differs between two renders of an unchanged page.
 *
 * THE ACCEPTANCE TEST FOR THIS FUNCTION IS EMPIRICAL: capture twice with no code change and
 * the diff must be empty. Every rule below was derived that way, against a real capture,
 * rather than from a guess about what a page contains. Two of them would not have been
 * guessed:
 *
 *   - ANTISPAMBOT RANDOMIZES. WordPress's antispambot() decides per CHARACTER, via
 *     mt_rand(), whether to emit a literal or a numeric entity, so every {{email}} on the
 *     page renders differently on every single request. It is the largest churn source on
 *     the fixture site by line count and it is invisible to reasoning — the markup looks
 *     stable until two captures are diffed.
 *   - THE CACHE PLUGIN SIGNS ITS WORK with a timestamped HTML comment in the footer.
 *
 * WHAT IS DELIBERATELY NOT NORMALIZED: `<style>` blocks whose id begins with `bws-`. Those
 * are OUR output ({{table}}'s footer CSS is one), and that feature's update trigger says to
 * confirm on the front end that it prints exactly once — a normalization that erased it
 * would answer that question "yes" forever. Every other generated stylesheet is dependency
 * churn and its BODY goes, while the tag and id stay, so a block vanishing entirely is
 * still a visible diff.
 *
 * Pure: no WordPress, no network, no filesystem.
 */
function bws_page_snapshot_normalize( $html, $base_url = BWS_PAGE_SNAPSHOT_DEFAULT_BASE_URL ) {
	$s = (string) $html;

	// Uniform line endings first, so every rule below can assume "\n".
	$s = str_replace( array( "\r\n", "\r" ), "\n", $s );

	// 1a. POST-LIFECYCLE TIMESTAMPS. `post_modified` moves every time the seeder touches a
	//     post, so without this rule `bin/seed.sh` — the operation `docs/testbed.md` tells
	//     you to run — invalidates all nine baselines at once and the instrument cries wolf
	//     on its own maintenance. Confirmed empirically: every fixture page carries four of
	//     these, all derived from the post row rather than from anything we render.
	//
	//     NAMED CARRIERS ONLY, NEVER A GENERIC DATE PATTERN. Rendered {{datetime_*}} output
	//     is on these same pages and IS the signal — `D0.3: 2030-08-12 9:00 am` is a tag
	//     result, not chrome. The carriers below are head metadata WordPress derives from
	//     post_date/post_modified; nothing our tags emit can land inside one.
	//
	//     `\x27` rather than a literal apostrophe so the PCRE class needs no PHP-level
	//     escaping — the pattern reads the same in both languages.
	$s = preg_replace(
		'#((?:article:(?:published|modified)_time|og:updated_time)["\x27]\s+content=["\x27])[^"\x27]+#i',
		'${1}TIMESTAMP',
		$s
	);
	$s = preg_replace( '#("(?:datePublished|dateModified)"\s*:\s*")[^"]+#i', '${1}TIMESTAMP', $s );

	// 1b. Cache-plugin footprints. Timestamped by construction.
	$s = preg_replace( '#<!--\s*Page cached by LiteSpeed Cache.*?-->#s', '', $s );
	$s = preg_replace( '#<!--\s*(?:QUIC\.cloud|Powered by LiteSpeed).*?-->#s', '', $s );

	// 2. Generated <style> bodies. Ours (id="bws-*") are content, not churn — see docblock.
	$s = preg_replace_callback(
		'#(<style\b[^>]*>)(.*?)(</style\s*>)#s',
		static function ( $m ) {
			if ( preg_match( '#\bid=(["\'])bws-#i', $m[1] ) ) {
				return $m[0];
			}

			return $m[1] . "\n/* normalized-away */\n" . $m[3];
		},
		$s
	);

	// 3. Nonces. Regenerated on a 12-hour tick, so without this a baseline goes stale by the
	//    clock rather than by anything having changed.
	$s = preg_replace( '#(_wpnonce=)[0-9a-f]{6,}#i', '${1}NONCE', $s );
	$s = preg_replace( '#(["\']?nonce["\']?\s*[:=]\s*["\'])[0-9a-f]{6,}#i', '${1}NONCE', $s );

	// 4. Cache-busting and asset-version query strings. `?ver=` moves on every dependency
	//    upgrade; that IS the drift env-versions.php reports, and reporting it once beats
	//    scattering it across a hundred diff lines where it drowns the real change.
	//    THE SEPARATOR IS MATCHED IN ITS ENCODED FORMS TOO. WordPress emits `&amp;` (and
	//    occasionally `&#038;`) between query args inside an HTML attribute, so a bare
	//    `[?&]` class reaches only the FIRST argument — any asset carrying `ver` in second
	//    position would leak its dependency version straight into the diff, which is exactly
	//    the noise this rule exists to keep out.
	$sep = '(?:[?&]|&amp;|&\#0?38;)';

	$s = preg_replace( '#(' . $sep . ')nocache=[^"\x27&\s>]*#', '${1}nocache=BUST', $s );
	$s = preg_replace( '#(' . $sep . ')ver=[^"\x27&\s>]*#', '${1}ver=VER', $s );

	// 5. Absolute URLs → root-relative. The host is an operator's choice, not a property of
	//    the output, so a baseline captured on one env must diff clean on another.
	$host = preg_replace( '#^https?://#', '', rtrim( (string) $base_url, '/' ) );

	if ( '' !== $host ) {
		$s = preg_replace( '#(?:https?:)?//' . preg_quote( $host, '#' ) . '#i', '', $s );
	}

	// Any remaining local-dev host, so a capture taken against a differently-named env still
	// lands on the same bytes.
	$s = preg_replace( '#https?://[a-z0-9-]+\.(?:test|local)\b#i', '', $s );

	// 6. Generated block ids. GB derives per-block class suffixes and WP emits per-block
	//    element ids and container classes; all are regenerated by a reseed, so they churn
	//    against content that did not change.
	$s = preg_replace( '#\bgb-([a-z]+)-[0-9a-f]{6,12}\b#i', 'gb-$1-ID', $s );
	$s = preg_replace( '#\bid="block-[0-9a-f-]{8,}"#i', 'id="block-ID"', $s );
	$s = preg_replace( '#\bwp-container-[a-z0-9-]*[0-9a-f]{6,}\b#i', 'wp-container-ID', $s );

	// 7. antispambot()'s per-character coin flip. Decoding numeric references in the ASCII
	//    range collapses every spelling it can produce onto one. The five markup-significant
	//    characters are excluded: this artifact is read as text, but a decoded `&#60;` would
	//    make a diff line unreadable, and antispambot cannot emit them anyway — an email
	//    address contains none of them.
	$s = preg_replace_callback(
		'#&\#(?:x([0-9a-f]{1,2})|(\d{1,3}));#i',
		static function ( $m ) {
			$code = ( isset( $m[1] ) && '' !== $m[1] ) ? hexdec( $m[1] ) : (int) $m[2];

			if ( $code < 32 || $code > 127 ) {
				return $m[0];
			}

			if ( in_array( $code, array( 34, 38, 39, 60, 62 ), true ) ) {
				return $m[0];
			}

			return chr( $code );
		},
		$s
	);

	// 8. Trailing whitespace and blank-line runs. Cosmetic, but they make a real diff
	//    louder than it is.
	$s = preg_replace( '#[ \t]+$#m', '', $s );
	$s = preg_replace( "#\n{3,}#", "\n\n", $s );

	return trim( $s ) . "\n";
}

/* -------------------------------------------------------------------------
 * Diffing — the other pure half
 * ---------------------------------------------------------------------- */

/**
 * Line-level differences between two normalized snapshots.
 *
 * Deliberately NOT an LCS diff. An LCS would report an inserted line as one insertion,
 * where a positional compare reports it as a long run of changes. That is the wrong
 * tradeoff for READING and the right one for a GATE: the question here is "did anything
 * move", the answer is binary, and the report exists to point a human at the first place to
 * look. A real diff algorithm would be complexity bought to make a failure prettier.
 *
 * Returns array of array( 'line' => int, 'baseline' => string|null, 'current' => string|null );
 * beyond $limit the entries are counted but carry null text, so a wholesale change does not
 * print a book.
 */
function bws_page_snapshot_diff( $baseline, $current, $limit = 12 ) {
	$a = explode( "\n", (string) $baseline );
	$b = explode( "\n", (string) $current );
	$n = max( count( $a ), count( $b ) );

	$diffs = array();

	for ( $i = 0; $i < $n; $i++ ) {
		$la = isset( $a[ $i ] ) ? $a[ $i ] : '<<absent>>';
		$lb = isset( $b[ $i ] ) ? $b[ $i ] : '<<absent>>';

		if ( $la === $lb ) {
			continue;
		}

		if ( count( $diffs ) < $limit ) {
			$diffs[] = array(
				'line'     => $i + 1,
				'baseline' => $la,
				'current'  => $lb,
			);
		} else {
			$diffs[] = array(
				'line'     => $i + 1,
				'baseline' => null,
				'current'  => null,
			);
		}
	}

	return $diffs;
}

/* -------------------------------------------------------------------------
 * Baseline I/O
 * ---------------------------------------------------------------------- */

function bws_page_snapshot_path( $key ) {
	return BWS_PAGE_SNAPSHOT_DIR . '/' . $key . '.html';
}

function bws_page_snapshot_read( $key ) {
	$path = bws_page_snapshot_path( $key );

	return is_readable( $path ) ? file_get_contents( $path ) : null;
}

/* -------------------------------------------------------------------------
 * The two operations
 * ---------------------------------------------------------------------- */

/**
 * Fetch + normalize every fixture page.
 *
 * Returns array( 'shots' => array( key => normalized|null ), 'errors' => array( key => msg ) ).
 */
function bws_page_snapshot_capture_all( array $opts = array() ) {
	$base  = rtrim( isset( $opts['base_url'] ) ? $opts['base_url'] : BWS_PAGE_SNAPSHOT_DEFAULT_BASE_URL, '/' );
	$pages = isset( $opts['pages'] ) ? $opts['pages'] : bws_page_snapshot_pages();

	$shots  = array();
	$errors = array();

	foreach ( $pages as $key => $page ) {
		$res = bws_page_snapshot_fetch( $base . $page['path'] );

		if ( null !== $res['error'] ) {
			$errors[ $key ] = $res['error'];
			$shots[ $key ]  = null;
			continue;
		}

		$shots[ $key ] = bws_page_snapshot_normalize( $res['body'], $base );
	}

	return array(
		'shots'  => $shots,
		'errors' => $errors,
	);
}

/**
 * Which pages block a baseline write.
 *
 * ALL OR NOTHING, AND THE REASON IS THE FAILURE THAT FOLLOWS A PARTIAL ONE. Writing the
 * reachable pages and printing a warning leaves 8 fresh files beside 1 stale one, which is
 * exactly the state a `git add -A` commits; the stale file then diffs clean forever, so the
 * warning is announced once, at the moment nobody is looking, and is silent from then on.
 * Refusing costs nothing here because bws_page_snapshot_capture_all() completes every fetch
 * before the caller writes anything.
 *
 * A null shot with no recorded error counts too: an unexplained empty capture is the one
 * shape that would otherwise be written out as a legitimate baseline.
 *
 * WHAT THIS CANNOT PROMISE: atomicity. A write can still fail on file 5 of 9, leaving a mixed
 * set — the caller reports that separately, and both docs state the limit rather than implying
 * it away.
 *
 * Pure: no filesystem, no network.
 *
 * @param array $captured Return value of bws_page_snapshot_capture_all().
 * @return array Page keys that make the capture uncommittable; empty means write.
 */
function bws_page_snapshot_capture_blockers( array $captured ) {
	$errors = isset( $captured['errors'] ) ? (array) $captured['errors'] : array();
	$shots  = isset( $captured['shots'] ) ? (array) $captured['shots'] : array();

	$blockers = array_keys( $errors );

	foreach ( $shots as $key => $shot ) {
		if ( null === $shot && ! isset( $errors[ $key ] ) ) {
			$blockers[] = $key;
		}
	}

	sort( $blockers, SORT_STRING );

	return array_values( array_unique( $blockers ) );
}

/**
 * Compare live pages against the committed baseline.
 *
 * Returns a report: per-page status ('same' | 'differs' | 'missing-baseline' |
 * 'fetch-failed'), the diff hunks, and a failure count. REPORTING ONLY — the caller decides
 * what a failure means, because verify.php and the CLI print differently.
 */
function bws_page_snapshot_compare_all( array $opts = array() ) {
	$base  = rtrim( isset( $opts['base_url'] ) ? $opts['base_url'] : BWS_PAGE_SNAPSHOT_DEFAULT_BASE_URL, '/' );
	$pages = isset( $opts['pages'] ) ? $opts['pages'] : bws_page_snapshot_pages();

	$captured = bws_page_snapshot_capture_all(
		array(
			'base_url' => $base,
			'pages'    => $pages,
		)
	);

	$report = array(
		'pages'    => array(),
		'failed'   => 0,
		'compared' => 0,
	);

	foreach ( $pages as $key => $page ) {
		$row = array(
			'key'    => $key,
			'path'   => $page['path'],
			'status' => 'same',
			'diffs'  => array(),
			'detail' => '',
		);

		if ( isset( $captured['errors'][ $key ] ) ) {
			$row['status'] = 'fetch-failed';
			$row['detail'] = $captured['errors'][ $key ];
			$report['failed']++;
			$report['pages'][ $key ] = $row;
			continue;
		}

		$baseline = bws_page_snapshot_read( $key );

		if ( null === $baseline ) {
			$row['status'] = 'missing-baseline';
			$row['detail'] = 'no committed snapshot — run --capture and commit it';
			$report['failed']++;
			$report['pages'][ $key ] = $row;
			continue;
		}

		$report['compared']++;

		$baseline = str_replace( array( "\r\n", "\r" ), "\n", $baseline );
		$current  = $captured['shots'][ $key ];

		if ( $baseline !== $current ) {
			$row['status'] = 'differs';
			$row['diffs']  = bws_page_snapshot_diff( $baseline, $current );
			$report['failed']++;
		}

		$report['pages'][ $key ] = $row;
	}

	return $report;
}

/* -------------------------------------------------------------------------
 * The dependency-version record
 * ---------------------------------------------------------------------- */

/**
 * Compare the recorded dependency versions against what is actually installed.
 *
 * Returns array( 'drift' => string[], 'missing' => string[], 'checked' => int,
 * 'captured' => string ).
 *
 * REPORTING ONLY, and the split matters: a version CHANGE is a warning, because only a
 * human can judge whether moved output is acceptable and the instrument that says WHAT
 * moved is the snapshot diff sitting beside this. A required dependency being ABSENT is a
 * different claim entirely and is not this function's call to make.
 *
 * Needs WordPress: get_plugins() is an admin-side function.
 */
function bws_page_snapshot_env_drift( $record = null ) {
	if ( null === $record ) {
		$record = is_readable( BWS_PAGE_SNAPSHOT_ENV_RECORD ) ? require BWS_PAGE_SNAPSHOT_ENV_RECORD : array();
	}

	$out = array(
		'drift'    => array(),
		'missing'  => array(),
		'checked'  => 0,
		'captured' => isset( $record['captured'] ) ? $record['captured'] : '?',
	);

	if ( ! function_exists( 'get_plugins' ) ) {
		if ( ! defined( 'ABSPATH' ) || ! is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			return $out;
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$installed = get_plugins();

	foreach ( (array) ( isset( $record['plugins'] ) ? $record['plugins'] : array() ) as $file => $spec ) {
		$out['checked']++;

		$label = isset( $spec['label'] ) ? $spec['label'] : $file;

		if ( ! isset( $installed[ $file ] ) ) {
			$out['missing'][] = $label;
			continue;
		}

		$live     = isset( $installed[ $file ]['Version'] ) ? (string) $installed[ $file ]['Version'] : '';
		$recorded = isset( $spec['version'] ) ? (string) $spec['version'] : '';

		if ( '' === $recorded ) {
			// An entry with no recorded version pins nothing. Say that, rather than
			// reporting drift against the empty string on every single run.
			$out['drift'][] = sprintf( '%s: no version recorded — the entry pins nothing.', $label );
			continue;
		}

		if ( $live !== $recorded ) {
			$out['drift'][] = sprintf( '%s: recorded %s, installed %s', $label, $recorded, $live );
		}
	}

	return $out;
}

/* -------------------------------------------------------------------------
 * CLI
 * ---------------------------------------------------------------------- */

/**
 * True when this file was invoked directly as a script, false when it was require()d.
 *
 * `wp eval-file` includes the file in a CLI process, so PHP_SAPI alone cannot tell the two
 * apart — compare the entry script instead.
 */
function bws_page_snapshot_is_cli_entry() {
	if ( 'cli' !== PHP_SAPI ) {
		return false;
	}

	$entry = '';

	if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) ) {
		$entry = $_SERVER['SCRIPT_FILENAME'];
	} elseif ( ! empty( $_SERVER['argv'][0] ) ) {
		$entry = $_SERVER['argv'][0];
	}

	return '' !== $entry && realpath( $entry ) === realpath( __FILE__ );
}

if ( bws_page_snapshot_is_cli_entry() ) {
	$bws_argv     = isset( $_SERVER['argv'] ) ? $_SERVER['argv'] : array();
	$bws_capture  = in_array( '--capture', $bws_argv, true );
	$bws_base_url = BWS_PAGE_SNAPSHOT_DEFAULT_BASE_URL;

	foreach ( $bws_argv as $bws_arg ) {
		if ( 0 === strpos( $bws_arg, '--base-url=' ) ) {
			$bws_base_url = substr( $bws_arg, strlen( '--base-url=' ) );
		}
	}

	$bws_pages = bws_page_snapshot_pages();

	if ( ! $bws_pages ) {
		fwrite( STDERR, "No fixture pages found in the manifest — is BWS_PAGE_SNAPSHOT_MANIFEST right?\n" );
		exit( 2 );
	}

	if ( $bws_capture ) {
		if ( ! is_dir( BWS_PAGE_SNAPSHOT_DIR ) && ! mkdir( BWS_PAGE_SNAPSHOT_DIR, 0777, true ) && ! is_dir( BWS_PAGE_SNAPSHOT_DIR ) ) {
			fwrite( STDERR, 'Cannot create ' . BWS_PAGE_SNAPSHOT_DIR . "\n" );
			exit( 2 );
		}

		$bws_res = bws_page_snapshot_capture_all(
			array(
				'base_url' => $bws_base_url,
				'pages'    => $bws_pages,
			)
		);

		// REFUSE BEFORE WRITING ANYTHING. Every fetch is already done at this point, so a
		// single unreachable page means no file is touched at all — see
		// bws_page_snapshot_capture_blockers() for why a warned-about partial baseline is
		// worse than none.
		$bws_blockers = bws_page_snapshot_capture_blockers( $bws_res );

		if ( $bws_blockers ) {
			foreach ( $bws_blockers as $bws_key ) {
				printf(
					"[FAIL] %-26s %s — %s\n",
					$bws_key,
					isset( $bws_pages[ $bws_key ]['path'] ) ? $bws_pages[ $bws_key ]['path'] : '?',
					isset( $bws_res['errors'][ $bws_key ] ) ? $bws_res['errors'][ $bws_key ] : 'captured nothing, with no error reported'
				);
			}

			printf(
				"\nNOTHING WRITTEN — %d of %d page(s) unreachable. The baseline on disk is untouched.\n",
				count( $bws_blockers ),
				count( $bws_pages )
			);

			exit( 1 );
		}

		$bws_bad = 0;

		foreach ( $bws_pages as $bws_key => $bws_page ) {
			$bws_written = file_put_contents( bws_page_snapshot_path( $bws_key ), $bws_res['shots'][ $bws_key ] );

			if ( false === $bws_written ) {
				// A capture that PRINTS success while writing nothing leaves the previous
				// baseline in place, and that stale file then diffs clean forever.
				printf( "[FAIL] %-26s could not write %s\n", $bws_key, bws_page_snapshot_path( $bws_key ) );
				$bws_bad++;
				continue;
			}

			printf( "[capture] %-24s %s (%d bytes normalized)\n", $bws_key, $bws_page['path'], $bws_written );
		}

		// The residual case the refusal above cannot reach: every page fetched, but a write
		// failed partway through. That DOES leave a mixed set on disk, and saying so is the
		// only thing left to do about it.
		echo $bws_bad
			? "\nCAPTURE INCOMPLETE — {$bws_bad} page(s) fetched but not written, so the baseline on disk is MIXED. Do not commit it; re-run.\n"
			: "\nBaseline written to tools/test/snapshots/. Re-record env-versions.php in the SAME commit.\n";

		exit( $bws_bad ? 1 : 0 );
	}

	$bws_report = bws_page_snapshot_compare_all(
		array(
			'base_url' => $bws_base_url,
			'pages'    => $bws_pages,
		)
	);

	foreach ( $bws_report['pages'] as $bws_key => $bws_row ) {
		if ( 'same' === $bws_row['status'] ) {
			printf( "[PASS] %-26s %s\n", $bws_key, $bws_row['path'] );
			continue;
		}

		printf(
			"[FAIL] %-26s %s — %s\n",
			$bws_key,
			$bws_row['path'],
			'differs' === $bws_row['status'] ? count( $bws_row['diffs'] ) . ' line(s) differ' : $bws_row['detail']
		);

		foreach ( $bws_row['diffs'] as $bws_d ) {
			if ( null === $bws_d['baseline'] ) {
				continue;
			}

			printf( "         L%-6d - %s\n", $bws_d['line'], substr( $bws_d['baseline'], 0, 160 ) );
			printf( "         %-7s + %s\n", '', substr( $bws_d['current'], 0, 160 ) );
		}
	}

	echo $bws_report['failed']
		? "\nPAGE SNAPSHOTS FAILED ({$bws_report['failed']} of " . count( $bws_report['pages'] ) . ")\n"
		: "\nPAGE SNAPSHOTS PASSED ({$bws_report['compared']} pages)\n";

	exit( $bws_report['failed'] ? 1 : 0 );
}
