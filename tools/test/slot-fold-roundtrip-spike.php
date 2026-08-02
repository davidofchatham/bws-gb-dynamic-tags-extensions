<?php
/**
 * SPIKE A — FW-57 folded-slot value: parse ⇄ emit round-trip harness.
 *
 * NOT a shipped-code harness (nothing in includes/ implements this yet — the
 * grammar is the FW-56/57 APPROVED wire spec from .claude/plans/src-chain-encoding.md
 * §WIRE SPEC, approved 2026-07-31). This spike answers ONE question before any
 * PHP/JS lands: is that grammar unambiguously PARSEABLE and round-trippable,
 * including hand-edited (ADR 0004) and hostile inputs — not merely emittable
 * (the tools/preview sandbox only emits)?
 *
 * Properties tested:
 *   P1 IDENTITY    — emit(parse(s)) === s for canonical corpus strings.
 *   P2 FIXPOINT    — parse(emit(struct)) == struct for structures.
 *   P3 NORMALIZE   — hand-edited (reordered / spaced) input parses, re-emits
 *                    CANONICAL (the DECISION 2 control-rewrites-value model).
 *   P4 ESCAPE      — free-form `:` `|` survive the GB layer both directions;
 *                    emitted values carry NO unescaped `:`/`|` (the JS
 *                    split-limit-2 tail-discard hazard, gb-constraints.md
 *                    §Tag string escape syntax).
 *   P5 BALANCE     — balanced inner brackets in free-form survive; UNBALANCED
 *                    is a detected error, not silent corruption.
 *   P6 ERRORS      — malformed input yields a flagged error + no crash.
 *
 * Separator chars are PARAMETERIZED ($G); the whole suite re-runs under an
 * alternate char set to prove the grammar doesn't secretly depend on the
 * canonical picks. Canonical set approved 2026-07-31: OPT `;` · HOP `;` ·
 * STEP `,` · L1 `()` · L2 `[]`.
 *
 * House pattern: pure PHP, no WP, no autoload; run `php tools/test/slot-fold-roundtrip-spike.php`;
 * exits non-zero on failure. Graduates to a real `*-test.php` harness when the
 * fold ships (rename + register in CLAUDE.md §Update triggers).
 */

// ── Grammar config (APPROVED chars, 2026-07-31 §WIRE SPEC) ────────────────
// Canonical chars drive EMIT; each `*_class` is the LENIENT-ACCEPT set the
// parser also honors (chosen-for-visual-distinctiveness chars have functional
// twins: `,`≡`;`, `+`≡`/`, `()`≡`[]`). Roles are POSITION-disambiguated
// (depth 0 vs inside src(...) vs inside a free-form bracket), so classes may
// OVERLAP ACROSS positions (opt + step both accept `,;`) but must stay
// DISJOINT within one (hop ∩ step = ∅ — both live inside the chain).
// bws_spike_grammar_validate() machine-checks that before any suite runs.
function bws_spike_grammar( $over = array() ) {
	return array_merge( array(
		'opt_sep'    => ';',                 // between slot tokens (canonical emit)
		'opt_class'  => array( ';', ',' ),   // lenient accept at depth 0
		'hop_sep'    => ';',                 // between chain steps inside src(...)
		// STRICT: `;` only. `+` and `/` are RESERVED for possible future roles.
		// Leniently accepting either would bind it to `hop` meaning NOW, so any
		// later use would silently CHANGE what already-saved wires parse as — a
		// lenient class is not free, it SPENDS the char.
		'hop_class'  => array( ';' ),
		// Chars deliberately held UNSPENT. Declared explicitly so the guard cannot
		// be disarmed by the same edit that breaks the property (see P8.r).
		'reserved'   => array( '+', '/' ),
		'step_sep'   => ',',                 // intra-step: slug,arg[,limit]
		// NARROWED to `{,}`: the approved hop-sep is `;`, and hop+step share a
		// position (both inside the chain), so `;` can no longer be leniently
		// accepted as a step separator — see bws_spike_grammar_validate().
		'step_class' => array( ',' ),
		'br_open'    => '(',                 // canonical L1 bracket (emit)
		'br_close'   => ')',
		// Accepted bracket pairs — PER-TOKEN delimiter rule: the open char after
		// a token name fixes THAT token's structural pair; the other pair is
		// inert text inside it (an author can pick the pair their content avoids).
		'br_pairs'   => array( '(' => ')', '[' => ']' ),
	), $over );
}

/**
 * Per-position disjointness validator — THE safety condition for lenient
 * separator classes. Returns array of violation strings (empty = safe).
 */
function bws_spike_grammar_validate( $G ) {
	$bad     = array();
	$br_all  = array_merge( array_keys( $G['br_pairs'] ), array_values( $G['br_pairs'] ) );
	// hop + step share a position (inside the chain) — must be disjoint.
	if ( array_intersect( $G['hop_class'], $G['step_class'] ) ) {
		$bad[] = 'hop_class ∩ step_class ≠ ∅';
	}
	// no separator class may contain a bracket char (brackets structural everywhere).
	foreach ( array( 'opt_class', 'hop_class', 'step_class' ) as $cls ) {
		if ( array_intersect( $G[ $cls ], $br_all ) ) {
			$bad[] = "$cls contains a bracket char";
		}
	}
	// canonical emit char must be inside its own accept class.
	foreach ( array( 'opt' => 'opt_sep', 'hop' => 'hop_sep', 'step' => 'step_sep' ) as $role => $k ) {
		if ( ! in_array( $G[ $k ], $G[ "{$role}_class" ], true ) ) {
			$bad[] = "{$k} not in its accept class";
		}
	}
	if ( ! isset( $G['br_pairs'][ $G['br_open'] ] ) || $G['br_pairs'][ $G['br_open'] ] !== $G['br_close'] ) {
		$bad[] = 'canonical bracket pair not in br_pairs';
	}
	return $bad;
}

// Free-form option names (arbitrary author text → escape + balance rules apply).
const BWS_SPIKE_FREEFORM = array( 'format', 'fallback', 'sep', 'valueSep', 'rangeSep', 'timeSep', 'label' );

// Option-R type tokens (agnostic containers). 'text' is a NON-TYPE — never on the wire.
const BWS_SPIKE_TYPES = array( 'title', 'content', 'email', 'phone', 'permalink', 'image', 'datetime_single', 'datetime_range' );

// Bare flag tokens.
const BWS_SPIKE_FLAGS = array( 'newTab', 'showCurrentYear', 'showMidnight' );

// FW-52 canonical (group, within) ranks — port of the sandbox RESPELL_KEY_MAP
// (itself mirroring serialization-order.php). label/type are emitted ahead of
// ranked tokens (sandbox serializeSlot: [labelTok, lead, ...sorted]).
const BWS_SPIKE_KEY_MAP = array(
	'as' => array( 0, 0 ), 'mode' => array( 0, 2 ), 'valueSep' => array( 0, 3 ),
	'format' => array( 0, 4 ), 'rangeSep' => array( 0, 5 ), 'timeSep' => array( 0, 6 ),
	'showCurrentYear' => array( 0, 7 ), 'showMidnight' => array( 0, 8 ),
	'src' => array( 1, 0 ), 'limit' => array( 1, 3 ), 'sep' => array( 1, 4 ),
	'use' => array( 1, 5 ), 'key' => array( 1, 6 ),
	'timeKey' => array( 1, 7 ), 'startKey' => array( 1, 8 ), 'startTimeKey' => array( 1, 9 ),
	'endKey' => array( 1, 10 ), 'endTimeKey' => array( 1, 11 ),
	'linkTo' => array( 2, 0 ), 'linkKey' => array( 2, 1 ), 'newTab' => array( 2, 2 ),
	'fallback' => array( 3, 0 ),
);

// ── GB layer (mirror of GB parse_options / the JS parser asymmetry) ────────

/** Escape GB-structural chars in a value (what our serializer must do on save). */
function bws_spike_gb_escape( $v ) {
	return preg_replace( '/([:|])/', '\\\\$1', $v );
}

/** Unescape — what GB's PHP parse_options does before handing values to callbacks. */
function bws_spike_gb_unescape( $v ) {
	return preg_replace( '/\\\\([:|])/', '$1', $v );
}

/**
 * Mirror of GB's PHP option split: pairs on unescaped `|`, key/value on FIRST
 * unescaped `:`, value unescaped. Returns array( key => value ).
 */
function bws_spike_gb_parse_options( $options_string ) {
	$out   = array();
	$pairs = preg_split( '/(?<!\\\\)\|/', $options_string );
	foreach ( $pairs as $pair ) {
		if ( '' === $pair ) {
			continue;
		}
		$kv = preg_split( '/(?<!\\\\):/', $pair, 2 );
		$k  = $kv[0];
		$v  = isset( $kv[1] ) ? bws_spike_gb_unescape( $kv[1] ) : true;
		$out[ $k ] = $v;
	}
	return $out;
}

/**
 * Mirror of the GB JS parser sharp edge (gb-constraints.md:343): split limit 2
 * DISCARDS everything after a second unescaped colon, and does NOT unescape.
 * Used to assert our emitted wire never trips it.
 */
function bws_spike_gb_js_value( $pair ) {
	$parts = preg_split( '/(?<!\\\\):/', $pair );
	// JS String.split(re, 2): caps at 2, tail thrown away.
	return isset( $parts[1] ) ? $parts[1] : true;
}

// ── Slot layer: balance-aware tokenizer ────────────────────────────────────

/**
 * Split a slot value into tokens on opt_sep, ignoring separators inside
 * (balanced) brackets. Returns array of raw token strings, or error array
 * array('error' => ...) on unbalanced brackets at token level.
 */
function bws_spike_tokenize( $value, $G ) {
	$toks  = array();
	$buf   = '';
	$depth = 0;
	$pair  = null;   // active structural pair (per-token delimiter rule): [open, close]
	$len   = strlen( $value );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $value[ $i ];
		if ( 0 === $depth ) {
			if ( isset( $G['br_pairs'][ $c ] ) ) {
				// First open char fixes this token's structural pair; the OTHER
				// pair is inert inside it.
				$pair  = array( $c, $G['br_pairs'][ $c ] );
				$depth = 1;
			} elseif ( in_array( $c, $G['br_pairs'], true ) ) {
				return array( 'error' => "unbalanced close bracket at $i" );
			} elseif ( in_array( $c, $G['opt_class'], true ) ) {
				$toks[] = $buf;
				$buf    = '';
				continue;
			}
		} else {
			// Inside a token value: only the ACTIVE pair is structural.
			if ( $c === $pair[0] ) {
				$depth++;
			} elseif ( $c === $pair[1] ) {
				$depth--;
			}
		}
		$buf .= $c;
	}
	if ( 0 !== $depth ) {
		return array( 'error' => 'unbalanced open bracket' );
	}
	$toks[] = $buf;
	return array_values( array_filter( array_map( 'trim', $toks ), 'strlen' ) );
}

/**
 * Parse one token: `name(value)` (value = everything inside the OUTER bracket
 * pair, inner balanced brackets verbatim) or bare `name`.
 */
function bws_spike_parse_token( $tok, $G ) {
	// Per-token delimiter rule: the FIRST accepted open char fixes the pair;
	// the other pair is inert text inside the value.
	$p     = false;
	$open  = null;
	foreach ( array_keys( $G['br_pairs'] ) as $o ) {
		$q = strpos( $tok, $o );
		if ( false !== $q && ( false === $p || $q < $p ) ) {
			$p    = $q;
			$open = $o;
		}
	}
	if ( false === $p ) {
		return array( 'name' => $tok, 'val' => null );
	}
	$close = $G['br_pairs'][ $open ];
	if ( substr( $tok, -1 ) !== $close ) {
		return array( 'error' => "token '$tok' bracket not closed at end" );
	}
	// The bracket opened at $p must be the one closed by the FINAL char — depth
	// (of the ACTIVE pair only) may not return to 0 before the end (catches
	// close-then-reopen junk like `src(a)+use(b)` arriving as ONE token; found
	// live in Spike B smoke).
	$depth = 0;
	$len   = strlen( $tok );
	for ( $i = $p; $i < $len; $i++ ) {
		if ( $tok[ $i ] === $open ) {
			$depth++;
		} elseif ( $tok[ $i ] === $close ) {
			$depth--;
			if ( 0 === $depth && $i < $len - 1 ) {
				return array( 'error' => "token '$tok' has trailing content after its value bracket" );
			}
		}
	}
	return array(
		'name' => substr( $tok, 0, $p ),
		'val'  => substr( $tok, $p + 1, strlen( $tok ) - $p - 2 ),
	);
}

// ── Chain sub-parser (FLAT LOCKED: slug,arg[,limit] joined by hop_sep) ─────

function bws_spike_parse_chain( $chain_str, $G ) {
	$steps   = array();
	$hop_re  = '/[' . preg_quote( implode( '', $G['hop_class'] ), '/' ) . ']/';
	$step_re = '/[' . preg_quote( implode( '', $G['step_class'] ), '/' ) . ']/';
	foreach ( preg_split( $hop_re, $chain_str ) as $seg ) {
		$seg = trim( $seg );
		if ( '' === $seg ) {
			continue;
		}
		$parts = preg_split( $step_re, $seg );
		$step  = array( 'slug' => trim( $parts[0] ), 'arg' => null, 'limit' => null );
		if ( isset( $parts[1] ) ) {
			$step['arg'] = trim( $parts[1] );
		}
		if ( isset( $parts[2] ) ) {
			if ( ! ctype_digit( trim( $parts[2] ) ) ) {
				return array( 'error' => "chain step '$seg': third token not a numeric limit" );
			}
			$step['limit'] = trim( $parts[2] );
		}
		if ( isset( $parts[3] ) ) {
			return array( 'error' => "chain step '$seg': too many intra-step tokens" );
		}
		$steps[] = $step;
	}
	return $steps;
}

function bws_spike_emit_chain( $steps, $G ) {
	$segs = array();
	foreach ( $steps as $s ) {
		$seg = $s['slug'];
		if ( null !== $s['arg'] && '' !== $s['arg'] ) {
			$seg .= $G['step_sep'] . $s['arg'];
		}
		if ( null !== $s['limit'] && '' !== $s['limit'] ) {
			$seg .= $G['step_sep'] . $s['limit'];
		}
		$segs[] = $seg;
	}
	return implode( $G['hop_sep'], $segs );
}

// ── Semantic slot parse ─────────────────────────────────────────────────────

/**
 * Parse a slot VALUE (already GB-unescaped) into structure.
 *
 * $container: 'try' (format-FIXED — no type token) | 'join'|'table' (agnostic —
 * type may lead). Container-conditional `key(...)` semantics: on a datetime_*
 * type, `key` is a datetime option, not the read (sandbox `if (read && !dtKeys)`).
 *
 * Returns array{label, type, chain, read, opts, extra} or array{error}.
 */
function bws_spike_parse_slot( $value, $container, $G ) {
	$toks = bws_spike_tokenize( $value, $G );
	if ( isset( $toks['error'] ) ) {
		return $toks;
	}
	$slot = array(
		'label' => null,
		'type'  => null,
		'chain' => array(),
		'read'  => null,
		'opts'  => array(),   // name => value|true, canonical-ranked on emit
		'extra' => array(),   // unknown tokens preserved verbatim (ADR 0004 tolerance)
	);
	foreach ( $toks as $tok ) {
		$t = bws_spike_parse_token( $tok, $G );
		if ( isset( $t['error'] ) ) {
			return $t;
		}
		$name = $t['name'];
		$val  = $t['val'];

		if ( null === $val ) {
			// Bare token: type (agnostic container, once) | flag | unknown.
			if ( 'try' !== $container && null === $slot['type'] && in_array( $name, BWS_SPIKE_TYPES, true ) ) {
				$slot['type'] = $name;
			} elseif ( in_array( $name, BWS_SPIKE_FLAGS, true ) ) {
				$slot['opts'][ $name ] = true;
			} else {
				$slot['extra'][] = $tok;
			}
			continue;
		}
		$is_dt = null !== $slot['type'] && 0 === strpos( (string) $slot['type'], 'datetime' );
		switch ( $name ) {
			case 'label':
				$slot['label'] = $val;
				break;
			case 'src':
				$chain = bws_spike_parse_chain( $val, $G );
				if ( isset( $chain['error'] ) ) {
					return $chain;
				}
				$slot['chain'] = $chain;
				break;
			case 'use':
				$slot['read'] = ( 'same' === $val )
					? array( 'kind' => 'same' )
					: array( 'kind' => 'analog', 'slug' => $val );
				break;
			case 'key':
				if ( $is_dt ) {
					$slot['opts']['key'] = $val;   // datetime option, not the read
				} else {
					$slot['read'] = array( 'kind' => 'key', 'field' => $val );
				}
				break;
			default:
				if ( isset( BWS_SPIKE_KEY_MAP[ $name ] ) ) {
					$slot['opts'][ $name ] = $val;
				} else {
					$slot['extra'][] = $tok;
				}
		}
	}
	return $slot;
}

// ── Canonical emit ──────────────────────────────────────────────────────────

/**
 * Emit a slot structure to the canonical wire value. Redundancy rules per the
 * sandbox: analog slug equal to the type token drops; `default` analog drops
 * (absent ≡ default); 'text' type never emits. Free-form values GB-escaped.
 */
function bws_spike_emit_slot( $slot, $G ) {
	$O = $G['br_open'];
	$C = $G['br_close'];

	$entries = array();
	$push    = function ( $name, $val ) use ( &$entries ) {
		$rank      = isset( BWS_SPIKE_KEY_MAP[ $name ] ) ? BWS_SPIKE_KEY_MAP[ $name ] : array( 1, 99 );
		$entries[] = array( 'name' => $name, 'val' => $val, 'rank' => $rank, 'i' => count( $entries ) );
	};

	if ( ! empty( $slot['chain'] ) ) {
		$push( 'src', bws_spike_emit_chain( $slot['chain'], $G ) );
	}
	$read = $slot['read'];
	if ( $read ) {
		if ( 'same' === $read['kind'] ) {
			$push( 'use', 'same' );
		} elseif ( 'key' === $read['kind'] ) {
			$push( 'key', $read['field'] );
		} elseif ( 'analog' === $read['kind']
			&& 'default' !== $read['slug']
			&& $read['slug'] !== $slot['type'] ) {
			$push( 'use', $read['slug'] );
		}
	}
	foreach ( $slot['opts'] as $name => $val ) {
		$push( $name, $val );
	}

	usort( $entries, function ( $a, $b ) {
		return $a['rank'][0] - $b['rank'][0]
			?: $a['rank'][1] - $b['rank'][1]
			?: $a['i'] - $b['i'];
	} );

	$emit_one = function ( $name, $val ) use ( $O, $C ) {
		if ( true === $val ) {
			return $name;   // bare flag
		}
		if ( in_array( $name, BWS_SPIKE_FREEFORM, true ) ) {
			$val = bws_spike_gb_escape( $val );
		}
		return $name . $O . $val . $C;
	};

	$toks = array();
	if ( null !== $slot['label'] && '' !== $slot['label'] ) {
		$toks[] = $emit_one( 'label', $slot['label'] );
	}
	if ( null !== $slot['type'] && 'text' !== $slot['type'] ) {
		$toks[] = $slot['type'];
	}
	foreach ( $entries as $e ) {
		$toks[] = $emit_one( $e['name'], $e['val'] );
	}
	foreach ( $slot['extra'] as $x ) {
		$toks[] = $x;
	}
	return implode( $G['opt_sep'], $toks );
}

/**
 * IMPORTANT ASYMMETRY under test: emit ESCAPES free-form (wire form); parse
 * receives the GB-UNESCAPED value on the PHP side but the still-ESCAPED value
 * on the JS side (gb-constraints: JS parser never unescapes). So the slot
 * parser must accept BOTH. We normalize: unescape free-form at semantic-parse
 * time so struct holds the raw author text either way.
 */
function bws_spike_parse_slot_wire( $value, $container, $G ) {
	$slot = bws_spike_parse_slot( $value, $container, $G );
	if ( isset( $slot['error'] ) ) {
		return $slot;
	}
	if ( null !== $slot['label'] ) {
		$slot['label'] = bws_spike_gb_unescape( $slot['label'] );
	}
	foreach ( $slot['opts'] as $name => $val ) {
		if ( true !== $val && in_array( $name, BWS_SPIKE_FREEFORM, true ) ) {
			$slot['opts'][ $name ] = bws_spike_gb_unescape( $val );
		}
	}
	return $slot;
}

// ── Harness ─────────────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;
function check( $label, $ok, $detail = '' ) {
	global $pass, $fail;
	if ( $ok ) {
		$pass++;
		return;
	}
	$fail++;
	echo "FAIL  $label\n";
	if ( '' !== $detail ) {
		echo "      $detail\n";
	}
}

function run_suite( $G, $tag ) {

	// ── P1 IDENTITY: canonical corpus (approved-spec + sandbox scenarios) ───
	// Format: [ container, canonical string ]
	$s = $G['opt_sep'];
	$h = $G['hop_sep'];
	$c = $G['step_sep'];
	$O = $G['br_open'];
	$C = $G['br_close'];
	$corpus = array(
		// try_text — worked-example header strings
		array( 'try',  "key{$O}custom_field{$C}" ),
		array( 'try',  "src{$O}terms{$c}category{$C}{$s}use{$O}title{$C}" ),
		array( 'try',  "src{$O}refs{$c}office{$h}refs{$c}region{$C}{$s}key{$O}name{$C}" ),
		// intent paths (2×2)
		array( 'try',  "src{$O}refs{$c}office{$C}{$s}use{$O}same{$C}" ),                    // A: new ctx, same read
		array( 'try',  "src{$O}same{$C}{$s}key{$O}second{$C}" ),                            // B: same ctx, new read
		array( 'try',  "src{$O}refs{$c}office{$C}{$s}key{$O}second{$C}" ),                  // C: both new
		// start-kind + limit
		array( 'try',  "src{$O}post{$c}9999{$h}refs{$c}related_staff{$c}5{$C}{$s}use{$O}title{$C}" ),
		// join — agnostic, type leads (Option R); 'text' type absent by rule
		array( 'join', 'title' ),
		array( 'join', "phone{$s}key{$O}mobile{$C}" ),
		array( 'join', "email{$s}key{$O}contact_email{$C}" ),
		array( 'join', "key{$O}note{$C}" ),
		array( 'join', "title{$s}src{$O}refs{$c}office{$C}" ),
		// per-slot link
		array( 'join', "title{$s}linkTo{$O}permalink{$C}" ),
		array( 'join', "title{$s}linkTo{$O}key{$C}{$s}linkKey{$O}profile_url{$C}{$s}newTab" ),
		array( 'join', "email{$s}key{$O}contact_email{$C}{$s}linkTo{$O}key{$C}{$s}linkKey{$O}profile_url{$C}" ),
		// table columns — label first, then type
		array( 'table', "label{$O}Name{$C}{$s}title{$s}linkTo{$O}permalink{$C}" ),
		array( 'table', "label{$O}Office{$C}{$s}src{$O}refs{$c}office{$C}{$s}key{$O}address{$C}" ),
		// datetime slot — multi-key pivot, canonical rank order (as,format lead; keys in group 1)
		array( 'join', "datetime_range{$s}as{$O}date{$C}{$s}format{$O}M j{$C}{$s}startKey{$O}start_date{$C}{$s}endKey{$O}end_date{$C}" ),
		array( 'join', "datetime_single{$s}key{$O}event_date{$C}{$s}timeKey{$O}event_time{$C}" ),
	);
	foreach ( $corpus as $i => $case ) {
		list( $container, $str ) = $case;
		$slot = bws_spike_parse_slot_wire( $str, $container, $G );
		check( "[$tag] P1.$i parse ok: $str", ! isset( $slot['error'] ), isset( $slot['error'] ) ? $slot['error'] : '' );
		if ( isset( $slot['error'] ) ) {
			continue;
		}
		$out = bws_spike_emit_slot( $slot, $G );
		check( "[$tag] P1.$i identity: $str", $out === $str, "got: $out" );
	}

	// ── P2 FIXPOINT: structure → emit → parse → same structure ─────────────
	$structs = array(
		array( 'join', array(
			'label' => null, 'type' => 'phone',
			'chain' => array( array( 'slug' => 'refs', 'arg' => 'office', 'limit' => null ) ),
			'read'  => array( 'kind' => 'key', 'field' => 'mobile' ),
			'opts'  => array(), 'extra' => array(),
		) ),
		array( 'table', array(
			'label' => 'When', 'type' => 'datetime_range',
			'chain' => array(),
			'read'  => null,
			'opts'  => array( 'as' => 'date', 'startKey' => 'start_date', 'endKey' => 'end_date', 'format' => 'M j' ),
			'extra' => array(),
		) ),
		array( 'try', array(
			'label' => null, 'type' => null,
			'chain' => array(
				array( 'slug' => 'post', 'arg' => '9999', 'limit' => null ),
				array( 'slug' => 'refs', 'arg' => 'related_staff', 'limit' => '5' ),
			),
			'read'  => array( 'kind' => 'same' ),
			'opts'  => array(), 'extra' => array(),
		) ),
	);
	foreach ( $structs as $i => $case ) {
		list( $container, $st ) = $case;
		$wire = bws_spike_emit_slot( $st, $G );
		$back = bws_spike_parse_slot_wire( $wire, $container, $G );
		// opts key order isn't semantic — compare canonically sorted.
		$a = $st; $b = $back;
		if ( ! isset( $b['error'] ) ) { ksort( $a['opts'] ); ksort( $b['opts'] ); }
		check( "[$tag] P2.$i fixpoint via: $wire", $a == $b, var_export( $back, true ) );
	}

	// ── P3 NORMALIZE: hand-edits parse + re-emit canonical ─────────────────
	$hand = array(
		// reordered tokens (key before src) → canonical restores src first
		array( 'try',
			"key{$O}name{$C}{$s}src{$O}refs{$c}office{$C}",
			"src{$O}refs{$c}office{$C}{$s}key{$O}name{$C}" ),
		// stray whitespace around tokens + inside chain
		array( 'try',
			" key{$O}name{$C} {$s} src{$O} refs{$c}office {$C}",
			"src{$O}refs{$c}office{$C}{$s}key{$O}name{$C}" ),
		// join: type not first → still recognized, re-emitted leading
		array( 'join',
			"key{$O}mobile{$C}{$s}phone",
			"phone{$s}key{$O}mobile{$C}" ),
		// redundant analog == type → dropped on re-emit
		array( 'join',
			"title{$s}use{$O}title{$C}",
			'title' ),
		// explicit default analog → dropped (absent ≡ default)
		array( 'try',
			"src{$O}refs{$c}office{$C}{$s}use{$O}default{$C}",
			"src{$O}refs{$c}office{$C}" ),
		// unknown token preserved (tolerated, appended)
		array( 'try',
			"mystery{$O}x{$C}{$s}key{$O}name{$C}",
			"key{$O}name{$C}{$s}mystery{$O}x{$C}" ),
	);
	foreach ( $hand as $i => $case ) {
		list( $container, $in, $want ) = $case;
		$slot = bws_spike_parse_slot_wire( $in, $container, $G );
		check( "[$tag] P3.$i parse ok: $in", ! isset( $slot['error'] ), isset( $slot['error'] ) ? $slot['error'] : '' );
		if ( isset( $slot['error'] ) ) {
			continue;
		}
		$out = bws_spike_emit_slot( $slot, $G );
		check( "[$tag] P3.$i normalize: $in", $out === $want, "want: $want\n      got:  $out" );
	}

	// ── P4 ESCAPE: whole-tag GB round trip + JS hazard assert ───────────────
	// Slot with free-form colon (`Price: USD` label, `g:i A` format) through the
	// full GB pipeline: emit slots → build tag options string → GB PHP parse →
	// slot parse → re-emit → identical options string.
	$slot1 = array(
		'label' => 'Price: USD', 'type' => 'text',
		'chain' => array(), 'read' => array( 'kind' => 'key', 'field' => 'price' ),
		'opts' => array(), 'extra' => array(),
	);
	$slot2 = array(
		'label' => null, 'type' => 'datetime_single',
		'chain' => array(), 'read' => null,
		'opts' => array( 'key' => 'event_time', 'as' => 'time', 'format' => 'g:i A' ),
		'extra' => array(),
	);
	$v1 = bws_spike_emit_slot( $slot1, $G );
	$v2 = bws_spike_emit_slot( $slot2, $G );
	// Values already free-form-escaped by emit; the slot value itself must add
	// no further GB-structural chars.
	$options_string = '1:' . $v1 . '|2:' . $v2;
	check( "[$tag] P4 wire has escaped colon", false !== strpos( $v1, '\\:' ), "v1: $v1" );

	// JS hazard: NO unescaped ':' or '|' anywhere in either slot value.
	foreach ( array( $v1, $v2 ) as $vi => $v ) {
		check( "[$tag] P4 js-safe value $vi", ! preg_match( '/(?<!\\\\)[:|]/', $v ), "value: $v" );
	}
	// JS view: split keeps whole value (limit-2 tail-discard not tripped).
	check( "[$tag] P4 js split keeps tail", bws_spike_gb_js_value( '1:' . $v1 ) === $v1, '' );

	// GB PHP parse → per-slot parse (unescaped path) → re-emit → same wire.
	$opts = bws_spike_gb_parse_options( $options_string );
	check( "[$tag] P4 gb split slots", isset( $opts['1'], $opts['2'] ), var_export( $opts, true ) );
	if ( isset( $opts['1'], $opts['2'] ) ) {
		$p1 = bws_spike_parse_slot_wire( $opts['1'], 'table', $G );   // PHP side: GB already unescaped
		$p2 = bws_spike_parse_slot_wire( $opts['2'], 'join', $G );
		check( "[$tag] P4 label survives", ! isset( $p1['error'] ) && 'Price: USD' === $p1['label'], var_export( $p1, true ) );
		check( "[$tag] P4 format survives", ! isset( $p2['error'] ) && 'g:i A' === $p2['opts']['format'], var_export( $p2, true ) );
		if ( ! isset( $p1['error'], $p2['error'] ) ) {
			$re = '1:' . bws_spike_emit_slot( $p1, $G ) . '|2:' . bws_spike_emit_slot( $p2, $G );
			check( "[$tag] P4 full round trip", $re === $options_string, "want: $options_string\n      got:  $re" );
		}
	}
	// JS side receives ESCAPED value — parser must cope identically.
	$p1js = bws_spike_parse_slot_wire( $v1, 'table', $G );
	check( "[$tag] P4 js-side parse (escaped in)", ! isset( $p1js['error'] ) && 'Price: USD' === $p1js['label'], var_export( $p1js, true ) );

	// ── P5 BALANCE: balanced inner brackets survive; opt_sep inside brackets ─
	$bal = array(
		'label' => "Size {$O}cm{$C}", 'type' => 'text',
		'chain' => array(), 'read' => array( 'kind' => 'key', 'field' => 'size' ),
		'opts' => array(), 'extra' => array(),
	);
	$vb = bws_spike_emit_slot( $bal, $G );
	$pb = bws_spike_parse_slot_wire( $vb, 'table', $G );
	check( "[$tag] P5 balanced inner brackets", ! isset( $pb['error'] ) && $pb['label'] === "Size {$O}cm{$C}", "wire: $vb → " . var_export( $pb, true ) );

	$sepin = array(
		'label' => "Price{$s} USD", 'type' => 'text',
		'chain' => array(), 'read' => array( 'kind' => 'key', 'field' => 'price' ),
		'opts' => array(), 'extra' => array(),
	);
	$vs = bws_spike_emit_slot( $sepin, $G );
	$ps = bws_spike_parse_slot_wire( $vs, 'table', $G );
	check( "[$tag] P5 opt_sep inside bracket not split", ! isset( $ps['error'] ) && $ps['label'] === "Price{$s} USD", "wire: $vs" );

	// ── P6 ERRORS: malformed input flagged, not corrupted ──────────────────
	$bad = array(
		"label{$O}Note {$O}TBD{$C}{$s}key{$O}note{$C}X",       // trailing junk after close
		"label{$O}Note{$s}key{$O}note{$C}",                     // unbalanced open
		"key{$O}note{$C}{$C}",                                  // stray close
		// close-then-reopen inside ONE token (author typed the hop-sep as an
		// opt-sep: `src(a)+use(b)` with no real opt-sep) — found live, Spike B
		// smoke: silently parsed as a junk chain before the depth-return guard.
		"src{$O}refs{$c}office{$C}+use{$O}same{$C}",
	);
	foreach ( $bad as $i => $in ) {
		$r = bws_spike_parse_slot_wire( $in, 'table', $G );
		check( "[$tag] P6.$i flagged: $in", isset( $r['error'] ), var_export( $r, true ) );
	}
	// Unbalanced INSIDE a would-be value = unbalanced at tokenizer level.
	$r = bws_spike_tokenize( "label{$O}Note {$O}TBD{$C}", $G );
	check( "[$tag] P6.3 unbalanced free-form flagged", isset( $r['error'] ), var_export( $r, true ) );
	// Chain errors: non-numeric limit, too many tokens.
	$r = bws_spike_parse_chain( "refs{$c}office{$c}lots", $G );
	check( "[$tag] P6.4 non-numeric limit flagged", isset( $r['error'] ), var_export( $r, true ) );
	$r = bws_spike_parse_chain( "refs{$c}office{$c}5{$c}extra", $G );
	check( "[$tag] P6.5 excess step tokens flagged", isset( $r['error'] ), var_export( $r, true ) );

	// ── P7 BACKSLASH ADVERSARIAL: PHP date-format literals + author `\:` ────
	// (a) Real PHP date format with backslash-escaped literals: `l, F jS \a\t g:i A`.
	//     Our GB escape must touch ONLY `:`/`|`; `\a`/`\t` pass verbatim both ways.
	$dtf = array(
		'label' => null, 'type' => 'datetime_single',
		'chain' => array(), 'read' => null,
		'opts' => array( 'key' => 'event', 'format' => 'l, F jS \\a\\t g:i A' ),
		'extra' => array(),
	);
	$vf = bws_spike_emit_slot( $dtf, $G );
	check( "[$tag] P7.0 dt literals kept on wire", false !== strpos( $vf, '\\a\\t' ) && false !== strpos( $vf, 'g\\:i' ), "wire: $vf" );
	$pf = bws_spike_parse_slot_wire( $vf, 'join', $G );
	check( "[$tag] P7.0 dt format round trip", ! isset( $pf['error'] ) && 'l, F jS \\a\\t g:i A' === $pf['opts']['format'], var_export( $pf, true ) );
	// Full GB layer too (escaped colon inside a `\a\t`-bearing value).
	$os2 = '1:' . $vf;
	$gb2 = bws_spike_gb_parse_options( $os2 );
	$pg  = bws_spike_parse_slot_wire( $gb2['1'], 'join', $G );
	check( "[$tag] P7.0 dt format thru GB", ! isset( $pg['error'] ) && 'l, F jS \\a\\t g:i A' === $pg['opts']['format'], var_export( $pg, true ) );

	// (b) Author typed a LITERAL backslash-colon in a fallback (`g\:i` raw).
	//     Escape → `g\\:i` (their `\` kept, our escape added); GB lookbehind sees
	//     the colon as escaped (no split); unescape restores exactly one level.
	$lit = array(
		'label' => null, 'type' => 'text',
		'chain' => array(), 'read' => array( 'kind' => 'key', 'field' => 'x' ),
		'opts' => array( 'fallback' => 'g\\:i raw' ),
		'extra' => array(),
	);
	$vl = bws_spike_emit_slot( $lit, $G );
	$pl = bws_spike_parse_slot_wire( $vl, 'join', $G );
	check( "[$tag] P7.1 literal backslash-colon round trip", ! isset( $pl['error'] ) && 'g\\:i raw' === $pl['opts']['fallback'], "wire: $vl → " . var_export( isset( $pl['opts']['fallback'] ) ? $pl['opts']['fallback'] : $pl, true ) );

	// (c) STRUCTURAL GUARD: bracket-kv slot values always END with a bracket or
	//     alpha flag — never `\`. A trailing `\` before GB's `|` would read as
	//     `\|` (escaped pipe) and MERGE two slots. Assert on every emitted value
	//     incl. a fallback whose raw text ends with a backslash.
	$tail = array(
		'label' => null, 'type' => 'text',
		'chain' => array(), 'read' => array( 'kind' => 'key', 'field' => 'x' ),
		'opts' => array( 'fallback' => 'ends with \\' ),
		'extra' => array(),
	);
	$vt = bws_spike_emit_slot( $tail, $G );
	check( "[$tag] P7.2 slot value never ends with backslash", '\\' !== substr( $vt, -1 ), "wire: $vt" );
	// And the merged-slot hazard is real if violated — demonstrate the guard matters:
	$merged = bws_spike_gb_parse_options( '1:a\\|2:b' );
	check( "[$tag] P7.2 hazard demo: trailing \\ merges slots", 1 === count( $merged ), var_export( $merged, true ) );
	// Round trip of the trailing-backslash fallback itself.
	$pt = bws_spike_parse_slot_wire( $vt, 'join', $G );
	check( "[$tag] P7.2 trailing-backslash fallback round trip", ! isset( $pt['error'] ) && 'ends with \\' === $pt['opts']['fallback'], "wire: $vt → " . var_export( $pt, true ) );
}

// ── P8 LENIENT SEPARATOR CLASSES (approved grammar only) ───────────────────
// Visually-distinct canonical chars have functional twins; the parser accepts
// the CLASS, emit stays canonical (normalize-on-commit). Position disambiguates
// roles; bws_spike_grammar_validate() proves the class config is conflict-free.
function run_lenient_suite( $G, $tag ) {
	// Validator: approved config safe; a hop/step overlap must be caught.
	check( "[$tag] P8.v0 approved classes validate", array() === bws_spike_grammar_validate( $G ), implode( '; ', bws_spike_grammar_validate( $G ) ) );
	$bad_g = bws_spike_grammar( array( 'hop_class' => array( ';', ',' ) ) );   // ',' also in step_class
	check( "[$tag] P8.v1 hop∩step overlap rejected", array() !== bws_spike_grammar_validate( $bad_g ), '' );
	$bad_g = bws_spike_grammar( array( 'opt_class' => array( ';', '(' ) ) );   // bracket char as sep
	check( "[$tag] P8.v2 bracket-as-sep rejected", array() !== bws_spike_grammar_validate( $bad_g ), '' );

	// Lenient input → canonical emit. [container, lenient in, canonical want]
	$cases = array(
		// depth-0 `,` accepted as opt-sep
		array( 'try',  'src(refs,office),key(name)',        'src(refs,office);key(name)' ),
		// `[]` accepted as token bracket
		array( 'try',  'key[name]',                          'key(name)' ),
		array( 'try',  'src[refs,office];key[name]',        'src(refs,office);key(name)' ),
		// everything at once, mixed pairs + lenient opt-sep. NB the hop class is
		// STRICT (`;` only), so no hop leniency case exists — `+`/`/` are reserved
		// and must NOT parse as hops (asserted in the reserved-char suite below).
		array( 'join', 'phone,src[post,9999;refs,related_staff,5],key[mobile]',
		               'phone;src(post,9999;refs,related_staff,5);key(mobile)' ),
		// per-token delimiter rule affordance: inert OTHER pair inside a free-form
		array( 'table', 'label[Note (TBD];key(note)',       null /* parse-only check below */ ),
	);
	foreach ( $cases as $i => $case ) {
		list( $container, $in, $want ) = $case;
		$slot = bws_spike_parse_slot_wire( $in, $container, $G );
		check( "[$tag] P8.$i parse ok: $in", ! isset( $slot['error'] ), isset( $slot['error'] ) ? $slot['error'] : '' );
		if ( isset( $slot['error'] ) ) {
			continue;
		}
		if ( null === $want ) {
			check( "[$tag] P8.$i inert other-pair label", 'Note (TBD' === $slot['label'], var_export( $slot['label'], true ) );
			continue;
		}
		$out = bws_spike_emit_slot( $slot, $G );
		check( "[$tag] P8.$i normalize: $in", $out === $want, "want: $want\n      got:  $out" );
	}
	// Lone close char of the NON-canonical pair at depth 0 still errors.
	$r = bws_spike_parse_slot_wire( 'key(note)]', 'try', $G );
	check( "[$tag] P8.x stray alt-pair close flagged", isset( $r['error'] ), var_export( $r, true ) );

	// ── RESERVED CHARS: must NOT parse as hops ─────────────────────────────
	// `+` and `/` are held back for possible future roles. The guarantee under
	// test is that neither currently CARRIES hop meaning — so if one is later
	// given a job, no already-saved wire silently changes what it resolves to.
	//
	// The reserved set is declared PER-GRAMMAR ($G['reserved']), never inferred
	// from hop_class: inferring it means a char that gets wrongly re-admitted as
	// a hop also removes its own guard, so the suite goes quiet exactly when the
	// property breaks. Asserted, not skipped.
	foreach ( $G['reserved'] as $rc ) {
		check(
			"[$tag] P8.r'$rc' reserved char is not in hop_class",
			! in_array( $rc, $G['hop_class'], true ),
			"'$rc' is declared reserved but hop_class accepts it"
		);
		// Three intra-step tokens: the reserved char did NOT split, so `region`
		// falls into the limit position and must be REJECTED, never silently kept.
		$r = bws_spike_parse_slot_wire( "src(refs,office{$rc}refs,region);key(name)", 'try', $G );
		check( "[$tag] P8.r'$rc' not a hop — misparse flagged, not silent", isset( $r['error'] ), var_export( $r, true ) );

		// Two-token case: the reserved char is absorbed into the ARG verbatim and
		// MUST round-trip unchanged (it is ordinary content until it is spent).
		$r = bws_spike_parse_slot_wire( "src(refs,off{$rc}ice);key(name)", 'try', $G );
		$ok = ! isset( $r['error'] ) && isset( $r['chain'][0]['arg'] ) && "off{$rc}ice" === $r['chain'][0]['arg'] && 1 === count( $r['chain'] );
		check( "[$tag] P8.r'$rc' inert inside an arg (1 step, arg intact)", $ok, var_export( $r, true ) );
	}
}

// APPROVED chars (2026-07-31): OPT `;` · HOP `;` · STEP `,` · L1 `()` · L2 `[]`.
$G_front = bws_spike_grammar();
check( 'grammar validate: approved', array() === bws_spike_grammar_validate( $G_front ), implode( '; ', bws_spike_grammar_validate( $G_front ) ) );
run_suite( $G_front, 'approved ;;,()' );
run_lenient_suite( $G_front, 'lenient' );
// …and an alternate set (proves the grammar is not char-dependent).
$G_alt = bws_spike_grammar( array(
	'opt_sep' => ',', 'opt_class' => array( ',', ';' ),
	'hop_sep' => '/', 'hop_class' => array( '/' ),
	'step_sep' => '~', 'step_class' => array( '~' ),
	'br_open' => '[', 'br_close' => ']',
	// This set deliberately SPENDS `/` as its hop char, so only `+` stays
	// reserved here — the reserved-char property is per-grammar, not global.
	'reserved' => array( '+' ),
) );
check( 'grammar validate: alt', array() === bws_spike_grammar_validate( $G_alt ), implode( '; ', bws_spike_grammar_validate( $G_alt ) ) );
run_suite( $G_alt, 'alt ,/~[]' );

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
