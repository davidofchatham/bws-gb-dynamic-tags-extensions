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

// ===============================================
// EDITOR PREVIEW LABELS
// ===============================================

/**
 * Build a structured preview label for a tag that can't resolve in the editor.
 *
 * Schema: [tag matrix §Editor preview label schema].
 * Called only when $instance->context['bwsEditorPreview'] is set and
 * the real tag value is empty.
 *
 * @since 1.7.0
 * @param array  $options  Parsed tag options.
 * @param string $template Full template name including modifier prefix (e.g. 'term_text', 'text').
 * @return string Bracket preview label, or '' when template is excluded.
 */
if ( ! function_exists( 'bws_build_preview_label' ) ) {
function bws_build_preview_label( array $options, string $template ): string {
	// Detect modifier prefix → base template.
	$modifier_label = '';
	$base_template  = $template;
	$modifier_map   = [ 'term_' => 'Term', 'views_' => 'Views' ];
	foreach ( $modifier_map as $prefix => $label ) {
		if ( str_starts_with( $template, $prefix ) ) {
			$modifier_label = $label;
			$base_template  = substr( $template, strlen( $prefix ) );
			break;
		}
	}

	$source_val = $options['source'] ?? '';
	$ref        = $options['ref'] ?? '';
	$src_term   = ! empty( $options['srcTerm'] );
	$tax        = $options['tax'] ?? '';
	$key        = $options['key'] ?? '';
	$use        = $options['use'] ?? '';
	$as         = $options['as'] ?? '';
	$fallback   = $options['fallback'] ?? '';

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
			$inner .= ' · fallback: "' . $fallback . '"';
		}
		return '[' . $inner . ']';
	}

	// Build context part (space-joined segments).
	$ctx_segments = [];
	if ( $modifier_label ) {
		$ctx_segments[] = $modifier_label;
	}
	if ( 'ref' === $source_val && $ref ) {
		$ctx_segments[] = 'Ref (' . $ref . ')';
	}
	if ( $src_term && $tax ) {
		$tax_obj        = get_taxonomy( $tax );
		$tax_name       = $tax_obj ? $tax_obj->labels->singular_name : $tax;
		$ctx_segments[] = '→ ' . $tax_name . ' Term';
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
				$offset    = DAY_IN_SECONDS;
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
			$end       = clone $now;
			$end->modify( '+' . $offset . ' seconds' );
			$sep       = $options['rangeSep'] ?? $options['range_sep'] ?? $options['separator'] ?? ' – ';
			$formatted = $now->format( $wp_format ) . $sep . $end->format( $wp_format );
		} else {
			$formatted = $now->format( $wp_format );
		}

		$inner = $prefix . ' like "' . $formatted . '"';
		if ( $context_part ) {
			$inner .= ' from ' . $context_part;
		}
		if ( $fallback ) {
			$inner .= ' · fallback: "' . $fallback . '"';
		}
		return '[' . $inner . ']';
	}

	// Build field part (template-specific).
	$field_part = '';
	switch ( $base_template ) {
		case 'text':
			$field_part = 'title' === $use ? 'Title' : 'Text Field (' . $key . ')';
			break;
		case 'content':
			if ( 'excerpt' === $use ) {
				$field_part = 'Excerpt';
			} elseif ( 'key' === $use ) {
				$field_part = 'Content Field (' . $key . ')';
			} else {
				$field_part = 'Content';
			}
			break;
		case 'image':
			$suffix     = 'alt' === $as ? ' Alt Text' : ' Caption';
			$field_part = 'featured' === $use
				? 'Featured Image' . $suffix
				: 'Image Field (' . $key . ')' . $suffix;
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
		$inner .= ' · fallback: "' . $fallback . '"';
	}
	return '[' . $inner . ']';
}
}
