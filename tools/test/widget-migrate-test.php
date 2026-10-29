<?php
/**
 * Standalone unit harness for the block-widget migrate, backup and restore (FW-73, ticket 03).
 *
 * Four seams, all pure once WordPress's option calls are stubbed:
 *   - TagConverter::parse_row_ids()           `widget:N` beside integer post ids.
 *   - TagConverter::migrate_widget_option()   the widget_block array in, the rewritten array out.
 *   - TagConverter::restore_widget_option()   THE RESTORE EQUALITY RULE: revert an instance only
 *                                             while its content still equals what the run wrote.
 *   - TagConverter::migrate_widgets() / restore_widgets()  the option reads and writes around them:
 *                                             fresh read, one write, backup first, only when
 *                                             something changed.
 *
 * The real files are loaded; only get_option / update_option / delete_option / wp_date are stubbed
 * (the stub records every write in order, so "backup before the widget write" is observable), and
 * the migration entries are synthetic so a registration change elsewhere cannot move this file.
 *
 * WHAT A PASS HERE DOES NOT PROVE. That the AJAX handler routes widget ids here, that the
 * Migration Tool draws the Restore button, and that the seeded widget renders the same after a
 * migrate: those are the testbed's (core-block-test-matrix.md §CB6, the Scan Content button).
 *
 * Run:  php tools/test/widget-migrate-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$GLOBALS['wm_options'] = array();
$GLOBALS['wm_writes']  = array(); // [ [ name, autoload ], ... ] in call order.

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $one, $many, $n, $d = null ) { return 1 === $n ? $one : $many; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $GLOBALS['wm_options'][ $name ] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['wm_options'][ $name ] = $value;
		$GLOBALS['wm_writes'][]         = array( $name, $autoload );
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) { unset( $GLOBALS['wm_options'][ $name ] ); return true; }
}
if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $ts = null ) { return gmdate( $format, $ts ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
function bws_gb_tag_name_collisions(): array {
	return array();
}

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/slot-fold.php';
require $root . '/includes/helpers/serialization-order.php';
require $root . '/includes/helpers/gb-output-boundary.php';
require $root . '/includes/helpers/converter-ownership.php';
require $root . '/includes/helpers/core-block-survey.php';
require $root . '/includes/classes/class-migration-registry.php';
require $root . '/includes/classes/admin/class-tag-converter.php';

use BWS\DynamicTags\Admin\TagConverter;
use BWS\DynamicTags\MigrationRegistry;

$failures = 0;
$count    = 0;

function assert_eq( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo '       expected: ' . var_export( $expected, true ) . "\n";
	echo '       actual:   ' . var_export( $actual, true ) . "\n";
}

MigrationRegistry::register( array(
	'type'               => 'tag',
	'match_tag'          => 'wm_old',
	'new_tag'            => 'wm_new',
	'transform_callback' => static fn ( string $s ): string => str_replace( '{{wm_old', '{{wm_new', $s ),
) );

$old   = '<p>{{wm_old use:x}}</p>';
$new   = '<p>{{wm_new use:x}}</p>';
$clean = '<p>{{unrelated}}</p>';

echo "WM1 - row ids: widget:N beside integer post ids\n";

assert_eq( 'WM1.1 mixed ids split; ints and numeric strings are posts',
	array( 'posts' => array( 12, 7 ), 'widgets' => array( 3, 9001 ) ),
	TagConverter::parse_row_ids( array( 12, 'widget:3', '7', 'widget:9001' ) ) );
assert_eq( 'WM1.2 junk, zero and malformed widget ids are dropped',
	array( 'posts' => array(), 'widgets' => array() ),
	TagConverter::parse_row_ids( array( 0, -4, 'widget:', 'widget:x', 'widget:0', 'post:3', null, '' ) ) );

echo "\nWM2 - migrating the widget_block array\n";

$option = array(
	'_multiwidget' => 1,
	3              => array( 'content' => $old, 'extra' => 'kept' ),
	4              => array( 'content' => $clean ),
	9001           => array( 'content' => $old ),
);

$r = TagConverter::migrate_widget_option( $option, null );
assert_eq( 'WM2.1 only the content of a changed instance moves; every other key survives',
	array(
		'_multiwidget' => 1,
		3              => array( 'content' => $new, 'extra' => 'kept' ),
		4              => array( 'content' => $clean ),
		9001           => array( 'content' => $new ),
	),
	$r['option'] );
assert_eq( 'WM2.2 changed instances are reported with before and after',
	array( 3 => array( 'before' => $old, 'after' => $new ), 9001 => array( 'before' => $old, 'after' => $new ) ),
	$r['changed'] );
assert_eq( 'WM2.3 per-instance results: counts, and a clean instance is unchanged',
	array( true, 1, false ),
	array( $r['results'][3]['changed'], $r['results'][3]['tag_count'], $r['results'][4]['changed'] ) );

$r = TagConverter::migrate_widget_option( $option, array( 9001 ) );
assert_eq( 'WM2.4 a single-widget migrate touches only the named instance',
	array( 9001 ), array_keys( $r['changed'] ) );
assert_eq( 'WM2.5 and leaves the others byte-identical', $old, $r['option'][3]['content'] );

$r = TagConverter::migrate_widget_option( $option, array( 777 ) );
assert_eq( 'WM2.6 an instance that no longer exists is simply absent from the results', array(), $r['results'] );

echo "\nWM3 - the wrapper: fresh read, one write, backup first, only when something changed\n";

$GLOBALS['wm_options'] = array( 'widget_block' => $option );
$GLOBALS['wm_writes']  = array();
$run = TagConverter::migrate_widgets( null, 'run-a' );

assert_eq( 'WM3.1 the backup is written BEFORE the widget option, and the backup is not autoloaded',
	array( array( TagConverter::WIDGET_BACKUP_OPTION, false ), array( 'widget_block', null ) ),
	$GLOBALS['wm_writes'] );
assert_eq( 'WM3.2 the widget option holds the rewritten content, _multiwidget intact',
	array( 1, $new, $new ),
	array( $GLOBALS['wm_options']['widget_block']['_multiwidget'], $GLOBALS['wm_options']['widget_block'][3]['content'], $GLOBALS['wm_options']['widget_block'][9001]['content'] ) );
$bk = $GLOBALS['wm_options'][ TagConverter::WIDGET_BACKUP_OPTION ];
assert_eq( 'WM3.3 the backup holds before and after per changed instance, the run id and a date',
	array( array( 3, 9001 ), $old, $new, 'run-a', true ),
	array( array_keys( $bk['instances'] ), $bk['instances'][3]['before'], $bk['instances'][3]['after'], $bk['run'], is_int( $bk['date'] ) ) );
assert_eq( 'WM3.4 the run result carries per-instance results', true, $run[3]['changed'] );

$GLOBALS['wm_writes'] = array();
TagConverter::migrate_widgets( null, 'run-b' );
assert_eq( 'WM3.5 a run that changes nothing writes nothing, and keeps the earlier backup',
	array( array(), 'run-a' ),
	array( $GLOBALS['wm_writes'], $GLOBALS['wm_options'][ TagConverter::WIDGET_BACKUP_OPTION ]['run'] ) );

// A new run replaces the backup; the same run id (a later batch of one bulk run) merges into it.
$GLOBALS['wm_options']['widget_block'] = array(
	3    => array( 'content' => $old ),
	9001 => array( 'content' => $old ),
);
TagConverter::migrate_widgets( array( 3 ), 'run-c' );
$bk = $GLOBALS['wm_options'][ TagConverter::WIDGET_BACKUP_OPTION ];
assert_eq( 'WM3.6 a new run overwrites the backup', array( 'run-c', array( 3 ) ), array( $bk['run'], array_keys( $bk['instances'] ) ) );
TagConverter::migrate_widgets( array( 9001 ), 'run-c' );
$bk = $GLOBALS['wm_options'][ TagConverter::WIDGET_BACKUP_OPTION ];
assert_eq( 'WM3.7 a later batch of the same run adds to it instead of replacing it', array( 3, 9001 ), array_keys( $bk['instances'] ) );

echo "\nWM4 - the restore equality rule\n";

$edited = '<p>{{wm_new use:x}} and I typed more</p>';
$option = array(
	'_multiwidget' => 1,
	3              => array( 'content' => $new, 'extra' => 'kept' ),
	5              => array( 'content' => $edited ),
);
$instances = array(
	3  => array( 'before' => $old, 'after' => $new ),
	5  => array( 'before' => $old, 'after' => $new ),
	8  => array( 'before' => $old, 'after' => $new ),
);
$r = TagConverter::restore_widget_option( $option, $instances );

assert_eq( 'WM4.1 unchanged since the run: reverted, other keys kept', array( $old, 'kept' ), array( $r['option'][3]['content'], $r['option'][3]['extra'] ) );
assert_eq( 'WM4.2 edited since the run: skipped, content untouched', array( $edited, 'edited' ), array( $r['option'][5]['content'], $r['skipped'][5] ) );
assert_eq( 'WM4.3 instance gone since the run: skipped as missing, nothing re-created', array( 'missing', false ), array( $r['skipped'][8], isset( $r['option'][8] ) ) );
assert_eq( 'WM4.4 restored list', array( 3 ), $r['restored'] );
assert_eq( 'WM4.5 _multiwidget survives', 1, $r['option']['_multiwidget'] );

echo "\nWM5 - the restore wrapper\n";

$GLOBALS['wm_options'] = array(
	'widget_block'                        => $option,
	TagConverter::WIDGET_BACKUP_OPTION    => array( 'date' => 1700000000, 'run' => 'x', 'instances' => $instances ),
);
$GLOBALS['wm_writes'] = array();
$out = TagConverter::restore_widgets();
assert_eq( 'WM5.1 one widget write, and the backup is cleared after a restore',
	array( array( array( 'widget_block', null ) ), false ),
	array( $GLOBALS['wm_writes'], isset( $GLOBALS['wm_options'][ TagConverter::WIDGET_BACKUP_OPTION ] ) ) );
assert_eq( 'WM5.2 the reply names what was restored and what was skipped', array( array( 3 ), array( 5 => 'edited', 8 => 'missing' ) ), array( $out['restored'], $out['skipped'] ) );

$GLOBALS['wm_writes'] = array();
$out = TagConverter::restore_widgets();
assert_eq( 'WM5.3 with no backup there is nothing to do and nothing is written', array( array(), array(), array() ), array( $out['restored'], $out['skipped'], $GLOBALS['wm_writes'] ) );

echo "\nWM6 - the Restore button's state\n";

assert_eq( 'WM6.1 no backup: no line, so no button', '', TagConverter::widget_backup_line() );
$GLOBALS['wm_options'][ TagConverter::WIDGET_BACKUP_OPTION ] = array( 'date' => 1700000000, 'run' => 'x', 'instances' => $instances );
assert_eq( 'WM6.2 a backup: a line naming the date and the count', true, 1 === preg_match( '/2023-11-14.*3/', TagConverter::widget_backup_line() ) );

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
