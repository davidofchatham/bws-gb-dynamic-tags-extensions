<?php
/**
 * Editor preview label helpers.
 *
 * Functions that build bracket-style preview labels shown in the block editor
 * when a tag resolves empty. Covers base, modifier, and try_ tags across all
 * templates (text, content, image, title, permalink, datetime_*).
 *
 * @package BWS_Dynamic_Tags
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wrap a preview label bracket string with link annotation and optional <a> for editor display.
 *
 * Appends "(link: permalink)" or "(link: 'key')" inside the brackets, then wraps the
 * whole string in <a href="#"> so the editor user sees both the annotation and the link
 * treatment. Only fires when linkTo is set (non-empty, non-'none'). Image templates pass
 * no linkTo so this is a no-op for them.
 *
 * @since 1.7.0
 * @param string $bracket_label Already-assembled bracket string e.g. "[Title from Term]".
 * @param array  $options       Tag options array (reads linkTo, linkKey, newTab).
 * @return string Annotated and wrapped label, or original string unchanged.
 */
if ( ! function_exists( 'bws_wrap_preview_label_with_link' ) ) {
function bws_wrap_preview_label_with_link( string $bracket_label, array $options ): string {
	if ( '' === $bracket_label ) {
		return $bracket_label;
	}
	$link_to  = $options['linkTo'] ?? 'none';
	$link_key = trim( $options['linkKey'] ?? '' );
	$new_tab  = ! empty( $options['newTab'] );

	if ( 'none' === $link_to || '' === $link_to ) {
		return $bracket_label;
	}

	// Build annotation suffix.
	if ( 'key' === $link_to ) {
		$annotation = '' !== $link_key ? "(link: '" . esc_html( $link_key ) . "')" : '(link: key)';
	} else {
		$annotation = '(link: ' . esc_html( $link_to ) . ')';
	}

	// Inject annotation before closing bracket.
	if ( str_ends_with( $bracket_label, ']' ) ) {
		$inner   = substr( $bracket_label, 1, -1 );
		$labeled = '[' . $inner . ' ' . $annotation . ']';
	} else {
		$labeled = $bracket_label . ' ' . $annotation;
	}

	// Wrap in <a href="#">.
	$attrs = ' href="#"';
	if ( $new_tab ) {
		$attrs .= ' target="_blank" rel="noopener noreferrer"';
	}
	return '<a' . $attrs . '>' . $labeled . '</a>';
}
}

/**
 * Build a structured editor preview label for the {{join}} combining tag.
 *
 * join is the standalone COMBINING tag (see join-helpers.php): it absorbs up to
 * BWS_JOIN_MAX_SLOTS base `text` reads as slots and assembles ALL non-empty
 * values into one string (separator or template mode). At editor time the fields
 * rarely exist on the editing context, so — like every other base tag — the
 * callback shows this configuration preview instead of an empty block.
 *
 * Mirrors the base/try_ marker conventions (docs/editor-tag-previews.md):
 *   [Join {field list}]                       — separator mode, default sep
 *   [Join {field list} (sep: “X”)]            — separator mode, custom sep
 *   [Join “'first' ('last')”]                 — template mode: format quoted with
 *                                               %N substituted by slot field
 *                                               parts (+ inline ` from {src}`
 *                                               on non-current slots); unbound
 *                                               %N stays literal
 *   [⚠ Join: {warnings}]                      — misconfigured slot(s) / no format
 * Trailing ` (fallback: “X”)` appended when `fallback` is set.
 *
 * Slot walk goes through the SAME seam bws_join_callback() renders through
 * (bws_fold_slot_struct + bws_fold_slot_flat_options), so it reads folded and legacy
 * wire alike and cannot drift from the renderer. It used to hold its own transcription
 * of the skip rule and the carry-forward, which is exactly the copy that made "the
 * preview matches the callback" a claim rather than a property. No link-wrap (join
 * composes raw values).
 *
 * @since 1.15.0
 * @since 1.17.0 Reads through the folded-slot seam (FW-56/57) instead of its own copy
 *               of join's slot walk.
 * @param array $options Parsed tag options (folded slot keys `A`,`B`,… or the legacy
 *                       flat per-slot keys; tag-level mode/valueSep/format/fallback).
 * @return string Bracket preview label, or '' when nothing is configured.
 */
if ( ! function_exists( 'bws_build_join_preview_label' ) ) {
function bws_build_join_preview_label( array $options ): string {
	$max      = defined( 'BWS_JOIN_MAX_SLOTS' ) ? BWS_JOIN_MAX_SLOTS : 10;
	$mode     = $options['mode'] ?? '';
	$format   = $options['format'] ?? '';
	$sep      = $options['valueSep'] ?? '';
	$fallback = $options['fallback'] ?? '';

	// Walk slots 1..max through the render seam. A slot the seam skips (unconfigured,
	// or a chain the flat read cannot express) contributes nothing here either.
	$field_parts  = array();
	$source_parts = array();
	$warnings     = array();
	$carry        = array( 'src' => '', 'ref' => '', 'use' => '', 'key' => '' );
	for ( $n = 1; $n <= $max; $n++ ) {
		$slot = function_exists( 'bws_fold_slot_struct' ) ? bws_fold_slot_struct( $n, $options, 'join' ) : null;
		if ( null === $slot ) {
			continue;
		}
		$skip = '';
		$flat = bws_fold_slot_flat_options( $slot, $carry, true, $skip );
		if ( null === $flat ) {
			// An UNCONFIGURED slot is a normal in-progress state and says nothing.
			// A slot whose CHAIN has no flat spelling will never render, so it is
			// flagged — otherwise the preview silently omits a slot the author
			// configured, and reads as if the tag were one slot smaller.
			if ( 'chain' === $skip ) {
				$warnings[] = 'slot ' . $n . ' source not supported';
			}
			continue;
		}

		$eff_src = '' === $flat['src'] ? 'current' : $flat['src'];
		$eff_use = $flat['use'];   // Already defaulted to `key` by the seam (I3).
		$key     = $flat['key'];

		// Per-slot warnings: src:ref with no ref key; key-mode with no key.
		if ( 'ref' === $eff_src && '' === $flat['ref'] ) {
			$warnings[] = 'slot ' . $n . ' no ref';
		}
		if ( 'title' !== $eff_use && '' === $key ) {
			$warnings[] = 'slot ' . $n . ' no key';
		}

		$field_parts[ $n ]  = bws_try_preview_field_part( 'text', $eff_use, $key, '' );
		$source_parts[ $n ] = bws_try_preview_source_part( $eff_src, $flat['ref'], $flat['srcTermIn'], true );
	}

	// Template mode with no format is unresolvable — warn (matches the callback
	// returning '' for template+empty-format).
	if ( 'template' === $mode && '' === $format ) {
		$warnings[] = 'no format set';
	}

	// Nothing configured at all → no preview (GB shows its own placeholder).
	if ( empty( $field_parts ) && empty( $warnings ) ) {
		return '';
	}

	if ( ! empty( $warnings ) ) {
		$inner = '⚠ Join: ' . implode( ', ', $warnings );
		if ( '' !== $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
	}

	// Assemble per-slot display parts, keyed by slot number. Sources appended
	// per-slot only when a slot actually binds to a non-current source (join
	// slots frequently share the current entity — no noise then).
	$parts = array();
	foreach ( $field_parts as $n => $fp ) {
		$src_segment = $source_parts[ $n ];
		if ( '' !== $src_segment && 'Current' !== $src_segment ) {
			$fp .= ' from ' . $src_segment;
		}
		$parts[ $n ] = $fp;
	}

	if ( 'template' === $mode ) {
		// Format string shown with %N tokens substituted by their slot's field
		// part (source annotation inline) — the author reads structure and
		// bindings in one string. Unbound %N stays literal (visible mistake,
		// matches render). ~…~ group delimiters shown as typed.
		$inner = 'Join “' . bws_join_preview_format( $format, $parts, $max ) . '”';
	} else {
		$inner = 'Join ' . implode( ', ', $parts );
		// Custom separator noted; the default ', ' is unremarkable, so omit it.
		if ( '' !== $sep ) {
			$inner .= ' (sep: “' . $sep . '”)';
		}
	}

	if ( '' !== $fallback ) {
		$inner .= ' (fallback: “' . $fallback . '”)';
	}
	return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
}
}

/**
 * Substitute wire tokens in a join format string with per-slot display parts, for the
 * template-mode preview label.
 *
 * BOTH token alphabets are read, exactly as bws_join_wire_format() reads them: `%A`
 * canonical since 1.17.0 (tokens follow the slot KEY spelling), `%1` accepted forever
 * (both collapse to one internal token, so nothing downstream can tell them apart).
 * A preview that understood only one would show an author their own format string with
 * half its tokens unsubstituted.
 *
 * `%%` is protected (shown as typed) with the sentinel trick from
 * bws_join_wire_format(); high→low so a multi-character token matches before its own
 * prefix. A token with no configured slot stays literal — the misconfiguration is
 * visible and matches what render does.
 *
 * @since 1.15.0
 * @since 1.17.0 Reads `%A` alongside `%1`.
 * @param string $format Author-written wire format.
 * @param array  $parts  Slot-number-keyed display parts (field + optional src).
 * @param int    $max    Slot cap (BWS_JOIN_MAX_SLOTS).
 * @return string Display format.
 */
if ( ! function_exists( 'bws_join_preview_format' ) ) {
function bws_join_preview_format( string $format, array $parts, int $max ): string {
	$format = str_replace( '%%', "\x00", $format );
	for ( $n = $max; $n >= 1; $n-- ) {
		if ( isset( $parts[ $n ] ) ) {
			$format = str_replace( '%' . bws_slot_ordinal( $n ), $parts[ $n ], $format );
			$format = str_replace( '%' . $n, $parts[ $n ], $format );
		}
	}
	return str_replace( "\x00", '%%', $format );
}
}

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
	// Image `as` may carry a folded `,<size>` arg (as+size fold, FW-52) — read the
	// bare return MODE for the exclusion test. Datetime/other `as` has no size fold.
	$as       = ( 'image' === $base_template && function_exists( 'bws_parse_as_option' ) )
		? bws_parse_as_option( $options )['mode']
		: ( $options['as'] ?? '' );
	$fallback = $options['fallback'] ?? $options['fallback_text'] ?? '';

	// Image excluded for output-attribute modes (bracket string breaks attribute).
	if ( 'image' === $base_template && ! in_array( $as, [ 'alt', 'caption' ], true ) ) {
		return '';
	}

	// Permalink excluded — URL context, bracket string breaks <a href>.
	if ( 'permalink' === $base_template ) {
		return '';
	}

	// Per-template defaults (mirrors bws_build_preview_label). A non-empty default is
	// also exactly what "this template has a per-slot `use` axis" means: the three
	// per_slot_use templates are the three with a default read token.
	$use_defaults = array( 'text' => 'key', 'image' => 'key', 'content' => 'content' );
	$use_default  = $use_defaults[ $base_template ] ?? '';
	$per_slot_use = '' !== $use_default;

	// Walk slots 1-5 through the SAME render seam the callback resolves with
	// (bws_fold_slot_struct + bws_fold_slot_flat_options), so this preview reads folded
	// and legacy wire alike and cannot drift from what will actually render. The walk
	// this replaced was a private copy of the era rules, the skip rule and the
	// carry-forward — the third such copy, and the one most likely to go stale, since
	// nothing renders when it is wrong.
	$slots = [];
	// Slots the seam cannot express. Kept separate from $slots so a tag whose ONLY
	// slot is inexpressible reports the real reason instead of "no slots configured".
	$skips = [];
	$carry = array(
		'src' => '',
		'ref' => '',
		'use' => $use_default,
		'key' => '',
	);
	for ( $n = 1; $n <= 5; $n++ ) {
		$slot = function_exists( 'bws_fold_slot_struct' )
			? bws_fold_slot_struct( $n, $options, 'try', $per_slot_use )
			: null;
		if ( null === $slot ) {
			continue;
		}
		$skip = '';
		$flat = bws_fold_slot_flat_options( $slot, $carry, false, $skip );
		if ( null === $flat ) {
			// 'read' cannot happen here (an absent read INHERITS in a selecting
			// container); 'chain' is wire that will never render, so flag it.
			if ( 'chain' === $skip ) {
				$skips[] = 'slot ' . $n . ' source not supported';
			}
			continue;
		}

		$slots[] = [
			'n'   => $n,
			// The seam reports an unset source; the preview vocabulary names it.
			'src' => '' === $flat['src'] ? 'current' : $flat['src'],
			'ref' => $flat['ref'],
			'tax' => $flat['srcTermIn'],
			'key' => $flat['key'],
			'use' => $flat['use'],
		];
	}

	if ( empty( $slots ) ) {
		$inner = '⚠ Try: ' . ( empty( $skips ) ? 'no slots configured' : implode( ', ', $skips ) );
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
	}

	// Collect per-slot warnings. Seeded with the inexpressible-chain flags, which are
	// per-slot warnings from the same walk.
	$warnings = $skips;
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
		} elseif ( 'email' === $base_template || 'phone' === $base_template ) {
			// No `use` enum (single key-mode); a slot always needs a field key,
			// and there are no no-key values (try_use_no_key_values = []). #24.
			$needs_key = true;
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
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
			return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
	return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
		case 'email':
			return 'Email';
		case 'phone':
			return 'Phone';
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
		case 'email':
		case 'phone':
			return "'" . $key . "'";
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
		// One parse point (FW-2): the shared normalizer maps rangeSep/showCurrentYear/
		// showMidnight to the canonical keys bws_format_date_range() reads.
		$range_options = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $options, true )
				: $options;
		$formatted     = bws_format_date_range( $now, $end, $wp_format, $range_options );
	} else {
		$formatted = $now->format( $wp_format );
	}

	return $prefix . ' like “' . $formatted . '”';
}
}

/**
 * Build a structured preview label for a tag that returned empty in the editor.
 *
 * Schema: docs/editor-tag-previews.md (authoritative).
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
	// Image `as` may carry a folded `,<size>` arg (as+size fold, FW-52) — read the
	// bare return MODE for the exclusion test. Datetime/other `as` has no size fold.
	$as       = ( 'image' === $base_template && function_exists( 'bws_parse_as_option' ) )
		? bws_parse_as_option( $options )['mode']
		: ( $options['as'] ?? '' );
	$fallback = $options['fallback'] ?? '';

	// Image excluded for output-attribute modes (bracket string silently breaks the element).
	if ( 'image' === $base_template && ! in_array( $as, [ 'alt', 'caption' ], true ) ) {
		return '';
	}

	// Invalid combo: `src:site` on a rooting modifier (term_*, view_*). The src
	// dropdown filters site out (bws_filter_site_from_src), but a hand-typed
	// `src:site` slips the UI — the runtime guard then resolves EMPTY (a site read
	// is entity-blind, so it would only duplicate the unrooted base tag). Warn in
	// preview so the editor reflects the empty frontend, not a normal label. [#37]
	if ( '' !== $modifier_label && 'site' === $source_val ) {
		$inner = '⚠ Site source not valid on ' . $modifier_label . ' tag — use the base tag';
		if ( $fallback ) {
			$inner .= ' (fallback: “' . $fallback . '”)';
		}
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
	} elseif ( 'email' === $base_template && '' === $key ) {
		$missing[] = 'field key'; // Email key-required in every source (no analog).
	} elseif ( 'phone' === $base_template && '' === $key ) {
		$missing[] = 'field key'; // Phone key-required in every source (no analog).
	} elseif ( 'call' === $base_template && '' === ( $options['fn'] ?? '' ) ) {
		// {{call}} INERT preview (VC-inert) — never executes the function; describes
		// config only. A missing fn is the bucket-A drift case (VC-fail) surfaced as
		// a warning here. The live allowlist-membership warning is client-side (the
		// allowlist is JS-available); this PHP path catches the empty-fn case.
		$missing[] = 'function';
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
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
	// Base-tag `src:site` → 'Site' context segment (yields "… from Site"). Site has
	// no entity to hop from, so it never combines with ref/srcTermIn here; on a
	// rooting modifier it is already short-circuited to the invalid-combo warning
	// above, so this only fires for base tags. [#37 preview parity]
	if ( 'site' === $source_val && ! $modifier_label ) {
		$ctx_segments[] = 'Site';
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
			// One parse point (FW-2): the shared normalizer maps rangeSep/showCurrentYear/
			// showMidnight to the canonical keys bws_format_date_range() reads.
			$range_options = function_exists( 'bws_normalize_datetime_options' )
				? bws_normalize_datetime_options( $options, true )
				: $options;
			$formatted     = bws_format_date_range( $now, $end, $wp_format, $range_options );
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
		return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
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
		case 'email':
			$field_part = '' !== $key ? "Email: '" . $key . "'" : 'Email';
			break;
		case 'phone':
			$field_part = '' !== $key ? "Phone: '" . $key . "'" : 'Phone';
			break;
		case 'call':
			// INERT config-describing label (VC-inert): the function name, plus the
			// single arg in parentheses when set. Never the function's actual output.
			$fn_name    = $options['fn'] ?? '';
			$arg_val    = $options['arg'] ?? '';
			$field_part = 'Function: ' . $fn_name;
			if ( '' !== $arg_val ) {
				$field_part .= ' (' . $arg_val . ')';
			}
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
	return bws_wrap_preview_label_with_link( '[' . $inner . ']', $options );
}
}
