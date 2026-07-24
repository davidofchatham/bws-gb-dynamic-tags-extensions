<?php
/**
 * Standalone unit harness for the inline-CSS / content-extraction pipeline.
 *
 * Two pure surfaces, both copied INLINE (house pattern — no WP, no autoload):
 *
 *   PART A — bws_queue_inline_css() (content-helpers.php, added 1.17.0). The shared
 *   footer-queue that dedupes plugin-authored CSS by id so N tag instances on one
 *   page yield ONE <style id> per distinct id. add_action is stubbed to a recorder
 *   so the dedupe / per-id-payload / empty-guard logic is asserted without WP.
 *
 *   PART B — ContentProcessor's pure string transforms
 *   (includes/classes/content/class-content-processor.php): the {{content}} inline
 *   <style> extractor, block-comment strip, block-comment CSS extractor, and
 *   dynamic-tag strip. These regex transforms were never harnessed; this closes
 *   that gap. The extractor's static CSS queue is replaced here by a returned
 *   accumulator so the pure regex is exercised in isolation.
 *
 * Keep in sync with those functions on any change (CLAUDE.md §Update triggers —
 * text read-seam / content pipeline; the table family also references this for the
 * shared queue helper).
 *
 * Run:  php tools/test/inline-css-pipeline-test.php   (exit 0 = pass, 1 = fail)
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

// ---------------------------------------------------------------------------
// WP stubs. add_action records each registration so we can inspect what the
// queue helper registered (hook, callback, priority) and later FIRE the
// callbacks to capture the emitted <style> markup.
// ---------------------------------------------------------------------------
$GLOBALS['__actions'] = array();

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
		$GLOBALS['__actions'][] = array( 'hook' => $hook, 'cb' => $cb, 'prio' => $prio );
		return true;
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
}

/** Fire every registered wp_footer callback, return the concatenated echo output. */
function fire_footer(): string {
	ob_start();
	foreach ( $GLOBALS['__actions'] as $a ) {
		if ( 'wp_footer' === $a['hook'] && is_callable( $a['cb'] ) ) {
			call_user_func( $a['cb'] );
		}
	}
	return (string) ob_get_clean();
}

// ---------------------------------------------------------------------------
// PART A — INLINE COPY of bws_queue_inline_css (content-helpers.php).
// NOTE: uses a per-call-site static; each distinct id registers once. The static
// persists for the whole process, so the test sequences ids deliberately.
// ---------------------------------------------------------------------------
function t_queue_inline_css( string $id, string $css ): void {
	static $queued = array();

	if ( '' === $id || '' === $css || isset( $queued[ $id ] ) ) {
		return;
	}
	if ( ! function_exists( 'add_action' ) ) {
		return;
	}
	$queued[ $id ] = true;

	add_action(
		'wp_footer',
		static function () use ( $id, $css ): void {
			echo '<style id="' . esc_attr( $id ) . '">' . $css . "</style>\n";
		},
		5
	);
}

echo "PART A — bws_queue_inline_css dedupe\n";

// Empty guards register nothing.
t_queue_inline_css( '', 'x' );
t_queue_inline_css( 'no-css', '' );
assert_eq( 'A1 empty id/css register no action', 0, count( $GLOBALS['__actions'] ) );

// Same id, 3 calls → ONE registration (N tables, one <style>).
t_queue_inline_css( 'bws-table-inline-css', 'A{overflow:auto}' );
t_queue_inline_css( 'bws-table-inline-css', 'A{overflow:auto}' );
t_queue_inline_css( 'bws-table-inline-css', 'A{overflow:auto}' );
assert_eq( 'A2 same id thrice → 1 registration', 1, count( $GLOBALS['__actions'] ) );

// Different id → a second, independent registration.
t_queue_inline_css( 'bws-list-inline-css', 'B{display:grid}' );
assert_eq( 'A3 different id → 2 registrations', 2, count( $GLOBALS['__actions'] ) );

// Registered at wp_footer priority 5 (the {{content}} precedent slot).
assert_eq( 'A4 hook is wp_footer', 'wp_footer', $GLOBALS['__actions'][0]['hook'] );
assert_eq( 'A4 priority 5', 5, $GLOBALS['__actions'][0]['prio'] );

// Fire the footer — each id emits its OWN payload once (closure captured per id).
$footer = fire_footer();
assert_eq(
	'A5 footer emits both styles, each once, own payload',
	'<style id="bws-table-inline-css">A{overflow:auto}</style>' . "\n"
		. '<style id="bws-list-inline-css">B{display:grid}</style>' . "\n",
	$footer
);

// id is attribute-escaped in the emitted tag.
$GLOBALS['__actions'] = array();
t_queue_inline_css( 'bad"id', 'C{}' );
assert_eq( 'A6 id escaped in <style>', '<style id="bad&quot;id">C{}</style>' . "\n", fire_footer() );

// ---------------------------------------------------------------------------
// PART B — INLINE COPIES of ContentProcessor pure transforms.
// bws_content_debug + queue_inline_css side-effects stubbed away; the extractor
// returns its accumulated CSS instead of queueing (pure regex under test).
// ---------------------------------------------------------------------------

/** Mirror of ContentProcessor::extract_and_queue_inline_styles (regex only). */
function t_extract_inline_styles( string $content, string &$extracted_css ): string {
	if ( '' === $content || false === strpos( $content, '<style' ) ) {
		return $content;
	}
	$extracted_css = '';
	$content = preg_replace_callback(
		'/<style\b[^>]*>([\s\S]*?)<\/style>/i',
		static function ( $matches ) use ( &$extracted_css ) {
			$extracted_css .= $matches[1];
			return '';
		},
		$content
	);
	return $content;
}

/** Mirror of ContentProcessor::strip_block_comments. */
function t_strip_block_comments( string $content ): string {
	return preg_replace( '/<!--\s+\/?wp:\S+.*?-->/s', '', $content );
}

/** Mirror of ContentProcessor::extract_css_from_block_comments. */
function t_extract_css_from_block_comments( string $content ): string {
	$css = '';
	if ( ! preg_match_all( '/<!--\s+wp:\S+.*?-->/s', $content, $matches ) ) {
		return $css;
	}
	foreach ( $matches[0] as $comment ) {
		$json_start = strpos( $comment, '{' );
		if ( false === $json_start ) {
			continue;
		}
		$json_end = strrpos( $comment, '}' );
		if ( false === $json_end || $json_end < $json_start ) {
			continue;
		}
		$attrs = json_decode( substr( $comment, $json_start, $json_end - $json_start + 1 ) );
		if ( $attrs && isset( $attrs->css ) && is_string( $attrs->css ) ) {
			$css .= $attrs->css;
		}
	}
	return $css;
}

/** Mirror of ContentProcessor::strip_dynamic_tags. */
function t_strip_dynamic_tags( string $content ): string {
	return preg_replace( '/\{\{[^}]+\}\}/', '', $content );
}

echo "\nPART B — inline <style> extraction\n";

$css = '';
$out = t_extract_inline_styles( 'no styles here', $css );
assert_eq( 'B1 no <style> → content unchanged', 'no styles here', $out );

$css = '';
$out = t_extract_inline_styles( '<style>.a{color:red}</style><p>hi</p>', $css );
assert_eq( 'B2 single style removed from content', '<p>hi</p>', $out );
assert_eq( 'B2 css captured', '.a{color:red}', $css );

$css = '';
$out = t_extract_inline_styles(
	'<style id="one">.a{color:red}</style><div>x</div><style class="two">.b{color:blue}</style>',
	$css
);
assert_eq( 'B3 two styles removed', '<div>x</div>', $out );
assert_eq( 'B3 both css concatenated in order', '.a{color:red}.b{color:blue}', $css );

$css = '';
$out = t_extract_inline_styles(
	"<style>\n.multi {\n  gap: 1rem;\n}\n</style><table></table>",
	$css
);
assert_eq( 'B4 multiline style removed', '<table></table>', $out );
assert_eq( 'B4 multiline css captured', "\n.multi {\n  gap: 1rem;\n}\n", $css );

echo "\nPART B — block-comment strip\n";
assert_eq(
	'B5 wp block comments stripped, inner HTML kept',
	'<div>body</div>',
	t_strip_block_comments( '<!-- wp:generateblocks/element {"x":1} --><div>body</div><!-- /wp:generateblocks/element -->' )
);
assert_eq( 'B6 no comments → unchanged', '<p>plain</p>', t_strip_block_comments( '<p>plain</p>' ) );

echo "\nPART B — CSS from block-comment attrs\n";
assert_eq(
	'B7 css prop extracted from comment JSON',
	'.gb-x{color:green}',
	t_extract_css_from_block_comments( '<!-- wp:generateblocks/element {"css":".gb-x{color:green}"} -->' )
);
assert_eq(
	'B8 multiple comments concatenate their css',
	'.a{}.b{}',
	t_extract_css_from_block_comments(
		'<!-- wp:x {"css":".a{}"} --><!-- wp:y {"css":".b{}"} -->'
	)
);
assert_eq(
	'B9 comment without css prop → empty',
	'',
	t_extract_css_from_block_comments( '<!-- wp:x {"align":"wide"} -->' )
);
assert_eq(
	'B10 no block comments → empty',
	'',
	t_extract_css_from_block_comments( '<div>nothing</div>' )
);

echo "\nPART B — dynamic-tag strip\n";
assert_eq(
	'B11 unresolved tags removed',
	'before  after',
	t_strip_dynamic_tags( 'before {{text key:foo}} after' )
);
assert_eq( 'B12 no tags → unchanged', 'plain text', t_strip_dynamic_tags( 'plain text' ) );

// ---------------------------------------------------------------------------
echo "\n";
if ( $failures > 0 ) {
	echo "FAILED {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED {$count}/{$count}\n";
exit( 0 );
