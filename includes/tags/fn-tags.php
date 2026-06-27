<?php
/**
 * `{{call}}` function-passthrough dynamic tag.
 *
 * A FOURTH structural position beyond base / modifier / join-absorber
 * (CONTEXT.md §Tag structural vocabulary): post-context-in, opaque-string-out.
 * `{{call}}` reuses L1 post-resolution ONLY (binds the loop-correct post entity
 * via bws_resolve_post_by_source) then DELEGATES to an allowlisted, site-defined
 * PHP function, surfacing whatever string that function returns. There is no L2
 * resolve-field, no L2b fetch, no L3 assemble — no resolved field, no field
 * value; the output is opaque to the read pipeline (no list mode, no composite,
 * no analog, single string).
 *
 * DESIGN NON-GOAL — `{{call}}` is intentionally POST-CONTEXT-ONLY, NOT
 * source-agnostic like the standard base tags. It offers `src:current` +
 * `src:ref` ONLY; both resolve to a POST ID, exactly what a `$post_id`-contract
 * function consumes. `src:site` (a wp_options namespace) and `srcTermIn` (terms)
 * are deliberately NOT offered — neither is a post id, a `$post_id` function
 * cannot consume them, and they add no post-binding affordance (I4 source-level
 * gate). A future reader must NOT "fix" this by adding term/site sources: the
 * post binding is the entire purpose. The GB tag type is `'post'`, NOT
 * `'cross-source'` — `{{call}}` has none of the term/site/media/taxonomy editor
 * features the cross-source type implies.
 *
 * KNOWN LIMIT — `bws_resolve_post_by_source` resolves to a POST ID. Mode 2a
 * loops (relationship / post-object — the row IS a post) resolve and are the
 * driver. Mode 2b (flat ACF repeater, no row post entity) returns false for
 * `src:current`; there is no post to bind and the `$post_id` function contract
 * cannot consume a bag of row fields. Passing current-repeater-row FIELDS into a
 * function needs a different fn contract + a new src mode — a separate, deferred
 * design, NOT a bug in this release.
 *
 * SECURITY — `{{call}}` ships with an EMPTY allowlist and NO built-in functions.
 * It produces nothing until the site supplies both code (the function) and an
 * allowlist entry. The allowlist source of truth is the `bws_fn_passthrough_functions`
 * filter (file/code-access trust boundary only — no DB-write widening). The
 * execution gate is security-only: function_exists + ReflectionFunction::isInternal()
 * === false (blocks PHP builtins like system/exec/unlink). `{{call}}` grants
 * editors NO capability the developer did not already hold in PHP — a routing
 * convenience, not privilege escalation.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read + normalize the `{{call}}` function allowlist.
 *
 * Source of truth is the `bws_fn_passthrough_functions` filter, default EMPTY
 * (VC-allow). Storage is ASSOCIATIVE from v1 (`[ $fn => $meta ]`), so bulk/raw
 * `add_filter` callers may add bare-string entries (a list) and they are
 * normalized here to the associative shape (`'fn'` → `'fn' => []`). `$meta` is
 * stored but UNUSED in v1 (label / post_id_arg are v2). Normalizing on read
 * erases any future associative migration.
 *
 * @invariant VC-allow — filter is the source of truth, empty default,
 *   associative storage, bare-string entries normalized on read.
 *
 * @since 1.12.0
 * @return array<string,array> Map of function name => meta (meta unused in v1).
 */
function bws_call_get_allowlist(): array {
	$raw = apply_filters( 'bws_fn_passthrough_functions', array() );
	if ( ! is_array( $raw ) || empty( $raw ) ) {
		return array();
	}

	// A pure list of bare strings → normalize each to `name => []`.
	if ( array_is_list( $raw ) ) {
		$out = array();
		foreach ( $raw as $fn ) {
			if ( is_string( $fn ) && '' !== $fn ) {
				$out[ $fn ] = array();
			}
		}
		return $out;
	}

	// Associative (or mixed): keep string keys; coerce non-array meta to [].
	$out = array();
	foreach ( $raw as $fn => $meta ) {
		// A mixed array may carry bare-string entries under integer keys.
		if ( is_int( $fn ) ) {
			if ( is_string( $meta ) && '' !== $meta ) {
				$out[ $meta ] = array();
			}
			continue;
		}
		if ( is_string( $fn ) && '' !== $fn ) {
			$out[ $fn ] = is_array( $meta ) ? $meta : array();
		}
	}
	return $out;
}

/**
 * Security gate for a candidate `{{call}}` function — security-only, NOT a
 * contract check.
 *
 * Two checks (VC-gate):
 *   1. function_exists( $fn )
 *   2. ( new ReflectionFunction( $fn ) )->isInternal() === false — the HARD gate;
 *      blocks PHP builtins (system / exec / unlink / eval-likes), reducing the
 *      surface to site-defined functions.
 *
 * There is NO machine contract check: site functions are untyped, so reflection
 * cannot distinguish `bws_get_game_result($post_id)` from
 * `get_game_date_time_for_display($format)` (both untyped first param).
 * post_id-first is a DEVELOPER CONVENTION upheld when allowlisting, never
 * machine-verified. Run at registration (fail-fast) AND defensively at resolve.
 *
 * @invariant VC-gate — function_exists && ! isInternal; no contract check.
 *
 * @since 1.12.0
 * @param string $fn Candidate function name.
 * @return bool True if the function exists and is not a PHP builtin.
 */
function bws_call_passes_gate( string $fn ): bool {
	if ( '' === $fn || ! function_exists( $fn ) ) {
		return false;
	}
	try {
		$ref = new ReflectionFunction( $fn );
	} catch ( \ReflectionException $e ) {
		return false;
	}
	return ! $ref->isInternal();
}

/**
 * Register a function for `{{call}}` (developer registration sugar).
 *
 * A thin wrapper over the raw `add_filter( 'bws_fn_passthrough_functions', … )`
 * path (which STILL works for power users / bulk registration). Runs the
 * security gate at REGISTRATION time so a bad entry fails fast via
 * `_doing_it_wrong`, feeding the admin mirror rather than surfacing only at
 * render. `$meta` is accepted for forward-compat (label / post_id_arg are v2)
 * but UNUSED in v1.
 *
 * @invariant VC-gate — gate run at registration (and again at resolve).
 * @invariant VC-allow — appends associatively (`$fn => $meta`).
 *
 * @since 1.12.0
 * @param string $fn   Function name to allowlist.
 * @param array  $meta Forward-compat metadata (unused in v1).
 * @return bool True if registered; false (with _doing_it_wrong) if it fails the gate.
 */
function bws_register_call_function( string $fn, array $meta = array() ): bool {
	if ( ! function_exists( $fn ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html( "Function '$fn' not found." ), '1.12.0' );
		return false;
	}
	if ( ! bws_call_passes_gate( $fn ) ) {
		_doing_it_wrong( __FUNCTION__, esc_html( "Refusing built-in '$fn'." ), '1.12.0' );
		return false;
	}
	add_filter(
		'bws_fn_passthrough_functions',
		static function ( $list ) use ( $fn, $meta ) {
			if ( ! is_array( $list ) ) {
				$list = array();
			}
			$list[ $fn ] = $meta;
			return $list;
		}
	);
	return true;
}

/**
 * Build the source dropdown for `{{call}}` — post-yielding sources ONLY.
 *
 * Bespoke 2-value menu (`current` + `ref`); site/srcTermIn are simply never
 * offered (VC2). Not derived-and-filtered from bws_base_source_option() because
 * there is nothing to filter — only the two post-yielding values exist here.
 *
 * @since 1.12.0
 * @return array Single-entry array keyed 'src'.
 */
function bws_call_source_option(): array {
	return array(
		'src' => array(
			'type'           => 'select',
			'label'          => __( 'Source', 'generateblocks' ),
			'options'        => array(
				array( 'value' => 'current', 'label' => __( 'Current', 'generateblocks' ) ),
				array( 'value' => 'ref',     'label' => __( 'In Reference/Relational Field', 'generateblocks' ) ),
			),
			'_strip_default' => true,
		),
	);
}

/**
 * Build the `fn:` select options from the current allowlist.
 *
 * The select is POPULATED IN PHP at registration time (VC-select) — no JS is
 * involved (the conditional-options JS only does show/hide). v1 uses the raw
 * function name as both value and label; pretty labels ride the v2 `$meta` flip.
 *
 * @since 1.12.0
 * @return array[] GB select option rows ([ 'value' => fn, 'label' => fn ]).
 */
function bws_call_fn_select_options(): array {
	$opts = array(
		// Empty leading row so an unconfigured tag has no implicit function.
		array( 'value' => '', 'label' => __( '— Select a function —', 'generateblocks' ) ),
	);
	foreach ( array_keys( bws_call_get_allowlist() ) as $fn ) {
		$opts[] = array( 'value' => $fn, 'label' => $fn );
	}
	return $opts;
}

/**
 * Register the `{{call}}` tag.
 *
 * @invariant VC1 — L1 post-resolution only; opaque single-string output.
 * @invariant VC2 — type 'post' (NOT cross-source); src current/ref only.
 * @invariant VC-select — `fn:` is a select populated from the allowlist.
 *
 * @since 1.12.0
 */
function bws_register_call_tag(): void {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'    => __( 'Call Custom Function', 'generateblocks' ),
		'tag'      => 'call',
		'type'     => 'post',
		'supports' => array(),
		'options'  => bws_strip_default_select_values( array_merge(
			bws_call_source_option(),
			array(
				'ref'      => array(
					'type'        => 'text',
					'label'       => __( 'Relationship Field Key', 'generateblocks' ),
					'help'        => __( 'ACF relationship or post object field key (the related post the function runs on).', 'generateblocks' ),
					'placeholder' => 'related_posts',
					'show_if'     => array( 'src' => 'ref' ),
				),
				'fn'       => array(
					'type'           => 'select',
					'label'          => __( 'Function', 'generateblocks' ),
					'help'           => __( 'Allowlisted PHP function to call. Add functions via the bws_fn_passthrough_functions filter or bws_register_call_function(); see the diagnostics list under the BWS Dynamic Tags settings.', 'generateblocks' ),
					'options'        => bws_call_fn_select_options(),
					'_strip_default' => true,
				),
				'arg'      => array(
					'type'        => 'text',
					'label'       => __( 'Argument', 'generateblocks' ),
					'help'        => __( 'Optional single argument passed to the function (e.g. a format like "short" or "Y-m-d"). Left empty, the function\'s own default applies.', 'generateblocks' ),
					'placeholder' => 'short',
				),
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback', 'generateblocks' ),
					'help'  => __( 'Text to output if the function is unavailable, returns nothing, or errors.', 'generateblocks' ),
				),
			)
		) ),
		'return'   => 'bws_call_callback',
	) );
}

/**
 * Build the positional argument list for the call.
 *
 * post_id is ALWAYS position 0 (hardcoded v1, VC-arg). The single `arg:` option
 * is appended as position 1 ONLY when non-empty (sanitized), so an absent arg
 * lets the function's OWN default fire (e.g. `$format = 'full'`). Pure — no
 * WP/GB symbols beyond sanitize_text_field (shimmed in the test harness).
 *
 * @invariant VC-arg — post_id pos-0; sanitized single arg appended only when
 *   non-empty; tag-level pid: does not exist.
 *
 * @since 1.12.0
 * @param int   $post_id Resolved post id (position 0).
 * @param array $options Tag options (reads `arg`).
 * @return array Positional argument list.
 */
function bws_call_build_args( int $post_id, array $options ): array {
	$args = array( $post_id );
	if ( isset( $options['arg'] ) && '' !== $options['arg'] ) {
		$args[] = sanitize_text_field( (string) $options['arg'] );
	}
	return $args;
}

/**
 * Callback for the `{{call}}` tag.
 *
 * Pipeline: resolve loop-correct post id (L1 only) → security-gate the chosen
 * function (bucket A) → build args (post_id pos-0 + optional arg) → call inside
 * a Throwable catch (#6) → surface the returned string VERBATIM and UNESCAPED
 * (VC3). Any failure resolves to `fallback`.
 *
 * Failure taxonomy (VC-fail), 3 buckets:
 *   Bucket A — fn not in allowlist / function_exists false / fails isInternal →
 *     fallback (+ editor ⚠ warning via the inert preview; config/safety drift).
 *   Bucket B — post unresolvable / non-string-or-empty return → fallback, silent
 *     (legitimate data-absence).
 *   #6       — function throws/fatals → catch \Throwable, ALWAYS error_log
 *     (never debug-gated), fallback output, exception message NEVER reaches the
 *     page (no leaking internals/paths). The catch exists because of the opacity
 *     (no base tag try/catches a field read).
 *
 * @invariant VC1  — L1 post-resolution only; opaque single string.
 * @invariant VC3  — returned string surfaced verbatim + UNESCAPED; the function
 *   owns its own escaping (the allowlist is the trust boundary).
 * @invariant VC-gate — gate re-checked here (defensively).
 * @invariant VC-arg  — args built via bws_call_build_args (post_id pos-0).
 * @invariant VC-fail — 3-bucket fallback; Throwable caught + always logged; the
 *   exception message never returned.
 *
 * @since 1.12.0
 * @param array  $options  Tag options.
 * @param object $block    Block instance (unused).
 * @param object $instance GB tag instance.
 * @return string Function output (verbatim), or the fallback.
 */
function bws_call_callback( $options, $block, $instance ): string {
	$options    = (array) $options;
	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );
	$fallback   = (string) ( $options['fallback'] ?? '' );

	// Inert preview — NEVER execute the function (VC-inert). Describe the config.
	if ( $is_preview ) {
		return function_exists( 'bws_build_preview_label' )
			? bws_build_preview_label( $options, 'call' )
			: '';
	}

	$fn = (string) ( $options['fn'] ?? '' );

	// Bucket A — config/safety drift: not allowlisted, missing, or a builtin.
	$allowlist = bws_call_get_allowlist();
	if ( '' === $fn || ! isset( $allowlist[ $fn ] ) || ! bws_call_passes_gate( $fn ) ) {
		return $fallback;
	}

	// L1 ONLY — bind the loop-correct post entity. Bucket B (silent) on failure.
	$post_id = bws_resolve_post_by_source( $options, $instance );
	if ( ! $post_id ) {
		return $fallback;
	}

	$args = bws_call_build_args( (int) $post_id, $options );

	// #6 — opaque call guarded; the exception message never reaches the page.
	try {
		$out = call_user_func_array( $fn, $args );
	} catch ( \Throwable $e ) {
		error_log( sprintf( 'bws {{call}}: %s threw: %s', $fn, $e->getMessage() ) );
		return $fallback;
	}

	// Bucket B — non-string or empty return → fallback (silent).
	if ( ! is_string( $out ) || '' === $out ) {
		return $fallback;
	}

	// VC3 — verbatim, UNESCAPED. The function owns its own escaping.
	return $out;
}
