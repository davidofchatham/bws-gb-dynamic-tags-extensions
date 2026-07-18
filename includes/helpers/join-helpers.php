<?php
/**
 * {{join}} helpers — slot absorb seam + assembly + the pure template/collapse
 * algorithm.
 *
 * join is the standalone COMBINING tag (third structural position — neither a
 * base tag nor a modifier): it absorbs up to BWS_JOIN_MAX_SLOTS base `text`
 * reads as slots and assembles their resolved values into one output string.
 * The collect-all loop lives in bws_join_callback() (base-tags.php); this file
 * holds the per-slot absorb seam and everything downstream of the collected
 * values.
 *
 * ABSORB INVARIANT (CONTEXT.md I-absorb): a join slot resolves EXACTLY as a
 * standalone {{text}} would — bws_join_resolve_slot() delegates to
 * bws_base_text_resolve_value() and never re-decides value emptiness. "Empty"
 * is exactly '' everywhere in this file; a stored '0' is a REAL value and is
 * kept (the base text '0' hook, hooks.php, is absorbed — no coercion here).
 *
 * bws_join_assemble() / bws_join_separator() / bws_join_template() /
 * bws_join_remove_empty_token() / bws_join_strip_connective_separators() are
 * PURE string functions (no WP/GB symbols) — harnessed locally by
 * tools/test/join-template-test.php (house pattern: fns copied inline there;
 * keep both in sync).
 *
 * Template-mode smart literal removal (five ordered steps on empty-token
 * positions — docs/tag-reference.md §{{join}}):
 *   1. Attached punctuation sheds with the empty token. Split by punctuation
 *      class: UNIT punct (. ' ") directly attached AFTER the token always
 *      sheds with it ({3}'{4}" with empty {4} → the dangling " dies);
 *      CONNECTIVE punct (, :) collapses only when the empty token sits BETWEEN
 *      two connectives (the leading one is consumed — {last}, {gen}, {cred}
 *      with empty {gen} keeps ONE comma); a single-sided connective survives
 *      as the separator between the remaining neighbors and is repaired by
 *      Step 4 / stripped by Step 4b at the string edges.
 *   2. Bracket pairs around an empty token removed (scan outward through
 *      whitespace).
 *   3. Floating separators (· • / | - – —) adjacent to an empty token removed;
 *      look right, except the LAST token in the format looks left.
 *   4. Whitespace collapse; whitespace-before-connective repair; leading
 *      orphan connective strip.
 *   4b. Trailing orphan : , . stripped UNLESS the original format string ends
 *      with '.' (authorial sentence terminator). Quote marks never stripped
 *      (a surviving 5' is intentional).
 *   5. Exactly one surviving token → remaining connective separators stripped;
 *      literal text and brackets around a survivor are kept.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.15.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'BWS_JOIN_MAX_SLOTS' ) ) {
	/**
	 * Fixed v1 slot cap. Threaded through the resolve loop, the {N}-token scan,
	 * the option-emit loop, and the editor reveal chain — raising it is one
	 * change. Driver: a full personal name needs 7 parts + headroom to spare.
	 * Dynamic/unbounded slots are tracked future work (docs/future-work.md).
	 */
	define( 'BWS_JOIN_MAX_SLOTS', 10 );
}

// ===============================================
// ABSORB SEAM — per-slot text resolve
// ===============================================

/**
 * Resolve ONE join slot identically to {{text}} (the absorb seam).
 *
 * Delegates to bws_base_text_resolve_value() (shipped 1.14.1) — the full text
 * read: post/term/ref/srcTermIn dispatch AND the src:site arm (the try_text
 * site gap is closed by construction). link_id/link_type are IGNORED: join
 * composes raw values, no per-slot link-wrap (wrapping — if ever — happens
 * once at the join layer). '0' preserved.
 *
 * @since 1.15.0
 * @param array  $slot_opts Single-slot text-tag option set (src/ref/use/key/
 *                          srcTermIn/limit — never join's tag-level sep).
 * @param object $instance  GB tag instance.
 * @return string Finished slot value ('' on miss).
 */
function bws_join_resolve_slot( array $slot_opts, $instance ): string {
	if ( ! function_exists( 'bws_base_text_resolve_value' ) ) {
		return '';
	}
	$resolved = bws_base_text_resolve_value( $slot_opts, $instance );
	return (string) $resolved['value'];
}

// ===============================================
// ASSEMBLY (pure — harnessed in join-template-test.php)
// ===============================================

/**
 * Assemble collected slot values per the tag-level mode.
 *
 * @since 1.15.0
 * @param array $values  1-based slot values (finished strings, '' = empty).
 * @param array $options Tag options (mode, sep, format).
 * @return string Assembled output ('' when nothing survives).
 */
function bws_join_assemble( array $values, array $options ): string {
	$mode = $options['mode'] ?? '';
	if ( 'template' === $mode ) {
		$format = $options['format'] ?? '';
		return '' === $format ? '' : bws_join_template( $values, bws_join_wire_format( $format ) );
	}
	// Separator mode: absent key → default ', '; explicit '' honored is NOT
	// offered here (GB never serializes an empty option value), so '' → default.
	$sep = isset( $options['sep'] ) && '' !== $options['sep'] ? $options['sep'] : ', ';
	return bws_join_separator( $values, $sep );
}

/**
 * Translate the WIRE format syntax (%1…%N) to the canonical internal token
 * syntax ({1}…{N}).
 *
 * GB CONSTRAINT (docs/gb-constraints.md): find_matches() captures tag options
 * as `[^}]+` — a `}` anywhere inside a tag's options kills the whole tag
 * match, so brace tokens `{1}` can NEVER ride the wire inside {{join …}}.
 * Authors therefore write `%1`…`%N` in the Format control; this translates to
 * the brace form the pure algorithm (and its harness) canonically uses.
 * `%%` escapes a literal percent sign directly before a digit (printf
 * convention); a lone `%` not followed by a slot digit passes through as-is.
 *
 * Pure — no WP/GB symbols. Harnessed in join-template-test.php.
 *
 * @since 1.15.0
 * @param string $format Author-written wire format (%N tokens).
 * @return string Canonical format ({N} tokens).
 */
function bws_join_wire_format( string $format ): string {
	$format = str_replace( '%%', "\x00", $format ); // protect escaped %
	// High → low so a two-digit token (`%10`) matches before its `%1` prefix;
	// low-first would rewrite `%10` to `{1}0`.
	for ( $n = BWS_JOIN_MAX_SLOTS; $n >= 1; $n-- ) {
		$format = str_replace( '%' . $n, '{' . $n . '}', $format );
	}
	return str_replace( "\x00", '%', $format );
}

/**
 * Separator mode: join all non-empty values. '' is the ONLY empty; '0' kept.
 *
 * @since 1.15.0
 * @param array  $values 1-based slot values.
 * @param string $sep    Assembly separator.
 * @return string
 */
function bws_join_separator( array $values, string $sep ): string {
	return implode( $sep, array_filter( $values, static fn( $v ) => '' !== $v ) );
}

/**
 * Template mode: substitute positional {N} tokens, then smart-remove literal
 * punctuation attached to empty tokens (Steps 1–5, file header).
 *
 * All-empty short-circuit: when the format contains at least one {N} token and
 * every one of them resolved empty, returns '' (so literal-only residue like a
 * stray "Mr." never renders and the fallback path can fire). A format with NO
 * tokens is returned verbatim (author literal).
 *
 * @since 1.15.0
 * @param array  $values 1-based slot values.
 * @param string $format Format string with {1}…{N} positional tokens.
 * @return string
 */
function bws_join_template( array $values, string $format ): string {
	$result    = $format;
	$empty     = array();
	$present   = 0; // tokens present in the format
	$non_empty = 0; // present tokens with a non-empty value

	for ( $n = 1; $n <= BWS_JOIN_MAX_SLOTS; $n++ ) {
		$token = '{' . $n . '}';
		if ( ! str_contains( $format, $token ) ) {
			continue;
		}
		$present++;
		if ( '' !== ( $values[ $n ] ?? '' ) ) {
			$non_empty++;
			$result = str_replace( $token, $values[ $n ], $result );
		} else {
			$empty[] = $n;
		}
	}

	if ( $present > 0 && 0 === $non_empty ) {
		return '';
	}
	if ( empty( $empty ) ) {
		return $result;
	}

	// Highest {N} present in the ORIGINAL format = the "last token" (drives
	// Step 3's look-left exception), regardless of its value.
	$last_token = 0;
	for ( $n = BWS_JOIN_MAX_SLOTS; $n >= 1; $n-- ) {
		if ( str_contains( $format, '{' . $n . '}' ) ) {
			$last_token = $n;
			break;
		}
	}

	// Steps 1–3 per empty token, left-to-right (slot order) so adjacent-empty
	// cascades resolve against the current string state.
	foreach ( $empty as $n ) {
		$result = bws_join_remove_empty_token( $result, '{' . $n . '}', $n === $last_token );
	}

	// Step 4 — whitespace collapse + whitespace-before-connective repair +
	// leading orphan connective strip.
	$result = preg_replace( '/\s{2,}/', ' ', $result );
	$result = preg_replace( '/\s+([,:])/', '$1', $result );
	$result = trim( $result );
	$result = preg_replace( '/^[,:]\s*/', '', $result );

	// Step 4b — trailing orphan punctuation. Keep a trailing '.' only when the
	// ORIGINAL format intentionally ends with one. Quotes never stripped.
	$ends_period = str_ends_with( rtrim( $format ), '.' );
	$result      = preg_replace( $ends_period ? '/[,:]\s*$/' : '/[,:.]\s*$/', '', $result );

	// Step 5 — single surviving token strips remaining connective separators.
	if ( 1 === $non_empty ) {
		$result = bws_join_strip_connective_separators( $result );
	}

	return trim( $result );
}

/**
 * Steps 1–3 for ONE empty token occurrence: attached-punctuation shed,
 * bracket-pair removal, floating-separator removal. Byte-index scan (the
 * multi-byte separators – — · • are matched as whole UTF-8 substrings).
 *
 * @since 1.15.0
 * @param string $result        Current string (token still literally present).
 * @param string $token         The literal token, e.g. '{2}'.
 * @param bool   $is_last_token Step 3 looks LEFT for the format's last token.
 * @return string String with the token and its shed surroundings removed.
 */
function bws_join_remove_empty_token( string $result, string $token, bool $is_last_token ): string {
	$seps = array( '·', '•', '/', '|', '-', '–', '—' );

	while ( false !== ( $pos = strpos( $result, $token ) ) ) {
		$len   = strlen( $result );
		$start = $pos;                          // deletion range [start, end)
		$end   = $pos + strlen( $token );

		// --- Step 1a: trailing-attached UNIT punct (. ' ") sheds with the token.
		while ( $end < $len && in_array( $result[ $end ], array( '.', "'", '"' ), true ) ) {
			$end++;
		}

		// --- Step 1b: CONNECTIVE (, :) collapse — only when connectives flank
		// BOTH sides (whitespace-adjacent or attached); consume the LEFT one.
		// A single-sided connective survives as the neighbors' separator.
		$l = $start;
		while ( $l > 0 && ctype_space( $result[ $l - 1 ] ) ) {
			$l--;
		}
		$left_conn = $l > 0 && in_array( $result[ $l - 1 ], array( ',', ':' ), true );
		$r         = $end;
		while ( $r < $len && ctype_space( $result[ $r ] ) ) {
			$r++;
		}
		$right_conn = $r < $len && in_array( $result[ $r ], array( ',', ':' ), true );
		if ( $left_conn && $right_conn ) {
			$start = $l - 1;
		}

		// --- Step 2: bracket pair around the (extended) deletion range.
		$bl = $start;
		while ( $bl > 0 && ctype_space( $result[ $bl - 1 ] ) ) {
			$bl--;
		}
		$br = $end;
		while ( $br < $len && ctype_space( $result[ $br ] ) ) {
			$br++;
		}
		$pairs = array(
			'(' => ')',
			'[' => ']',
		);
		if ( $bl > 0 && $br < $len
			&& isset( $pairs[ $result[ $bl - 1 ] ] )
			&& $result[ $br ] === $pairs[ $result[ $bl - 1 ] ] ) {
			$start = $bl - 1;
			$end   = $br + 1;
		} else {
			// --- Step 3: floating separator adjacent to the token. Look right;
			// the format's LAST token looks left instead.
			if ( $is_last_token ) {
				$sl = $start;
				while ( $sl > 0 && ctype_space( $result[ $sl - 1 ] ) ) {
					$sl--;
				}
				foreach ( $seps as $sep ) {
					$sw = strlen( $sep );
					if ( $sl >= $sw && substr( $result, $sl - $sw, $sw ) === $sep ) {
						$start = $sl - $sw;
						break;
					}
				}
			} else {
				$sr = $end;
				while ( $sr < $len && ctype_space( $result[ $sr ] ) ) {
					$sr++;
				}
				foreach ( $seps as $sep ) {
					$sw = strlen( $sep );
					if ( substr( $result, $sr, $sw ) === $sep ) {
						$end = $sr + $sw;
						break;
					}
				}
			}
		}

		$result = substr( $result, 0, $start ) . substr( $result, $end );
	}

	return $result;
}

/**
 * Step 5: strip remaining floating connective separators when exactly one
 * token survived. Only whitespace-flanked separator runs are removed (a
 * hyphen inside a word is never touched); leading/trailing separator runs at
 * the string edges go too. Literal text and brackets around the survivor stay.
 *
 * @since 1.15.0
 * @param string $result Assembled string.
 * @return string
 */
function bws_join_strip_connective_separators( string $result ): string {
	$sep_class = '·•\/|\x{2013}\x{2014}-';
	$result    = preg_replace( '/\s[' . $sep_class . ']+(\s|$)/u', ' ', $result );
	$result    = preg_replace( '/^[' . $sep_class . ']+\s/u', '', $result );
	return trim( $result );
}
