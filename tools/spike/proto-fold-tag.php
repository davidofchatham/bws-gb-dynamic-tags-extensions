<?php
/**
 * SPIKE B (FW-56/57) — {{proto_fold}} proto tag: folded `{N}:` slot value +
 * per-slot intent radio (reveal lens) on a THROWAWAY tag.
 *
 * NOT SHIPPED CODE. Lives in tools/ (.distignore'd — absent from released
 * builds; loaded via a file_exists-guarded require in the main plugin file,
 * same pattern as the CLI command). Registers ONLY when the spike file is
 * present, i.e. dev checkouts / the testbed bind mount.
 *
 * What this spike proves (per .claude/plans/src-chain-encoding.md §Spike B):
 *   B1  A composite control can own ONE folded `{N}:` option key and rewrite
 *       the whole value per commit (DECISION 2), through the stock
 *       `tagSpecificControls` seam — the chain-control model.
 *   B2  The reveal-LENS (2×2 intent cell) is inferable from the WIRE alone on
 *       load/remount (Model 1 — radio ephemeral, never serialized).
 *   B3  The migrated reveal-trigger predicate works: slot N+1 gates on the
 *       FOLDED `{N}` value's presence (show_if_any on the numeric key), not the
 *       dead `{N}-key`/`{N}-use` pair.
 *   B4  `wp.components.RadioControl` (stock, stable) wires into the seam.
 *   B5  Numeric option keys ('1','2','3') survive GB registration + JS state.
 *   B6  PHP render side parses the same wire (dump renderer) — grammar shared
 *       with tools/test/slot-fold-roundtrip-spike.php (Spike A, 154/154).
 *
 * Deliberately OUT of scope: migration, real resolution through the base read
 * seam, full chain UX (multi-hop append), field discovery. The renderer DUMPS
 * the parsed structure — it proves wire comprehension, not field reads.
 *
 * Frontrunner grammar (provisional chars — see plan §FRONTRUNNER):
 *   opt-sep `;` · hop-sep `+` · step-sep `,` · L1 bracket `()`
 *   slot value = [type];src(chain);use(x)|key(x)  (canonical order)
 *
 * @package BWS_Dynamic_Tags
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Grammar (mirror of Spike A — APPROVED chars, 2026-07-31) ────────────────

// Canonical chars (emit) + lenient accept classes (parse) — opt `;`≡`,`,
// hop `;` ONLY, step `,` only, `()`≡`[]`. Roles are position-disambiguated;
// classes validated disjoint per position in the Spike A harness
// (bws_spike_grammar_validate). STEP_CLASS is NARROWED to `{,}`: the approved
// hop-sep is `;` and hop+step share a position (both inside the chain).
// HOP_CLASS is STRICT: `+` and `/` are RESERVED for possible future roles, so
// neither is accepted as a hop — a lenient class SPENDS the char, and any later
// use would change what already-saved wires mean.
const BWS_SPIKE_FOLD_OPT_SEP    = ';';
const BWS_SPIKE_FOLD_OPT_CLASS  = array( ';', ',' );
const BWS_SPIKE_FOLD_HOP_SEP    = ';';
const BWS_SPIKE_FOLD_HOP_CLASS  = array( ';' );
const BWS_SPIKE_FOLD_STEP_SEP   = ',';
const BWS_SPIKE_FOLD_STEP_CLASS = array( ',' );
const BWS_SPIKE_FOLD_BR_OPEN    = '(';
const BWS_SPIKE_FOLD_BR_CLOSE   = ')';
const BWS_SPIKE_FOLD_BR_PAIRS   = array( '(' => ')', '[' => ']' );

// Repeater ceiling. GB registers options STATICALLY, so a control can never mint
// a new option key at runtime — "add slot" can only fill a key that already
// exists. Register generously; unused keys cost one registry row each and never
// serialize (delete-omit). 8 is arbitrary-but-ample for the spike.
const BWS_SPIKE_FOLD_MAX_SLOTS  = 8;

// Slots always visible before the author adds any (the "starts with 2" rule).
const BWS_SPIKE_FOLD_MIN_SLOTS  = 2;

/**
 * Balance-aware tokenize of a slot value on the opt-sep (Spike A port).
 *
 * @param string $value Slot value (GB-unescaped).
 * @return array Token strings, or array{error} on unbalanced brackets.
 */
function bws_spike_fold_tokenize( string $value ): array {
	$toks  = array();
	$buf   = '';
	$depth = 0;
	$pair  = null;   // active structural pair (per-token delimiter rule)
	$len   = strlen( $value );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $value[ $i ];
		if ( 0 === $depth ) {
			if ( isset( BWS_SPIKE_FOLD_BR_PAIRS[ $c ] ) ) {
				$pair  = array( $c, BWS_SPIKE_FOLD_BR_PAIRS[ $c ] );
				$depth = 1;
			} elseif ( in_array( $c, BWS_SPIKE_FOLD_BR_PAIRS, true ) ) {
				return array( 'error' => 'unbalanced close bracket' );
			} elseif ( in_array( $c, BWS_SPIKE_FOLD_OPT_CLASS, true ) ) {
				$toks[] = $buf;
				$buf    = '';
				continue;
			}
		} else {
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
 * Parse one slot value into { type, chain, read, extra } (Spike A semantics,
 * agnostic-container flavor — bare leading token may be an Option-R type).
 *
 * @param string $value Slot value.
 * @return array Parsed structure or array{error}.
 */
function bws_spike_fold_parse_slot( string $value ): array {
	$toks = bws_spike_fold_tokenize( $value );
	if ( isset( $toks['error'] ) ) {
		return $toks;
	}
	$types = array( 'title', 'content', 'email', 'phone', 'permalink', 'image', 'datetime_single', 'datetime_range' );
	$slot  = array( 'type' => null, 'chain' => array(), 'read' => null, 'extra' => array() );
	$hop_re  = '/[' . preg_quote( implode( '', BWS_SPIKE_FOLD_HOP_CLASS ), '/' ) . ']/';
	$step_re = '/[' . preg_quote( implode( '', BWS_SPIKE_FOLD_STEP_CLASS ), '/' ) . ']/';
	foreach ( $toks as $tok ) {
		// Per-token delimiter rule: first accepted open char fixes the pair.
		$p    = false;
		$open = null;
		foreach ( array_keys( BWS_SPIKE_FOLD_BR_PAIRS ) as $o ) {
			$q = strpos( $tok, $o );
			if ( false !== $q && ( false === $p || $q < $p ) ) {
				$p    = $q;
				$open = $o;
			}
		}
		if ( false === $p ) {
			if ( null === $slot['type'] && in_array( $tok, $types, true ) ) {
				$slot['type'] = $tok;
			} else {
				$slot['extra'][] = $tok;
			}
			continue;
		}
		$close = BWS_SPIKE_FOLD_BR_PAIRS[ $open ];
		if ( substr( $tok, -1 ) !== $close ) {
			return array( 'error' => "token '$tok' bracket not closed at end" );
		}
		// Depth (of the active pair) must not return to 0 before the final char
		// (close-then-reopen junk guard — found live in the first smoke render).
		$depth = 0;
		$tlen  = strlen( $tok );
		for ( $i = $p; $i < $tlen; $i++ ) {
			if ( $open === $tok[ $i ] ) {
				$depth++;
			} elseif ( $close === $tok[ $i ] ) {
				$depth--;
				if ( 0 === $depth && $i < $tlen - 1 ) {
					return array( 'error' => "token '$tok' has trailing content after its value bracket" );
				}
			}
		}
		$name = substr( $tok, 0, $p );
		$val  = substr( $tok, $p + 1, strlen( $tok ) - $p - 2 );
		switch ( $name ) {
			case 'src':
				foreach ( preg_split( $hop_re, $val ) as $seg ) {
					$parts           = preg_split( $step_re, trim( $seg ) );
					$slot['chain'][] = array(
						'slug'  => trim( $parts[0] ),
						'arg'   => isset( $parts[1] ) ? trim( $parts[1] ) : null,
						'limit' => isset( $parts[2] ) ? trim( $parts[2] ) : null,
					);
				}
				break;
			case 'use':
				$slot['read'] = ( 'same' === $val )
					? array( 'kind' => 'same' )
					: array( 'kind' => 'analog', 'slug' => $val );
				break;
			case 'key':
				$slot['read'] = array( 'kind' => 'key', 'field' => $val );
				break;
			default:
				$slot['extra'][] = $tok;
		}
	}
	return $slot;
}

// ── Legacy → fold mapping (mount-reconcile + render dual-read share THIS) ───

/**
 * Synthesize a folded slot struct from LEGACY separate options
 * (`src`/`ref`/`use`/`key`, slot 1 bare, slot ≥2 `{N}-`-prefixed — the shipped
 * try_/join wire shape). ONE mapping consumed by all three recovery paths
 * (converter migrator / editor mount-reconcile / render dual-read) so the
 * position-dependent absence rules live exactly once (JS port must mirror).
 *
 * Position rules (plan §Migration b):
 *   - slot 1, read absent            → default analog (null read)
 *   - slot ≥2, read absent           → `same` (S1 synthesis from old absence)
 *   - slot ≥2, `key` set, `use` absent → FLAG unmigratable (FW-51 broken shape
 *     — old wire rendered silently empty; do NOT guess)
 *
 * @param int   $n       Slot ordinal (1-based).
 * @param array $options All tag options (GB-parsed).
 * @return array|null array{slot, legacy:true} | array{flag:string} | null (no legacy keys).
 */
function bws_spike_fold_from_legacy( int $n, array $options ) {
	$p   = ( 1 === $n ) ? '' : "{$n}-";
	$src = (string) ( $options[ "{$p}src" ] ?? '' );
	$ref = (string) ( $options[ "{$p}ref" ] ?? '' );
	$use = (string) ( $options[ "{$p}use" ] ?? '' );
	$key = (string) ( $options[ "{$p}key" ] ?? '' );
	if ( '' === $src && '' === $ref && '' === $use && '' === $key ) {
		return null;
	}

	$chain = array();
	if ( 'ref' === $src ) {
		$chain[] = array( 'slug' => 'refs', 'arg' => ( '' !== $ref ? $ref : null ), 'limit' => null );
	} elseif ( 'same' === $src ) {
		$chain[] = array( 'slug' => 'same', 'arg' => null, 'limit' => null );
	} elseif ( '' !== $src && 'current' !== $src ) {
		$chain[] = array( 'slug' => $src, 'arg' => null, 'limit' => null );
	}

	if ( 'key' === $use || ( '' === $use && '' !== $key && 1 === $n ) ) {
		$read = array( 'kind' => 'key', 'field' => $key );
	} elseif ( '' !== $use ) {
		$read = ( 'same' === $use ) ? array( 'kind' => 'same' ) : array( 'kind' => 'analog', 'slug' => $use );
	} elseif ( '' === $key ) {
		$read = ( $n >= 2 ) ? array( 'kind' => 'same' ) : null;   // S1 synth vs slot-1 default
	} else {
		// slot ≥2: key set, use absent — ambiguous FW-51 shape. Flag, never guess.
		return array( 'flag' => "legacy slot $n: key set without use (ambiguous FW-51 shape) — needs author review" );
	}

	return array(
		'slot'   => array( 'type' => null, 'chain' => $chain, 'read' => $read, 'extra' => array() ),
		'legacy' => true,
	);
}

// ── Registration ─────────────────────────────────────────────────────────────

/**
 * Register the {{proto_fold}} spike tag. Called from the guarded require site
 * in bws_dynamic_tags_register_all() — after base-shared.php is loaded, so the
 * real base builders are available for the derivation seam (B-obligation: the
 * control's source enum derives from bws_base_source_option(), not hand-authored).
 */
function bws_spike_register_proto_fold_tag(): void {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}
	static $registered = false;
	if ( $registered ) {
		return;
	}
	$registered = true;

	$options = array();
	for ( $n = 1; $n <= BWS_SPIKE_FOLD_MAX_SLOTS; $n++ ) {
		// REPEATER MODEL (2026-07-30): NO show_if_any. The earlier build gated
		// slot N on slot N-1's value (plus legacy sibling keys), which made
		// cardinality a side effect of content — a slot could not exist while
		// empty, so "add" and "configure" were the same act and out-of-order
		// REMOVE left a hole that silently re-gated everything after it.
		//
		// Under the repeater the control owns cardinality outright: slots 1..2
		// always render, slot N≥3 renders when it HOLDS A VALUE, and the add
		// button creates that value (the `src(same);use(same)` seed). Reveal is
		// therefore still "non-empty" — but the control, not the author's
		// configuration progress, decides when that becomes true.
		//
		// Registration keeps every key up to the ceiling so the control always
		// has a real key to write into; GB cannot mint option keys at runtime.
		$options[ (string) $n ] = array(
			'type'  => 'bws-proto-fold',
			/* translators: %d: slot number */
			'label' => sprintf( 'Slot %d (folded)', $n ),
		);   // B5 — numeric string option keys
	}

	// Suspect-2 probe (lockup triage 2026-07-29): render the SAME parse as plain
	// ASCII with NO markup, to test whether GB's preview response path wedges on
	// returned markup / non-ASCII (⚠ ‖ ⚑ and the <code> wrapper). Same wire, same
	// parser, different OUTPUT only — so a difference isolates the response path.
	$options['plain'] = array(
		'type'  => 'checkbox',
		'label' => 'Probe: plain ASCII output (no markup)',
	);

	new GenerateBlocks_Register_Dynamic_Tag( array(
		'title'       => 'Proto Fold (SPIKE — dev only)',
		'tag'         => 'proto_fold',
		'type'        => 'cross-source',
		'supports'    => array(),
		'description' => 'FW-56/57 spike: folded {N}: slot values + intent radio. Dump renderer — never ship.',
		'options'     => $options,
		'return'      => 'bws_spike_proto_fold_callback',
	) );
}

/**
 * Dump renderer (B6): parse each folded slot value with the shared grammar and
 * emit a structured, human-readable interpretation. Proves the PHP render side
 * reads the exact wire the JS control writes — NO field resolution (out of scope).
 *
 * @param array  $options  Parsed tag options from GB (values already unescaped).
 * @param object $block    Block instance (unused).
 * @param object $instance Tag instance (unused).
 * @return string HTML dump.
 */
function bws_spike_proto_fold_callback( $options, $block, $instance ): string {
	// Lockup forensics (2026-07-29 incident): entry/exit markers, BWS_DEBUG_PROTO-
	// gated. A wedged request shows ENTER with no EXIT + the exact wire; ENTER
	// absent entirely = the hang is upstream of the callback (GB core).
	$dbg = defined( 'BWS_DEBUG_PROTO' ) && BWS_DEBUG_PROTO;
	if ( $dbg ) {
		error_log( '[proto_fold] ENTER ' . wp_json_encode( $options ) );
	}
	// Suspect-2 probe: `plain` swaps every non-ASCII glyph + the markup wrapper
	// for ASCII. Parse path is IDENTICAL, so a wedge that only happens without
	// `plain` implicates GB's preview RESPONSE path, not our tag.
	$plain = ! empty( $options['plain'] );
	$g     = $plain
		? array( 'flag' => '(!)', 'warn' => '(X)', 'arrow' => '->', 'join' => ' || ', 'dash' => '-' )
		: array( 'flag' => '⚑', 'warn' => '⚠', 'arrow' => ' → ', 'join' => ' ‖ ', 'dash' => '—' );

	$out = array();
	for ( $n = 1; $n <= BWS_SPIKE_FOLD_MAX_SLOTS; $n++ ) {
		$raw    = isset( $options[ (string) $n ] ) ? (string) $options[ (string) $n ] : ( isset( $options[ $n ] ) ? (string) $options[ $n ] : '' );
		$legacy = false;
		if ( '' === $raw ) {
			// Render DUAL-READ: folded value absent → recover from the legacy
			// separate options via the shared mapping (unrewritten wires render).
			$rec = bws_spike_fold_from_legacy( $n, $options );
			if ( null === $rec ) {
				continue;
			}
			if ( isset( $rec['flag'] ) ) {
				$out[] = sprintf( 'slot %d %s %s', $n, $g['flag'], $rec['flag'] );
				continue;
			}
			$slot   = $rec['slot'];
			$legacy = true;
		} else {
			$slot = bws_spike_fold_parse_slot( $raw );
		}
		if ( isset( $slot['error'] ) ) {
			$out[] = sprintf( 'slot %d %s %s [raw: %s]', $n, $g['warn'], $slot['error'], $raw );
			continue;
		}
		// UNSET-MEANS-CURRENT WAS THE BUG (2026-07-30): an untouched slot ≥2 fell
		// to ambient context, so it silently RESET rather than inheriting — the
		// worst of both worlds (looks configured, resolves elsewhere). Under the
		// explicit-seed model an added slot always carries `src(same)`, so a bare
		// empty chain on slot ≥2 is now a MALFORMED wire, not a default. Flag it
		// rather than resolving it. Slot 1 has no predecessor, so empty there is
		// genuinely "current" and stays legal.
		$chain = empty( $slot['chain'] )
			? ( $n >= 2
				? $g['warn'] . ' unset (no src token — hand-edited? slot ≥2 must state src(same) or a real source)'
				: 'current' )
			: implode( $g['arrow'], array_map( static function ( $s ) use ( $g ) {
				// INCOMPLETE HOP: `refs` with no reference field has nothing to hop
				// THROUGH, so it silently resolves to nothing. Same class as the
				// unset-chain shape above — flag it rather than rendering a bare
				// `refs` that looks configured (seen in editor trial 2026-07-31,
				// wire `2:src(refs);use(same)`).
				$incomplete = ( 'refs' === $s['slug'] && ( null === $s['arg'] || '' === $s['arg'] ) );
				return $s['slug']
					. ( null !== $s['arg'] ? ':' . $s['arg'] : '' )
					. ( $incomplete ? ' ' . $g['warn'] . ' no reference field' : '' )
					. ( null !== $s['limit'] ? ' (limit ' . $s['limit'] . ')' : '' );
			}, $slot['chain'] ) );
		$read = 'default (implicit)';
		if ( $slot['read'] ) {
			$read = 'same' === $slot['read']['kind'] ? 'same (inherit)'
				: ( 'key' === $slot['read']['kind'] ? 'key:' . $slot['read']['field'] : 'analog:' . $slot['read']['slug'] );
		}
		$out[] = sprintf(
			'slot %d%s { type: %s | chain: %s | read: %s%s }',
			$n,
			$legacy ? ' (recovered from legacy)' : '',
			$slot['type'] ?: '—',
			$chain,
			$read,
			$slot['extra'] ? ' | extra: ' . implode( ' ', $slot['extra'] ) : ''
		);
	}
	if ( $dbg ) {
		error_log( '[proto_fold] EXIT ' . count( $out ) . ' slots' );
	}
	if ( ! $out ) {
		return '[proto_fold: no slots]';
	}
	$joined = implode( $g['join'], $out );
	// Plain mode: NO markup wrapper at all (the other half of the probe).
	return $plain ? esc_html( $joined ) : '<code>' . esc_html( $joined ) . '</code>';
}

/**
 * Enqueue the spike control JS (self-hooked — the ONLY main-file touch is the
 * guarded require). Localizes the source enum DERIVED from the real base
 * builder (bws_base_source_option) — the anti-drift derivation seam under test:
 * a base src-enum change must reach the proto control with no hand-edit here.
 */
function bws_spike_proto_fold_enqueue(): void {
	if ( ! class_exists( 'GenerateBlocks_Register_Dynamic_Tag' ) ) {
		return;
	}
	// Honor the A/B kill switch even if this file was somehow required.
	if ( defined( 'BWS_SPIKE_PROTO_OFF' ) && BWS_SPIKE_PROTO_OFF ) {
		return;
	}
	wp_enqueue_script(
		'bws-spike-proto-fold-control',
		BWS_DYNAMIC_TAGS_URL . 'tools/spike/proto-fold-control.js',
		// Depends on the SHIPPED field-combo control: the spike renders it directly
		// (bridged through a synthetic context) for the slot's field picker, so the
		// `window.bwsFieldComboControl` export must exist first. The spike degrades
		// to free text if it does not, so this is an ordering guarantee, not a hard
		// requirement.
		array( 'wp-hooks', 'wp-element', 'wp-components', 'wp-i18n', 'bws-dynamic-tags-field-combo-control' ),
		BWS_DYNAMIC_TAGS_VERSION . '-spike',
		true
	);
	$src_enum = array();
	if ( function_exists( 'bws_base_source_option' ) ) {
		$base     = bws_base_source_option();
		$src_enum = $base['src']['options'] ?? array();
	}
	wp_add_inline_script(
		'bws-spike-proto-fold-control',
		'window.bwsProtoFold = ' . wp_json_encode( array( 'srcOptions' => $src_enum ) ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'bws_spike_proto_fold_enqueue' );

// Lockup forensics: log every dynamic-tag-replacements request BODY before GB
// dispatches it (BWS_DEBUG_PROTO-gated). Pairs with the callback ENTER/EXIT
// markers: body logged + no ENTER = hang upstream of the callback (GB core);
// ENTER + no EXIT = hang inside the callback. Remove with the spike.
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( defined( 'BWS_DEBUG_PROTO' ) && BWS_DEBUG_PROTO
		&& false !== strpos( $request->get_route(), 'dynamic-tag-replacements' ) ) {
		error_log( '[proto_fold] REST body: ' . $request->get_body() );
	}
	return $result;
}, 10, 3 );
