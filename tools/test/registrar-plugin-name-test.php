<?php
/**
 * Standalone unit harness for bws_gb_registrar_plugin_name() — the derivation that
 * turns a registrar's FILE into the name of the plugin, theme or core directory that
 * ships it (includes/helpers/gb-registration-boundary.php), and for the phrase helper
 * that leads with it.
 *
 * The real file is loaded, not copied. It defines functions only and guards on ABSPATH,
 * so it loads inert once ABSPATH is defined.
 *
 * WHY THIS EXISTS. GB's registry holds `title` — the other TAG's editor label, "Term
 * Title" — and the callable, and nothing else. A tag label in an owner slot reads as an
 * owner while naming nothing anybody can deactivate, so every report surface derives the
 * owner from the callable's path instead. That derivation is pure path work plus one
 * WordPress header read, and the pure half is the half that breaks silently: a root that
 * stops matching returns '', the caller falls back to the file, and the page still looks
 * reasonable. Nothing else in the suite reaches this function — control-order-test.php
 * runs against a stubbed world with no WP_PLUGIN_DIR, so every path through it returns ''
 * there and its assertions pass on the fallback.
 *
 * SCOPE — the pure half:
 *   - re-absolutizing an ABSPATH-relative path (what bws_gb_tag_registrar_file() returns
 *     for a file under the root) before any root comparison;
 *   - matching against WP_PLUGIN_DIR, WPMU_PLUGIN_DIR, the theme root and core;
 *   - the realpath arm, which is what makes a symlinked plugins directory resolve at all;
 *   - the folder-vs-single-file split inside a root;
 *   - the '' answers: no root, no header, no source. A WRONG name is worse than none,
 *     because it sends a reader to deactivate the wrong plugin.
 *
 * NOT IN SCOPE — get_plugins() / get_plugin_data() / wp_get_theme() themselves. Those are
 * WordPress's, they are stubbed here, and what they return on a real site is measured on
 * the fixture instead: GB Query Enhancements holds `term_title` there, and `wp eval` reads
 * back "GB Query Enhancements" through this function (measured 2026-08-28, recorded in
 * docs/update-triggers.md).
 *
 * THE FIXTURE TREE IS REAL FILES IN A TEMP DIRECTORY, not a virtual filesystem. The
 * function calls realpath(), which only answers about a path that exists, so a stubbed
 * filesystem would make the one arm most likely to rot the one arm untested.
 *
 * Run:  php tools/test/registrar-plugin-name-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

$failures = 0;
$count    = 0;

function assert_same( $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

/* ── the fixture tree ────────────────────────────────────────────────────────── */

$root = rtrim( strtr( (string) sys_get_temp_dir(), '\\', '/' ), '/' ) . '/bws-registrar-name-' . getmypid();

$tree = array(
	'/wp-includes',
	'/wp-content/plugins/acme-tags/includes',
	'/wp-content/plugins/headerless',
	'/wp-content/mu-plugins',
	'/wp-content/themes/mytheme',
	'/elsewhere',
);

foreach ( $tree as $dir ) {
	if ( ! is_dir( $root . $dir ) ) {
		mkdir( $root . $dir, 0777, true );
	}
}

foreach ( array(
	'/wp-includes/formatting.php',
	'/wp-content/plugins/acme-tags/acme-tags.php',
	'/wp-content/plugins/acme-tags/includes/tags.php',
	'/wp-content/plugins/headerless/headerless.php',
	'/wp-content/plugins/one-file.php',
	'/wp-content/mu-plugins/must-load.php',
	'/wp-content/themes/mytheme/functions.php',
	'/elsewhere/stray.php',
) as $file ) {
	file_put_contents( $root . $file, "<?php\n" );
}

register_shutdown_function( static function () use ( $root ) {
	$it = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $it as $path ) {
		$path->isDir() ? @rmdir( $path->getPathname() ) : @unlink( $path->getPathname() );
	}
	@rmdir( $root );
} );

/* ── the world the function runs in ──────────────────────────────────────────── */

define( 'ABSPATH', $root . '/' );

// WP_PLUGIN_DIR IS SPELT WITH A DETOUR ON PURPOSE. `/wp-content/../wp-content/plugins`
// names the same directory as the plain spelling and does not string-match a path under
// it, so the plain prefix test fails and only the realpath arm can answer. That is the
// arm a symlinked plugins directory needs, and it is untestable by symlink on Windows
// without elevation. Everything else here matches on the plain spelling.
define( 'WP_PLUGIN_DIR', $root . '/wp-content/../wp-content/plugins' );
define( 'WPMU_PLUGIN_DIR', $root . '/wp-content/mu-plugins' );

$GLOBALS['bws_get_plugins_calls'] = 0;

function __( $text, $domain = null ) { return $text; }

function get_theme_root() { return rtrim( ABSPATH, '/' ) . '/wp-content/themes'; }

function wp_get_theme( $slug = '' ) {
	return new class( $slug ) {
		private $slug;
		public function __construct( $slug ) { $this->slug = (string) $slug; }
		public function get( $header ) {
			return 'mytheme' === $this->slug && 'Name' === $header ? 'My Theme' : '';
		}
	};
}

function get_plugins( $folder = '' ) {
	$GLOBALS['bws_get_plugins_calls']++;

	// Keyed by file name relative to the folder, which is what core returns when a
	// folder is named. `headerless` holds a php file with no plugin header, so core
	// would report nothing for it.
	$by_folder = array(
		'/acme-tags'  => array( 'acme-tags.php' => array( 'Name' => 'Acme Tags' ) ),
		'/headerless' => array(),
	);

	return $by_folder[ $folder ] ?? array();
}

function get_plugin_data( $file, $markup = true, $translate = true ) {
	$names = array(
		'one-file.php'   => 'One File Plugin',
		'must-load.php'  => 'Must Load',
	);

	return array( 'Name' => $names[ basename( (string) $file ) ] ?? '' );
}

require __DIR__ . '/../../includes/helpers/gb-registration-boundary.php';

/* ── §1 the plugin arm ───────────────────────────────────────────────────────── */

echo "§1 — a file inside a plugin folder names the plugin\n";

assert_same(
	'an ABSPATH-relative path resolves, which is the shape a file under the root arrives in',
	'Acme Tags',
	bws_gb_registrar_plugin_name( 'wp-content/plugins/acme-tags/includes/tags.php' )
);

assert_same(
	'an absolute path resolves the same, which is the shape a symlinked tree arrives in',
	'Acme Tags',
	bws_gb_registrar_plugin_name( $root . '/wp-content/plugins/acme-tags/acme-tags.php' )
);

assert_same(
	'a Windows-style separator normalizes rather than failing the prefix test',
	'Acme Tags',
	bws_gb_registrar_plugin_name( strtr( $root . '/wp-content/plugins/acme-tags/includes/tags.php', '/', '\\' ) )
);

// THE DETOUR SPELLING OF WP_PLUGIN_DIR IS THE POINT: every assertion above matched
// through realpath(), because the constant does not string-prefix any of those paths.
// Losing that arm turns a symlinked plugins directory — the normal case on this
// developer's own environment — into an unnamed owner.
assert_same(
	'the constant really is spelt so that only the realpath arm can match',
	false,
	0 === strpos( $root . '/wp-content/plugins/acme-tags/acme-tags.php', WP_PLUGIN_DIR . '/' )
);

assert_same(
	'a single-file plugin at the plugins root is read directly, with no folder to scan',
	'One File Plugin',
	bws_gb_registrar_plugin_name( 'wp-content/plugins/one-file.php' )
);

/* ── §2 the other roots ──────────────────────────────────────────────────────── */

echo "\n§2 — mu-plugins, themes, core\n";

assert_same(
	'a must-use plugin is named and marked, because it is not in the Plugins list',
	'Must Load (must-use)',
	bws_gb_registrar_plugin_name( 'wp-content/mu-plugins/must-load.php' )
);

assert_same(
	'a theme is named and marked, because deactivating it is a different action',
	'My Theme (theme)',
	bws_gb_registrar_plugin_name( 'wp-content/themes/mytheme/functions.php' )
);

assert_same(
	'a core file names WordPress, not an empty owner',
	'WordPress core',
	bws_gb_registrar_plugin_name( 'wp-includes/formatting.php' )
);

/* ── §3 the '' answers ───────────────────────────────────────────────────────── */

echo "\n§3 — what cannot be told is '', never a guess\n";

assert_same(
	'a path under no root names nobody',
	'',
	bws_gb_registrar_plugin_name( 'elsewhere/stray.php' )
);

assert_same(
	'a plugin folder with no readable header names nobody rather than the folder slug',
	'',
	bws_gb_registrar_plugin_name( 'wp-content/plugins/headerless/headerless.php' )
);

assert_same(
	'an empty source names nobody and reads no headers',
	'',
	bws_gb_registrar_plugin_name( '' )
);

assert_same(
	'the plugins root itself is not a file in a plugin',
	'',
	bws_gb_registrar_plugin_name( 'wp-content/plugins' )
);

/* ── §4 memoization ──────────────────────────────────────────────────────────── */

echo "\n§4 — one scan per source path\n";

// The notice channel runs on front-end requests and a settings page renders several
// rows, so a repeat must not re-read headers.
$before = $GLOBALS['bws_get_plugins_calls'];
bws_gb_registrar_plugin_name( 'wp-content/plugins/acme-tags/includes/tags.php' );
bws_gb_registrar_plugin_name( 'wp-content/plugins/acme-tags/includes/tags.php' );

assert_same( 'a repeated source path reads no headers again', $before, $GLOBALS['bws_get_plugins_calls'] );

assert_same(
	'and still answers',
	'Acme Tags',
	bws_gb_registrar_plugin_name( 'wp-content/plugins/acme-tags/includes/tags.php' )
);

/* ── §5 the phrase every report surface shares ───────────────────────────────── */

echo "\n§5 — the phrase leads with the owner, keeps the tag label as evidence\n";

assert_same(
	'the owner leads and the tag label follows it, never the other way round',
	'Acme Tags ("Text", wp-content/plugins/acme-tags/includes/tags.php)',
	bws_gb_other_registrar_phrase( 'Text', 'wp-content/plugins/acme-tags/includes/tags.php' )
);

assert_same(
	'an unnamed owner falls back to the label and the file, which is what this read before',
	'"Text" (elsewhere/stray.php)',
	bws_gb_other_registrar_phrase( 'Text', 'elsewhere/stray.php' )
);

// AN OWNER IS ONLY EVER DERIVED FROM A FILE, so a named owner always has that file to
// show and "owner with no evidence" is unreachable rather than merely untested. A tag
// label can be missing — GB accepts a registration whose title is '' — and this is what
// that reads as.
assert_same(
	'a named owner with no tag label keeps the file as its evidence',
	'Acme Tags (wp-content/plugins/acme-tags/acme-tags.php)',
	bws_gb_other_registrar_phrase( '', 'wp-content/plugins/acme-tags/acme-tags.php' )
);

assert_same(
	'knowing neither says so, rather than printing an empty pair of quotes',
	'another plugin',
	bws_gb_other_registrar_phrase( '', '' )
);

assert_same(
	'a label carrying markup is escaped where it enters our sentence',
	'"&lt;b&gt;Text&lt;/b&gt;" (elsewhere/stray.php)',
	bws_gb_other_registrar_phrase( '<b>Text</b>', 'elsewhere/stray.php' )
);

/* ── result ──────────────────────────────────────────────────────────────────── */

echo "\n";

if ( $failures > 0 ) {
	echo "FAILED: {$failures} of {$count}\n";
	exit( 1 );
}

echo "PASSED: {$count}/{$count}\n";
exit( 0 );
