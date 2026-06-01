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
 *  - field-helpers.php        (meta/ACF reads, loop-row context, ACF object_id)
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
// RELATIONSHIP FIELD OPTIONS
// ===============================================

/**
 * Get ACF relationship field option for related post tags.
 *
 * Returns a single-option array with key 'rel' identifying the ACF
 * relationship or post_object field that links to the related post.
 * Use with array_merge() alongside other tag-specific options.
 *
 * @since 1.1.0
 * @return array
 */
if ( ! function_exists( 'bws_get_relationship_field_options' ) ) {
function bws_get_relationship_field_options() {
	return array(
		'rel' => array(
			'type'        => 'text',
			'label'       => __( 'Relationship Field Key', 'generateblocks' ),
			'help'        => __( 'Relationship or post object field key that links to the related post.', 'generateblocks' ),
			'placeholder' => 'related_posts',
		),
	);
}
}

/**
 * Get ACF second-hop relationship field option for SecondRelatedPost tags.
 *
 * Returns a single-option array with key 'rel_2' identifying the ACF
 * relationship or post_object field on the first related post that links
 * to the second-degree related post.
 * Use with array_merge() alongside bws_get_relationship_field_options().
 *
 * @since 1.2.0
 * @return array
 */
if ( ! function_exists( 'bws_get_second_relationship_field_options' ) ) {
function bws_get_second_relationship_field_options() {
	return array(
		'rel_2' => array(
			'type'        => 'text',
			'label'       => __( 'Second Relationship Field Key', 'generateblocks' ),
			'help'        => __( 'ACF relationship or post object field key on the first related post that links to the second-degree related post.', 'generateblocks' ),
			'placeholder' => 'related_posts',
		),
	);
}
}

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

/**
 * Queue CSS for output via wp_footer.
 *
 * @since 1.2.0
 * @param string $css CSS rules to queue.
 */
if ( ! function_exists( 'bws_queue_inline_css' ) ) {
function bws_queue_inline_css( $css ) {
	\BWS\DynamicTags\Content\ContentProcessor::queue_inline_css( (string) $css );
}
}

/**
 * Output CSS queued by bws_queue_inline_css() as a single <style> element.
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
 * Process post content through the primary rendering pipeline.
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

	$raw = get_post_field( 'post_content', $post_id );

	return \BWS\DynamicTags\Content\ContentProcessor::render( (string) $raw, 'post:' . $post_id, $args );
}
}

/**
 * Output helper that strips options unsafe for rich HTML content.
 *
 * GB's output() utility applies text filters (truncation, case conversion,
 * wpautop, link-wrapping) designed for simple text values. These are
 * destructive on full rendered HTML. This helper removes them before
 * passing to output() while preserving the generateblocks_dynamic_tag_output
 * filter hook for third-party compatibility.
 *
 * @since 1.1.0
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
	return GenerateBlocks_Dynamic_Tag_Callbacks::output( $content, $safe_options, $instance );
}
}

