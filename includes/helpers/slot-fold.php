<?php
/**
 * Folded slot-value grammar — THE single PHP owner of the FW-56/57 wire.
 *
 * One option key per slot (`{{join A:…|B:…}}`), whose VALUE carries the slot's
 * whole configuration: an ordered source CHAIN, the field READ, and any per-slot
 * options. Grammar APPROVED 2026-07-31; ADR 0006 owns which chars may enter an
 * accept class at all:
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
 *   all four sat on a superseded step char at once. This file is the PHP owner;
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
 *   `step` now would silently change what already-saved wires mean if it is ever
 *   given a job. They are ordinary CONTENT inside a value. Before widening any
 *   accept class, read ADR 0006 — the rule is that the char must already be
 *   allocated, and `bws_fold_grammar_validate()` cannot check that for you.
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
const BWS_FOLD_STEP_SEP = ';';
/** STRICT — `;` only. See the reserved-char note in the file header. */
const BWS_FOLD_STEP_CLASS = array( ';' );
/** Canonical separator inside one chain step (slug, arg, limit token). */
const BWS_FOLD_PART_SEP = ',';
/**
 * STRICT — `,` only. Narrowed when `;` became the canonical step char: step and step
 * share a position (both inside the chain), so `;` can no longer be forgiven as a
 * step separator. Machine-checked by bws_fold_grammar_validate().
 */
const BWS_FOLD_PART_CLASS = array( ',' );
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
 * inert — a `/` in `Date/time TBA` is not a step.
 */
const BWS_FOLD_FREEFORM = array( 'format', 'fallback', 'sep', 'valueSep', 'rangeSep', 'timeSep', 'label' );

/** Chain step slugs that FAN OUT (one → many). Used by the legacy limit mapping. */
const BWS_FOLD_FANNING_SLUGS = array( 'refs', 'terms', 'rows' );

/**
 * The largest magnitude a `limit` can hold IDENTICALLY in both languages — JS's
 * Number.MAX_SAFE_INTEGER, named here so the twin anchor is symmetric rather than a bare
 * literal on one side. Beyond it PHP's `(int)` saturates at PHP_INT_MAX where JS reaches
 * Infinity, so a migration that materialized such a value would put a DIFFERENT number on
 * the wire in each language. Both halves treat it as the unlimited case instead, which is
 * what it already reads as in either era.
 *
 * @since 1.17.0
 */
const BWS_FOLD_MAX_SAFE_LIMIT = 9007199254740991.0;

/**
 * Pattern a FOLDED slot key matches. Deliberately the GENERAL form, not `^[A-Z]$`:
 * bws_slot_ordinal() encodes spreadsheet-style (27 → `AA`), so a single-letter pattern
 * could reject wire its own encoder produced. The grammar therefore states NO MAXIMUM —
 * the slot count is a CONTAINER property (join 10, try_ 5), and baking one container's
 * count into the wire would mean re-cutting the pattern whenever a container changed.
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
 * The LEGACY per-slot option axes, i.e. exactly the keys bws_fold_from_flat() reads
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
const BWS_FOLD_FLAT_AXES = array( 'src', 'ref', 'srcTermIn', 'use', 'key', 'limit' );

/**
 * Legacy `src` VALUES the fold must refuse to fold — the four retired source tokens.
 *
 * These name the related-post source classes made inert in 1.17.0 (#56). A slot spelling
 * one is not foldable HERE, because the information needed to rewrite it faithfully is
 * the relationship field in `rel` (or `key`), and `rel` is not a fold axis at all while a
 * container's `key` may be tag-level and filtered out before the mapper sees it. The
 * converter's own entry (bws_migrate_related_post_src) reads the undifferentiated option
 * array — exactly what the retired class read — and rewrites the token to `src:ref`
 * BEFORE the fold entry runs, so on that path this list is never reached.
 *
 * The MOUNT path has no entry chain, so without this guard it would fold `related_post`
 * verbatim into a chain root that now resolves to nothing — storing the tag one way while
 * the converter stores it another, which is the exact divergence the twin exists to stop.
 * Declining is what keeps that impossible: the slot keeps its legacy keys, the tag is
 * left as it was, and the converter (or a later mount, once it has run) still fixes it.
 * Writing NOTHING is not a second way to store a tag; writing the wrong wire is.
 *
 * @since 1.17.0
 */
const BWS_FOLD_RETIRED_SRC_TOKENS = array(
	'related_post',
	'second_related_post',
	'post_term_related_post',
	'term_related_post',
);

/**
 * The legacy per-slot axes a container actually owns — the full set minus its TAG-LEVEL
 * axes, which are excluded at EVERY slot position.
 *
 * THE SINGLE OWNER OF THAT SUBTRACTION, because three places need the same answer and
 * they must not each compute it: the converter migrator strips these keys
 * (bws_fold_migration_slot_keys), the registered fold config ships them to the editor as
 * `flatAxes` (bws_build_fold_slot_options), and the editor's mount migrator + control
 * read that config to decide which siblings to fold and delete. A control that kept its
 * own list deleted the tag-level `limit` off a `try_text` and the tag-level `key` off a
 * `try_datetime_single` the first time a slot was touched.
 *
 * @since 1.17.0
 * @param string[] $tag_level Axes this container owns at TAG level (never per slot).
 * @return string[] Per-slot axes, in BWS_FOLD_FLAT_AXES order.
 */
function bws_fold_slot_flat_axes( array $tag_level = array() ): array {
	return array_values( array_diff( BWS_FOLD_FLAT_AXES, $tag_level ) );
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
 * opposite — a bare `limit` there bounds every slot — which is why this list is
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
 * accepted `,;`) but must be disjoint WITHIN one. step and part share a position.
 * This caught the `;`-step change putting `;` in two same-position classes.
 *
 * @return string[] Violation strings; empty array = safe.
 */
function bws_fold_grammar_validate(): array {
	$bad    = array();
	$br_all = array_merge( array_keys( BWS_FOLD_BR_PAIRS ), array_values( BWS_FOLD_BR_PAIRS ) );

	if ( array_intersect( BWS_FOLD_STEP_CLASS, BWS_FOLD_PART_CLASS ) ) {
		$bad[] = 'step_class ∩ part_class ≠ ∅';
	}
	$classes = array(
		'opt_class'  => BWS_FOLD_OPT_CLASS,
		'step_class' => BWS_FOLD_STEP_CLASS,
		'part_class' => BWS_FOLD_PART_CLASS,
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
		'step_sep' => array( BWS_FOLD_STEP_SEP, BWS_FOLD_STEP_CLASS ),
		'part_sep' => array( BWS_FOLD_PART_SEP, BWS_FOLD_PART_CLASS ),
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
 * Used at all three positions (slot tokens, chain steps, intra-step). It is
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
 * encoding needs an interior hole (`rows,,5`) that fails SILENTLY when it is
 * missing — `rows,5` parses as arg=5.
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
	$step_parts = bws_fold_split_depth( $chain_str, BWS_FOLD_STEP_CLASS );
	if ( isset( $step_parts['error'] ) ) {
		return $step_parts;
	}

	$steps = array();
	foreach ( $step_parts as $step_str ) {
		$step_str = trim( $step_str );
		if ( '' === $step_str ) {
			continue;
		}
		$parts = bws_fold_split_depth( $step_str, BWS_FOLD_PART_CLASS );
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
					return array( 'error' => "chain step '$step_str': unexpected extra positional token '$part'" );
				}
				$step['arg'] = $part;
				continue;
			}
			if ( 'limit' === $tok['name'] ) {
				if ( ! is_numeric( $tok['val'] ) ) {
					return array( 'error' => "chain step '$step_str': limit '{$tok['val']}' is not numeric" );
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
			return array( 'error' => "chain step '$step_str': missing slug" );
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
			$segment .= BWS_FOLD_PART_SEP . $arg;
		}
		$limit = $step['limit'] ?? null;
		if ( null !== $limit && '' !== $limit ) {
			// 0 = unlimited and MUST survive as a literal: an author who pinned "all"
			// silently reverts if a falsy guard drops it. Negative forms normalize to 0.
			$normalized = (int) $limit;
			$segment   .= BWS_FOLD_PART_SEP . 'limit' . $open . ( $normalized < 0 ? 0 : $normalized ) . $close;
		}
		foreach ( $step['extra'] ?? array() as $extra ) {
			$segment .= BWS_FOLD_PART_SEP . $extra;
		}
		$segments[] = $segment;
	}
	return implode( BWS_FOLD_STEP_SEP, $segments );
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
		// `use(key)` with no key token is a KEYED READ WHOSE FIELD IS NOT CHOSEN YET —
		// the state the editor is in between picking "Meta/Option Field" and picking the
		// field. It has to round-trip (emit writes it back), because the control rewrites
		// the whole value on every commit and derives the read select's value by RE-PARSING
		// it: a shape that parses but never emits made the kind un-selectable (it reverted
		// to unset on commit, and with it the field picker it reveals). Legacy-shaped in
		// origin, canonical now for the empty-field case only — with a field present the
		// bare `key(x)` still wins (bws_fold_emit_slot).
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
			// Field chosen → the bare `key(x)` IS the keyed read (`use` is redundant).
			// Field not chosen yet → `use(key)` is the only spelling of "keyed, pending",
			// and it must be written or the editor loses the author's kind choice on
			// commit. Renders exactly as the flat-era `use:key` with an empty key did.
			if ( '' !== $read['field'] ) {
				$values['key'] = $read['field'];
			} else {
				$values['use'] = 'key';
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
 * AN EXPLICIT `src:current` MAPS TO A REAL `current` STEP, NEVER TO "NO STEP" — see
 * the inline comment at the `elseif ( '' !== $src )` branch below for the concrete
 * failure this guards (mapping it to nothing deleted a fallback attempt's entire
 * content on containers with no per-slot read axis).
 *
 * `limit` goes onto the chain's FANNING STEPS, through the same owner a base tag's
 * migration uses (bws_fold_chain_apply_legacy_limit): an explicit `N` on the last
 * one with `1` on every earlier one, and a bare `1` on each when the legacy wire
 * stated none. The unset case is not decoration — a folded slot defaults to
 * UNLIMITED like any other chain wire, so the flat era's implied `1` must be
 * written or a migrated slot fans out where the stored tag rendered one value.
 * With no fanning step there is nothing to bound and an explicit positive limit
 * stays a slot-level token — that case bounds a multi-value READ rather than a
 * step, and is the one meaning a slot-level `limit` still has.
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
function bws_fold_from_flat( int $n, array $options, bool $combining = false, bool $per_slot_use = true ) {
	$prefix = ( 1 === $n ) ? '' : "{$n}-";
	$src    = trim( (string) ( $options[ "{$prefix}src" ] ?? '' ) );
	$ref    = trim( (string) ( $options[ "{$prefix}ref" ] ?? '' ) );
	$tax    = trim( (string) ( $options[ "{$prefix}srcTermIn" ] ?? '' ) );
	$use    = trim( (string) ( $options[ "{$prefix}use" ] ?? '' ) );
	$key    = trim( (string) ( $options[ "{$prefix}key" ] ?? '' ) );
	// SLOT 1's prefix is '', so on a SELECTING container the bare `limit` it would read here
	// is the TAG-level key, not this slot's own. Skipping it is what makes every attempt
	// take that key the same way — through the gated fallback below — instead of slot 1
	// alone swallowing it as a slot-level token with nothing to bound. Combining containers
	// own `limit` per slot, so there the bare key IS slot 1's.
	$limit  = ( $combining || $n >= 2 ) ? trim( (string) ( $options[ "{$prefix}limit" ] ?? '' ) ) : '';

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
	// A SITE ROOT NEVER TAKES THE LEGACY TERM STEP, which is the same refusal
	// bws_fold_chain_from_options() already makes for a base tag's flat keys and
	// bws_fold_migrate_base_src() states as a rule ("A SITE ROOT IS LEFT ALONE, even beside
	// a hand-edited `srcTermIn`"). `srcTermIn` registers `show_if src: not:site`, so the
	// pair is hand-edit-only, and every arm has always let the site read win.
	//
	// It became LOAD-BEARING at #104 and was harmless before it: the retired flatten
	// collapsed this chain back to a triple, and the chain reader dropped the step off the
	// triple, so appending it here changed nothing. Now the chain IS the hand-off, so
	// appending it makes the slot resolve a term step off a site source — no post input,
	// hence empty — where the identically-keyed base tag still reads the site. That is the
	// [I6]/[I16] parity §F9b.5 closed one release earlier, re-opened from the other side.
	//
	// ONE FIX, THREE PATHS: the render dual-read, the converter's slot mapper and the
	// editor's mount migrator all build a slot's chain here (bws_fold_migrate_slots() calls
	// this), so a stored tag, a converted one and an editor-touched one cannot disagree.
	if ( '' !== $tax && 'site' !== $src ) {
		// srcTermIn always FOLLOWS ref: the term step needs a post input, which the
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

	// A SELECTING container states `limit` ONCE, at TAG level, and it is EVERY attempt's
	// own default rather than a bound across attempts (TagTemplateRegistry::try_slot_axes
	// puts it on `tag_level`, so no `{N}-limit` key exists to read). Slot 1's prefix is ''
	// so it already reads that key; slots ≥2 must read the SAME one, or the materialized
	// default below writes a `1` that SHADOWS the author's number — a slot's own limit
	// wins over the tag-level one in every container arm.
	//
	// Combining containers are the deliberate contrast: {{join}}/{{table}} own `limit` per
	// slot, so an absent `{N}-limit` genuinely means "this slot states none" and must take
	// the default rather than reach back to slot 1's.
	//
	// Read AFTER the emptiness test above, never before: a tag-level limit is not content,
	// and folding it in earlier would conjure a slot out of every unused ordinal. And read
	// only where THIS slot's chain fans — the same predicate everything else here shares —
	// so a slot with nothing to bound gets no limit, per #60. It loses nothing: a slot that
	// fans by INHERITING is handed the bound with the source it inherits
	// (bws_fold_slot_chain_options), which is what let the key itself be retired (#61).
	if ( '' === $limit && ! $combining && bws_fold_chain_fanning_steps( $chain ) ) {
		$limit = trim( (string) ( $options['limit'] ?? '' ) );
	}

	// ── limit → the chain's fanning steps ──────────────────────────────────
	//
	// A FOLDED SLOT DEFAULTS TO UNLIMITED, exactly as a base tag spelled the same way
	// does (bws_limit_default). So the flat era's implied `1` has to be MATERIALIZED
	// here. It used to arrive for free — the retired bws_fold_slot_flat_options() re-spelled every
	// slot as a flat triple before any container arm resolved a limit, so the default
	// was chosen from wire the slot no longer had, and every slot answered 1 whatever
	// it was spelled as. Once the seam hands its ERA back (#60), that prop is gone and
	// migration has to state what the old spelling implied.
	//
	// ONE OWNER FOR BOTH DEPTHS: the rule that bounds a migrated base tag's chain
	// bounds a migrated slot's, positional `N`-on-the-last-fanning-step and all
	// (bws_fold_chain_apply_legacy_limit — read its docblock for why the earlier
	// steps are not decoration).
	$opts    = array();
	$applied = bws_fold_chain_apply_legacy_limit( $chain, '' !== $limit ? $limit : null );
	$chain   = $applied['chain'];
	if ( ! $applied['consumed']
		&& '' !== $limit
		&& is_numeric( $limit )
		&& abs( (float) $limit ) <= BWS_FOLD_MAX_SAFE_LIMIT ) {
		// An explicit limit that owner declined to relocate — `0`/`-1` (unlimited), or a
		// positive one with no fanning step to carry it. IT STILL NEEDS A CARRIER HERE,
		// and the reason is the dual-read rather than migration: this same mapping serves
		// the render path for UNMIGRATED flat wire, which takes the FLAT era's default of
		// 1, so dropping an author's explicit `0` would re-bound a tag they deliberately
		// unbounded. (On migrated wire the token is merely redundant with the chain-era
		// default, which is the safe direction to be wrong in.)
		//
		// The one shape still left unwritten: a magnitude neither language holds
		// identically (PHP saturates at PHP_INT_MAX where JS reaches Infinity). It reads
		// as unlimited in both eras already, so writing it would buy nothing and risk the
		// one divergence a twin exists to make impossible.
		$normalized = max( 0, (int) $limit );
		$fanning    = bws_fold_chain_fanning_steps( $chain );
		if ( $fanning ) {
			$chain[ end( $fanning ) ]['limit'] = (string) $normalized;
		} else {
			// Nothing fans, so there is no step to bound: the token keeps its slot-level
			// meaning (bound a multi-value READ).
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

/**
 * Which steps of a chain FAN — the single owner of that question.
 *
 * Two readers that must never disagree: the migrator, which materializes the flat era's
 * implied limit onto exactly these steps, and the render seam, which hands a slot the
 * UNLIMITED default only where the slot states a list to bound. A slot that gets the
 * chain-era default on a step the migrator declined to stamp fans out where its stored
 * twin returned one value, which is the one thing the fold must never do.
 *
 * AN ARGLESS FANNING STEP DOES NOT COUNT. bws_fold_chain_to_steps() drops it (a
 * field-less `refs` would short-circuit to empty), so the chain does not fan on its own
 * account. It may still resolve a fanning source by INHERITANCE — the flattener hands an
 * argless `refs` the carried relationship field — and that is precisely why it must not
 * count: the source it fans over belongs to an earlier slot, which stated its own bound.
 * Same reasoning covers `src(same)`, which states no step at all.
 *
 * @since 1.17.0
 * @param array $chain Parsed chain (grammar shape).
 * @return int[] Indexes of the fanning steps, in chain order.
 */
function bws_fold_chain_fanning_steps( array $chain ): array {
	$out = array();
	foreach ( $chain as $i => $chain_step ) {
		if ( ! in_array( (string) ( $chain_step['slug'] ?? '' ), BWS_FOLD_FANNING_SLUGS, true ) ) {
			continue;
		}
		if ( '' === trim( (string) ( $chain_step['arg'] ?? '' ) ) ) {
			continue;
		}
		$out[] = $i;
	}
	return $out;
}

/**
 * Materialize the limit a LEGACY flat source implied, as per-step limits on the chain
 * that respells it.
 *
 * THE ONE OWNER, at both depths. It was first written as the base tag's half alone, on
 * the reasoning that a folded SLOT needed only the explicit case: the flatten seam
 * collapsed a slot's chain back to a flat triple before any container arm resolved a
 * limit, so the default was chosen from wire the slot no longer had and folding a slot
 * could not change what it rendered. That prop is gone — the seam now hands its ERA back
 * (#60) and a slot's own spelling decides its own default exactly as a base tag's does —
 * so bws_fold_from_flat() materializes through this same function, and the two depths
 * cannot drift into two rules for one idea.
 *
 * A LIMIT IS STATED WHERE THE SOURCE IS STATED (user, 2026-08-06; ADR 0005). A chain
 * states its source as steps, so migration writes the limit onto the steps and never as
 * a tag-level `limit`: a number attached to the step it bounds says which quantity it
 * bounds, where a tag-level `1` beside a two-step chain does not. The tag-level control
 * retires with the flat spelling it belongs to, so nothing arrives here needing to be
 * cleared afterwards.
 *
 * AN EXPLICIT `limit:N` MOVES ONTO THE LAST FANNING STEP, with `1` on every earlier one,
 * and the tag-level key is deleted. Positional, not `terms`-specific — `refs` takes the
 * `N` when `refs` is the last fanning step; the terms step is merely what "last" always
 * is in legacy wire, since `srcTermIn` follows `ref`. Two alternatives were examined and
 * rejected: `N` on the FIRST fanning step (per-input and total coincide there, which is
 * elegant, but it restates the author's number rather than preserving what the tag did),
 * and leaving `N` at tag level untouched (output-preserving by ordinary option
 * precedence, since the reader slices by `$options['limit']` without inspecting `src` —
 * but it keeps the incoherent object alive on exactly the tags that have one).
 *
 * The earlier fanning steps are NOT optional. Per-step limits are PER-INPUT and MULTIPLY
 * (`∏ limitₙ`), so `N` on the last step alone yields `N` per parent × every parent; `1`
 * on the earlier ones bounds the product at `N`, which is what the flat limit meant.
 *
 * Three shapes are left alone, each because touching them would change output or invent
 * an intent:
 *
 * - **An explicit `0` / `-1`.** Already unlimited, which is what chain wire defaults to,
 *   so there is nothing to carry. Whether the KEY survives is the caller's position, not
 *   this rule's — see `$consume_unlimited`.
 * - **An ARGLESS fanning step.** bws_fold_chain_to_steps() drops it (a field-less `refs`
 *   would short-circuit to empty), so the chain does not fan and a limit on it is inert
 *   noise. A source that resolves one entity has nothing to bound.
 * - **A chain that ALREADY carries a step limit.** The author has stated per-step intent;
 *   materializing a default on top of it would overwrite a decision with a guess. Only
 *   reachable from the author-conversion path, where the same commit can do both.
 *
 * A NON-NUMERIC value is treated as absent — `bws_clamp_limit`'s `is_numeric` gate
 * already gives it the default — but it is NOT consumed: deleting an author's text on
 * that basis is a bigger move than this rewrite is entitled to, and leaving it renders
 * identically.
 *
 * `$consume_unlimited` IS THE DEPTH-0 POSITION, and the asymmetry is the point. At depth 0
 * the rewrite is the whole story: the chain spelling itself selects unlimited
 * (`bws_limit_default`), so an explicit `0`/`-1` left behind states nothing the wire does
 * not already say — and since #62 retired the tag-level control there is no field left to
 * see or clear it in, which makes it a token on chain wire that no editor surface can
 * reach. So the depth-0 callers ask for it to be consumed. A SLOT caller must NOT: the
 * same mapper renders UNMIGRATED flat wire, where an absent limit takes the flat era's 1,
 * so there the `0` carrier is load-bearing (slot-fold-test.php §P12.18/19).
 *
 * Narrow on purpose. Only the explicit non-positive value is consumed, and only where the
 * chain actually FANS — a stated per-step limit still stands the whole mapping down
 * (above), and a source with no fanning step never reaches this at depth 0.
 *
 * @since 1.17.0
 * @since 1.17.0 `$consume_unlimited` (#62).
 * @param array $chain             Parsed chain, freshly built from legacy keys (grammar shape).
 * @param mixed $limit             The legacy tag-level `limit` value, or null when absent.
 * @param bool  $consume_unlimited Depth-0 callers pass true: an explicit `0`/`-1` is
 *                                 reported consumed so the caller deletes the key.
 * @return array{chain: array, consumed: bool} The chain with the implied limit
 *               materialized where one was implied, and whether the caller must now
 *               delete the tag-level key.
 */
function bws_fold_chain_apply_legacy_limit( array $chain, $limit, bool $consume_unlimited = false ): array {
	foreach ( $chain as $chain_step ) {
		if ( ! in_array( (string) ( $chain_step['slug'] ?? '' ), BWS_FOLD_FANNING_SLUGS, true ) ) {
			continue;
		}
		$step_limit = $chain_step['limit'] ?? null;
		if ( null !== $step_limit && '' !== (string) $step_limit ) {
			return array( 'chain' => $chain, 'consumed' => false );   // the author's own limits win
		}
	}
	$fanning = bws_fold_chain_fanning_steps( $chain );

	if ( ! $fanning ) {
		return array( 'chain' => $chain, 'consumed' => false );
	}

	$raw      = trim( (string) ( $limit ?? '' ) );
	$explicit = ( '' !== $raw && is_numeric( $raw ) );

	// A magnitude the two languages cannot hold identically is treated as the unlimited
	// case rather than materialized: PHP's (int) saturates at PHP_INT_MAX (with a warning)
	// where JS reaches Infinity, so writing it would put a DIFFERENT number on the wire in
	// each language — the one divergence a twin exists to make impossible. Nothing is lost:
	// a limit that large already reads as unlimited in both eras, and the tag-level key is
	// left exactly as authored, so the reader still answers what it always answered.
	//
	// It is NOT consumed at depth 0 either, and that is the one acknowledged hole in #62's
	// promise: the key survives on chain wire with no control to reach it. Left deliberately
	// — the value is a stated BOUND, not the explicit unlimited the consume rule is written
	// for, so deleting it would answer a question the author asked with a number of our own.
	// Hand-written wire only (no control could ever produce a magnitude this large), which
	// is also the wire ADR 0004 says must keep meaning what it says.
	if ( $explicit && abs( (float) $raw ) > BWS_FOLD_MAX_SAFE_LIMIT ) {
		return array( 'chain' => $chain, 'consumed' => false );
	}

	$value = $explicit ? (int) $raw : 1;

	if ( $value <= 0 ) {
		// Unlimited either way, so no step limit is written. The KEY goes only at depth 0
		// (see $consume_unlimited) — and only when it was EXPLICIT, since an absent or
		// empty value is not the author's token to delete.
		return array( 'chain' => $chain, 'consumed' => ( $consume_unlimited && $explicit ) );
	}

	$last = array_pop( $fanning );
	foreach ( $fanning as $i ) {
		$chain[ $i ]['limit'] = '1';
	}
	$chain[ $last ]['limit'] = (string) $value;

	return array( 'chain' => $chain, 'consumed' => $explicit );
}

// ── Render seam (folded struct → the options a container renders through) ──

/**
 * Read slot $n as a struct, whichever wire ERA it is stored in (the render/preview
 * DUAL-READ).
 *
 * Mode purity is a property of a MIGRATED tag, not of the reader: a half-applied
 * migration or a hand-edit can leave slot 2 folded between legacy slots 1 and 3, and
 * a reader that picked one era per TAG would drop half of it. So the era is decided
 * PER SLOT — folded value present ⇒ parse it; absent ⇒ recover through
 * bws_fold_from_flat(). Both feed the same caller-held carry accumulator, which is
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
 * bws_fold_slot_chain_options() skips it one step later anyway.
 *
 * THE ERA RIDES ON THE STRUCT, under a non-grammar `era` key, because this is the only
 * function that can see it: one step later the chain has been collapsed to a flat
 * `src`/`ref`/`srcTermIn` triple, which is structurally blind to how the slot was
 * SPELLED. Reading the default off that triple is what made every slot answer 1
 * whatever it was spelled as (#60). `era` is provenance, not wire — bws_fold_emit_slot()
 * reads the six grammar keys by name and never sees it, and nothing round-trips it.
 *
 * @since 1.17.0
 * @param int    $n            Slot ordinal (1-based).
 * @param array  $options      All tag options (GB-parsed).
 * @param string $container    'try' (selecting) | 'join' | 'table' (combining).
 * @param bool   $per_slot_use True when the container gives each slot its own read
 *                             axis. Ignored for combining containers.
 * @return array|null Slot struct + an `era` key ('chain' when the slot is stored as
 *                    folded wire, 'flat' when recovered from the legacy keys), or null
 *                    when this slot holds nothing (or unparsable folded wire).
 */
function bws_fold_slot_struct( int $n, array $options, string $container = 'join', bool $per_slot_use = true ) {
	$raw = trim( (string) ( $options[ bws_slot_ordinal( $n ) ] ?? '' ) );
	if ( '' !== $raw ) {
		$parsed = bws_fold_parse_slot( $raw, $container );
		if ( isset( $parsed['error'] ) ) {
			return null;
		}
		$parsed['era'] = 'chain';
		return $parsed;
	}
	$rec = bws_fold_from_flat( $n, $options, bws_fold_is_combining( $container ), $per_slot_use );
	if ( $rec && isset( $rec['slot'] ) ) {
		$slot        = $rec['slot'];
		$slot['era'] = 'flat';
		return $slot;
	}
	if ( 1 === $n && 'try' === $container ) {
		$slot        = bws_fold_empty_slot();
		$slot['era'] = 'flat';
		return $slot;
	}
	return null;
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
 * A fresh carry-forward accumulator for a container's slot walk.
 *
 * ONE OWNER for the seed, beside the seam that reads it. Every axis but the READ starts
 * empty and the seam fills the rest in through its own `+=` defaults, so the only thing a
 * caller has to state is what an ABSENT slot-1 read means on its template — which is the
 * one axis the seam cannot know. Four call sites held the literal before #104 (both
 * container loops, both preview walks) and the fold's own rename had to edit all four in
 * lockstep, which is the tell.
 *
 * @since 1.17.0
 * @param string $default_read The stripped first `use` value of the leaf the container's
 *                             slots read through (bws_use_stripped_default()); '' on a
 *                             container with no read axis. Every container states it —
 *                             the seam writes no read default of its own.
 * @return array Carry accumulator.
 */
function bws_fold_empty_carry( string $default_read = '' ): array {
	return array(
		'chain' => array(),
		'ref'   => '',
		'use'   => $default_read,
		'key'   => '',
	);
}

/**
 * Resolve one folded slot into the option set a container's slot loop renders through,
 * threading the caller's carry-forward accumulator.
 *
 * A SLOT'S SOURCE *IS* A BASE TAG'S SOURCE (CONTEXT.md I16). Same wire language, same
 * parser, same resolution path; only STORAGE differs, and that is [I13]'s axis. So this
 * seam EMITS the slot's resolved chain as depth-0 CHAIN WIRE in `$opts['src']` — the
 * option key a base tag states its source in — and never as a `src`/`ref`/`srcTermIn`
 * triple. It replaced bws_fold_slot_flat_options(), which re-spelled every slot as that
 * triple: one relationship step plus one term step, whatever the wire said. That respelling
 * is what made a multi-step slot inexpressible, and it was DELETED rather than adapted —
 * a seam serving both shapes is the shape that guarantees a half-shipped divergence.
 *
 * Carry-forward, `same` resolution, the read axis, the skip-reason out-param and the #60
 * limit-era out-param all stayed exactly where they were. ONLY THE SOURCE AXIS CHANGED
 * SHAPE.
 *
 * WIRE, NOT A PARSED STRUCTURE, and that is a corollary of ADR 0004 rather than a fresh
 * choice. `$opts['src']` is already read by several things that are not the chain parser —
 * bws_fold_src_root_token() (the factory's root read), bws_limit_default()'s era selection,
 * the preview source namer — so a parsed chain in a side key would have to be taught to
 * each of them, arriving as a SECOND way to state a source.
 *
 * THE EMITTED STRING DIFFERS FROM THE STORED SLOT VALUE BY DESIGN. Bracket alternation is
 * by depth with two pairs (BWS_FOLD_BR_PAIRS: `()` at level 1, `[]` at level 2), and a
 * slot's chain sits at enclosing level 1 while this emits at level 0 — so `limit[3]`
 * re-levels to `limit(3)`. Re-leveling only ever goes SHALLOWER, which is why it is safe:
 * it cannot run out of pairs. Any idempotence assertion is therefore a FIXED POINT on the
 * re-leveled form (`emit₀(parse(emit₀(chain))) === emit₀(chain)`) and NEVER a comparison
 * against the stored slot wire — written the naive way it fails on every chain carrying a
 * bound, and the reflex fix is to loosen it.
 *
 * IT SUPERSEDES THE LEGACY SOURCE AXES BY CONTRACT. The returned array always carries
 * explicit empties for `ref` and `srcTermIn`, so merging it over anything is sufficient.
 * That belongs HERE and not in each container: bws_fold_chain_from_options() APPENDS a
 * `terms` step for any surviving `srcTermIn`, so a tag-level leftover would grow a step on
 * EVERY slot's chain — and `{{join}}` builds its slot options from this array alone while
 * `try_` merges them over the tag's, so a container-side rule would be one caller carrying
 * it for the other's sake. This is an [I15]-class failure: a leaked step returns a
 * PLAUSIBLE VALUE FROM THE WRONG ENTITY, not an empty one.
 *
 * ACCUMULATOR. `$carry` is `{chain, ref, use, key, limit}` and is updated ONLY by a slot
 * that actually resolves. A skipped slot must not feed it: shipped join `continue`s before
 * its carry-forward, so a half-configured slot 2 leaves slot 3 inheriting slot 1 — feeding
 * the accumulator first would re-point slot 3 at a source the author never chose. `ref` is
 * passed through even under a non-`ref` source (inert there, but a later slot carrying back
 * to the same relationship needs it — shipped behaviour, and the one thing an ARGLESS
 * `refs` step consumes).
 *
 * THE CARRY IS CHAIN-SHAPED, WHICH DELETED A SPECIAL CASE. `src(same)` copies the prior
 * slot's RESOLVED CHAIN rather than four scalars, so an inheriting slot carries the prior
 * slot's HOPS for free. The `$tax_inherit` branch this used to need existed only because a
 * flat triple cannot carry a step, so an inheriting slot took the root alone and landed on
 * the ambient entity — [I15]'s corollary ("an inherited source carries what it IS, not
 * merely its root") stops being enforced by a branch.
 *
 * WHAT SURVIVES THE CARRY BECOMING A CHAIN is the corollary's second half: an inherited hop
 * is a DEFAULT, not a step this chain took, so part of the inherited chain can GIVE WAY to a
 * step this slot states of its own. That is a rule about what `same` MEANS — a slot sentinel
 * the container resolves before compiling — and not about the chain grammar, which is why a
 * base tag's `terms,a;terms,b` still hops twice. WHAT DECIDES HOW MUCH GIVES WAY IS OWNED BY
 * bws_fold_chain_join() (slot-fold-compile.php) AND IS NOT RESTATED HERE — it changed axis
 * three times during #104, and every site that had named an axis went stale, including two in
 * this file. See the merge at the end of the source axis.
 *
 * CONTAINER SENSITIVITY IS ON THE READ AXIS, AND ONLY THERE — specifically on what
 * ABSENCE means. An explicit `use(same)` inherits in BOTH containers (so the read is
 * always tracked in the accumulator); an ABSENT read is UNCONFIGURED in a combining
 * container (skip the slot) and INHERIT in a selecting one.
 *
 * THE `'chain'` REFUSAL DISSOLVED — a chain with no flat spelling — because there is no flat
 * spelling left to fail at, so the branch has nothing to test. The other four are correct at
 * any emit shape and STAY; a FIFTH arrived with the emit change, so the count is five and not
 * four wherever it is restated. Each has its own author-facing answer:
 *   - `'read'`         an unconfigured combining slot. Silent (a resting state).
 *   - `'inherit'`      a `same` root with nothing to be the same AS.
 *   - `'step:refs'`    a relationship step with no field AND nothing carried to inherit one.
 *   - `'step:terms'`   a term step with no taxonomy.
 *   - `'step:rows'` a repeater step with no field. It is listed apart from the other two
 *                      because it is the one the FLATTEN never reached: `rows` was refused
 *                      outright as inexpressible, argument or not, so an unfinished one had
 *                      nowhere to be reported from. The rule is the fanning family's, not a
 *                      per-slug decision — an argless fanning step of any slug is unfinished.
 * The repeater-row refusal MOVED rather than dissolving: it belongs to the container that
 * consumes a `meta_row`, so `try_`'s arm table skips that kind (includes/helpers/
 * try-slot-arms.php) and `{{table}}` waits on its own arm — not on this seam.
 *
 * WHY THE SKIP REASON IS AN OUT-PARAM. The editor PREVIEW has to tell the skips apart — an
 * unconfigured slot is a normal in-progress state and says nothing, while an unfinished step
 * is wire that will never render and has to be FLAGGED, or the author reads a preview that
 * silently omits a slot they configured. Deriving the reason in the preview would be a
 * second copy of the skip rule, i.e. the exact drift this seam removed, so the reason is
 * reported BY THE OWNER. Optional and by reference: the render callers pass nothing.
 *
 * WHY THE LIMIT DEFAULT IS AN OUT-PARAM TOO, AND WHY IT IS NOW LOAD-BEARING. A SLOT'S OWN
 * SOURCE SPELLING DECIDES ITS OWN DEFAULT, exactly as a base tag's does — chain wire returns
 * everything, flat wire bounds at 1 (bws_limit_default). The emitted `src` is CHAIN WIRE ON
 * EVERY SLOT now, so bws_limit_default() read off it answers *unlimited* even for a slot
 * recovered from legacy flat keys. Both containers write the resolved number back into
 * `$slot_opts['limit']` explicitly; that line stopped being a nicety the moment this seam
 * landed and must not be removed or "simplified".
 *
 * A CHAIN-SPELLED SLOT ONLY TAKES THE UNLIMITED DEFAULT WHERE ITS OWN CHAIN FANS
 * (bws_fold_chain_fanning_steps — the same predicate the migrator stamps by, which is the
 * whole point of sharing it). A slot spelling `src(same)`, or an argless `src(refs)`,
 * states no list of its own: it fans only by INHERITING an earlier slot's source, and that
 * slot already stated its own bound.
 *
 * WHICH IS WHY, ON A SELECTING CONTAINER, THAT BOUND IS CARRIED (#61). `src(same)` names
 * the same SOURCE and a limit is one of a source's parameters, so an attempt that inherits
 * the source inherits what bounds it. That is what let `try_`'s TAG-LEVEL `limit` be
 * retired without moving output.
 *
 * CONTAINER-SENSITIVE, and it is the contrast this file already draws twice
 * (bws_fold_from_flat's tag-level read is the other). A COMBINING container registered
 * `limit` PER SLOT, so an absent `{N}-limit` there is a slot saying "I state none" and must
 * take the default — carrying it there moves shipped {{join}} output, which
 * slot-fold-test.php §P13.1 `term hop with limit` is the case that says so. A SELECTING
 * container never had a per-slot limit at all, so absence there can only mean inherit.
 *
 * @since 1.17.0 Replaces bws_fold_slot_flat_options() (#104, FW-71).
 * @param array  $slot        Slot struct (bws_fold_parse_slot / bws_fold_from_flat shape).
 * @param array  $carry       Carry-forward accumulator, BY REFERENCE: {chain,ref,use,key,limit}.
 *                            `limit` is WRITTEN on every container so the accumulator
 *                            means one thing, and READ only on a selecting one.
 * @param bool   $combining   True for {{join}}/{{table}}; false for `try_*`. Derive it
 *                            with bws_fold_is_combining() rather than at the call site.
 * @param string $skip_reason OUT, by reference. '' when the slot resolves; otherwise one of
 *                            the five reasons above. Written before any early return, so a
 *                            caller may reuse one variable across a whole slot walk.
 * @param int    $limit_default OUT, by reference. The limit this slot takes when its wire
 *                            states none: 0 (unlimited) for a chain-spelled slot whose own
 *                            chain fans, 1 otherwise. Same reset contract as $skip_reason.
 * @return array|null Slot options ({src,ref,srcTermIn,use,key} + optional limit), where
 *                    `src` is DEPTH-0 CHAIN WIRE and `ref`/`srcTermIn` are explicit
 *                    empties; or null when the slot is skipped.
 */
function bws_fold_slot_chain_options( array $slot, array &$carry, bool $combining, &$skip_reason = null, &$limit_default = null ) {
	$skip_reason   = '';
	$limit_default = ( 'chain' === ( $slot['era'] ?? 'flat' ) && bws_fold_chain_fanning_steps( $slot['chain'] ?? array() ) )
		? 0
		: 1;
	// `_fed` is not a wire axis — it records whether ANY slot has fed the accumulator
	// yet, which the `same` root needs and no other key can answer: `chain` initialises to
	// the empty chain, and the empty chain is also how the ambient entity is spelled, so an
	// inherit off a fresh accumulator is indistinguishable from an inherit off an ambient
	// slot 1 (#74).
	$carry += array( 'chain' => array(), 'ref' => '', 'use' => '', 'key' => '', 'limit' => null, '_fed' => false );

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

	// ── source axis: the slot's chain → the chain this slot RESOLVES to ────
	//
	// Everything here is a chain-to-chain rewrite of exactly two sentinels — `same` at the
	// root, and an argless `refs` taking the carried relationship field. Every other step
	// passes through verbatim, which is what makes a multi-step source resolve at all: the
	// old flattener had to REJECT what it could not re-spell.
	$steps = array_values( $slot['chain'] ?? array() );
	// The inherited chain is held APART from this slot's own steps until both are known,
	// because part of the inherited chain can GIVE WAY to a step this slot states of its own
	// rather than being followed by it — bws_fold_chain_join() owns what decides how much, and
	// this comment deliberately does not restate it (see the merge below). Appending blind is
	// what the first draft of #104 did, and it silently deleted a slot: legacy
	// `2-src:same|2-srcTermIn:office` behind a slot that already hopped `department` came out
	// as two term steps and hopped twice.
	$inherited = array();
	$own       = array();
	$ref       = $carry['ref'];
	$first     = true;

	foreach ( $steps as $step ) {
		$slug = (string) ( $step['slug'] ?? '' );
		$arg  = trim( (string) ( $step['arg'] ?? '' ) );

		if ( $first ) {
			$first = false;

			if ( 'same' === $slug ) {
				// Nothing has resolved yet, so there is no source to be the same AS.
				// Falling through would inherit the accumulator's initialiser, which
				// spells the ambient entity — and at slot ≥2 ambient is not a default to
				// fall back to, it is something the wire has to SAY (#74).
				//
				// Its OWN reason: `inherit` names a perfectly expressible chain with
				// nothing yet to be the same as, and the author-facing answer is "finish
				// an earlier slot".
				if ( empty( $carry['_fed'] ) ) {
					$skip_reason = 'inherit';
					return null;
				}
				// THE WHOLE CHAIN, hops and all. A flat triple could only carry the root,
				// which is why the taxonomy needed a scalar of its own; a chain carries
				// what it IS, so [I15]'s corollary stops needing that scalar. What it does
				// NOT stop needing is the corollary's second half — an inherited hop is a
				// DEFAULT, not a step this chain took — which is the merge below.
				$inherited = array_values( $carry['chain'] );
				$ref       = $carry['ref'];
				continue;
			}

			if ( 'refs' === $slug && '' === $arg ) {
				// An ARGLESS step at the ROOT keeps the carried relationship field rather
				// than blanking it: shipped `$last_ref` survives every src override, so
				// `3-src:ref` with no `3-ref` steps through slot 1's field. The step is
				// COMPLETE that way — the carry supplied its argument. With nothing carried
				// there is no argument from anywhere and the step is unfinished; skipping is
				// what stops it compiling to a rootless chain, which resolves the AMBIENT
				// entity and hands back a plausible wrong value (#74).
				if ( '' === $carry['ref'] ) {
					$skip_reason = 'step:refs';
					return null;
				}
				$ref         = $carry['ref'];
				$step['arg'] = $ref;
				$own[]       = $step;
				continue;
			}
		}

		// An INCOMPLETE step is refused wherever it sits, and the reason NAMES it: the two
		// need different author-facing nouns, and deriving that in the preview would be a
		// second copy of the skip rule. Only the ROOT position can complete an argless
		// `refs` from the carry (above) — a later one has no carried argument to take, since
		// what the accumulator holds is the SOURCE's relationship field and not this step's.
		if ( '' === $arg && in_array( $slug, BWS_FOLD_FANNING_SLUGS, true ) ) {
			$skip_reason = 'step:' . $slug;
			return null;
		}
		if ( 'refs' === $slug ) {
			$ref = $arg;
		}
		// PASSED THROUGH VERBATIM, and `$arg` above is only the reading the tests need: both
		// producers of a slot struct already trim (bws_fold_parse_chain array_maps trim over
		// the parts; bws_fold_from_flat trims each option value), so re-normalizing here
		// would be a third owner for a rule two callers already keep — and one that would
		// quietly become the only one if either stopped.
		$own[] = $step;
	}

	// ── the merge: AN INHERITED HOP IS A DEFAULT, NOT A STEP THIS CHAIN TOOK ────
	//
	// [I15]'s corollary, second half. `src(same)` names the same SOURCE, so its steps travel
	// with it — but a slot that goes on to state a step of its OWN may be refining that source
	// rather than hopping again off the end of it, so part of the inherited chain can GIVE WAY.
	//
	// WHAT DECIDES HOW MUCH IS OWNED BY bws_fold_chain_join() AND IS NOT RESTATED HERE. It
	// changed axis three times during #104 — append, then same-slug, then the join — each
	// reading correct on every legacy shape and wrong on a different hand-written one, and the
	// sites that had restated an axis all went stale while the sites naming only the consequence
	// stayed true. Two of the stale ones were in this file (#106). The derivation, both wrong
	// cuts and what each cost live in that function's docblock; CLAUDE.md §Documentation
	// ownership is the general rule this is an instance of.
	//
	// WHAT IS LOCAL HERE is that the shape is EDITOR-AUTHORABLE at all, which a pure chain
	// function cannot know: leave slot 2's source alone and pick a different taxonomy, and the
	// old panel wrote `2-src:same|2-srcTermIn:office`, which the flat resolver read as "the
	// inherited source, into office terms". So this is shipped wire, not a hand-edit hazard.
	// MEASURED both ways on the testbed; §P16.4 has pinned the shape since #74.
	//
	// THIS IS NOT THE SPECIAL CASE #104 DELETED. That one was `$tax_inherit`, a SCALAR held
	// beside the flat triple because a triple cannot carry a step; it is gone and stays gone.
	// This is a rule about what `same` MEANS, and `same` is a slot sentinel the container
	// resolves BEFORE compiling (bws_fold_chain_root never interprets one), so it is the
	// container's vocabulary rather than the chain grammar's. A base tag cannot write it, so
	// nothing here says a base tag's `terms,a;terms,b` should collapse — it hops twice, as
	// its wire says.
	$resolved = function_exists( 'bws_fold_chain_join' )
		? bws_fold_chain_join( $inherited, $own )
		: $inherited;
	foreach ( $own as $own_step ) {
		$resolved[] = $own_step;
	}

	// ── limit: the LAST STEP's own, else the slot-level token ───────────────
	//
	// This is the slot's ITEM bound, which the container slices by; the per-step limits
	// ride the emitted wire and bound the ENGINE's hops. A LIMIT APPLIES TO THE STEP IT
	// IS STATED ON (ADR 0005's own sentence, one level down; ADR 0007) — so only the
	// FINAL step's number can be the item bound, because the final step's outputs ARE
	// the rendered items. The selection this replaced kept the last step that PINNED
	// one, which let a number written on `terms` go on governing output after the
	// author appended an unbounded `refs` — a number stated in one place silently
	// acting on another. An earlier step's limit still bounds its own step, in the
	// engine, exactly as its position says. (Consequence, correct and not a
	// regression: a bound whose step is no longer last stops governing rendered items
	// and goes back to bounding how far its own step spreads.)
	//
	// Read off the slot's OWN steps, never the resolved chain: an inherited step's
	// bound belongs to the slot that stated it, and re-reading it here would restate
	// an earlier slot's number as this one's.
	$limit     = null;
	$last_step = end( $steps );
	if ( is_array( $last_step ) && null !== ( $last_step['limit'] ?? null ) && '' !== $last_step['limit'] ) {
		$limit = (string) $last_step['limit'];
	}
	if ( null === $limit && isset( $slot['opts']['limit'] ) && '' !== $slot['opts']['limit'] ) {
		$limit = (string) $slot['opts']['limit'];
	}
	// …and where the slot states none and its OWN chain does not fan, the bound comes
	// along with the source (see the docblock: selecting containers only).
	//
	// The predicate is literally "my chain does not fan", which is WIDER than the case it
	// exists for — a slot stating its own non-fanning root (`src(site)`, `src(current)`)
	// takes the carried number too. That is deliberate rather than tolerated: a
	// non-fanning source resolves exactly one entity, so any limit over it is inert
	// (CONTEXT.md §Language — the read is 1:1), and narrowing the test would mean a
	// SECOND spelling of "does this chain fan" beside the one the migrator already owns.
	// §P15.7 pins the wider case so the reasoning is tested, not asserted.
	if ( null === $limit && ! $combining && ! bws_fold_chain_fanning_steps( $steps ) ) {
		$limit = $carry['limit'];
	}

	// The slot resolved: feed the accumulator (never before this point).
	$carry['_fed']  = true;
	$carry['chain'] = $resolved;
	$carry['ref']   = $ref;
	$carry['use']   = $use;
	$carry['key']   = $key;
	// What is carried is the QUANTITY this slot resolved, which is its own default where
	// it states nothing — an attempt inheriting `src(refs,office)` should read every
	// office, as that slot does, not fall back to a default chosen for wire it does not
	// have. An UNINTERPRETABLE token is not a parameter and is not carried: it renders as
	// the default here (bws_clamp_limit's is_numeric guard) and must do so downstream too.
	$carry['limit'] = ( null !== $limit && is_numeric( trim( (string) $limit ) ) )
		? (string) $limit
		: (string) $limit_default;

	$opts = array(
		// DEPTH 0, because this string lands in the same option key a base tag states its
		// source in and is re-read by the same bws_fold_chain_from_options(). An empty
		// chain emits '', which is exactly how a base tag spells the ambient entity.
		'src'       => bws_fold_emit_chain( $resolved, 0 ),
		// SUPERSEDED, explicitly. See the docblock: an inherited tag-level `srcTermIn`
		// would grow a term step on every slot's chain, and a leaked step is a plausible
		// value from the wrong entity rather than an empty one ([I15]).
		'ref'       => '',
		'srcTermIn' => '',
		// The read is the CARRY's, seeded by the container with its template's stripped
		// default (bws_fold_empty_carry) — this seam knows no tag and states no default.
		// It held a literal 'key' through 1.18.x for the '' case, reachable only on a
		// container with no read axis, where nothing reads it; the literal was correct
		// solely because `content` (the one non-key default) always seeded its own.
		'use'       => $use,
		'key'       => $key,
	);
	if ( null !== $limit ) {
		$opts['limit'] = $limit;
	}
	return $opts;
}
