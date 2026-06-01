<?php
/**
 * Post content rendering pipeline.
 *
 * Owns the recursion guard, memory check, do_blocks dispatch, inline-style
 * extraction, and CSS queue for the {{content}} tag and any caller that needs
 * to render raw post_content / block markup through GenerateBlocks.
 *
 * ## Recursion stack contract
 *
 * `render()` accepts a string `$cache_key` identifying the logical entity whose
 * content is being rendered. The key is pushed onto a static stack on entry and
 * popped on exit. A repeat key on the stack short-circuits with `''` (the
 * recursion guard). Callers MUST pick stable, unique keys per logical entity —
 * collisions defeat the guard.
 *
 * Conventional formats:
 *   - `'post:' . $post_id`   — post_content render (current and only caller in v1.8.0)
 *   - `'option:' . $key`     — wp_options render (reserved for v1.9.0 src:site)
 *
 * The differentiator is `src`, not `use`: a tag with `src:site` reads
 * wp_options instead of post meta, regardless of the `use` value.
 *
 * ## Memory threshold
 *
 * Below `bws_content_memory_threshold` (default 0.80 of memory_limit) the
 * primary path runs. At or above, callers fall back to a CSS-extraction-only
 * pipeline that does NOT push the stack (no recursion to guard).
 *
 * ## Filters
 *
 * - `bws_content_memory_threshold` (float, default 0.80)
 * - `bws_content_max_recursion_depth` (int, default 3)
 *
 * @package BWS_Dynamic_Tags
 * @since 1.8.0
 */

namespace BWS\DynamicTags\Content;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\ContentProcessor' ) ) {

class ContentProcessor {

	/**
	 * Active processing stack of cache_keys.
	 *
	 * @var string[]
	 */
	private static $stack = array();

	/**
	 * Queued inline CSS pending wp_footer output.
	 *
	 * @var string
	 */
	private static $queued_css = '';

	/**
	 * Whether the wp_footer queue hook has been registered for this request.
	 *
	 * @var bool
	 */
	private static $footer_hook_registered = false;

	/**
	 * Current stack depth (for debug).
	 */
	public static function stack_depth(): int {
		return count( self::$stack );
	}

	/**
	 * Whether $cache_key may be processed.
	 *
	 * Returns false when:
	 *  - $cache_key is already on the stack (recursion guard), OR
	 *  - stack depth >= bws_content_max_recursion_depth filter (default 3).
	 *
	 * @invariant Same $cache_key on stack → render() returns ''.
	 * @invariant Stack depth >= max → render() returns ''.
	 */
	public static function can_process( string $cache_key ): bool {
		$max = (int) apply_filters( 'bws_content_max_recursion_depth', 3 );
		return ! in_array( $cache_key, self::$stack, true ) && count( self::$stack ) < $max;
	}

	/**
	 * Push $cache_key onto the processing stack.
	 */
	public static function start( string $cache_key ): void {
		self::$stack[] = $cache_key;
	}

	/**
	 * Pop $cache_key from the processing stack.
	 *
	 * Removed by value (array_search + array_splice), NOT LIFO. Protects
	 * against unbalanced push/pop pairs during exception unwinding.
	 *
	 * @invariant end() removes by value, not LIFO.
	 */
	public static function end( string $cache_key ): void {
		if ( empty( self::$stack ) ) {
			return;
		}
		$key = array_search( $cache_key, self::$stack, true );
		if ( false !== $key ) {
			array_splice( self::$stack, $key, 1 );
		}
	}

	/**
	 * Whether memory_get_usage / memory_limit is below the configured threshold.
	 *
	 * Threshold filterable via bws_content_memory_threshold (default 0.80).
	 *
	 * @invariant memory_limit = '-1' → returns true (no limit set).
	 * @invariant wp_convert_hr_to_bytes($limit) <= 0 → returns true
	 *            (indeterminate limit = sufficient).
	 */
	public static function has_sufficient_memory(): bool {
		$limit_str = ini_get( 'memory_limit' );

		if ( '-1' === $limit_str ) {
			return true;
		}

		$limit = wp_convert_hr_to_bytes( $limit_str );

		if ( $limit <= 0 ) {
			return true;
		}

		$threshold = (float) apply_filters( 'bws_content_memory_threshold', 0.80 );
		return ( memory_get_usage( true ) / $limit ) < $threshold;
	}

	/**
	 * Render raw block content through the full pipeline.
	 *
	 * Primary path: do_blocks → wpautop → inline-CSS extract → kses.
	 * Falls back to CSS-only extraction when memory is below threshold.
	 *
	 * @invariant Memory check runs BEFORE stack push. Fallback path does NOT
	 *            push the stack (no nested rendering to guard).
	 * @invariant Empty raw content with push already done → pop still fires.
	 * @invariant Inline-style extraction runs AFTER do_blocks + wpautop, BEFORE
	 *            wp_kses_post. Cross-post <style> elements must survive to be
	 *            queued, not stripped.
	 *
	 * @param string $raw       Raw post_content / block markup.
	 * @param string $cache_key Stack-identifying key. See class docblock.
	 * @param array  $args      Reserved for future use.
	 * @return string Rendered HTML, or '' if recursion/depth blocked or raw empty.
	 */
	public static function render( string $raw, string $cache_key, array $args = array() ): string {
		if ( '' === $cache_key ) {
			return '';
		}

		if ( ! self::can_process( $cache_key ) ) {
			\bws_content_debug( 'Recursion blocked for cache_key=' . $cache_key );
			return '';
		}

		if ( ! self::has_sufficient_memory() ) {
			return self::render_fallback( $raw, $args );
		}

		$start = \bws_content_debug_start( $cache_key );
		self::start( $cache_key );

		if ( '' === $raw ) {
			self::end( $cache_key );
			return '';
		}

		$content = do_blocks( $raw );
		$content = wpautop( $content );
		$content = self::extract_and_queue_inline_styles( $content );
		$content = \bws_sanitize_rich_content( $content );

		self::end( $cache_key );
		\bws_content_debug_end( $cache_key, $start );

		return $content;
	}

	/**
	 * Fallback pipeline for low-memory conditions.
	 *
	 * Extracts CSS from block comment JSON attributes, strips block comments
	 * and unresolved {{tag}} placeholders, wpautop + kses. Does NOT push the
	 * stack (no nested rendering to guard).
	 */
	public static function render_fallback( string $raw, array $args = array() ): string {
		if ( '' === $raw ) {
			return '';
		}

		\bws_content_debug( 'Using fallback pipeline' );

		$css     = self::extract_css_from_block_comments( $raw );
		$content = self::strip_block_comments( $raw );
		$content = self::strip_dynamic_tags( $content );
		$content = wpautop( $content );
		$content = \bws_sanitize_rich_content( $content );

		if ( '' !== $css ) {
			self::queue_inline_css( $css );
		}

		return $content;
	}

	/**
	 * Queue CSS for consolidated wp_footer output (priority 5).
	 *
	 * @invariant wp_footer hook (output_queued_inline_css at priority 5)
	 *            registers exactly once per request, on the first call.
	 */
	public static function queue_inline_css( string $css ): void {
		if ( '' === $css ) {
			return;
		}

		if ( ! self::$footer_hook_registered ) {
			add_action( 'wp_footer', array( __CLASS__, 'output_queued_inline_css' ), 5 );
			self::$footer_hook_registered = true;
		}

		self::$queued_css .= $css;
	}

	/**
	 * Output queued inline CSS as a single <style> element. Hooked at wp_footer:5.
	 */
	public static function output_queued_inline_css(): void {
		if ( '' === self::$queued_css ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS from trusted GB block rendering via do_blocks().
		echo '<style id="bws-dynamic-content-inline-css">' . self::$queued_css . "</style>\n";
		self::$queued_css = '';
	}

	/**
	 * Extract inline <style> elements from content and queue their CSS.
	 *
	 * GB inlines block CSS as <style> elements before each block's HTML when
	 * rendering a post other than the current page (wp_head has already fired).
	 * wp_kses_post strips the tags but leaves CSS text visible as content; this
	 * method removes the elements and queues their content for wp_footer.
	 */
	public static function extract_and_queue_inline_styles( string $content ): string {
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

		if ( '' !== $extracted_css ) {
			\bws_content_debug( 'Extracted ' . strlen( $extracted_css ) . ' bytes of GB inline CSS; queuing for wp_footer.' );
			self::queue_inline_css( $extracted_css );
		}

		return $content;
	}

	/**
	 * Strip WordPress block comment delimiters; leave inner block HTML intact.
	 */
	public static function strip_block_comments( string $content ): string {
		return preg_replace( '/<!--\s+\/?wp:\S+.*?-->/s', '', $content );
	}

	/**
	 * Extract concatenated "css" property values from block comment JSON attrs.
	 *
	 * Used by the fallback pipeline to preserve GB styling when do_blocks
	 * cannot run.
	 */
	public static function extract_css_from_block_comments( string $content ): string {
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

		\bws_content_debug( 'Extracted ' . strlen( $css ) . ' bytes of CSS from block comments.' );

		return $css;
	}

	/**
	 * Strip unresolved {{tag ...}} placeholders. Used by fallback path only.
	 */
	public static function strip_dynamic_tags( string $content ): string {
		return preg_replace( '/\{\{[^}]+\}\}/', '', $content );
	}
}

} // class_exists guard
