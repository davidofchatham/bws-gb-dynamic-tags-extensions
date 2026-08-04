<?php
/**
 * Folded slot-value grammar — THE single PHP owner of the FW-56/57 wire.
 *
 * One option key per slot (`{{join A:…|B:…}}`), whose VALUE carries the slot's
 * whole configuration: an ordered source CHAIN, the field READ, and any per-slot
 * options. Grammar (APPROVED 2026-07-31, `.claude/plans/src-chain-encoding.md`
 * §WIRE SPEC):
 *
 *   slot value := token ( ';' token )*
 *   token      := name '(' value ')'  |  bare-name          (bracket-kv, no `=`)
 *   chain      := step ( ';' step )*                        (inside src(...), FLAT)
 *   step       := slug [ ',' arg ] [ ',' limit'['N']' ]      (limit is NAMED, not positional)
 *
 *   OPT `;` · HOP `;` · STEP `,` · L1 `()` · L2 `[]` · RESERVED `+` `/`
 *
 * Load-bearing properties, each with the reason it exists:
 *
 * - **ONE grammar owner.** The spike carried FOUR copies of these constants and
 *   all four sat on a superseded hop char at once. This file is the PHP owner;
 *   `assets/js/slot-fold-grammar.js` is its unavoidable twin (different language,
 *   so agreement must be TESTED, not assumed) and carries no independent decisions.
 * - **Bracket ALTERNATION by depth, never a pinned char.** `limit` sits one level
 *   INSIDE whatever encloses its chain, so its bracket is `bracket_pair(enclosing+1)`:
 *   `limit(3)` on a base tag's `src:` (enclosing level 0) and `limit[3]` inside a
 *   slot's `src(...)` (enclosing level 1). Same construct, two spellings — reviewed
 *   and kept, because alternation is what keeps depth trackable for nesting (FW-24).
 *   The emitter that prints the wrapper passes its own level down; nothing
 *   recomputes depth independently (both shipped-spike emitter bugs came from that).
 * - **Parse LENIENT, emit CANONICAL.** Parse accepts either bracket pair at either
 *   depth, `,` as an opt separator, and both `0`/`-1` for unlimited; emit
 *   re-canonicalizes on the next control commit. `+` and `/` are RESERVED — NOT
 *   leniently accepted, because a lenient class SPENDS the char: binding it to
 *   `hop` now would silently change what already-saved wires mean if it is ever
 *   given a job. They are ordinary CONTENT inside a value.
 * - **Reserved/grammar chars are INERT inside values.** `+ / ; , ( )` in an
 *   author's `format`/`fallback`/`label` text are content, never grammar — shipped
 *   defaults already contain them (`Date/time TBA`, `F j, Y g:i A`).
 * - **Read axis is single-valued, resolved by NAME precedence, never by order.**
 *   `use` wins when present and not `key`; otherwise `key` supplies the read. This
 *   mirrors the shipped `$use = $options['use'] ?? 'key'` dispatch, so no tag that
 *   renders today changes meaning — and it kills the order-dependence that a
 *   last-token-wins switch would re-import into bracket-kv.
 * - **Token ORDER is never semantic**, and canonical order is not restated here:
 *   emit ranks tokens through `bws_serialization_order_sort()` (FW-52's canonical
 *   KEY_MAP), so a fifth copy of the group ranks cannot drift.
 *
 * Pure: no WP or GB symbols (beyond the serialization-order helper, itself pure),
 * so `tools/test/slot-fold-test.php` loads this file rather than copying it.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Grammar ─────────────────────────────────────────────────────────────────

/** Canonical separator between tokens in a slot value. */
const BWS_FOLD_OPT_SEP = ';';
/** Lenient accept set at token level (`,` forgiven; depth disambiguates it from a step sep). */
const BWS_FOLD_OPT_CLASS = array( ';', ',' );
/** Canonical separator between chain steps. */
const BWS_FOLD_HOP_SEP = ';';
/** STRICT — `;` only. See the reserved-char note in the file header. */
const BWS_FOLD_HOP_CLASS = array( ';' );
/** Canonical separator inside one chain step (slug, arg, limit token). */
const BWS_FOLD_STEP_SEP = ',';
/**
 * STRICT — `,` only. Narrowed when `;` became the canonical hop char: hop and step
 * share a position (both inside the chain), so `;` can no longer be forgiven as a
 * step separator. Machine-checked by bws_fold_grammar_validate().
 */
const BWS_FOLD_STEP_CLASS = array( ',' );
/** Accepted bracket pairs, in alternation order: level 1 `()`, level 2 `[]`. */
const BWS_FOLD_BR_PAIRS = array( '(' => ')', '[' => ']' );
/** Chars held UNSPENT — never separators, always content. */
const BWS_FOLD_RESERVED = array( '+', '/' );

/**
 * Slot TYPE tokens — the terminal tag a slot's value is processed as, in
 * format-AGNOSTIC containers ({{join}}, {{table}}). `text` is a NON-type and is
 * never on the wire ({{text}} = {{field}} identity), so a slot with no type token
 * IS a text slot.
 */
const BWS_FOLD_TYPES = array( 'title', 'content', 'email', 'phone', 'permalink', 'image', 'datetime_single', 'datetime_range' );

/** Bare (valueless) option tokens. */
const BWS_FOLD_FLAGS = array( 'newTab', 'showCurrentYear', 'showMidnight', 'noLink' );

/**
 * Option names whose values are ARBITRARY AUTHOR TEXT. Their content is escaped on
 * emit / unescaped on parse for the GB layer, and grammar chars inside them are
 * inert — a `/` in `Date/time TBA` is not a hop.
 */
const BWS_FOLD_FREEFORM = array( 'format', 'fallback', 'sep', 'valueSep', 'rangeSep', 'timeSep', 'label' );

/** Chain step slugs that FAN OUT (one → many). Used by the legacy limit mapping. */
const BWS_FOLD_FANNING_SLUGS = array( 'refs', 'terms', 'entries' );

/**
 * Pattern a FOLDED slot key matches. Deliberately the GENERAL form, not `^[A-Z]$`:
 * bws_slot_ordinal() encodes spreadsheet-style (27 → `AA`), so a single-letter pattern
 * could reject wire its own encoder produced. There is therefore NO CAP IN THE GRAMMAR —
 * the cap is a CONTAINER property (join 10, try_ 5), and baking one container's limit into
 * the wire would mean re-cutting the pattern whenever a container changed.
 *
 * Safe against collision by construction: every option key in the plugin, and every GB
 * reserved key, is lowercase or lowercase-initial camelCase, so an all-caps key cannot be
 * anything but a slot.
 *
 * @since 1.17.0
 */
const BWS_FOLD_SLOT_KEY_RE = '/^[A-Z]+$/';

/**
 * The FOLDED slot key for slot $n — the wire spelling of a slot ordinal. `1` → `A`.
 *
 * THE SINGLE OWNER of the digit→letter mapping, because the same answer is needed by two
 * registrations, the converter migrator, the editor control, both order parsers, nine
 * option LABELS and `{{join}}`'s format tokens. Nine hand-typed `chr( 64 + $n )` calls is
 * precisely the drift the leaf/twin extractions removed.
 *
 * WHY CAPITALS AND NOT DIGITS (decided 2026-08-04): an all-digit key is a JS array-index
 * property, which ECMAScript enumerates ahead of every string key whatever order the
 * object was built in, and GB serializes with `Object.entries( extraTagParams )`. Digit
 * slots are therefore PINNED to the front of the saved string and no sort — ours or GB's —
 * can move them. Capitals hand rank back to bws_serialization_order_sort() and its JS
 * port, letting `format` and a container's tag-level options lead as
 * docs/tag-reference.md §Option order intends. The prefix itself reads slightly worse than
 * a digit; that was weighed and accepted against the ordering, and the format TOKENS
 * (`%A`) read better.
 *
 * @since 1.17.0
 * @param int $n 1-based slot ordinal.
 * @return string Slot key (`A`, `B`, … `Z`, `AA`), or '' for $n < 1.
 */
function bws_slot_ordinal( int $n ): string {
	$out = '';
	while ( $n > 0 ) {
		$r   = ( $n - 1 ) % 26;
		$out = chr( 65 + $r ) . $out;
		$n   = intdiv( $n - 1 - $r, 26 );
	}
	return $out;
}

/**
 * Inverse of bws_slot_ordinal(): `A` → 1, `AA` → 27, anything else → 0.
 *
 * The order parsers DECODE rather than compare raw strings, which is what keeps slot order
 * numeric: `AA` sorts after `Z` here but before it as a string. Nothing anywhere may rely
 * on ASCII order of these keys.
 *
 * @since 1.17.0
 * @param string $key Candidate option key.
 * @return int 1-based ordinal, or 0 when $key is not a folded slot key.
 */
function bws_slot_ordinal_num( string $key ): int {
	if ( ! preg_match( BWS_FOLD_SLOT_KEY_RE, $key ) ) {
		return 0;
	}
	$n   = 0;
	$len = strlen( $key );
	for ( $i = 0; $i < $len; $i++ ) {
		$n = $n * 26 + ( ord( $key[ $i ] ) - 64 );
	}
	return $n;
}

/**
 * The LEGACY per-slot option axes, i.e. exactly the keys bws_fold_from_legacy() reads
 * (bare for slot 1, `{N}-`-prefixed for slots ≥2). Declared as data because the migrator
 * has to STRIP the same set the mapper CONSUMES, and two hand-written lists is how a
 * dropped axis becomes silent data loss.
 *
 * `limit` is in the set but is NOT slot-level on every container — a try_ list template
 * owns a tag-level `limit` (TagTemplateRegistry::try_slot_axes()). The container's
 * tag-level exclusions are applied before this set is consumed.
 *
 * @since 1.17.0
 */
const BWS_FOLD_LEGACY_AXES = array( 'src', 'ref', 'srcTermIn', 'use', 'key', 'limit' );

/**
 * The legacy per-slot axes a container actually owns — the full set minus its TAG-LEVEL
 * axes, which are excluded at EVERY slot position.
 *
 * THE SINGLE OWNER OF THAT SUBTRACTION, because three places need the same answer and
 * they must not each compute it: the converter migrator strips these keys
 * (bws_fold_migration_slot_keys), the registered fold config ships them to the editor as
 * `legacyAxes` (bws_build_fold_slot_options), and the editor's mount migrator + control
 * read that config to decide which siblings to fold and delete. A control that kept its
 * own list deleted the tag-level `limit` off a `try_text` and the tag-level `key` off a
 * `try_datetime_single` the first time a slot was touched.
 *
 * @since 1.17.0
 * @param string[] $tag_level Axes this container owns at TAG level (never per slot).
 * @return string[] Per-slot axes, in BWS_FOLD_LEGACY_AXES order.
 */
function bws_fold_slot_legacy_axes( array $tag_level = array() ): array {
	return array_values( array_diff( BWS_FOLD_LEGACY_AXES, $tag_level ) );
}

/**
 * Is this container COMBINING ({{join}}, {{table}}) or SELECTING (`try_*`)?
 *
 * ONE derivation, and the reason it is worth a function: `container` is the
 * representation everything passes around — it names all three containers, it is what
 * bws_fold_parse_slot() takes and what the fold config carries — while `combining` is a
 * derived PROPERTY of it that several seams branch on. Every site that spelled
 * `'try' !== $x` inline was a place the two names could drift apart, and the failure is
 * silent: a wrong bool swaps what an ABSENT read means (skip vs inherit), which changes
 * rendered output with no error anywhere.
 *
 * @since 1.17.0
 * @param string $container 'try' | 'join' | 'table'.
 * @return bool True for the combining containers.
 */
function bws_fold_is_combining( string $container ): bool {
	return 'try' !== $container;
}

/**
 * {{join}}'s fold container facts — the parameters the option REGISTRATION and the
 * MIGRATOR both need.
 *
 * SINGLE OWNER, for the same reason try_'s facts live on
 * TagTemplateRegistry::try_slot_axes(): join is hand-described (there is no template
 * descriptor to derive from), so without one home bws_get_join_options() and
 * bws_fold_migration_container() are two hand-kept copies of the same four facts. They
 * disagree silently and expensively — a stale `max` leaves a slot unmigrated, a stale
 * `tag_level` folds an option the resolver reads at TAG level — and nothing fails until
 * a stored tag is rewritten wrong.
 *
 * `tag_level` is EMPTY, which is the fact worth stating rather than just asserting.
 * Join's tag-level options (mode/valueSep/format/fallback) share no name with a legacy
 * slot axis, and `limit` IS a join SLOT axis: join registered one per slot
 * (`limit`/`N-limit`) and threaded it into that slot's text resolve. try_ is the
 * opposite — a bare `limit` there caps every slot — which is why this list is
 * per-container data and not one shared constant.
 *
 * @since 1.17.0
 * @return array{container:string,combining:bool,per_slot_use:bool,max:int,tag_level:string[]}
 */
function bws_join_fold_container(): array {
	return array(
		'container'    => 'join',
		'combining'    => true,
		'per_slot_use' => true,
		'max'          => defined( 'BWS_JOIN_MAX_SLOTS' ) ? BWS_JOIN_MAX_SLOTS : 10,
		'tag_level'    => array(),
	);
}

/**
 * Bracket pair for a nesting level. Level 1 = `()`, level 2 = `[]`, alternating.
 *
 * Alternation is the mechanism, not decoration: same-char immediate nesting makes
 * depth locally unreadable, which is exactly where FW-24 (tag-in-slot) needs to
 * nest. Level 0 means "unwrapped" (a base tag's `src:` value) and has no pair.
 *
 * @param int $level 1-based nesting level.
 * @return array{0:string,1:string} [open, close]
 */
function bws_fold_bracket_pair( int $level ): array {
	$pairs = array_keys( BWS_FOLD_BR_PAIRS );
	$open  = $pairs[ ( max( 1, $level ) - 1 ) % count( $pairs ) ];
	return array( $open, BWS_FOLD_BR_PAIRS[ $open ] );
}

/**
 * Machine-check the grammar's separator classes for per-position conflicts.
 *
 * THE safety condition for lenient accept classes: roles are disambiguated by
 * POSITION, so two classes may overlap across positions (opt and step both once
 * accepted `,;`) but must be disjoint WITHIN one. hop and step share a position.
 * This caught the `;`-hop change putting `;` in two same-position classes.
 *
 * @return string[] Violation strings; empty array = safe.
 */
function bws_fold_grammar_validate(): array {
	$bad    = array();
	$br_all = array_merge( array_keys( BWS_FOLD_BR_PAIRS ), array_values( BWS_FOLD_BR_PAIRS ) );

	if ( array_intersect( BWS_FOLD_HOP_CLASS, BWS_FOLD_STEP_CLASS ) ) {
		$bad[] = 'hop_class ∩ step_class ≠ ∅';
	}
	$classes = array(
		'opt_class'  => BWS_FOLD_OPT_CLASS,
		'hop_class'  => BWS_FOLD_HOP_CLASS,
		'step_class' => BWS_FOLD_STEP_CLASS,
	);
	foreach ( $classes as $name => $class ) {
		if ( array_intersect( $class, $br_all ) ) {
			$bad[] = "$name contains a bracket char";
		}
		if ( array_intersect( $class, BWS_FOLD_RESERVED ) ) {
			$bad[] = "$name accepts a RESERVED char";
		}
	}
	$canon = array(
		'opt_sep'  => array( BWS_FOLD_OPT_SEP, BWS_FOLD_OPT_CLASS ),
		'hop_sep'  => array( BWS_FOLD_HOP_SEP, BWS_FOLD_HOP_CLASS ),
		'step_sep' => array( BWS_FOLD_STEP_SEP, BWS_FOLD_STEP_CLASS ),
	);
	foreach ( $canon as $name => $pair ) {
		if ( ! in_array( $pair[0], $pair[1], true ) ) {
			$bad[] = "$name not in its accept class";
		}
	}
	return $bad;
}

// ── Escaping (the GB layer) ─────────────────────────────────────────────────

/**
 * Escape the GB-structural chars in a value. `:` and `|` split GB's own option
 * grammar; a raw one inside a slot value merges slots or truncates the value (the
 * GB JS parser splits with limit 2 and DISCARDS the tail).
 *
 * @param string $value Raw author text.
 * @return string Wire form.
 */
function bws_fold_escape( string $value ): string {
	return preg_replace( '/([:|])/', '\\\\$1', $value );
}

/**
 * Reverse of bws_fold_escape(). Applied at parse so the struct always holds raw
 * author text: GB's PHP parser already unescaped values before a callback sees
 * them, but the JS side never does, so both arrive here and both must normalize.
 *
 * @param string $value Wire form.
 * @return string Raw author text.
 */
function bws_fold_unescape( string $value ): string {
	return preg_replace( '/\\\\([:|])/', '$1', $value );
}

// ── Splitting (bracket-aware; one implementation, three positions) ──────────

/**
 * Split a string on a separator CLASS, ignoring separators inside brackets.
 *
 * Per-token delimiter rule: the first accepted OPEN char fixes the active
 * structural pair until it closes; the other pair is inert text inside it (so an
 * author can pick the pair their content avoids — `label[Note (TBD]` is legal).
 *
 * Used at all three positions (slot tokens, chain hops, intra-step). It is
 * bracket-aware at the STEP position too, which the spike's naive `preg_split`
 * was not: `limit[5]` survived a naive split only because a bare integer cannot
 * contain a separator, and the moment a step token carries free-form or nested
 * content (FW-24's whole-tag-in-slot) a naive split shreds it mid-token.
 *
 * @param string   $value Input.
 * @param string[] $class Accepted separator chars.
 * @return array{0:array<int,string>}|array{error:string} Segments (raw, untrimmed) or an error.
 */
function bws_fold_split_depth( string $value, array $class ) {
	$segments = array();
	$buf      = '';
	$depth    = 0;
	$pair     = null;
	$len      = strlen( $value );

	for ( $i = 0; $i < $len; $i++ ) {
		$c = $value[ $i ];
		if ( 0 === $depth ) {
			if ( isset( BWS_FOLD_BR_PAIRS[ $c ] ) ) {
				$pair  = array( $c, BWS_FOLD_BR_PAIRS[ $c ] );
				$depth = 1;
			} elseif ( in_array( $c, BWS_FOLD_BR_PAIRS, true ) ) {
				return array( 'error' => "unbalanced close bracket at $i" );
			} elseif ( in_array( $c, $class, true ) ) {
				$segments[] = $buf;
				$buf        = '';
				continue;
			}
		} elseif ( $c === $pair[0] ) {
			$depth++;
		} elseif ( $c === $pair[1] ) {
			$depth--;
		}
		$buf .= $c;
	}
	if ( 0 !== $depth ) {
		return array( 'error' => 'unbalanced open bracket' );
	}
	$segments[] = $buf;
	return $segments;
}

/**
 * Split a slot value into its tokens (empties dropped, each trimmed).
 *
 * @param string $value Slot value.
 * @return array<int,string>|array{error:string}
 */
function bws_fold_tokenize( string $value ) {
	$segments = bws_fold_split_depth( $value, BWS_FOLD_OPT_CLASS );
	if ( isset( $segments['error'] ) ) {
		return $segments;
	}
	return array_values( array_filter( array_map( 'trim', $segments ), 'strlen' ) );
}

/**
 * Parse one token into `name` + `val` (`val` null for a bare token).
 *
 * The bracket opened after the name must be the one closed by the FINAL char:
 * depth may not return to 0 early. That guard catches close-then-reopen junk
 * arriving as ONE token (`src(a)+use(b)` — an author typing a reserved char where
 * an opt separator belongs), which otherwise parses as a plausible-looking chain.
 *
 * @param string $tok Token string (trimmed).
 * @return array{name:string,val:?string}|array{error:string}
 */
function bws_fold_parse_token( string $tok ) {
	$at   = false;
	$open = null;
	foreach ( array_keys( BWS_FOLD_BR_PAIRS ) as $candidate ) {
		$pos = strpos( $tok, $candidate );
		if ( false !== $pos && ( false === $at || $pos < $at ) ) {
			$at   = $pos;
			$open = $candidate;
		}
	}
	if ( false === $at ) {
		return array( 'name' => $tok, 'val' => null );
	}
	$close = BWS_FOLD_BR_PAIRS[ $open ];
	if ( substr( $tok, -1 ) !== $close ) {
		return array( 'error' => "token '$tok' bracket not closed at end" );
	}
	$depth = 0;
	$len   = strlen( $tok );
	for ( $i = $at; $i < $len; $i++ ) {
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
		'name' => substr( $tok, 0, $at ),
		'val'  => substr( $tok, $at + 1, strlen( $tok ) - $at - 2 ),
	);
}

// ── Chain (the ordered source steps) ───────────────────────────────────────

/**
 * Parse a chain value into ordered steps.
 *
 * Step shape: a positional SLUG, an optional positional ARG, and any number of
 * NAMED bracket-kv tokens (`limit[5]` today; unknown names are preserved verbatim
 * so a future keyword does not break an older parser). `limit` is named rather
 * than positional because argless-with-limit is a reachable state, so a positional
 * encoding needs an interior hole (`entries,,5`) that fails SILENTLY when it is
 * missing — `entries,5` parses as arg=5.
 *
 * `limit` accepts `0` and `-1` (both mean unlimited in the wild — GB uses `-1`,
 * WP's Query Loop uses `0`); emit normalizes to `0`. A non-numeric limit is a
 * grammar error here, NOT a silent 0: under `0 = unlimited`, `(int)'abc' === 0`
 * would fan out an entire relationship from a typo.
 *
 * @param string $chain_str Chain value (inside `src(...)` or after `src:`).
 * @return array<int,array{slug:string,arg:?string,limit:?string,extra:array}>|array{error:string}
 */
function bws_fold_parse_chain( string $chain_str ) {
	$hops = bws_fold_split_depth( $chain_str, BWS_FOLD_HOP_CLASS );
	if ( isset( $hops['error'] ) ) {
		return $hops;
	}

	$steps = array();
	foreach ( $hops as $hop ) {
		$hop = trim( $hop );
		if ( '' === $hop ) {
			continue;
		}
		$parts = bws_fold_split_depth( $hop, BWS_FOLD_STEP_CLASS );
		if ( isset( $parts['error'] ) ) {
			return $parts;
		}
		$parts = array_map( 'trim', $parts );

		$step       = array(
			'slug'  => $parts[0],
			'arg'   => null,
			'limit' => null,
			'extra' => array(),
		);
		$positional = 0;
		foreach ( array_slice( $parts, 1 ) as $part ) {
			if ( '' === $part ) {
				continue;   // interior/trailing empty normalizes to absent, never ''.
			}
			$tok = bws_fold_parse_token( $part );
			if ( isset( $tok['error'] ) ) {
				return $tok;
			}
			if ( null === $tok['val'] ) {
				$positional++;
				if ( $positional > 1 ) {
					return array( 'error' => "chain step '$hop': unexpected extra positional token '$part'" );
				}
				$step['arg'] = $part;
				continue;
			}
			if ( 'limit' === $tok['name'] ) {
				if ( ! is_numeric( $tok['val'] ) ) {
					return array( 'error' => "chain step '$hop': limit '{$tok['val']}' is not numeric" );
				}
				// Both `0` and `-1` are read as unlimited (GB uses `-1`, WP's Query
				// Loop uses `0`); the STRUCT holds one representation so nothing
				// downstream has to know both. Canonical `0` is what emit writes back.
				$step['limit'] = (string) max( 0, (int) $tok['val'] );
				continue;
			}
			$step['extra'][] = $part;
		}
		if ( '' === $step['slug'] ) {
			return array( 'error' => "chain step '$hop': missing slug" );
		}
		$steps[] = $step;
	}
	return $steps;
}

/**
 * Emit ordered steps as a chain value.
 *
 * @param array $steps           Step structs.
 * @param int   $enclosing_level Nesting level of whatever WRAPS this chain: 0 for a
 *                               base tag's `src:` (unwrapped), 1 for a slot's
 *                               `src(...)`. `limit` is emitted one level inside it —
 *                               the caller that prints the wrapper owns this number;
 *                               never recompute depth locally.
 * @return string Chain value.
 */
function bws_fold_emit_chain( array $steps, int $enclosing_level = 1 ): string {
	list( $open, $close ) = bws_fold_bracket_pair( $enclosing_level + 1 );

	$segments = array();
	foreach ( $steps as $step ) {
		$segment = $step['slug'];
		$arg     = $step['arg'] ?? null;
		if ( null !== $arg && '' !== $arg ) {
			$segment .= BWS_FOLD_STEP_SEP . $arg;
		}
		$limit = $step['limit'] ?? null;
		if ( null !== $limit && '' !== $limit ) {
			// 0 = unlimited and MUST survive as a literal: an author who pinned "all"
			// silently reverts if a falsy guard drops it. Negative forms normalize to 0.
			$normalized = (int) $limit;
			$segment   .= BWS_FOLD_STEP_SEP . 'limit' . $open . ( $normalized < 0 ? 0 : $normalized ) . $close;
		}
		foreach ( $step['extra'] ?? array() as $extra ) {
			$segment .= BWS_FOLD_STEP_SEP . $extra;
		}
		$segments[] = $segment;
	}
	return implode( BWS_FOLD_HOP_SEP, $segments );
}

// ── Slot value ──────────────────────────────────────────────────────────────

/**
 * Parse a folded slot value into its structure.
 *
 * Container shapes the parse in exactly two places, both already-settled
 * container sensitivities rather than new ones:
 *   - a TYPE token leads only in format-AGNOSTIC containers ({{join}}, {{table}});
 *     `try_*` slots inherit the template's fixed type, so a bare type word there is
 *     an unknown token and is preserved as such;
 *   - on a `datetime_*` type, `key` is that tag's own date field option, not the
 *     read (the read of a datetime slot is the type).
 *
 * Both the read axis and the datetime `key` meaning are resolved AFTER the token
 * loop, never inside it: a hand-edited wire may state the type last, and assigning
 * during the scan is what made the spike's read order-dependent.
 *
 * @param string $value     Slot value (escaped or unescaped — normalized here).
 * @param string $container 'try' (format-fixed) | 'join' | 'table' (agnostic).
 * @return array{label:?string,type:?string,chain:array,read:?array,opts:array,extra:array}|array{error:string}
 */
function bws_fold_parse_slot( string $value, string $container = 'join' ) {
	$tokens = bws_fold_tokenize( $value );
	if ( isset( $tokens['error'] ) ) {
		return $tokens;
	}

	$agnostic = bws_fold_is_combining( $container );
	$slot     = array(
		'label' => null,
		'type'  => null,
		'chain' => array(),
		'read'  => null,
		'opts'  => array(),
		'extra' => array(),
	);
	$use_tok  = null;
	$key_tok  = null;

	foreach ( $tokens as $token ) {
		$parsed = bws_fold_parse_token( $token );
		if ( isset( $parsed['error'] ) ) {
			return $parsed;
		}
		$name = $parsed['name'];
		$val  = $parsed['val'];

		if ( null === $val ) {
			if ( $agnostic && null === $slot['type'] && in_array( $name, BWS_FOLD_TYPES, true ) ) {
				$slot['type'] = $name;
			} elseif ( in_array( $name, BWS_FOLD_FLAGS, true ) ) {
				$slot['opts'][ $name ] = true;
			} else {
				$slot['extra'][] = $token;
			}
			continue;
		}

		switch ( $name ) {
			case 'label':
				$slot['label'] = $val;
				break;
			case 'src':
				$chain = bws_fold_parse_chain( $val );
				if ( isset( $chain['error'] ) ) {
					return $chain;
				}
				$slot['chain'] = $chain;
				break;
			case 'use':
				$use_tok = $val;
				break;
			case 'key':
				$key_tok = $val;
				break;
			default:
				if ( isset( bws_serialization_order_key_map()[ $name ] ) ) {
					$slot['opts'][ $name ] = $val;
				} else {
					$slot['extra'][] = $token;
				}
		}
	}

	// `key` on a datetime slot is that tag's own field option, not the read.
	$is_datetime = null !== $slot['type'] && 0 === strpos( (string) $slot['type'], 'datetime' );
	if ( $is_datetime && null !== $key_tok ) {
		$slot['opts']['key'] = $key_tok;
		$key_tok             = null;
	}

	// Read axis — NAME precedence, order-independent, mirroring the shipped
	// `$use = $options['use'] ?? 'key'` dispatch: `use` is consulted first and
	// `key` is read only in the keyed arm. Both-present is not author error (GB
	// cannot unset one option from another's value, so a stale `key` legitimately
	// rides the wire), so it resolves rather than flagging.
	if ( null !== $use_tok && 'key' !== $use_tok ) {
		$slot['read'] = ( 'same' === $use_tok )
			? array( 'kind' => 'same' )
			: array( 'kind' => 'analog', 'slug' => $use_tok );
	} elseif ( null !== $key_tok ) {
		$slot['read'] = array( 'kind' => 'key', 'field' => $key_tok );
	} elseif ( 'key' === $use_tok ) {
		// Legacy-shaped `use(key)` with no key token: tolerate on parse, never emit.
		$slot['read'] = array( 'kind' => 'key', 'field' => '' );
	}

	if ( null !== $slot['label'] ) {
		$slot['label'] = bws_fold_unescape( $slot['label'] );
	}
	foreach ( $slot['opts'] as $name => $val ) {
		if ( true !== $val && in_array( $name, BWS_FOLD_FREEFORM, true ) ) {
			$slot['opts'][ $name ] = bws_fold_unescape( $val );
		}
	}
	return $slot;
}

/**
 * Emit a slot structure as its canonical wire value.
 *
 * Order: `label` → type → canonically-ranked tokens → preserved unknowns. The rank
 * comes from bws_serialization_order_sort() (FW-52's canonical KEY_MAP), so the
 * fold adds no fifth copy of the group ranks. Redundancy drops: the `default`
 * analog (absent IS default), and an analog naming the slot's own type.
 *
 * @param array $slot  Slot struct (as parsed).
 * @param int   $level Nesting level of the slot's own tokens — 1 in a slot value.
 * @return string Canonical slot value.
 */
function bws_fold_emit_slot( array $slot, int $level = 1 ): string {
	list( $open, $close ) = bws_fold_bracket_pair( $level );

	$values = array();
	if ( ! empty( $slot['chain'] ) ) {
		$values['src'] = bws_fold_emit_chain( $slot['chain'], $level );
	}
	$read = $slot['read'] ?? null;
	if ( $read ) {
		if ( 'same' === $read['kind'] ) {
			$values['use'] = 'same';
		} elseif ( 'key' === $read['kind'] ) {
			if ( '' !== $read['field'] ) {
				$values['key'] = $read['field'];
			}
		} elseif ( 'analog' === $read['kind']
			&& 'default' !== $read['slug']
			&& ( $slot['type'] ?? null ) !== $read['slug'] ) {
			$values['use'] = $read['slug'];
		}
	}
	foreach ( $slot['opts'] ?? array() as $name => $val ) {
		$values[ $name ] = $val;
	}

	$emit_one = static function ( $name, $val ) use ( $open, $close ) {
		if ( true === $val ) {
			return $name;
		}
		if ( in_array( $name, BWS_FOLD_FREEFORM, true ) ) {
			$val = bws_fold_escape( (string) $val );
		}
		return $name . $open . $val . $close;
	};

	$tokens = array();
	if ( null !== ( $slot['label'] ?? null ) && '' !== $slot['label'] ) {
		$tokens[] = $emit_one( 'label', $slot['label'] );
		unset( $values['label'] );
	}
	if ( null !== ( $slot['type'] ?? null ) && 'text' !== $slot['type'] ) {
		$tokens[] = $slot['type'];
	}
	foreach ( bws_serialization_order_sort( array_keys( $values ) ) as $name ) {
		$tokens[] = $emit_one( $name, $values[ $name ] );
	}
	foreach ( $slot['extra'] ?? array() as $extra ) {
		$tokens[] = $extra;
	}
	return implode( BWS_FOLD_OPT_SEP, $tokens );
}

// ── Legacy → fold mapping (ONE mapping, three consumers) ───────────────────

/**
 * Build a folded slot struct from the LEGACY separate option keys.
 *
 * ONE mapping shared by the converter migrator, the editor mount-reconcile and
 * the render dual-read, so the position-dependent absence rules exist exactly
 * once (the JS twin mirrors it). Reads all SIX legacy keys — `srcTermIn` and
 * `limit` are as load-bearing as `src`/`ref`/`use`/`key`, and dropping either is
 * an output change.
 *
 * ABSENCE IS CONTAINER-SENSITIVE ON THE READ AXIS, and only there:
 *   - SELECTING (`try_*`) with a per-slot read axis: an absent read at slot ≥2
 *     inherits (the resolver carries `$last_use`/`$last_key` forward), so it
 *     materializes as `use(same)` — identical behaviour, intent now explicit. A
 *     slot whose ONLY legacy content was a `key` is SKIPPED by the shipped
 *     resolver (the key is discarded first, then `$has_new` is false), so it maps
 *     to nothing at all — this is the FW-51 shape, faithfully preserved.
 *     That materialization is COSMETIC — an absent read and `use(same)` resolve
 *     identically in a selecting container — so it is written only where the UI
 *     can show it: `$per_slot_use`. The four try_ templates with no per-slot read
 *     axis at all (title/permalink/datetime_*, whose read is a TAG-level option)
 *     get no read token, because `use(same)` there names an axis the tag does not
 *     have. Their per-slot key inherit (email/phone, per-slot key WITHOUT a `use`
 *     enum) is spelled the same way: absent read, key carried by the accumulator.
 *   - COMBINING ({{join}}, {{table}}) has NO read carry-forward: an absent read
 *     means UNCONFIGURED and the slot is skipped. It maps to an UNSET read, not
 *     to `same`. Synthesizing `same` here would make a previously-skipped slot
 *     render AND — because the shipped `continue` precedes the carry-forward —
 *     re-point a LATER slot's `src(same)` at this slot's source, changing output
 *     at slots the migration never touched.
 *
 * `limit` attaches to the LAST FANNING step, which is unambiguous because legacy
 * data cannot fan twice (there was no chain syntax before the fold). With no
 * fanning step the chain has nothing to cap, so the limit stays a slot-level
 * token — that case caps a multi-value READ rather than a hop, and is the one
 * meaning a slot-level `limit` still has.
 *
 * @param int   $n            Slot ordinal (1-based).
 * @param array $options      All tag options (GB-parsed).
 * @param bool  $combining    True for {{join}}/{{table}}; false for `try_*`. Derive it
 *                            with bws_fold_is_combining(), never by comparing the
 *                            container string at the call site.
 * @param bool  $per_slot_use True when the container gives each slot its own read
 *                            axis (try_ templates with per_slot_use). Ignored for
 *                            combining containers.
 * @return array{slot:array,legacy:bool}|null Null when this slot holds no legacy
 *                            keys, or when the shipped resolver skips it entirely.
 */
function bws_fold_from_legacy( int $n, array $options, bool $combining = false, bool $per_slot_use = true ) {
	$prefix = ( 1 === $n ) ? '' : "{$n}-";
	$src    = trim( (string) ( $options[ "{$prefix}src" ] ?? '' ) );
	$ref    = trim( (string) ( $options[ "{$prefix}ref" ] ?? '' ) );
	$tax    = trim( (string) ( $options[ "{$prefix}srcTermIn" ] ?? '' ) );
	$use    = trim( (string) ( $options[ "{$prefix}use" ] ?? '' ) );
	$key    = trim( (string) ( $options[ "{$prefix}key" ] ?? '' ) );
	$limit  = trim( (string) ( $options[ "{$prefix}limit" ] ?? '' ) );

	if ( '' === $src && '' === $ref && '' === $tax && '' === $use && '' === $key && '' === $limit ) {
		return null;
	}

	// An explicitly key-moded selecting slot ≥2 with NO key of its own BORROWED the
	// carried key under the flat resolver: the picker sat there empty and the slot
	// rendered a field named somewhere else — FW-51's ambiguity from the other side.
	// Read it as the inherit it was, so `use(same)` carries both axes. That reproduces
	// the shipped result whenever the carried read was itself key-moded, which is every
	// shape the shipped UI could author: a slot in an ANALOG mode hides its key field,
	// so a stale key surviving behind one is not a state the editor can produce.
	if ( ! $combining && $per_slot_use && $n >= 2 && 'key' === $use && '' === $key ) {
		$use = '';
	}

	// A selecting slot ≥2 with an absent read DISCARDS any stale key (the shipped
	// resolver does this before testing whether the slot has new content at all).
	$read_absent = ( '' === $use );
	if ( ! $combining && $per_slot_use && $n >= 2 && $read_absent ) {
		$key = '';
		if ( '' === $src && '' === $ref && '' === $tax ) {
			return null;   // shipped: $has_new false → slot skipped. Preserve the skip.
		}
	}

	// ── src axis → chain ────────────────────────────────────────────────────
	$chain = array();
	$step  = static function ( string $slug, ?string $arg = null ): array {
		return array( 'slug' => $slug, 'arg' => $arg, 'limit' => null, 'extra' => array() );
	};
	if ( 'ref' === $src ) {
		// An orphan `ref` (src:ref with no field) is already dead at render; the
		// incomplete step preserves that rather than inventing a source.
		$chain[] = $step( 'refs', '' !== $ref ? $ref : null );
	} elseif ( 'same' === $src || ( '' === $src && $n >= 2 ) ) {
		// Legacy absence at slot ≥2 already MEANT inherit, in both containers —
		// only the read axis diverges. Materialize the sentinel.
		$chain[] = $step( 'same' );
	} elseif ( '' !== $src ) {
		// An EXPLICIT `current` becomes a step like any other source value, even though an
		// empty chain also resolves against the ambient entity. Treating it as "nothing"
		// DELETED slots: on a container with no per-slot read axis (try_permalink,
		// try_title, try_datetime_*) a fallback attempt whose entire content was
		// `{N}-src:current` folded to an empty struct, which emits the empty string, which
		// means the slot key is never written — the attempt vanished. It renders under the
		// legacy wire and under `{N}:src(current)`, verified both ways on the testbed, so
		// the step is the output-preserving mapping and the omission was a bug.
		$chain[] = $step( $src );
	}
	if ( '' !== $tax ) {
		// srcTermIn always FOLLOWS ref: the term hop needs a post input, which the
		// ref step produces (issue #44's order, one global rule in one builder).
		$chain[] = $step( 'terms', $tax );
	}

	// ── read axis ──────────────────────────────────────────────────────────
	if ( '' !== $use ) {
		if ( 'same' === $use ) {
			$read = array( 'kind' => 'same' );
		} elseif ( 'key' === $use ) {
			$read = array( 'kind' => 'key', 'field' => $key );
		} else {
			$read = array( 'kind' => 'analog', 'slug' => $use );
		}
	} elseif ( '' !== $key ) {
		// `use` absent with a key set is the CANONICAL wire for a keyed read (`use`
		// is _strip_default with `key` first) — not an ambiguous shape.
		$read = array( 'kind' => 'key', 'field' => $key );
	} elseif ( $combining ) {
		$read = null;   // UNCONFIGURED — the shipped resolver skips this slot.
	} else {
		// Selecting: absent read inherits either way, so materialize the sentinel
		// only where a read axis exists to show it (see the docblock).
		$read = ( $n >= 2 && $per_slot_use ) ? array( 'kind' => 'same' ) : null;
	}

	// ── limit → last fanning step, else slot-level ─────────────────────────
	$opts = array();
	if ( '' !== $limit && is_numeric( $limit ) ) {
		// 0 / -1 were CLAMPED to 1 by the old rule, never designed to mean 1: an
		// author wanting one result types 1 or leaves it unset. Honor the written
		// value under the new semantics (0 = unlimited) rather than freezing a clamp.
		$normalized = (int) $limit;
		$normalized = ( $normalized < 0 ) ? 0 : $normalized;
		$last_fan   = null;
		foreach ( $chain as $i => $chain_step ) {
			if ( in_array( $chain_step['slug'], BWS_FOLD_FANNING_SLUGS, true ) ) {
				$last_fan = $i;
			}
		}
		if ( null !== $last_fan ) {
			$chain[ $last_fan ]['limit'] = (string) $normalized;
		} else {
			$opts['limit'] = (string) $normalized;
		}
	}

	return array(
		'slot'   => array(
			'label' => null,
			'type'  => null,
			'chain' => $chain,
			'read'  => $read,
			'opts'  => $opts,
			'extra' => array(),
		),
		'legacy' => true,
	);
}

// ── Render seam (folded struct → the shipped flat read) ────────────────────

/**
 * Read slot $n as a struct, whichever wire ERA it is stored in (the render/preview
 * DUAL-READ).
 *
 * Mode purity is a property of a MIGRATED tag, not of the reader: a half-applied
 * migration or a hand-edit can leave slot 2 folded between legacy slots 1 and 3, and
 * a reader that picked one era per TAG would drop half of it. So the era is decided
 * PER SLOT — folded value present ⇒ parse it; absent ⇒ recover through
 * bws_fold_from_legacy(). Both feed the same caller-held carry accumulator, which is
 * what makes a mixed-era wire resolve as its author last saw it.
 *
 * Malformed folded wire returns null (the slot contributes nothing) rather than
 * falling back to the legacy keys: the folded value is the author's INTENT, and
 * silently rendering a stale legacy sibling instead would be a wrong answer dressed
 * as a working one.
 *
 * SLOT 1 OF A SELECTING CONTAINER IS NEVER ABSENT. Every axis unset there IS a
 * configuration — the ambient source read through the template's default — which is
 * what a bare `{{try_title}}` renders. Returning null would make the first attempt
 * of an unconfigured try_ tag vanish. A COMBINING container needs no exception: an
 * empty struct has no read, and an absent read means unconfigured there, so
 * bws_fold_slot_flat_options() skips it one step later anyway.
 *
 * @since 1.17.0
 * @param int    $n            Slot ordinal (1-based).
 * @param array  $options      All tag options (GB-parsed).
 * @param string $container    'try' (selecting) | 'join' | 'table' (combining).
 * @param bool   $per_slot_use True when the container gives each slot its own read
 *                             axis. Ignored for combining containers.
 * @return array|null Slot struct, or null when this slot holds nothing (or unparsable
 *                    folded wire).
 */
function bws_fold_slot_struct( int $n, array $options, string $container = 'join', bool $per_slot_use = true ) {
	$raw = trim( (string) ( $options[ bws_slot_ordinal( $n ) ] ?? '' ) );
	if ( '' !== $raw ) {
		$parsed = bws_fold_parse_slot( $raw, $container );
		return isset( $parsed['error'] ) ? null : $parsed;
	}
	$rec = bws_fold_from_legacy( $n, $options, bws_fold_is_combining( $container ), $per_slot_use );
	if ( $rec && isset( $rec['slot'] ) ) {
		return $rec['slot'];
	}
	return ( 1 === $n && 'try' === $container ) ? bws_fold_empty_slot() : null;
}

/**
 * An empty slot struct in the grammar's shape (the PHP twin of the control's
 * emptySlot()). Every axis unset: no chain, no read, no options.
 *
 * @since 1.17.0
 * @return array Slot struct.
 */
function bws_fold_empty_slot(): array {
	return array(
		'label' => null,
		'type'  => null,
		'chain' => array(),
		'read'  => null,
		'opts'  => array(),
		'extra' => array(),
	);
}

/**
 * Flatten one folded slot into the FLAT option set the shipped read seam consumes,
 * threading the caller's carry-forward accumulator.
 *
 * This is the whole adapter between the folded wire and the engine as it ships: a
 * chain of steps becomes `src`/`ref`/`srcTermIn` (+ `limit`), and the read axis
 * becomes `use`/`key`. Every container's slot loop goes through here, so the
 * carry-forward rules exist exactly once — three copies of "'' src inherits" is what
 * the pre-fold code had (join's loop, try_'s loop, the join PREVIEW walk), and the
 * preview copy had already drifted from the renderer it claimed to match.
 *
 * ACCUMULATOR. `$carry` is `{src, ref, use, key}` and is updated ONLY by a slot that
 * actually resolves. A skipped slot must not feed it: shipped join `continue`s before
 * its carry-forward, so a half-configured slot 2 leaves slot 3 inheriting slot 1 —
 * feeding the accumulator first would re-point slot 3 at a source the author never
 * chose. `ref` is passed through even under a non-`ref` source (inert there, but a
 * later slot carrying back to the same relationship needs it — shipped behaviour).
 *
 * CONTAINER SENSITIVITY IS ON THE READ AXIS, AND ONLY THERE — specifically on what
 * ABSENCE means. An explicit `use(same)` inherits in BOTH containers (so the read is
 * always tracked in the accumulator); an ABSENT read is UNCONFIGURED in a combining
 * container (skip the slot) and INHERIT in a selecting one.
 *
 * INEXPRESSIBLE CHAINS SKIP THE SLOT. The flat triple holds ONE ref hop and ONE term
 * hop; a second relationship hop or a repeater `entries` step (both legal wire, both
 * reachable only by hand-editing) cannot be represented. Rendering the expressible
 * PREFIX would silently read a different source than the wire states, so the slot
 * renders nothing instead.
 *
 * The 1.17.0 chain COMPILER (5h, slot-fold-compile.php) does NOT lift this: it gave the
 * ENGINE arbitrary hops, but a slot's output is produced by its container's ARMS — the
 * term-hop arm, the site arm, the list-mode gate, the ref plural path — and each of them
 * dispatches on the flat `src`/`srcTermIn` TOKENS this function returns. A chain with no
 * flat spelling has no token to dispatch on, and inventing the nearest one is the
 * truncated-prefix hazard by another route. Slots gain multi-hop chains when those arms
 * dispatch on the chain's TERMINAL STEP KIND instead (the verb-agnostic resolver
 * refactor), not when the compiler lands. Depth-0 chains DO resolve today, through
 * bws_field_values_assemble_steps().
 *
 * WHY THE SKIP REASON IS AN OUT-PARAM. The editor PREVIEW has to tell the two skips
 * apart — an unconfigured slot is a normal in-progress state and says nothing, while an
 * inexpressible chain is wire that will never render and has to be FLAGGED, or the author
 * reads a preview that silently omits a slot they configured. Deriving the reason in the
 * preview would be a second copy of the skip rule, i.e. the exact drift this seam removed,
 * so the reason is reported BY THE OWNER. Optional and by reference: the render callers
 * pass nothing and are unaffected.
 *
 * @since 1.17.0
 * @param array  $slot        Slot struct (bws_fold_parse_slot / bws_fold_from_legacy shape).
 * @param array  $carry       Carry-forward accumulator, BY REFERENCE: {src,ref,use,key}.
 * @param bool   $combining   True for {{join}}/{{table}}; false for `try_*`. Derive it
 *                            with bws_fold_is_combining() rather than at the call site.
 * @param string $skip_reason OUT, by reference. '' when the slot resolves; 'read' when a
 *                            combining slot has no read configured; 'chain' when the chain
 *                            has no flat spelling.
 * @return array|null Flat options ({src,ref,srcTermIn,use,key} + optional limit), or
 *                    null when the slot is skipped (unconfigured / inexpressible).
 */
function bws_fold_slot_flat_options( array $slot, array &$carry, bool $combining, &$skip_reason = null ) {
	$skip_reason = '';
	$carry += array( 'src' => '', 'ref' => '', 'use' => '', 'key' => '' );

	// ── read axis ──────────────────────────────────────────────────────────
	$read = $slot['read'] ?? null;
	if ( null === $read ) {
		if ( $combining ) {
			$skip_reason = 'read';
			return null;   // UNCONFIGURED — shipped combining resolvers skip, before carry.
		}
		$use = $carry['use'];
		$key = $carry['key'];
	} else {
		switch ( $read['kind'] ?? '' ) {
			case 'same':
				$use = $carry['use'];
				$key = $carry['key'];
				break;
			case 'key':
				$use = 'key';
				$key = (string) ( $read['field'] ?? '' );
				break;
			default:
				$use = (string) ( $read['slug'] ?? '' );
				$key = '';
		}
	}

	// ── source axis: chain → src / ref / srcTermIn ─────────────────────────
	$steps = array_values( $slot['chain'] ?? array() );
	$src   = '';
	$ref   = $carry['ref'];
	$tax   = '';
	$first = true;

	foreach ( $steps as $step ) {
		$slug = (string) ( $step['slug'] ?? '' );
		$arg  = $step['arg'] ?? null;

		if ( 'entries' === $slug ) {
			$skip_reason = 'chain';
			return null;   // repeater rows: no flat spelling (the {{table}} resolver owns them).
		}

		if ( $first ) {
			$first = false;
			if ( 'same' === $slug ) {
				$src = $carry['src'];
				$ref = $carry['ref'];
				continue;
			}
			if ( 'refs' === $slug ) {
				// An ARGLESS hop keeps the carried relationship field rather than
				// blanking it: shipped `$last_ref` survives every src override, so
				// `3-src:ref` with no `3-ref` hops through slot 1's field. With nothing
				// carried it stays empty and the hop is dead — as it is today.
				$src = 'ref';
				$ref = ( null !== $arg && '' !== $arg ) ? (string) $arg : $carry['ref'];
				continue;
			}
			if ( 'terms' !== $slug ) {
				$src = $slug;
				continue;
			}
			// A leading `terms` hop reads the AMBIENT entity's terms — src stays unset.
		}

		if ( 'terms' !== $slug || '' !== $tax ) {
			$skip_reason = 'chain';
			return null;   // second term hop, second ref hop, or `entries`: not expressible.
		}
		$tax = (string) ( $arg ?? '' );
	}

	// ── limit: the LAST step that pins one, else the slot-level token ───────
	$limit = null;
	foreach ( $steps as $step ) {
		if ( null !== ( $step['limit'] ?? null ) && '' !== $step['limit'] ) {
			$limit = (string) $step['limit'];
		}
	}
	if ( null === $limit && isset( $slot['opts']['limit'] ) && '' !== $slot['opts']['limit'] ) {
		$limit = (string) $slot['opts']['limit'];
	}

	// The slot resolved: feed the accumulator (never before this point).
	$carry['src'] = $src;
	$carry['ref'] = $ref;
	$carry['use'] = $use;
	$carry['key'] = $key;

	$flat = array(
		'src'       => $src,
		'ref'       => $ref,
		'srcTermIn' => $tax,
		'use'       => '' === $use ? 'key' : $use,   // '' = the stripped `key` default (I3).
		'key'       => $key,
	);
	if ( null !== $limit ) {
		$flat['limit'] = $limit;
	}
	return $flat;
}
