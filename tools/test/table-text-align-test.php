<?php
/**
 * Standalone harness for {{table}}'s `textAlign` value: the shared `base;header,x` reader
 * (bws_headed_value_parse), the textAlign validator and the header/body cascade, all in
 * includes/helpers/table-helpers.php.
 *
 * Pure: the reader splits through bws_fold_split_depth() in slot-fold.php, nothing else.
 * This pins which alignment each cell resolves to.
 *
 * Run:  php tools/test/table-text-align-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/table-helpers.php';

$failures = 0;

function check( $label, $expected, $actual ): void {
	global $failures;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n       expected: " . json_encode( $expected ) . "\n       actual:   " . json_encode( $actual ) . "\n";
}

/** Parse and drop the flag wording, keeping only whether each was raised. */
function parsed( string $value ): ?array {
	$p = bws_table_text_align_parse( $value );
	if ( null !== $p ) {
		$p['flags'] = count( $p['flags'] );
	}
	return $p;
}

echo "the reader: well-formed values\n";
check( 'bare base', array( 'base' => 'right', 'header' => null, 'flags' => 0 ), parsed( 'right' ) );
check( 'base and header', array( 'base' => 'left', 'header' => 'center', 'flags' => 0 ), parsed( 'left;header,center' ) );
check( 'header only', array( 'base' => null, 'header' => 'center', 'flags' => 0 ), parsed( 'header,center' ) );
check( 'a header before the base reads the same, base-first by construction', parsed( 'left;header,center' ), parsed( 'header,center;left' ) );
check( 'case and padding are forgiven', array( 'base' => 'right', 'header' => 'center', 'flags' => 0 ), parsed( ' RIGHT ; header , Center ' ) );
check( 'CSS globals are values', array( 'base' => 'inherit', 'header' => 'revert-layer', 'flags' => 0 ), parsed( 'inherit;header,revert-layer' ) );
check( 'empty value is nothing, not an error', array( 'base' => null, 'header' => null, 'flags' => 0 ), parsed( '' ) );
check( 'a trailing `;` is harmless', array( 'base' => 'right', 'header' => null, 'flags' => 0 ), parsed( 'right;' ) );

echo "the reader: hand-edit rules (drop the item, flag it, keep the siblings)\n";
check( 'unrecognized base is dropped and flagged', array( 'base' => null, 'header' => 'center', 'flags' => 1 ), parsed( 'justify;header,center' ) );
check( 'no `justify`', array( 'base' => null, 'header' => null, 'flags' => 1 ), parsed( 'justify' ) );
check( 'a second base keeps the first', array( 'base' => 'left', 'header' => null, 'flags' => 1 ), parsed( 'left;right' ) );
check( 'a repeated header keeps the first', array( 'base' => 'left', 'header' => 'center', 'flags' => 1 ), parsed( 'left;header,center;header,right' ) );
check( 'an unknown head is dropped', array( 'base' => 'left', 'header' => null, 'flags' => 1 ), parsed( 'left;footer,center' ) );
check( 'an empty `header,` is dropped', array( 'base' => 'left', 'header' => null, 'flags' => 1 ), parsed( 'left;header,' ) );
check( 'a header with two values is dropped', array( 'base' => 'left', 'header' => null, 'flags' => 1 ), parsed( 'left;header,center,right' ) );
check( 'an invalid first base does not block a valid second', array( 'base' => 'right', 'header' => null, 'flags' => 1 ), parsed( 'bogus;right' ) );
check( 'an unbalanced open bracket drops the whole key', null, parsed( 'left;header,(center' ) );
check( 'an unbalanced close bracket drops the whole key', null, parsed( 'left)' ) );

echo "the cascade\n";
$col_bare   = bws_table_text_align_parse( 'right' );
$col_head   = bws_table_text_align_parse( 'right;header,center' );
$tbl        = bws_table_text_align_parse( 'left;header,center' );
$tbl_bare   = bws_table_text_align_parse( 'left' );
$tbl_header = bws_table_text_align_parse( 'header,center' );
$none       = bws_table_text_align_parse( '' );
check( 'body cell: column base wins over the table', 'right', bws_table_text_align_for_cell( $tbl, $col_head, false ) );
check( 'body cell: falls to the table base', 'left', bws_table_text_align_for_cell( $tbl, null, false ) );
check( 'body cell never takes a header override', '', bws_table_text_align_for_cell( $tbl_header, $none, false ) );
check( 'header cell: column Header first', 'center', bws_table_text_align_for_cell( $tbl, $col_head, true ) );
check( 'header cell: a bare column value covers it ("header included")', 'right', bws_table_text_align_for_cell( $tbl, $col_bare, true ) );
check( 'header cell: the column base beats the table Header', 'right', bws_table_text_align_for_cell( $tbl_header, $col_bare, true ) );
check( 'header cell: table Header beats table base', 'center', bws_table_text_align_for_cell( $tbl, null, true ) );
check( 'header cell: table base last', 'left', bws_table_text_align_for_cell( $tbl_bare, null, true ) );
check( 'no value anywhere is no alignment, not a default', '', bws_table_text_align_for_cell( null, null, true ) );
check( 'a column with no textAlign contributes none', '', bws_table_text_align_for_cell( null, null, false ) );
check( 'a refused value is as if absent: the cascade falls through', 'left', bws_table_text_align_for_cell( $tbl_bare, bws_table_text_align_parse( 'justify' ), false ) );
check( 'an unparseable column key (null) falls through too', 'left', bws_table_text_align_for_cell( $tbl_bare, bws_table_text_align_parse( 'right;header,[x' ), false ) );

echo $failures ? "\n{$failures} FAILED\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );
