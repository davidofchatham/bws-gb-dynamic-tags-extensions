<?php
/**
 * `wp bws render-tag` — render a dynamic tag string as if on a given URL.
 *
 * The keystone of discovery-mode testing (fixture-testbed plan): renders a tag
 * with REAL ambient context, not faked. Grown from tools/debug/spike-render-seam.php.
 *
 * Mechanism (proven by the spike):
 *   1. --url sets $_SERVER keys via WP_CLI but does NOT run the main query —
 *      so is_tax()/get_queried_object() are empty until we call wp().
 *   2. wp() runs parse_request()/query_posts()/register_globals() → a genuine
 *      $wp_query, so is_tax()/get_queried_object() reflect the URL (precedent:
 *      wp-cli's own profile-command).
 *   3. This plugin touches the block instance ONLY through ->context, so a bare
 *      stdClass with a context array is a sufficient fake instance (the shape GB
 *      ships in its editor REST route). Leaving bwsEditorPreview unset yields real
 *      output, not preview text.
 *
 * Dev/testing only — registered under `defined('WP_CLI')` from the main plugin
 * file; never part of shipped runtime.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Render dynamic tags with real ambient context.
 */
class BWS_Render_Tag_Command {

	/**
	 * Render a dynamic tag string as if requested at a given URL.
	 *
	 * ## OPTIONS
	 *
	 * <tag>
	 * : The tag string to render, e.g. '{{text key:main_line}}'. Quote it.
	 *
	 * [--url=<url>]
	 * : Render as if this URL was requested. Runs the real main query so
	 *   ambient context (is_tax / queried object / current post) is genuine.
	 *   Omit to render in the default (no query) context. NOTE: --url is a
	 *   WP-CLI global param — it is consumed before this command sees it, which
	 *   is exactly what sets the $_SERVER request keys we need. The command
	 *   detects its presence via the runtime config, not $assoc_args.
	 *
	 * [--loop-item=<post_id>]
	 * : Simulate a query-loop row by seeding the block context with this post
	 *   ID as the loop item (queryType WP_Query). Loop-row context wins over
	 *   ambient, matching front-end precedence.
	 *
	 * [--preview]
	 * : Seed the block context with bwsEditorPreview so callbacks return the
	 *   editor CONFIGURATION-PREVIEW string (the bracket label shown while a tag
	 *   is being configured) instead of real output. Mirrors the editor REST
	 *   route. Use to eyeball the preview a tag produces on a given context
	 *   (`bws_build_preview_label` / `_try_` / `_join_`). Combine with --url to
	 *   preview against a real queried object.
	 *
	 * [--porcelain]
	 * : Output only the rendered result, nothing else.
	 *
	 * ## EXAMPLES
	 *
	 *     wp bws render-tag '{{text key:main_line}}' --url=https://testbed.test/matrix-post-meta/
	 *     wp bws render-tag '{{phone srcTermIn:department|key:phone|limit:5}}' --url=https://testbed.test/matrix-terms-mixed/
	 *     wp bws render-tag '{{title src:current}}' --url=https://testbed.test/benefit-type/health/
	 *     wp bws render-tag '{{join key:name_first|2-key:name_last}}' --preview --porcelain
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional args: [0] = tag string.
	 * @param array $assoc_args Flags: url, loop-item, preview, porcelain.
	 */
	public function __invoke( $args, $assoc_args ) {
		$tag       = $args[0];
		$loop_item = $assoc_args['loop-item'] ?? null;
		$preview   = isset( $assoc_args['preview'] );
		$porcelain = isset( $assoc_args['porcelain'] );

		// --url is a WP-CLI GLOBAL param — consumed before the command runs (which
		// is what sets $_SERVER's request keys). Read it from the runtime config.
		$url = (string) WP_CLI::get_runner()->config['url'] ?? '';

		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			WP_CLI::error( 'GenerateBlocks dynamic tags API not available (GB Pro active?).' );
		}

		// --url only set $_SERVER; run the real main query so ambient context is genuine.
		if ( '' !== $url ) {
			wp();
		}

		$instance          = new stdClass();
		$instance->context = array();

		if ( $preview ) {
			$instance->context['bwsEditorPreview'] = true;
		}

		if ( null !== $loop_item ) {
			$instance->context['generateblocks/loopItem']  = (int) $loop_item;
			$instance->context['generateblocks/queryType'] = 'WP_Query';
		}

		$out = GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, array(), $instance );

		if ( $porcelain ) {
			WP_CLI::line( (string) $out );
			return;
		}

		$qo    = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		$ctx   = 'default';
		if ( '' !== $url ) {
			if ( $qo instanceof WP_Term ) {
				$ctx = "term {$qo->taxonomy}:{$qo->slug} (id {$qo->term_id})";
			} elseif ( $qo instanceof WP_Post ) {
				$ctx = "post {$qo->post_type}:{$qo->post_name} (id {$qo->ID})";
			} elseif ( null === $qo ) {
				$ctx = 'no queried object';
			} else {
				$ctx = get_class( $qo );
			}
		}

		WP_CLI::log( 'tag     : ' . $tag );
		WP_CLI::log( 'url     : ' . ( '' !== $url ? $url : '(none)' ) );
		WP_CLI::log( 'context : ' . $ctx );
		if ( null !== $loop_item ) {
			WP_CLI::log( 'loop    : post ' . (int) $loop_item );
		}
		WP_CLI::log( 'output  : ' . ( '' === (string) $out ? '(empty)' : (string) $out ) );
	}
}
