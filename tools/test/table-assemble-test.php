<?php
/**
 * Standalone unit harness for {{table}} HTML assembly (bws_table_assemble).
 *
 * Copies the pure assembler INLINE (house pattern — no WP, no autoload). Mirror of
 * bws_table_assemble() in includes/tags/table-tags.php. esc_html/esc_attr are
 * stubbed to a minimal htmlspecialchars so the markup shape is exercised without WP.
 *
 * Covers the W3C responsive-data-table pattern added 1.17.0:
 *   - the always-present scroll wrapper (role=region, tabindex=0),
 *   - <caption> + aria-labelledby wiring (present only when a caption is set),
 *   - <thead> emitted only when a header label exists,
 *   - escaping of caption / headers / cells,
 *   - empty-matrix short-circuit.
 *
 * Keep in sync with bws_table_assemble on any assembly change (CLAUDE.md §Update
 * triggers — table family).
 *
 * Run:  php tools/test/table-assemble-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

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
	echo "       expected: " . var_export( $expected, true ) . "\n";
	echo "       actual:   " . var_export( $actual, true ) . "\n";
}

function assert_contains( string $label, string $needle, string $haystack ): void {
	global $failures, $count;
	$count++;
	if ( str_contains( $haystack, $needle ) ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       missing: {$needle}\n";
	echo "       in:      {$haystack}\n";
}

function assert_not_contains( string $label, string $needle, string $haystack ): void {
	global $failures, $count;
	$count++;
	if ( ! str_contains( $haystack, $needle ) ) {
		echo "  ok   {$label}\n";
		return;
	}
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       unexpected: {$needle}\n";
	echo "       in:         {$haystack}\n";
}

// ---------------------------------------------------------------------------
// WP escaping stubs — minimal htmlspecialchars, enough to exercise the shape.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}

// ---------------------------------------------------------------------------
// INLINE COPY — bws_table_assemble (mirror of includes/tags/table-tags.php).
// ---------------------------------------------------------------------------
function t_table_assemble( array $headers, array $matrix, string $caption = '', string $caption_id = '' ): string {
	if ( empty( $matrix ) ) {
		return '';
	}

	$has_caption = '' !== $caption && '' !== $caption_id;

	$has_header = false;
	foreach ( $headers as $h ) {
		if ( '' !== (string) $h ) {
			$has_header = true;
			break;
		}
	}

	$html = '';

	if ( $has_caption ) {
		$html .= '<div role="region" tabindex="0" aria-labelledby="' . esc_attr( $caption_id ) . '">';
	}

	$html .= '<table>';

	if ( $has_caption ) {
		$html .= '<caption id="' . esc_attr( $caption_id ) . '">' . esc_html( $caption ) . '</caption>';
	}

	if ( $has_header ) {
		$html .= '<thead><tr>';
		foreach ( $headers as $h ) {
			$html .= '<th>' . esc_html( (string) $h ) . '</th>';
		}
		$html .= '</tr></thead>';
	}

	$html .= '<tbody>';
	foreach ( $matrix as $cells ) {
		$html .= '<tr>';
		foreach ( $cells as $cell ) {
			$html .= '<td>' . esc_html( (string) $cell ) . '</td>';
		}
		$html .= '</tr>';
	}
	$html .= '</tbody></table>';

	if ( $has_caption ) {
		$html .= '</div>';
	}

	return $html;
}

// ---------------------------------------------------------------------------
// Cases
// ---------------------------------------------------------------------------

echo "Empty matrix\n";
assert_eq( 'T1 no rows → empty string', '', t_table_assemble( array( 'A' ), array(), 'Cap', 'c1' ) );

echo "\nNo-caption table = bare <table>, no wrapper, no classes\n";
$out = t_table_assemble( array(), array( array( 'x' ) ) );
assert_not_contains( 'T2 no wrapper div', '<div', $out );
assert_not_contains( 'T2 no role=region', 'role="region"', $out );
assert_not_contains( 'T2 no tabindex', 'tabindex', $out );
assert_contains( 'T2 bare table (no class)', '<table><tbody>', $out );
assert_not_contains( 'T2 no bws-table class', 'class=', $out );

echo "\nHeader-less table\n";
$out = t_table_assemble( array( '', '' ), array( array( 'a', 'b' ) ) );
assert_not_contains( 'T3 no thead', '<thead>', $out );
assert_contains( 'T3 body row', '<tbody><tr><td>a</td><td>b</td></tr></tbody>', $out );

echo "\nHeaders (bare th — scope pair deferred)\n";
$out = t_table_assemble( array( 'Name', 'Role' ), array( array( 'Jane', 'Lead' ) ) );
assert_contains( 'T4 thead', '<thead><tr><th>Name</th><th>Role</th></tr></thead>', $out );
assert_not_contains( 'T4 no scope=col yet', 'scope=', $out );

echo "\nCaption → wrapper (role=region tabindex aria-labelledby) + <caption id>\n";
$out = t_table_assemble( array( 'Name' ), array( array( 'Jane' ) ), 'Team members', 'bws-table-caption-1' );
assert_contains( 'T5 wrapper with all three attrs', '<div role="region" tabindex="0" aria-labelledby="bws-table-caption-1">', $out );
assert_contains( 'T5 caption with id', '<caption id="bws-table-caption-1">Team members</caption>', $out );
assert_contains( 'T5 wrapper closes', '</table></div>', $out );

echo "\nCaption text but no id → no wrapper, no caption (guard)\n";
$out = t_table_assemble( array( 'Name' ), array( array( 'Jane' ) ), 'Orphan', '' );
assert_not_contains( 'T6 no caption element', '<caption', $out );
assert_not_contains( 'T6 no wrapper', '<div', $out );

echo "\nNo caption → no wrapper, no caption, no aria-labelledby\n";
$out = t_table_assemble( array( 'Name' ), array( array( 'Jane' ) ) );
assert_not_contains( 'T7 no caption element', '<caption', $out );
assert_not_contains( 'T7 no wrapper', '<div', $out );
assert_not_contains( 'T7 no aria-labelledby', 'aria-labelledby', $out );

echo "\nEscaping\n";
$out = t_table_assemble(
	array( '<b>H</b>' ),
	array( array( 'a&b<c>' ) ),
	'Cap "quoted" & <script>',
	'id"with<bad'
);
assert_contains( 'T8 header escaped', '<th>&lt;b&gt;H&lt;/b&gt;</th>', $out );
assert_contains( 'T8 cell escaped', '<td>a&amp;b&lt;c&gt;</td>', $out );
assert_contains( 'T8 caption text escaped', 'Cap &quot;quoted&quot; &amp; &lt;script&gt;', $out );
assert_contains( 'T8 caption id attr escaped', '<caption id="id&quot;with&lt;bad">', $out );

echo "\nFull shape (caption + header + multi-row)\n";
$out = t_table_assemble(
	array( 'Name', 'Role' ),
	array( array( 'Jane', 'Lead' ), array( 'Bob', 'Dev' ) ),
	'Staff',
	'cap9'
);
$expected = '<div role="region" tabindex="0" aria-labelledby="cap9">'
	. '<table>'
	. '<caption id="cap9">Staff</caption>'
	. '<thead><tr><th>Name</th><th>Role</th></tr></thead>'
	. '<tbody><tr><td>Jane</td><td>Lead</td></tr><tr><td>Bob</td><td>Dev</td></tr></tbody>'
	. '</table></div>';
assert_eq( 'T9 exact markup', $expected, $out );

// ---------------------------------------------------------------------------
echo "\n";
if ( $failures > 0 ) {
	echo "FAILED {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED {$count}/{$count}\n";
exit( 0 );
