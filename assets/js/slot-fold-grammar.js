/**
 * Folded slot-value grammar — the JS TWIN of includes/helpers/slot-fold.php.
 *
 * This file carries NO independent decisions. Every rule here is decided in the
 * PHP owner, and the twin exists only because the two sides run in different
 * languages: the render/migration path parses in PHP, the editor control parses
 * and EMITS in JS, and elimination is unavailable. So agreement is TESTED rather
 * than assumed — tools/test/slot-fold-twin-test.php runs one shared corpus through
 * both implementations and diffs the results, including the grammar CONSTANTS.
 *
 * The spike learned this the hard way: it carried four copies of these constants
 * and all four sat on a superseded separator at once, because nothing forced them
 * to agree. A divergence between the parse copy and the emit copy does not fail a
 * suite — it corrupts author-facing wire.
 *
 * ONE asymmetry the twin must absorb, not fix: GB's PHP option parser unescapes
 * values before a callback sees them, while its JS parser NEVER unescapes. So this
 * side routinely receives the still-escaped value. Both sides therefore unescape
 * free-form values at parse time, and a struct always holds raw author text.
 *
 * Depends on window.bwsReorderKeys (serialization-order-normalizer.js) for
 * canonical token order — reused rather than re-copied, so the fold adds no second
 * JS copy of the KEY_MAP. Absent, emit degrades to insertion order (which the twin
 * harness would catch as a diff against PHP, so the dependency is enforced).
 *
 * @package BWS_Dynamic_Tags
 * @since   1.17.0
 */
( function () {
	'use strict';

	// --- Grammar (mirror of the PHP constants; agreement asserted by the twin harness) ---

	var OPT_SEP = ';';
	var OPT_CLASS = [ ';', ',' ];
	var STEP_SEP = ';';
	// STRICT: `;` only. `+` and `/` are RESERVED — a lenient class SPENDS the char,
	// so accepting one binds it to hop meaning now and would silently change what
	// already-saved wires mean if it is ever given a job.
	var STEP_CLASS = [ ';' ];
	var PART_SEP = ',';
	// STRICT `,`: hop and step share a position (both inside the chain), so `;`
	// cannot also be forgiven here.
	var PART_CLASS = [ ',' ];
	var BR_PAIRS = { '(': ')', '[': ']' };
	var RESERVED = [ '+', '/' ];

	// FOLDED slot key spelling — twin of bws_slot_ordinal() / BWS_FOLD_SLOT_KEY_RE
	// (includes/helpers/slot-fold.php), where the reasoning lives. Deliberately the general
	// `^[A-Z]+$`: slotKey() encodes spreadsheet-style (27 → AA), so a single-letter pattern
	// could reject wire its own encoder produced. No maximum in the grammar — the slot
	// count is a CONTAINER property. Nothing may rely on ASCII order of these keys; both order parsers
	// DECODE to an int, because 'AA' sorts after 'Z' numerically but before it as a string.
	var SLOT_KEY_RE = /^[A-Z]+$/;

	function slotKey( n ) {
		var out = '';
		while ( n > 0 ) {
			var r = ( n - 1 ) % 26;
			out = String.fromCharCode( 65 + r ) + out;
			n = ( n - 1 - r ) / 26;
		}
		return out;
	}

	function slotOrdinal( key ) {
		if ( ! SLOT_KEY_RE.test( String( key ) ) ) {
			return 0;
		}
		var s = String( key );
		var n = 0;
		for ( var i = 0; i < s.length; i++ ) {
			n = n * 26 + ( s.charCodeAt( i ) - 64 );
		}
		return n;
	}

	var TYPES = [ 'title', 'content', 'email', 'phone', 'permalink', 'image', 'datetime_single', 'datetime_range' ];
	var FLAGS = [ 'newTab', 'showCurrentYear', 'showMidnight', 'noLink' ];
	var FREEFORM = [ 'format', 'fallback', 'sep', 'valueSep', 'rangeSep', 'timeSep', 'label' ];
	var FANNING_SLUGS = [ 'refs', 'terms', 'entries' ];

	// Option names the emitter ranks. Membership only — the RANKS come from
	// window.bwsReorderKeys, so this list must not grow its own ordering opinion.
	var RANKED_KEYS = [
		'as', 'size', 'mode', 'valueSep', 'format', 'rangeSep', 'timeSep', 'showCurrentYear', 'showMidnight',
		'src', 'ref', 'srcTermIn', 'limit', 'sep', 'use', 'key',
		'timeKey', 'startKey', 'startTimeKey', 'endKey', 'endTimeKey',
		'linkTo', 'linkKey', 'newTab', 'subject', 'noLink',
		'fallback'
	];

	var CLOSERS = Object.keys( BR_PAIRS ).map( function ( open ) {
		return BR_PAIRS[ open ];
	} );

	/**
	 * Bracket pair for a nesting level: level 1 `()`, level 2 `[]`, alternating.
	 * Level 0 means unwrapped (a base tag's `src:` value) and has no pair of its own.
	 *
	 * @param {number} level 1-based nesting level.
	 * @return {Array} [open, close]
	 */
	function bracketPair( level ) {
		var opens = Object.keys( BR_PAIRS );
		var open = opens[ ( Math.max( 1, level ) - 1 ) % opens.length ];
		return [ open, BR_PAIRS[ open ] ];
	}

	/** Escape the GB-structural chars. A raw `:`/`|` merges slots or truncates the value. */
	function escapeValue( value ) {
		return String( value ).replace( /([:|])/g, '\\$1' );
	}

	/** Reverse of escapeValue(). */
	function unescapeValue( value ) {
		return String( value ).replace( /\\([:|])/g, '$1' );
	}

	/**
	 * Split on a separator CLASS, ignoring separators inside brackets.
	 *
	 * Per-token delimiter rule: the first accepted OPEN char fixes the active pair
	 * until it closes; the other pair is inert text inside it. Bracket-aware at the
	 * STEP position too — `limit[5]` survives a naive split only because a bare
	 * integer cannot contain a separator, and the moment a step token carries
	 * free-form or nested content it splits mid-token.
	 *
	 * @param {string} value Input.
	 * @param {Array}  cls   Accepted separator chars.
	 * @return {Array|Object} Segments, or { error }.
	 */
	function splitDepth( value, cls ) {
		var segments = [];
		var buf = '';
		var depth = 0;
		var pair = null;
		for ( var i = 0; i < value.length; i++ ) {
			var c = value.charAt( i );
			if ( 0 === depth ) {
				if ( BR_PAIRS.hasOwnProperty( c ) ) {
					pair = [ c, BR_PAIRS[ c ] ];
					depth = 1;
				} else if ( -1 !== CLOSERS.indexOf( c ) ) {
					return { error: 'unbalanced close bracket at ' + i };
				} else if ( -1 !== cls.indexOf( c ) ) {
					segments.push( buf );
					buf = '';
					continue;
				}
			} else if ( c === pair[ 0 ] ) {
				depth++;
			} else if ( c === pair[ 1 ] ) {
				depth--;
			}
			buf += c;
		}
		if ( 0 !== depth ) {
			return { error: 'unbalanced open bracket' };
		}
		segments.push( buf );
		return segments;
	}

	/** Split a slot value into its tokens (trimmed, empties dropped). */
	function tokenize( value ) {
		var segments = splitDepth( String( value ), OPT_CLASS );
		if ( segments.error ) {
			return segments;
		}
		return segments.map( function ( s ) {
			return s.trim();
		} ).filter( function ( s ) {
			return '' !== s;
		} );
	}

	/**
	 * Parse one token into { name, val } (val null for a bare token).
	 *
	 * The bracket opened after the name must be the one closed by the FINAL char:
	 * depth may not return to 0 early. That catches close-then-reopen junk arriving
	 * as ONE token (`key(a)x(b)`), which otherwise parses as a plausible value.
	 */
	function parseToken( tok ) {
		var at = -1;
		var open = null;
		Object.keys( BR_PAIRS ).forEach( function ( candidate ) {
			var pos = tok.indexOf( candidate );
			if ( -1 !== pos && ( -1 === at || pos < at ) ) {
				at = pos;
				open = candidate;
			}
		} );
		if ( -1 === at ) {
			return { name: tok, val: null };
		}
		var close = BR_PAIRS[ open ];
		if ( tok.charAt( tok.length - 1 ) !== close ) {
			return { error: "token '" + tok + "' bracket not closed at end" };
		}
		var depth = 0;
		for ( var i = at; i < tok.length; i++ ) {
			if ( tok.charAt( i ) === open ) {
				depth++;
			} else if ( tok.charAt( i ) === close ) {
				depth--;
				if ( 0 === depth && i < tok.length - 1 ) {
					return { error: "token '" + tok + "' has trailing content after its value bracket" };
				}
			}
		}
		return { name: tok.slice( 0, at ), val: tok.slice( at + 1, tok.length - 1 ) };
	}

	/**
	 * Parse a chain value into ordered steps.
	 *
	 * Step shape: positional SLUG, optional positional ARG, then NAMED bracket-kv
	 * tokens (`limit[5]`; unknown names preserved verbatim so a future keyword does
	 * not break an older parser). `limit` is named rather than positional because
	 * argless-with-limit is reachable, and a positional encoding fails SILENTLY
	 * there (`entries,5` parses as arg=5).
	 *
	 * Both `0` and `-1` read as unlimited; the struct holds one representation (`0`),
	 * so nothing downstream needs to know both. A non-numeric limit is an ERROR, not
	 * a silent 0: under `0 = unlimited`, Number('abc')||0 would fan a whole
	 * relationship out of a typo.
	 */
	function parseChain( chainStr ) {
		var rawSteps = splitDepth( String( chainStr ), STEP_CLASS );
		if ( rawSteps.error ) {
			return rawSteps;
		}
		var steps = [];
		for ( var h = 0; h < rawSteps.length; h++ ) {
			var rawStep = rawSteps[ h ].trim();
			if ( '' === rawStep ) {
				continue;
			}
			var parts = splitDepth( rawStep, PART_CLASS );
			if ( parts.error ) {
				return parts;
			}
			parts = parts.map( function ( p ) {
				return p.trim();
			} );
			var step = { slug: parts[ 0 ], arg: null, limit: null, extra: [] };
			var positional = 0;
			for ( var i = 1; i < parts.length; i++ ) {
				var part = parts[ i ];
				if ( '' === part ) {
					continue;   // interior/trailing empty normalizes to absent, never ''
				}
				var tok = parseToken( part );
				if ( tok.error ) {
					return tok;
				}
				if ( null === tok.val ) {
					positional++;
					if ( positional > 1 ) {
						return { error: "chain step '" + rawStep + "': unexpected extra positional token '" + part + "'" };
					}
					step.arg = part;
					continue;
				}
				if ( 'limit' === tok.name ) {
					if ( '' === tok.val.trim() || isNaN( Number( tok.val ) ) ) {
						return { error: "chain step '" + rawStep + "': limit '" + tok.val + "' is not numeric" };
					}
					step.limit = String( Math.max( 0, parseInt( tok.val, 10 ) ) );
					continue;
				}
				step.extra.push( part );
			}
			if ( '' === step.slug ) {
				return { error: "chain step '" + rawStep + "': missing slug" };
			}
			steps.push( step );
		}
		return steps;
	}

	/**
	 * Emit ordered steps as a chain value.
	 *
	 * @param {Array}  steps          Step structs.
	 * @param {number} enclosingLevel Nesting level of whatever WRAPS this chain: 0
	 *                                for a base tag's `src:`, 1 for a slot's
	 *                                `src(...)`. `limit` is emitted one level inside
	 *                                it. The caller that prints the wrapper owns this
	 *                                number — never recompute depth locally (both
	 *                                shipped spike bugs on this axis did).
	 * @return {string} Chain value.
	 */
	function emitChain( steps, enclosingLevel ) {
		var pair = bracketPair( ( void 0 === enclosingLevel ? 1 : enclosingLevel ) + 1 );
		return ( steps || [] ).map( function ( step ) {
			var segment = step.slug;
			if ( null !== step.arg && void 0 !== step.arg && '' !== step.arg ) {
				segment += PART_SEP + step.arg;
			}
			// Guard on null/undefined, NEVER truthiness: `0` means unlimited and must
			// survive as a literal, or an author who pinned "all" silently reverts the
			// next time the contextual default changes.
			if ( null !== step.limit && void 0 !== step.limit && '' !== step.limit ) {
				var n = parseInt( step.limit, 10 );
				segment += PART_SEP + 'limit' + pair[ 0 ] + ( n < 0 ? 0 : n ) + pair[ 1 ];
			}
			( step.extra || [] ).forEach( function ( extra ) {
				segment += PART_SEP + extra;
			} );
			return segment;
		} ).join( STEP_SEP );
	}

	/**
	 * Parse a folded slot value into its structure.
	 *
	 * Container shapes the parse in exactly two already-settled places: a TYPE token
	 * leads only in format-AGNOSTIC containers (join/table — a try_ slot inherits its
	 * template's type), and on a `datetime_*` type `key` is that tag's own field
	 * option rather than the read.
	 *
	 * The read axis and the datetime `key` meaning resolve AFTER the token loop, never
	 * inside it: a hand-edited wire may state the type last, and assigning during the
	 * scan is what made the spike's read order-dependent.
	 *
	 * @param {string} value     Slot value (escaped or not — normalized here).
	 * @param {string} container 'try' | 'join' | 'table'.
	 * @return {Object} Slot struct, or { error }.
	 */
	function parseSlot( value, container ) {
		var tokens = tokenize( value );
		if ( tokens.error ) {
			return tokens;
		}
		var agnostic = ( 'try' !== container );
		var slot = { label: null, type: null, chain: [], read: null, opts: {}, extra: [] };
		var useTok = null;
		var keyTok = null;

		for ( var i = 0; i < tokens.length; i++ ) {
			var parsed = parseToken( tokens[ i ] );
			if ( parsed.error ) {
				return parsed;
			}
			var name = parsed.name;
			var val = parsed.val;

			if ( null === val ) {
				if ( agnostic && null === slot.type && -1 !== TYPES.indexOf( name ) ) {
					slot.type = name;
				} else if ( -1 !== FLAGS.indexOf( name ) ) {
					slot.opts[ name ] = true;
				} else {
					slot.extra.push( tokens[ i ] );
				}
				continue;
			}
			if ( 'label' === name ) {
				slot.label = val;
			} else if ( 'src' === name ) {
				var chain = parseChain( val );
				if ( chain.error ) {
					return chain;
				}
				slot.chain = chain;
			} else if ( 'use' === name ) {
				useTok = val;
			} else if ( 'key' === name ) {
				keyTok = val;
			} else if ( -1 !== RANKED_KEYS.indexOf( name ) ) {
				slot.opts[ name ] = val;
			} else {
				slot.extra.push( tokens[ i ] );
			}
		}

		// `key` on a datetime slot is that tag's own field option, not the read.
		if ( null !== slot.type && 0 === String( slot.type ).indexOf( 'datetime' ) && null !== keyTok ) {
			slot.opts.key = keyTok;
			keyTok = null;
		}

		// Read axis — NAME precedence, order-independent, mirroring the shipped
		// `$use = $options['use'] ?? 'key'` dispatch. Both-present is not author error
		// (GB cannot unset one option from another's value, so a stale `key` legitimately
		// rides the wire), so it resolves rather than flagging.
		if ( null !== useTok && 'key' !== useTok ) {
			slot.read = ( 'same' === useTok ) ? { kind: 'same' } : { kind: 'analog', slug: useTok };
		} else if ( null !== keyTok ) {
			slot.read = { kind: 'key', field: keyTok };
		} else if ( 'key' === useTok ) {
			// `use(key)` with no key token is a KEYED READ WHOSE FIELD IS NOT CHOSEN YET —
			// the state the editor is in between picking "Meta/Option Field" and picking
			// the field. It round-trips (emitSlot writes it back), because the control
			// rewrites the whole value on every commit and derives the read select's value
			// by RE-PARSING it: a shape that parses but never emits made the kind
			// un-selectable. With a field present the bare `key(x)` still wins.
			slot.read = { kind: 'key', field: '' };
		}

		if ( null !== slot.label ) {
			slot.label = unescapeValue( slot.label );
		}
		Object.keys( slot.opts ).forEach( function ( name ) {
			if ( true !== slot.opts[ name ] && -1 !== FREEFORM.indexOf( name ) ) {
				slot.opts[ name ] = unescapeValue( slot.opts[ name ] );
			}
		} );
		return slot;
	}

	/**
	 * Emit a slot structure as its canonical wire value.
	 *
	 * Order: `label` → type → canonically-ranked tokens → preserved unknowns. Ranks
	 * come from window.bwsReorderKeys (the FW-52 normalizer), so the fold adds no
	 * second JS copy of the group ranks.
	 *
	 * @param {Object} slot  Slot struct.
	 * @param {number} level Nesting level of the slot's own tokens — 1 in a slot value.
	 * @return {string} Canonical slot value.
	 */
	function emitSlot( slot, level ) {
		var pair = bracketPair( void 0 === level ? 1 : level );
		var open = pair[ 0 ];
		var close = pair[ 1 ];
		var values = {};

		if ( slot.chain && slot.chain.length ) {
			values.src = emitChain( slot.chain, void 0 === level ? 1 : level );
		}
		var read = slot.read || null;
		if ( read ) {
			if ( 'same' === read.kind ) {
				values.use = 'same';
			} else if ( 'key' === read.kind ) {
				// Field chosen → the bare `key(x)` IS the keyed read. Field not chosen yet
				// → `use(key)` is the only spelling of "keyed, pending", and it must be
				// written or the editor loses the author's kind choice on commit.
				if ( '' !== read.field ) {
					values.key = read.field;
				} else {
					values.use = 'key';
				}
			} else if ( 'analog' === read.kind && 'default' !== read.slug && ( slot.type || null ) !== read.slug ) {
				values.use = read.slug;
			}
		}
		Object.keys( slot.opts || {} ).forEach( function ( name ) {
			values[ name ] = slot.opts[ name ];
		} );

		function emitOne( name, val ) {
			if ( true === val ) {
				return name;
			}
			return name + open + ( -1 !== FREEFORM.indexOf( name ) ? escapeValue( val ) : val ) + close;
		}

		var tokens = [];
		if ( null !== slot.label && void 0 !== slot.label && '' !== slot.label ) {
			tokens.push( emitOne( 'label', slot.label ) );
			delete values.label;
		}
		if ( null !== slot.type && void 0 !== slot.type && 'text' !== slot.type ) {
			tokens.push( slot.type );
		}
		var names = Object.keys( values );
		if ( 'function' === typeof window.bwsReorderKeys ) {
			names = window.bwsReorderKeys( names );
		}
		names.forEach( function ( name ) {
			tokens.push( emitOne( name, values[ name ] ) );
		} );
		( slot.extra || [] ).forEach( function ( extra ) {
			tokens.push( extra );
		} );
		return tokens.join( OPT_SEP );
	}

	/**
	 * Build a folded slot struct from the LEGACY separate option keys.
	 *
	 * ONE mapping shared by the converter migrator, the editor mount-reconcile and
	 * the render dual-read (this is the editor half of it) so the position-dependent
	 * absence rules exist exactly once. Reads all SIX legacy keys — `srcTermIn` and
	 * `limit` are as load-bearing as the other four, and dropping either is an
	 * output change.
	 *
	 * ABSENCE IS CONTAINER-SENSITIVE ON THE READ AXIS, and only there. Selecting
	 * containers carry the read forward, so an absent read at slot ≥2 materializes as
	 * `use(same)`. Combining containers have NO read carry-forward: absent means
	 * UNCONFIGURED and the read stays unset, because synthesizing `same` would make a
	 * previously-skipped slot render AND re-point a LATER slot's `src(same)` at this
	 * slot's source (the shipped `continue` precedes the carry-forward).
	 *
	 * @param {number}  n           Slot ordinal (1-based).
	 * @param {Object}  options     All tag options.
	 * @param {boolean} combining   True for join/table, false for try_.
	 * @param {boolean} perSlotUse  True when the container gives each slot its own
	 *                              read axis. Ignored for combining containers.
	 * @return {Object|null} { slot, legacy: true }, or null when there are no legacy
	 *                       keys or the shipped resolver skips the slot entirely.
	 */
	function foldFromFlat( n, options, combining, perSlotUse ) {
		var prefix = ( 1 === n ) ? '' : String( n ) + '-';
		function read( name ) {
			var v = options[ prefix + name ];
			return ( void 0 === v || null === v || true === v ) ? '' : String( v ).trim();
		}
		var src = read( 'src' );
		var ref = read( 'ref' );
		var tax = read( 'srcTermIn' );
		var use = read( 'use' );
		var key = read( 'key' );
		// Slot 1's prefix is '', so on a SELECTING container the bare `limit` here is the
		// TAG-level key rather than this slot's own — see the PHP owner's comment.
		var limit = ( combining || n >= 2 ) ? read( 'limit' ) : '';

		if ( '' === src && '' === ref && '' === tax && '' === use && '' === key && '' === limit ) {
			return null;
		}
		if ( void 0 === perSlotUse ) {
			perSlotUse = true;
		}

		// An explicitly key-moded selecting slot ≥2 with NO key of its own BORROWED the
		// carried key under the flat resolver — read it as the inherit it was (the PHP
		// owner's docblock carries the reasoning).
		if ( ! combining && perSlotUse && n >= 2 && 'key' === use && '' === key ) {
			use = '';
		}

		// A selecting slot ≥2 with an absent read DISCARDS any stale key (the shipped
		// resolver does that before testing whether the slot has new content at all).
		if ( ! combining && perSlotUse && n >= 2 && '' === use ) {
			key = '';
			if ( '' === src && '' === ref && '' === tax ) {
				return null;   // shipped: $has_new false → slot skipped. Preserve the skip.
			}
		}

		var chain = [];
		function step( slug, arg ) {
			return { slug: slug, arg: ( void 0 === arg ? null : arg ), limit: null, extra: [] };
		}
		if ( 'ref' === src ) {
			// An orphan `ref` is already dead at render; the incomplete step preserves
			// that rather than inventing a source.
			chain.push( step( 'refs', '' !== ref ? ref : null ) );
		} else if ( 'same' === src || ( '' === src && n >= 2 ) ) {
			// Legacy absence at slot ≥2 already MEANT inherit, in both containers —
			// only the read axis diverges. Materialize the sentinel.
			chain.push( step( 'same' ) );
		} else if ( '' !== src ) {
			// An EXPLICIT `current` is a step like any other source value, even though an
			// empty chain also resolves against the ambient entity. Treating it as nothing
			// DELETED slots on containers with no per-slot read axis (see the PHP owner's
			// comment): the whole struct came out empty, and an empty emit means the slot
			// key is never written.
			chain.push( step( src ) );
		}
		// A SITE ROOT NEVER TAKES THE LEGACY TERM STEP — the twin of the PHP owner's rule,
		// which carries the reasoning. Load-bearing since #104: appending it makes the slot
		// resolve a term step off a site source (no post input, hence empty) where the
		// identically-keyed base tag still reads the site.
		if ( '' !== tax && 'site' !== src ) {
			// srcTermIn always FOLLOWS ref: the term hop needs a post input, which the
			// ref step produces (issue #44's order, one global rule in one builder).
			chain.push( step( 'terms', tax ) );
		}

		var slotRead = null;
		if ( '' !== use ) {
			if ( 'same' === use ) {
				slotRead = { kind: 'same' };
			} else if ( 'key' === use ) {
				slotRead = { kind: 'key', field: key };
			} else {
				slotRead = { kind: 'analog', slug: use };
			}
		} else if ( '' !== key ) {
			// `use` absent with a key set is the CANONICAL wire for a keyed read (`use`
			// is _strip_default with `key` first) — not an ambiguous shape.
			slotRead = { kind: 'key', field: key };
		} else if ( combining ) {
			slotRead = null;   // UNCONFIGURED — the shipped resolver skips this slot.
		} else {
			// Selecting: absent read inherits either way, so materialize the sentinel
			// only where a read axis exists to show it (PHP owner's docblock).
			slotRead = ( n >= 2 && perSlotUse ) ? { kind: 'same' } : null;
		}

		// A SELECTING container states `limit` ONCE, at TAG level, and it is every attempt's
		// own default — slots ≥2 must read the same key or the materialized default below
		// shadows the author's number. Combining containers own `limit` per slot, so an
		// absent one there means the slot states none. Read AFTER the emptiness test, never
		// before: a tag-level limit is not content — and only where THIS slot's chain fans,
		// so a slot with nothing to bound gets no limit. A slot that fans by INHERITING is
		// handed the bound with the source it inherits, on the render seam PHP owns alone
		// (bws_fold_slot_chain_options), which is what let the key be retired (#61). See the
		// PHP owner's comment.
		if ( '' === limit && ! combining && chainFanningSteps( chain ).length ) {
			var tagLimit = options.limit;
			limit = ( void 0 === tagLimit || null === tagLimit || true === tagLimit ) ? '' : String( tagLimit ).trim();
		}

		// The limit goes onto the chain's FANNING STEPS, through the same owner a base
		// tag's migration uses. A folded slot defaults to UNLIMITED like any other chain
		// wire, so the flat era's implied 1 has to be materialized here too — see the PHP
		// owner's docblock for why that stopped arriving for free (#60).
		var opts = {};
		var applied = applyLegacyLimit( chain, '' !== limit ? limit : null );
		chain = applied.chain;
		if ( ! applied.consumed
			&& '' !== limit
			&& isNumericLike( limit )
			&& Math.abs( Number( limit ) ) <= Number.MAX_SAFE_INTEGER ) {
			// An explicit limit that owner declined to relocate — 0/-1 (unlimited), or a
			// positive one with no fanning step to carry it. It still needs a carrier: the
			// same mapping serves the render dual-read of UNMIGRATED flat wire, which takes
			// the flat era's default of 1. See the PHP owner's comment.
			var normalized = Math.max( 0, Math.trunc( Number( limit ) ) );
			var fanning = chainFanningSteps( chain );
			if ( fanning.length ) {
				chain[ fanning[ fanning.length - 1 ] ].limit = String( normalized );
			} else {
				// Nothing fans, so there is no step to bound: the token keeps its
				// slot-level meaning (bound a multi-value READ).
				opts.limit = String( normalized );
			}
		}

		return {
			slot: { label: null, type: null, chain: chain, read: slotRead, opts: opts, extra: [] },
			legacy: true
		};
	}

	/**
	 * Whether a `src` VALUE is chain wire rather than a plain legacy token.
	 *
	 * Twin of bws_fold_chain_is_wire(). Conservative by design: every shipped `src`
	 * value is a single bare token (`current`, `site`, `ref`, a registry source
	 * name) and cannot hold a chain separator or bracket — those chars are grammar.
	 * So a value carrying one IS a chain, and a value that IS a fanning slug is a
	 * one-hop chain (`src:refs`). Everything else stays a token.
	 *
	 * Lives HERE, with the grammar it reads, because three editor surfaces need it —
	 * the list-mode reveal predicate, the base-tag chain control, and anything that
	 * follows. Three copies of a wire rule is how the two languages come to disagree
	 * about what a stored value means.
	 *
	 * @param {string} value Raw `src` value.
	 * @return {boolean}
	 */
	function chainIsWire( value ) {
		var v = String( ( value === null || value === undefined ) ? '' : value ).trim();
		if ( '' === v ) {
			return false;
		}
		if ( -1 !== FANNING_SLUGS.indexOf( v ) ) {
			return true;
		}
		return /[;,[\]()]/.test( v );
	}

	/**
	 * The chain's ROOT token — twin of bws_fold_chain_root().
	 *
	 * '' when the chain is empty or LEADS with a hop (the hop applies to the ambient
	 * entity, which is what a bare tag resolves).
	 *
	 * @param {Array} chain Parsed chain.
	 * @return {string}
	 */
	function chainRoot( chain ) {
		if ( ! chain || ! chain.length ) {
			return '';
		}
		var slug = chain[ 0 ].slug || '';
		return ( -1 !== FANNING_SLUGS.indexOf( slug ) ) ? '' : slug;
	}

	/**
	 * Whether a chain HOPS — the `fans` half of bws_fold_chain_resolution().
	 *
	 * CAPACITY read from the wire ("this chain may resolve more than one source"),
	 * never a claim about a given render. A step at position 0 counts only when its
	 * slug fans, because otherwise it is the chain's ROOT rather than a hop.
	 *
	 * @param {Array} chain Parsed chain.
	 * @return {boolean}
	 */
	function chainFans( chain ) {
		return ( chain || [] ).some( function ( link, i ) {
			return i > 0 || -1 !== FANNING_SLUGS.indexOf( link.slug );
		} );
	}

	/**
	 * PHP's is_numeric(), spelled out rather than approximated with Number().
	 *
	 * Number() accepts hex (`0x10` → 16) where PHP refuses it, and parseInt() reads an
	 * exponent as its mantissa. So the two languages would disagree about a value neither
	 * author would ever type — exactly the divergence a twin exists to make impossible.
	 * ONE predicate, because every place that decides whether a `limit` is stated at all
	 * must decide it the same way. (Math.trunc, at the call sites, mirrors PHP's `(int)`
	 * cast, toward zero.)
	 *
	 * @param {*} value Raw value.
	 * @return {boolean}
	 */
	function isNumericLike( value ) {
		var raw = String( ( value === null || value === undefined ) ? '' : value ).trim();
		return /^[+-]?(\d+(\.\d*)?|\.\d+)([eE][+-]?\d+)?$/.test( raw );
	}

	/**
	 * Which steps of a chain FAN — twin of bws_fold_chain_fanning_steps(), which owns the
	 * reasoning. An argless fanning step does not count: the compiler drops it, so it fans
	 * only by inheriting an earlier slot's source, and that slot stated its own bound.
	 *
	 * @param {Array} chain Parsed chain.
	 * @return {number[]} Indexes of the fanning steps, in chain order.
	 */
	function chainFanningSteps( chain ) {
		var out = [];
		( chain || [] ).forEach( function ( chainStep, i ) {
			if ( -1 === FANNING_SLUGS.indexOf( chainStep.slug ) ) {
				return;
			}
			if ( '' === String( chainStep.arg === null || chainStep.arg === undefined ? '' : chainStep.arg ).trim() ) {
				return;
			}
			out.push( i );
		} );
		return out;
	}

	/**
	 * Materialize the limit a LEGACY flat source implied, as per-step limits on the
	 * chain that respells it.
	 *
	 * TWIN of bws_fold_chain_apply_legacy_limit() (includes/helpers/slot-fold.php),
	 * which is where every rule below is decided and explained. Three surfaces call it —
	 * the converter, the mount migrator and the author-conversion commit — and they must
	 * write one tag ONE way, so none of them owns a copy of the rule.
	 *
	 * @param {Array}   chain            Parsed chain, freshly built from legacy keys.
	 * @param {*}       limit            The legacy tag-level `limit` value, or null/undefined.
	 * @param {boolean} [consumeUnlimited] Depth-0 callers pass true: an explicit `0`/`-1`
	 *                                   is reported consumed so the caller deletes the key.
	 *                                   A SLOT caller must not — its mapper also renders
	 *                                   unmigrated flat wire, where the carrier is what
	 *                                   keeps absence from meaning the flat era's 1.
	 * @return {{chain: Array, consumed: boolean}} `consumed` = the caller must now
	 *                                             delete the tag-level key.
	 */
	function applyLegacyLimit( chain, limit, consumeUnlimited ) {
		var steps   = chain || [];
		var stated  = false;

		steps.forEach( function ( chainStep ) {
			if ( -1 === FANNING_SLUGS.indexOf( chainStep.slug ) ) {
				return;
			}
			var stepLimit = ( chainStep.limit === null || chainStep.limit === undefined ) ? '' : String( chainStep.limit );
			if ( '' !== stepLimit ) {
				stated = true;   // the author's own limits win
			}
		} );
		var fanning = chainFanningSteps( steps );

		if ( stated || ! fanning.length ) {
			return { chain: steps, consumed: false };
		}

		var raw      = String( ( limit === null || limit === undefined ) ? '' : limit ).trim();
		var explicit = isNumericLike( raw );


		// Twin of the PHP guard: a magnitude the two languages cannot hold identically is
		// the unlimited case, never a materialized step limit. See slot-fold.php.
		if ( explicit && Math.abs( Number( raw ) ) > Number.MAX_SAFE_INTEGER ) {
			return { chain: steps, consumed: false };
		}

		var value = explicit ? Math.trunc( Number( raw ) ) : 1;

		if ( ! ( value > 0 ) ) {
			// Unlimited either way, so no step limit is written. The KEY goes only at
			// depth 0, and only when it was EXPLICIT (see the PHP owner).
			return { chain: steps, consumed: !! ( consumeUnlimited && explicit ) };
		}

		// The stated number rides the LAST fanning step, 1 every earlier one: per-step
		// limits are per-input and multiply, so the last step alone would give N per
		// parent rather than the N total the flat spelling meant.
		var last = fanning.pop();
		var out  = steps.map( function ( chainStep, i ) {
			if ( i === last ) {
				return Object.assign( {}, chainStep, { limit: String( value ) } );
			}
			if ( -1 !== fanning.indexOf( i ) ) {
				return Object.assign( {}, chainStep, { limit: '1' } );
			}
			return chainStep;
		} );

		return { chain: out, consumed: explicit };
	}

	window.bwsSlotFold = {
		// Grammar surface — exported so the twin harness can assert agreement with
		// the PHP constants rather than trusting that both were edited together.
		grammar: {
			slotKeyRe: SLOT_KEY_RE.source,
			optSep: OPT_SEP,
			optClass: OPT_CLASS,
			stepSep: STEP_SEP,
			stepClass: STEP_CLASS,
			partSep: PART_SEP,
			partClass: PART_CLASS,
			brPairs: BR_PAIRS,
			reserved: RESERVED,
			types: TYPES,
			flags: FLAGS,
			freeform: FREEFORM,
			fanningSlugs: FANNING_SLUGS
		},
		bracketPair: bracketPair,
		escapeValue: escapeValue,
		unescapeValue: unescapeValue,
		splitDepth: splitDepth,
		tokenize: tokenize,
		parseToken: parseToken,
		parseChain: parseChain,
		emitChain: emitChain,
		parseSlot: parseSlot,
		emitSlot: emitSlot,
		foldFromFlat: foldFromFlat,
		slotKey: slotKey,
		slotOrdinal: slotOrdinal,
		// Depth-0 chain questions, twinned with slot-fold-compile.php. Exported so the
		// editor surfaces that need them share ONE copy of each rule.
		chainIsWire: chainIsWire,
		chainRoot: chainRoot,
		chainFans: chainFans,
		// Which steps FAN. Exported for the chain editor, whose per-step limit help says
		// something different once an EARLIER step fans (#95). Not `chainFans`, which is
		// a whole-chain CAPACITY read and counts an argless step; this is the twin of the
		// shared PHP predicate, and the editor must answer the question the renderer
		// answers rather than a second one that agrees most of the time.
		chainFanningSteps: chainFanningSteps,
		// Exported for the mount migrator, which decides whether a tag-level `limit` is a
		// number to push down at all. Its own docblock is why: ONE predicate, or the two
		// languages disagree about hex and exponents where PHP's is_numeric() does not.
		isNumericLike: isNumericLike,
		applyLegacyLimit: applyLegacyLimit
	};
}() );
