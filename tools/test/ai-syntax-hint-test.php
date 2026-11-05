<?php
/**
 * Standalone harness for the `aiSyntax` hints (includes/helpers/ai-syntax-hints.php) and the
 * seam that adds them (bws_gb_register_tag()).
 *
 * REQUIRES THE REAL FILES, not copies: it loads the plugin's registration code and runs the
 * real registration passes against a capturing stand-in for GB's registrar (the
 * tag-description-test.php setup). No WordPress, no GenerateBlocks, no network.
 *
 * WHAT IT HOLDS
 *
 * 1. INACTIVE. With GeneratePress AI absent, no registered definition carries `aiSyntax`.
 *
 * 2. EXAMPLES. Every `{{tag opt:value|...}}` in a hint names a registered tag, registered
 *    option keys, a `use` value the option offers, and a `src` chain whose segments start with
 *    a root or step the source control offers, all read off the registered definitions, so a
 *    renamed option cannot leave a stale example behind. A bare `option:value` fragment of
 *    the hint's own option, outside a full example, is held to the same values. An example
 *    never writes a `use` beside the token that implies it (`fixed:x`, not
 *    `use:fixed|fixed:x`), as the editor never does. A hint is also held to its byte budget
 *    and to text `sanitize_text_field()` returns unchanged. No example teaches a field read
 *    (teaches_field_read() says what counts). Shared hints are checked on every tag they land on.
 *
 * 2b. REVIEWED VOCABULARY. Each hinted option's offered values (chain roots and steps, `use`
 *    values, link targets) are pinned to the list its hint was last reviewed against, so a new
 *    value fails here and forces the re-read nobody would otherwise be prompted to do.
 *
 * 3. ACTIVE. With the plugin's constant defined, `aiSyntax` lands on each hinted option
 *    and nowhere else, through a synthetic registration and the real tree.
 *
 * The checker is fed hints that break each rule and must name the rule: the real tree
 * satisfies every rule, so a pass alone would not show it can fail.
 *
 * Run:  php tools/test/ai-syntax-hint-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// The child-process arm (section 3): the plugin's constant is defined before anything loads.
$active_mode = in_array( '--active', $argv ?? array(), true );
if ( $active_mode ) {
	define( 'GENERATEPRESS_AI_VERSION', '0' );
}

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

function register_real_tree(): void {
	GenerateBlocks_Register_Dynamic_Tag::$tags = array();
	bws_register_base_tags();
	bws_register_email_tag();
	bws_register_phone_tag();
	bws_register_table_tag();
	bws_register_call_tag();
	\BWS\DynamicTags\TagTemplateRegistry::generate_base_try_tags();
}

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

const HINT_BYTE_BUDGET     = 350;
const HINT_BYTE_BUDGET_OWN = 450; // a tag's own hint that replaces a shared one repeats its syntax
const HINT_TOTAL_BUDGET    = 15000;

/** The roots and steps a source chain offers, off an option's fold config. */
function chain_slugs( array $option ): array {
	return array_merge( array( 'current' ), array_keys( $option['fold']['roots'] ?? array() ), array_keys( $option['fold']['steps'] ?? array() ) );
}

/**
 * The values a registered option offers: a select's `options` values, or for the `src` chain
 * the roots and step slugs its fold config names. Null when the option offers no closed set.
 */
function offered_values( array $option ): ?array {
	// A slot (`A`, `B`, …) is pinned by the reads it offers; its chain is the `src` chain's vocabulary.
	if ( 'bws-slot-fold' === ( $option['type'] ?? '' ) ) {
		return array_map( fn( $r ) => (string) $r['value'], $option['fold']['readRows'] ?? array() );
	}
	if ( isset( $option['fold'] ) ) {
		return chain_slugs( $option );
	}
	if ( 'select' === ( $option['type'] ?? '' ) && is_array( $option['options'] ?? null ) ) {
		return array_map( fn( $o ) => is_array( $o ) ? (string) $o['value'] : (string) $o, $option['options'] );
	}
	return null;
}

/**
 * Whether a value is one the registered option offers. A chain value is `;`-separated steps,
 * each `slug,arg...`, and each slug must be offered; any other value is one token. An option
 * with no closed set accepts anything.
 */
function value_offered( array $option, string $value ): bool {
	// A slot value is parsed by the real slot parser: it must parse clean (no error, no token the
	// parser could not place), start each chain step with a slug the slot offers, and read through
	// an analog the slot offers.
	if ( 'bws-slot-fold' === ( $option['type'] ?? '' ) ) {
		$slot = bws_fold_parse_slot( $value, $option['fold']['container'] ?? 'table' );
		if ( isset( $slot['error'] ) || ! empty( $slot['extra'] ) ) {
			return false;
		}
		$slugs = chain_slugs( $option );
		foreach ( $slot['chain'] as $step ) {
			if ( ! in_array( $step['slug'], $slugs, true ) ) {
				return false;
			}
		}
		$read = $slot['read'] ?? null;
		return null === $read || 'analog' !== $read['kind'] || in_array( $read['slug'], offered_values( $option ), true );
	}
	$offered = offered_values( $option );
	if ( null === $offered ) {
		return true;
	}
	$heads = isset( $option['fold'] )
		? array_map( fn( $s ) => strstr( $s . ',', ',', true ), explode( ';', $value ) )
		: array( $value );
	return array() === array_diff( $heads, $offered );
}

/**
 * Whether one `key:value` pair teaches a field read, which a hint never does: a `key` or
 * `linkKey` token, a `use` or `linkTo` value naming a field, or a chain ENDING in `rows`, where
 * what prints is a row sub-field. A step that only finds a source through a field (`refs,x`,
 * `rows,x;refs,y`) prints nothing from that field, so it is allowed.
 */
function teaches_field_read( string $key, string $value ): bool {
	if ( in_array( $key, array( 'key', 'linkKey' ), true ) ) {
		return true;
	}
	if ( 'use' === $key ) {
		return in_array( $value, array( 'key', 'meta', 'option' ), true );
	}
	if ( 'linkTo' === $key ) {
		return 'key' === $value;
	}
	// A slot value (`A:...`, `B:...`): a keyed read, or a chain ending in `rows`.
	if ( 1 === strlen( $key ) && ctype_upper( $key ) ) {
		$slot = bws_fold_parse_slot( $value, 'table' );
		if ( isset( $slot['error'] ) ) {
			return false;
		}
		$steps = $slot['chain'] ?? array();
		return 'key' === ( $slot['read']['kind'] ?? '' ) || ( $steps && 'rows' === end( $steps )['slug'] );
	}
	if ( 'src' === $key ) {
		$steps = explode( ';', $value );
		return 'rows' === strstr( end( $steps ) . ',', ',', true );
	}
	return false;
}

/**
 * Rules for one hint. Returns the NAMES of the rules it breaks, so a fixture can require a
 * rule by name.
 *
 * @param array $tags The registered definitions, keyed tag name.
 */
function check_hint( string $tag, string $option, $hint, array $tags ): array {
	$broken = array();

	if ( ! isset( $tags[ $tag ]['options'][ $option ] ) ) {
		$broken[] = 'unregistered-option';
	}
	if ( ! is_string( $hint ) || '' === trim( $hint ) ) {
		return array_merge( $broken, array( 'empty' ) );
	}
	$all    = bws_ai_syntax_hints();
	$budget = isset( $all['*'][ $option ], $all[ $tag ][ $option ] ) ? HINT_BYTE_BUDGET_OWN : HINT_BYTE_BUDGET;
	if ( strlen( $hint ) > $budget ) {
		$broken[] = 'too-long';
	}
	// What sanitize_text_field() would change.
	if ( preg_match( '/[<>%\r\n\t]|\s\s|^\s|\s$/', $hint ) ) {
		$broken[] = 'sanitizer-unsafe';
	}

	// A bare `option:value` fragment of the hint's own option, outside any full example, is an
	// example too and is held to the same offered values. A sentence's closing `.` or `,` is not
	// part of the value.
	$prose = (string) preg_replace( '/\{\{.*?\}\}/', '', $hint );
	preg_match_all( '/(?<![\w])' . preg_quote( $option, '/' ) . ':([^\s|}]+)/', $prose, $fragments );
	foreach ( $fragments[1] as $value ) {
		$value = rtrim( $value, '.,' );
		if ( teaches_field_read( $option, $value ) ) {
			$broken[] = 'teaches-field-read';
		}
		if ( isset( $tags[ $tag ]['options'][ $option ] ) && ! value_offered( $tags[ $tag ]['options'][ $option ], $value ) ) {
			$broken[] = 'fragment-unoffered-value';
		}
	}

	// A shared hint names no tag, so a fragment can be its only example.
	if ( ! preg_match_all( '/\{\{(\w+)((?:\s[^}]*)?)\}\}/', $hint, $examples, PREG_SET_ORDER ) ) {
		$examples = array();
		if ( ! $fragments[1] ) {
			$broken[] = 'no-example';
		}
	}
	foreach ( $examples as $ex ) {
		$ex_tag = $ex[1];
		if ( ! isset( $tags[ $ex_tag ] ) ) {
			$broken[] = 'example-unregistered-tag';
			continue;
		}
		$written = array();
		foreach ( array_filter( explode( '|', trim( $ex[2] ) ), 'strlen' ) as $pair ) {
			$key   = strstr( $pair, ':', true );
			$value = false === $key ? '' : substr( $pair, strlen( $key ) + 1 );
			if ( false !== $key ) {
				$written[ $key ] = $value;
				if ( teaches_field_read( $key, $value ) ) {
					$broken[] = 'teaches-field-read';
				}
			}
			if ( false === $key || ! isset( $tags[ $ex_tag ]['options'][ $key ] ) ) {
				$broken[] = 'example-unregistered-key';
				continue;
			}
			if ( ! value_offered( $tags[ $ex_tag ]['options'][ $key ], $value ) ) {
				$broken[] = 'example-unoffered-value';
			}
		}
		// The editor never writes a `use` beside the token that implies it (`fixed:x`, not
		// `use:fixed|fixed:x`), so an example must not either.
		foreach ( BWS_USE_IMPLIED_BY_TOKEN as $token => $implied ) {
			if ( isset( $written[ $token ] ) && ( $written['use'] ?? null ) === $implied ) {
				$broken[] = 'example-redundant-use';
			}
		}
	}

	return array_values( array_unique( $broken ) );
}

/**
 * Where the hints land on a registered tree: "tag.option" => hint, for every hinted tag and
 * every hint whose option the tag registers. A tag's OWN hint is listed even when the option
 * is missing, so check_hint() can report it; a shared one is not, since not every tag has
 * every option.
 */
function hint_placements( array $tags ): array {
	$hints = bws_ai_syntax_hints();
	$out   = array();
	foreach ( $tags as $tag => $def ) {
		foreach ( bws_ai_syntax_hints_for( $tag ) as $option => $hint ) {
			if ( isset( $def['options'][ $option ] ) || isset( $hints[ $tag ][ $option ] ) ) {
				$out[ "{$tag}.{$option}" ] = $hint;
			}
		}
	}
	return $out;
}

if ( $active_mode ) {
	register_real_tree();
	$tree = GenerateBlocks_Register_Dynamic_Tag::get_tags();
	$with = array();
	foreach ( $tree as $name => $def ) {
		foreach ( $def['options'] ?? array() as $key => $opt ) {
			if ( isset( $opt['aiSyntax'] ) ) {
				$with[ "{$name}.{$key}" ] = $opt['aiSyntax'];
			}
		}
	}
	$expected = hint_placements( $tree );
	ksort( $expected );
	ksort( $with );
	assert_same( 'active tree: aiSyntax sits on exactly the hinted options, with their text', $expected, $with );
	exit( $failures ? 1 : 0 );
}

// ---------------------------------------------------------------------------
echo "1. INACTIVE: no aiSyntax in any registered definition\n";
// ---------------------------------------------------------------------------

register_real_tree();
$tags = GenerateBlocks_Register_Dynamic_Tag::get_tags();

assert_same( 'the real tree registered', true, count( $tags ) > 20 );
assert_same( 'GeneratePress AI constant is not defined in this process', false, defined( 'GENERATEPRESS_AI_VERSION' ) );
assert_same( 'inactive: no aiSyntax anywhere in the real tree', false, str_contains( json_encode( $tags ), 'aiSyntax' ) );

$synthetic = static function (): array {
	GenerateBlocks_Register_Dynamic_Tag::$tags = array();
	bws_gb_register_tag( array(
		'tag'     => 'text',
		'title'   => 'T',
		'return'  => 'bws_base_text_callback',
		'options' => array( 'src' => array( 'type' => 'x' ), 'use' => array( 'type' => 'select' ), 'sep' => array( 'type' => 'text' ) ),
	) );
	bws_gb_register_tag( array(
		'tag'     => 'call',
		'title'   => 'C',
		'return'  => 'bws_base_text_callback',
		'options' => array( 'src' => array( 'type' => 'x' ) ),
	) );
	bws_gb_register_tag( array(
		'tag'     => 'hidden_one',
		'title'   => 'H',
		'return'  => 'bws_base_text_callback',
		'hidden'  => true,
		'options' => array( 'src' => array( 'type' => 'x' ) ),
	) );
	bws_gb_register_tag( array( 'tag' => 'no_options', 'title' => 'O', 'return' => 'bws_base_text_callback' ) );
	return GenerateBlocks_Register_Dynamic_Tag::get_tags();
};

$inactive = $synthetic();
assert_same( 'inactive: synthetic registration adds nothing', false, str_contains( json_encode( $inactive ), 'aiSyntax' ) );

// ---------------------------------------------------------------------------
echo "2. EXAMPLES: every hint checks clean against the registered definitions\n";
// ---------------------------------------------------------------------------

$hints      = bws_ai_syntax_hints();
$placements = hint_placements( $tags );
assert_same( 'there is at least one placement', true, count( $placements ) > 0 );
foreach ( $placements as $where => $hint ) {
	list( $tag, $option ) = explode( '.', $where, 2 );
	assert_same( "hint {$where}", array(), check_hint( $tag, $option, $hint, $tags ) );
}
assert_same( 'every placed hint together stays under the total budget', true, array_sum( array_map( 'strlen', $placements ) ) <= HINT_TOTAL_BUDGET );
foreach ( array_keys( $hints['*'] ) as $option ) {
	$lands = array_filter( array_keys( $placements ), fn( $w ) => str_ends_with( $w, ".{$option}" ) );
	assert_same( "shared hint {$option} lands on at least one tag", true, count( $lands ) > 0 );
}
foreach ( array_keys( $hints ) as $tag ) {
	if ( '*' !== $tag ) {
		assert_same( "tag-specific entry {$tag} names a registered tag", true, isset( $tags[ $tag ] ) );
	}
}
foreach ( BWS_AI_SYNTAX_UNHINTED_TAGS as $tag ) {
	assert_same( "{$tag} receives no hint", array(), bws_ai_syntax_hints_for( $tag ) );
}

foreach ( array( 'email', 'phone' ) as $tag ) {
	$own = bws_ai_syntax_hints_for( $tag );
	assert_same( "{$tag}: the shared src hint is withheld, its own fixed hint kept", array( false, true ), array( isset( $own['src'] ), isset( $own['fixed'] ) ) );
}
// The try_ tags: five carry a first-attempt and a later-attempt hint; the four whose every read is
// a field read or a typed address carry none, shared hints included (the list is the constant's).
foreach ( array( 'try_text', 'try_title', 'try_permalink', 'try_content', 'try_image' ) as $tag ) {
	$own = bws_ai_syntax_hints_for( $tag );
	assert_same( "{$tag}: a hint on the first attempt and on the later ones", true, isset( $own['A'], $own['B'] ) );
}
foreach ( array_filter( BWS_AI_SYNTAX_UNHINTED_TAGS, fn( $t ) => str_starts_with( $t, 'try_' ) ) as $tag ) {
	assert_same( "{$tag} receives no hint", array(), bws_ai_syntax_hints_for( $tag ) );
}
assert_same(
	'fixture: a try_ example whose chain step the slot does not offer fails',
	true,
	in_array( 'example-unoffered-value', check_hint( 'try_title', 'A', '{{try_title A:src(nope,x)|B:src(site)}}', $tags ), true )
);
assert_same(
	'fixture: a try_ example with a read the tag does not offer fails',
	true,
	in_array( 'example-unoffered-value', check_hint( 'try_text', 'A', '{{try_text A:use(excerpt)}}', $tags ), true )
);
echo "   checker fixtures: each broken hint names its rule\n";
$good = 'Leave src out for the current post. {{text src:parent|fixed:Back|linkTo:permalink}} links to the parent.';
assert_same( 'fixture: a good hint is clean', array(), check_hint( 'text', 'src', $good, $tags ) );
$fixtures = array(
	'unregistered-option'      => array( 'text', 'nope', $good ),
	'empty'                    => array( 'text', 'src', '  ' ),
	'too-long'                 => array( 'text', 'src', $good . str_repeat( ' x', 200 ) ),
	'sanitizer-unsafe'         => array( 'text', 'src', $good . ' <b>' ),
	'no-example'               => array( 'text', 'src', 'No example here, not even use:title.' ),
	'example-unregistered-tag' => array( 'text', 'src', '{{nope src:parent}}' ),
	'example-unregistered-key' => array( 'text', 'src', '{{text srcc:parent}}' ),
	'example-unoffered-value'  => array( 'text', 'src', '{{text src:nope,x|use:title}}' ),
	'example-redundant-use'    => array( 'text', 'use', '{{text use:fixed|fixed:Read more}}' ),
	'fragment-unoffered-value' => array( 'text', 'src', 'For example src:nope,x gives nothing. {{text src:parent|use:title}}' ),
);
foreach ( $fixtures as $rule => $f ) {
	assert_same( "fixture: {$rule}", true, in_array( $rule, check_hint( $f[0], $f[1], $f[2], $tags ), true ) );
}
foreach ( array(
	'a key token'               => '{{text key:foo}}',
	'a use value naming a field' => '{{text use:meta}}',
	'a link target naming a field' => '{{text fixed:x|linkTo:key}}',
	'a full example ending in rows' => '{{text src:rows,team|use:title}}',
	'a fragment ending in rows' => 'For example src:refs,x;rows,team reads a row.',
	'a keyed slot read'        => '{{table A:header(Name);key(name)}}',
	'a slot chain ending in rows' => '{{table A:src(rows,team);use(title)}}',
) as $what => $bad ) {
	assert_same( "fixture: teaches-field-read, {$what}", true, in_array( 'teaches-field-read', check_hint( 'text', 'src', $bad, $tags ), true ) );
}
foreach ( array(
	'a slot read the slot does not offer' => '{{table A:header(Name);use(titel)}}',
	'a slot step the chain does not offer' => '{{table A:src(nope,x);use(title)}}',
	'a slot token the parser cannot place' => '{{table A:head(Name);use(title)}}',
	'a slot value that does not parse'    => '{{table A:header(Name;use(title)}}',
) as $what => $bad ) {
	assert_same( "fixture: example-unoffered-value, {$what}", true, in_array( 'example-unoffered-value', check_hint( 'table', 'A', $bad, $tags ), true ) );
}
assert_same( 'fixture: a step that finds a source through a field is allowed', array(), check_hint( 'text', 'src', 'For example src:refs,x and src:rows,team;refs,y.', $tags ) );
$own_ok  = $good . str_repeat( ' x', 150 );
$own_bad = $good . str_repeat( ' x', 200 );
assert_same( 'fixture: a tag-own replacing hint may pass 350 bytes', false, in_array( 'too-long', check_hint( 'title', 'src', $own_ok, $tags ), true ) );
assert_same( 'fixture: but not 450', true, in_array( 'too-long', check_hint( 'title', 'src', $own_bad, $tags ), true ) );
assert_same(
	'fixture: a use value the option does not offer',
	true,
	in_array( 'example-unoffered-value', check_hint( 'text', 'src', '{{text use:titel}}', $tags ), true )
);

// ---------------------------------------------------------------------------
echo "2b. REVIEWED VOCABULARY: a hinted option's values are the ones its hint was written for\n";
// ---------------------------------------------------------------------------
//
// A hint is invisible to authors and to the editor, so nothing else would notice that a new
// chain root or step, `use` value or link target arrived without the hint being re-read. This
// pins, per hinted option, the values it offered when its hint was last reviewed: the union over
// every tag it lands on (roots and step slugs for a chain). A change fails here by name.
//
// TO CLEAR A FAILURE: re-read that option's hint in includes/helpers/ai-syntax-hints.php
// against the new values, change the hint if it should teach them (or say why it should not
// in the commit), then update the list below. Updating the list without the re-read defeats it.

const REVIEWED_VOCABULARY = array(
	'src'    => array( 'current', 'parent', 'post', 'refs', 'rows', 'site', 'term', 'terms' ),
	'use'    => array( '', 'excerpt', 'featured', 'fixed', 'key', 'title' ),
	'fixed'  => array(),
	// A and B (table and the try_ tags) pin the reads a slot offers and the roots and steps of its chain.
	'A'      => array( '', 'content', 'current', 'excerpt', 'featured', 'fixed', 'key', 'parent', 'post', 'refs', 'rows', 'site', 'term', 'terms', 'title' ),
	'B'      => array( 'content', 'current', 'excerpt', 'featured', 'fixed', 'key', 'parent', 'post', 'refs', 'rows', 'site', 'term', 'terms', 'title' ),
	'linkTo' => array( '', 'key', 'permalink' ),
);

$vocabulary = array();
foreach ( $placements as $where => $hint ) {
	list( $tag, $option ) = explode( '.', $where, 2 );
	$def = $tags[ $tag ]['options'][ $option ];
	// A slot's hint also teaches its chain, so the roots and steps it offers are pinned with its reads.
	$vocabulary[ $option ] = array_merge( $vocabulary[ $option ] ?? array(), offered_values( $def ) ?? array(), 'bws-slot-fold' === $def['type'] ? chain_slugs( $def ) : array() );
}
foreach ( $vocabulary as $option => $values ) {
	$values = array_values( array_unique( $values ) );
	sort( $values );
	assert_same( "hinted option {$option}: offered values match the reviewed list", REVIEWED_VOCABULARY[ $option ] ?? '(no reviewed list: add one)', $values );
}

// ---------------------------------------------------------------------------
echo "3. ACTIVE: aiSyntax lands on the hinted options only\n";
// ---------------------------------------------------------------------------

define( 'GENERATEPRESS_AI_VERSION', '0' );

$active = $synthetic();
assert_same( 'active: a shared hint lands', $hints['*']['src'], $active['text']['options']['src']['aiSyntax'] ?? null );
assert_same( 'active: a tag-specific hint lands', $hints['text']['use'], $active['text']['options']['use']['aiSyntax'] ?? null );
assert_same( 'active: an unhinted option gets nothing', false, isset( $active['text']['options']['sep']['aiSyntax'] ) );
assert_same( 'active: the hint adds no other key', array( 'type', 'aiSyntax' ), array_keys( $active['text']['options']['src'] ) );
assert_same( 'active: an unhinted tag gets no shared hint', false, str_contains( json_encode( $active['call'] ), 'aiSyntax' ) );
assert_same( 'active: a hidden registration gets no hint', false, str_contains( json_encode( $active['hidden_one'] ), 'aiSyntax' ) );
assert_same( 'active: a tag with no options registers', true, isset( $active['no_options'] ) );

// The real tree registers once per process (a static guard in bws_register_base_tags()), so
// its active arm runs in a second process with the constant defined from the start.
passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --active', $child_status );
assert_same( 'active: the real tree carries aiSyntax on exactly the hinted options (child process)', 0, $child_status );

echo "\n{$count} checks, {$failures} failed\n";
exit( $failures ? 1 : 0 );
