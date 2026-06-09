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
					'type'        => 'text',
					'label'       => __( 'Meta/Option Field', 'generateblocks' ),
					'help'        => __( 'ACF or meta field key holding the email address. For src:site this is the wp_options / ACF-options key (supports dot-path).', 'generateblocks' ),
					'placeholder' => 'email_field',
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
 * Resolve the raw email address(es) for the active source.
 *
 * Site reads route through the allowlist-gated canonical option reader
 * (bws_site_read_option); every other source uses the standard field-read path
 * (bws_read_field) after entity resolution. Returns a list of raw strings (the
 * caller validates/filters); list mode is produced by the term/ref traversal.
 *
 * @since 1.9.0
 * @param array  $options  Tag options.
 * @param object $instance GB tag instance.
 * @return string[] Raw candidate address strings (unvalidated).
 */
function bws_email_resolve_addresses( array $options, $instance ): array {
	$key = sanitize_text_field( $options['key'] ?? '' );
	if ( '' === $key ) {
		return array();
	}

	// src:site — wp_options / ACF-options, dot-path + allowlist gated. No entity.
	if ( 'site' === ( $options['src'] ?? '' ) ) {
		if ( ! function_exists( 'bws_site_read_option' ) ) {
			return array();
		}
		$value = bws_site_read_option( $key );
		return '' !== $value ? array( $value ) : array();
	}

	// Non-site dot-paths are not valid meta keys; gate like bws_post_custom_text_core.
	if ( ! bws_is_valid_meta_key( $key ) ) {
		return array();
	}

	$tax = sanitize_key( $options['srcTermIn'] ?? '' );

	// Term-hop list mode: read the field on each matching term.
	if ( '' !== $tax ) {
		$post_id = bws_resolve_post_by_source( $options, $instance );
		$terms   = bws_get_srcterm_terms( (int) $post_id, $tax );
		$limit   = max( 1, (int) ( $options['limit'] ?? 1 ) );
		$out     = array();
		foreach ( array_slice( $terms, 0, $limit ) as $term ) {
			$raw = bws_read_term_field( $key, (int) $term->term_id );
			if ( is_scalar( $raw ) && '' !== (string) $raw ) {
				$out[] = (string) $raw;
			}
		}
		return $out;
	}

	$post_id = bws_resolve_post_by_source( $options, $instance );
	$raw     = bws_read_field( $key, $instance, $post_id );
	return ( is_scalar( $raw ) && '' !== (string) $raw ) ? array( (string) $raw ) : array();
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

	$link      = empty( $options['noLink'] );            // VE1 — absent = wrap.
	$subject   = (string) ( $options['subject'] ?? '' ); // VE2 — already unescaped.
	$sep       = $options['sep'] ?? ', ';
	$obfuscate = bws_email_obfuscation_enabled();

	// VE4 — validate raw, keep only is_email()-valid addresses.
	$valid = array();
	foreach ( bws_email_resolve_addresses( (array) $options, $instance ) as $candidate ) {
		$candidate = trim( $candidate );
		if ( is_email( $candidate ) ) {
			$valid[] = $candidate;
		}
	}

	// Fallback fires only on a fully-empty valid set.
	if ( empty( $valid ) ) {
		$fallback = trim( (string) ( $options['fallback'] ?? '' ) );
		if ( '' !== $fallback && is_email( $fallback ) ) {
			$valid[] = $fallback;
		}
	}

	if ( empty( $valid ) ) {
		return $is_preview && function_exists( 'bws_build_preview_label' )
			? bws_build_preview_label( (array) $options, 'email' )
			: '';
	}

	$parts = array();
	foreach ( $valid as $address ) {
		$parts[] = bws_email_render_one( $address, $subject, $link, $obfuscate );
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
