<?php
/**
 * Standalone harness for the `description` every base tag registers, and for its tie to the
 * README tag overview.
 *
 * REQUIRES THE REAL FILES, not copies of them: it loads the plugin's registration code and
 * runs the real registration passes against a capturing stand-in for GB's registrar (the
 * entry-lifecycle-test.php setup), then reads what each tag handed GB. It also reads the real
 * README.md table. No WordPress, no GenerateBlocks, no network.
 *
 * WHAT IT HOLDS
 *
 * 1. LINT. A description is plain-English author text that GB shows under the tag picker and
 *    other plugins read and run through `sanitize_text_field()`. It must be non-empty, and carry
 *    none of `<`, `>`, a backtick, `{{`, an em dash, a `%` escape, or any whitespace that
 *    sanitizing would collapse or trim, so the sanitizer returns it byte for byte.
 *
 * 2. README TIE. README's tag overview "Returns" cell is the source and each base tag's
 *    description mirrors it, so the two cannot drift apart unseen. Equality is read after
 *    stripping backticks and unwrapping links. The ONLY declared divergence is the angle
 *    brackets in `content` and `table`: README says `<div>`, the description says "div"
 *    (a description is plain text, so angle brackets are spelled out). `try_*` descriptions are generated
 *    from the base text and have no README cell, so they are out of this tie.
 *
 * Both checks are fed a fixture that violates them, and the report must name the rule: the
 * real tree satisfies every rule, so a pass alone would not show the checker can fail
 * (precedent: entry-lifecycle-test.php section 3).
 *
 * LENGTH is not asserted. GB's picker wraps a description to any length without cutting it off
 * (measured in the testbed editor), so brevity is an authoring preference, not a rule.
 *
 * Run:  php tools/test/tag-description-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );
define( 'BWS_DYNAMIC_TAGS_PATH', $root . '/' );

// ---------------------------------------------------------------------------
// WP + GB stubs: only what registration touches.
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

/** Capturing stand-in for GB's registrar: keeps every args array. */
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

/** The twelve base tags. Order is irrelevant: everything is keyed by tag name. */
const BASE_TAGS = array(
	'text', 'title', 'permalink', 'content', 'image', 'datetime_single',
	'datetime_range', 'email', 'phone', 'join', 'table', 'call',
);

/**
 * The declared README-to-description divergence: tags whose README cell spells an angle-bracket
 * tag the description must spell in words. Anything not listed here must match exactly.
 */
const ANGLE_BRACKET_TAGS = array( 'content', 'table' );
const ANGLE_BRACKET_WORDS = array(
	'<div>' => 'div',
);

/**
 * Lint rules for one description. Returns the NAMES of the rules it breaks, so a fixture can
 * require a rule by name. The `%` and whitespace rules exist because `sanitize_text_field()`
 * would change a string carrying them, and the point is that it changes nothing.
 */
function lint_description( $description ): array {
	$broken = array();
	if ( ! is_string( $description ) || '' === trim( $description ) ) {
		return array( 'empty' );
	}
	$checks = array(
		'less-than'    => false !== strpos( $description, '<' ),
		'greater-than' => false !== strpos( $description, '>' ),
		'backtick'     => false !== strpos( $description, '`' ),
		'brace-pair'   => false !== strpos( $description, '{{' ),
		'em-dash'      => false !== strpos( $description, "\u{2014}" ),
		'percent-hex'  => 1 === preg_match( '/%[a-f0-9]{2}/i', $description ),
		'whitespace'   => $description !== trim( preg_replace( '/\s+/', ' ', $description ) ),
	);
	foreach ( $checks as $name => $hit ) {
		if ( $hit ) {
			$broken[] = $name;
		}
	}
	return $broken;
}

/** A README cell as it reads after stripping backticks and unwrapping links. */
function normalize_readme_cell( string $cell ): string {
	$cell = preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $cell );
	$cell = str_replace( '`', '', $cell );
	return trim( preg_replace( '/\s+/', ' ', $cell ) );
}

/**
 * The "Returns" cell of each tag in the README tag overview table, keyed by tag name and
 * normalized. A row starts with the tag in its first cell, bare or as a link.
 */
function readme_returns( string $markdown ): array {
	$rows = array();
	$in   = false;
	foreach ( preg_split( '/\R/', $markdown ) as $line ) {
		// Only the first table headed "Tag": later tables repeat tag names with other content.
		if ( ! $in ) {
			$in = 0 === strpos( $line, '| Tag |' );
			continue;
		}
		if ( 0 !== strpos( $line, '|' ) ) {
			break;
		}
		if ( 1 !== preg_match( '/^\|\s*\[?`([a-z_]+)`/', $line, $m ) ) {
			continue;
		}
		$cells = array_map( 'trim', explode( '|', trim( $line, " \t|" ) ) );
		if ( count( $cells ) < 2 ) {
			continue;
		}
		$rows[ $m[1] ] = normalize_readme_cell( $cells[1] );
	}
	return $rows;
}

/**
 * Names the tags whose description disagrees with README. $returns is README's cells,
 * $described is tag => registered description.
 */
function readme_disagreements( array $returns, array $described ): array {
	$bad = array();
	foreach ( BASE_TAGS as $tag ) {
		if ( ! isset( $returns[ $tag ] ) ) {
			$bad[] = $tag . ':no-readme-row';
			continue;
		}
		$expected = $returns[ $tag ];
		if ( in_array( $tag, ANGLE_BRACKET_TAGS, true ) ) {
			$expected = strtr( $expected, ANGLE_BRACKET_WORDS );
		}
		if ( ( $described[ $tag ] ?? null ) !== $expected ) {
			$bad[] = $tag;
		}
	}
	return $bad;
}

// ---------------------------------------------------------------------------
// 1. Every base tag registers a description that survives the sanitizer unchanged.
// ---------------------------------------------------------------------------

echo "1. Lint, over the real registrations\n";

$registered = GenerateBlocks_Register_Dynamic_Tag::get_tags();
$described  = array();

foreach ( BASE_TAGS as $tag ) {
	assert_same( "{$tag} is registered", true, isset( $registered[ $tag ] ) );
	$described[ $tag ] = $registered[ $tag ]['description'] ?? null;
	assert_same( "{$tag} description breaks no lint rule", array(), lint_description( $described[ $tag ] ) );
}

// ---------------------------------------------------------------------------
// 2. The checker can fail: one fixture per rule, required BY NAME.
// ---------------------------------------------------------------------------

echo "2. Lint rules each fire on a violating fixture\n";

foreach ( array(
	'empty'        => '',
	'less-than'    => 'Put it in a <div block.',
	'greater-than' => 'More > less.',
	'backtick'     => 'The `text` tag.',
	'brace-pair'   => 'Use {{text}} here.',
	'em-dash'      => "A tag \u{2014} a thing.",
	'percent-hex'  => 'Fifty%20percent.',
	'whitespace'   => 'Two  spaces.',
) as $rule => $fixture ) {
	assert_same( "rule '{$rule}' fires", true, in_array( $rule, lint_description( $fixture ), true ) );
}
assert_same( 'a clean sentence breaks nothing', array(), lint_description( 'Plain text, nothing else.' ) );

// ---------------------------------------------------------------------------
// 3. Each base description equals its README "Returns" cell.
// ---------------------------------------------------------------------------

echo "3. README tie\n";

$readme_path = BWS_DYNAMIC_TAGS_PATH . 'README.md';
assert_same( 'README.md is readable', true, is_readable( $readme_path ) );
$returns = readme_returns( (string) file_get_contents( $readme_path ) );

assert_same( 'README tie holds for the twelve base tags', array(), readme_disagreements( $returns, $described ) );

// The divergence is declared, so it must still be needed: a README that stopped spelling the
// angle brackets would leave the declaration silently unused.
foreach ( ANGLE_BRACKET_TAGS as $tag ) {
	assert_same(
		"{$tag}: README still spells <div>, so the declared divergence is live",
		true,
		false !== strpos( $returns[ $tag ] ?? '', '<div>' )
	);
}

// ---------------------------------------------------------------------------
// 4. The tie can fail: fixtures that disagree, required BY NAME.
// ---------------------------------------------------------------------------

echo "4. README tie fires on a disagreeing fixture\n";

$drifted = $described;
$drifted['permalink'] = $described['permalink'] . ' Extra.';
assert_same( 'an edited description names its tag', array( 'permalink' ), readme_disagreements( $returns, $drifted ) );

$no_row = $returns;
unset( $no_row['join'] );
assert_same( 'a tag with no README row is named', array( 'join:no-readme-row' ), readme_disagreements( $no_row, $described ) );

$unspelled = $described;
$unspelled['content'] = str_replace( 'div or', '<div> or', $described['content'] );
assert_same( 'angle brackets in a description are named, not forgiven', array( 'content' ), readme_disagreements( $returns, $unspelled ) );

// ---------------------------------------------------------------------------
// 5. A description that names another tag by its picker title names a title that exists.
// ---------------------------------------------------------------------------

echo "5. Picker titles quoted in descriptions\n";

// A description reads "the <picker title> tag". The title is a separate string at the
// registration, so renaming it would leave the sentence naming nothing; each pair below is a
// quote the descriptions make today, and the sentence must still be present to be held.
foreach ( array(
	'text'           => array( 'Title/Name', 'title' ),
	'datetime_range' => array( 'Date/Time', 'datetime_single' ),
) as $host => $quote ) {
	list( $label, $target ) = $quote;
	assert_same( "{$host} description still names the {$label} tag", true, false !== strpos( (string) $described[ $host ], $label . ' tag' ) );
	assert_same( "...and {$target} is still titled {$label}", $label, $registered[ $target ]['title'] ?? null );
}

// ---------------------------------------------------------------------------

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: {$failures} of {$count}\n";
	exit( 1 );
}
echo "All {$count} assertions passed.\n";
exit( 0 );
