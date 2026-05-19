<?php
/**
 * Content extraction helper functions.
 *
 * Shared functions for related post data retrieval, text/URL field extraction,
 * link URL resolution, meta key validation, and content sanitization.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get related posts from ACF relationship or post object field.
 *
 * @since 1.0.0
 * @param int|string $entity_id ACF-compatible entity ID: post ID (int) or term entity string ("term_N").
 * @param string $field_key ACF field key.
 * @return array Array of related posts.
 */
if ( ! function_exists( 'bws_get_related_posts_data' ) ) {
function bws_get_related_posts_data( $post_id, $field_key ) {
	if ( ! function_exists( 'get_field' ) || ! function_exists( 'get_field_object' ) ) {
		return array();
	}

	// Validate field type for security.
	$field_object = get_field_object( $field_key, $post_id );

	if ( ! $field_object || ! in_array( $field_object['type'], array( 'relationship', 'post_object' ), true ) ) {
		return array();
	}

	$related_posts = get_field( $field_key, $post_id );

	if ( ! $related_posts ) {
		return array();
	}

	if ( ! is_array( $related_posts ) ) {
		$related_posts = array( $related_posts );
	}

	return $related_posts;
}
}

/**
 * Extract post ID from various ACF return formats.
 *
 * Handles ACF single-post return formats (WP_Post object, numeric ID, assoc
 * array with 'ID' key) and list return formats (array of any of the above,
 * such as Relationship/post_object subfield with no max_size limit). For
 * lists, returns the first entry's ID — caller is responsible for iteration
 * if multiple are needed.
 *
 * @since 1.0.0
 * @param mixed $post_data Post data from ACF.
 * @return int|false Post ID or false.
 */
if ( ! function_exists( 'bws_extract_post_id' ) ) {
function bws_extract_post_id( $post_data ) {
	if ( $post_data instanceof WP_Post ) {
		return $post_data->ID;
	}

	if ( is_object( $post_data ) && isset( $post_data->ID ) ) {
		return $post_data->ID;
	}

	if ( is_numeric( $post_data ) ) {
		return intval( $post_data );
	}

	if ( is_array( $post_data ) ) {
		if ( isset( $post_data['ID'] ) ) {
			return $post_data['ID'];
		}
		// List-of-posts (Relationship/post_object subfield): take first entry.
		if ( ! empty( $post_data ) ) {
			return bws_extract_post_id( reset( $post_data ) );
		}
	}

	return false;
}
}

/**
 * Resolve loop-row context from a block instance.
 *
 * Inspects $instance->context for GB Pro post_meta loop data and classifies the
 * row into one of three states. Result cached on $instance->context['bws/loopItemPostId']
 * so callers paying for `get_post()` only do so once per block render.
 *
 * Returned shape:
 *   [
 *     'loop_item'   => mixed   // raw row (WP_Post|array|int|null when not in a loop)
 *     'row_post_id' => int|false // resolved post ID for Mode 2a; false for Mode 2b/none
 *     'in_loop'     => bool    // true when GB Pro loop row context detected
 *   ]
 *
 * @since 1.7.0
 * @param mixed $instance Block instance (WP_Block) or anything else.
 * @return array
 */
if ( ! function_exists( 'bws_get_loop_row_context' ) ) {
function bws_get_loop_row_context( $instance ): array {
	$out = array(
		'loop_item'   => null,
		'row_post_id' => false,
		'in_loop'     => false,
	);

	if ( ! is_object( $instance ) || ! isset( $instance->context ) || ! is_array( $instance->context ) ) {
		return $out;
	}

	$raw_item = $instance->context['generateblocks/loopItem'] ?? null;
	$has_item = is_array( $raw_item )
		|| $raw_item instanceof WP_Post
		|| is_numeric( $raw_item );
	if ( ! $has_item ) {
		return $out;
	}

	$out['in_loop']   = true;
	$out['loop_item'] = $raw_item;

	if ( ! isset( $instance->context['bws/loopItemPostId'] ) ) {
		// Non-array rows (WP_Post / numeric) carry post identity directly under any
		// queryType — covers standard 'WP_Query' post loops and post-meta relationship
		// loops that GB Pro materializes into WP_Post instances. Array rows resolve only
		// under 'post_meta' AND with an explicit 'ID' key, so flat repeater rows
		// (Mode 2b) don't accidentally extract a post id via list-of-posts fallback.
		$query_type = $instance->context['generateblocks/queryType'] ?? '';
		$candidate  = 0;
		if ( ! is_array( $raw_item ) ) {
			$candidate = bws_extract_post_id( $raw_item );
		} elseif ( 'post_meta' === $query_type && isset( $raw_item['ID'] ) ) {
			$candidate = (int) $raw_item['ID'];
		}
		$row_post_id = ( $candidate && get_post( $candidate ) ) ? $candidate : false;
		$instance->context['bws/loopItemPostId'] = $row_post_id !== false ? $row_post_id : 0;
	}

	$cached              = (int) $instance->context['bws/loopItemPostId'];
	$out['row_post_id']  = $cached > 0 ? $cached : false;

	return $out;
}
}

/**
 * Read a meta/ACF field for a post-like context.
 *
 * Routes through GenerateBlocks_Meta_Handler so GB Pro's ACF integration fires
 * via the generateblocks_get_meta_pre_value filter. Falls back to raw WP meta
 * functions if Meta_Handler unavailable.
 *
 * Branching order:
 *  1. Mode 2a (loop row resolves to post)  → read post meta on row post
 *  2. Mode 2b (flat repeater row)          → read $loop_item[$key] directly
 *  3. $post_id > 0                         → read post meta
 *  4. Term archive (non-REST)              → read term meta on queried term
 *  5. null
 *
 * @since 1.7.0
 * @param string         $key         Meta/ACF field key.
 * @param mixed          $instance    Block instance (WP_Block) — used for context cache.
 * @param int|false      $post_id     Resolved post ID, or false.
 * @param bool           $single_only When true (default) coerce arrays/objects to ''. Pass false to preserve raw ACF arrays (e.g. image fields).
 * @return mixed Field value, '' on miss from Meta_Handler, or null when no context resolved.
 */
if ( ! function_exists( 'bws_read_field' ) ) {
function bws_read_field( string $key, $instance, $post_id, bool $single_only = true ) {
	// Security guard — block credential/internal-auth fields explicitly.
	// Underscore-prefixed protected meta is allowed on frontend (matches GB Meta_Handler),
	// since plugins like Pie Calendar legitimately store data in _-prefixed keys.
	if ( class_exists( 'GenerateBlocks_Dynamic_Tag_Security' )
		&& in_array( $key, GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS, true )
	) {
		return null;
	}

	// Mode 2 subtype detection.
	$loop = bws_get_loop_row_context( $instance );
	if ( $loop['in_loop'] ) {
		// Mode 2a — row resolves to a post entity.
		if ( $loop['row_post_id'] ) {
			return bws_meta_handler_read( (int) $loop['row_post_id'], $key, $single_only, 'get_post_meta' );
		}
		// Mode 2b — flat repeater row; read directly from row data.
		// Only applies when no explicit post_id was resolved (e.g. GB-injected id: in REST).
		// If a valid post_id was passed in, fall through to normal post context read.
		if ( is_array( $loop['loop_item'] ) && ! ( is_int( $post_id ) && $post_id > 0 ) && ! ( is_numeric( $post_id ) && (int) $post_id > 0 ) ) {
			return $loop['loop_item'][ $key ] ?? null;
		}
	}

	// Normal post context.
	if ( is_int( $post_id ) && $post_id > 0 ) {
		return bws_meta_handler_read( $post_id, $key, $single_only, 'get_post_meta' );
	}
	if ( is_numeric( $post_id ) && (int) $post_id > 0 ) {
		return bws_meta_handler_read( (int) $post_id, $key, $single_only, 'get_post_meta' );
	}

	// Term archive fallback — non-REST only.
	if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Term ) {
			return bws_meta_handler_read( (int) $queried->term_id, $key, $single_only, 'get_term_meta' );
		}
	}

	return null;
}
}

/**
 * Read a meta/ACF field for a term context.
 *
 * Routes through GenerateBlocks_Meta_Handler. GB Pro builds the "term_{$id}"
 * ACF key internally — no $taxonomy param needed.
 *
 * @since 1.7.0
 * @param string $key         Meta/ACF field key.
 * @param int    $term_id     Term ID.
 * @param bool   $single_only When true (default) coerce arrays/objects to ''. Pass false to preserve raw ACF arrays.
 * @return mixed Field value, '' on miss, or null if blocked by security guard.
 */
if ( ! function_exists( 'bws_read_term_field' ) ) {
function bws_read_term_field( string $key, int $term_id, bool $single_only = true ) {
	if ( class_exists( 'GenerateBlocks_Dynamic_Tag_Security' )
		&& in_array( $key, GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS, true )
	) {
		return null;
	}
	return bws_meta_handler_read( $term_id, $key, $single_only, 'get_term_meta' );
}
}

/**
 * Internal: route a meta read through GenerateBlocks_Meta_Handler with raw WP fallback.
 *
 * @since 1.7.0
 * @param int    $object_id   Post or term ID.
 * @param string $key         Meta key.
 * @param bool   $single_only When false, return raw (preserves ACF arrays).
 * @param string $wp_fn       Fallback WP function: get_post_meta or get_term_meta.
 * @return mixed
 */
if ( ! function_exists( 'bws_meta_handler_read' ) ) {
function bws_meta_handler_read( int $object_id, string $key, bool $single_only, string $wp_fn ) {
	if ( class_exists( 'GenerateBlocks_Meta_Handler' ) ) {
		$value = GenerateBlocks_Meta_Handler::get_meta( $object_id, $key, $single_only, $wp_fn );
	} else {
		$value = $wp_fn( $object_id, $key, true );
	}
	if ( $single_only && ( is_array( $value ) || is_object( $value ) ) ) {
		return '';
	}
	return $value;
}
}

/**
 * Validate meta key format.
 *
 * @since 1.0.0
 * @param string $meta_key Meta key to validate.
 * @return bool True if valid.
 */
if ( ! function_exists( 'bws_is_valid_meta_key' ) ) {
function bws_is_valid_meta_key( $meta_key ) {
	return (bool) preg_match( '/^[a-zA-Z0-9_-]+$/', $meta_key );
}
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
// POST CONTENT: SAFETY LAYER
// ===============================================

/**
 * Check if a post can be processed (recursion + depth protection).
 *
 * Returns false if the post is already being processed (recursion guard)
 * or if the processing stack is at maximum depth (3 levels).
 *
 * @since 1.1.0
 * @param int $post_id Post ID to check.
 * @return bool True if safe to process.
 */
if ( ! function_exists( 'bws_can_process_post_content' ) ) {
function bws_can_process_post_content( $post_id ) {
	$stack = $GLOBALS['bws_content_processing_stack'] ?? array();
	return ! in_array( $post_id, $stack, true ) && count( $stack ) < 3;
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
	if ( ! isset( $GLOBALS['bws_content_processing_stack'] ) ) {
		$GLOBALS['bws_content_processing_stack'] = array();
	}
	$GLOBALS['bws_content_processing_stack'][] = $post_id;
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
	if ( empty( $GLOBALS['bws_content_processing_stack'] ) ) {
		return;
	}
	$key = array_search( $post_id, $GLOBALS['bws_content_processing_stack'], true );
	if ( false !== $key ) {
		array_splice( $GLOBALS['bws_content_processing_stack'], $key, 1 );
	}
}
}

/**
 * Check if sufficient memory is available for full content processing.
 *
 * @since 1.1.0
 * @return bool True if memory usage is below 80% of the PHP limit.
 */
if ( ! function_exists( 'bws_has_sufficient_memory' ) ) {
function bws_has_sufficient_memory() {
	$limit_str = ini_get( 'memory_limit' );

	if ( '-1' === $limit_str ) {
		return true; // No limit set.
	}

	$limit = wp_convert_hr_to_bytes( $limit_str );

	if ( $limit <= 0 ) {
		return true; // Can't determine limit, assume sufficient.
	}

	return ( memory_get_usage( true ) / $limit ) < 0.80;
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
 * @param int $post_id Post ID being processed.
 * @return array Start data array, or empty array if debug is inactive.
 */
if ( ! function_exists( 'bws_content_debug_start' ) ) {
function bws_content_debug_start( $post_id ) {
	if ( ! class_exists( 'BWS\DynamicTags\Admin\SettingsPage' )
		|| ! \BWS\DynamicTags\Admin\SettingsPage::is_benchmark_logging_enabled()
	) {
		return array();
	}

	return array(
		'time'    => microtime( true ),
		'memory'  => memory_get_usage( true ),
		'post_id' => $post_id,
	);
}
}

/**
 * Log elapsed time and memory delta since bws_content_debug_start().
 *
 * @since 1.1.0
 * @param int   $post_id    Post ID that was processed.
 * @param array $start_data Data from bws_content_debug_start().
 */
if ( ! function_exists( 'bws_content_debug_end' ) ) {
function bws_content_debug_end( $post_id, $start_data ) {
	if ( empty( $start_data ) ) {
		return;
	}

	$duration  = round( ( microtime( true ) - $start_data['time'] ) * 1000, 2 );
	$mem_delta = memory_get_usage( true ) - $start_data['memory'];
	$sign      = $mem_delta >= 0 ? '+' : '-';
	$depth     = count( $GLOBALS['bws_content_processing_stack'] ?? array() );

	bws_content_debug( sprintf(
		'post_id=%d time=%sms mem_delta=%s%s stack_depth=%d',
		$post_id,
		$duration,
		$sign,
		size_format( abs( $mem_delta ) ),
		$depth
	) );
}
}

// ===============================================
// POST CONTENT: INLINE CSS QUEUE
// ===============================================

/**
 * Queue CSS for output via wp_footer.
 *
 * CSS collected from multiple calls is consolidated into a single <style>
 * element output at wp_footer priority 5.
 *
 * @since 1.2.0
 * @param string $css CSS rules to queue.
 */
if ( ! function_exists( 'bws_queue_inline_css' ) ) {
function bws_queue_inline_css( $css ) {
	if ( empty( $css ) ) {
		return;
	}

	if ( ! isset( $GLOBALS['bws_queued_inline_css'] ) ) {
		$GLOBALS['bws_queued_inline_css'] = '';
		add_action( 'wp_footer', 'bws_output_queued_inline_css', 5 );
	}

	$GLOBALS['bws_queued_inline_css'] .= $css;
}
}

/**
 * Output CSS queued by bws_queue_inline_css() as a single <style> element.
 *
 * Hooked to wp_footer at priority 5. Outputs CSS from do_blocks() rendering
 * of non-current posts that GB inlined (because wp_head had already fired).
 *
 * @since 1.2.0
 */
if ( ! function_exists( 'bws_output_queued_inline_css' ) ) {
function bws_output_queued_inline_css() {
	if ( empty( $GLOBALS['bws_queued_inline_css'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS from trusted GB block rendering via do_blocks().
	echo '<style id="bws-dynamic-content-inline-css">' . $GLOBALS['bws_queued_inline_css'] . "</style>\n";
	$GLOBALS['bws_queued_inline_css'] = '';
}
}

/**
 * Extract inline <style> elements from content and queue them for wp_footer.
 *
 * When do_blocks() processes a post other than the current page post (e.g., a
 * related post), GB inlines block CSS as <style> elements before each block's
 * HTML because wp_head has already fired. wp_kses_post() then strips the
 * <style> tags but leaves the raw CSS text visible as page content.
 *
 * This function removes those <style> elements from the content string and
 * queues their CSS via bws_queue_inline_css() for consolidated wp_footer output.
 *
 * @since 1.2.0
 * @param string $content HTML content that may contain inline <style> elements.
 * @return string Content with <style> elements removed.
 */
if ( ! function_exists( 'bws_extract_and_queue_inline_styles' ) ) {
function bws_extract_and_queue_inline_styles( $content ) {
	if ( empty( $content ) || false === strpos( $content, '<style' ) ) {
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

	if ( $extracted_css ) {
		bws_content_debug( 'Extracted ' . strlen( $extracted_css ) . ' bytes of GB inline CSS; queuing for wp_footer.' );
		bws_queue_inline_css( $extracted_css );
	}

	return $content;
}
}

// ===============================================
// POST CONTENT: PROCESSING PIPELINE
// ===============================================

/**
 * Strip WordPress block comments from post content.
 *
 * Removes opening, closing, and self-closing block comment delimiters.
 * Leaves the inner block HTML intact.
 *
 * @since 1.1.0
 * @param string $content Raw post content.
 * @return string Content with block comment delimiters removed.
 */
if ( ! function_exists( 'bws_strip_block_comments' ) ) {
function bws_strip_block_comments( $content ) {
	return preg_replace( '/<!--\s+\/?wp:\S+.*?-->/s', '', $content );
}
}

/**
 * Extract GenerateBlocks CSS from block comment JSON attributes.
 *
 * Parses each block comment, extracts the JSON attributes object,
 * and collects any "css" property values. Used in the fallback pipeline
 * to preserve styling when do_blocks() cannot run.
 *
 * @since 1.1.0
 * @param string $content Raw post content.
 * @return string Concatenated CSS from all block comments.
 */
if ( ! function_exists( 'bws_extract_css_from_block_comments' ) ) {
function bws_extract_css_from_block_comments( $content ) {
	$css = '';

	// Match all block comments (opening and self-closing, not closing tags).
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

	bws_content_debug( 'Extracted ' . strlen( $css ) . ' bytes of CSS from block comments.' );

	return $css;
}
}

/**
 * Strip unresolved dynamic tag placeholders from content.
 *
 * Used only in the fallback pipeline where do_blocks() did not run
 * and {{tag_name ...}} patterns were not resolved.
 *
 * @since 1.1.0
 * @param string $content Content potentially containing {{tag ...}} patterns.
 * @return string Content with dynamic tag placeholders removed.
 */
if ( ! function_exists( 'bws_strip_dynamic_tags' ) ) {
function bws_strip_dynamic_tags( $content ) {
	return preg_replace( '/\{\{[^}]+\}\}/', '', $content );
}
}

/**
 * Fallback content pipeline for when memory is insufficient.
 *
 * Extracts CSS from block comment JSON, strips block comments and
 * unresolved dynamic tags, then prepends any extracted CSS as an
 * inline <style> element. Dynamic tags are not resolved in this path.
 *
 * @since 1.1.0
 * @param int   $post_id Post ID.
 * @param array $args    Reserved for future use.
 * @return string Processed content.
 */
if ( ! function_exists( 'bws_process_post_content_fallback' ) ) {
function bws_process_post_content_fallback( $post_id, $args = array() ) {
	$raw = get_post_field( 'post_content', $post_id );

	if ( empty( $raw ) ) {
		return '';
	}

	bws_content_debug( 'Using fallback pipeline for post_id=' . $post_id );

	$css     = bws_extract_css_from_block_comments( $raw );
	$content = bws_strip_block_comments( $raw );
	$content = bws_strip_dynamic_tags( $content );
	$content = wpautop( $content );
	$content = bws_sanitize_rich_content( $content );

	if ( $css ) {
		bws_queue_inline_css( $css );
	}

	return $content;
}
}

/**
 * Process post content through the primary rendering pipeline.
 *
 * Primary path: do_blocks() for full rendering — CSS, dynamic tags,
 * and shortcodes via the render_block filter. Automatic fallback to
 * CSS-extraction mode when memory is insufficient.
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

	if ( ! bws_can_process_post_content( $post_id ) ) {
		bws_content_debug( 'Recursion blocked for post_id=' . $post_id );
		return '';
	}

	if ( ! bws_has_sufficient_memory() ) {
		return bws_process_post_content_fallback( $post_id, $args );
	}

	$start = bws_content_debug_start( $post_id );
	bws_start_processing_post( $post_id );

	$raw = get_post_field( 'post_content', $post_id );

	if ( empty( $raw ) ) {
		bws_end_processing_post( $post_id );
		return '';
	}

	$content = do_blocks( $raw );
	$content = wpautop( $content );
	// Extract any <style> elements GB inlined (when rendering a non-current post
	// after wp_head fires). wp_kses_post would strip the tags but leave CSS text
	// visible; queue the CSS for wp_footer instead.
	$content = bws_extract_and_queue_inline_styles( $content );
	$content = bws_sanitize_rich_content( $content );

	bws_end_processing_post( $post_id );
	bws_content_debug_end( $post_id, $start );

	return $content;
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

// ===============================================
// EDITOR PREVIEW LABELS
// ===============================================

/**
 * Build a structured editor preview label for a try_ tag's slot fallback chain.
 *
 * Walks slots 1-5, applies carry-forward (slot ≥2 empty fields inherit prior slot's
 * canonical value), then renders a comma-separated summary keyed off the template's
 * field-part shape. Image excluded for output-attribute modes (url/id) where the
 * bracket string would break HTML attributes.
 *
 * @since 1.6.0
 * @param array  $options       Parsed tag options (slot fields prefixed N- for N≥2).
 * @param string $base_template Template key ('text', 'content', 'image', 'title', 'permalink', 'datetime_single', 'datetime_range').
 * @return string Bracket preview label, or '' when template excluded or no slots configured.
 */
if ( ! function_exists( 'bws_build_try_preview_label' ) ) {
function bws_build_try_preview_label( array $options, string $base_template ): string {
	$as       = $options['as'] ?? '';
	$fallback = $options['fallback'] ?? $options['fallback_text'] ?? '';

	// Image excluded for output-attribute modes (bracket string breaks attribute).
	if ( 'image' === $base_template && ! in_array( $as, [ 'alt', 'caption' ], true ) ) {
		return '';
	}

	// Permalink excluded — URL context, bracket string breaks <a href>.
	if ( 'permalink' === $base_template ) {
		return '';
	}

	// Per-template defaults (mirrors bws_build_preview_label).
	$use_defaults = array( 'text' => 'key', 'image' => 'key', 'content' => 'content' );
	$use_default  = $use_defaults[ $base_template ] ?? '';

	// Walk slots 1-5, normalize each into canonical-token struct.
	// Apply carry-forward: slot ≥2 empty fields inherit prior slot's value.
	$slots    = [];
	$last_src = 'current';
	$last_ref = '';
	$last_key = '';
	$last_use = $use_default;
	$last_tax = '';
	for ( $n = 1; $n <= 5; $n++ ) {
		$src_k = ( 1 === $n ) ? 'src'       : "{$n}-src";
		$ref_k = ( 1 === $n ) ? 'ref'       : "{$n}-ref";
		$stm_k = ( 1 === $n ) ? 'srcTermIn' : "{$n}-srcTermIn";
		$key_k = ( 1 === $n ) ? 'key'       : "{$n}-key";
		$use_k = ( 1 === $n ) ? 'use'       : "{$n}-use";

		$src_raw = $options[ $src_k ] ?? '';
		$ref_raw = $options[ $ref_k ] ?? '';
		$stm_raw = $options[ $stm_k ] ?? '';
		$key_raw = $options[ $key_k ] ?? '';
		$use_raw = $options[ $use_k ] ?? '';

		// Slot ≥2 'same' sentinel normalizes to empty for inherit.
		if ( $n > 1 ) {
			if ( 'same' === $src_raw ) { $src_raw = ''; }
			if ( 'same' === $use_raw ) { $use_raw = ''; }
			// When use=same, key field is hidden in UI — discard stale key.
			if ( '' === $use_raw ) { $key_raw = ''; }
		}

		// Skip slot if no override (slot ≥2 only).
		if ( $n > 1 ) {
			$has_new = '' !== $src_raw
				|| '' !== $ref_raw
				|| '' !== $stm_raw
				|| '' !== $key_raw
				|| '' !== $use_raw;
			if ( ! $has_new ) {
				continue;
			}
		}

		// Slot 1: '' = first-option default token.
		if ( 1 === $n ) {
			if ( '' === $src_raw ) { $src_raw = 'current'; }
			if ( '' === $use_raw && '' !== $use_default ) { $use_raw = $use_default; }
		}

		// Apply carry-forward semantics. srcTermIn does NOT carry forward.
		if ( '' !== $src_raw ) { $last_src = $src_raw; }
		if ( '' !== $ref_raw ) { $last_ref = $ref_raw; }
		$last_tax = $stm_raw;
		if ( '' !== $key_raw ) { $last_key = $key_raw; }
		if ( '' !== $use_raw ) { $last_use = $use_raw; }

		$slots[] = [
			'n'   => $n,
			'src' => $last_src,
			'ref' => $last_ref,
			'tax' => $last_tax,
			'key' => $last_key,
			'use' => $last_use,
		];
	}

	if ( empty( $slots ) ) {
		$inner = '⚠ Try: no slots configured';
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return '[' . $inner . ']';
	}

	// Collect per-slot warnings.
	$warnings = [];
	foreach ( $slots as $slot ) {
		if ( 'ref' === $slot['src'] && '' === $slot['ref'] ) {
			$warnings[] = 'slot ' . $slot['n'] . ' no ref';
		}
		// Per-template missing-key checks.
		$needs_key = false;
		if ( 'text' === $base_template ) {
			$needs_key = 'title' !== $slot['use'];
		} elseif ( 'content' === $base_template ) {
			$needs_key = 'key' === $slot['use'];
		} elseif ( 'image' === $base_template ) {
			$needs_key = 'featured' !== $slot['use'];
		}
		if ( $needs_key && '' === $slot['key'] ) {
			$warnings[] = 'slot ' . $slot['n'] . ' no key';
		}
	}

	if ( ! empty( $warnings ) ) {
		$inner = '⚠ Try: ' . implode( ', ', $warnings );
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return '[' . $inner . ']';
	}

	// Compute uniformity across slots.
	$field_parts  = [];
	$source_parts = [];
	foreach ( $slots as $slot ) {
		$field_parts[]  = bws_try_preview_field_part( $base_template, $slot['use'], $slot['key'], $as );
		$source_parts[] = bws_try_preview_source_part( $slot['src'], $slot['ref'], $slot['tax'], true );
	}
	$uniform_field  = 1 === count( array_unique( $field_parts ) );
	$uniform_source = 1 === count( array_unique( $source_parts ) );

	// Datetime templates: same field across slots; render base shape + source list.
	if ( str_starts_with( $base_template, 'datetime_' ) ) {
		$datetime_part = bws_try_preview_datetime_part( $base_template, $options );
		if ( $uniform_source ) {
			// Single slot or all sources match — drop source list, keep base form.
			$inner = 'Try ' . $datetime_part;
			$src_segment = $source_parts[0];
			if ( '' !== $src_segment && 'Current' !== $src_segment ) {
				$inner .= ' from ' . $src_segment;
			}
		} else {
			$inner = 'Try ' . $datetime_part . ' from ' . implode( ', ', $source_parts );
		}
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return '[' . $inner . ']';
	}

	// Template-name prefix per matrix:
	//   text → no template label (default).
	//   content/image → label always (with as-suffix for image).
	//   title/permalink → template name only (no slot variance possible).
	$template_label = bws_try_preview_template_label( $base_template, $as );

	// Title/permalink: single value per slot, always uniform → just `[Try Title]`/`[Try Permalink]`.
	if ( in_array( $base_template, [ 'title', 'permalink' ], true ) ) {
		$inner = 'Try ' . $template_label;
		// Source list when sources vary.
		if ( ! $uniform_source ) {
			$inner .= ' from ' . implode( ', ', $source_parts );
		} else {
			$src_segment = $source_parts[0];
			if ( '' !== $src_segment && 'Current' !== $src_segment ) {
				$inner .= ' from ' . $src_segment;
			}
		}
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return '[' . $inner . ']';
	}

	// Default-mode collapse: single slot at template default → bare label.
	// Applies to content/image only (text has no label to collapse to).
	if ( 1 === count( $slots ) && '' !== $template_label ) {
		$slot = $slots[0];
		$is_template_default = ( 'content' === $base_template && 'content' === $slot['use'] )
			|| ( 'image'   === $base_template && 'key'     === $slot['use'] && '' === $slot['key'] );
		// (Image default would never hit this — empty key triggers warning above.)
		if ( $is_template_default ) {
			$inner = 'Try ' . $template_label;
			$src_segment = $source_parts[0];
			if ( '' !== $src_segment && 'Current' !== $src_segment ) {
				$inner .= ' from ' . $src_segment;
			}
			if ( $fallback ) {
				$inner .= ' (fallback: “' . $fallback . '”)';
			}
			return '[' . $inner . ']';
		}
	}

	// Render based on uniformity.
	if ( $uniform_field && $uniform_source ) {
		// Single distinct slot effective. Rare past slot 1.
		$inner = 'Try';
		if ( '' !== $template_label ) {
			$inner .= ' ' . $template_label . ': ' . $field_parts[0];
		} else {
			$inner .= ' ' . $field_parts[0];
		}
		$src_segment = $source_parts[0];
		if ( '' !== $src_segment && 'Current' !== $src_segment ) {
			$inner .= ' from ' . $src_segment;
		}
	} elseif ( $uniform_field ) {
		// Same field, varying sources. Render `from <list>`.
		$inner = 'Try';
		if ( '' !== $template_label ) {
			$inner .= ' ' . $template_label . ': ' . $field_parts[0];
		} else {
			$inner .= ' ' . $field_parts[0];
		}
		$inner .= ' from ' . implode( ', ', $source_parts );
	} elseif ( $uniform_source ) {
		// Same source, varying fields. Render field list.
		$inner = 'Try';
		if ( '' !== $template_label ) {
			$inner .= ' ' . $template_label . ': ' . implode( ', ', $field_parts );
		} else {
			$inner .= ' ' . implode( ', ', $field_parts );
		}
		$src_segment = $source_parts[0];
		if ( '' !== $src_segment && 'Current' !== $src_segment ) {
			$inner .= ' from ' . $src_segment;
		}
	} else {
		// Mixed: per-slot enumeration, each slot = field + ' from ' + source.
		$slot_summaries = [];
		foreach ( $slots as $i => $slot ) {
			$slot_summary = $field_parts[ $i ];
			$src_segment  = $source_parts[ $i ];
			if ( '' !== $src_segment ) {
				$slot_summary .= ' from ' . $src_segment;
			}
			$slot_summaries[] = $slot_summary;
		}
		$inner = 'Try';
		if ( '' !== $template_label ) {
			$inner .= ' ' . $template_label . ': ' . implode( ', ', $slot_summaries );
		} else {
			$inner .= ' ' . implode( ', ', $slot_summaries );
		}
	}

	if ( $fallback ) {
		$inner .= ' (fallback: “' . $fallback . '”)';
	}
	return '[' . $inner . ']';
}
}

/**
 * Template-name label for try_ preview labels.
 *
 * Returns '' for text (text is the default; no template label needed). Returns
 * 'Content', 'Image Alt Text'/'Image Caption', 'Title', 'Permalink' for the
 * other templates.
 *
 * @since 1.6.0
 */
if ( ! function_exists( 'bws_try_preview_template_label' ) ) {
function bws_try_preview_template_label( string $base_template, string $as ): string {
	switch ( $base_template ) {
		case 'text':
			return '';
		case 'content':
			return 'Content';
		case 'image':
			$suffix = 'alt' === $as ? ' Alt Text' : ' Caption';
			return 'Image' . $suffix;
		case 'title':
			return 'Title';
		case 'permalink':
			return 'Permalink';
	}
	return '';
}
}

/**
 * Build a try_ preview slot's field-part.
 *
 * Mode-value keywords (Title, Excerpt, Content, Featured) capitalized.
 * User-supplied identifiers wrapped in straight single quotes.
 *
 * @since 1.6.0
 */
if ( ! function_exists( 'bws_try_preview_field_part' ) ) {
function bws_try_preview_field_part( string $base_template, string $use, string $key, string $as ): string {
	switch ( $base_template ) {
		case 'text':
			return 'title' === $use ? 'Title' : "'" . $key . "'";
		case 'content':
			if ( 'excerpt' === $use ) {
				return 'Excerpt';
			}
			if ( 'key' === $use ) {
				return "'" . $key . "'";
			}
			return 'Content';
		case 'image':
			return 'featured' === $use ? 'Featured' : "'" . $key . "'";
		case 'title':
			return 'Title';
		case 'permalink':
			return 'Permalink';
	}
	return '';
}
}

/**
 * Build a try_ preview slot's source-part.
 *
 * @since 1.6.0
 * @param string $src       Canonical source token ('current', 'ref').
 * @param string $ref       Relationship field key (when src='ref').
 * @param string $tax       Taxonomy slug (when srcTermIn set).
 * @param bool   $named_current When true, returns 'Current' for src=current
 *                          (used when source-part appears in a list and needs
 *                          a visible anchor). Default false (returns '').
 * @return string Source segment (e.g. "Current", "Ref 'rel_post'", "Ref 'rel_post' → Category Term").
 */
if ( ! function_exists( 'bws_try_preview_source_part' ) ) {
function bws_try_preview_source_part( string $src, string $ref, string $tax, bool $named_current = false ): string {
	$segments = [];
	if ( 'ref' === $src && $ref ) {
		$segments[] = "Ref '" . $ref . "'";
	} elseif ( 'current' === $src && $named_current ) {
		$segments[] = 'Current';
	}
	if ( '' !== $tax ) {
		$tax_obj    = get_taxonomy( $tax );
		$tax_name   = $tax_obj ? $tax_obj->labels->singular_name : $tax;
		$segments[] = '→ ' . $tax_name . ' Term';
	}
	return implode( ' ', $segments );
}
}

/**
 * Build the datetime portion of a try_ preview label (e.g. "Date like \"Apr 24\"").
 *
 * Reuses the same shape as bws_build_preview_label() for datetime base tags.
 *
 * @since 1.6.0
 */
if ( ! function_exists( 'bws_try_preview_datetime_part' ) ) {
function bws_try_preview_datetime_part( string $base_template, array $options ): string {
	$is_range = 'datetime_range' === $base_template;
	$as       = $options['as'] ?? '';

	switch ( $as ) {
		case 'date':
			$prefix    = $is_range ? 'Date Range' : 'Date';
			$offset    = DAY_IN_SECONDS;
			$wp_format = get_option( 'date_format', 'F j, Y' );
			break;
		case 'time':
			$prefix    = $is_range ? 'Time Range' : 'Time';
			$offset    = HOUR_IN_SECONDS;
			$wp_format = get_option( 'time_format', 'g:i A' );
			break;
		default:
			$prefix    = $is_range ? 'Date-Time Range' : 'Date-Time';
			$offset    = HOUR_IN_SECONDS;
			$wp_format = get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i A' );
	}

	$custom_format = $options['format'] ?? $options['custom_format'] ?? '';
	if ( $custom_format ) {
		$wp_format = $custom_format;
	}

	$tz  = wp_timezone();
	$now = new DateTime( 'now', $tz );

	if ( $is_range ) {
		$end = clone $now;
		$end->modify( '+' . $offset . ' seconds' );
		// Normalize new option keys → legacy keys expected by bws_format_date_range().
		$range_options = $options;
		if ( isset( $range_options['rangeSep'] ) && ! isset( $range_options['separator'] ) ) {
			$range_options['separator'] = $range_options['rangeSep'];
		} elseif ( ! isset( $range_options['separator'] ) ) {
			$range_options['separator'] = '–';
		}
		$range_options['omit_current_year'] = empty( $options['showCurrentYear'] );
		$range_options['smart_time']        = empty( $options['showMidnight'] );
		$formatted = bws_format_date_range( $now, $end, $wp_format, $range_options );
	} else {
		$formatted = $now->format( $wp_format );
	}

	return $prefix . ' like “' . $formatted . '”';
}
}

/**
 * Strip default-marked select options' first-entry value to '' before GB registration.
 *
 * Options array entries flagged `_strip_default => true` get their first option's
 * value flipped to '' so the wire format omits the default token (GB drops empty
 * values from the serialized tag string). Internal canonical token (e.g. 'current',
 * 'key', 'content') is preserved in source files for readability; consumers apply
 * `?? '<canonical>'` to restore it at read time.
 *
 * The `_strip_default` marker itself is removed before passing to GB.
 *
 * @since 1.7.0
 * @param array $options Options array as registered in PHP.
 * @return array Options with strip applied.
 */
if ( ! function_exists( 'bws_strip_default_select_values' ) ) {
function bws_strip_default_select_values( array $options ): array {
	foreach ( $options as &$opt ) {
		if ( ! empty( $opt['_strip_default'] ) && isset( $opt['options'][0]['value'] ) ) {
			$opt['options'][0]['value'] = '';
		}
		unset( $opt['_strip_default'] );
	}
	return $options;
}
}

/**
 * Build a structured preview label for a tag that returned empty in the editor.
 *
 * Schema: [tag matrix §Editor preview label schema].
 * Called only when $instance->context['bwsEditorPreview'] is set and
 * resolution produced an empty value.
 *
 * @since 1.6.0
 * @param array  $options  Parsed tag options.
 * @param string $template Full template name including modifier prefix (e.g. 'term_text', 'text').
 * @return string Bracket preview label, or '' when template is excluded.
 */
if ( ! function_exists( 'bws_build_preview_label' ) ) {
function bws_build_preview_label( array $options, string $template ): string {
	// Detect modifier prefix → base template.
	// Built-in modifier prefixes; external plugins register their own via the
	// `bws_dynamic_tags_preview_modifier_map` filter (see plugin-integration.md §2).
	$modifier_label = '';
	$base_template  = $template;
	$modifier_map   = apply_filters(
		'bws_dynamic_tags_preview_modifier_map',
		[ 'term_' => 'Term' ]
	);
	foreach ( $modifier_map as $prefix => $label ) {
		if ( str_starts_with( $template, $prefix ) ) {
			$modifier_label = $label;
			$base_template  = substr( $template, strlen( $prefix ) );
			break;
		}
	}

	$source_val = $options['src'] ?? $options['source'] ?? 'current';
	if ( '' === $source_val ) {
		$source_val = 'current';
	}
	$ref = $options['ref'] ?? '';
	// Term-modifier (`term_*`): read GB's native `tax` (term's own taxonomy, descriptive).
	// Cross-source base tag: read `srcTermIn` (post→term hop).
	$is_term_modifier = ( 'Term' === $modifier_label );
	$tax              = $is_term_modifier
		? ( $options['tax'] ?? '' )
		: ( $options['srcTermIn'] ?? '' );
	$src_term = '' !== $tax;
	$key      = $options['key'] ?? '';
	$use_defaults = array( 'text' => 'key', 'image' => 'key', 'content' => 'content' );
	$use_default  = $use_defaults[ $base_template ] ?? '';
	$use          = $options['use'] ?? $use_default;
	if ( '' === $use && '' !== $use_default ) {
		$use = $use_default;
	}
	$as       = $options['as'] ?? '';
	$fallback = $options['fallback'] ?? '';

	// Image excluded for output-attribute modes (bracket string silently breaks the element).
	if ( 'image' === $base_template && ! in_array( $as, [ 'alt', 'caption' ], true ) ) {
		return '';
	}

	// Collect missing required items for warning label.
	$missing = [];
	if ( 'ref' === $source_val && '' === $ref ) {
		$missing[] = 'ref key';
	}
	if ( $src_term && '' === $tax ) {
		$missing[] = 'taxonomy';
	}
	if ( 'text' === $base_template && '' === $key && 'title' !== $use ) {
		$missing[] = 'meta key';
	} elseif ( 'content' === $base_template && 'key' === $use && '' === $key ) {
		$missing[] = 'meta key';
	} elseif ( 'image' === $base_template && 'featured' !== $use && '' === $key ) {
		$missing[] = 'meta key';
	}

	if ( ! empty( $missing ) ) {
		$count = count( $missing );
		if ( 1 === $count ) {
			$warning = 'No ' . $missing[0] . ' set';
		} elseif ( 2 === $count ) {
			$warning = 'No ' . $missing[0] . ' or ' . $missing[1] . ' set';
		} else {
			$last    = array_pop( $missing );
			$warning = 'No ' . implode( ', ', $missing ) . ', or ' . $last . ' set';
		}
		$inner = '⚠ ' . $warning;
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return '[' . $inner . ']';
	}

	// Build context part (space-joined segments).
	// Term-modifier with tax: merge modifier label and taxonomy name into one segment
	//   ('Benefit Tier Term'), no hop arrow — entity is directly that term.
	// Term-modifier without tax: bare 'Term' (entity is current term context).
	// Cross-source base with srcTermIn: append '→ <Tax> Term' as hop segment after
	//   any modifier/source segments.
	$ctx_segments = [];
	$tax_obj      = $src_term ? get_taxonomy( $tax ) : null;
	$tax_name     = $tax_obj ? $tax_obj->labels->singular_name : $tax;

	if ( $is_term_modifier ) {
		if ( $src_term ) {
			$ctx_segments[] = $tax_name . ' Term';
		} else {
			$ctx_segments[] = 'Term';
		}
	} elseif ( $modifier_label ) {
		$ctx_segments[] = $modifier_label;
	}
	if ( 'ref' === $source_val && $ref ) {
		$ctx_segments[] = "Ref '" . $ref . "'";
	}
	if ( $src_term && ! $is_term_modifier ) {
		// '→' arrow only when this hop segment follows another segment (modifier label
		// or ref). When standalone (current post → term, no other context), drop arrow.
		$prefix = empty( $ctx_segments ) ? '' : '→ ';
		$ctx_segments[] = $prefix . $tax_name . ' Term';
	}
	$context_part = implode( ' ', $ctx_segments );

	// Datetime templates: live preview using current time.
	if ( str_starts_with( $base_template, 'datetime_' ) ) {
		$is_range = 'datetime_range' === $base_template;

		switch ( $as ) {
			case 'date':
				$prefix    = $is_range ? 'Date Range' : 'Date';
				$offset    = DAY_IN_SECONDS;
				$wp_format = get_option( 'date_format', 'F j, Y' );
				break;
			case 'time':
				$prefix    = $is_range ? 'Time Range' : 'Time';
				$offset    = HOUR_IN_SECONDS;
				$wp_format = get_option( 'time_format', 'g:i A' );
				break;
			default:
				$prefix    = $is_range ? 'Date-Time Range' : 'Date-Time';
				$offset    = HOUR_IN_SECONDS;
				$wp_format = get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i A' );
		}

		// Respect custom format option if set.
		$custom_format = $options['format'] ?? $options['custom_format'] ?? '';
		if ( $custom_format ) {
			$wp_format = $custom_format;
		}

		$tz  = wp_timezone();
		$now = new DateTime( 'now', $tz );

		if ( $is_range ) {
			$end = clone $now;
			$end->modify( '+' . $offset . ' seconds' );
			// Normalize new option keys → legacy keys expected by bws_format_date_range().
			$range_options = $options;
			if ( isset( $range_options['rangeSep'] ) && ! isset( $range_options['separator'] ) ) {
				$range_options['separator'] = $range_options['rangeSep'];
			} elseif ( ! isset( $range_options['separator'] ) ) {
				$range_options['separator'] = '–';
			}
			$range_options['omit_current_year'] = empty( $options['showCurrentYear'] );
			$range_options['smart_time']        = empty( $options['showMidnight'] );
			$formatted = bws_format_date_range( $now, $end, $wp_format, $range_options );
		} else {
			$formatted = $now->format( $wp_format );
		}

		$inner = $prefix . ' like “' . $formatted . '”';
		if ( $context_part ) {
			$inner .= ' from ' . $context_part;
		}
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return '[' . $inner . ']';
	}

	// Build field part (template-specific).
	// Convention: template label (Content, Image Alt Text, etc.) leads; mode-value
	// or quoted user identifier follows after a colon when both are present.
	// Marker convention: 'X' = literal user-supplied identifier (straight single quotes).
	$field_part = '';
	switch ( $base_template ) {
		case 'text':
			// Text has no template label by default. Title mode uses bare 'Title'.
			$field_part = 'title' === $use ? 'Title' : "'" . $key . "'";
			break;
		case 'content':
			if ( 'excerpt' === $use ) {
				$field_part = 'Content: Excerpt';
			} elseif ( 'key' === $use ) {
				$field_part = "Content: '" . $key . "'";
			} else {
				$field_part = 'Content';
			}
			break;
		case 'image':
			$suffix     = 'alt' === $as ? ' Alt Text' : ' Caption';
			$field_part = 'featured' === $use
				? 'Image' . $suffix . ': Featured'
				: 'Image' . $suffix . ": '" . $key . "'";
			break;
		case 'title':
			$field_part = 'Title';
			break;
	}

	// Assemble final label.
	if ( $field_part && $context_part ) {
		$inner = $field_part . ' from ' . $context_part;
	} elseif ( $field_part ) {
		$inner = $field_part;
	} elseif ( $context_part ) {
		$inner = $context_part;
	} else {
		return '';
	}

	if ( $fallback ) {
		$inner .= ' (fallback: “' . $fallback . '”)';
	}
	return '[' . $inner . ']';
}
}
