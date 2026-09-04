<?php
/**
 * Standalone unit harness for the folded slot-value grammar —
 * includes/helpers/slot-fold.php, THE single PHP owner of the FW-56/57 wire.
 *
 * The real functions are LOADED, not copied: they are pure (no WP/GB symbols
 * beyond the pure serialization-order helper), and a test-local copy of the
 * grammar is exactly the drift this file exists to catch — the spike carried four
 * copies of these constants and all four sat on a superseded separator at once.
 *
 * Graduated from the FW-56/57 grammar spike (178 cases on the spike grammar;
 * `tools/spike/` and the spike harness were deleted at 1.17.0 — history in git).
 * Two deliberate changes at graduation:
 *   - The separator set is no longer PARAMETERIZED. The spike re-ran its whole
 *     suite under an alternate char set to prove the grammar was not
 *     char-dependent; the shipped grammar is a decided set of constants, so what
 *     survives that intent is bws_fold_grammar_validate() (per-position class
 *     disjointness) plus the RESERVED-char assertions, both below.
 *   - `limit` is BRACKET-KV (`limit[5]`), not the spike's positional third token.
 *
 * Properties:
 *   P1 IDENTITY     — emit(parse(s)) === s for canonical corpus strings.
 *   P2 FIXPOINT     — parse(emit(struct)) == struct.
 *   P3 NORMALIZE    — hand-edited input parses and re-emits CANONICAL.
 *   P4 ESCAPE       — free-form `:`/`|` survive the GB layer both directions, and
 *                     no emitted value carries an unescaped one (the GB JS parser
 *                     splits with limit 2 and DISCARDS the tail).
 *   P5 BALANCE      — balanced inner brackets survive; unbalanced is a detected
 *                     error, never silent corruption.
 *   P6 ERRORS       — malformed input yields a flagged error and no crash.
 *   P7 BACKSLASH    — PHP date-format literals + an author's own `\:`; a slot value
 *                     never ENDS in a backslash (which would escape GB's `|` and
 *                     merge two slots).
 *   P8 LENIENT      — forgiven twins parse and re-emit canonical; RESERVED `+`/`/`
 *                     carry no hop meaning and stay verbatim inside a value.
 *   P9 LIMIT        — bracket-kv form, `0` = unlimited surviving as a literal,
 *                     `-1` normalizing to `0`, non-numeric REJECTED (under
 *                     `0 = unlimited`, `(int)'abc' === 0` would fan a whole
 *                     relationship from a typo), and argless-with-limit needing no
 *                     positional hole.
 *   P10 DEPTH       — bracket ALTERNATION: the same chain spells `limit(3)` at
 *                     depth 0 (a base tag's `src:`) and `limit[3]` inside a slot,
 *                     asserted on the EMITTED STRING via a bracket walk that is
 *                     independent of the emitter's own model of depth. Both shipped
 *                     spike bugs on this axis passed a harness written from the
 *                     same wrong premise as the code.
 *   P11 READ        — read precedence is by token NAME, not order: `use` wins when
 *                     present and not `key`. Hand-edit-only, so the round-trip
 *                     property cannot reach it (the control never emits both).
 *   P12 LEGACY      — bws_fold_from_flat(): the six-key input surface, and the
 *                     read-axis CONTAINER divergence (selecting carries over, combining
 *                     leaves unset because a skipped combining slot must not start
 *                     rendering or re-point a later slot's `src(same)`).
 *
 * Run:  php tools/test/slot-fold-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
// The MIGRATOR is loaded, not transcribed: P13.1/P14.x assert that migrated wire
// resolves like the legacy wire it replaced, which is only evidence if the thing being
// migrated by is the shipped one. Its container CONFIG is derived the same way (via
// TagTemplateRegistry::try_slot_axes) rather than hand-listed here.
require __DIR__ . '/../../includes/helpers/slot-fold-migrate.php';
require __DIR__ . '/../../includes/classes/class-tag-template-registry.php';
// bws_clamp_limit — the single limit INTERPRETER. §P13/§P14 compare what each era
// RESOLVES rather than what it spells, so the walks below must clamp exactly as the
// container arms do; re-inlining the rule here is what the extraction removed.
// BWS_USE_STRIPPED_DEFAULTS — the try_ walks below are driven with each template's
// stripped `use` default, and that value is DATA with an owner. A harness copy of
// data is not an independent check, only a second thing to update.
require __DIR__ . '/../../includes/helpers/registration-helpers.php';
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';

// The two templates the P13/P14 walks model, each driven with its OWN stripped `use`
// default read from the owner rather than re-typed here — a walk seeded with the wrong
// default compares two spellings of the same mistake and passes.
$text_default    = bws_use_stripped_default( 'text' );
$content_default = bws_use_stripped_default( 'content' );
require __DIR__ . '/../../includes/helpers/field-helpers.php';
// THE ENGINE'S INPUT-KIND LIST, because the seam's `same` merge derives from it: an
// carried step is dropped only where its slug CANNOT repeat, and "can it repeat" is
// answered from BWS_TRAVERSAL_STEP_INPUT_KINDS + BWS_FOLD_STEP_KINDS rather than from a
// literal. Without this require the predicate degrades to "everything repeats", which is
// the SAFE direction (no drop) and would make §P16.4 fail rather than pass vacuously —
// checked, and the non-vacuity assertion beside §P16.4 says so out loud.
if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post { public $ID; public function __construct( $id ) { $this->ID = $id; } }
}
if ( ! class_exists( 'WP_Term' ) ) {
	class WP_Term { public $term_id; public function __construct( $id ) { $this->term_id = $id; } }
}
if ( ! function_exists( 'bws_extract_post_id' ) ) {
	function bws_extract_post_id( $post_data ) {
		if ( is_numeric( $post_data ) ) { return intval( $post_data ); }
		if ( $post_data instanceof WP_Post ) { return $post_data->ID; }
		if ( is_array( $post_data ) && isset( $post_data['ID'] ) ) { return $post_data['ID']; }
		return false;
	}
}
require __DIR__ . '/../../includes/helpers/traversal-pipeline.php';

// One WP call in the COMPILED path (§P18.4 asks what the engine is told): the taxonomy slug
// of a `terms` hop. Same shim fold-chain-compile-test.php uses — LOWERCASE FIRST, then strip,
// which is WP's order; stripping first deletes every capital.
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
}

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

// ── GB layer mirrors (test infrastructure — GB's own parse behaviour) ───────

/** Mirror of GB's PHP option split: pairs on unescaped `|`, kv on the FIRST unescaped `:`. */
function t_gb_parse_options( $options_string ) {
	$out = array();
	foreach ( preg_split( '/(?<!\\\\)\|/', $options_string ) as $pair ) {
		if ( '' === $pair ) {
			continue;
		}
		$kv        = preg_split( '/(?<!\\\\):/', $pair, 2 );
		$out[ $kv[0] ] = isset( $kv[1] ) ? bws_fold_unescape( $kv[1] ) : true;
	}
	return $out;
}

/** Mirror of the GB JS parser's sharp edge: split limit 2 DISCARDS the tail, no unescape. */
function t_gb_js_value( $pair ) {
	$parts = preg_split( '/(?<!\\\\):/', $pair );
	return isset( $parts[1] ) ? $parts[1] : true;
}

/**
 * Assert no SAME-CHAR immediate bracket nesting anywhere in an emitted string.
 * Independent of how the emitter models depth, so it catches the whole class
 * mechanically. Scoped to values with no bracket-bearing free-form content
 * (author text is not structural and legitimately nests either pair).
 */
function t_no_same_char_nesting( $wire ) {
	$stack = array();
	$len   = strlen( $wire );
	for ( $i = 0; $i < $len; $i++ ) {
		$c = $wire[ $i ];
		if ( isset( BWS_FOLD_BR_PAIRS[ $c ] ) ) {
			if ( $stack && end( $stack ) === $c ) {
				return "same-char nesting '$c' at $i";
			}
			$stack[] = $c;
		} elseif ( in_array( $c, BWS_FOLD_BR_PAIRS, true ) ) {
			array_pop( $stack );
		}
	}
	return '';
}

// ── Grammar validator ───────────────────────────────────────────────────────

check( 'V0 shipped grammar classes validate', array() === bws_fold_grammar_validate(), implode( '; ', bws_fold_grammar_validate() ) );
check( 'V1 bracket alternation: level 1 = ()', array( '(', ')' ) === bws_fold_bracket_pair( 1 ), var_export( bws_fold_bracket_pair( 1 ), true ) );
check( 'V2 bracket alternation: level 2 = []', array( '[', ']' ) === bws_fold_bracket_pair( 2 ), var_export( bws_fold_bracket_pair( 2 ), true ) );
check( 'V3 bracket alternation: level 3 wraps to ()', array( '(', ')' ) === bws_fold_bracket_pair( 3 ), var_export( bws_fold_bracket_pair( 3 ), true ) );
check( 'V4 reserved chars are not separators', array() === array_intersect( BWS_FOLD_RESERVED, array_merge( BWS_FOLD_OPT_CLASS, BWS_FOLD_STEP_CLASS, BWS_FOLD_PART_CLASS ) ), '' );

// ── P1 IDENTITY ─────────────────────────────────────────────────────────────

$corpus = array(
	// try_ slots — format-fixed container, no type token
	array( 'try', 'key(custom_field)' ),
	array( 'try', 'src(terms,category);use(title)' ),
	array( 'try', 'src(refs,office;refs,region);key(name)' ),
	array( 'try', 'src(refs,office);use(same)' ),                       // new source, carried read
	array( 'try', 'src(same);key(second)' ),                            // same source, new read
	array( 'try', 'src(refs,office);key(second)' ),                     // both new
	array( 'try', 'src(post,9999;refs,related_staff,limit[5]);use(title)' ),
	// join — agnostic, type leads
	array( 'join', 'title' ),
	array( 'join', 'phone;key(mobile)' ),
	array( 'join', 'email;key(contact_email)' ),
	array( 'join', 'key(note)' ),
	array( 'join', 'title;src(refs,office)' ),
	array( 'join', 'title;linkTo(permalink)' ),
	array( 'join', 'title;linkTo(key);linkKey(profile_url);newTab' ),
	array( 'join', 'email;key(contact_email);linkTo(key);linkKey(profile_url)' ),
	// table columns — label first, then type
	array( 'table', 'label(Name);title;linkTo(permalink)' ),
	array( 'table', 'label(Office);src(refs,office);key(address)' ),
	array( 'table', 'label(Depts);src(terms,department,limit[3]);use(title)' ),
	// datetime slots — `key` is the datetime field option, not the read
	array( 'join', 'datetime_range;as(date);format(M j);startKey(start_date);endKey(end_date)' ),
	array( 'join', 'datetime_single;key(event_date);timeKey(event_time)' ),
	// combining slot with a chain and NO read: legal folded wire meaning SKIP —
	// exactly what the repeater seeds. Must never be treated as malformed.
	array( 'join', 'src(same)' ),
	array( 'join', 'src(refs,office)' ),
	// unlimited, author-pinned
	array( 'table', 'src(rows,staff_members,limit[0]);key(name)' ),
);
foreach ( $corpus as $i => $case ) {
	list( $container, $wire ) = $case;
	$slot = bws_fold_parse_slot( $wire, $container );
	check( "P1.$i parse ok: $wire", ! isset( $slot['error'] ), $slot['error'] ?? '' );
	if ( isset( $slot['error'] ) ) {
		continue;
	}
	$out = bws_fold_emit_slot( $slot );
	check( "P1.$i identity: $wire", $out === $wire, "got: $out" );
}

// ── P2 FIXPOINT ─────────────────────────────────────────────────────────────

$structs = array(
	array( 'join', array(
		'label' => null,
		'type'  => 'phone',
		'chain' => array( array( 'slug' => 'refs', 'arg' => 'office', 'limit' => null, 'extra' => array() ) ),
		'read'  => array( 'kind' => 'key', 'field' => 'mobile' ),
		'opts'  => array(),
		'extra' => array(),
	) ),
	array( 'table', array(
		'label' => 'When',
		'type'  => 'datetime_range',
		'chain' => array(),
		'read'  => null,
		'opts'  => array( 'as' => 'date', 'startKey' => 'start_date', 'endKey' => 'end_date', 'format' => 'M j' ),
		'extra' => array(),
	) ),
	array( 'try', array(
		'label' => null,
		'type'  => null,
		'chain' => array(
			array( 'slug' => 'post', 'arg' => '9999', 'limit' => null, 'extra' => array() ),
			array( 'slug' => 'refs', 'arg' => 'related_staff', 'limit' => '5', 'extra' => array() ),
		),
		'read'  => array( 'kind' => 'same' ),
		'opts'  => array(),
		'extra' => array(),
	) ),
	// Unlimited pinned on the terminal step (the {{table}} row-set shape).
	array( 'table', array(
		'label' => null,
		'type'  => null,
		'chain' => array( array( 'slug' => 'rows', 'arg' => 'staff_members', 'limit' => '0', 'extra' => array() ) ),
		'read'  => array( 'kind' => 'key', 'field' => 'name' ),
		'opts'  => array(),
		'extra' => array(),
	) ),
);
foreach ( $structs as $i => $case ) {
	list( $container, $struct ) = $case;
	$wire = bws_fold_emit_slot( $struct );
	$back = bws_fold_parse_slot( $wire, $container );
	$a    = $struct;
	$b    = $back;
	if ( ! isset( $b['error'] ) ) {
		ksort( $a['opts'] );
		ksort( $b['opts'] );
	}
	check( "P2.$i fixpoint via: $wire", $a == $b, var_export( $back, true ) );
}

// ── P3 NORMALIZE ────────────────────────────────────────────────────────────

$hand = array(
	// reordered tokens → canonical order restored (src before key)
	array( 'try', 'key(name);src(refs,office)', 'src(refs,office);key(name)' ),
	// stray whitespace around tokens and inside the chain
	array( 'try', ' key(name) ; src( refs,office )', 'src(refs,office);key(name)' ),
	// join: type stated last → recognized, re-emitted leading
	array( 'join', 'key(mobile);phone', 'phone;key(mobile)' ),
	// redundant analog naming the slot's own type → dropped
	array( 'join', 'title;use(title)', 'title' ),
	// explicit `default` analog → dropped (absent IS default)
	array( 'try', 'src(refs,office);use(default)', 'src(refs,office)' ),
	// unknown token preserved verbatim, appended (ADR 0004 tolerance)
	array( 'try', 'mystery(x);key(name)', 'key(name);mystery(x)' ),
	// canonical group order across groups: format → source → link → fallback
	array( 'join', 'fallback(TBA);key(x);as(date);linkTo(permalink)', 'as(date);key(x);linkTo(permalink);fallback(TBA)' ),
	// legacy-shaped `use(key)` + key → plain keyed read, emitted as bare key()
	array( 'try', 'use(key);key(role)', 'key(role)' ),
	// `use(key)` with NO key → keyed read, field pending. SURVIVES emit: the control
	// re-parses what it wrote to drive the read select, so dropping this shape made
	// "Meta/Option Field" un-selectable (it reverted to unset on commit, taking the
	// field picker with it).
	array( 'join', 'src(same);use(key)', 'src(same);use(key)' ),
	array( 'try', 'use(key)', 'use(key)' ),
);
foreach ( $hand as $i => $case ) {
	list( $container, $in, $want ) = $case;
	$slot = bws_fold_parse_slot( $in, $container );
	check( "P3.$i parse ok: $in", ! isset( $slot['error'] ), $slot['error'] ?? '' );
	if ( isset( $slot['error'] ) ) {
		continue;
	}
	$out = bws_fold_emit_slot( $slot );
	check( "P3.$i normalize: $in", $out === $want, "want: $want\n      got:  $out" );
}

// ── P4 ESCAPE — the whole GB round trip ─────────────────────────────────────

$slot1 = array(
	'label' => 'Price: USD',
	'type'  => 'text',
	'chain' => array(),
	'read'  => array( 'kind' => 'key', 'field' => 'price' ),
	'opts'  => array(),
	'extra' => array(),
);
$slot2 = array(
	'label' => null,
	'type'  => 'datetime_single',
	'chain' => array(),
	'read'  => null,
	'opts'  => array( 'key' => 'event_time', 'as' => 'time', 'format' => 'g:i A' ),
	'extra' => array(),
);
$v1             = bws_fold_emit_slot( $slot1 );
$v2             = bws_fold_emit_slot( $slot2 );
$options_string = '1:' . $v1 . '|2:' . $v2;

check( 'P4.0 wire escapes the free-form colon', false !== strpos( $v1, '\\:' ), "v1: $v1" );
foreach ( array( $v1, $v2 ) as $i => $v ) {
	check( "P4.1 value $i has no unescaped :|", ! preg_match( '/(?<!\\\\)[:|]/', $v ), "value: $v" );
}
check( 'P4.2 GB JS split keeps the whole value', t_gb_js_value( '1:' . $v1 ) === $v1, '' );

$gb = t_gb_parse_options( $options_string );
check( 'P4.3 GB splits both slots', isset( $gb['1'], $gb['2'] ), var_export( $gb, true ) );
if ( isset( $gb['1'], $gb['2'] ) ) {
	$p1 = bws_fold_parse_slot( $gb['1'], 'table' );
	$p2 = bws_fold_parse_slot( $gb['2'], 'join' );
	check( 'P4.4 label survives the GB layer', ! isset( $p1['error'] ) && 'Price: USD' === $p1['label'], var_export( $p1, true ) );
	check( 'P4.5 format survives the GB layer', ! isset( $p2['error'] ) && 'g:i A' === $p2['opts']['format'], var_export( $p2, true ) );
	if ( ! isset( $p1['error'], $p2['error'] ) ) {
		$re = '1:' . bws_fold_emit_slot( $p1 ) . '|2:' . bws_fold_emit_slot( $p2 );
		check( 'P4.6 full tag round trip', $re === $options_string, "want: $options_string\n      got:  $re" );
	}
}
// The JS side receives the still-ESCAPED value — the parser must cope identically.
$p1js = bws_fold_parse_slot( $v1, 'table' );
check( 'P4.7 parse of an escaped-in value', ! isset( $p1js['error'] ) && 'Price: USD' === $p1js['label'], var_export( $p1js, true ) );

// Reserved + grammar chars inside author TEXT are content, never grammar.
$content = array(
	'label' => null,
	'type'  => 'datetime_single',
	'chain' => array(),
	'read'  => null,
	'opts'  => array( 'key' => 'd', 'fallback' => 'Date/time TBA', 'format' => 'F j, Y g:i A' ),
	'extra' => array(),
);
$vc = bws_fold_emit_slot( $content );
$pc = bws_fold_parse_slot( $vc, 'join' );
check( 'P4.8 reserved / inside a fallback is content', ! isset( $pc['error'] ) && 'Date/time TBA' === $pc['opts']['fallback'], "wire: $vc → " . var_export( $pc, true ) );
check( 'P4.9 comma inside a format is content', ! isset( $pc['error'] ) && 'F j, Y g:i A' === $pc['opts']['format'], var_export( $pc, true ) );

// ── P5 BALANCE ──────────────────────────────────────────────────────────────

$balanced = array(
	'label' => 'Size (cm)',
	'type'  => 'text',
	'chain' => array(),
	'read'  => array( 'kind' => 'key', 'field' => 'size' ),
	'opts'  => array(),
	'extra' => array(),
);
$vb = bws_fold_emit_slot( $balanced );
$pb = bws_fold_parse_slot( $vb, 'table' );
check( 'P5.0 balanced inner brackets survive', ! isset( $pb['error'] ) && 'Size (cm)' === $pb['label'], "wire: $vb → " . var_export( $pb, true ) );

$sep_inside = array(
	'label' => 'Price; USD',
	'type'  => 'text',
	'chain' => array(),
	'read'  => array( 'kind' => 'key', 'field' => 'price' ),
	'opts'  => array(),
	'extra' => array(),
);
$vs = bws_fold_emit_slot( $sep_inside );
$ps = bws_fold_parse_slot( $vs, 'table' );
check( 'P5.1 opt separator inside a bracket does not split', ! isset( $ps['error'] ) && 'Price; USD' === $ps['label'], "wire: $vs" );

// ── P6 ERRORS ───────────────────────────────────────────────────────────────

$bad = array(
	'label(Note (TBD);key(note)X',            // trailing junk after the close
	'label(Note;key(note)',                    // unbalanced open
	'key(note))',                              // stray close
	'src(refs,office)+use(same)',               // close-then-reopen in ONE token
	// Close-then-reopen where BOTH halves are individually balanced: only the
	// depth-return guard catches this. Without it the token parses as name `key`
	// with the value `a)x(b` — a silently wrong read rather than an error.
	'key(a)x(b)',
	'src(refs,office,extra,more)',              // two positional args after the slug
	'src(refs,office,limit[abc])',              // non-numeric limit — never silently 0
	'src(,office)',                             // missing slug
);
foreach ( $bad as $i => $in ) {
	$r = bws_fold_parse_slot( $in, 'table' );
	check( "P6.$i flagged: $in", isset( $r['error'] ), var_export( $r, true ) );
}
$r = bws_fold_tokenize( 'label(Note (TBD)' );
check( 'P6.7 unbalanced free-form flagged at tokenize', isset( $r['error'] ), var_export( $r, true ) );

// ── P7 BACKSLASH ────────────────────────────────────────────────────────────

$dt_format = array(
	'label' => null,
	'type'  => 'datetime_single',
	'chain' => array(),
	'read'  => null,
	'opts'  => array( 'key' => 'event', 'format' => 'l, F jS \\a\\t g:i A' ),
	'extra' => array(),
);
$vf = bws_fold_emit_slot( $dt_format );
check( 'P7.0 date literals kept, colon escaped', false !== strpos( $vf, '\\a\\t' ) && false !== strpos( $vf, 'g\\:i' ), "wire: $vf" );
$pf = bws_fold_parse_slot( $vf, 'join' );
check( 'P7.1 date format round trip', ! isset( $pf['error'] ) && 'l, F jS \\a\\t g:i A' === $pf['opts']['format'], var_export( $pf, true ) );
$pg = bws_fold_parse_slot( t_gb_parse_options( '1:' . $vf )['1'], 'join' );
check( 'P7.2 date format through the GB layer', ! isset( $pg['error'] ) && 'l, F jS \\a\\t g:i A' === $pg['opts']['format'], var_export( $pg, true ) );

$literal = array(
	'label' => null,
	'type'  => 'text',
	'chain' => array(),
	'read'  => array( 'kind' => 'key', 'field' => 'x' ),
	'opts'  => array( 'fallback' => 'g\\:i raw' ),
	'extra' => array(),
);
$vl = bws_fold_emit_slot( $literal );
$pl = bws_fold_parse_slot( $vl, 'join' );
check( 'P7.3 author backslash-colon round trip', ! isset( $pl['error'] ) && 'g\\:i raw' === $pl['opts']['fallback'], "wire: $vl → " . var_export( $pl, true ) );

$tail = array(
	'label' => null,
	'type'  => 'text',
	'chain' => array(),
	'read'  => array( 'kind' => 'key', 'field' => 'x' ),
	'opts'  => array( 'fallback' => 'ends with \\' ),
	'extra' => array(),
);
$vt = bws_fold_emit_slot( $tail );
check( 'P7.4 slot value never ends with a backslash', '\\' !== substr( $vt, -1 ), "wire: $vt" );
check( 'P7.5 hazard demo: a trailing backslash merges slots', 1 === count( t_gb_parse_options( '1:a\\|2:b' ) ), '' );
$pt = bws_fold_parse_slot( $vt, 'join' );
check( 'P7.6 trailing-backslash fallback round trip', ! isset( $pt['error'] ) && 'ends with \\' === $pt['opts']['fallback'], var_export( $pt, true ) );

// ── P8 LENIENT + RESERVED ───────────────────────────────────────────────────

$lenient = array(
	array( 'try', 'src(refs,office),key(name)', 'src(refs,office);key(name)' ),   // `,` forgiven at token level
	array( 'try', 'key[name]', 'key(name)' ),                                      // L2 pair accepted at L1
	array( 'try', 'src[refs,office];key[name]', 'src(refs,office);key(name)' ),
	array( 'join', 'phone,src[post,9999;refs,related_staff,limit(5)],key[mobile]', 'phone;src(post,9999;refs,related_staff,limit[5]);key(mobile)' ),
);
foreach ( $lenient as $i => $case ) {
	list( $container, $in, $want ) = $case;
	$slot = bws_fold_parse_slot( $in, $container );
	check( "P8.$i parse ok: $in", ! isset( $slot['error'] ), $slot['error'] ?? '' );
	if ( isset( $slot['error'] ) ) {
		continue;
	}
	$out = bws_fold_emit_slot( $slot );
	check( "P8.$i normalize: $in", $out === $want, "want: $want\n      got:  $out" );
}
// Per-token delimiter rule: the OTHER pair is inert inside a value.
$inert = bws_fold_parse_slot( 'label[Note (TBD];key(note)', 'table' );
check( 'P8.4 inert other-pair inside a free-form value', ! isset( $inert['error'] ) && 'Note (TBD' === $inert['label'], var_export( $inert, true ) );
// A lone close char of the non-canonical pair at depth 0 still errors.
check( 'P8.5 stray alt-pair close flagged', isset( bws_fold_parse_slot( 'key(note)]', 'try' )['error'] ), '' );

// RESERVED chars: declared in the grammar, never inferred from the hop class — a
// char wrongly re-admitted as a hop would otherwise disarm its own guard, so the
// suite would go quiet exactly when the property breaks.
foreach ( BWS_FOLD_RESERVED as $rc ) {
	check( "P8.r'$rc' not in step_class", ! in_array( $rc, BWS_FOLD_STEP_CLASS, true ), '' );
	// It did NOT split, so `refs,region` lands as a second positional token and must
	// be REJECTED rather than silently traversed as two hops.
	$r = bws_fold_parse_slot( "src(refs,office{$rc}refs,region);key(name)", 'try' );
	check( "P8.r'$rc' misparse flagged, not silent", isset( $r['error'] ), var_export( $r, true ) );
	// Inside an arg it is ordinary content and must round-trip verbatim.
	$r  = bws_fold_parse_slot( "src(refs,off{$rc}ice);key(name)", 'try' );
	$ok = ! isset( $r['error'] ) && 1 === count( $r['chain'] ) && "off{$rc}ice" === $r['chain'][0]['arg'];
	check( "P8.r'$rc' inert inside an arg", $ok, var_export( $r, true ) );
	if ( $ok ) {
		check( "P8.r'$rc' arg re-emits verbatim", false !== strpos( bws_fold_emit_slot( $r ), "off{$rc}ice" ), '' );
	}
}

// ── P9 LIMIT ────────────────────────────────────────────────────────────────

$limit_cases = array(
	// wire in, expected step limit, expected re-emit
	array( 'src(refs,office,limit[5])', '5', 'src(refs,office,limit[5])' ),
	array( 'src(refs,office,limit[0])', '0', 'src(refs,office,limit[0])' ),          // 0 survives as a literal
	array( 'src(refs,office,limit[-1])', '0', 'src(refs,office,limit[0])' ),         // parse both, emit 0
	array( 'src(rows,limit[5])', '5', 'src(rows,limit[5])' ),                  // argless step, no positional hole
	array( 'src(refs,office,limit(5))', '5', 'src(refs,office,limit[5])' ),          // lenient pair, canonical out
);
foreach ( $limit_cases as $i => $case ) {
	list( $in, $want_limit, $want_wire ) = $case;
	$slot = bws_fold_parse_slot( $in, 'table' );
	check( "P9.$i parse ok: $in", ! isset( $slot['error'] ), $slot['error'] ?? '' );
	if ( isset( $slot['error'] ) ) {
		continue;
	}
	check( "P9.$i limit value: $in", $want_limit === $slot['chain'][0]['limit'], var_export( $slot['chain'], true ) );
	check( "P9.$i re-emit: $in", $want_wire === bws_fold_emit_slot( $slot ), 'got: ' . bws_fold_emit_slot( $slot ) );
}
// A slot-level `limit` (no fanning step to attach to) is an ordinary source-group token.
$slot_limit = bws_fold_parse_slot( 'limit(3);key(prices)', 'join' );
check( 'P9.5 slot-level limit is an opt token', ! isset( $slot_limit['error'] ) && '3' === $slot_limit['opts']['limit'], var_export( $slot_limit, true ) );
check( 'P9.5 slot-level limit re-emits before use/key', 'limit(3);key(prices)' === bws_fold_emit_slot( $slot_limit ), 'got: ' . bws_fold_emit_slot( $slot_limit ) );

// TWO LIVE EXAMPLES OF COVERAGE THAT ONLY LOOKED PRESENT before these rows/cases were
// added: (1) the bracket-aware step split below — nothing exercised a separator
// INSIDE a step token's brackets until P9.6, so a naive (non-bracket-aware) splitter
// could ship green; (2) a truthiness guard on `limit` — dropping a numeric `0`
// (unlimited) is invisible to a parse-only corpus, since `0` and absent both parse.
// The struct-emit path that catches it (numeric values a string-only parse corpus
// cannot reach) lives in the twin's `emitStructs` corpus section, exercised by
// slot-fold-twin-test.php.
//
// A step's own tokens may carry BRACKETED content containing a step separator.
// `limit[5]` is safe under a naive split only by ACCIDENT (a bare integer cannot
// contain a separator); the moment a step token holds free-form or nested content
// — which is FW-24's whole-tag-in-slot shape — a naive split shreds it mid-token.
// These rows are what make the splitter's bracket-awareness a tested property
// rather than an unexercised claim.
// The HOP split is bracket-aware on the same grounds: a `;` inside a step token's
// brackets is content, not the next hop.
$nested = array(
	// in, canonical out (a known `limit` sorts ahead of preserved unknowns)
	array( 'src(refs,related_staff,label[A, B])', 'src(refs,related_staff,label[A, B])' ),
	array( 'src(refs,related_staff,tag[datetime_single,as[date]])', 'src(refs,related_staff,tag[datetime_single,as[date]])' ),
	array( 'src(refs,related_staff,label[A, B],limit[5])', 'src(refs,related_staff,limit[5],label[A, B])' ),
	array( 'src(refs,related_staff,label[A; B])', 'src(refs,related_staff,label[A; B])' ),
);
foreach ( $nested as $i => $case ) {
	list( $in, $want ) = $case;
	$slot = bws_fold_parse_slot( $in, 'table' );
	check( "P9.6.$i bracket-aware step split parses: $in", ! isset( $slot['error'] ), $slot['error'] ?? '' );
	if ( isset( $slot['error'] ) ) {
		continue;
	}
	check( "P9.6.$i one step, arg intact: $in", 1 === count( $slot['chain'] ) && 'related_staff' === $slot['chain'][0]['arg'], var_export( $slot['chain'], true ) );
	check( "P9.6.$i unknown step token preserved verbatim: $in", $want === bws_fold_emit_slot( $slot ), 'got: ' . bws_fold_emit_slot( $slot ) );
}

// ── P10 DEPTH / ALTERNATION ─────────────────────────────────────────────────

$steps = array( array( 'slug' => 'refs', 'arg' => 'related_staff', 'limit' => '5', 'extra' => array() ) );
check( 'P10.0 depth 0 (base tag src:) spells limit(5)', 'refs,related_staff,limit(5)' === bws_fold_emit_chain( $steps, 0 ), 'got: ' . bws_fold_emit_chain( $steps, 0 ) );
check( 'P10.1 inside a slot src(...) spells limit[5]', 'refs,related_staff,limit[5]' === bws_fold_emit_chain( $steps, 1 ), 'got: ' . bws_fold_emit_chain( $steps, 1 ) );
// Either spelling parses at either depth (the parser discovers the pair from the CHAR).
foreach ( array( 'refs,related_staff,limit(5)', 'refs,related_staff,limit[5]' ) as $i => $chain_str ) {
	$parsed = bws_fold_parse_chain( $chain_str );
	check( "P10.2.$i depth-agnostic parse: $chain_str", ! isset( $parsed['error'] ) && '5' === $parsed[0]['limit'], var_export( $parsed, true ) );
}
// The invariant asserted on OUTPUT, not on reasoning about depth: a {{table}} tag
// carries BOTH wrapper styles at once (a tag-level row chain and a slot column chain).
$row_chain  = 'src:' . bws_fold_emit_chain(
	array(
		array( 'slug' => 'post', 'arg' => '9999', 'limit' => null, 'extra' => array() ),
		array( 'slug' => 'refs', 'arg' => 'related_staff', 'limit' => '5', 'extra' => array() ),
	),
	0
);
$column     = '2:' . bws_fold_emit_slot(
	array(
		'label' => 'Depts',
		'type'  => null,
		'chain' => array( array( 'slug' => 'terms', 'arg' => 'department', 'limit' => '3', 'extra' => array() ) ),
		'read'  => array( 'kind' => 'analog', 'slug' => 'title' ),
		'opts'  => array(),
		'extra' => array(),
	)
);
$table_wire = '{{table ' . $row_chain . '|' . $column . '}}';
check( 'P10.3 both wrapper styles in one tag', '{{table src:post,9999;refs,related_staff,limit(5)|2:label(Depts);src(terms,department,limit[3]);use(title)}}' === $table_wire, "got: $table_wire" );
check( 'P10.4 no same-char immediate nesting in the emitted tag', '' === t_no_same_char_nesting( $table_wire ), t_no_same_char_nesting( $table_wire ) );
// And the violation IS detectable — the guard fails under a deliberate break.
check( 'P10.5 nesting guard fails on a deliberate break', '' !== t_no_same_char_nesting( 'src(refs,x,limit(2))' ), '' );

// ── P11 READ PRECEDENCE (hand-edit only) ────────────────────────────────────

$orderings = array(
	'use(title);key(staff_role)',
	'key(staff_role);use(title)',
);
$reads = array();
foreach ( $orderings as $i => $in ) {
	$slot = bws_fold_parse_slot( $in, 'try' );
	check( "P11.$i parse ok: $in", ! isset( $slot['error'] ), $slot['error'] ?? '' );
	$reads[] = $slot['read'] ?? null;
	check( "P11.$i use wins over key: $in", array( 'kind' => 'analog', 'slug' => 'title' ) === ( $slot['read'] ?? null ), var_export( $slot['read'] ?? null, true ) );
}
check( 'P11.2 both orderings agree (order is never semantic)', $reads[0] === $reads[1], var_export( $reads, true ) );
// `use(same)` beside a stale key: carry over wins, the key is inert.
$stale = bws_fold_parse_slot( 'src(refs,office);use(same);key(old)', 'try' );
check( 'P11.3 use(same) wins over a stale key', array( 'kind' => 'same' ) === ( $stale['read'] ?? null ), var_export( $stale, true ) );
check( 'P11.4 emit is exclusive (never both tokens)', 'src(refs,office);use(same)' === bws_fold_emit_slot( $stale ), 'got: ' . bws_fold_emit_slot( $stale ) );

// ── P12 LEGACY MAPPING ──────────────────────────────────────────────────────

/** Convenience: map a legacy option set and return the emitted folded value ('' = dropped). */
function t_legacy_wire( $n, $options, $combining = false, $per_slot_use = true ) {
	$rec = bws_fold_from_flat( $n, $options, $combining, $per_slot_use );
	if ( null === $rec ) {
		return null;
	}
	return bws_fold_emit_slot( $rec['slot'] );
}

// VERIFY BY MUTATION, NOT BY A GREEN COUNT: a corpus that never exercises a shape
// can pass 100% while the mapping under it is wrong. P12.2/P12.2b exist because every
// pre-existing equivalence case carried a SECOND axis beside `src:current`, so all of
// them passed while `current` mapped to no step at all — break the property
// deliberately (map `current` back to nothing) and confirm this section fails before
// trusting a future change here.
//
// Slot 1 — bare keys. An ABSENT src is an empty chain (the stripped default); an
// EXPLICIT `current` is a step, because a slot whose only content is that token has to
// keep existing (see P12.2b).
check( 'P12.0 no legacy keys → null', null === bws_fold_from_flat( 1, array(), false ), '' );
check( 'P12.1 slot 1 key only', 'key(role)' === t_legacy_wire( 1, array( 'key' => 'role' ) ), var_export( t_legacy_wire( 1, array( 'key' => 'role' ) ), true ) );
check( 'P12.2 slot 1 explicit src:current is a step, not nothing', 'src(current);key(role)' === t_legacy_wire( 1, array( 'src' => 'current', 'key' => 'role' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'current', 'key' => 'role' ) ), true ) );
// THE CASE THAT FORCED IT (found 2026-08-04 by the mount-migration smoke, then verified
// three ways with `bws render-tag`): on a container with NO per-slot read axis
// (try_permalink / try_title / try_datetime_*) a fallback attempt's ENTIRE content can be
// `{N}-src:current`. Mapping `current` to nothing left an empty struct, which emits '',
// which means the slot key is never written — the attempt DISAPPEARED, while the legacy
// wire rendered it and `{N}:src(current)` renders it too.
check( 'P12.2b a chain-only slot whose only content is src:current still exists', 'src(current)' === t_legacy_wire( 2, array( '2-src' => 'current' ), false, false ), var_export( t_legacy_wire( 2, array( '2-src' => 'current' ), false, false ), true ) );
// A FANNING step carries the limit the flat era implied (`limit[1]`): folded wire
// defaults to UNLIMITED, so the old default has to be stated or the migrated slot
// fans out where the stored tag rendered one value (#60). Non-fanning shapes above
// get nothing — there is no list to bound.
check( 'P12.3 src:ref + ref → refs step, carrying the flat era default', 'src(refs,office,limit[1]);key(name)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name' ) ), true ) );
check( 'P12.4 srcTermIn → terms step, carrying the flat era default', 'src(terms,category,limit[1]);use(title)' === t_legacy_wire( 1, array( 'srcTermIn' => 'category', 'use' => 'title' ) ), var_export( t_legacy_wire( 1, array( 'srcTermIn' => 'category', 'use' => 'title' ) ), true ) );
// #44: ref and srcTermIn COMPOUND, ref first (the term hop needs a post input). BOTH
// fanning steps take the 1 — per-step limits are per-input and MULTIPLY, so a bare 1
// on the last one alone would still fan the first.
check( 'P12.5 ref + srcTermIn compound in order', 'src(refs,office,limit[1];terms,category,limit[1]);use(title)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'category', 'use' => 'title' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'category', 'use' => 'title' ) ), true ) );
check( 'P12.6 src:site is a chain step', 'src(site);key(phone)' === t_legacy_wire( 1, array( 'src' => 'site', 'key' => 'phone' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'site', 'key' => 'phone' ) ), true ) );

// Slot ≥2, src axis — absence and the `same` sentinel both mean carry over, in BOTH
// containers (the src axis is NOT container-sensitive).
check( 'P12.7 selecting slot 2, src absent → src(same)', 'src(same);use(title)' === t_legacy_wire( 2, array( '2-use' => 'title' ) ), var_export( t_legacy_wire( 2, array( '2-use' => 'title' ) ), true ) );
check( 'P12.8 combining slot 2, src absent → src(same)', 'src(same);key(alt)' === t_legacy_wire( 2, array( '2-key' => 'alt' ), true ), var_export( t_legacy_wire( 2, array( '2-key' => 'alt' ), true ), true ) );
check( 'P12.9 explicit same sentinel → src(same)', 'src(same);key(alt)' === t_legacy_wire( 2, array( '2-src' => 'same', '2-key' => 'alt' ), true ), '' );

// Slot ≥2, READ axis — THE container divergence.
$sel_src_only = t_legacy_wire( 2, array( '2-src' => 'ref', '2-ref' => 'office' ), false, true );
check( 'P12.10 selecting: source set, read absent → use(same)', 'src(refs,office,limit[1]);use(same)' === $sel_src_only, var_export( $sel_src_only, true ) );
$com_src_only = t_legacy_wire( 2, array( '2-src' => 'ref', '2-ref' => 'office' ), true );
check( 'P12.11 combining: source set, read absent → read UNSET (skip preserved)', 'src(refs,office,limit[1])' === $com_src_only, var_export( $com_src_only, true ) );
// The FW-51 shape: a selecting slot whose only content was a key. The shipped
// resolver discards the key, finds nothing new, and SKIPS — so it maps to nothing.
check( 'P12.12 selecting psu: key-only slot 2 is dropped (FW-51 preserved)', null === bws_fold_from_flat( 2, array( '2-key' => 'b' ), false, true ), var_export( t_legacy_wire( 2, array( '2-key' => 'b' ), false, true ), true ) );
// psk-only (no per-slot read axis): the key is a real override, kept.
check( 'P12.13 selecting psk-only: key-only slot 2 keeps the key', 'src(same);key(b)' === t_legacy_wire( 2, array( '2-key' => 'b' ), false, false ), var_export( t_legacy_wire( 2, array( '2-key' => 'b' ), false, false ), true ) );
// Combining: `use` absent with a key set is the CANONICAL join slot-2 wire.
check( 'P12.14 combining: key-only slot 2 is a plain keyed read', 'src(same);key(b)' === t_legacy_wire( 2, array( '2-key' => 'b' ), true ), var_export( t_legacy_wire( 2, array( '2-key' => 'b' ), true ), true ) );
// A selecting slot with a stale key beside an explicit use → use wins, key discarded.
check( 'P12.15 selecting: explicit use:same discards the stale key', 'src(same);use(same)' === t_legacy_wire( 2, array( '2-use' => 'same', '2-key' => 'stale' ), false, true ), var_export( t_legacy_wire( 2, array( '2-use' => 'same', '2-key' => 'stale' ), false, true ), true ) );

// limit — the same owner a base tag's migration uses (bws_fold_chain_apply_legacy_limit):
// an explicit N on the LAST fanning step, 1 on every earlier one, and unlimited honored
// by writing nothing at all, because that is what folded wire already defaults to.
check( 'P12.16 limit → the fanning step', 'src(refs,office,limit[2]);key(name)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '2' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '2' ) ), true ) );
check( 'P12.17 limit → the LAST fanning step of a compound chain, 1 on the earlier one', 'src(refs,office,limit[1];terms,category,limit[3]);use(title)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3' ) ), true ) );
// An explicit `0`/`-1` KEEPS its carrier even though folded wire defaults to unlimited,
// and the reason is the dual-read rather than migration: this same mapping renders
// UNMIGRATED flat wire, which takes the flat era's default of 1, so dropping the token
// would re-bound a tag its author deliberately unbounded.
check( 'P12.18 legacy limit:0 → unlimited, NOT 1', 'src(refs,office,limit[0]);key(name)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '0' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '0' ) ), true ) );
check( 'P12.19 legacy limit:-1 → unlimited', 'src(refs,office,limit[0]);key(name)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '-1' ) ), '' );
check( 'P12.20 non-numeric legacy limit reads as ABSENT, so the era default is stated', 'src(refs,office,limit[1]);key(name)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => 'abc' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => 'abc' ) ), true ) );
// A slot-level `limit` is a COMBINING container's shape: {{join}} owns `limit` per slot, so
// slot 1's bare key is genuinely its own and with nothing fanning it bounds a multi-value
// READ — the one meaning a slot-level limit still has.
check( 'P12.21 limit with no fanning step stays slot-level (combining)', 'limit(4);key(prices)' === t_legacy_wire( 1, array( 'key' => 'prices', 'limit' => '4' ), true ), var_export( t_legacy_wire( 1, array( 'key' => 'prices', 'limit' => '4' ), true ), true ) );
// The SELECTING contrast, and the reason slot 1 must not read the bare key as its own:
// there `limit` is TAG-level and is every attempt's default, so slot 1 takes it exactly as
// slots ≥2 do — through the fanning-gated fallback. With nothing fanning that writes
// nothing, per #60 ("a slot with no fanning step gets no limit"), and the tag-level key
// still reaches the container arm for any slot that pins none.
check( 'P12.21b selecting: slot 1 does NOT swallow the tag-level limit as its own', 'key(prices)' === t_legacy_wire( 1, array( 'key' => 'prices', 'limit' => '4' ) ), var_export( t_legacy_wire( 1, array( 'key' => 'prices', 'limit' => '4' ) ), true ) );
check( 'P12.21c selecting: a FANNING slot 1 does take it, on its own step', 'src(refs,office,limit[4]);key(name)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '4' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '4' ) ), true ) );
// Nothing FANS, so there is no list to bound and no default to state — an unset limit
// on a singular source writes nothing in either era.
check( 'P12.21b no fanning step and no limit writes nothing', 'key(prices)' === t_legacy_wire( 1, array( 'key' => 'prices' ) ), var_export( t_legacy_wire( 1, array( 'key' => 'prices' ) ), true ) );
// A magnitude the two languages cannot hold identically (PHP saturates at PHP_INT_MAX,
// JS reaches Infinity) is left UNWRITTEN rather than materialized — it already reads as
// unlimited in both eras, so writing it buys nothing and risks a twin divergence.
check( 'P12.21c an unholdable magnitude is left unwritten, not saturated', 'src(refs,office);key(name)' === t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '9007199254740993' ) ), var_export( t_legacy_wire( 1, array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '9007199254740993' ) ), true ) );

// Every mapped legacy slot must re-parse (the mapping cannot emit wire its own
// parser rejects — the property that lets one mapping serve three consumers).
$legacy_sets = array(
	array( 1, array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3' ), false ),
	array( 2, array( '2-src' => 'ref', '2-ref' => 'office' ), true ),
	array( 3, array( '3-use' => 'same' ), false ),
	array( 2, array( '2-src' => 'site', '2-key' => 'phone' ), true ),
);
foreach ( $legacy_sets as $i => $case ) {
	list( $n, $options, $combining ) = $case;
	$wire = t_legacy_wire( $n, $options, $combining );
	if ( null === $wire ) {
		check( "P12.22.$i mapped wire re-parses", false, 'unexpected null' );
		continue;
	}
	$back = bws_fold_parse_slot( $wire, $combining ? 'join' : 'try' );
	check( "P12.22.$i mapped wire re-parses: $wire", ! isset( $back['error'] ), $back['error'] ?? '' );
	check( "P12.22.$i mapped wire is canonical: $wire", ! isset( $back['error'] ) && bws_fold_emit_slot( $back ) === $wire, 'got: ' . ( isset( $back['error'] ) ? $back['error'] : bws_fold_emit_slot( $back ) ) );
}

// ── P13 RENDER SEAM (dual-read + flatten + carry accumulator) ───────────────
//
// What a container's slot loop actually calls. The property under test is that the
// FOLDED path resolves to the same flat option set the shipped LEGACY loop built, so
// a migrated tag renders byte-identically — and that a MIXED-era wire threads one
// accumulator, which no spike fixture ever exercised.

/**
 * The SOURCE an option set describes, as canonical depth-0 chain wire.
 *
 * The comparison surface for §P13/§P14 since #104: the seam emits chain wire and the
 * reference walks below build the flat triple the shipped resolvers built, so both sides
 * are read through the ONE reading every consumer takes
 * (bws_fold_chain_from_options → bws_fold_emit_chain at depth 0). Comparing a wire string
 * against a triple would compare two spellings; comparing two wire strings compares the
 * source each era resolves.
 *
 * Step LIMITS are stripped before the compare, and deliberately: the flat era stated ONE
 * bound for a whole slot while a chain states one per step (bws_fold_from_flat materializes
 * `1` on every earlier fanning step so a migrated slot keeps the flat product). §P15 owns
 * the resolved quantity and §P18 owns what rides the wire, so folding both questions into
 * this one would make an equivalence break unreadable.
 */
function t_src_wire( array $options ): string {
	$chain = bws_fold_chain_from_options( $options );
	// An explicit `current` ROOT and an absent one are the SAME source — the factory reads
	// both as the ambient entity. The fold materializes the token (on a read-less container
	// `{N}-src:current` can be a slot's entire content, and mapping it to nothing deleted
	// the slot), while the shipped walks left it unset, so normalize the spelling away
	// before comparing what each era resolves.
	if ( 'current' === bws_fold_chain_root( $chain ) ) {
		array_shift( $chain );
	}
	foreach ( $chain as $i => $step ) {
		$chain[ $i ]['limit'] = null;
	}
	return bws_fold_emit_chain( array_values( $chain ), 0 );
}

/**
 * Walk slots 1..$max through the seam, returning [ n => resolved options ] for the
 * resolved ones, with the source projected onto canonical chain wire.
 *
 * The `limit` is MATERIALIZED from the seam's era out-param, exactly as the shipped
 * container arms do it. That is the whole point of the out-param: `src` is CHAIN WIRE on
 * every slot now, so a container re-deriving the default from it would answer *unlimited*
 * for a slot recovered from legacy flat keys, and a comparison that left `limit` absent
 * would be comparing two option sets that resolve DIFFERENT quantities and calling them
 * equal (#60, sign-flipped by #104).
 */
function t_seam_walk( $options, $container = 'join', $max = 5, $per_slot_use = true ) {
	$carry = array();
	$out   = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$slot = bws_fold_slot_struct( $n, $options, $container, $per_slot_use );
		if ( null === $slot ) {
			continue;
		}
		$skip          = '';
		$limit_default = 1;
		$flat          = bws_fold_slot_chain_options( $slot, $carry, 'try' !== $container, $skip, $limit_default );
		if ( null === $flat ) {
			continue;
		}
		// THE LEAK ASSERTION'S SUBJECT, kept in the walk's own output rather than tested
		// once: the seam supersedes the legacy source axes by CONTRACT, so every resolved
		// slot must carry explicit empties for them. §P18.3 names the leak.
		$out[ $n ] = array(
			'src'       => t_src_wire( array( 'src' => $flat['src'] ) ),
			'ref'       => $flat['ref'],
			'srcTermIn' => $flat['srcTermIn'],
			'use'       => $flat['use'],
			'key'       => $flat['key'],
			'limit'     => (string) bws_clamp_limit( $flat['limit'] ?? null, $limit_default ),
		);
	}
	return $out;
}

/**
 * The flat option set shipped {{join}} built for one slot, transcribed from base-tags.php.
 *
 * One DELIBERATE departure from what shipped through 1.16.x: `srcTermIn` carries to a slot
 * that carries over its source (#74). The shipped loop read it fresh per slot, so an carrying over
 * slot behind a term hop resolved against the ambient entity — the defect this model would
 * otherwise pin in place. Everything else is the shipped walk verbatim, so the equivalence
 * checks below still compare two independent implementations rather than one with itself.
 */
function t_shipped_join_walk( $options, $max = 5 ) {
	$last_src = '';
	$last_ref = '';
	$last_stm = '';
	$out      = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$p   = ( 1 === $n ) ? '' : "{$n}-";
		$src = $options[ $p . 'src' ] ?? '';
		$ref = $options[ $p . 'ref' ] ?? '';
		$use = $options[ $p . 'use' ] ?? '';
		$key = $options[ $p . 'key' ] ?? '';
		$stm = $options[ $p . 'srcTermIn' ] ?? '';
		$lim = $options[ $p . 'limit' ] ?? '';
		if ( '' === $key && '' === $use ) {
			continue;
		}
		// The hop travels WITH the source (#74): a slot carries over it only when it carries over
		// the source, and a slot stating its own hop replaces the carried one.
		$carries_src = ( '' === $src || 'same' === $src );
		if ( '' !== $src && 'same' !== $src ) {
			$last_src = $src;
		}
		if ( '' !== $ref ) {
			$last_ref = $ref;
		}
		if ( '' === $stm && $carries_src ) {
			$stm = $last_stm;
		}
		$last_stm = $stm;
		$flat = array(
			// Projected onto the SAME reading the seam's output is projected through, so
			// the two sides are one comparison rather than two spellings (#104).
			'src'       => t_src_wire( array( 'src' => $last_src, 'ref' => $last_ref, 'srcTermIn' => $stm ) ),
			'ref'       => '',
			'srcTermIn' => '',
			'use'       => '' === $use ? 'key' : $use,
			'key'       => $key,
			// The shipped loop resolved every slot against FLAT wire, so its default is
			// the flat era's 1, unconditionally. Materialized rather than left absent so
			// the equivalence below compares the QUANTITY each era resolves.
			'limit'     => (string) bws_clamp_limit( '' !== $lim ? $lim : null, 1 ),
		);
		$out[ $n ] = $flat;
	}
	return $out;
}

/** Migrate a whole legacy join option set to folded wire (what the 5e migrator will write). */
function t_migrate_join( $options, $max = 5 ) {
	$cfg = array(
		'container'    => 'join',
		'combining'    => true,
		'per_slot_use' => true,
		'max'          => $max,
		// Join owns `limit` per slot (it registered `limit`/`N-limit` and threaded each
		// into that slot's text resolve), so nothing is excluded here — the opposite of
		// try_ below. bws_fold_migration_container('join') says the same.
		'tag_level'    => array(),
	);
	$out = bws_fold_migrate_slots( $options, $cfg );
	return null === $out ? $options : $out;
}

// P13.1 — MIGRATION EQUIVALENCE: legacy wire and its migrated folded twin must
// produce identical flat option sets, slot for slot. `src` normalizes ('' and
// `current` are the same source), so compare after collapsing that one spelling.
//
// ONE SHAPE IS EXEMPT, deliberately: a stale hidden `N-ref` under `src(same)` (§P17). The
// flat walk read it and the fold drops it, because a value whose control is off screen is
// residue rather than configuration. Do not "restore" equivalence there without reading
// §P17 first — the fold's reading is the correct one.
function t_norm_walk( $walk ) {
	foreach ( $walk as $n => $flat ) {
		// An explicit `current` ROOT and an empty chain are the same source; the fold
		// materializes the token (it is a slot's entire content on a read-less container)
		// where the shipped walk left it unset.
		if ( 'current' === $flat['src'] ) {
			$walk[ $n ]['src'] = '';
		}
	}
	return $walk;
}
$legacy_join_cases = array(
	'plain two-slot'          => array( 'key' => 'first_name', '2-key' => 'last_name' ),
	'title + key'            => array( 'use' => 'title', '2-key' => 'role' ),
	'ref hop then carry over'   => array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', '2-key' => 'phone' ),
	'term hop with limit'    => array( 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3', '2-key' => 'blurb' ),
	'site slot'              => array( 'key' => 'a', '2-src' => 'site', '2-key' => 'org_phone' ),
	'explicit same sentinel' => array( 'src' => 'ref', 'ref' => 'office', 'key' => 'a', '2-src' => 'same', '2-key' => 'b' ),
	'stale ref carried back' => array( 'src' => 'ref', 'ref' => 'office', 'key' => 'a', '2-src' => 'current', '2-key' => 'b', '3-src' => 'ref', '3-key' => 'c' ),
	'half-configured slot 2' => array( 'key' => 'a', '2-src' => 'site', '3-key' => 'c' ),
	'unlimited limit'        => array( 'src' => 'ref', 'ref' => 'office', 'key' => 'a', 'limit' => '0' ),
	'compound ref + terms'   => array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '2', '2-key' => 'b' ),
);
foreach ( $legacy_join_cases as $name => $legacy ) {
	$shipped = t_norm_walk( t_shipped_join_walk( $legacy ) );
	$folded  = t_norm_walk( t_seam_walk( t_migrate_join( $legacy ), 'join' ) );
	check(
		"P13.1 [$name] migrated folded wire resolves identically to legacy",
		$shipped === $folded,
		"legacy:  " . json_encode( $shipped ) . "\n      folded: " . json_encode( $folded )
	);
	// And the seam reading the LEGACY keys directly (dual-read) must agree too — that
	// is the pre-migration front end, which must not change on upgrade.
	$dual = t_norm_walk( t_seam_walk( $legacy, 'join' ) );
	check(
		"P13.1 [$name] dual-read of unmigrated wire resolves identically",
		$shipped === $dual,
		"legacy: " . json_encode( $shipped ) . "\n      dual:   " . json_encode( $dual )
	);
}

// P13.2 — THE MIXED-ERA CASE the spike could not reach: folded slot 2 between legacy
// slots 1 and 3. Slot 3 carries over, so it must see slot 2's FOLDED source through the
// one shared accumulator.
$mixed = array(
	'src'   => 'ref',
	'ref'   => 'office',
	'key'   => 'a',
	'B'     => 'src(site);key(org_phone)',
	'3-key' => 'c',
);
$walk = t_seam_walk( $mixed, 'join' );
check( 'P13.2 mixed era: legacy slot 1 resolves', array( 'src' => 'refs,office', 'ref' => '', 'srcTermIn' => '', 'use' => 'key', 'key' => 'a', 'limit' => '1' ) === ( $walk[1] ?? null ), json_encode( $walk[1] ?? null ) );
check( 'P13.2 mixed era: folded slot 2 resolves', array( 'src' => 'site', 'ref' => '', 'srcTermIn' => '', 'use' => 'key', 'key' => 'org_phone', 'limit' => '1' ) === ( $walk[2] ?? null ), json_encode( $walk[2] ?? null ) );
check( 'P13.2 mixed era: legacy slot 3 carries over the FOLDED slot 2 source', 'site' === ( $walk[3]['src'] ?? '' ), json_encode( $walk[3] ?? null ) );
// The reverse threading: a FOLDED slot carrying over from a LEGACY predecessor.
$mixed_rev = array(
	'2-src' => 'site',
	'2-key' => 'org_phone',
	'C'     => 'src(same);key(c)',
);
$walk_rev = t_seam_walk( $mixed_rev, 'join' );
check( 'P13.2 mixed era: folded slot 3 carries over a LEGACY slot 2 source', 'site' === ( $walk_rev[3]['src'] ?? '' ), json_encode( $walk_rev[3] ?? null ) );

// P13.3 — SKIP BEFORE CARRY. A combining slot with no read is unconfigured: it
// renders nothing AND must not feed the accumulator, or a later slot's carry over
// re-points at a source the author never chose.
$skip = array( 'A' => 'key(a)', 'B' => 'src(site)', 'C' => 'src(same);key(c)' );
$walk = t_seam_walk( $skip, 'join' );
check( 'P13.3 combining: read-less slot 2 is skipped', ! isset( $walk[2] ), json_encode( $walk[2] ?? null ) );
check( 'P13.3 combining: slot 3 carries over slot 1, NOT the skipped slot 2', '' === ( $walk[3]['src'] ?? 'X' ), json_encode( $walk[3] ?? null ) );
// Selecting reads absence as carry over, and DOES resolve the slot.
$walk_sel = t_seam_walk( array( 'A' => 'key(a)', 'B' => 'src(site)' ), 'try' );
check( 'P13.3 selecting: read-less slot 2 carries over the read and resolves', isset( $walk_sel[2] ) && 'a' === $walk_sel[2]['key'] && 'site' === $walk_sel[2]['src'], json_encode( $walk_sel[2] ?? null ) );

// P13.4 — explicit `use(same)` carries over in BOTH containers (only ABSENCE diverges).
$same_join = t_seam_walk( array( 'A' => 'key(a)', 'B' => 'src(site);use(same)' ), 'join' );
check( 'P13.4 combining honors an explicit use(same)', isset( $same_join[2] ) && 'a' === $same_join[2]['key'] && 'key' === $same_join[2]['use'], json_encode( $same_join[2] ?? null ) );
$same_analog = t_seam_walk( array( 'A' => 'use(title)', 'B' => 'src(site);use(same)' ), 'join' );
check( 'P13.4 a carried ANALOG read carries too', 'title' === ( $same_analog[2]['use'] ?? '' ), json_encode( $same_analog[2] ?? null ) );

// P13.4b — AN EMPTY ANALOG SLUG NAMES NO READ, so it resolves as the carry's.
//
// `use()` is legal hand-written wire (ADR 0004) and parses to {kind:analog, slug:''}. The
// seam must not pass that '' on: a dispatcher's `?? '<default>'` does not fire on the empty
// string, only on an absent key, so a bare '' reaches the branch as a third state and drops
// the read — the B6 trap ([I3]) one layer down. Through 1.18.x a hardcoded 'key' covered
// this, which was right for the containers that reached it and wrong for content's own
// default; the carry answers it per container instead.
// Driven at the seam with an EXPLICIT seed, because that is the variable under test —
// t_seam_walk seeds nothing, which models a container with no read axis and would answer
// a different question.
function t_empty_analog( string $container, string $seed ) {
	$slot = bws_fold_slot_struct( 1, array( 'A' => 'use()' ), $container, 'try' === $container );
	if ( null === $slot ) {
		return 'NO SLOT';
	}
	$carry = bws_fold_empty_carry( $seed );
	$skip  = '';
	$flat  = bws_fold_slot_chain_options( $slot, $carry, 'try' !== $container, $skip );
	return null === $flat ? "SKIP({$skip})" : $flat['use'];
}
// The parse really does produce the shape under test — without this the three checks
// below could pass against a slot that never had an empty analog slug in it.
$empty_slug_slot = bws_fold_slot_struct( 1, array( 'A' => 'use()' ), 'join', false );
check(
	'P13.4b use() parses to an EMPTY ANALOG SLUG (the shape under test exists)',
	array( 'kind' => 'analog', 'slug' => '' ) === ( $empty_slug_slot['read'] ?? null ),
	json_encode( $empty_slug_slot['read'] ?? null )
);
check(
	'P13.4b combining: use() takes the carry seed, not a bare empty read',
	$text_default === t_empty_analog( 'join', $text_default ),
	var_export( t_empty_analog( 'join', $text_default ), true )
);
check(
	'P13.4b selecting: use() takes the TEMPLATE default, not a container-blind literal',
	$content_default === t_empty_analog( 'try', $content_default ),
	var_export( t_empty_analog( 'try', $content_default ), true )
);
// The bound of the rule, stated rather than left to be discovered: a container that seeds
// NO default still resolves '' here. That is safe only because such a container has no
// `use` dispatch to drop a read from — the same reason the hardcoded 'key' this replaced
// was correct for the containers that reached it. A container that grows a read axis must
// seed the carry, and §8b of control-order-test.php is what catches one that does not.
check(
	'P13.4b an unseeded container still resolves empty, and that is the rule\'s bound',
	'' === t_empty_analog( 'join', '' ),
	var_export( t_empty_analog( 'join', '' ), true )
);

// P13.5 — THE FOUR INEXPRESSIBLE-CHAIN SKIPS, INVERTED (#104).
//
// These four asserted a SKIP through 1.16.x: the flat triple held one relationship step
// and one term step, so anything else had no flat spelling and rendering the expressible
// PREFIX would silently read a different source. The flatten is gone, so there is nothing
// left to fail at and the refusal dissolved with it. This inversion is the harness-side
// acceptance signal for FW-71 — each slot now resolves, and its `src` is the chain the
// author wrote, re-leveled to depth 0.
//
// A CHAIN THAT RESOLVES IS NOT A CHAIN THAT RENDERS, and the difference is deliberate:
// `rows` still returns nothing, because no `try_`/join arm assembles a repeater row.
// That refusal MOVED to the container that consumes the kind (try-slot-arms.php) rather
// than living in a re-spelling that could not describe the wire.
foreach ( array(
	'two ref hops'   => array( 'src(refs,a;refs,b);key(x)', 'refs,a;refs,b' ),
	'two term hops'  => array( 'src(terms,category;terms,post_tag);key(x)', 'terms,category;terms,post_tag' ),
	'repeater entry' => array( 'src(rows,rows);key(x)', 'rows,rows' ),
	'ref after term' => array( 'src(terms,category;refs,a);key(x)', 'terms,category;refs,a' ),
) as $why => $case ) {
	list( $wire, $want ) = $case;
	$w = t_seam_walk( array( 'A' => $wire ), 'join' );
	check( "P13.5 a multi-step chain RESOLVES ($why)", isset( $w[1] ), json_encode( $w[1] ?? null ) );
	check( "P13.5 …and hands on the whole chain, not a prefix ($why)", $want === ( $w[1]['src'] ?? null ), json_encode( $w[1] ?? null ) );
}
// P13.5b — the seam REPORTS WHY it skipped, and the two reasons are not
// interchangeable: the editor preview flags an inexpressible chain and stays silent
// on an unconfigured slot. Asserted on the seam, not through the preview, because the
// preview must never re-derive it (a second copy of the skip rule is the drift the
// seam removed).
//
// The reset is part of the CONTRACT, not decoration: the out-param is by reference, so
// a caller is entitled to reuse one variable across a whole slot walk. A reason that is
// only ever WRITTEN on a skip leaks the previous slot's answer into a later resolving
// slot — invisible while every caller happens to re-init, and a silent misflag the
// moment one does not.
$sr_carry = array();
$sr       = 'STALE';
bws_fold_slot_chain_options( bws_fold_parse_slot( 'src(rows,rows);key(x)' ), $sr_carry, true, $sr );
check( 'P13.5b the `chain` reason is RETIRED — a multi-step chain reports no skip', '' === $sr, var_export( $sr, true ) );
$sr_carry = array();
bws_fold_slot_chain_options( bws_fold_parse_slot( 'src(site)' ), $sr_carry, true, $sr );
check( 'P13.5b an unconfigured combining slot reports reason `read`', 'read' === $sr, var_export( $sr, true ) );
$sr_carry = array();
bws_fold_slot_chain_options( bws_fold_parse_slot( 'src(site);key(a)' ), $sr_carry, true, $sr );
check( 'P13.5b a RESOLVING slot clears the reason (reused variable, no leak)', '' === $sr, var_export( $sr, true ) );

// P13.5c — an INCOMPLETE step (a `terms` hop with no taxonomy) skips, with its own
// reason. This is the fold's own hazard: the flat wire could not state a hop without its
// argument, so the shape did not exist before. Flattening it is worse than skipping —
// an empty `srcTermIn` is exactly how NO term hop is spelled, so the slot would read the
// un-hopped entity and return a plausible WRONG value instead of nothing. `refs` is the
// deliberate contrast: argless there means "take the carried relationship field".
foreach ( array(
	'leading, argless'   => 'src(terms);key(role)',
	'after a real src'   => 'src(post,9;terms);key(role)',
) as $why => $wire ) {
	$w = t_seam_walk( array( 'A' => $wire ), 'join' );
	check( "P13.5c incomplete term hop skips the slot ($why)", ! isset( $w[1] ), json_encode( $w[1] ?? null ) );
}
$sr_carry = array();
bws_fold_slot_chain_options( bws_fold_parse_slot( 'src(terms);key(role)' ), $sr_carry, true, $sr );
// The reason NAMES the unfinished step. Two steps can be incomplete and they need
// different author-facing nouns, so the slug rides the reason rather than the preview
// guessing — which is what "no taxonomy" on an argless `refs` hop looked like.
check( 'P13.5c an incomplete step reports reason `step:terms`, not `chain`', 'step:terms' === $sr, var_export( $sr, true ) );
$argless_ref = t_seam_walk( array( 'A' => 'src(refs,office);key(a)', 'B' => 'src(refs);key(b)' ), 'join' );
check(
	'P13.5c an argless `refs` hop still CARRIES OVER rather than skipping',
	'refs,office' === ( $argless_ref[2]['src'] ?? null ),
	json_encode( $argless_ref[2] ?? null )
);
// …but an argless `refs` with NOTHING CARRIED is incomplete, not carried (#74). The
// distinction is the whole of it: the step is complete when the carry supplies its field,
// and only unfinished when nothing ever did. Skipping is what the seam docblock always
// CLAIMED happened here ("the step is dead"); what actually happened is that the flat
// triple `{src:'ref', ref:''}` compiled to a rootless chain and read the AMBIENT entity.
$argless_orphan = t_seam_walk( array( 'A' => 'src(refs);key(a)' ), 'join' );
check(
	'P13.5c an argless `refs` with nothing carried SKIPS (never falls back to ambient)',
	! isset( $argless_orphan[1] ),
	json_encode( $argless_orphan[1] ?? null )
);
$orphan_carry = array();
bws_fold_slot_chain_options( bws_fold_parse_slot( 'src(refs);key(a)' ), $orphan_carry, true, $osr );
check( 'P13.5c …and reports `step:refs`, so the preview names the RIGHT missing thing', 'step:refs' === $osr, var_export( $osr, true ) );

// A LEADING term hop is expressible (the ambient entity's terms) and must resolve.
$lead_terms = t_seam_walk( array( 'A' => 'src(terms,category);use(title)' ), 'join' );
// The limit is the walk's materialized EFFECTIVE value (see t_seam_walk): a chain-spelled
// slot whose own chain FANS takes the unlimited default, which is the whole of #60 — the
// identically-spelled base tag `{{text src:terms,category|use:title}}` returns every term.
check( 'P13.5 leading term hop resolves as a rootless chain, and unbounded', array( 'src' => 'terms,category', 'ref' => '', 'srcTermIn' => '', 'use' => 'title', 'key' => '', 'limit' => '0' ) === ( $lead_terms[1] ?? null ), json_encode( $lead_terms[1] ?? null ) );

// P13.6 — limit threading. A per-step limit reaches the flat `limit`; a pinned 0
// (unlimited) must survive, which a truthiness guard would drop.
$lim = t_seam_walk( array( 'A' => 'src(refs,office,limit[3]);key(a)' ), 'join' );
check( 'P13.6 per-step limit reaches the flat option', '3' === ( $lim[1]['limit'] ?? null ), json_encode( $lim[1] ?? null ) );
$lim0 = t_seam_walk( array( 'A' => 'src(refs,office,limit[0]);key(a)' ), 'join' );
check( 'P13.6 a pinned limit 0 (unlimited) survives the seam', '0' === ( $lim0[1]['limit'] ?? null ), json_encode( $lim0[1] ?? null ) );
$lim_slot = t_seam_walk( array( 'A' => 'limit(4);key(prices)' ), 'join' );
check( 'P13.6 a slot-level limit reaches the flat option', '4' === ( $lim_slot[1]['limit'] ?? null ), json_encode( $lim_slot[1] ?? null ) );
// The slot-level twin of the pinned zero: a legacy `limit:0` with no fanning step
// migrates to `limit(0)`, which bounds an unlimited multi-value READ. PHP's '0' is
// FALSY (unlike JS), so this is the one spelling a truthiness guard eats here.
$lim_slot0 = t_seam_walk( array( 'A' => 'limit(0);key(prices)' ), 'join' );
check( 'P13.6 a slot-level limit 0 (unlimited) survives the seam', '0' === ( $lim_slot0[1]['limit'] ?? null ), json_encode( $lim_slot0[1] ?? null ) );
// The RAW seam still writes no `limit` key when the wire states none — t_seam_walk
// materializes the effective value the way a container arm does, so this one asks the
// seam directly rather than through the walk.
$lim_none_carry = array();
$lim_none_raw   = bws_fold_slot_chain_options( bws_fold_slot_struct( 1, array( 'A' => 'key(a)' ), 'join' ), $lim_none_carry, true );
check( 'P13.6 no limit token → no limit key (the caller default stands)', ! isset( $lim_none_raw['limit'] ), json_encode( $lim_none_raw ) );

// P13.6c — A LIMIT BINDS THE STEP IT IS WRITTEN ON (ADR 0005 one level down; slice A).
// The selection this replaced kept the last step that PINNED one, so a number written
// on an earlier step went on governing the slot's rendered items after the author
// appended an unbounded later step — a number stated in one place acting on another.
// Only the FINAL step's limit can be the item bound now; an earlier step's number
// still rides the emitted wire and bounds ITS OWN step in the engine (P13.7 below
// pins the wire half staying put).
$own_carry = array();
$own_raw   = bws_fold_slot_chain_options( bws_fold_slot_struct( 1, array( 'A' => 'src(refs,office,limit[3];terms,region);key(a)' ), 'join' ), $own_carry, true );
check( 'P13.6c an EARLIER step\'s limit no longer becomes the item bound', ! isset( $own_raw['limit'] ), json_encode( $own_raw ) );
check( 'P13.6c …and it still rides the emitted wire, on its own step', 'refs,office,limit(3);terms,region' === ( $own_raw['src'] ?? null ), json_encode( $own_raw['src'] ?? null ) );
$own_two = t_seam_walk( array( 'A' => 'src(refs,office,limit[2];terms,region,limit[3]);key(a)' ), 'join' );
check( 'P13.6c limits on two steps: the LAST step\'s is the item bound', '3' === ( $own_two[1]['limit'] ?? null ), json_encode( $own_two[1] ?? null ) );
// The migration-written shape (1 on earlier, N on last) is the one the old and new
// selections agree on — asserted so the agreement is measured, not assumed.
$own_mig = t_seam_walk( array( 'A' => 'src(refs,office,limit[1];terms,department,limit[3]);key(a)' ), 'join' );
check( 'P13.6c migrated wire is unmoved (N on the last fanning step)', '3' === ( $own_mig[1]['limit'] ?? null ), json_encode( $own_mig[1] ?? null ) );
// With the final step unbounded, the slot-level token is next in precedence, as ever.
$own_tok = t_seam_walk( array( 'A' => 'src(refs,office,limit[3];terms,region);limit(4);key(a)' ), 'join' );
check( 'P13.6c final step unbounded → the slot-level token governs', '4' === ( $own_tok[1]['limit'] ?? null ), json_encode( $own_tok[1] ?? null ) );

// P13.6b — THE ERA THE FLATTEN ERASES, handed back (#60). A slot's own source spelling
// decides its own default exactly as a base tag's does, and the flat triple below cannot
// answer that question: its `src` is a legacy token on every slot, which is why every slot
// used to answer 1 however it was spelled. Measured before the fix:
// `{{text src:terms,department|use:title}}` → two terms, `{{try_text
// A:src(terms,department);use(title)}}` → one.
//
// The predicate is the slot's OWN chain fanning, shared with the migrator's stamp
// (bws_fold_chain_fanning_steps). A slot that only fans by CARRYING OVER an earlier slot's
// source keeps the flat default — see the seam's docblock for why a limit must not carry
// forward — and the mutation that matters is the one that flips `src(same)` to 0, since
// that is what silently unbounds a migrated join slot.
$era_cases = array(
	// folded wire, container, expected default, why
	array( 'src(terms,department);use(title)', 'try', 0, 'chain-spelled and fans → unlimited' ),
	array( 'src(refs,related_staff);key(name)', 'join', 0, 'a refs-spelled slot fans too' ),
	array( 'src(rows,rows);key(a)', 'table', 0, 'a repeater step fans (its container refuses the kind, era still reported)' ),
	array( 'src(site);key(org_phone)', 'join', 1, 'a singular chain states no list to bound' ),
	array( 'key(a)', 'join', 1, 'no chain at all' ),
	array( 'src(same);key(b)', 'join', 1, 'carries over a source, so states no bound of its own' ),
	array( 'src(refs);key(b)', 'join', 1, 'an ARGLESS refs step fans only by carry-over' ),
);
foreach ( $era_cases as $i => $case ) {
	list( $wire, $container, $want, $why ) = $case;
	$era_carry   = array();
	$era_skip    = '';
	$era_default = 99;
	bws_fold_slot_chain_options( bws_fold_slot_struct( 1, array( 'A' => $wire ), $container ), $era_carry, bws_fold_is_combining( $container ), $era_skip, $era_default );
	check( "P13.6b [$wire] limit default $want — $why", $want === $era_default, "got: " . var_export( $era_default, true ) );
}
// The LEGACY era, read through the same seam: recovered flat wire keeps the 1 it always
// had, whatever its recovered chain looks like.
$era_carry   = array();
$era_skip    = '';
$era_default = 99;
bws_fold_slot_chain_options( bws_fold_slot_struct( 1, array( 'srcTermIn' => 'department', 'use' => 'title' ), 'try' ), $era_carry, false, $era_skip, $era_default );
check( 'P13.6b legacy flat wire still bounds at 1 through the same seam', 1 === $era_default, var_export( $era_default, true ) );
// The out-param is written BEFORE any early return, same reset contract as $skip_reason:
// a caller reusing one variable across a walk must not read the previous slot's answer.
$era_carry   = array();
$era_default = 0;
bws_fold_slot_chain_options( bws_fold_slot_struct( 1, array( 'A' => 'src(site)' ), 'join' ), $era_carry, true, $era_skip, $era_default );
check( 'P13.6b a SKIPPED slot still reports its default (no leak into the next slot)', 1 === $era_default, var_export( $era_default, true ) );

// P13.7 — malformed folded wire contributes nothing and NEVER falls back to a stale
// legacy sibling (that would render an intent the author replaced).
$bad = t_seam_walk( array( 'A' => 'key(a', '1-key' => 'ignored', 'key' => 'stale' ), 'join' );
check( 'P13.7 malformed folded wire skips the slot', ! isset( $bad[1] ), json_encode( $bad[1] ?? null ) );

// P13.8 — an empty chain at slot ≥2 is a RESET to the ambient entity, not a carry-over
// (legacy absence migrates to an explicit `src(same)`, so absence is unambiguous).
$reset = t_seam_walk( array( 'A' => 'src(site);key(a)', 'B' => 'key(b)' ), 'join' );
check( 'P13.8 empty chain at slot 2 resets to current, not carry over', '' === ( $reset[2]['src'] ?? 'X' ), json_encode( $reset[2] ?? null ) );

// ── P14 SELECTING CONTAINER (`try_*`) ───────────────────────────────────────
//
// The same migration-equivalence property as P13.1, for the container whose ABSENCE
// rules are the mirror image: an absent read CARRIES OVER instead of skipping the slot,
// and slot 1 with every axis unset is still an attempt. Three READ SHAPES exist and
// each resolves differently, so each is walked separately:
//   psu  — per-slot `use` enum + key   (text / content / image)
//   psk  — per-slot key, no `use` enum (email / phone)
//   none — no per-slot read at all     (title / permalink / datetime_*)

/**
 * The flat option set the shipped try_ resolver built for one slot, transcribed from
 * class-tag-template-registry.php before the flip. Models the loop's own variables —
 * NOT the `$eval_opts` carry-over around them, which additionally leaked slot 1's
 * bare `srcTermIn` into every later slot's core call (P14.5 covers that fix).
 */
function t_shipped_try_walk( $options, $psk, $psu, $nku = array(), $default_use = '', $max = 5 ) {
	$last_src = 'current';
	$last_ref = '';
	$last_key = '';
	$last_use = '';
	$last_stm = '';
	$out      = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$p   = ( 1 === $n ) ? '' : "{$n}-";
		$src = $options[ $p . 'src' ] ?? '';
		$ref = $options[ $p . 'ref' ] ?? '';
		$stm = $options[ $p . 'srcTermIn' ] ?? '';
		$key = $options[ $p . 'key' ] ?? '';
		$use = $options[ $p . 'use' ] ?? '';
		if ( $n > 1 ) {
			if ( 'same' === $src ) { $src = ''; }
			if ( 'same' === $use ) { $use = ''; }
		}
		if ( 1 === $n ) {
			$last_src = ( '' === $src ) ? 'current' : $src;
			$last_ref = $ref;
			$last_key = $key;
			if ( $psu ) {
				$last_use = ( '' === $use ) ? $default_use : $use;
			}
		} else {
			if ( $psu && '' === $use ) {
				$key = '';
			}
			$has_new = '' !== $src || '' !== $ref || '' !== $stm
				|| ( ( $psk || $psu ) && '' !== $key )
				|| ( $psu && '' !== $use );
			if ( ! $has_new ) {
				continue;
			}
			if ( '' !== $src ) { $last_src = $src; }
			if ( '' !== $ref ) { $last_ref = $ref; }
			if ( '' !== $key ) { $last_key = $key; }
			if ( $psu && '' !== $use ) { $last_use = $use; }
		}
		if ( $psk || $psu ) {
			$in_no_key = $psu && in_array( $last_use, $nku, true );
			if ( ! $in_no_key && '' === $last_key ) {
				continue;
			}
		}
		// The hop travels WITH the source (#74), and only a RESOLVED slot feeds the carry —
		// the `continue`s above model the shipped skips, which must not.
		if ( '' === $stm && '' === $src ) {
			$stm = $last_stm;
		}
		$last_stm  = $stm;
		$out[ $n ] = array(
			// Same projection as the seam side (see t_src_wire): one comparison, not two
			// spellings. `current` is the shipped walk's own normalization of an unset
			// source, and the chain reading maps it back to a root token either way.
			'src' => t_src_wire( array( 'src' => $last_src, 'ref' => $last_ref, 'srcTermIn' => $stm ) ),
			'use' => $psu ? $last_use : null,
			'key' => '' !== $last_key ? $last_key : null,
		);
	}
	return $out;
}

/** The same walk through the seam, projected onto the shipped shape. */
function t_seam_try_walk( $options, $psk, $psu, $nku = array(), $default_use = '', $max = 5 ) {
	$carry = array( 'chain' => array(), 'ref' => '', 'use' => $psu ? $default_use : '', 'key' => '' );
	$out   = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$slot = bws_fold_slot_struct( $n, $options, 'try', $psu );
		if ( null === $slot ) {
			continue;
		}
		$flat = bws_fold_slot_chain_options( $slot, $carry, false );
		if ( null === $flat ) {
			continue;
		}
		if ( $psk || $psu ) {
			$in_no_key = $psu && in_array( $flat['use'], $nku, true );
			if ( ! $in_no_key && '' === $flat['key'] ) {
				continue;
			}
		}
		$out[ $n ] = array(
			'src' => t_src_wire( array( 'src' => $flat['src'] ) ),
			'use' => $psu ? $flat['use'] : null,
			'key' => '' !== $flat['key'] ? $flat['key'] : null,
		);
	}
	return $out;
}

/**
 * Migrate a legacy try_ option set to folded wire — the SHIPPED migrator, with the
 * container config derived from the shipped read-shape mapping for the shape under test.
 * `$psk`/`$psu` are the two template flags, so the three read shapes are addressed the
 * same way the registry addresses them.
 */
function t_migrate_try( $options, $psk, $psu, $max = 5 ) {
	$axes = \BWS\DynamicTags\TagTemplateRegistry::try_slot_axes(
		array(
			'try_per_slot_key' => $psk,
			'try_per_slot_use' => $psu,
		)
	);
	$out = bws_fold_migrate_slots(
		$options,
		array(
			'container'    => 'try',
			'combining'    => false,
			'per_slot_use' => $psu,
			'max'          => $max,
			'tag_level'    => $axes['tag_level'],
		)
	);
	return null === $out ? $options : $out;
}

// P14.1 — psu shape (text: default read `key`, no-key value `title`).
$try_psu_cases = array(
	'two keyed slots'        => array( 'key' => 'a', '2-use' => 'key', '2-key' => 'b' ),
	'analog then keyed'      => array( 'use' => 'title', '2-use' => 'key', '2-key' => 'role' ),
	'ref hop then carry over'   => array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', '2-src' => 'current', '2-use' => 'key', '2-key' => 'phone' ),
	'term hop at slot 1'     => array( 'srcTermIn' => 'category', 'use' => 'title', '2-use' => 'key', '2-key' => 'b' ),
	'site slot'              => array( 'key' => 'a', '2-src' => 'site', '2-use' => 'key', '2-key' => 'org_phone' ),
	'slot 2 carries over read'   => array( 'key' => 'a', '2-src' => 'site' ),
	'key-only slot 2 drops'  => array( 'key' => 'a', '2-key' => 'b' ),
	'bare tag'               => array(),
	'unset key mode slot 2'  => array( 'key' => 'a', '2-src' => 'site', '2-use' => 'key' ),
);
foreach ( $try_psu_cases as $name => $legacy ) {
	$shipped = t_shipped_try_walk( $legacy, true, true, array( 'title' ), $text_default );
	$folded  = t_seam_try_walk( t_migrate_try( $legacy, true, true ), true, true, array( 'title' ), $text_default );
	check( "P14.1 [psu: $name] migrated folded wire resolves identically", $shipped === $folded, 'legacy:  ' . json_encode( $shipped ) . "\n      folded: " . json_encode( $folded ) );
	$dual = t_seam_try_walk( $legacy, true, true, array( 'title' ), $text_default );
	check( "P14.1 [psu: $name] dual-read of unmigrated wire resolves identically", $shipped === $dual, 'legacy: ' . json_encode( $shipped ) . "\n      dual:   " . json_encode( $dual ) );
}

// The ONE shape where the walks legitimately differ: a slot that carries over on BOTH
// axes. The flat resolver skipped it (nothing new), the seam resolves it as a
// DUPLICATE of its predecessor — output-identical either way, since a duplicate
// returns what the previous slot already returned (or is empty like it). Reachable
// only from hand-written `same` sentinels, because the shipped UI strips them as the
// slot ≥2 default; the fold's own control seeds exactly this shape for a new slot,
// which is why the seam must resolve it rather than treat it as absent.
$all_carry = t_seam_try_walk( array( 'key' => 'a', '2-src' => 'same', '2-use' => 'same' ), true, true, array( 'title' ), $text_default );
check(
	'P14.1 all-carry-over slot 2 resolves as a duplicate of slot 1',
	isset( $all_carry[2] ) && $all_carry[1] === $all_carry[2],
	json_encode( $all_carry )
);
check(
	'P14.1 …and the flat resolver skipped it, so output is unchanged either way',
	array() === array_diff_key( t_shipped_try_walk( array( 'key' => 'a', '2-src' => 'same', '2-use' => 'same' ), true, true, array( 'title' ), $text_default ), array( 1 => null ) ),
	''
);

// P14.2 — psu with a NO-KEY default read (content: default `content`), where a bare
// tag resolves instead of being dropped by the key gate.
$content_cases = array(
	'bare tag reads content' => array(),
	'excerpt then key'       => array( 'use' => 'excerpt', '2-use' => 'key', '2-key' => 'body' ),
	'key then carry over'       => array( 'use' => 'key', 'key' => 'body', '2-src' => 'ref', '2-ref' => 'office' ),
);
foreach ( $content_cases as $name => $legacy ) {
	$shipped = t_shipped_try_walk( $legacy, true, true, array( 'content', 'excerpt' ), $content_default );
	$folded  = t_seam_try_walk( t_migrate_try( $legacy, true, true ), true, true, array( 'content', 'excerpt' ), $content_default );
	check( "P14.2 [content: $name] migrated folded wire resolves identically", $shipped === $folded, 'legacy:  ' . json_encode( $shipped ) . "\n      folded: " . json_encode( $folded ) );
}
$bare_content = t_seam_try_walk( array(), true, true, array( 'content', 'excerpt' ), $content_default );
check( 'P14.2 a bare selecting tag still ATTEMPTS slot 1', isset( $bare_content[1] ) && '' === $bare_content[1]['src'] && 'content' === $bare_content[1]['use'], json_encode( $bare_content ) );

// P14.3 — psk shape (email/phone: per-slot key, NO `use` enum). An empty key at slot
// ≥2 carries over, and no `use(same)` is written for an axis the tag does not have.
$psk_cases = array(
	'two keyed slots'      => array( 'key' => 'a', '2-key' => 'b' ),
	'carry over the key'      => array( 'key' => 'a', '2-src' => 'site' ),
	'ref hop'              => array( 'key' => 'a', '2-src' => 'ref', '2-ref' => 'office' ),
	'bare tag drops'       => array(),
);
foreach ( $psk_cases as $name => $legacy ) {
	$shipped = t_shipped_try_walk( $legacy, true, false );
	$folded  = t_seam_try_walk( t_migrate_try( $legacy, true, false ), true, false );
	check( "P14.3 [psk: $name] migrated folded wire resolves identically", $shipped === $folded, 'legacy:  ' . json_encode( $shipped ) . "\n      folded: " . json_encode( $folded ) );
}
$psk_wire = bws_fold_emit_slot( bws_fold_from_flat( 2, array( '2-src' => 'site' ), false, false )['slot'] );
check( 'P14.3 no read axis → no read token on the wire', 'src(site)' === $psk_wire, var_export( $psk_wire, true ) );

// P14.4 — `none` shape (title/permalink/datetime_*): source chain only, read is a
// TAG-level option, so the wire must carry no read token at all.
$none_cases = array(
	'bare tag reads ambient' => array(),
	'ref hop at slot 2'      => array( '2-src' => 'ref', '2-ref' => 'office' ),
	'term hop at slot 1'     => array( 'srcTermIn' => 'category' ),
	'site fallback'          => array( '2-src' => 'site' ),
	// The shape this walk did NOT cover, and the omission cost a whole slot: with no
	// per-slot read axis, `{N}-src:current` can be a slot's ENTIRE content. It maps to a
	// struct with nothing but a chain, so mapping `current` to no step at all emitted ''
	// and the slot key was never written. Every other case here carries a second axis,
	// which is why they all passed while the mapping was wrong.
	'explicit current only'  => array( 'src' => 'ref', 'ref' => 'office', '2-src' => 'current' ),
);
foreach ( $none_cases as $name => $legacy ) {
	$shipped = t_shipped_try_walk( $legacy, false, false );
	$folded  = t_seam_try_walk( t_migrate_try( $legacy, false, false ), false, false );
	check( "P14.4 [none: $name] migrated folded wire resolves identically", $shipped === $folded, 'legacy:  ' . json_encode( $shipped ) . "\n      folded: " . json_encode( $folded ) );
}
$none_wire = bws_fold_emit_slot( bws_fold_from_flat( 3, array( '3-src' => 'ref', '3-ref' => 'office' ), false, false )['slot'] );
check( 'P14.4 read-less container emits a bare chain (carrying the flat era default)', 'src(refs,office,limit[1])' === $none_wire, var_export( $none_wire, true ) );

// P14.5 — `srcTermIn` DOES carry forward to a slot that carries over its source (#74).
//
// INVERTED from what shipped through 1.16.x, where a term hop stayed on its own slot and
// a slot that carries over silently read the AMBIENT entity: a leading `terms` step leaves `src`
// unset by design, so the carrying over slot carried an empty source plus no taxonomy and
// landed on the page.
//
// The old rule was a UI ARTIFACT, which is the part worth recording. `srcTermIn` was a
// SEPARATE control beside the source, and carrying over a standalone control's state across
// slots in the editor caused problems of its own. With `terms` as a step INSIDE the source
// chain, that constraint is gone: `src(same)` names the same source, and a taxonomy step is
// not a parameter of the source but part of what the source IS. The previous comment here
// recorded the mechanism ("the flat resolver's own variable never carried it") rather than
// the cause, which is why the decision read as unmotivated on re-reading.
$stm_walk = t_seam_try_walk( array( 'srcTermIn' => 'category', 'use' => 'title', '2-use' => 'key', '2-key' => 'b' ), true, true, array( 'title' ), $text_default );
check( 'P14.5 a term hop carries to a slot that carries over its source', 'terms,category' === ( $stm_walk[1]['src'] ?? null ) && 'terms,category' === ( $stm_walk[2]['src'] ?? null ), json_encode( $stm_walk ) );

// P14.6 — slot 1 is never ABSENT in a selecting container, and a combining container
// needs no such exception (its unconfigured read skips the slot one step later).
check( 'P14.6 selecting slot 1 with no keys at all is still a slot', null !== bws_fold_slot_struct( 1, array(), 'try' ), 'null' );
check( 'P14.6 selecting slot 2 with no keys is absent', null === bws_fold_slot_struct( 2, array(), 'try' ), 'not null' );
$empty_join = t_seam_walk( array(), 'join' );
check( 'P14.6 combining with no keys resolves nothing', array() === $empty_join, json_encode( $empty_join ) );

// P14.7 — what the FOLD adds over the flat wire: an explicit per-slot read at slot ≥2
// with no source of its own. Legacy `2-key` alone was DROPPED (FW-51) because a bare
// key could not say whether it meant "override" or "left blank"; `2:key(b)` says it.
$explicit = t_seam_try_walk( array( 'A' => 'key(a)', 'B' => 'key(b)' ), true, true, array( 'title' ), $text_default );
check( 'P14.7 folded key-only slot 2 resolves (the FW-51 ambiguity is gone)', 'b' === ( $explicit[2]['key'] ?? null ) && '' === ( $explicit[2]['src'] ?? null ), json_encode( $explicit ) );

// ── P15 THE CARRIED LIMIT (#61) ───────────────────────────────────────────
//
// `src(same)` means the SAME SOURCE, and a limit is one of that source's parameters.
// A slot that carries over its source therefore carries over its bound — which is what lets
// the try_ tag-level `limit` be retired without moving output: the number it used to
// supply to every attempt now reaches an attempt that carries over, through the carry.
//
// CONTAINER-SENSITIVE, and the gate is the one the file already draws twice: a
// COMBINING container registered `limit` per slot (`{N}-limit`), so an absent one
// there genuinely means "this slot states none" and must take the default. A
// SELECTING container never had a per-slot limit at all, so absence there can only
// mean carry over. Getting this uniform breaks {{join}} — P13.1's `term hop with limit`
// is the case that says so.

/** Walk a container's slots through the seam, returning [ n => resolved limit ]. */
function t_limit_walk( $options, $container = 'try', $per_slot_use = true, $max = 5 ) {
	$carry = array();
	$out   = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$slot = bws_fold_slot_struct( $n, $options, $container, $per_slot_use );
		if ( null === $slot ) {
			continue;
		}
		$skip          = '';
		$limit_default = 1;
		$flat          = bws_fold_slot_chain_options( $slot, $carry, 'try' !== $container, $skip, $limit_default );
		if ( null === $flat ) {
			continue;
		}
		$out[ $n ] = bws_clamp_limit( $flat['limit'] ?? null, $limit_default );
	}
	return $out;
}

$carry_try = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(same);use(name)' ) );
check( 'P15.1 a selecting slot that carries over its SOURCE carries over its LIMIT', array( 1 => 3, 2 => 3 ) === $carry_try, json_encode( $carry_try ) );

// An argless fanning step carries over the same way: it re-states the step but takes its
// relationship field from the carried source, so the bound it fans under is that
// source's. bws_fold_chain_fanning_steps() already treats the two as one shape.
$carry_argless = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(refs);use(name)' ) );
check( 'P15.2 …and so does an ARGLESS fanning step', array( 1 => 3, 2 => 3 ) === $carry_argless, json_encode( $carry_argless ) );

// A slot that states its own source states its own bound — carrying over there would
// silently bound a list the earlier slot knows nothing about.
$own_src = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(terms,category);use(name)' ) );
check( 'P15.3 a slot that states its OWN fanning source takes the chain default, not the carry', array( 1 => 3, 2 => 0 ) === $own_src, json_encode( $own_src ) );

// What is carried is the QUANTITY the earlier slot resolved, including where that slot
// stated nothing — an attempt carrying over `src(refs,office)` reads every office because
// that is what the slot it carries over from reads. Falling back to a default chosen for the
// FLAT wire this slot does not have is the #60 defect one level down.
$no_carry = t_limit_walk( array( 'A' => 'src(refs,office);use(title)', 'B' => 'src(same);use(name)' ) );
check( 'P15.4 a slot that carries over takes the resolved quantity, not its own default', array( 1 => 0, 2 => 0 ) === $no_carry, json_encode( $no_carry ) );

// The same, one era down: the flat spelling's implied 1 is what a slot that carries over gets,
// which is what the shipped legacy walk resolved (P14) and must keep resolving.
$flat_carry = t_limit_walk( array( 'src' => 'ref', 'ref' => 'office', 'use' => 'title', '2-src' => 'same', '2-use' => 'key', '2-key' => 'name' ) );
check( 'P15.4b …and a FLAT-spelled slot carries its implied 1', array( 1 => 1, 2 => 1 ) === $flat_carry, json_encode( $flat_carry ) );

// The guard is literally "my chain does not fan", which is WIDER than carry-over: a slot
// stating its own NON-FANNING root takes the carried number too. Pinned rather than left
// to the comment, because the reason it is harmless is a property rather than an accident
// — a non-fanning source resolves one entity, so any limit over it is inert.
$own_static = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(site);key(org_name)' ) );
check( 'P15.7 a slot stating its own NON-fanning root also takes the carry (inert: one source)', array( 1 => 3, 2 => 3 ) === $own_static, json_encode( $own_static ) );

// COMBINING is the deliberate contrast — and P13.1 `term hop with limit` is the
// shipped-legacy case that would otherwise move.
$carry_join = t_limit_walk( array( 'A' => 'src(terms,category,limit[3]);use(title)', 'B' => 'src(same);key(blurb)' ), 'join' );
check( 'P15.5 a COMBINING slot does NOT carry over the limit (it owns one per slot)', array( 1 => 3, 2 => 1 ) === $carry_join, json_encode( $carry_join ) );

// #61's own equivalence, end to end: the legacy wire the tag-level key served, and the
// migrated wire that no longer has it, must resolve the same quantity at every slot.
$legacy_tag_level = array( 'src' => 'ref', 'ref' => 'office', 'use' => 'title', 'limit' => '3', '2-src' => 'same', '2-use' => 'key', '2-key' => 'name' );
check(
	'P15.6 retiring the tag-level limit does not move what any attempt resolves',
	t_limit_walk( $legacy_tag_level ) === t_limit_walk( t_migrate_try( $legacy_tag_level, true, true ) ),
	'legacy: ' . json_encode( t_limit_walk( $legacy_tag_level ) ) . "\n      migrated: " . json_encode( t_limit_walk( t_migrate_try( $legacy_tag_level, true, true ) ) )
);

// ── P16 THE CARRIED TAXONOMY (#74) ────────────────────────────────────────
//
// `src(same)` names the same SOURCE, and a taxonomy step is part of what the source IS
// (unlike `limit`, which is a parameter OF a source — hence §P15's container split).
// A slot that carries over its source therefore carries the hop too.
//
// NOT CONTAINER-SENSITIVE, and §P15 is the test that tells them apart: the `limit` and
// read-axis splits are both about what ABSENCE means, and exist only because the two
// families registered those keys differently. `srcTermIn` is registered per slot in BOTH,
// so absence means the same thing in each — and absence is not what changes here. What
// changes is the meaning of `src(same)`, an explicit value, spelled and resolved
// identically in both containers.

/**
 * Walk a container's slots through the seam, returning [ n => the taxonomy each slot's
 * resolved chain hops into ], '' where it takes none.
 *
 * Read off the CHAIN since #104 — the flat `srcTermIn` the seam used to return is gone,
 * and reading the LAST term step is what "which taxonomy does this slot end up in" means
 * on a chain that may hold more than one.
 */
function t_tax_walk( $options, $container = 'join', $per_slot_use = true, $max = 5 ) {
	$carry = array();
	$out   = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$slot = bws_fold_slot_struct( $n, $options, $container, $per_slot_use );
		if ( null === $slot ) {
			continue;
		}
		$flat = bws_fold_slot_chain_options( $slot, $carry, 'try' !== $container, $sk );
		if ( null === $flat ) {
			continue;
		}
		$tax = '';
		foreach ( bws_fold_chain_from_options( array( 'src' => $flat['src'] ) ) as $step ) {
			if ( 'terms' === ( $step['slug'] ?? '' ) ) {
				$tax = (string) ( $step['arg'] ?? '' );
			}
		}
		$out[ $n ] = $tax;
	}
	return $out;
}

// P16.1 — the reported repro. Every department term carries a `phone`, so slot B was not
// reading an empty field: it was reading a different entity (the page).
$tax_join = t_tax_walk( array( 'A' => 'src(terms,department);use(title)', 'B' => 'src(same);key(phone)' ) );
check( 'P16.1 a COMBINING slot carries over the term hop', array( 1 => 'department', 2 => 'department' ) === $tax_join, json_encode( $tax_join ) );

$tax_try = t_tax_walk( array( 'A' => 'src(terms,department);use(title)', 'B' => 'src(same);use(key);key(phone)' ), 'try' );
check( 'P16.1 …and so does a SELECTING attempt (uniform, not container-sensitive)', array( 1 => 'department', 2 => 'department' ) === $tax_try, json_encode( $tax_try ) );

// P16.1b — ERA UNIFORMITY, witnessed WITHOUT the reference walks. §P13.1/§P14 compare the
// seam against `t_shipped_*_walk`, and those models were edited to carry the hop too — so
// they can no longer independently witness that the two ERAS agree about it. This drives
// legacy wire and its folded twin through the SEAM alone and compares the two, which is the
// property "no era gate" actually names.
$tax_legacy_wire = array( 'srcTermIn' => 'department', 'use' => 'title', 'limit' => '2', '2-src' => 'same', '2-key' => 'phone' );
$tax_legacy = t_tax_walk( $tax_legacy_wire );
$tax_folded = t_tax_walk( array( 'A' => 'src(terms,department,limit[2]);use(title)', 'B' => 'src(same);key(phone)' ) );
check( 'P16.1b legacy wire and its folded twin carry over the hop identically (no era gate)', $tax_legacy === $tax_folded && array( 1 => 'department', 2 => 'department' ) === $tax_legacy, 'legacy: ' . json_encode( $tax_legacy ) . ' folded: ' . json_encode( $tax_folded ) );

// P16.2 — CARRY DISCIPLINE: the taxonomy follows `src`, not `ref`.
//
// `ref` is init-carried on EVERY slot and survives a non-ref source, which is deliberate:
// a second construct reads it (an argless `refs` step takes the carried field). Nothing
// but `same` can consume a carried taxonomy — an argless `terms` step is refused outright —
// so init-carrying it would only ever hand an intervening slot a hop it never asked for.
$tax_cleared = t_tax_walk( array(
	'A' => 'src(terms,department);use(title)',
	'B' => 'src(current);key(phone)',
	'C' => 'src(same);key(x)',
) );
check( 'P16.2 a slot stating its OWN root does not acquire the carried hop', '' === ( $tax_cleared[2] ?? 'X' ), json_encode( $tax_cleared ) );
check( 'P16.2 …and a later carry over takes THAT slot\'s source, hop and all', '' === ( $tax_cleared[3] ?? 'X' ), json_encode( $tax_cleared ) );

// P16.3 — the hop travels WITH the source, so a slot carrying over a ref-rooted source
// carries over no taxonomy from further back.
$tax_ref = t_tax_walk( array( 'A' => 'src(terms,department);use(title)', 'B' => 'src(refs,office);key(a)', 'C' => 'src(same);key(b)' ) );
check( 'P16.3 a carried relationship source carries no stale taxonomy', array( 1 => 'department', 2 => '', 3 => '' ) === $tax_ref, json_encode( $tax_ref ) );

// P16.4 — a slot that states its own hop keeps it; carrying over one that already hopped and
// hopping again is a SECOND term step, which the flat triple cannot express.
//
// KNOW THE LEGACY SHAPE SPACE BEFORE TREATING A GREEN LEGACY CORPUS AS A COVERED ONE — the
// cases below are the whole of it, and enumerating them is what showed the first #104 draft
// was wrong on half. Flat wire holds at most TWO steps and the second can only ever be
// `terms`: post-1.6.0 `srcTermIn` was the only second-step option and it always FOLLOWS
// `ref` (#44). So the ONE adjacency flat wire can spell is `refs → terms`, which is a join
// that RUNS, and a slot's own step under `same` is only ever a `terms` (a `2-ref` there is
// §P17 residue and is dropped). Four merge cases total —
//   carried ∈ { ∅, refs, terms, refs;terms }  ×  own = terms
// — of which blind APPEND is wrong on two (the two whose tail is already `terms`). What
// decides how much of the carried chain gives way is bws_fold_chain_join()'s PHPDoc.
$tax_own = t_tax_walk( array( 'A' => 'src(terms,department);use(title)', 'B' => 'src(terms,office);key(a)' ) );
check( 'P16.4 a slot states its own hop over any carried one', array( 1 => 'department', 2 => 'office' ) === $tax_own, json_encode( $tax_own ) );
// A carried hop is a DEFAULT, not a step this chain took, so a slot that carries over and
// then states its own `terms` REPLACES it rather than colliding with it. This shape is
// reachable flat wire — `2-src:same|2-srcTermIn:office`, since srcTermIn shows under every
// non-site source — and migrates to exactly this chain, so reading it as a second term step
// would skip a slot that renders today.
$tax_double = t_tax_walk( array( 'A' => 'src(terms,department);use(title)', 'B' => 'src(same;terms,office);key(a)' ) );
check( 'P16.4 a carried hop is REPLACED by the slot\'s own, not collided with', array( 1 => 'department', 2 => 'office' ) === $tax_double, json_encode( $tax_double ) );
// …AND THE ROW ABOVE CANNOT SEE THE DIFFERENCE ON ITS OWN. t_tax_walk reports the LAST term
// step, so `terms,department;terms,office` answers `office` too — it sat green through the
// first draft of #104, which appended. The CHAIN is what says which happened, and the
// rendered difference is the whole reason it matters: a term step off a TERM input has no
// post to read, so the appended form resolves EMPTY and the slot disappears from a
// {{join}} that rendered it (measured on the testbed, both eras).
$tax_double_wire = t_seam_walk( array( 'A' => 'src(terms,department);use(title)', 'B' => 'src(same;terms,office);key(a)' ), 'join' );
check( 'P16.4 …asserted on the CHAIN, which is the only place replace and append differ', 'terms,office' === ( $tax_double_wire[2]['src'] ?? null ), json_encode( $tax_double_wire[2] ?? null ) );
// A DIFFERENT slug appends, because it is not refining the same axis — a carried
// relationship source that this slot then drops into a taxonomy is two steps and means it.
$tax_other_slug = t_seam_walk( array( 'A' => 'src(refs,office);key(a)', 'B' => 'src(same;terms,category);key(b)' ), 'join' );
check( 'P16.4 …while a step on a DIFFERENT axis appends to the carried chain', 'refs,office;terms,category' === ( $tax_other_slug[2]['src'] ?? null ), json_encode( $tax_other_slug[2] ?? null ) );
// …AND THE TEST IS THE JOIN, NOT THE SLUG (bws_fold_chain_join). The carried tail gives
// way only where this slot's first step cannot RUN off it, and by as little as possible.
// `refs` accepts a post input and produces one, so `src(same;refs,manager)` behind
// `src(refs,office)` is office → manager — the two-relationships-away chain FW-56 exists
// for. A cut of this scoped by SLUG instead dropped it and read `manager` off the AMBIENT
// entity: a plausible value from the wrong entity ([I15]), and the very capability #104
// shipped, broken by its own fix.
$ref_repeat = t_seam_walk( array( 'A' => 'src(refs,office);key(a)', 'B' => 'src(same;refs,manager);key(b)' ), 'join' );
check( 'P16.4 …and a REPEATABLE slug is NOT dropped: a carried ref hop keeps hopping', 'refs,office;refs,manager' === ( $ref_repeat[2]['src'] ?? null ), json_encode( $ref_repeat[2] ?? null ) );
// THE CASE THAT SEPARATES "the JOIN cannot run" FROM "the SLUG cannot repeat", and the
// reason the second reading had to go. A carried `terms` step in front of an own
// `refs;terms` pair conflicts on the SLUG — both chains state `terms` — but not on the
// JOIN: `refs` ACCEPTS a term input, so the chain runs exactly as written and nothing
// should give way. The slug reading deleted the carried step and rooted the slot at the
// ambient entity.
$join_ok = t_seam_walk( array( 'A' => 'src(terms,department);key(a)', 'B' => 'src(same;refs,x;terms,y);key(b)' ), 'join' );
check( 'P16.4 a carried step that this slot CAN run off is kept, same slug or not', 'terms,department;refs,x;terms,y' === ( $join_ok[2]['src'] ?? null ), json_encode( $join_ok[2] ?? null ) );

// NON-VACUITY for the derive itself. WHAT bws_fold_chain_join() DERIVES FROM is stated at the
// owner and not here: the four rows above pin its ANSWERS case by case, but nothing below pins
// which maps it reads, so a third map could be added and every assertion would still pass —
// an unpinned clause takes a pointer (CLAUDE.md §Documentation ownership, the harness
// exemption). What IS pinned: it DECLINES TO TRIM when either end is unknown vocabulary, the
// safe direction, since an unknown answer must not rewrite a source. Asserted so a harness
// that forgot to load the engine's list fails HERE, by name, rather than passing the rows
// above for the wrong reason.
$j_terms = array( array( 'slug' => 'terms', 'arg' => 'a', 'limit' => null, 'extra' => array() ) );
$j_refs  = array( array( 'slug' => 'refs', 'arg' => 'x', 'limit' => null, 'extra' => array() ) );
$j_junk  = array( array( 'slug' => 'wibble', 'arg' => 'x', 'limit' => null, 'extra' => array() ) );
check( 'P16.4 the join derive is LIVE: a terms tail cannot feed a terms step', array() === bws_fold_chain_join( $j_terms, $j_terms ), json_encode( bws_fold_chain_join( $j_terms, $j_terms ) ) );
check( 'P16.4 …a terms tail CAN feed a refs step (nothing trimmed)', $j_terms === bws_fold_chain_join( $j_terms, $j_refs ), json_encode( bws_fold_chain_join( $j_terms, $j_refs ) ) );
check( 'P16.4 …a refs tail can feed a terms step (nothing trimmed)', $j_refs === bws_fold_chain_join( $j_refs, $j_terms ), json_encode( bws_fold_chain_join( $j_refs, $j_terms ) ) );
check( 'P16.4 …and UNKNOWN vocabulary trims nothing: the chain short-circuits, it does not rewrite', $j_terms === bws_fold_chain_join( $j_terms, $j_junk ), json_encode( bws_fold_chain_join( $j_terms, $j_junk ) ) );
// The ROOT is not a step and is never trimmed — a slot carries over the SOURCE, whatever this
// slot then fails to run off it.
$j_rooted = array( array( 'slug' => 'site', 'arg' => null, 'limit' => null, 'extra' => array() ) );
check( 'P16.4 …and the ROOT is never trimmed', $j_rooted === bws_fold_chain_join( $j_rooted, $j_terms ), json_encode( bws_fold_chain_join( $j_rooted, $j_terms ) ) );
// The legacy wire this rule exists for, driven through the seam end to end. Editor-authorable:
// leave slot 2's source alone, pick a different taxonomy.
$tax_legacy_pair = t_seam_walk( array( 'srcTermIn' => 'department', 'use' => 'title', '2-src' => 'same', '2-srcTermIn' => 'office', '2-key' => 'phone' ), 'join' );
check( 'P16.4 …and the LEGACY pair it exists for resolves to the slot\'s own hop alone', 'terms,office' === ( $tax_legacy_pair[2]['src'] ?? null ), json_encode( $tax_legacy_pair[2] ?? null ) );
// A real second term step RESOLVES since #104 — the chain states two hops and hands both
// on. What the slot ends up IN is the last one, which is what this walk reads.
$tax_two_steps = t_tax_walk( array( 'A' => 'src(terms,department;terms,office);key(a)' ) );
check( 'P16.4 …and two REAL term steps now resolve, ending in the LAST one', array( 1 => 'office' ) === $tax_two_steps, json_encode( $tax_two_steps ) );

// ── P16.5 AMBIENT MUST BE SPELLED (#74) ─────────────────────────────────────
//
// At slot 1 an ABSENT source legitimately means the ambient entity. At slot ≥2 absence
// means CARRY-OVER, so ambient is not a default a slot can fall back to — it has to be
// spelled, or carried from something that spelled it.
//
// The accumulator initialises to `{src:''}`, which reads as "ambient", and a SKIPPED slot
// never feeds it. So when every preceding slot skipped, `src(same)` carried the
// INITIALISER and resolved against the ambient entity while the only source on screen said
// something else.
$never_carried = t_tax_walk( array( 'A' => 'src(site)', 'B' => 'src(same);key(x)' ) );
check( 'P16.5 `same` skips when NOTHING was ever carried', ! isset( $never_carried[2] ), json_encode( $never_carried ) );
// Its OWN reason, not `chain`. That one means the flat read cannot EXPRESS the wire; this
// chain is expressible and simply has nothing yet to be the same as, so telling the author
// "source not supported" would send them after the wrong thing.
$same_carry = array( '_fed' => false );
bws_fold_slot_chain_options( bws_fold_parse_slot( 'src(same);key(x)' ), $same_carry, true, $isr );
// The WORDING for it is the preview's to own (preview-label-test.php §skip warnings).
check( 'P16.5 …reporting `same`, which is not the inexpressible-wire reason', 'same' === $isr, var_export( $isr, true ) );
$never_carried_flat = t_seam_walk( array( 'A' => 'src(site)', 'B' => 'src(same);key(x)' ), 'join' );
check( 'P16.5 …and does not silently read the ambient entity instead', ! isset( $never_carried_flat[2] ), json_encode( $never_carried_flat[2] ?? null ) );

// Carrying over an AMBIENT slot 1 is legitimate — slot 1 spelled it, by being slot 1.
$ambient_carry = t_seam_walk( array( 'A' => 'key(a)', 'B' => 'src(same);key(b)' ), 'join' );
check(
	'P16.5 carrying over an ambient slot 1 still resolves (absence there IS the spelling)',
	isset( $ambient_carry[2] ) && '' === $ambient_carry[2]['src'],
	json_encode( $ambient_carry[2] ?? null )
);

// ── P17 A DELIBERATE DIVERGENCE: the stale hidden `ref` (#74) ───────────────
//
// Everywhere else in §P13/§P14 the legacy wire and its folded twin resolve identically.
// This is the one shape where they do NOT, and it is a decision rather than a gap — so it
// is pinned here, or the next reader sees an equivalence break and "fixes" it.
//
// `2-src:same|2-ref:manager` is reachable through ordinary UI use: set slot 2's source to
// Related Post, fill the relationship key, then switch slot 2's source to Same as Previous.
// `show_if` is display-only (editor-conditional-options.js returns null and never calls
// setState), `src`/`ref` are not a composite control so neither owns the other's key, and no
// reconcile-on-src-change exists. The `2-ref` stays, with its control off screen.
//
// The shipped flat walk honoured it — sticky `$last_ref` never asked whether the key was
// reachable — so an invisible value silently steered which entity slot 2 read. That is the
// same defect this issue exists to remove, so the FOLD's reading is the correct one and the
// flat behaviour was the latent bug.
//
// The rejected alternative is worth recording: making bws_fold_from_flat() honour the stale
// key would preserve equivalence strictly, but migration would MATERIALIZE the residue into
// a visible `src(refs,manager)` step — so a tag the author believed said "same as previous"
// would open showing a relationship hop they never configured. That launders invisible state
// into apparent intent, which is worse than dropping it.
$stale = array( 'key' => 'a', 'src' => 'ref', 'ref' => 'office', '2-src' => 'same', '2-key' => 'b', '2-ref' => 'manager' );
$stale_shipped = t_shipped_join_walk( $stale );
$stale_folded  = t_seam_walk( t_migrate_join( $stale ), 'join' );
check(
	'P17 the shipped flat walk read slot 2 through the HIDDEN `2-ref`',
	'refs,manager' === ( $stale_shipped[2]['src'] ?? null ),
	json_encode( $stale_shipped[2] ?? null )
);
check(
	'P17 …and the fold deliberately drops it, reading the source slot 2 actually shows',
	'refs,office' === ( $stale_folded[2]['src'] ?? null ),
	json_encode( $stale_folded[2] ?? null )
);


// ── P18 THE CHAIN HAND-OFF (#104, FW-71) ────────────────────────────────────
//
// The seam emits the slot's resolved chain as DEPTH-0 CHAIN WIRE in `src` — the same
// option key, and the same language, a base tag states its source in ([I16]). Everything
// above asserts what each era RESOLVES; this section asserts the hand-off itself.

// P18.1 — IDEMPOTENCE, as a FIXED POINT ON THE RE-LEVELED FORM.
//
// The emitted string DIFFERS from the stored slot value by design: bracket alternation is
// by depth (BWS_FOLD_BR_PAIRS), a slot's chain sits at enclosing level 1, and this emits at
// level 0 — so `limit[3]` re-levels to `limit(3)`. Written the naive way (emit === the
// stored `src(…)` inner text) this fails on EVERY chain carrying a bound, and the fix
// someone reaches for is to loosen the assertion. So the property is
// `emit₀(parse(emit₀(chain))) === emit₀(chain)`: what the seam hands on must survive the
// reading every consumer gives it (bws_fold_chain_from_options), unchanged.
//
// Re-leveling only ever goes SHALLOWER, which is why it is safe: it cannot run out of
// bracket pairs. Had the direction been the other way, a deep chain would have had nowhere
// to go.
$fixed_point = array(
	'root only'              => 'src(site);key(a)',
	'explicit current'       => 'src(current);key(a)',
	'one ref hop'            => 'src(refs,office);key(a)',
	'ref hop with a bound'   => 'src(refs,office,limit[3]);key(a)',
	'two ref hops'           => 'src(refs,a;refs,b);key(x)',
	'ref then terms'         => 'src(refs,office;terms,category);use(title)',
	'rooted, bound, hopped'  => 'src(site;refs,partner,limit[2];terms,category,limit[0]);key(a)',
	'repeater rows'          => 'src(rows,rows);key(a)',
	'unknown step slug'      => 'src(refs,a;wibble,b);key(x)',
);
foreach ( $fixed_point as $why => $wire ) {
	$fp_carry = array();
	$fp       = bws_fold_slot_chain_options( bws_fold_slot_struct( 1, array( 'A' => $wire ), 'join' ), $fp_carry, true );
	$emitted  = (string) ( $fp['src'] ?? "\0" );
	$again    = bws_fold_emit_chain( bws_fold_chain_from_options( array( 'src' => $emitted ) ), 0 );
	check( "P18.1 [$why] the emitted chain is a fixed point of the reading every consumer gives it", $emitted === $again, "emitted: $emitted\n      re-read: $again" );
}

// P18.1b — …SEEDED WITH `same`-RESOLVED SYNTHETIC CHAINS, which is the part the corpus
// above cannot supply. The chain a `same` slot hands on is one NO AUTHOR EVER WROTE: it is
// slot 1's chain spliced under slot 2's own steps, so the shapes most at risk of an emit
// bug (a bound carried from another slot, a hop appended behind a carried hop) exist
// only here.
$synth = array(
	'carries over a bounded ref hop'   => array( 'A' => 'src(refs,office,limit[3]);key(a)', 'B' => 'src(same);key(b)' ),
	'carries over, then hops again'    => array( 'A' => 'src(refs,office,limit[3]);key(a)', 'B' => 'src(same;refs,manager);key(b)' ),
	'carries over, then drops to terms' => array( 'A' => 'src(refs,office);key(a)', 'B' => 'src(same;terms,category,limit[2]);key(b)' ),
	'carries over a carry-over'          => array( 'A' => 'src(site;refs,partner);key(a)', 'B' => 'src(same);key(b)', 'C' => 'src(same);key(c)' ),
	'argless refs takes the field' => array( 'A' => 'src(refs,office,limit[3]);key(a)', 'B' => 'src(refs);key(b)' ),
);
foreach ( $synth as $why => $options ) {
	$sy_carry = array();
	foreach ( array( 1, 2, 3 ) as $sy_n ) {
		$sy_slot = bws_fold_slot_struct( $sy_n, $options, 'join' );
		if ( null === $sy_slot ) {
			continue;
		}
		$sy = bws_fold_slot_chain_options( $sy_slot, $sy_carry, true );
		if ( null === $sy ) {
			continue;
		}
		$sy_emitted = (string) $sy['src'];
		$sy_again   = bws_fold_emit_chain( bws_fold_chain_from_options( array( 'src' => $sy_emitted ) ), 0 );
		check( "P18.1b [$why] slot $sy_n's synthetic chain is a fixed point", $sy_emitted === $sy_again, "emitted: $sy_emitted\n      re-read: $sy_again" );
	}
}

// P18.2 — the CARRY is chain-shaped, so a slot that carries over takes the prior slot's HOPS and
// not merely its root. That deletes the `$tax_inherit` special case the flat triple needed:
// a triple could carry one taxonomy and no relationship step at all, so a slot carrying over a
// two-step source landed somewhere the wire never said.
$carry_chain = t_seam_walk( array( 'A' => 'src(site;refs,partner);key(a)', 'B' => 'src(same);key(b)' ), 'join' );
check( 'P18.2 `same` carries over the WHOLE chain, root and hops', 'site;refs,partner' === ( $carry_chain[2]['src'] ?? null ), json_encode( $carry_chain[2] ?? null ) );
$carry_then = t_seam_walk( array( 'A' => 'src(refs,office);key(a)', 'B' => 'src(same;terms,category);key(b)' ), 'join' );
check( 'P18.2 …and a slot\'s own step APPENDS to what it carried', 'refs,office;terms,category' === ( $carry_then[2]['src'] ?? null ), json_encode( $carry_then[2] ?? null ) );

// P18.3 — THE LEAK, NAMED. bws_fold_chain_from_options() APPENDS a `terms` step for any
// surviving `srcTermIn`, so a tag-level one left in the options a container merges the
// seam's output over would grow a step on EVERY slot's chain — and now compose with
// whatever the slot itself said. The seam SUPERSEDES the legacy source axes by contract
// (explicit empties), which is why this belongs to the seam and not to each caller:
// {{join}} builds its slot options from the seam alone while try_ merges them over the
// tag's, so a container-side rule would be one caller carrying it for the other's sake.
//
// [I15]-CLASS: a leaked step returns a PLAUSIBLE VALUE FROM THE WRONG ENTITY, not an
// empty one, so the assertion names the leak rather than merely rendering green.
$leak_tag_level = array(
	'srcTermIn' => 'department',   // tag-level residue off a half-migrated tag
	'ref'       => 'stale_rel',
	'A'         => 'src(site);key(a)',
	'B'         => 'src(refs,office);key(b)',
);
// Taken from the RAW seam return, never through t_seam_walk: the walk NAMES the two axes
// when it builds its row, which would supply the empties the seam is supposed to and make
// this pass on a seam that omitted them.
$leak_carry = array();
$leak_raw   = array();
foreach ( array( 1, 2 ) as $leak_n ) {
	$leak_raw[ $leak_n ] = bws_fold_slot_chain_options( bws_fold_slot_struct( $leak_n, $leak_tag_level, 'join' ), $leak_carry, true );
}
check(
	'P18.3 the seam returns EXPLICIT empties for both axes it supersedes',
	array_key_exists( 'ref', (array) $leak_raw[1] ) && '' === $leak_raw[1]['ref']
		&& array_key_exists( 'srcTermIn', (array) $leak_raw[1] ) && '' === $leak_raw[1]['srcTermIn'],
	json_encode( $leak_raw[1] )
);
// THE MERGE is where the leak would actually land, so assert the merge and not only the
// seam: a container merging the seam's output over the tag's options must end up with a
// chain the tag-level key cannot reach into. A missing empty shows up HERE as a GROWN
// STEP — the [I15] failure, named.
foreach ( array( 1 => 'site', 2 => 'refs,office' ) as $leak_n => $want ) {
	$leak_merged = array_merge( $leak_tag_level, (array) $leak_raw[ $leak_n ] );
	$leak_got    = bws_fold_emit_chain( bws_fold_chain_from_options( $leak_merged ), 0 );
	check(
		"P18.3 slot $leak_n's chain is its own — no tag-level `srcTermIn` step grown onto it",
		$want === $leak_got,
		"want: $want  got: $leak_got"
	);
}

// P18.4 — PER-STEP BOUNDS RIDE THE WIRE. §P15 owns the slot's ITEM bound (what the
// container slices by); this owns what the ENGINE is told, which is a different quantity
// and only visible now that the source is handed on whole.
//
// A STATED CHANGE, not a silent one: a slot with TWO fanning steps and a bound used to
// flatten to one triple plus one number — every hop unbounded, the finished items sliced at
// the end — and now bounds each hop as the identically-spelled base tag does. Reachable
// only where a slot fans twice, which is `refs` + `terms`; the flat triple could express
// nothing else. Recorded in fold-test-matrix.md §F9d.
$steps_of = static function ( $wire ) {
	$sw_carry = array();
	$sw       = bws_fold_slot_chain_options( bws_fold_slot_struct( 1, array( 'A' => $wire ), 'join' ), $sw_carry, true );
	return bws_fold_chain_to_steps( bws_fold_chain_from_options( array( 'src' => (string) ( $sw['src'] ?? '' ) ) ) );
};
check(
	'P18.4 a per-step bound reaches the engine as a step limit',
	array( array( 'type' => 'refs', 'field' => 'office', 'limit' => 3 ) ) === $steps_of( 'src(refs,office,limit[3]);key(a)' ),
	json_encode( $steps_of( 'src(refs,office,limit[3]);key(a)' ) )
);
check(
	'P18.4 an UNLIMITED step stays byte-identical to what the flat assembler produced',
	array( array( 'type' => 'refs', 'field' => 'office' ) ) === $steps_of( 'src(refs,office,limit[0]);key(a)' ),
	json_encode( $steps_of( 'src(refs,office,limit[0]);key(a)' ) )
);
// P18.6 — A SITE ROOT NEVER TAKES THE LEGACY TERM STEP, and the twin of this rule is what
// keeps a slot at parity with the identically-keyed base tag ([I6]/[I16]). `srcTermIn`
// registers `show_if src: not:site`, so the pair is hand-edit-only, and every arm has always
// let the site read win — which is why bws_fold_chain_from_options() refuses to append it
// for a BASE tag's flat keys and bws_fold_migrate_base_src() leaves a site root alone.
//
// HARMLESS BEFORE #104 AND LOAD-BEARING AFTER IT: the retired flatten collapsed the mapped
// chain back to a triple and the reader dropped the step off the triple, so appending it
// changed nothing. Now the chain IS the hand-off, so appending it resolves a term step off a
// site source — no post input, hence empty — which silently re-opened the parity §F9b.5
// closed one release earlier. MEASURED on the testbed both ways.
$site_tax_flat = t_seam_walk( array( 'A' => '', 'src' => 'site', 'srcTermIn' => 'department', 'key' => 'org_phone' ), 'join' );
check( 'P18.6 flat site + srcTermIn maps to a site-ONLY chain', 'site' === ( $site_tax_flat[1]['src'] ?? null ), json_encode( $site_tax_flat[1] ?? null ) );
// The same fact one layer down, on the mapper the converter and the editor's mount migrator
// both share with the render dual-read — one fix, three paths, so a stored tag, a converted
// one and an editor-touched one cannot disagree.
check(
	'P18.6 …so the SLOT migrator writes no term step either',
	'src(site);key(org_phone)' === bws_fold_emit_slot( bws_fold_from_flat( 2, array( '2-src' => 'site', '2-srcTermIn' => 'department', '2-key' => 'org_phone' ), true )['slot'] ),
	bws_fold_emit_slot( bws_fold_from_flat( 2, array( '2-src' => 'site', '2-srcTermIn' => 'department', '2-key' => 'org_phone' ), true )['slot'] )
);
// HAND-WRITTEN chain wire is the deliberate contrast: it SAYS the step, so it keeps it and
// resolves empty — exactly as the identically-spelled base tag `{{phone src:site;terms,x}}`
// does. Wire means what it says (ADR 0004); what the rule protects is the flat KEYS.
$site_tax_wire = t_seam_walk( array( 'A' => 'src(site;terms,department);key(org_phone)' ), 'join' );
check( 'P18.6 hand-written chain wire KEEPS its term step', 'site;terms,department' === ( $site_tax_wire[1]['src'] ?? null ), json_encode( $site_tax_wire[1] ?? null ) );

// P18.5 — THE SEAM PASSES A STEP THROUGH VERBATIM, and the emitted chain is canonical
// anyway, because both producers of a slot struct already trim. Pinned so the seam is not
// "hardened" with a third copy of that rule: a normalization here would pass this row
// whether or not the parser still trimmed, i.e. it would hide the producer regressing.
$trim_carry = array();
$trim       = bws_fold_slot_chain_options( bws_fold_parse_slot( 'src(refs, office ; terms, category );key(a)' ), $trim_carry, true );
check( 'P18.5 the emitted chain is canonical — the PARSER owns the trim', 'refs,office;terms,category' === ( $trim['src'] ?? null ), var_export( $trim['src'] ?? null, true ) );

check(
	'P18.4 both hops of a two-step chain are bounded, in wire order',
	array(
		array( 'type' => 'refs', 'field' => 'office', 'limit' => 1 ),
		array( 'type' => 'terms', 'slug' => 'category', 'limit' => 2 ),
	) === $steps_of( 'src(refs,office,limit[1];terms,category,limit[2]);key(a)' ),
	json_encode( $steps_of( 'src(refs,office,limit[1];terms,category,limit[2]);key(a)' ) )
);

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
