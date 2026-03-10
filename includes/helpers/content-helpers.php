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
 * @param int    $post_id   Source post ID.
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
 * @since 1.0.0
 * @param mixed $post_data Post data from ACF (WP_Post, int, array).
 * @return int|false Post ID or false.
 */
if ( ! function_exists( 'bws_extract_post_id' ) ) {
function bws_extract_post_id( $post_data ) {
	if ( is_object( $post_data ) && isset( $post_data->ID ) ) {
		return $post_data->ID;
	}

	if ( $post_data instanceof WP_Post ) {
		return $post_data->ID;
	}

	if ( is_numeric( $post_data ) ) {
		return intval( $post_data );
	}

	if ( is_array( $post_data ) && isset( $post_data['ID'] ) ) {
		return $post_data['ID'];
	}

	return false;
}
}

/**
 * Extract text field from post with security constraints.
 *
 * @since 1.0.0
 * @param int    $post_id    Post ID.
 * @param string $field_name Field name to extract.
 * @return string Text content.
 */
if ( ! function_exists( 'bws_extract_text_field' ) ) {
function bws_extract_text_field( $post_id, $field_name ) {
	$standard_fields = array(
		'post_title'   => get_the_title( $post_id ),
		'post_content' => get_post_field( 'post_content', $post_id ),
		'post_excerpt' => get_the_excerpt( $post_id ),
	);

	if ( array_key_exists( $field_name, $standard_fields ) ) {
		return $standard_fields[ $field_name ];
	}

	// ACF field - only return string values.
	if ( function_exists( 'get_field' ) ) {
		$value = get_field( $field_name, $post_id );

		if ( is_string( $value ) ) {
			return $value;
		}

		// For non-string values, return empty to prevent data exposure.
		if ( null !== $value && false !== $value ) {
			return '';
		}
	}

	// Standard meta.
	$meta_value = get_post_meta( $post_id, $field_name, true );

	if ( is_string( $meta_value ) && '' !== $meta_value ) {
		return $meta_value;
	}

	return '';
}
}

/**
 * Extract URL field from post.
 *
 * @since 1.0.0
 * @param int    $post_id    Post ID.
 * @param string $field_name Field name to extract.
 * @return string URL.
 */
if ( ! function_exists( 'bws_extract_url_field' ) ) {
function bws_extract_url_field( $post_id, $field_name ) {
	if ( 'permalink' === $field_name ) {
		return get_permalink( $post_id );
	}

	if ( function_exists( 'get_field' ) ) {
		$value = get_field( $field_name, $post_id );

		if ( $value ) {
			if ( is_array( $value ) && isset( $value['url'] ) ) {
				return $value['url'];
			}

			if ( is_string( $value ) ) {
				return $value;
			}
		}
	}

	$meta_value = get_post_meta( $post_id, $field_name, true );
	return is_string( $meta_value ) ? $meta_value : '';
}
}

/**
 * Get link URL based on link type.
 *
 * @since 1.0.0
 * @param int    $post_id    Post ID.
 * @param string $link_to    Link type (post, custom).
 * @param string $link_field Custom field for URL.
 * @return string URL.
 */
if ( ! function_exists( 'bws_get_link_url' ) ) {
function bws_get_link_url( $post_id, $link_to, $link_field ) {
	switch ( $link_to ) {
		case 'post':
			return get_permalink( $post_id );

		case 'custom':
			if ( ! empty( $link_field ) ) {
				return bws_extract_url_field( $post_id, $link_field );
			}
			break;
	}

	return '';
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
			'help'        => __( 'ACF relationship or post object field key that links to the related post.', 'generateblocks' ),
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
 * Active when WP_DEBUG is true or the benchmark logging setting is on.
 *
 * @since 1.1.0
 * @param string $message Message to log.
 */
if ( ! function_exists( 'bws_content_debug' ) ) {
function bws_content_debug( $message ) {
	$enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG );

	if ( ! $enabled && class_exists( 'BWS\DynamicTags\Admin\SettingsPage' ) ) {
		$enabled = \BWS\DynamicTags\Admin\SettingsPage::is_benchmark_logging_enabled();
	}

	if ( ! $enabled ) {
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
	$enabled = ( defined( 'WP_DEBUG' ) && WP_DEBUG );

	if ( ! $enabled && class_exists( 'BWS\DynamicTags\Admin\SettingsPage' ) ) {
		$enabled = \BWS\DynamicTags\Admin\SettingsPage::is_benchmark_logging_enabled();
	}

	if ( ! $enabled ) {
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
