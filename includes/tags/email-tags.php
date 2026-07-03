<?php
/**
 * `{{email}}` base dynamic tag.
 *
 * Outputs a stored email address, by default wrapped in a `mailto:` link with an
 * optional subject line. Reads the address from a meta/option field via the
 * standard field-read path, so it works cross-source exactly like `text`:
 *   - src:site            → wp_options / ACF-options (via bws_site_read_option)
 *   - src:current / unset → post/term meta
 *   - src:ref / srcTermIn → traversed entity meta
 *
 * Email is keyed-by-nature in every source (no intrinsic analog), so it has NO
 * `use` enum — `key` is always required. A future `use:author` / `use:admin`
 * enum is additive (gated by the C10 qualifying test) and intentionally out of
 * scope this release.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the `email` base tag.
 *
 * Clones the `text` base-tag option shape (source + traversal + key + list mode
 * + fallback) and adds the two email-specific controls (`subject`, `noLink`).
 *
 * @invariant VE3 — registers with native GB `visibility` `tagName NOT_IN
 *   ['a','button','img','picture']`, mirroring GB core's own `term_list`
 *   registration. The default-ON mailto wrap emits an `<a>`, so placing the tag
 *   inside an anchor/button is nested/invalid interactive markup and inside
 *   img/picture is text in a void/replaced element. This is the plugin's first
 *   `visibility` use; it is a block-attribute gate (NOT the JS `show_if` option
 *   gate). Removing or narrowing this list re-opens nested-anchor output.
 *
 * @since 1.9.0
 */
function bws_register_email_tag(): void {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}

	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	$source_opt     = bws_base_source_option();
	$traversal_opts = bws_base_traversal_options();

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'      => __( 'Email', 'generateblocks' ),
		'tag'        => 'email',
		'type'       => 'cross-source',
		'supports'   => array(),
		// VE3 — mirror GB core term_list: hide on interactive/void elements.
		'visibility' => array(
			'attributes' => array(
				array(
					'name'    => 'tagName',
					'value'   => array( 'a', 'button', 'img', 'picture' ),
					'compare' => 'NOT_IN',
				),
			),
		),
		'options'    => bws_strip_default_select_values( array_merge(
			$source_opt,
			$traversal_opts,
			array(
				'key'      => array(
					'type'         => 'bws-field-combo',
					'label'        => __( 'Meta/Option Field', 'generateblocks' ),
					'dynamicLabel' => true,
					'help'         => __( 'ACF or meta field key holding the email address.', 'generateblocks' ),
					'placeholder'  => 'email_field',
				),
				'subject'  => array(
					// VE2 — bws-format-input escapes `:`/`|` so the subject survives
					// GB's parseTag; GB's parse_options unescapes server-side before
					// the callback, which only rawurlencodes. Hidden when noLink (no
					// mailto query to carry a subject).
					'type'    => 'bws-format-input',
					'label'   => __( 'Subject', 'generateblocks' ),
					'help'    => __( 'Optional subject line for the mailto link.', 'generateblocks' ),
					'show_if' => array( 'noLink' => 'empty' ),
				),
				'noLink'   => array(
					// VE1 — inverted bare-key boolean. Absence = wrap (default-on);
					// present = plain text. Modeled as a presence flag because GB's
					// serializer drops `false`, so "default-on, serialize-when-off" is
					// reachable only via an inverted-name bare key.
					'type'  => 'checkbox',
					'label' => __( 'Disable email link (plain text)', 'generateblocks' ),
					'help'  => __( 'Output the address as plain text instead of a mailto: link.', 'generateblocks' ),
				),
			),
			array(
				// List mode only applies to the final traversal step (terms / related
				// posts). Scalar sources return one address — hide both.
				'limit'    => array(
					'type'        => 'number',
					'label'       => __( 'Result Limit', 'generateblocks' ),
					'help'        => __( 'Maximum number of results to return. Default: 1.', 'generateblocks' ),
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
				),
				'sep'      => array(
					'type'        => 'text',
					'label'       => __( 'Result Separator', 'generateblocks' ),
					'help'        => __( 'Text to place between results. Default: ", ".', 'generateblocks' ),
					'placeholder' => ', ',
					'show_if_any' => array( 'srcTermIn' => 'not_empty', 'src' => 'ref' ),
				),
				// Fallback last. A fallback EMAIL ADDRESS (not arbitrary text), like
				// {{image}} fallback = attachment ID. Validated + wrapped like a real
				// address (VE4).
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Email', 'generateblocks' ),
					'help'  => __( 'Email address to use if the field is empty or invalid (e.g. a department address). Validated as an email.', 'generateblocks' ),
				),
			)
		) ),
		'return'     => 'bws_email_callback',
	) );
}

/**
 * Build one anchor (or plain text) for a single, already-validated address.
 *
 * @since 1.9.0
 * @param string $address   Validated email address (is_email() true).
 * @param string $subject   Raw (already-unescaped) subject, or ''.
 * @param bool   $link      Whether to wrap in a mailto: anchor.
 * @param bool   $obfuscate Whether to antispambot() the address.
 * @return string HTML-safe output for this address.
 */
function bws_email_render_one( string $address, string $subject, bool $link, bool $obfuscate ): string {
	// VE4 — when obfuscating, antispambot() output is already entity-encoded for
	// BOTH display and href; it MUST NOT be passed through esc_html() (which would
	// double-encode `&#xNN;`). When not obfuscating, escape normally.
	$display = $obfuscate ? antispambot( $address ) : esc_html( $address );

	if ( ! $link ) {
		return $display;
	}

	$mailto_local = $obfuscate ? antispambot( $address ) : $address;
	$href         = 'mailto:' . $mailto_local;
	if ( '' !== $subject ) {
		// VE2 — subject arrives already unescaped from GB's parse_options; the only
		// render step is rawurlencode into the query. Do NOT unescape here.
		$href .= '?subject=' . rawurlencode( $subject );
	}

	return '<a href="' . esc_attr( $href ) . '">' . $display . '</a>';
}

/**
 * Callback for the `email` base tag.
 *
 * @invariant VE1 — mailto wrap is DEFAULT-ON; link-on iff the `noLink` bare key
 *   is ABSENT from options. Never model as a positive `link:true` default.
 * @invariant VE2 — `subject` is rawurlencode()d into the query at render ONLY;
 *   it arrives already unescaped (GB parse_options handled `\:`/`\|`). The
 *   callback MUST NOT unescape it again.
 * @invariant VE4 — validate-before-obfuscate: is_email() runs on the RAW value;
 *   only a valid address is ever wrapped. antispambot() output is terminal-
 *   escaped (never re-esc_html'd). Obfuscation is gated by the global
 *   `email.obfuscate` setting (default ON). List mode wraps each valid address
 *   individually and joins by `sep`. Fallback fires ONLY when zero valid
 *   addresses resolve (whole-result-empty), then returns '' if it too is invalid.
 *
 * @since 1.9.0
 * @param array  $options  Tag options.
 * @param object $block    Block instance (unused).
 * @param object $instance GB tag instance.
 * @return string
 */
function bws_email_callback( $options, $block, $instance ): string {
	// VE-vis runtime backstop — the native visibility gate can't catch the media
	// block (empty tagName); its default-on mailto: <a> would corrupt the <img src>.
	// See bws_tag_blocked_on_media_block() / docs/gb-constraints.md.
	if ( bws_tag_blocked_on_media_block( $block ) ) {
		return '';
	}

	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$sep = $options['sep'] ?? ', ';

	// L3 compose — validate + render each raw value via the shared finisher
	// (SAME per-item compose the try_ dispatchers use, VE4 / V10).
	$parts = bws_email_finish_values( bws_resolve_field_values( (array) $options, $instance ), (array) $options );

	// Fallback fires only on a fully-empty valid set — fed through the SAME
	// validate+compose so it wraps identically (VE4).
	if ( empty( $parts ) ) {
		$fallback = trim( (string) ( $options['fallback'] ?? '' ) );
		if ( '' !== $fallback ) {
			$parts = bws_email_finish_values( array( $fallback ), (array) $options );
		}
	}

	if ( empty( $parts ) ) {
		return $is_preview && function_exists( 'bws_build_preview_label' )
			? bws_build_preview_label( (array) $options, 'email' )
			: '';
	}

	return implode( $sep, $parts );
}

/**
 * Whether email-address obfuscation is enabled (global setting, default ON).
 *
 * Thin guarded wrapper over the settings accessor so the callback can run even
 * if the admin class is not loaded (defaults to obfuscating, matching the
 * accessor default).
 *
 * @invariant VE4 — default is ON (true); the global only ever DISABLES.
 * @since 1.9.0
 * @return bool
 */
function bws_email_obfuscation_enabled(): bool {
	if ( class_exists( '\\BWS\\DynamicTags\\Admin\\SettingsPage' )
		&& method_exists( '\\BWS\\DynamicTags\\Admin\\SettingsPage', 'is_email_obfuscation_enabled' )
	) {
		return \BWS\DynamicTags\Admin\SettingsPage::is_email_obfuscation_enabled();
	}
	return true;
}

/**
 * Compose a list of raw candidate addresses into finished mailto/plain strings.
 *
 * The L3 assembly step shared by the base callback and the try_ dispatchers:
 * validate each raw value (VE4 is_email), then bws_email_render_one per valid
 * address (the SAME per-item compose the base {{email}} callback uses — subject,
 * obfuscation, mailto wrap). Returns finished per-item strings; the caller joins
 * (base via implode, try_ via the seam). NO fallback here — fallback is the
 * caller's concern (base: whole-result-empty; try_: chain-level next slot).
 *
 * @since 1.11.0
 * @param string[] $raw     Raw candidate address strings (from bws_resolve_field_values).
 * @param array    $options Tag options (noLink, subject).
 * @return string[] Finished per-item strings (already wrapped/escaped).
 */
function bws_email_finish_values( array $raw, array $options ): array {
	$link      = empty( $options['noLink'] );            // VE1 — absent = wrap.
	$subject   = (string) ( $options['subject'] ?? '' ); // VE2 — already unescaped.
	$obfuscate = bws_email_obfuscation_enabled();

	$out = array();
	foreach ( $raw as $candidate ) {
		$candidate = trim( (string) $candidate );
		if ( is_email( $candidate ) ) { // VE4 — validate raw before compose.
			$out[] = bws_email_render_one( $candidate, $subject, $link, $obfuscate );
		}
	}
	return $out;
}

/**
 * Try-tag post-slot dispatch for the `email` template (try_core_fn).
 *
 * Returns finished mailto/plain address strings for the slot (CONTEXT.md I6 — the
 * try_ machinery joins; this produces the per-item finished strings). Honors the
 * registry-resolved $post_id (loop-row override, feedback_loop_context_override)
 * for post/term sources; src:site reads the option (no entity, $post_id ignored).
 *
 * @since 1.11.0
 * @param int|false $post_id  Registry-resolved entity id (0/false for src:site).
 * @param array     $options  Slot options (src, key, srcTermIn, noLink, subject).
 * @param object    $instance GB tag instance.
 * @return string[] Finished per-item strings.
 */
function bws_try_email_post_dispatch( $post_id, $options, $instance ) {
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		return bws_email_finish_values( bws_resolve_field_values( (array) $options, $instance ), (array) $options );
	}

	$key = sanitize_text_field( $options['key'] ?? '' );
	if ( '' === $key || ( function_exists( 'bws_is_valid_meta_key' ) && ! bws_is_valid_meta_key( $key ) ) ) {
		return array();
	}
	$raw = bws_read_field( $key, $instance, $post_id );
	$raw = ( is_scalar( $raw ) && '' !== (string) $raw ) ? array( (string) $raw ) : array();
	return bws_email_finish_values( $raw, (array) $options );
}

/**
 * Modifier post-source reader for the `email` template (post_fn).
 *
 * Reads the field off $post_id, composes finished mailto/plain strings, joins by
 * sep — the string contract make_modifier_callback expects (term_email at
 * src:ref). Mirrors bws_try_email_post_dispatch's post branch but returns a
 * joined string instead of the try_ array.
 *
 * @since 1.11.0
 * @return string Joined finished addresses, or '' on empty.
 */
function bws_email_post_core( $post_id, $options, $instance ): string {
	$sep   = $options['sep'] ?? ', ';
	$parts = bws_try_email_post_dispatch( $post_id, $options, $instance );
	return implode( $sep, $parts );
}

/**
 * Modifier term-source reader for the `email` template (term_fn).
 *
 * Reads the field off $term_id (the term entity at src:current, or a srcTermIn
 * hop), composes + joins. String contract for make_modifier_callback.
 *
 * @since 1.11.0
 * @return string Joined finished addresses, or '' on empty.
 */
function bws_email_term_core( $term_id, $options, $instance ): string {
	$sep   = $options['sep'] ?? ', ';
	$parts = bws_try_email_term_dispatch( $term_id, $options, $instance );
	return implode( $sep, $parts );
}

/**
 * Register the `email` modifier TEMPLATE descriptor (not the standalone {{email}}
 * GB tag — that is bws_register_email_tag). Called from bws_register_base_tags()
 * BEFORE register_modifier(prefix=term) so term_email falls out, and before
 * generate_base_try_tags() so try_email falls out. [SPEC §32 T8/T11]
 *
 * @since 1.11.0
 */
function bws_register_email_template(): void {
	if ( ! class_exists( '\\BWS\\DynamicTags\\TagTemplateRegistry' ) ) {
		return;
	}
	\BWS\DynamicTags\TagTemplateRegistry::register_modifier_template( array(
		'key'                 => 'email',
		'title'               => __( 'Email', 'generateblocks' ),
		'options'             => array(
			'key'      => array(
				'type'         => 'bws-field-combo',
				'label'        => __( 'Meta/Option Field', 'generateblocks' ),
				'dynamicLabel' => true,
				'help'         => __( 'ACF or meta field key holding the email address.', 'generateblocks' ),
				'placeholder'  => 'email_field',
			),
			'subject'  => array(
				'type'    => 'bws-format-input',
				'label'   => __( 'Subject', 'generateblocks' ),
				'help'    => __( 'Optional subject line for the mailto link.', 'generateblocks' ),
				'show_if' => array( 'noLink' => 'empty' ),
			),
			'noLink'   => array(
				'type'  => 'checkbox',
				'label' => __( 'Disable email link (plain text)', 'generateblocks' ),
				'help'  => __( 'Output the address as plain text instead of a mailto: link.', 'generateblocks' ),
			),
			'fallback' => array(
				'type'  => 'text',
				'label' => __( 'Fallback Email', 'generateblocks' ),
				'help'  => __( 'Email address to use if the field is empty or invalid (e.g. a department address). Validated as an email.', 'generateblocks' ),
			),
		),
		// VE3/VP-vis — hide on interactive/void elements (threaded to term_/try_ tags).
		'visibility'          => array(
			'attributes' => array(
				array(
					'name'    => 'tagName',
					'value'   => array( 'a', 'button', 'img', 'picture' ),
					'compare' => 'NOT_IN',
				),
			),
		),
		'term_fn'             => 'bws_email_term_core',
		'post_fn'             => 'bws_email_post_core',
		'try_core_fn'         => 'bws_try_email_post_dispatch',
		'try_term_fn'         => 'bws_try_email_term_dispatch',
		'supports_try'        => true,
		'try_per_slot_key'    => true,
		'try_per_slot_use'    => false,
		'try_use_no_key_values' => array(),
		'try_list_options'    => true,
		'try_allow_site_slot' => true,
		'try_media_block_guard' => true,
		'is_image'            => false,
	) );
}

/**
 * Try-tag srcTermIn-slot dispatch for the `email` template (try_term_fn).
 *
 * Reads the field off the given term and composes it. The registry term arm calls
 * this once per term and collects the results into the slot's list.
 *
 * @since 1.11.0
 * @param int    $term_id  Term id supplied by the registry srcTermIn arm.
 * @param array  $options  Slot options (key, noLink, subject).
 * @param object $instance GB tag instance.
 * @return string[] Finished per-item strings for this term.
 */
function bws_try_email_term_dispatch( $term_id, $options, $instance ) {
	$key = sanitize_text_field( $options['key'] ?? '' );
	if ( '' === $key || ( function_exists( 'bws_is_valid_meta_key' ) && ! bws_is_valid_meta_key( $key ) ) ) {
		return array();
	}
	$raw = bws_read_term_field( $key, (int) $term_id );
	$raw = ( is_scalar( $raw ) && '' !== (string) $raw ) ? array( (string) $raw ) : array();
	return bws_email_finish_values( $raw, (array) $options );
}
