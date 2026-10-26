<?php
/**
 * Standalone harness for the settings-page block list (FW-156) — the pure row builder and the
 * text helper in includes/helpers/core-block-picker.php.
 *
 * Asserts what the page shows and what a save sends back: which rows exist and in what groups,
 * which are selected or locked, which note each row carries, and that the checkboxes the page
 * posts, run through the real sanitizer (bws_core_blocks_clean), keep every saved name. The
 * wording itself is not asserted; every note is checked only to have a string.
 *
 * Loads the real picker, enabled-set and classification files. Fabricated: __ / esc_html.
 *
 * Run:  php tools/test/core-block-picker-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

function __( $text ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/link-helpers.php';
require $root . '/includes/helpers/core-block-tags.php';
require $root . '/includes/helpers/core-block-picker.php';

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

function entry( $name, $title, $category = 'text', $inserter = true, $variations = array() ) {
	return array( 'name' => $name, 'title' => $title, 'category' => $category, 'inserter' => $inserter, 'variations' => $variations );
}

/** Flatten groups to name => row. */
function rows_by_name( array $groups ): array {
	$out = array();
	foreach ( $groups as $g ) {
		foreach ( $g['rows'] as $r ) {
			$out[ $r['name'] ] = $r;
		}
	}
	return $out;
}

$cats    = array( 'text' => 'Text', 'media' => 'Media', 'design' => 'Design' );
$entries = array(
	entry( 'core/paragraph', 'Paragraph' ),
	entry( 'core/heading', 'Heading' ),
	entry( 'core/list', 'List' ),
	entry( 'core/list-item', 'List Item' ),
	entry( 'core/button', 'Button', 'design' ),
	entry( 'core/table', 'Table', 'text' ),
	entry( 'core/embed', 'Embed', 'media', true, array( 'YouTube', 'Vimeo' ) ),
	entry( 'acme/hidden', 'Hidden Thing', 'text', false ),
	entry( 'acme/widget', 'Widget', 'acme-cat' ),
	entry( 'acme/untitled', '', 'text' ),
);

echo "§1 — groups and ordering\n";
$groups = bws_core_blocks_picker_rows( $entries, $cats, array(), array() );
assert_same( 'categories in registry order, unknown category last', array( 'text', 'media', 'design', 'acme-cat' ), array_column( $groups, 'slug' ) );
assert_same( 'rows sorted by title inside a group (untitled falls back to its slug label)', array( 'core/heading', 'core/list', 'core/list-item', 'core/paragraph', 'core/table', 'acme/untitled' ), array_map( fn( $r ) => $r['name'], $groups[0]['rows'] ) );
assert_same( 'unknown category gets a readable label', 'Acme-cat', $groups[3]['label'] );
assert_same( 'inserter:false is hidden when not selected', false, isset( rows_by_name( $groups )['acme/hidden'] ) );
assert_same( 'untitled block is labeled from its slug', 'Untitled', rows_by_name( $groups )['acme/untitled']['title'] );

echo "§2 — selection, code-set and unregistered rows\n";
$groups = bws_core_blocks_picker_rows( $entries, $cats, array( 'core/paragraph', 'acme/gone', 'acme/hidden' ), array( 'core/paragraph', 'acme/gone', 'acme/hidden', 'core/heading', 'core/zzz-gone' ) );
$rows   = rows_by_name( $groups );
assert_same( 'saved row is selected and editable', array( true, false ), array( $rows['core/paragraph']['selected'], $rows['core/paragraph']['disabled'] ) );
assert_same( 'unselected row is neither', array( false, false ), array( $rows['core/button']['selected'], $rows['core/button']['disabled'] ) );
assert_same( 'filter-added row is selected, locked and marked', array( true, true, true ), array( $rows['core/heading']['selected'], $rows['core/heading']['disabled'], in_array( 'code_set', $rows['core/heading']['notes'], true ) ) );
assert_same( 'saved inserter:false block is shown so a save keeps it', true, isset( $rows['acme/hidden'] ) && $rows['acme/hidden']['selected'] );
assert_same( 'the not-registered group comes last', true, end( $groups )['inactive'] );
assert_same( 'saved unregistered name is a selected, editable row titled by its name', array( true, false, 'acme/gone' ), array( $rows['acme/gone']['selected'], $rows['acme/gone']['disabled'], $rows['acme/gone']['title'] ) );
assert_same( 'unregistered row says so', true, in_array( 'unregistered', $rows['acme/gone']['notes'], true ) );
assert_same( 'unregistered filter-added row is locked and says both', array( true, array( 'unregistered', 'code_set' ) ), array( $rows['core/zzz-gone']['disabled'], $rows['core/zzz-gone']['notes'] ) );
assert_same( 'no empty groups', true, count( array_filter( $groups, fn( $g ) => array() === $g['rows'] ) ) === 0 );

echo "§3 — the consequence note comes from the classification owner\n";
$rows = rows_by_name( bws_core_blocks_picker_rows( $entries, $cats, array(), array() ) );
foreach ( array( 'core/paragraph', 'core/heading', 'core/list-item', 'core/table' ) as $name ) {
	assert_same( "{$name}: inline consequence", array( 'kind_inline' ), $rows[ $name ]['notes'] );
}
assert_same( 'core/button: button consequence', array( 'kind_button' ), $rows['core/button']['notes'] );
assert_same( 'core/list: points at List Item, not "not tested"', array( 'list_hint' ), $rows['core/list']['notes'] );
assert_same( 'unclassified: not tested', array( 'not_tested' ), $rows['acme/widget']['notes'] );

echo "§4 — variations and search text\n";
assert_same( 'variations carried through', array( 'YouTube', 'Vimeo' ), $rows['core/embed']['variations'] );
assert_same( 'no variations is an empty list', array(), $rows['core/paragraph']['variations'] );
$hay = $rows['core/embed']['search'];
foreach ( array( 'embed', 'core/embed', 'media', 'youtube' ) as $needle ) {
	assert_same( "search text holds '{$needle}'", true, false !== strpos( $hay, $needle ) );
}
assert_same( 'search text is lowercase', strtolower( $hay ), $hay );

echo "§5 — what a save sends back\n";
$saved   = array( 'core/paragraph', 'acme/gone', 'acme/hidden' );
$enabled = array( 'core/paragraph', 'acme/gone', 'acme/hidden', 'core/heading' );
$rows    = rows_by_name( bws_core_blocks_picker_rows( $entries, $cats, $saved, $enabled ) );
$posted  = array_keys( array_filter( $rows, fn( $r ) => $r['selected'] && ! $r['disabled'] ) );
assert_same( 'posted names, cleaned, equal the saved list (unregistered and hidden kept, code-set not persisted)', $saved, array_values( array_intersect( $saved, bws_core_blocks_clean( $posted ) ) ) );
assert_same( 'and nothing else rides along', count( $saved ), count( bws_core_blocks_clean( $posted ) ) );
$rows   = rows_by_name( bws_core_blocks_picker_rows( $entries, $cats, array(), array() ) );
assert_same( 'nothing selected posts nothing (empty = off)', array(), array_keys( array_filter( $rows, fn( $r ) => $r['selected'] ) ) );

echo "§6 — every note has a string, and the text helper\n";
$strings = bws_core_blocks_picker_strings();
foreach ( array( 'kind_inline', 'kind_button', 'list_hint', 'not_tested', 'unregistered', 'code_set' ) as $key ) {
	assert_same( "string for note '{$key}'", true, is_string( $strings[ $key ] ?? null ) && '' !== $strings[ $key ] );
}
assert_same( 'backticks become code, html is escaped first', 'a <code>x &lt;b&gt;</code> &amp; b', bws_core_blocks_picker_markup( 'a `x <b>` & b' ) );
assert_same( 'asterisks become emphasis', 'set to <em>Link To</em>.', bws_core_blocks_picker_markup( 'set to *Link To*.' ) );
assert_same( 'plain text is untouched', 'No marks here.', bws_core_blocks_picker_markup( 'No marks here.' ) );

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
