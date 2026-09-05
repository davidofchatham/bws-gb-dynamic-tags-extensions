<?php
/**
 * Post content rendering pipeline + related helpers.
 *
 * Houses thin procedural wrappers over `\BWS\DynamicTags\Content\ContentProcessor`
 * (recursion guard, memory check, do_blocks dispatch, inline-style extraction),
 * rich-content sanitization, relationship-field option builders, GB query-loop
 * setup phase detection, and safe-output (strips destructive output options for HTML).
 *
 * Other helpers split across:
 *  - field-helpers.php        (meta/ACF reads, loop-item context, ACF object_id)
 *  - preview-helpers.php      (editor preview labels)
 *  - link-helpers.php         (linkTo/linkKey resolution + <a> wrapping)
 *  - registration-helpers.php (wire-format / GB-registration utilities)
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize rich content with proper HTML handling.
 *
 * @since 1.0.0
 * @param string $content Content to sanitize.
 * @return string Sanitized content.
 */
if ( ! function_exists( 'bws_sanitize_rich_content' ) ) {
function bws_sanitize_rich_content( $content ) {
	if ( empty( $content ) ) {
		return '';
	}

	add_filter( 'wp_kses_allowed_html', array( 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ), 10, 2 );
	$sanitized = wp_kses_post( $content );
	remove_filter( 'wp_kses_allowed_html', array( 'GenerateBlocks_Dynamic_Tags', 'expand_allowed_html' ), 10, 2 );

	return $sanitized;
}
}

// ===============================================
// INLINE CSS QUEUE (frontend, footer-printed)
// ===============================================

/**
 * Queue a fixed block of frontend CSS to print once per request.
 *
 * A shared footer-queue for tags that emit their own presentation CSS but ship no
 * stylesheet asset. Mirrors ContentProcessor::output_queued_inline_css (which
 * queues GB block CSS extracted from {{content}}); this variant takes STATIC,
 * plugin-authored CSS keyed by a stable id. Each id prints at most once — repeated
 * calls with the same id are no-ops — so any number of tag instances on the page
 * yield a single <style id="…"> at wp_footer:5.
 *
 * The CSS is echoed verbatim (NOT escaped): callers must pass only trusted,
 * plugin-authored CSS — never user input.
 *
 * @since 1.17.0
 * @param string $id  Stable style-element id (also the dedupe key).
 * @param string $css CSS text (no <style> tags). Trusted, plugin-authored only.
 * @return void
 */
if ( ! function_exists( 'bws_queue_inline_css' ) ) {
function bws_queue_inline_css( string $id, string $css ): void {
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
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted plugin-authored CSS; id is a caller-supplied literal.
			echo '<style id="' . esc_attr( $id ) . '">' . $css . "</style>\n";
		},
		5
	);
}
}

// ===============================================
// RELATIONSHIP FIELD OPTIONS
// ===============================================

// bws_get_relationship_field_options() (`rel`) and bws_get_second_relationship_field_options()
// (`rel_2`) — REMOVED in 1.17.0 (#56).
//
// They were the last definitions of the `rel`/`rel_2` option vocabulary anywhere in the
// plugin, and their only callers were the two related-post sources' get_source_options().
// Those sources are now inert, so the definitions would have registered controls for a
// traversal that cannot fire. The live spelling is `ref`, defined once in
// bws_base_traversal_options() (base-shared.php) and read by the chain compiler.
//
// Deleted rather than emptied: a builder returning array() is a Middle Man that reads like
// a supported extension point. Both were guarded by function_exists(), so an external
// plugin that defines its own is unaffected.

// ===============================================
// POST CONTENT: SAFETY LAYER (thin wrappers over ContentProcessor)
// ===============================================
//
// Procedural API preserved for back-compat. All state lives in
// \BWS\DynamicTags\Content\ContentProcessor. The `int $post_id` legacy
// signature is normalized to the `'post:' . $id` cache_key on entry.

/**
 * Check if a post can be processed (recursion + depth protection).
 *
 * @since 1.1.0
 * @param int $post_id Post ID to check.
 * @return bool True if safe to process.
 */
if ( ! function_exists( 'bws_can_process_post_content' ) ) {
function bws_can_process_post_content( $post_id ) {
	return \BWS\DynamicTags\Content\ContentProcessor::can_process( 'post:' . (int) $post_id );
}
}

/**
 * Push a post onto the content processing stack.
 *
 * @since 1.1.0
 * @param int $post_id Post ID.
 */
if ( ! function_exists( 'bws_start_processing_post' ) ) {
function bws_start_processing_post( $post_id ) {
	\BWS\DynamicTags\Content\ContentProcessor::start( 'post:' . (int) $post_id );
}
}

/**
 * Pop a post from the content processing stack.
 *
 * @since 1.1.0
 * @param int $post_id Post ID.
 */
if ( ! function_exists( 'bws_end_processing_post' ) ) {
function bws_end_processing_post( $post_id ) {
	\BWS\DynamicTags\Content\ContentProcessor::end( 'post:' . (int) $post_id );
}
}

/**
 * Check if sufficient memory is available for full content processing.
 *
 * Threshold filterable via `bws_content_memory_threshold` (default 0.80).
 *
 * @since 1.1.0
 * @return bool True if memory usage is below the threshold.
 */
if ( ! function_exists( 'bws_has_sufficient_memory' ) ) {
function bws_has_sufficient_memory() {
	return \BWS\DynamicTags\Content\ContentProcessor::has_sufficient_memory();
}
}

/**
 * Detect if we're in a GB query loop setup phase (not a real iteration).
 *
 * During setup, queryId is present in context but postId is missing or
 * still matches the outer page ID. Processing content at this stage would
 * show the wrong post's content or cause unnecessary overhead.
 *
 * @since 1.1.0
 * @param object|null $instance Block instance.
 * @return bool True if in setup phase (should skip processing).
 */
if ( ! function_exists( 'bws_is_query_loop_setup_phase' ) ) {
function bws_is_query_loop_setup_phase( $instance ) {
	if ( ! isset( $instance->context['queryId'] ) ) {
		return false; // Not in a query loop.
	}

	$context_post_id = $instance->context['postId'] ?? null;

	if ( null === $context_post_id ) {
		return true; // queryId set but no postId — setup phase.
	}

	return (int) $context_post_id === (int) get_the_ID();
}
}

/**
 * Log a debug message for post content processing.
 *
 * Gated solely by the admin "Enable benchmark logging" setting; WP_DEBUG alone
 * does not enable this output.
 *
 * @since 1.1.0
 * @param string $message Message to log.
 */
if ( ! function_exists( 'bws_content_debug' ) ) {
function bws_content_debug( $message ) {
	if ( ! class_exists( 'BWS\DynamicTags\Admin\SettingsPage' )
		|| ! \BWS\DynamicTags\Admin\SettingsPage::is_benchmark_logging_enabled()
	) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( '[BWS Content] ' . $message );
}
}

/**
 * Capture start metrics for debug timing.
 *
 * @since 1.1.0
 * @param int|string $cache_key Cache key (or legacy post_id) being processed.
 * @return array Start data array, or empty array if debug is inactive.
 */
if ( ! function_exists( 'bws_content_debug_start' ) ) {
function bws_content_debug_start( $cache_key ) {
	if ( ! class_exists( 'BWS\DynamicTags\Admin\SettingsPage' )
		|| ! \BWS\DynamicTags\Admin\SettingsPage::is_benchmark_logging_enabled()
	) {
		return array();
	}

	return array(
		'time'      => microtime( true ),
		'memory'    => memory_get_usage( true ),
		'cache_key' => $cache_key,
	);
}
}

/**
 * Log elapsed time and memory delta since bws_content_debug_start().
 *
 * @since 1.1.0
 * @param int|string $cache_key  Cache key (or legacy post_id) that was processed.
 * @param array      $start_data Data from bws_content_debug_start().
 */
if ( ! function_exists( 'bws_content_debug_end' ) ) {
function bws_content_debug_end( $cache_key, $start_data ) {
	if ( empty( $start_data ) ) {
		return;
	}

	$duration  = round( ( microtime( true ) - $start_data['time'] ) * 1000, 2 );
	$mem_delta = memory_get_usage( true ) - $start_data['memory'];
	$sign      = $mem_delta >= 0 ? '+' : '-';
	$depth     = \BWS\DynamicTags\Content\ContentProcessor::stack_depth();

	bws_content_debug( sprintf(
		'cache_key=%s time=%sms mem_delta=%s%s stack_depth=%d',
		$cache_key,
		$duration,
		$sign,
		size_format( abs( $mem_delta ) ),
		$depth
	) );
}
}

// ===============================================
// POST CONTENT: INLINE CSS QUEUE (thin wrappers over ContentProcessor)
// ===============================================

// bws_queue_inline_css( $css ) -- REMOVED (#133).
//
// The 1.2.0 wrapper over ContentProcessor::queue_inline_css() had been UNREACHABLE
// since 1.17.0: bws_queue_inline_css( string $id, string $css ) took the same name
// higher up this file, and the function_exists() guard here meant this body never
// defined. Nothing called it -- a 1-arg call would have been an ArgumentCountError
// against the 2-arg definition that wins -- so nothing changes by deleting it.
//
// Deleted rather than renamed: the sink it wrapped is not orphaned. The two live
// queues are separate BY DESIGN and the id-keyed helper's docblock says so --
// ContentProcessor::queue_inline_css() takes harvested per-post CSS and concatenates
// without dedupe, bws_queue_inline_css( $id, $css ) takes static plugin-authored CSS
// keyed for print-once. Both print at wp_footer:5, into different <style> elements.
// The content sink has never had an external caller and is reached in-class.
//
// bws_output_queued_inline_css() and bws_extract_and_queue_inline_styles() below are
// the other two 1.2.0 wrappers and are ALSO uncalled -- but they define fine and are
// a different case from this one, which was broken. Tracked as FW-126, not swept here.

/**
 * Output CSS queued by ContentProcessor::queue_inline_css() as a single <style> element.
 *
 * Hooked to wp_footer at priority 5 (registered on first queue call).
 *
 * @since 1.2.0
 */
if ( ! function_exists( 'bws_output_queued_inline_css' ) ) {
function bws_output_queued_inline_css() {
	\BWS\DynamicTags\Content\ContentProcessor::output_queued_inline_css();
}
}

/**
 * Extract inline <style> elements from content and queue them for wp_footer.
 *
 * @since 1.2.0
 * @param string $content HTML content that may contain inline <style> elements.
 * @return string Content with <style> elements removed.
 */
if ( ! function_exists( 'bws_extract_and_queue_inline_styles' ) ) {
function bws_extract_and_queue_inline_styles( $content ) {
	return \BWS\DynamicTags\Content\ContentProcessor::extract_and_queue_inline_styles( (string) $content );
}
}

// ===============================================
// POST CONTENT: PROCESSING PIPELINE (thin wrappers over ContentProcessor)
// ===============================================

/**
 * Render raw block content through the full pipeline.
 *
 * Generic entry — caller supplies the raw markup and a stack-identifying
 * cache_key. Use this when rendering content that isn't a post_content fetch
 * (e.g. wp_options-stored block markup under a future src:site).
 *
 * @since 1.8.0
 * @param string $raw       Raw post_content / block markup.
 * @param string $cache_key Stack-identifying key. Conventional: 'post:'.$id
 *                          for post_content, 'option:'.$key for wp_options.
 *                          Collisions defeat the recursion guard.
 * @param array  $args      Reserved for future use.
 * @return string Rendered HTML, or '' if blocked / empty.
 */
if ( ! function_exists( 'bws_render_block_content' ) ) {
function bws_render_block_content( $raw, $cache_key, $args = array() ) {
	return \BWS\DynamicTags\Content\ContentProcessor::render( (string) $raw, (string) $cache_key, $args );
}
}

/**
 * Fallback content pipeline for when memory is insufficient.
 *
 * @since 1.1.0
 * @param int   $post_id Post ID.
 * @param array $args    Reserved for future use.
 * @return string Processed content.
 */
if ( ! function_exists( 'bws_process_post_content_fallback' ) ) {
function bws_process_post_content_fallback( $post_id, $args = array() ) {
	$raw = get_post_field( 'post_content', (int) $post_id );
	return \BWS\DynamicTags\Content\ContentProcessor::render_fallback( (string) $raw, $args );
}
}

/**
 * Run a callable with the global post context switched to $post_id.
 *
 * Inner dynamic tags rendered by do_blocks() (and excerpt filters such as
 * excerpt_more) carry no block context and fall back to the global $post /
 * get_the_ID() — the AMBIENT entity, not the post being rendered. When a
 * {{content}} read hops (src:ref), that ambient fallback makes the hopped
 * post's inner tags resolve against the viewing page (#58). This is the
 * front-end analog of the editor-only id threading in CONTEXT.md I11.
 *
 * Swap-and-restore, the same mechanism a query loop applies per item. The swap
 * is skipped when $post_id already IS the ambient post: the callable would see
 * the identical context either way, so the work and the global writes are pure
 * cost.
 *
 * RESTORE IS BY VALUE, NEVER wp_reset_postdata(). That function does not mean
 * "put back what was there" — it assigns $post from $wp_query->post and sets
 * that up, so calling it where there was NO ambient post (a REST render, an
 * admin context) leaves the main query's post current instead of leaving the
 * globals empty. It would swap one leak for a quieter one. setup_postdata()
 * writes $pages/$page/$numpages/$multipage/$more as well as $post, so all of
 * them are captured and restored together; restoring $post alone would leave
 * the target's pagination state behind on a multi-page ambient post.
 *
 * @since 1.17.0
 * @param int      $post_id Post the callable should see as current.
 * @param callable $fn      Callable run inside the swapped context.
 * @return mixed The callable's return value.
 */
if ( ! function_exists( 'bws_with_post_context' ) ) {
function bws_with_post_context( $post_id, callable $fn ) {
	$post_id = (int) $post_id;

	if ( ! $post_id || $post_id === (int) get_the_ID() ) {
		return $fn();
	}

	$target = get_post( $post_id );
	if ( ! $target ) {
		return $fn();
	}

	global $post, $pages, $page, $numpages, $multipage, $more;

	$prev = array(
		'post'      => $post,
		'pages'     => $pages,
		'page'      => $page,
		'numpages'  => $numpages,
		'multipage' => $multipage,
		'more'      => $more,
	);

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored in the finally below.
	$post = $target;
	setup_postdata( $post );

	try {
		return $fn();
	} finally {
		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring the captured values.
		$post      = $prev['post'];
		$pages     = $prev['pages'];
		$page      = $prev['page'];
		$numpages  = $prev['numpages'];
		$multipage = $prev['multipage'];
		$more      = $prev['more'];
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited
	}
}
}

/**
 * Process post content through the primary rendering pipeline.
 *
 * Equivalent to:
 *   ContentProcessor::render(
 *       get_post_field( 'post_content', $post_id ),
 *       'post:' . $post_id,
 *       $args
 *   )
 * run inside bws_with_post_context(), so the post's own inner tags resolve
 * against IT rather than the ambient entity when the read hopped here (#58).
 *
 * @since 1.1.0
 * @param int   $post_id Post ID.
 * @param array $args    Reserved for future use.
 * @return string Processed HTML content.
 */
if ( ! function_exists( 'bws_process_post_content' ) ) {
function bws_process_post_content( $post_id, $args = array() ) {
	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return '';
	}

	// Ask the guard BEFORE swapping: a blocked render returns '' without running
	// do_blocks(), so there is nothing to give a context to and the swap would be
	// global writes for a call that produces nothing. render() re-checks — this
	// only decides whether to pay for the context.
	if ( ! \BWS\DynamicTags\Content\ContentProcessor::can_process( 'post:' . $post_id ) ) {
		return '';
	}

	$raw = get_post_field( 'post_content', $post_id );

	return bws_with_post_context(
		$post_id,
		static fn() => \BWS\DynamicTags\Content\ContentProcessor::render( (string) $raw, 'post:' . $post_id, $args )
	);
}
}

/**
 * Output helper that strips options unsafe for rich HTML content.
 *
 * GB's output pipeline applies text filters (truncation, case conversion,
 * wpautop, link-wrapping) designed for simple text values. These are
 * destructive on full rendered HTML. This helper removes them, then hands the
 * value to bws_gb_tag_output() — which still reaches GB's pipeline, so the
 * generateblocks_dynamic_tag_output hook stays available to third parties.
 *
 * TWO SEAMS THAT COMPOSE RATHER THAN STACK. This function owns CONTENT SAFETY:
 * which of GB's transforms would corrupt rich HTML. WHICH OPTIONS REACH GB AT
 * ALL is not decided here — that rule, and why it is an allowlist, live on
 * BWS_GB_TAG_OUTPUT_OPTIONS. The consequence for a reader of this function:
 * nothing below unsets `fallback`, because the boundary already drops it for
 * every tag, and a second unset here would be the same rule in two places.
 *
 * ORDER IS IMMATERIAL, which is worth stating because the two sets INTERSECT:
 * all four keys below are also keys GB consumes. Unsetting then intersecting
 * gives the same array as intersecting then unsetting — a set identity that holds
 * for ANY two sets, so the composition is order-free by construction rather than
 * by what either set happens to contain today.
 *
 * WHAT THE COMPOSITION CURRENTLY LEAVES is a smaller claim, and it is checked
 * rather than taken on trust: a content tag hands GB at most `replace`, `trim`
 * and `id`. That residue is derived from two sets, only one of which this
 * function owns, so a GB release adding a transform would falsify it in silence.
 * §B3 of tools/test/gb-output-boundary-test.php runs THIS function and asserts
 * the residue, which is where such a release breaks instead.
 *
 * @since 1.1.0
 * @since 1.19.0 Ends in bws_gb_tag_output() instead of calling GB directly.
 * @param string $content  Processed HTML content.
 * @param array  $options  Tag options.
 * @param object $instance Block instance.
 * @return string
 */
if ( ! function_exists( 'bws_safe_content_output' ) ) {
function bws_safe_content_output( $content, $options, $instance ) {
	$safe_options = $options;
	unset( $safe_options['trunc'] );   // substr() would break mid-tag.
	unset( $safe_options['case'] );    // strtolower() breaks HTML/CSS.
	unset( $safe_options['wpautop'] ); // Pipeline already ran wpautop.
	unset( $safe_options['link'] );    // Wrapping HTML in <a> is invalid HTML.
	return bws_gb_tag_output( $content, $safe_options, $instance );
}
}

