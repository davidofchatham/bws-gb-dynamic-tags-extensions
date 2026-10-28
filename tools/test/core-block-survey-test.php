<?php
/**
 * Standalone harness for the core-block survey walk (FW-156) in
 * includes/helpers/core-block-survey.php: a post-content string in, per-block-type counts of
 * tag-like `{{...}}` strings out, split registered vs unregistered.
 *
 * REQUIRES THE REAL WORDPRESS BLOCK PARSER. A fabricated parser would test our idea of what
 * WordPress does with nesting, self-closing blocks and malformed comments, not what it does.
 * The harness loads wp-includes/class-wp-block-parser.php from $BWS_WP_CORE_DIR, else
 * D:/Resources/WordPress/wordpress, and FAILS (never skips) when it is not there. Fabricated:
 * a one-line parse_blocks() over that parser, the way core defines it.
 *
 * Asserts what a scan reports: which block type owns a tag, what counts as registered, and
 * that malformed markup never fatals. The WordPress-side runner (database, widgets, AJAX) is
 * not reachable here and is measured on the testbed.
 *
 * Run:  php tools/test/core-block-survey-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

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

$core   = rtrim( (string) ( getenv( 'BWS_WP_CORE_DIR' ) ?: 'D:/Resources/WordPress/wordpress' ), '/\\' );
$parser = $core . '/wp-includes/class-wp-block-parser.php';
if ( ! is_file( $parser ) ) {
	assert_same( "the real WP block parser is at {$parser} (set BWS_WP_CORE_DIR)", true, false );
	echo "\n{$count} assertions, {$failures} failed\n";
	exit( 1 );
}
require $parser;

function parse_blocks( $content ) {
	return ( new WP_Block_Parser() )->parse( $content );
}
function __( $text ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

require dirname( __DIR__, 2 ) . '/includes/helpers/core-block-tags.php';
require dirname( __DIR__, 2 ) . '/includes/helpers/core-block-survey.php';

$known = array( 'text' => true, 'phone' => true, 'table' => true, 'content' => true );
$reg   = static fn( string $name ): bool => isset( $known[ $name ] );
$walk  = static fn( string $c ): array => bws_core_blocks_survey_walk( $c, $reg );
$cell  = static fn( int $r, int $u, array $rt = array(), array $ut = array() ): array => array( 'registered' => $r, 'unregistered' => $u, 'registered_tags' => $rt, 'unregistered_tags' => $ut );

echo "§1 — a flat block\n";
assert_same(
	'registered and unregistered are counted apart, per block type',
	array( 'core/paragraph' => $cell( 2, 1, array( 'text' => 1, 'phone' => 1 ), array( 'nope' => 1 ) ) ),
	$walk( '<!-- wp:paragraph --><p>{{text key:a}} and {{phone}} then {{nope}}</p><!-- /wp:paragraph -->' )
);
assert_same( 'the same tag twice counts twice', array( 'core/paragraph' => $cell( 2, 0, array( 'text' => 2 ) ) ), $walk( '<!-- wp:paragraph --><p>{{text}}{{text}}</p><!-- /wp:paragraph -->' ) );
assert_same( 'a tag with options and spaces inside the braces is recognized', array( 'core/heading' => $cell( 1, 0, array( 'text' => 1 ) ) ), $walk( '<!-- wp:heading --><h2>{{ text key:a|required:false }}</h2><!-- /wp:heading -->' ) );
assert_same( 'content with no braces is empty', array(), $walk( '<!-- wp:paragraph --><p>plain</p><!-- /wp:paragraph -->' ) );
assert_same( 'an empty string is empty', array(), $walk( '' ) );

echo "§2 — nested blocks attribute a tag to the block that holds it\n";
$nested = '<!-- wp:group --><div class="wp-block-group">{{text}}<!-- wp:paragraph --><p>{{phone}}{{zzz}}</p><!-- /wp:paragraph --><!-- wp:columns --><div><!-- wp:column --><div><!-- wp:heading --><h2>{{text}}</h2><!-- /wp:heading --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';
assert_same(
	'each level counted on its own; blocks with no tag are absent',
	array( 'core/group' => $cell( 1, 0, array( 'text' => 1 ) ), 'core/paragraph' => $cell( 1, 1, array( 'phone' => 1 ), array( 'zzz' => 1 ) ), 'core/heading' => $cell( 1, 0, array( 'text' => 1 ) ) ),
	$walk( $nested )
);

echo "§3 — a table cell belongs to core/table\n";
assert_same(
	'cells are the table block\'s own markup',
	array( 'core/table' => $cell( 2, 0, array( 'text' => 1, 'phone' => 1 ) ) ),
	$walk( '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>{{text}}</td><td>{{phone}}</td></tr></tbody></table></figure><!-- /wp:table -->' )
);

echo "§4 — references and widgets\n";
assert_same( 'a reusable-block reference has no markup of its own; the wp_block post is scanned as its own post', array(), $walk( '<!-- wp:block {"ref":42} /-->' ) );
assert_same( 'a template-part reference likewise', array(), $walk( '<!-- wp:template-part {"slug":"header"} /-->' ) );
assert_same(
	'a block widget\'s content is the same string-in shape',
	array( 'core/paragraph' => $cell( 1, 0, array( 'text' => 1 ) ), 'core/html' => $cell( 0, 1, array(), array( 'later' => 1 ) ) ),
	$walk( '<!-- wp:paragraph --><p>{{text}}</p><!-- /wp:paragraph --><!-- wp:html -->{{later}}<!-- /wp:html -->' )
);
assert_same( 'a GenerateBlocks block is reported like any other, by name', array( 'generateblocks/text' => $cell( 1, 0, array( 'text' => 1 ) ) ), $walk( '<!-- wp:generateblocks/text --><p class="gb-text">{{text}}</p><!-- /wp:generateblocks/text -->' ) );

echo "§5 — only markup counts\n";
assert_same( 'a tag in a block comment\'s attributes is not markup', array(), $walk( '<!-- wp:paragraph {"content":"{{text}}"} --><p>x</p><!-- /wp:paragraph -->' ) );
assert_same( 'text outside any block has no block type and is left out', array(), $walk( 'Classic {{text}} content' ) );

echo "§6 — malformed markup never fatals\n";
assert_same( 'a dotted name is counted, and is unregistered', array( 'core/table' => $cell( 0, 2, array(), array( 'field.a_b' => 1, 'field.c' => 1 ) ) ), $walk( '<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>{{field.a_b}}</td><td>{{field.c}}</td></tr></tbody></table></figure><!-- /wp:table -->' ) );
assert_same( 'an unterminated brace pair is not a tag', array(), $walk( '<!-- wp:paragraph --><p>{{text key:a</p><!-- /wp:paragraph -->' ) );
assert_same( 'empty and nameless braces are not tags', array(), $walk( '<!-- wp:paragraph --><p>{{}} {{ }} {{!}} {{ key:a }}</p><!-- /wp:paragraph -->' ) );
assert_same( 'an unclosed block still reports its tag', array( 'core/paragraph' => $cell( 1, 0, array( 'text' => 1 ) ) ), $walk( '<!-- wp:paragraph --><p>{{text}}</p>' ) );
assert_same( 'a stray closer is survived', true, is_array( $walk( '<!-- /wp:group --><!-- wp:paragraph --><p>{{text}}</p><!-- /wp:paragraph -->' ) ) );
assert_same( 'a broken comment is survived', true, is_array( $walk( '<!-- wp:paragraph {"a": --><p>{{text}}</p>' ) ) );
assert_same( 'a very deep nest is survived', true, is_array( $walk( str_repeat( '<!-- wp:group -->', 300 ) . '{{text}}' . str_repeat( '<!-- /wp:group -->', 300 ) ) ) );

echo "§7 — merging counts across posts\n";
$sums_hold = static function ( array $cells ): bool {
	foreach ( $cells as $c ) {
		if ( $c['registered'] !== array_sum( $c['registered_tags'] ) || $c['unregistered'] !== array_sum( $c['unregistered_tags'] ) ) {
			return false;
		}
	}
	return true;
};
$merged = bws_core_blocks_survey_merge( $walk( $nested ), $walk( '<!-- wp:group -->{{text}}{{nope}}{{nope}}<!-- /wp:group -->' ) );
assert_same( 'a walk, and a merge of walks, keep each count equal to the sum of its tag list', true, $sums_hold( $walk( $nested ) ) && $sums_hold( $merged ) && 2 === $merged['core/group']['unregistered_tags']['nope'] );
$a = array( 'core/paragraph' => $cell( 1, 2, array( 'text' => 1 ), array( 'x' => 2 ) ) );
$b = array( 'core/paragraph' => $cell( 3, 0, array( 'text' => 2, 'phone' => 1 ) ), 'core/table' => $cell( 0, 4, array(), array( 'x' => 4 ) ) );
assert_same(
	'counts and per-tag counts add per block type',
	array( 'core/paragraph' => $cell( 4, 2, array( 'text' => 3, 'phone' => 1 ), array( 'x' => 2 ) ), 'core/table' => $cell( 0, 4, array(), array( 'x' => 4 ) ) ),
	bws_core_blocks_survey_merge( $a, $b )
);
assert_same( 'merging into nothing returns the addition', $b, bws_core_blocks_survey_merge( array(), $b ) );

echo "§8 — the stored result is read back defensively\n";
assert_same( 'garbage is no result', null, bws_core_blocks_survey_clean( 'x' ) );
assert_same( 'missing blocks is no result', null, bws_core_blocks_survey_clean( array( 'time' => 5 ) ) );
assert_same(
	'a good result keeps only well-formed block rows, ints',
	array( 'time' => 5, 'blocks' => array( 'core/table' => $cell( 2, 0, array( 'text' => 2 ) ) ), 'widgets' => array( 'registered' => 1, 'unregistered' => 0 ) ),
	bws_core_blocks_survey_clean( array( 'time' => '5', 'blocks' => array( 'core/table' => array( 'registered' => '2', 'unregistered' => 0, 'registered_tags' => array( 'text' => '2', 'bad tag' => 1, 'a}}' => 3 ) ), 'bad name' => $cell( 1, 1 ), 'core/x' => 'no' ), 'widgets' => $cell( 1, 0 ) ) )
);
assert_same( 'a zero or negative per-tag count is dropped', array( 'text' => 2 ), bws_core_blocks_survey_clean( array( 'time' => 1, 'blocks' => array( 'core/table' => array( 'registered' => 2, 'unregistered' => 0, 'registered_tags' => array( 'text' => 2, 'zero' => 0, 'neg' => -1 ) ) ) ) )['blocks']['core/table']['registered_tags'] );
assert_same( 'a result stored before tag names were kept reads back with empty lists', array(), bws_core_blocks_survey_clean( array( 'time' => 1, 'blocks' => array( 'core/table' => array( 'registered' => 2, 'unregistered' => 0 ) ) ) )['blocks']['core/table']['registered_tags'] );
assert_same( 'a result with no widgets reports zeros', array( 'registered' => 0, 'unregistered' => 0 ), bws_core_blocks_survey_clean( array( 'time' => 1, 'blocks' => array() ) )['widgets'] );

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
