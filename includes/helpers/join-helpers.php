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
 * bws_join_apply_groups() / bws_join_remove_empty_token() /
 * bws_join_strip_connective_separators() are
 * PURE string functions (no WP/GB symbols) — harnessed locally by
 * tools/test/join-template-test.php (house pattern: fns copied inline there;
 * keep both in sync).
 *
 * Template-mode smart literal removal (ordered steps on empty-token
 * positions — docs/tag-reference.md §{{join}}):
 *   0. ~…~ unit groups: a group whose {N} tokens ALL resolved empty is excised
 *      whole (it then sheds adjacent separators like an empty token — Step 3);
 *      a group with any non-empty token is unwrapped and its contents run
 *      through Steps 1–5 normally. `~~` = literal tilde; an unpaired lone `~`
 *      stays literal; a group with NO tokens is unwrapped verbatim.
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
 *                          srcTermIn/limit — never join's tag-level valueSep).
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
 * @param array $options Tag options (mode, valueSep, format).
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
	// `valueSep` = join's tag-level assembly separator (renamed from `sep`,
	// 1.16.0/FW-52) — distinct from the source-group list-mode `sep`.
	$sep = isset( $options['valueSep'] ) && '' !== $options['valueSep'] ? $options['valueSep'] : ', ';
	return bws_join_separator( $values, $sep );
}

/**
 * Translate the WIRE format syntax (%A…%N) to the canonical internal token
 * syntax ({1}…{N}).
 *
 * GB CONSTRAINT (docs/gb-constraints.md): find_matches() captures tag options
 * as `[^}]+` — a `}` anywhere inside a tag's options kills the whole tag
 * match, so brace tokens `{1}` can NEVER ride the wire inside {{join …}}.
 * Authors therefore write `%A`…`%J` in the Format control; this translates to
 * the brace form the pure algorithm (and its harness) canonically uses.
 *
 * TOKENS FOLLOW THE SLOT KEY SPELLING (1.17.0). A slot's key and its format token
 * name the same thing, so they use the same alphabet: `{{join A:key(x)|format:%A}}`
 * reads as one statement, where `A:…|format:%1` reads as two. The DIGIT spelling is
 * still accepted and always will be — both alphabets collapse to the same internal
 * `{N}`, so there is exactly one translation point and nothing downstream can tell
 * which the author typed. That is what makes the move migration-free for authors, and
 * it is required by ADR 0004 anyway: wire is hand-editable and paste-portable, and no
 * scanner reaches wire on a clipboard.
 *
 * `%%` escapes a literal percent sign before a token (printf convention); a lone `%`
 * followed by anything else passes through as-is. THE ESCAPE SURFACE WIDENED with the
 * letters, and it follows the CONTAINER'S SLOT COUNT rather than the letter class —
 * `%K` stays literal in a 10-slot join. A stored pre-1.17.0 format holding a literal
 * `%` before A–J therefore changes meaning, which is why the escape is a real
 * migration (bws_migrate_join_format_escape) and not a nicety.
 *
 * Pure — no WP/GB symbols. Harnessed in join-template-test.php.
 *
 * @since 1.15.0
 * @since 1.17.0 Accepts `%A` alongside `%1`.
 * @param string $format Author-written wire format (%A or %N tokens).
 * @return string Canonical format ({N} tokens).
 */
function bws_join_wire_format( string $format ): string {
	$format = str_replace( '%%', "\x00", $format ); // protect escaped %
	// High → low so a multi-character token matches before its own prefix: `%10`
	// before `%1` (low-first rewrites `%10` to `{1}0`), and `%AA` before `%A` if a
	// container ever reaches 27 slots.
	for ( $n = BWS_JOIN_MAX_SLOTS; $n >= 1; $n-- ) {
		$token  = '{' . $n . '}';
		$format = str_replace( '%' . bws_slot_ordinal( $n ), $token, $format );
		$format = str_replace( '%' . $n, $token, $format );
	}
	return str_replace( "\x00", '%', $format );
}

/**
 * MigrationRegistry transform: escape a literal `%` that the letter tokens just made
 * significant.
 *
 * THE REGRESSION THIS EXISTS FOR. Before 1.17.0 a `%` not followed by a slot DIGIT
 * passed through `bws_join_wire_format()` untouched, so `Up 10%APR, paid %1` was a
 * legal stored format. With `%A`…`%J` tokenizing, that `%A` becomes slot 1 and the
 * output silently changes.
 *
 * GATED ON WIRE ERA, NOT ON CONTENT, and that is the whole design. A `%A` in a format
 * string is INDISTINGUISHABLE as literal-or-token by inspection, so any content test
 * ("does it also contain a digit token?", "which alphabet did the author mean?")
 * corrupts the case it guesses wrong — and the wrong guess destroys an intended token,
 * i.e. it breaks working output rather than failing to fix broken output. The era is
 * decidable, though: a FOLDED slot key could not exist before 1.17.0, and after 1.17.0
 * every join the editor writes has one. So a tag carrying any folded slot key is
 * post-letters wire and its `%A` is a TOKEN, left alone; a tag with none is
 * pre-letters wire and its `%A` is a LITERAL, escaped.
 *
 * That gate is why this entry must register BEFORE the fold entry (see
 * bws_register_option_migrations): the fold ADDS folded keys, so running afterwards
 * would see every tag as post-letters and never fire.
 *
 * FIXPOINT twice over: the lookbehind skips a `%` that is already escaped, and after
 * the fold has run the era gate skips the tag outright.
 *
 * The DIGIT tokens are deliberately NOT re-spelled. Both alphabets resolve identically,
 * so rewriting them would be churn on every stored template-mode join for no output
 * change.
 *
 * SCANNER PATH ONLY, and that gap is known: a join inside a block widget lives in the
 * `widget_block` option, which the content scanner never reads (the fold migration has
 * a JS mount twin for exactly that reason). A second twin was not built here because
 * the exposure is one rendering change in rare literal text, not lost configuration.
 *
 * @since 1.17.0
 * @param string $tag_string Raw tag string.
 * @return string Rewritten tag string, or the original.
 */
function bws_migrate_join_format_escape( string $tag_string ): string {
	$reg = 'BWS\DynamicTags\MigrationRegistry';
	if ( ! class_exists( $reg ) || ! function_exists( 'bws_slot_ordinal' ) ) {
		return $tag_string;
	}

	list( $tag_name, $options ) = $reg::parse_tag_string( $tag_string );
	if ( 'join' !== $tag_name || ! isset( $options['format'] ) ) {
		return $tag_string;
	}

	// Era gate. One folded slot key anywhere means the letters were already live when
	// this tag was written, so its `%A` is a token.
	foreach ( array_keys( $options ) as $option_key ) {
		if ( bws_slot_ordinal_num( (string) $option_key ) > 0 ) {
			return $tag_string;
		}
	}

	// The token set is bounded by the CONTAINER, not by A–Z: a `%K` in a 10-slot join is
	// literal today and must stay literal, or this migration escapes text the renderer
	// never touches. An ALTERNATION rather than a character class, longest-first, because
	// a container past 26 slots spells them `AA` — which no char class can match and
	// which must be tried before its own `A` prefix.
	$tokens = array();
	for ( $n = BWS_JOIN_MAX_SLOTS; $n >= 1; $n-- ) {
		$tokens[] = preg_quote( bws_slot_ordinal( $n ), '/' );
	}

	$escaped = preg_replace( '/(?<!%)%(' . implode( '|', $tokens ) . ')/', '%%$1', (string) $options['format'] );
	if ( null === $escaped || $escaped === $options['format'] ) {
		return $tag_string;
	}

	$options['format'] = $escaped;
	return $reg::format_tag_string( $tag_name, $options );
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
 * punctuation attached to empty tokens (Steps 0–5, file header).
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
	// --- Step 0: ~…~ unit groups. Protect `~~` (literal tilde) with the same
	// sentinel trick as %% in bws_join_wire_format(), parse groups, restore.
	// An all-empty group collapses to a \x02 marker that sheds like an empty
	// token below; other groups are unwrapped in place.
	$format = str_replace( '~~', "\x01", $format );
	if ( str_contains( $format, '~' ) ) {
		$format = bws_join_apply_groups( $format, $values );
	}
	$format = str_replace( "\x01", '~', $format );

	$result    = $format;
	$empty     = array();
	$markers   = substr_count( $format, "\x02" );
	$present   = $markers; // each marker = one all-empty group
	$non_empty = 0;        // present tokens with a non-empty value

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
	if ( empty( $empty ) && 0 === $markers ) {
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

	// Step 0 markers shed like empty tokens. The look-left exception belongs
	// to the FINAL marker when it is also the format's last token-ish element
	// (marker or {N}); it is retagged \x03 so the two passes get distinct
	// is_last flags (bws_join_remove_empty_token removes ALL occurrences of
	// its token per call).
	if ( $markers > 0 ) {
		preg_match_all( '/\{\d+\}|' . "\x02" . '/', $format, $tok_m );
		$last_is_marker = "\x02" === end( $tok_m[0] );
		if ( $last_is_marker ) {
			$p = strrpos( $result, "\x02" );
			if ( false !== $p ) {
				$result[ $p ] = "\x03";
			}
		}
		// Left-to-right like the empty-token loop: earlier markers first.
		$result = bws_join_remove_empty_token( $result, "\x02", false );
		if ( $last_is_marker ) {
			$result = bws_join_remove_empty_token( $result, "\x03", true );
		}
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
 * Step 0: parse ~…~ unit groups against the resolved values.
 *
 * A group binds literals (units like "lbs.") to the token(s) inside it so they
 * live or die together. Pieces from an explode on '~' alternate outside/inside;
 * an odd tilde count leaves the LAST delimiter unpaired → it is literal (folded
 * back). Per inside piece: no {N} tokens → unwrapped verbatim (author literal);
 * all tokens empty → the whole group collapses to the \x02 marker (shed by the
 * caller like an empty token, Step 3 separator rules included); any non-empty
 * token → unwrapped, contents processed by Steps 1–5 normally.
 *
 * Caller protects `~~` (literal tilde) BEFORE this runs — a raw tilde reaching
 * here is always a delimiter candidate. Pure; harnessed via bws_join_template()
 * in join-template-test.php.
 *
 * @since 1.15.0
 * @param string $format Canonical format ({N} tokens), `~~` already sentineled.
 * @param array  $values 1-based slot values.
 * @return string Format with groups resolved (markers in, delimiters out).
 */
function bws_join_apply_groups( string $format, array $values ): string {
	$pieces = explode( '~', $format );
	$n      = count( $pieces );
	if ( $n < 3 ) {
		return $format; // one lone ~ (or none) — literal, nothing to pair.
	}
	if ( 0 === $n % 2 ) {
		// Odd tilde count: last delimiter unpaired → literal; refold.
		$tail                          = array_pop( $pieces );
		$pieces[ count( $pieces ) - 1 ] .= '~' . $tail;
	}
	$out = '';
	foreach ( $pieces as $i => $piece ) {
		if ( 0 === $i % 2 ) {
			$out .= $piece; // outside any group
			continue;
		}
		if ( ! preg_match_all( '/\{(\d+)\}/', $piece, $m ) ) {
			$out .= $piece; // token-less group → unwrap verbatim
			continue;
		}
		$all_empty = true;
		foreach ( $m[1] as $d ) {
			if ( '' !== ( $values[ (int) $d ] ?? '' ) ) {
				$all_empty = false;
				break;
			}
		}
		$out .= $all_empty ? "\x02" : $piece;
	}
	return $out;
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
