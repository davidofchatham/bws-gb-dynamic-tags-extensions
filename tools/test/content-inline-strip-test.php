<?php
/**
 * Standalone harness for the inline-only core-block `{{content}}` strip (FW-156):
 * bws_content_strip_for_host() and the pure bws_markup_strip_to_inline() under it, both in
 * content-helpers.php, plus the vocabulary accessor bws_markup_inline_tags() they share with
 * bws_markup_has_block_level_tag().
 *
 * Requires the REAL files (no WP): every function in them is function_exists-guarded.
 *
 * Run:  php tools/test/content-inline-strip-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../../includes/helpers/link-helpers.php';
require __DIR__ . '/../../includes/helpers/content-helpers.php';

$para   = array( 'blockName' => 'core/paragraph' );
$group  = array( 'blockName' => 'generateblocks/element' );

$failures = 0;
$cases    = array(
	// [ label, value, block, expected ]
	array( 'wrapped bare text is unchanged text',  '<p>Just words</p>',                         $para,   'Just words' ),
	array( 'bare run, no wrap, unchanged',         'Just words',                                $para,   'Just words' ),
	array( 'empty',                                '',                                          $para,   '' ),
	array( 'already inline passes unchanged',      'a <strong>b</strong> <em>c</em>',           $para,   'a <strong>b</strong> <em>c</em>' ),
	array( 'wrapped inline tags kept',             '<p>a <strong>b</strong> <br />c</p>',       $para,   'a <strong>b</strong> <br />c' ),
	array( 'two paragraphs: one space',            "<p>One.</p>\n<p>Two.</p>",                  $para,   'One. Two.' ),
	array( 'heading then paragraph',               '<h2>Title</h2><p>Body</p>',                 $para,   'Title Body' ),
	array( 'nested list, text kept',               '<ul><li>a<ul><li>b</li></ul></li><li>c</li></ul>', $para, 'a b c' ),
	array( 'blockquote, div',                      '<blockquote><p>q</p></blockquote><div>d</div>', $para, 'q d' ),
	array( 'image dropped',      '<p>t <img src="x.png" alt="y"> u</p>',      $para,   't  u' ),
	array( 'figure dropped whole',                 '<p>a</p><figure><img src="x.png"><figcaption>cap</figcaption></figure><p>b</p>', $para, 'a b' ),
	array( 'a kept in paragraph',                  '<p>go <a href="/x">there</a></p>',          $para,   'go <a href="/x">there</a>' ),
	array( 'unknown tag dropped, text kept',       '<my-widget>x</my-widget>',                  $para,   'x' ),
	array( 'comment removed',                      '<!-- c --><p>x</p>',                        $para,   'x' ),
	array( 'angle-bracket text survives',          '<p>Write to <john@example.com> if 3 < 4</p>', $para, 'Write to <john@example.com> if 3 < 4' ),
	array( 'trimmed both ends',                    "  <p>x</p>\n\n",                            $para,   'x' ),
	array( 'GB container untouched',               '<p>One.</p><ul><li>a</li></ul>',            $group,  '<p>One.</p><ul><li>a</li></ul>' ),
	array( 'no block (null) untouched',            '<p>One.</p>',                               null,    '<p>One.</p>' ),
);

foreach ( $cases as [ $label, $value, $block, $expected ] ) {
	$actual = bws_content_strip_for_host( $value, $block );
	if ( $actual === $expected ) {
		echo "  ok   {$label}\n";
		continue;
	}
	$failures++;
	echo "  FAIL {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
}

// The join: a run of block tags collapses to ONE join, space by default, newline when asked.
$joins = array(
	// [ label, value, join, expected ]
	array( 'two paragraphs, newline join',            "<p>one</p>\n\n<p>two</p>",      "\n", "one\ntwo" ),
	array( 'two paragraphs, default join',            "<p>one</p>\n\n<p>two</p>",      null, 'one two' ),
	array( 'two paragraphs, explicit space',          "<p>one</p><p>two</p>",          ' ',  'one two' ),
	array( 'soft br survives, newline join',          '<p>a<br />b</p><p>c</p>',       "\n", "a<br />b\nc" ),
	array( 'soft br survives, default join',          '<p>a<br />b</p><p>c</p>',       null, 'a<br />b c' ),
	array( 'bare run unchanged, newline join',        'Just words',                    "\n", 'Just words' ),
	array( 'bare run unchanged, default join',        'Just words',                    null, 'Just words' ),
	array( 'leading and trailing breaks trimmed',     "\n<p>x</p>\n<p>y</p>\n",        "\n", "x\ny" ),
	array( 'a deep run is one newline, not a blank line', "<ul><li>a</li>\n\n</ul><p>b</p>", "\n", "a\nb" ),
	array( 'inline tags kept, newline join',          '<p><strong>a</strong></p><p><em>b</em></p>', "\n", "<strong>a</strong>\n<em>b</em>" ),
);
foreach ( $joins as [ $label, $value, $join, $expected ] ) {
	$actual = null === $join ? bws_markup_strip_to_inline( $value ) : bws_markup_strip_to_inline( $value, $join );
	if ( $actual === $expected ) {
		echo "  ok   join: {$label}\n";
		continue;
	}
	$failures++;
	echo "  FAIL join: {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
}

// The host picks the join: the preformatted kind gets a newline, the inline kind a space.
$host_joins = array(
	array( 'core/verse',        "one\ntwo" ),
	array( 'core/preformatted', "one\ntwo" ),
	array( 'core/code',         "one\ntwo" ),
	array( 'core/paragraph',    'one two' ),
	array( 'core/button',       'one two' ),
);
foreach ( $host_joins as [ $name, $expected ] ) {
	$actual = bws_content_strip_for_host( "<p>one</p>\n<p>two</p>", array( 'blockName' => $name ) );
	if ( $actual === $expected ) {
		echo "  ok   host join: {$name}\n";
		continue;
	}
	$failures++;
	echo "  FAIL host join: {$name}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n";
}

// The strip and the wrap share one vocabulary: nothing the strip keeps may read as block-level.
$kept = implode( '', array_map( static fn( $t ) => "<{$t}>x</{$t}>", bws_markup_inline_tags() ) );
if ( bws_markup_has_block_level_tag( $kept ) ) {
	$failures++;
	echo "  FAIL inline vocabulary disagrees with bws_markup_has_block_level_tag()\n";
} else {
	echo "  ok   inline vocabulary agrees with bws_markup_has_block_level_tag()\n";
}

echo $failures ? "\n{$failures} FAILED\n" : "\nall " . ( count( $cases ) + count( $joins ) + count( $host_joins ) + 1 ) . " passed\n";
exit( $failures ? 1 : 0 );
