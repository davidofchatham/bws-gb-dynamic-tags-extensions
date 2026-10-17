<?php
/**
 * PHP↔JS twin agreement for {{table}}'s presentation values.
 *
 * The renderer validates in PHP (includes/helpers/table-helpers.php); the editor controls
 * validate in JS (assets/js/table-style-grammar.js) so an author hears "not a recognized width"
 * before saving. Two languages, so one implementation cannot serve both: agreement is a TESTED
 * property. One corpus (table-style-corpus.json), two drivers, one diff, in the shape of
 * slot-fold-twin-test.php.
 *
 * Scope note: agreement, not correctness. Both sides wrong in the same way passes here and
 * fails table-style-test.php. Both are required.
 *
 * Requires `node` on PATH; a missing `node` is a FAILURE, not a skip, because silently passing
 * would hide the exact drift this file exists to catch.
 *
 * Run:  php tools/test/table-style-twin-test.php
 * Exit 0 = the two implementations agree on every corpus case.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
require __DIR__ . '/../../includes/helpers/table-helpers.php';

$pass = 0;
$fail = 0;

function check( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		return;
	}
	$fail++;
	echo "FAIL  $label\n";
	if ( '' !== $detail ) {
		echo "      $detail\n";
	}
}

/** Compare one row at a time so a failure names the corpus value, not the whole list. */
function twin_rows( string $label, array $inputs, array $php, $js ): void {
	if ( ! is_array( $js ) || count( $js ) !== count( $inputs ) ) {
		check( "$label: JS produced one result per input", false, json_encode( $js ) );
		return;
	}
	foreach ( $inputs as $i => $input ) {
		$a = json_encode( $php[ $i ], JSON_UNESCAPED_SLASHES );
		$b = json_encode( $js[ $i ], JSON_UNESCAPED_SLASHES );
		check( "$label: " . json_encode( $input ), $a === $b, "php: $a\n      js:  $b" );
	}
}

/** The PHP result with flags reduced to a count, matching the JS driver. */
function counted( ?array $parsed ): ?array {
	if ( null === $parsed ) {
		return null;
	}
	$parsed['flags'] = count( $parsed['flags'] );
	return $parsed;
}

$corpus = json_decode( file_get_contents( __DIR__ . '/table-style-corpus.json' ), true );

$vocab_file = tempnam( sys_get_temp_dir(), 'bwsvocab' );
file_put_contents( $vocab_file, json_encode( bws_table_style_vocabulary() ) );
$raw = (string) shell_exec( 'node ' . escapeshellarg( __DIR__ . '/table-style-twin-driver.js' ) . ' ' . escapeshellarg( $vocab_file ) . ' ' . escapeshellarg( __DIR__ . '/table-style-corpus.json' ) . ' 2>&1' );
unlink( $vocab_file );
$js = json_decode( $raw, true );
check( 'JS driver produced JSON (node on PATH)', is_array( $js ), substr( $raw, 0, 800 ) );
if ( ! is_array( $js ) ) {
	echo "\n$pass passed, $fail failed\n";
	exit( 1 );
}

twin_rows( 'width', $corpus['widths'], array_map( 'bws_table_width_value', $corpus['widths'] ), $js['widths'] );
twin_rows( 'color', $corpus['colors'], array_map( static fn( $v ) => bws_table_color_value( $v, false ), $corpus['colors'] ), $js['colors'] );
twin_rows( 'color+globals', $corpus['colors'], array_map( static fn( $v ) => bws_table_color_value( $v, true ), $corpus['colors'] ), $js['colorsGlobals'] );
twin_rows(
	'color alpha',
	$corpus['colors'],
	array_map(
		static function ( $v ) {
			$c = bws_table_color_value( $v, false );
			return null === $c ? null : bws_table_color_has_alpha( $c );
		},
		$corpus['colors']
	),
	$js['alpha']
);
twin_rows( 'fontSize', $corpus['fontSizes'], array_map( 'bws_table_font_size_value', $corpus['fontSizes'] ), $js['fontSizes'] );
twin_rows( 'opacity', $corpus['opacities'], array_map( 'bws_table_opacity_value', $corpus['opacities'] ), $js['opacities'] );
foreach ( $corpus['text'] as $key => $values ) {
	twin_rows( "text $key", $values, array_map( static fn( $v ) => bws_table_text_value( $key, $v ), $values ), $js['text'][ $key ] ?? null );
}
foreach ( $corpus['headed'] as $key => $values ) {
	twin_rows( "headed $key", $values, array_map( static fn( $v ) => counted( bws_table_text_parse( $key, $v ) ), $values ), $js['headed'][ $key ] ?? null );
}
twin_rows( 'stripe', $corpus['stripes'], array_map( static fn( $v ) => counted( bws_table_stripe_parse( $v ) ), $corpus['stripes'] ), $js['stripes'] );
twin_rows(
	'rules',
	$corpus['rules'],
	array_map(
		static fn( $c ) => bws_table_rules( bws_table_style_read( $c['options'] ), array_map( 'bws_table_column_style_read', $c['columns'] ), $c['hasHeader'], $c['defaults'] ),
		$corpus['rules']
	),
	$js['rules']
);
twin_rows( 'items', $corpus['items'], array_map( 'bws_head_args_items', $corpus['items'] ), $js['items'] );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
