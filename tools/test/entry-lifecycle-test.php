<?php
/**
 * Standalone harness holding the two FW-38 axes in AGREEMENT.
 *
 * Two axes, never derived from each other: a tag entry's hand-set `lifecycle` (intent) and
 * how the tag registers with GB (listed, hidden, or not at all). Lazy loading of entries
 * (FW-175) means the registration path can never look an entry up at run time, so nothing at
 * run time can notice the two drifting apart. This harness is the only place they meet. It
 * loads the real registry and runs the real registration passes against a capturing stub of
 * GB's registrar, then hands both populations to ONE checker, check_agreement().
 *
 * THE MEANING OF `lifecycle` IS OWNED BY MigrationRegistry::register()'s PHPDoc. What follows
 * is the consequence a run observes, plus the clauses an assertion below mechanically pins.
 *
 * TWO ASSERTIONS ARE VACUOUS ON REAL DATA. Measured 2026-10-24, no tag entry shares a name
 * with a tag we register, so "listed-tag-has-no-entry" and "retired-registered-is-hidden"
 * hold trivially against the shipped tree, and so does the absent `prefix_removed` key. A pass
 * that never had a population to fail on is not evidence the pin works, so §3 feeds each
 * assertion a fixture that violates it to the SAME checker and requires that assertion, by
 * name, in the report (precedent: replay-vacuity-test.php).
 *
 * Run:  php tools/test/entry-lifecycle-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );
define( 'BWS_DYNAMIC_TAGS_PATH', $root . '/' );

// ---------------------------------------------------------------------------
// WP + GB stubs. The same minimal set control-order-test.php registers against.
// ---------------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = null ) { return $s; }
}
foreach ( array( 'add_action', 'add_filter', 'do_action', 'register_rest_route' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return null; }" );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { return $value; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $default; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $k ) ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
}

/** Capturing stand-in for GB's registrar: keeps every args array, answers get_tags(). */
class GenerateBlocks_Register_Dynamic_Tag {
	public static array $tags = array();

	public function __construct( $args ) {
		self::$tags[ $args['tag'] ] = $args;
	}

	public static function get_tags() {
		return self::$tags;
	}
}

class GenerateBlocks_Dynamic_Tags {}
class GenerateBlocks_Dynamic_Tag_Callbacks {
	public static function output( $v, $o = array(), $i = null ) { return $v; }
}

// ---------------------------------------------------------------------------
// Load in the plugin's own order, then run the init pass in the bootstrap's order
// (bws_dynamic_tags_register_all()). The migration passes matter as much as the
// registration ones: the modifier generator skips a name an earlier entry claims.
// ---------------------------------------------------------------------------

require $root . '/autoload.php';

foreach ( array(
	'includes/helpers/gb-output-boundary.php',
	'includes/helpers/gb-registration-boundary.php',
	'includes/helpers/image-helpers.php',
	'includes/helpers/traversal-pipeline.php',
	'includes/helpers/field-helpers.php',
	'includes/helpers/link-helpers.php',
	'includes/helpers/preview-helpers.php',
	'includes/helpers/content-helpers.php',
	'includes/helpers/datetime-helpers.php',
	'includes/helpers/taxonomy-helpers.php',
	'includes/helpers/join-helpers.php',
	'includes/helpers/registration-helpers.php',
	'includes/helpers/serialization-order.php',
	'includes/helpers/slot-fold.php',
	'includes/helpers/slot-fold-compile.php',
	'includes/helpers/slot-fold-migrate.php',
	'includes/tags/base-shared.php',
	'includes/tags/base-tags.php',
	'includes/tags/content-tags.php',
	'includes/tags/image-tags.php',
	'includes/tags/datetime-tags.php',
	'includes/tags/email-tags.php',
	'includes/tags/phone-tags.php',
	'includes/tags/table-tags.php',
	'includes/tags/fn-tags.php',
	'includes/tags/taxonomy-tags.php',
	'includes/tags/deprecated-tags.php',
) as $rel ) {
	require_once $root . '/' . $rel;
}

bws_register_base_tags();
bws_register_email_tag();
bws_register_phone_tag();
bws_register_table_tag();
bws_register_call_tag();
\BWS\DynamicTags\TagTemplateRegistry::generate_base_try_tags();
bws_register_nxm_tag_migrations();
bws_register_early_deprecated_tag_migrations();
bws_register_option_migrations();
bws_register_modifier_root_migrations( 'term', 'term', array( 'since' => '1.20.0', 'lifecycle' => 'retired' ) );
bws_register_deprecated_tags();

// ---------------------------------------------------------------------------

$failures = 0;
$count    = 0;

function ok( string $label ): void {
	global $count;
	$count++;
	echo "  ok   {$label}\n";
}

function fail( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	$failures++;
	echo "  FAIL {$label}\n";
	echo '       expected: ' . json_encode( $expected ) . "\n";
	echo '       actual:   ' . json_encode( $actual ) . "\n";
}

function assert_same( string $label, $expected, $actual ): void {
	if ( $expected === $actual ) {
		ok( $label );
		return;
	}
	fail( $label, $expected, $actual );
}

/** PHP source with comments dropped, so a docblock that MENTIONS a symbol is not a reference. */
function code_without_comments( string $src ): string {
	$out = '';
	foreach ( token_get_all( $src ) as $t ) {
		if ( is_array( $t ) ) {
			if ( T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) {
				continue;
			}
			$out .= $t[1];
		} else {
			$out .= $t;
		}
	}
	return $out;
}

/**
 * Whether classification code reads GB's registry or our own record of it. Placement is a
 * hand-set fact (CONTEXT.md I10), so a classifier that asked would let load order or a
 * settings toggle move an entry between boxes.
 */
function reads_registration_state( string $php ): bool {
	return 1 === preg_match( '/GenerateBlocks_|\bget_tags\b|\bbws_gb_tags_we_registered\b/', code_without_comments( $php ) );
}

/** The source of one method, comments included, read off its file by reflection. */
function method_source( string $class, string $method ): string {
	$r     = new ReflectionMethod( $class, $method );
	$lines = file( $r->getFileName() );
	return '<?php ' . implode( '', array_slice( $lines, $r->getStartLine() - 1, $r->getEndLine() - $r->getStartLine() + 1 ) );
}

/**
 * THE CHECKER. Real data and every violating fixture go through this one function.
 *
 * @param array[] $entries    Tag entries (MigrationRegistry shape).
 * @param array[] $registered Name => record from bws_gb_tags_we_registered() ('hidden' => bool).
 * @param array   $sources    Path => PHP source of the shipped entry / generator-call sites.
 * @return string[] Names of the assertions that were violated, each with its subject.
 */
function check_agreement( array $entries, array $registered, array $sources ): array {
	$bad = array();

	foreach ( $entries as $e ) {
		$name = (string) ( $e['match_tag'] ?? '' );
		$life = $e['lifecycle'] ?? 'retired';

		if ( ! in_array( $life, array( 'deprecated', 'retired' ), true ) ) {
			$bad[] = "entry-lifecycle-is-deprecated-or-retired: {$name}";
		}
		if ( array_key_exists( 'prefix_removed', $e ) ) {
			$bad[] = "no-prefix-removed-key-on-entries: {$name}";
		}
		if ( ! isset( $registered[ $name ] ) ) {
			continue;
		}
		// Any entry at all means the tag is not active, and a tag in the picker must be active:
		// `deprecated` + listed is forbidden (decided 2026-10-26), so deprecating a tag means
		// hiding it in the same change. §3's first listed fixture is a `deprecated` entry.
		if ( empty( $registered[ $name ]['hidden'] ) ) {
			$bad[] = "listed-tag-has-no-entry: {$name}";
		}
		if ( 'retired' === $life && empty( $registered[ $name ]['hidden'] ) ) {
			$bad[] = "retired-registered-is-hidden: {$name}";
		}
	}

	foreach ( $sources as $path => $php ) {
		if ( 1 === preg_match( '/[\'"]prefix_removed[\'"]\s*=>/', code_without_comments( $php ) ) ) {
			$bad[] = "no-prefix-removed-key-in-shipped-calls: {$path}";
		}
	}

	return $bad;
}

/** Entry fixture. */
function entry( string $name, ?string $life, array $extra = array() ): array {
	$e = array( 'type' => 'tag', 'match_tag' => $name, 'gb_type' => 'deprecated' );
	if ( null !== $life ) {
		$e['lifecycle'] = $life;
	}
	return $e + $extra;
}

// ===========================================================================
// §1. The real tree
// ===========================================================================

$real_entries    = \BWS\DynamicTags\MigrationRegistry::get_by_type( 'tag' );
$real_registered = bws_gb_tags_we_registered();

$shipped = array();
foreach ( array_merge( array( $root . '/bws-gb-dynamic-tags-extensions.php' ), glob( $root . '/includes/{,*/,*/*/}*.php', GLOB_BRACE ) ) as $path ) {
	$shipped[ substr( $path, strlen( $root ) + 1 ) ] = (string) file_get_contents( $path );
}

// Positive controls: a green run over an empty population proves nothing.
assert_same( 'the real run loaded tag entries', true, count( $real_entries ) > 0 );
assert_same( 'the real run captured registered tags', true, count( $real_registered ) > 0 );
assert_same( 'the real run scanned shipped sources', true, count( $shipped ) > 1 );

assert_same( 'real tree: every assertion holds', array(), check_agreement( $real_entries, $real_registered, $shipped ) );

// ===========================================================================
// §2. Placement pin (CONTEXT.md I10: placement never reads registration state)
// ===========================================================================

foreach ( array( 'is_entry_live', 'get_by_type_and_liveness', 'register' ) as $fn ) {
	assert_same(
		"placement: MigrationRegistry::{$fn}() does not read GB's registry",
		false,
		reads_registration_state( method_source( \BWS\DynamicTags\MigrationRegistry::class, $fn ) )
	);
}
// The other half of "no run-time lookup between the axes": the boundary never reads entries.
assert_same(
	'the registration boundary does not read MigrationRegistry',
	false,
	false !== stripos( code_without_comments( (string) file_get_contents( $root . '/includes/helpers/gb-registration-boundary.php' ) ), 'MigrationRegistry' )
);
// Can the pin fail? The detector must see each way a classifier could ask.
foreach ( array(
	'GB class'      => 'if ( \GenerateBlocks_Register_Dynamic_Tag::get_tags() ) {}',
	'get_tags call' => '$t = $registrar->get_tags();',
	'our record'    => '$r = bws_gb_tags_we_registered();',
) as $label => $php ) {
	assert_same( "placement pin catches a classifier reading the {$label}", true, reads_registration_state( '<?php ' . $php ) );
}
assert_same( 'placement pin ignores a comment that only mentions the registry', false, reads_registration_state( "<?php // not GenerateBlocks_ nor get_tags\n/** bws_gb_tags_we_registered */ return 1;" ) );

// ===========================================================================
// §3. Each assertion can fail: violating fixtures through the same checker
// ===========================================================================

$vis  = array( 'dep_listed' => array( 'hidden' => false ) );
$hid  = array( 'dep_hidden' => array( 'hidden' => true ) );
$prefix_call = "<?php bws_register_modifier_root_migrations( 'term', 'term', array( 'prefix_removed' => true ) );";

// [ label, entries, registered, sources, the assertion that must be reported ]
$violations = array(
	array(
		'an unknown lifecycle value',
		array( entry( 'x', 'removed' ) ), array(), array(),
		'entry-lifecycle-is-deprecated-or-retired',
	),
	array(
		'an entry on a visibly registered tag',
		array( entry( 'dep_listed', 'deprecated' ) ), $vis, array(),
		'listed-tag-has-no-entry',
	),
	array(
		'a retired entry on a registered tag that is not hidden',
		array( entry( 'dep_listed', 'retired' ) ), $vis, array(),
		'retired-registered-is-hidden',
	),
	array(
		'a prefix_removed key on an entry',
		array( entry( 'x', 'retired', array( 'prefix_removed' => true ) ) ), array(), array(),
		'no-prefix-removed-key-on-entries',
	),
	array(
		'a prefix_removed argument in a shipped generator call',
		array(), array(), array( 'bootstrap.php' => $prefix_call ),
		'no-prefix-removed-key-in-shipped-calls',
	),
);
foreach ( $violations as list( $label, $entries, $registered, $sources, $must ) ) {
	$names = array_map(
		static function ( $line ) { return strstr( $line, ':', true ); },
		check_agreement( $entries, $registered, $sources )
	);
	assert_same( "violating fixture ({$label}) is reported as {$must}", true, in_array( $must, $names, true ) );
}

// Clean fixtures: the checker is not simply failing everything.
assert_same(
	'deprecated + hidden is a legal pairing (no real population until FW-81)',
	array(),
	check_agreement( array( entry( 'dep_hidden', 'deprecated' ) ), $hid, array() )
);
assert_same(
	'retired + hidden is a legal pairing',
	array(),
	check_agreement( array( entry( 'dep_hidden', 'retired' ) ), $hid, array() )
);
assert_same(
	'retired + not registered (migration-only) is legal',
	array(),
	check_agreement( array( entry( 'gone', 'retired' ) ), $hid, array() )
);
assert_same(
	'an absent lifecycle reads as retired, which is legal when unregistered',
	array(),
	check_agreement( array( entry( 'gone', null ) ), array(), array() )
);
assert_same(
	'an absent lifecycle reads as retired, so a registered listed tag still fails retired-registered-is-hidden',
	true,
	in_array( 'retired-registered-is-hidden', array_map( static function ( $l ) { return strstr( $l, ':', true ); }, check_agreement( array( entry( 'dep_listed', null ) ), $vis, array() ) ), true )
);
assert_same(
	'a prefix_removed mention in a comment is not a call',
	array(),
	check_agreement( array(), array(), array( 'a.php' => '<?php // was prefix_removed => true' ) )
);

// ---------------------------------------------------------------------------

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures} of {$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
