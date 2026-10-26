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
foreach ( array( 'core/paragraph', 'core/heading', 'core/list-item' ) as $name ) {
	assert_same( "{$name}: inline consequence", array( 'kind_inline' ), $rows[ $name ]['notes'] );
}
assert_same( 'core/table: its own table consequence, not the paragraph one', array( 'kind_table' ), $rows['core/table']['notes'] );
assert_same( 'core/button: button consequence', array( 'kind_button' ), $rows['core/button']['notes'] );
assert_same( 'core/list: points at List Item', array( 'list_hint' ), $rows['core/list']['notes'] );
assert_same( 'unclassified: no note (the intro says once that it is untested)', array(), $rows['acme/widget']['notes'] );

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
foreach ( array( 'kind_inline', 'kind_table', 'kind_button', 'list_hint', 'unregistered', 'code_set' ) as $key ) {
	assert_same( "string for note '{$key}'", true, is_string( $strings[ $key ] ?? null ) && '' !== $strings[ $key ] );
}
assert_same( 'backticks become code, html is escaped first', 'a <code>x &lt;b&gt;</code> &amp; b', bws_core_blocks_picker_markup( 'a `x <b>` & b' ) );
assert_same( 'asterisks become emphasis', 'set to <em>Link To</em>.', bws_core_blocks_picker_markup( 'set to *Link To*.' ) );
assert_same( 'plain text is untouched', 'No marks here.', bws_core_blocks_picker_markup( 'No marks here.' ) );

echo "§6b — scan counts ride on the rows\n";
$survey = array( 'core/paragraph' => array( 'registered' => 3, 'unregistered' => 1 ), 'core/gone' => array( 'registered' => 9, 'unregistered' => 0 ) );
$rows   = rows_by_name( bws_core_blocks_picker_rows( $entries, $cats, array(), array(), array(), $survey ) );
assert_same( 'a scanned block carries its counts', array( 3, 1 ), array( $rows['core/paragraph']['registered'], $rows['core/paragraph']['unregistered'] ) );
assert_same( 'a block the scan found nothing in carries zeros once a scan exists', array( 0, 0 ), array( $rows['core/heading']['registered'], $rows['core/heading']['unregistered'] ) );
$rows = rows_by_name( bws_core_blocks_picker_rows( $entries, $cats, array(), array() ) );
assert_same( 'before any scan the counts are null, not zero', array( null, null ), array( $rows['core/paragraph']['registered'], $rows['core/paragraph']['unregistered'] ) );
$text = bws_core_blocks_picker_strings();
assert_same( 'no counts: no line', '', bws_core_blocks_survey_row_html( null, $text ) );
assert_same( 'a scan that found nothing here: no line', '', bws_core_blocks_survey_row_html( array( 'registered' => 0, 'unregistered' => 0 ), $text ) );
$html = bws_core_blocks_survey_row_html( array( 'registered' => 3, 'unregistered' => 0 ), $text );
assert_same( 'registered only: one line carrying the number', true, 1 === substr_count( $html, '<p' ) && false !== strpos( $html, '3' ) );
$html = bws_core_blocks_survey_row_html( array( 'registered' => 3, 'unregistered' => 12 ), $text );
assert_same( 'both: two lines, both numbers', true, 2 === substr_count( $html, '<p' ) && false !== strpos( $html, '3' ) && false !== strpos( $html, '12' ) );
assert_same( 'unregistered only: one line', true, 1 === substr_count( bws_core_blocks_survey_row_html( array( 'registered' => 0, 'unregistered' => 2 ), $text ), '<p' ) );
assert_same( 'the status line names the date it is given', true, false !== strpos( bws_core_blocks_survey_status( 1700000000, $text, static fn( int $t ): string => 'DATE' ), 'DATE' ) );
foreach ( array( 'survey_hint', 'survey_scan', 'survey_running', 'survey_last', 'survey_error', 'row_registered', 'row_unregistered', 'confirm_intro', 'confirm_save', 'confirm_back' ) as $key ) {
	assert_same( "string for '{$key}'", true, is_string( $text[ $key ] ?? null ) && '' !== $text[ $key ] );
}

echo "§7 — GenerateBlocks' own allowlisted blocks are left out\n";
$gb_entries = array_merge( $entries, array( entry( 'generateblocks/text', 'Text', 'text' ), entry( 'generateblocks-pro/thing', 'Pro Thing', 'text' ) ) );
$native     = array( 'generateblocks/text', 'generateblocks-pro/thing' );
$rows       = rows_by_name( bws_core_blocks_picker_rows( $gb_entries, $cats, array(), array(), $native ) );
assert_same( 'native blocks are not listed', array( false, false ), array( isset( $rows['generateblocks/text'] ), isset( $rows['generateblocks-pro/thing'] ) ) );
assert_same( 'other rows are untouched', true, isset( $rows['core/paragraph'] ) && isset( $rows['acme/widget'] ) );
$rows = rows_by_name( bws_core_blocks_picker_rows( $gb_entries, $cats, array(), array(), array() ) );
assert_same( 'GB and GB Pro blocks are hidden by namespace even with no native list', array( false, false ), array( isset( $rows['generateblocks/text'] ), isset( $rows['generateblocks-pro/thing'] ) ) );
$rows = rows_by_name( bws_core_blocks_picker_rows( array_merge( $entries, array( entry( 'acme/native', 'Native', 'text' ) ) ), $cats, array(), array(), array( 'acme/native' ) ) );
assert_same( 'a third-party block on the GB list is hidden through the native list', false, isset( $rows['acme/native'] ) );
$groups = bws_core_blocks_picker_rows( $gb_entries, $cats, array( 'generateblocks/text' ), array( 'generateblocks/text' ), $native );
assert_same( 'a native block already saved stays listed so a save never drops it', true, isset( rows_by_name( $groups )['generateblocks/text'] ) );

echo "§8 — the reader of GB's own list\n";
$GLOBALS['__filters'] = array();
function add_filter( $tag, $fn, $priority = 10 ) {
	$GLOBALS['__filters'][ $tag ][ $priority ][] = $fn;
	return true;
}
function has_filter( $tag, $fn ) {
	foreach ( $GLOBALS['__filters'][ $tag ] ?? array() as $priority => $fns ) {
		if ( in_array( $fn, $fns, true ) ) {
			return $priority;
		}
	}
	return false;
}
function remove_filter( $tag, $fn, $priority = 10 ) {
	$key = array_search( $fn, $GLOBALS['__filters'][ $tag ][ $priority ] ?? array(), true );
	if ( false === $key ) {
		return false;
	}
	unset( $GLOBALS['__filters'][ $tag ][ $priority ][ $key ] );
	return true;
}
function apply_filters( $tag, $value ) {
	$by_priority = $GLOBALS['__filters'][ $tag ] ?? array();
	ksort( $by_priority );
	foreach ( $by_priority as $fns ) {
		foreach ( $fns as $fn ) {
			$value = $fn( $value );
		}
	}
	return $value;
}
assert_same( 'GB absent: nothing is known native, nothing fatals', array(), bws_core_blocks_gb_native() );

class WP_Block_Type_Registry {
	public static function get_instance() {
		return new self();
	}
	public function is_registered( $name ) {
		return true;
	}
}
// Declared conditionally so it is not hoisted above the 'GB absent' assertion.
if ( true ) {
	class GenerateBlocks_Dynamic_Tags {
		public static function get_instance() {
			return new self();
		}
		public function get_allowed_blocks() {
			return apply_filters( 'generateblocks_dynamic_tags_allowed_blocks', array( 'generateblocks/text', 'generateblocks/element' ) );
		}
	}
}
$gb = 'generateblocks_dynamic_tags_allowed_blocks';
add_filter( $gb, static fn( $a ) => array_merge( $a, array( 'generateblocks-pro/thing' ) ), 5 );
add_filter( $gb, 'bws_core_blocks_allow', 20 );
eval( 'namespace BWS\DynamicTags\Admin; class SettingsPage { public static function get_settings() { return array( "core_blocks" => array( "core/paragraph" ) ); } }' );
assert_same( 'control: with ours hooked, the live list does carry the enabled block', true, in_array( 'core/paragraph', GenerateBlocks_Dynamic_Tags::get_instance()->get_allowed_blocks(), true ) );
assert_same( 'GB core plus Pro additions, without ours', array( 'generateblocks/text', 'generateblocks/element', 'generateblocks-pro/thing' ), bws_core_blocks_gb_native() );
assert_same( 'our callback is back on the filter at its own priority afterwards', 20, has_filter( $gb, 'bws_core_blocks_allow' ) );

echo "\n{$count} assertions, {$failures} failed\n";
exit( $failures ? 1 : 0 );
