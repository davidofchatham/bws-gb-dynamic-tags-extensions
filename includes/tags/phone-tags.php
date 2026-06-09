<?php
/**
 * `{{phone}}` base dynamic tag.
 *
 * Outputs a stored phone number, by default wrapped in a `tel:` link. Reads the
 * number from a meta/option field via the standard field-read path, so it works
 * cross-source exactly like `email`/`text`:
 *   - src:site            → wp_options / ACF-options (via bws_site_read_option)
 *   - src:current / unset → post/term meta
 *   - src:ref / srcTermIn → traversed entity meta
 *
 * Unlike `email` (whose href is the address verbatim), the `tel:` href is REBUILT
 * from arbitrary stored formatting into a canonical dial value. The rebuild
 * preserves the author's own separators (model C): hyphens in the href appear
 * only where the author wrote a separator; bare-digit fields get no internal
 * hyphens. Country code resolves 2-tier (in-field `+`/`00` → global setting);
 * per-tag `cc:` is intentionally out of scope this release (strip-flag safety —
 * see SPEC §C / VP-strip).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the `phone` base tag.
 *
 * Clones the `email` base-tag option shape (source + traversal + key + list mode
 * + fallback) minus the email-only controls (`subject`), plus nothing tag-level:
 * country code + obfuscation-analog live in global settings, not per-tag.
 *
 * @invariant VP-vis — registers with native GB `visibility` `tagName NOT_IN
 *   ['a','button','img','picture']`, mirroring GB core `term_list` and the email
 *   tag. The default-ON `tel:` wrap emits an `<a>`, so nesting inside an
 *   anchor/button is invalid interactive markup and inside img/picture is text in
 *   a void/replaced element. Block-attribute gate, NOT the JS `show_if` gate.
 *
 * @since 1.10.0
 */
function bws_register_phone_tag(): void {
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
		'title'      => __( 'Phone', 'generateblocks' ),
		'tag'        => 'phone',
		'type'       => 'cross-source',
		'supports'   => array(),
		// VP-vis — mirror GB core term_list / email: hide on interactive/void elements.
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
					'help'        => __( 'ACF or meta field key holding the phone number. For src:site this is the wp_options / ACF-options key (supports dot-path).', 'generateblocks' ),
					'placeholder' => 'phone_field',
				),
				'noLink'   => array(
					// VP1 — inverted bare-key boolean. Absence = wrap (default-on);
					// present = plain text. Modeled as a presence flag because GB's
					// serializer drops `false`, so "default-on, serialize-when-off" is
					// reachable only via an inverted-name bare key.
					'type'  => 'checkbox',
					'label' => __( 'Disable phone link (plain text)', 'generateblocks' ),
					'help'  => __( 'Output the number as plain text instead of a tel: link.', 'generateblocks' ),
				),
			),
			array(
				// List mode only applies to the final traversal step (terms / related
				// posts). Scalar sources return one number — hide both.
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
				// Fallback last. A fallback PHONE NUMBER (not arbitrary text), like
				// {{email}} fallback = address. Normalized + wrapped like a real number
				// (VP4).
				'fallback' => array(
					'type'  => 'text',
					'label' => __( 'Fallback Phone Number', 'generateblocks' ),
					'help'  => __( 'Phone number to use if the field is empty or invalid (e.g. a main switchboard). Normalized like a stored number.', 'generateblocks' ),
				),
			)
		) ),
		'return'     => 'bws_phone_callback',
	) );
}

/**
 * Resolve the raw phone number(s) for the active source.
 *
 * Verbatim clone of bws_email_resolve_addresses: site reads route through the
 * allowlist-gated canonical option reader (bws_site_read_option); every other
 * source uses the standard field-read path (bws_read_field) after entity
 * resolution. Returns a list of raw strings (the caller validates/filters); list
 * mode is produced by the term/ref traversal.
 *
 * @since 1.10.0
 * @param array  $options  Tag options.
 * @param object $instance GB tag instance.
 * @return string[] Raw candidate number strings (unvalidated).
 */
function bws_phone_resolve_numbers( array $options, $instance ): array {
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
 * Strip a single leading trunk `0` from a flat national digit string.
 *
 * Isolated so the per-country rule table (deferred #3) can swap it. The single-0
 * heuristic is Europe-correct (UK `07911` → `7911`, applied when a country code
 * is prepended). It is WRONG for plans that keep the trunk 0 (e.g. Italy) — those
 * need the #3 table.
 *
 * Worked example: national `07911123456` (11 digits), CC about to be applied →
 * returns `7911123456` (10 digits); the caller shifts every author-separator
 * boundary left by 1 so the hyphen map stays aligned to the shortened string.
 *
 * @since 1.10.0
 * @param string $national_digits Flat national digit string (digits only).
 * @return array{0:string,1:int} [stripped string, number of digits removed (0 or 1)].
 */
function bws_phone_strip_trunk_zero( string $national_digits ): array {
	if ( '' !== $national_digits && '0' === $national_digits[0] ) {
		return array( substr( $national_digits, 1 ), 1 );
	}
	return array( $national_digits, 0 );
}

/**
 * Validate the final assembled digit count (loose length gate, VP4).
 *
 * Isolated so the per-country length tables (deferred #3) can swap it. The 7–15
 * window is E.164's max (15) plus a heuristic 7-digit floor. The floor is the
 * known false-reject for short codes / tiny-country numbers (→ #3).
 *
 * @since 1.10.0
 * @param string $digits Assembled digit string (CC + national, post-strip).
 * @return bool True if the length is plausible.
 */
function bws_phone_length_ok( string $digits ): bool {
	$n = strlen( $digits );
	return $n >= 7 && $n <= 15;
}

/**
 * Normalize a raw stored phone number into a `tel:` dial VALUE.
 *
 * Returns the dial value WITHOUT the `tel:` scheme prefix (the caller prepends
 * it). Non-empty = valid; '' = invalid (caller falls back). The value is `+`
 * (international) or bare national digits, hyphenated ONLY where the author wrote
 * separators (model C).
 *
 * Algorithm (SPEC §I T4 — 9 steps). All digit mutations operate on a flat digit
 * string; the author's separators are carried as a SET OF BOUNDARY POSITIONS
 * (digit index after which a hyphen is inserted) and shifted in lock-step with
 * every mutation so the hyphen map stays aligned:
 *
 *   1. Detect `+` / `00` international prefix → international, ignore $cc.
 *   2. Sever extension junk FIRST (x/ext/#/,/; after ≥7 digits) — preserved raw
 *      stored value, ignored this release (extension is deferred).
 *   3. Capture separator boundaries via preg_split('/\D+/', NO_EMPTY); parens
 *      break a group. count<=1 → bare-digit (no internal hyphens).
 *   4. Flatten to a digit string.
 *   5. Strip-leading-CC (VP-strip) when $stripCc + CC matches + ≥7 remain; shift
 *      boundaries left by the stripped length.
 *   6. Trunk-0 strip when a CC is applied; shift boundaries left by 1. National
 *      fallback (no CC) KEEPS the 0.
 *   7. Apply CC: prepend, add a boundary after the CC group, shift national
 *      boundaries right by strlen(cc).
 *   8. Reassemble: insert '-' at boundary positions; '+' iff international or CC.
 *   9. Length gate (VP4): 7–15 assembled digits else ''.
 *
 * @invariant VP-hyphen — hyphens follow the author's separators, never a locale
 *   guess. Bare digits → no internal hyphens. The +CC- boundary is always added.
 * @invariant VP3 — `+`/`00` are the ONLY international signals; no heuristic
 *   stripping of bare leading CC-digits except via $stripCc (VP-strip). No CC +
 *   not-international → national digits, no `+`.
 * @invariant VP-strip — leading-CC strip matches the GLOBAL CC only (the caller
 *   never passes a per-tag CC here), once, gated on ≥7 remaining.
 * @invariant VP4 — validity is the length gate on the final assembled digits; no
 *   group-shape policing.
 * @invariant VP-href-safe — the returned value is `+?[\d-]+` BY CONSTRUCTION:
 *   groups are digit-runs only and every non-digit is a discarded separator, so
 *   no raw field text survives into the value.
 *
 * @since 1.10.0
 * @param string $raw     Raw stored number (any formatting).
 * @param string $cc      Resolved global country code (digits only, no `+`), or ''.
 * @param bool   $stripCc Whether the global strip-leading-CC setting is ON.
 * @return string `tel:` dial value (no scheme prefix), or '' if invalid.
 */
function bws_phone_normalize_tel( string $raw, string $cc, bool $stripCc ): string {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return '';
	}

	$cc = preg_replace( '/\D/', '', $cc ); // defensive: digits only.

	// 1. International detection.
	$is_intl = false;
	if ( '' !== $raw && '+' === $raw[0] ) {
		$is_intl = true;
		$raw     = substr( $raw, 1 );
	} elseif ( 0 === strncmp( $raw, '00', 2 ) ) {
		$is_intl = true;
		$raw     = substr( $raw, 2 );
	}

	// 2. Sever extension junk: first x/ext/#/,/; once ≥7 digits have appeared.
	//    Walk char-by-char counting digits; cut at the delimiter after the floor.
	$digit_count = 0;
	$cut         = strlen( $raw );
	$len         = strlen( $raw );
	for ( $i = 0; $i < $len; $i++ ) {
		$ch = $raw[ $i ];
		if ( ctype_digit( $ch ) ) {
			$digit_count++;
			continue;
		}
		if ( $digit_count >= 7 ) {
			$lower = strtolower( substr( $raw, $i ) );
			if ( 'x' === $ch || '#' === $ch || ',' === $ch || ';' === $ch || 0 === strncmp( $lower, 'ext', 3 ) ) {
				$cut = $i;
				break;
			}
		}
	}
	$national_part = substr( $raw, 0, $cut );

	// 3. Capture author's separator boundaries (group lengths).
	$groups = preg_split( '/\D+/', $national_part, -1, PREG_SPLIT_NO_EMPTY );
	if ( empty( $groups ) ) {
		return '';
	}
	$has_structure = count( $groups ) > 1;

	// Boundary set: digit index (count of digits before the hyphen) after each
	// group except the last. e.g. groups [3,3,4] → boundaries {3,6}.
	$boundaries = array();
	if ( $has_structure ) {
		$acc = 0;
		for ( $g = 0; $g < count( $groups ) - 1; $g++ ) {
			$acc         += strlen( $groups[ $g ] );
			$boundaries[] = $acc;
		}
	}

	// 4. Flatten to a national digit string.
	$national = implode( '', $groups );

	$apply_cc = ! $is_intl && '' !== $cc;

	// 5. Strip-leading-CC (VP-strip) — global CC only, gated.
	if ( $stripCc && $apply_cc && '' !== $cc && 0 === strncmp( $national, $cc, strlen( $cc ) ) ) {
		$remaining = substr( $national, strlen( $cc ) );
		if ( strlen( $remaining ) >= 7 ) {
			$shift      = strlen( $cc );
			$national   = $remaining;
			$boundaries = bws_phone_shift_boundaries( $boundaries, -$shift );
		}
	}

	// 6. Trunk-0 strip when a CC is applied (intl already has its own digits; a
	//    malformed in-field `+44 0xxx` also gets the leading 0 dropped). National
	//    fallback keeps the 0.
	if ( $apply_cc || $is_intl ) {
		list( $national, $removed ) = bws_phone_strip_trunk_zero( $national );
		if ( $removed > 0 ) {
			$boundaries = bws_phone_shift_boundaries( $boundaries, -$removed );
		}
	}

	// 7. Apply CC: prepend + add boundary after the CC group + shift national
	//    boundaries right by strlen(cc). The +CC- boundary hyphen is added ONLY
	//    when the author wrote internal structure — a bare-digit national number
	//    stays hyphenless (VP-hyphen), so `9876543210`+CC → `+19876543210`, not
	//    `+1-9876543210`.
	$digits = $national;
	if ( $apply_cc ) {
		$cc_len     = strlen( $cc );
		$boundaries = bws_phone_shift_boundaries( $boundaries, $cc_len );
		if ( $has_structure ) {
			array_unshift( $boundaries, $cc_len ); // hyphen right after the CC.
		}
		$digits = $cc . $national;
	}

	// 9. Length gate (VP4) — on the final assembled digit string.
	if ( ! bws_phone_length_ok( $digits ) ) {
		return '';
	}

	// 8. Reassemble: insert '-' at boundary positions; '+' iff intl or CC applied.
	$value  = ( $is_intl || $apply_cc ) ? '+' : '';
	$value .= bws_phone_insert_hyphens( $digits, $boundaries );

	return $value;
}

/**
 * Shift a boundary set by a signed delta, dropping any that fall out of range.
 *
 * @since 1.10.0
 * @param int[] $boundaries Digit-index boundaries.
 * @param int   $delta      Signed shift.
 * @return int[] Shifted, still-positive boundaries (re-indexed).
 */
function bws_phone_shift_boundaries( array $boundaries, int $delta ): array {
	$out = array();
	foreach ( $boundaries as $b ) {
		$shifted = $b + $delta;
		if ( $shifted > 0 ) {
			$out[] = $shifted;
		}
	}
	return $out;
}

/**
 * Insert hyphens into a digit string at the given boundary positions.
 *
 * @since 1.10.0
 * @param string $digits     Digit string.
 * @param int[]  $boundaries Digit indices after which to place a hyphen.
 * @return string Hyphenated digit string.
 */
function bws_phone_insert_hyphens( string $digits, array $boundaries ): string {
	if ( empty( $boundaries ) ) {
		return $digits;
	}
	$set = array_flip( array_unique( $boundaries ) );
	$out = '';
	$n   = strlen( $digits );
	for ( $i = 0; $i < $n; $i++ ) {
		$out .= $digits[ $i ];
		// Boundary value = count of digits before the hyphen = index+1.
		if ( $i + 1 < $n && isset( $set[ $i + 1 ] ) ) {
			$out .= '-';
		}
	}
	return $out;
}

/**
 * Build one `tel:` anchor (or plain text) for a single raw number.
 *
 * @invariant VP2 — display is the stored value verbatim via esc_html(); only the
 *   href is normalized. Display and href may differ.
 * @invariant VP-href-safe — the href value is digits + boundary hyphens by
 *   construction; esc_attr() is defense-in-depth.
 *
 * @since 1.10.0
 * @param string $raw     Raw stored number.
 * @param string $cc      Resolved global country code (digits only), or ''.
 * @param bool   $link    Whether to wrap in a tel: anchor.
 * @param bool   $stripCc Whether the global strip-leading-CC setting is ON.
 * @return string HTML-safe output, or '' if the number is invalid.
 */
function bws_phone_render_one( string $raw, string $cc, bool $link, bool $stripCc ): string {
	$value = bws_phone_normalize_tel( $raw, $cc, $stripCc );
	if ( '' === $value ) {
		return ''; // VP4 — invalid never renders (caller skips / falls back).
	}

	$display = esc_html( trim( $raw ) );

	if ( ! $link ) {
		return $display;
	}

	$href = 'tel:' . $value;
	return '<a href="' . esc_attr( $href ) . '">' . $display . '</a>';
}

/**
 * Callback for the `phone` base tag.
 *
 * @invariant VP1 — tel: wrap is DEFAULT-ON; link-on iff the `noLink` bare key is
 *   ABSENT from options. Never a positive `link:true` default.
 * @invariant VP4 — validate-before-link: a number that does not normalize is
 *   SKIPPED, never rendered as plain text (strict this release). List mode wraps
 *   each valid number and joins by `sep`. Fallback fires ONLY when zero valid
 *   numbers resolve, then returns '' if it too is invalid. Normalization runs as
 *   the validity gate even in noLink mode.
 *
 * @since 1.10.0
 * @param array  $options  Tag options.
 * @param object $block    Block instance (unused).
 * @param object $instance GB tag instance.
 * @return string
 */
function bws_phone_callback( $options, $block, $instance ): string {
	// VP-vis runtime backstop — the native visibility gate can't catch the media
	// block (empty tagName); its default-on tel: <a> would corrupt the <img src>.
	// See bws_tag_blocked_on_media_block() / docs/gb-constraints.md.
	if ( bws_tag_blocked_on_media_block( $block ) ) {
		return '';
	}

	$is_preview = ! empty( $instance->context['bwsEditorPreview'] );

	$link    = empty( $options['noLink'] ); // VP1 — absent = wrap.
	$sep     = $options['sep'] ?? ', ';
	$cc      = bws_phone_default_cc();          // resolved ONCE.
	$stripCc = bws_phone_strip_leading_cc();    // resolved ONCE.

	// VP4 — render each candidate; skip those that don't normalize.
	$parts = array();
	foreach ( bws_phone_resolve_numbers( (array) $options, $instance ) as $candidate ) {
		$rendered = bws_phone_render_one( (string) $candidate, $cc, $link, $stripCc );
		if ( '' !== $rendered ) {
			$parts[] = $rendered;
		}
	}

	// Fallback fires only on a fully-empty valid set.
	if ( empty( $parts ) ) {
		$fallback = trim( (string) ( $options['fallback'] ?? '' ) );
		if ( '' !== $fallback ) {
			$rendered = bws_phone_render_one( $fallback, $cc, $link, $stripCc );
			if ( '' !== $rendered ) {
				$parts[] = $rendered;
			}
		}
	}

	if ( empty( $parts ) ) {
		return $is_preview && function_exists( 'bws_build_preview_label' )
			? bws_build_preview_label( (array) $options, 'phone' )
			: '';
	}

	return implode( $sep, $parts );
}

/**
 * Resolve the default country code (global setting, empty default).
 *
 * Thin guarded wrapper over the settings accessor so the callback can run even if
 * the admin class is not loaded (defaults to '' — national-only tel: links).
 *
 * @invariant VP3 — empty default; no assumed country.
 * @since 1.10.0
 * @return string Digits only, no `+`, or ''.
 */
function bws_phone_default_cc(): string {
	if ( class_exists( '\\BWS\\DynamicTags\\Admin\\SettingsPage' )
		&& method_exists( '\\BWS\\DynamicTags\\Admin\\SettingsPage', 'get_phone_country_code' )
	) {
		return \BWS\DynamicTags\Admin\SettingsPage::get_phone_country_code();
	}
	return '';
}

/**
 * Whether leading-country-code stripping is enabled (global setting, default OFF).
 *
 * @invariant VP-strip — default is OFF (false); opt-in only.
 * @since 1.10.0
 * @return bool
 */
function bws_phone_strip_leading_cc(): bool {
	if ( class_exists( '\\BWS\\DynamicTags\\Admin\\SettingsPage' )
		&& method_exists( '\\BWS\\DynamicTags\\Admin\\SettingsPage', 'is_phone_strip_leading_cc_enabled' )
	) {
		return \BWS\DynamicTags\Admin\SettingsPage::is_phone_strip_leading_cc_enabled();
	}
	return false;
}
