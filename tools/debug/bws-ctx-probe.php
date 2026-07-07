<?php
/**
 * Context probe — pre-work discovery for #19 (context-aware base tags) +
 * traversal-pipeline base-entity factory.
 *
 * NOT shipped with the plugin. Drop into wp-content/mu-plugins/ on a TEST
 * instance only (per the runtime-debug workflow: instrument + pull to test,
 * never probe a live/cached site — page cache will serve stale HTML and log
 * nothing).
 *
 * Registers a {{bws_ctx_probe}} dynamic tag. Each render logs one JSON line
 * describing every ambient-context signal the future base-entity factory
 * could consume, at the exact moment a GB tag callback runs. Placement +
 * visit matrix: tools/debug/bws-ctx-probe-matrix.md (run rows in order —
 * highest-signal first).
 *
 * Setup on the test instance:
 *   1. Add to wp-config.php:  define( 'BWS_DEBUG_CTX', true );
 *      (WP_DEBUG + WP_DEBUG_LOG on; read wp-content/debug.log)
 *   2. Place the tag where the matrix says, always with a note identifying
 *      the placement, e.g.:
 *        {{bws_ctx_probe note:element-header}}
 *        {{bws_ctx_probe note:loop-item}}
 *      The tag renders a visible [bws_ctx_probe <note>] marker so you can
 *      confirm the placement actually rendered (vs cached/skipped).
 *   3. Visit each matrix row's URL; one log line per probe render:
 *        [BWS_CTX] <note> {"conditionals":{...},...}
 *
 * Remove this file when the matrix is filled.
 *
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BWS_DEBUG_CTX' ) || ! BWS_DEBUG_CTX ) {
	return;
}

add_action(
	'init',
	static function () {
		if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
			error_log( '[BWS_CTX] GenerateBlocks_Register_Dynamic_Tag missing — GB not active, probe tag not registered.' );
			return;
		}

		new GenerateBlocks_Register_Dynamic_Tag(
			array(
				'title'    => 'Context Probe (debug)',
				'tag'      => 'bws_ctx_probe',
				'type'     => 'post',
				'supports' => array(),
				'options'  => array(
					'note' => array(
						'type'  => 'text',
						'label' => 'Placement note',
						'help'  => 'Identifies this placement in the log, e.g. element-header, loop-item.',
					),
				),
				'return'   => 'bws_ctx_probe_callback',
			)
		);
	},
	20
);

/**
 * Snapshot every ambient-context signal at tag-render time.
 *
 * @param array  $options  Tag options.
 * @param object $block    Block.
 * @param object $instance Block instance (carries context).
 * @return string Visible placement marker.
 */
function bws_ctx_probe_callback( $options, $block, $instance ) {
	$note = $options['note'] ?? 'unlabeled';

	// --- Queried object summary (the factory's likeliest primary signal). ---
	$qo         = get_queried_object();
	$qo_summary = null;
	if ( $qo instanceof WP_Term ) {
		$qo_summary = array( 'WP_Term', $qo->term_id, $qo->taxonomy, $qo->name );
	} elseif ( $qo instanceof WP_Post ) {
		$qo_summary = array( 'WP_Post', $qo->ID, $qo->post_type, get_the_title( $qo ) );
	} elseif ( $qo instanceof WP_User ) {
		$qo_summary = array( 'WP_User', $qo->ID, $qo->display_name );
	} elseif ( $qo instanceof WP_Post_Type ) {
		$qo_summary = array( 'WP_Post_Type', $qo->name, $qo->labels->name ?? '' );
	} elseif ( null !== $qo ) {
		$qo_summary = array( get_class( $qo ) );
	}

	// --- GB instance context: keys always; values only when scalar. ---
	$instance_ctx = null;
	if ( is_object( $instance ) && isset( $instance->context ) && is_array( $instance->context ) ) {
		$instance_ctx = array();
		foreach ( $instance->context as $k => $v ) {
			$instance_ctx[ $k ] = ( is_scalar( $v ) || null === $v )
				? $v
				: '(' . ( is_object( $v ) ? get_class( $v ) : gettype( $v ) ) . ')';
		}
	}

	// --- Existing loop-row detection (precedence candidate #1). ---
	$loop_ctx = null;
	if ( function_exists( 'bws_get_loop_row_context' ) ) {
		$raw      = bws_get_loop_row_context( $instance );
		$loop_ctx = array(
			'in_loop'     => $raw['in_loop'],
			'row_post_id' => $raw['row_post_id'],
			'item_type'   => is_object( $raw['loop_item'] ) ? get_class( $raw['loop_item'] ) : gettype( $raw['loop_item'] ),
		);
	}

	$snapshot = array(
		'conditionals'   => array(
			'singular' => is_singular(),
			'category' => is_category(),
			'tag'      => is_tag(),
			'tax'      => is_tax(),
			'author'   => is_author(),
			'date'     => is_date(),
			'search'   => is_search(),
			'404'      => is_404(),
			'home'     => is_home(),
			'front'    => is_front_page(),
			'pta'      => is_post_type_archive(),
			'archive'  => is_archive(),
		),
		'queried_object' => $qo_summary,
		'queried_id'     => get_queried_object_id(),
		'post_global'    => isset( $GLOBALS['post']->ID )
			? array( $GLOBALS['post']->ID, $GLOBALS['post']->post_type )
			: null,
		'get_the_id'     => get_the_ID(),
		'in_the_loop'    => in_the_loop(),
		'is_main_query'  => isset( $GLOBALS['wp_query'], $GLOBALS['wp_the_query'] )
			&& $GLOBALS['wp_query'] === $GLOBALS['wp_the_query'],
		'query_vars'     => array(
			'year'      => get_query_var( 'year' ),
			'monthnum'  => get_query_var( 'monthnum' ),
			'day'       => get_query_var( 'day' ),
			's'         => get_query_var( 's' ),
			'paged'     => get_query_var( 'paged' ),
			'post_type' => get_query_var( 'post_type' ),
		),
		'hook_stack'     => $GLOBALS['wp_current_filter'] ?? array(),
		'loop_ctx'       => $loop_ctx,
		'instance_ctx'   => $instance_ctx,
		'is_rest'        => defined( 'REST_REQUEST' ) && REST_REQUEST,
		'is_admin'       => is_admin(),
		'page_for_posts' => (int) get_option( 'page_for_posts' ),
		'page_on_front'  => (int) get_option( 'page_on_front' ),
	);

	error_log( '[BWS_CTX] ' . $note . ' ' . wp_json_encode( $snapshot ) );

	return '[bws_ctx_probe ' . esc_html( $note ) . ']';
}
