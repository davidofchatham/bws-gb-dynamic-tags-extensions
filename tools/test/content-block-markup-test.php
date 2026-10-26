<?php
/**
 * Standalone harness for bws_markup_has_block_level_tag() (content-helpers.php) — the one
 * owner of "is this value block-level", shared by the `{{content}}` wrap and FW-156's strip.
 *
 * Requires the REAL file (no WP): every function in it is function_exists-guarded.
 *
 * Run:  php tools/test/content-block-markup-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../../includes/helpers/content-helpers.php';

$failures = 0;
$cases    = array(
	// [ label, markup, expected ]
	array( 'plain text',                  'Just words',                                  false ),
	array( 'empty',                       '',                                            false ),
	array( 'blank-line text',             "One.\n\nTwo.",                                false ),
	array( 'inline tags only',            'a <strong>b</strong> <em>c</em> <a href="/x">d</a>', false ),
	array( 'void inline: br, img',        'a<br />b <img src="x.png" alt="">',           false ),
	array( 'uppercase inline',            '<STRONG>x</STRONG>',                          false ),
	array( 'paragraph',                   '<p>One.</p>',                                 true ),
	array( 'heading',                     '<h2>H</h2>',                                  true ),
	array( 'list',                        '<ul><li>a</li></ul>',                         true ),
	array( 'div',                         '<div>x</div>',                                true ),
	array( 'mixed: loose text + block',   "Loose\n\n<div>block</div>",                   true ),
	array( 'block nested in inline',      '<span><p>x</p></span>',                       true ),
	array( 'space after < is text',        'if x < y then',                               false ),
	array( 'closing tag only',            'x</p>',                                       true ),
	array( 'custom element',              '<my-widget>x</my-widget>',                    true ),
	array( 'comment is not a tag',        '<!-- note --> text',                          false ),
	array( 'comment hiding a block',      '<!-- wp:paragraph --><p>x</p>',               true ),
	array( 'lone angle bracket',          '3 < 4 and 5 > 2',                             false ),
);

foreach ( $cases as [ $label, $markup, $expected ] ) {
	$actual = bws_markup_has_block_level_tag( $markup );
	if ( $actual === $expected ) {
		echo "  ok   {$label}\n";
		continue;
	}
	$failures++;
	echo "  FAIL {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
}

echo $failures ? "\n{$failures} FAILED\n" : "\nall " . count( $cases ) . " passed\n";
exit( $failures ? 1 : 0 );
