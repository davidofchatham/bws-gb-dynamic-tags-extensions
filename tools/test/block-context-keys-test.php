<?php
/**
 * Standalone unit harness for the BLOCK-CONTEXT KEY VOCABULARY — every key this
 * plugin reads off `$instance->context`, and the one function whose whole job is to
 * read one of them: bws_is_query_loop_setup_phase() (includes/helpers/content-helpers.php).
 *
 * The real file is loaded, not copied. content-helpers.php defines functions only
 * behind an ABSPATH check, so it loads inert once ABSPATH is defined; a test-local
 * copy of the guard would put the key string in a second place, which is precisely
 * the condition this file exists to detect.
 *
 * WHY IT EXISTS — #133. `bws_is_query_loop_setup_phase()` read `context['queryId']`.
 * GB provides that key as `generateblocks/queryId` and has for every version this
 * plugin can run against, so the guard returned false unconditionally and had never
 * fired since 1.1.0. Nothing failed when it broke, because a context key that is
 * absent is not an error in PHP — `isset()` on the wrong spelling is a legal
 * expression with a legal answer, and the answer is the same one a real "not in a
 * loop" reading gives. That is the shape of the defect: **a misspelled context key
 * degrades to a plausible negative, never to a crash.**
 *
 * A test that only drove this one function would pin one spelling. §B is a CENSUS
 * instead, because the failure decays by ADDITION: the next wrong key is in a
 * function nobody has written yet, reading a context GB will namespace the same way,
 * and no case here would cover it. The census re-reads the tree and requires every
 * literal key to be one this file has recorded a provenance for, so a new spelling
 * fails by name and its author has to say where the key comes from.
 *
 * SCOPE — what §A holds is the SHAPE of the guard, not GB's render order. That the
 * setup phase happens at all, how many times it happens, and which contexts carry
 * which keys are GB facts, owned by docs/gb-constraints.md §The query block renders
 * its inner blocks once before it iterates. The four context shapes driven below were
 * captured off GB 2.4.1 on the fixture testbed (2026-09-05, recorded on #133); this
 * file pins what the guard MUST ANSWER given them, which is ours.
 *
 * WHAT A PASS HERE DOES NOT PROVE. Both halves are static: §A drives fabricated
 * context arrays and §B reads source text. Neither observes GB. If a future GB
 * renames or stops providing `generateblocks/queryId`, every assertion here still
 * passes and the guard is dead again in exactly the original way. That case is the
 * dependency-version trigger's, not this file's — re-drive the probe on #133 when GB
 * moves.
 *
 * Run:  php tools/test/block-context-keys-test.php   (exit 0 = pass, 1 = fail)
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );

/**
 * Stand-in for the WP global-post reader. The guard's second arm compares the context
 * post id against whatever post is CURRENT, so the ambient id has to be settable from
 * the outside: it is what distinguishes "this context names the surrounding page" from
 * "this context names a row".
 */
$GLOBALS['bws_test_current_post_id'] = 0;

function get_the_ID() { // phpcs:ignore WordPress.NamingConventions -- mirrors WP's own name.
	return $GLOBALS['bws_test_current_post_id'];
}

require $root . '/includes/helpers/content-helpers.php';

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

/** Minimal stand-in for a WP_Block: the guard reads nothing but ->context. */
function ctx( array $context ) {
	$i          = new stdClass();
	$i->context = $context;
	return $i;
}

// ---------------------------------------------------------------------------
echo "§A — bws_is_query_loop_setup_phase() against the four measured contexts\n";
// ---------------------------------------------------------------------------
//
// The arrays below are the key sets a `generateblocks/query` > `looper` > `loop-item`
// tree actually produced on GB 2.4.1, two rows, rendered through do_blocks() with a
// render_block filter recording each inner block's context. Trimmed to the keys the
// guard can see; nothing else about them is invented.

$ambient = 22; // the surrounding page.
$row     = 1777; // the first row's post.

// [0] The QUERY block's own discarded pre-render. WP core renders inner blocks before
// the query block's render_callback runs, and the query block has already provided its
// context to them — so queryId is set while postId is still the surrounding page's.
$GLOBALS['bws_test_current_post_id'] = $ambient;
assert_same(
	'[0] query-level setup render (postId === the ambient page) is the setup phase',
	true,
	bws_is_query_loop_setup_phase( ctx( array(
		'generateblocks/query'        => array(),
		'generateblocks/queryId'      => '9621dfad',
		'generateblocks/queryType'    => 'WP_Query',
		'generateblocks/inheritQuery' => false,
		'postId'                      => $ambient,
		'postType'                    => 'page',
	) ) )
);

// [1] The LOOPER's discarded pre-render, one level down. Same phase, different arm:
// postId has dropped out of the context entirely by this point.
assert_same(
	'[1] looper-level setup render (postId absent) is the setup phase',
	true,
	bws_is_query_loop_setup_phase( ctx( array(
		'generateblocks/query'     => array(),
		'generateblocks/queryData' => array(),
		'generateblocks/queryId'   => '9621dfad',
		'generateblocks/queryType' => 'WP_Query',
	) ) )
);

// [2] A real row. The looper builds each iteration's inner block with a FRESH context
// that carries no queryId at all — which is the reason arm 2 below is safe despite
// postId === get_the_ID() being TRUE on every genuine iteration.
$GLOBALS['bws_test_current_post_id'] = $row;
$iteration                           = array(
	'generateblocks/loopIndex' => 1,
	'generateblocks/loopItem'  => array( 'ID' => $row ),
	'generateblocks/queryType' => 'WP_Query',
	'postId'                   => $row,
	'postType'                 => 'post',
);
assert_same( '[2] a real iteration is NOT the setup phase', false, bws_is_query_loop_setup_phase( ctx( $iteration ) ) );

// The same row, stated as the property rather than the example: every iteration
// satisfies the arm-2 comparison, and is saved from it only by the missing queryId.
// If a future GB starts forwarding queryId into iteration context, THIS is the
// assertion that stops being true, and it says so in one line.
assert_same(
	'[2] the iteration context does satisfy arm 2\'s comparison (only the absent queryId saves it)',
	true,
	(int) $iteration['postId'] === (int) get_the_ID()
);

// [3] Not in a query loop at all — a tag on an ordinary page.
$GLOBALS['bws_test_current_post_id'] = $ambient;
assert_same(
	'[3] no query context at all is NOT the setup phase',
	false,
	bws_is_query_loop_setup_phase( ctx( array( 'postId' => $ambient, 'postType' => 'page' ) ) )
);

// A nested loop's INNER query block, pre-rendering inside an outer row: queryId is set,
// postId is the outer row, and get_the_ID() is that same row. Measured as renders [10]
// and [14] of the depth-2 probe. It matters because `loopItem` is PRESENT here — so
// "no loop item" is not a usable discriminator for this phase, and only queryId is.
$GLOBALS['bws_test_current_post_id'] = $row;
assert_same(
	'[10] a nested inner query\'s setup render is the setup phase even with loopItem present',
	true,
	bws_is_query_loop_setup_phase( ctx( array(
		'generateblocks/queryId'   => 'b1c2d3e4',
		'generateblocks/queryType' => 'WP_Query',
		'generateblocks/loopItem'  => array( 'ID' => $row ),
		'postId'                   => $row,
		'postType'                 => 'post',
	) ) )
);

// The bare spelling must not be honoured. A context carrying ONLY `queryId` is not a
// state GB can produce, so treating it as a query loop would mean the function is
// reading a key nothing sets — the #133 defect, in the direction that would survive a
// naive repair that added the namespaced key beside the old one.
$GLOBALS['bws_test_current_post_id'] = $ambient;
assert_same(
	'a bare `queryId` is not a query loop (GB namespaces it; nothing sets the bare form)',
	false,
	bws_is_query_loop_setup_phase( ctx( array( 'queryId' => '9621dfad', 'postId' => $ambient ) ) )
);

echo "\n";

// ---------------------------------------------------------------------------
echo "§B — CENSUS: every literal \$instance->context['…'] key in shipped PHP\n";
// ---------------------------------------------------------------------------
//
// THE RECORDED VOCABULARY. Each key with where it comes from, because provenance is
// what decides the spelling: a key GB provides is namespaced by GB's own block.json /
// register_block_type declaration and we do not get to choose; a key WordPress core
// provides is bare by the same reasoning; a key we inject is ours to spell.
//
// Adding a row is the point of this list, not a workaround for it — a new key fails
// here until someone writes down which of the three it is.

$vocabulary = array(
	// --- Provided by GenerateBlocks. Namespaced at the source; never bare. ---
	'generateblocks/queryId'   => 'GB — generateblocks/query providesContext, from the block\'s uniqueId.',
	'generateblocks/queryType' => 'GB — generateblocks/query providesContext (WP_Query, post_meta, …).',
	'generateblocks/loopItem'  => 'GB — set per iteration by the looper; the plugin\'s loop discriminator.',

	// --- Provided by WordPress core. Bare by core convention; GB passes it through
	//     under the same bare name (class-looper.php, class-query-loop.php). ---
	'postId'                   => 'WP core — the post a block renders against.',

	// --- Ours. Injected by this plugin, spelled by this plugin. ---
	'bwsEditorPreview'         => 'OURS — set by assets/js/editor-preview-context.js; marks an editor-time render.',
	'bws/loopItemEntity'       => 'OURS — memo written by bws_get_loop_item_context() so repeat callers do not re-classify.',
	'bws/loopItemPostId'       => 'OURS — memo written beside the above, holding the post arm only.',
);

// GB keys that MUST NOT appear bare. These are the names GB declares under its own
// namespace, so a bare spelling of any of them is the #133 defect exactly: a read that
// compiles, runs, and answers "no" forever. Listed separately from the vocabulary
// because the vocabulary check catches an unrecorded key, while this catches a key
// that would look plausible enough for someone to record.
$must_be_namespaced = array(
	'query',
	'queryId',
	'queryType',
	'queryData',
	'inheritQuery',
	'paginationType',
	'loopItem',
	'loopIndex',
);

// SCOPE IS DEFAULT-IN. Every .php file in the repo is scanned unless its directory is
// excluded, so a new shipped directory is covered without anyone remembering this file.
$skip_dirs = array(
	'.git',
	'.scratch',
	'.claude',
	'deprecated-files',
	'docs',          // prose; records what WAS true, by design.
	'libs',          // vendored third party (PUC); not ours to spell.
	'node_modules',
	'tools',         // not shipped, and a harness legitimately FABRICATES contexts —
	                 // this very file builds several.
);

$scan = static function ( string $dir ) use ( $skip_dirs ): array {
	$hits  = array();
	$files = 0;
	$it    = new RecursiveIteratorIterator(
		new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			static function ( $current ) use ( $skip_dirs ) {
				return ! ( $current->isDir() && in_array( $current->getFilename(), $skip_dirs, true ) );
			}
		)
	);
	foreach ( $it as $file ) {
		if ( ! $file->isFile() || 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}
		$files++;
		$rel   = strtr( substr( $file->getPathname(), strlen( $dir ) + 1 ), DIRECTORY_SEPARATOR, '/' );
		$lines = preg_split( '~\R~', (string) file_get_contents( $file->getPathname() ) );
		foreach ( $lines as $n => $line ) {
			if ( preg_match_all( '~->context\[\s*\'([^\']*)\'~', $line, $m ) ) {
				foreach ( $m[1] as $key ) {
					$hits[] = array( $rel, $n + 1, $key, trim( $line ) );
				}
			}
			// A non-literal subscript cannot be censused, so it is reported rather than
			// silently skipped: the vocabulary would still read as complete while a
			// variable key walked straight past it.
			if ( preg_match( '~->context\[\s*[^\'\]]~', $line ) ) {
				$hits[] = array( $rel, $n + 1, '(non-literal subscript)', trim( $line ) );
			}
		}
	}
	return array( $hits, $files );
};

list( $all_hits, $files_scanned ) = $scan( $root );

// NON-VACUITY FIRST. A scanner that read nothing, or a pattern that stopped matching,
// reports a clean tree — the one failure mode a "found nothing" assertion cannot tell
// apart from success. Both are pinned against the guard's own read, which is
// guaranteed to exist as long as the function this file's §A drives does.
assert_same( 'the scan reached the plugin source', true, $files_scanned > 20 );
assert_same(
	'the pattern still matches real code (content-helpers.php\'s own context read is found)',
	true,
	(bool) array_filter( $all_hits, static fn( $h ) => 'includes/helpers/content-helpers.php' === $h[0] )
);

$unrecorded = array_values( array_filter(
	$all_hits,
	static fn( $h ) => ! array_key_exists( $h[2], $vocabulary )
) );

assert_same( 'every context key read in shipped PHP is in the recorded vocabulary', 0, count( $unrecorded ) );

foreach ( $unrecorded as $h ) {
	echo "       {$h[0]}:{$h[1]}  key `{$h[2]}`\n";
	echo "         {$h[3]}\n";
}
if ( $unrecorded ) {
	echo "       If GB or WP provides this key, spell it the way THEY declare it (GB\n";
	echo "       namespaces its own as `generateblocks/…`). Then add it to \$vocabulary\n";
	echo "       above WITH its provenance. See #133 for what a misspelling costs.\n";
	echo "       ({$files_scanned} php files scanned.)\n";
}

$bare_gb = array_values( array_filter(
	$all_hits,
	static fn( $h ) => in_array( $h[2], $must_be_namespaced, true )
) );

assert_same( 'no GB-provided key is read under a bare spelling', 0, count( $bare_gb ) );

foreach ( $bare_gb as $h ) {
	echo "       {$h[0]}:{$h[1]}  bare `{$h[2]}` — GB declares this as `generateblocks/{$h[2]}`\n";
	echo "         {$h[3]}\n";
}
if ( $bare_gb ) {
	echo "       A bare GB key is not an error at runtime: isset() answers `false` and the\n";
	echo "       read degrades to a plausible negative that never fires. That is #133.\n";
}

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures}/{$count}\n";
	exit( 1 );
}
echo "PASSED {$count}/{$count}\n";
exit( 0 );
