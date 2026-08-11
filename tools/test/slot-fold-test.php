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
 *                     read-axis CONTAINER divergence (selecting inherits, combining
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
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
require __DIR__ . '/../../includes/helpers/field-helpers.php';

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
	array( 'try', 'src(refs,office);use(same)' ),                       // new source, inherited read
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
	array( 'table', 'src(entries,staff_members,limit[0]);key(name)' ),
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
		'chain' => array( array( 'slug' => 'entries', 'arg' => 'staff_members', 'limit' => '0', 'extra' => array() ) ),
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
	array( 'src(entries,limit[5])', '5', 'src(entries,limit[5])' ),                  // argless step, no positional hole
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
// `use(same)` beside a stale key: inherit wins, the key is inert.
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

// Slot ≥2, src axis — absence and the `same` sentinel both mean inherit, in BOTH
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
 * Walk slots 1..$max through the seam, returning [ n => flat options ] for the resolved ones.
 *
 * The `limit` is MATERIALIZED from the seam's era out-param, exactly as the shipped
 * container arms do it. That is the whole point of the out-param: the triple's `src` is a
 * legacy token on every slot, so a comparison that left `limit` absent would be comparing
 * two option sets that resolve DIFFERENT quantities and calling them equal (#60).
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
		$flat          = bws_fold_slot_flat_options( $slot, $carry, 'try' !== $container, $skip, $limit_default );
		if ( null === $flat ) {
			continue;
		}
		$flat['limit'] = (string) bws_clamp_limit( $flat['limit'] ?? null, $limit_default );
		$out[ $n ]     = $flat;
	}
	return $out;
}

/** The flat option set shipped {{join}} built for one slot, transcribed from base-tags.php. */
function t_shipped_join_walk( $options, $max = 5 ) {
	$last_src = '';
	$last_ref = '';
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
		if ( '' !== $src && 'same' !== $src ) {
			$last_src = $src;
		}
		if ( '' !== $ref ) {
			$last_ref = $ref;
		}
		$flat = array(
			'src'       => $last_src,
			'ref'       => $last_ref,
			'srcTermIn' => $stm,
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
function t_norm_walk( $walk ) {
	foreach ( $walk as $n => $flat ) {
		if ( 'current' === $flat['src'] ) {
			$walk[ $n ]['src'] = '';
		}
	}
	return $walk;
}
$legacy_join_cases = array(
	'plain two-slot'          => array( 'key' => 'first_name', '2-key' => 'last_name' ),
	'title + key'            => array( 'use' => 'title', '2-key' => 'role' ),
	'ref hop then inherit'   => array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', '2-key' => 'phone' ),
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
// slots 1 and 3. Slot 3 inherits, so it must see slot 2's FOLDED source through the
// one shared accumulator.
$mixed = array(
	'src'   => 'ref',
	'ref'   => 'office',
	'key'   => 'a',
	'B'     => 'src(site);key(org_phone)',
	'3-key' => 'c',
);
$walk = t_seam_walk( $mixed, 'join' );
check( 'P13.2 mixed era: legacy slot 1 resolves', array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => '', 'use' => 'key', 'key' => 'a', 'limit' => '1' ) === ( $walk[1] ?? null ), json_encode( $walk[1] ?? null ) );
check( 'P13.2 mixed era: folded slot 2 resolves', array( 'src' => 'site', 'ref' => 'office', 'srcTermIn' => '', 'use' => 'key', 'key' => 'org_phone', 'limit' => '1' ) === ( $walk[2] ?? null ), json_encode( $walk[2] ?? null ) );
check( 'P13.2 mixed era: legacy slot 3 inherits the FOLDED slot 2 source', 'site' === ( $walk[3]['src'] ?? '' ), json_encode( $walk[3] ?? null ) );
// The reverse threading: a FOLDED slot inheriting from a LEGACY predecessor.
$mixed_rev = array(
	'2-src' => 'site',
	'2-key' => 'org_phone',
	'C'     => 'src(same);key(c)',
);
$walk_rev = t_seam_walk( $mixed_rev, 'join' );
check( 'P13.2 mixed era: folded slot 3 inherits a LEGACY slot 2 source', 'site' === ( $walk_rev[3]['src'] ?? '' ), json_encode( $walk_rev[3] ?? null ) );

// P13.3 — SKIP BEFORE CARRY. A combining slot with no read is unconfigured: it
// renders nothing AND must not feed the accumulator, or a later slot's inherit
// re-points at a source the author never chose.
$skip = array( 'A' => 'key(a)', 'B' => 'src(site)', 'C' => 'src(same);key(c)' );
$walk = t_seam_walk( $skip, 'join' );
check( 'P13.3 combining: read-less slot 2 is skipped', ! isset( $walk[2] ), json_encode( $walk[2] ?? null ) );
check( 'P13.3 combining: slot 3 inherits slot 1, NOT the skipped slot 2', '' === ( $walk[3]['src'] ?? 'X' ), json_encode( $walk[3] ?? null ) );
// Selecting reads absence as inherit, and DOES resolve the slot.
$walk_sel = t_seam_walk( array( 'A' => 'key(a)', 'B' => 'src(site)' ), 'try' );
check( 'P13.3 selecting: read-less slot 2 inherits the read and resolves', isset( $walk_sel[2] ) && 'a' === $walk_sel[2]['key'] && 'site' === $walk_sel[2]['src'], json_encode( $walk_sel[2] ?? null ) );

// P13.4 — explicit `use(same)` inherits in BOTH containers (only ABSENCE diverges).
$same_join = t_seam_walk( array( 'A' => 'key(a)', 'B' => 'src(site);use(same)' ), 'join' );
check( 'P13.4 combining honors an explicit use(same)', isset( $same_join[2] ) && 'a' === $same_join[2]['key'] && 'key' === $same_join[2]['use'], json_encode( $same_join[2] ?? null ) );
$same_analog = t_seam_walk( array( 'A' => 'use(title)', 'B' => 'src(site);use(same)' ), 'join' );
check( 'P13.4 an inherited ANALOG read carries too', 'title' === ( $same_analog[2]['use'] ?? '' ), json_encode( $same_analog[2] ?? null ) );

// P13.5 — chain shapes the flat TRIPLE cannot express SKIP the slot rather than
// resolving a truncated prefix (which would silently read a different source).
//
// The 1.17.0 chain compiler (slot-fold-compile.php, 5h) does NOT flip these: it gave
// the ENGINE arbitrary hops, and depth-0 chains resolve through it, but a SLOT's output
// is produced by container arms that dispatch on the flat `src`/`srcTermIn` tokens this
// flattener returns. Handing them the nearest token is the truncated prefix again. They
// flip when those arms dispatch on the chain's terminal step KIND (the verb-agnostic
// resolver refactor) — see bws_fold_slot_flat_options()'s docblock.
foreach ( array(
	'two ref hops'   => 'src(refs,a;refs,b);key(x)',
	'two term hops'  => 'src(terms,category;terms,post_tag);key(x)',
	'repeater entry' => 'src(entries,rows);key(x)',
	'ref after term' => 'src(terms,category;refs,a);key(x)',
) as $why => $wire ) {
	$w = t_seam_walk( array( 'A' => $wire ), 'join' );
	check( "P13.5 inexpressible chain skips the slot ($why)", ! isset( $w[1] ), json_encode( $w[1] ?? null ) );
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
bws_fold_slot_flat_options( bws_fold_parse_slot( 'src(entries,rows);key(x)' ), $sr_carry, true, $sr );
check( 'P13.5b an inexpressible chain reports reason `chain`', 'chain' === $sr, var_export( $sr, true ) );
$sr_carry = array();
bws_fold_slot_flat_options( bws_fold_parse_slot( 'src(site)' ), $sr_carry, true, $sr );
check( 'P13.5b an unconfigured combining slot reports reason `read`', 'read' === $sr, var_export( $sr, true ) );
$sr_carry = array();
bws_fold_slot_flat_options( bws_fold_parse_slot( 'src(site);key(a)' ), $sr_carry, true, $sr );
check( 'P13.5b a RESOLVING slot clears the reason (reused variable, no leak)', '' === $sr, var_export( $sr, true ) );

// P13.5c — an INCOMPLETE step (a `terms` hop with no taxonomy) skips, with its own
// reason. This is the fold's own hazard: the flat wire could not state a hop without its
// argument, so the shape did not exist before. Flattening it is worse than skipping —
// an empty `srcTermIn` is exactly how NO term hop is spelled, so the slot would read the
// un-hopped entity and return a plausible WRONG value instead of nothing. `refs` is the
// deliberate contrast: argless there means "inherit the carried relationship field".
foreach ( array(
	'leading, argless'   => 'src(terms);key(role)',
	'after a real src'   => 'src(post,9;terms);key(role)',
) as $why => $wire ) {
	$w = t_seam_walk( array( 'A' => $wire ), 'join' );
	check( "P13.5c incomplete term hop skips the slot ($why)", ! isset( $w[1] ), json_encode( $w[1] ?? null ) );
}
$sr_carry = array();
bws_fold_slot_flat_options( bws_fold_parse_slot( 'src(terms);key(role)' ), $sr_carry, true, $sr );
// The reason NAMES the unfinished step. Two steps can be incomplete and they need
// different author-facing nouns, so the slug rides the reason rather than the preview
// guessing — which is what "no taxonomy" on an argless `refs` hop looked like.
check( 'P13.5c an incomplete step reports reason `step:terms`, not `chain`', 'step:terms' === $sr, var_export( $sr, true ) );
$argless_ref = t_seam_walk( array( 'A' => 'src(refs,office);key(a)', 'B' => 'src(refs);key(b)' ), 'join' );
check(
	'P13.5c an argless `refs` hop still INHERITS rather than skipping',
	array( 'src' => 'ref', 'ref' => 'office' ) === array_intersect_key( (array) ( $argless_ref[2] ?? array() ), array( 'src' => 1, 'ref' => 1 ) ),
	json_encode( $argless_ref[2] ?? null )
);
// …but an argless `refs` with NOTHING CARRIED is incomplete, not inherited (#74). The
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
bws_fold_slot_flat_options( bws_fold_parse_slot( 'src(refs);key(a)' ), $orphan_carry, true, $osr );
check( 'P13.5c …and reports `step:refs`, so the preview names the RIGHT missing thing', 'step:refs' === $osr, var_export( $osr, true ) );

// A LEADING term hop is expressible (the ambient entity's terms) and must resolve.
$lead_terms = t_seam_walk( array( 'A' => 'src(terms,category);use(title)' ), 'join' );
// The limit is the walk's materialized EFFECTIVE value (see t_seam_walk): a chain-spelled
// slot whose own chain FANS takes the unlimited default, which is the whole of #60 — the
// identically-spelled base tag `{{text src:terms,category|use:title}}` returns every term.
check( 'P13.5 leading term hop resolves with src unset, and unbounded', array( 'src' => '', 'ref' => '', 'srcTermIn' => 'category', 'use' => 'title', 'key' => '', 'limit' => '0' ) === ( $lead_terms[1] ?? null ), json_encode( $lead_terms[1] ?? null ) );

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
$lim_none_raw   = bws_fold_slot_flat_options( bws_fold_slot_struct( 1, array( 'A' => 'key(a)' ), 'join' ), $lim_none_carry, true );
check( 'P13.6 no limit token → no limit key (the caller default stands)', ! isset( $lim_none_raw['limit'] ), json_encode( $lim_none_raw ) );

// P13.6b — THE ERA THE FLATTEN ERASES, handed back (#60). A slot's own source spelling
// decides its own default exactly as a base tag's does, and the flat triple below cannot
// answer that question: its `src` is a legacy token on every slot, which is why every slot
// used to answer 1 however it was spelled. Measured before the fix:
// `{{text src:terms,department|use:title}}` → two terms, `{{try_text
// A:src(terms,department);use(title)}}` → one.
//
// The predicate is the slot's OWN chain fanning, shared with the migrator's stamp
// (bws_fold_chain_fanning_steps). A slot that only fans by INHERITING an earlier slot's
// source keeps the flat default — see the seam's docblock for why a limit must not carry
// forward — and the mutation that matters is the one that flips `src(same)` to 0, since
// that is what silently unbounds a migrated join slot.
$era_cases = array(
	// folded wire, container, expected default, why
	array( 'src(terms,department);use(title)', 'try', 0, 'chain-spelled and fans → unlimited' ),
	array( 'src(refs,related_staff);key(name)', 'join', 0, 'a refs-spelled slot fans too' ),
	array( 'src(entries,rows);key(a)', 'table', 0, 'a repeater step fans (skipped later, era still reported)' ),
	array( 'src(site);key(org_phone)', 'join', 1, 'a singular chain states no list to bound' ),
	array( 'key(a)', 'join', 1, 'no chain at all' ),
	array( 'src(same);key(b)', 'join', 1, 'inherits a source, so states no bound of its own' ),
	array( 'src(refs);key(b)', 'join', 1, 'an ARGLESS refs step fans only by inheritance' ),
);
foreach ( $era_cases as $i => $case ) {
	list( $wire, $container, $want, $why ) = $case;
	$era_carry   = array();
	$era_skip    = '';
	$era_default = 99;
	bws_fold_slot_flat_options( bws_fold_slot_struct( 1, array( 'A' => $wire ), $container ), $era_carry, bws_fold_is_combining( $container ), $era_skip, $era_default );
	check( "P13.6b [$wire] limit default $want — $why", $want === $era_default, "got: " . var_export( $era_default, true ) );
}
// The LEGACY era, read through the same seam: recovered flat wire keeps the 1 it always
// had, whatever its recovered chain looks like.
$era_carry   = array();
$era_skip    = '';
$era_default = 99;
bws_fold_slot_flat_options( bws_fold_slot_struct( 1, array( 'srcTermIn' => 'department', 'use' => 'title' ), 'try' ), $era_carry, false, $era_skip, $era_default );
check( 'P13.6b legacy flat wire still bounds at 1 through the same seam', 1 === $era_default, var_export( $era_default, true ) );
// The out-param is written BEFORE any early return, same reset contract as $skip_reason:
// a caller reusing one variable across a walk must not read the previous slot's answer.
$era_carry   = array();
$era_default = 0;
bws_fold_slot_flat_options( bws_fold_slot_struct( 1, array( 'A' => 'src(site)' ), 'join' ), $era_carry, true, $era_skip, $era_default );
check( 'P13.6b a SKIPPED slot still reports its default (no leak into the next slot)', 1 === $era_default, var_export( $era_default, true ) );

// P13.7 — malformed folded wire contributes nothing and NEVER falls back to a stale
// legacy sibling (that would render an intent the author replaced).
$bad = t_seam_walk( array( 'A' => 'key(a', '1-key' => 'ignored', 'key' => 'stale' ), 'join' );
check( 'P13.7 malformed folded wire skips the slot', ! isset( $bad[1] ), json_encode( $bad[1] ?? null ) );

// P13.8 — an empty chain at slot ≥2 is a RESET to the ambient entity, not an inherit
// (legacy absence migrates to an explicit `src(same)`, so absence is unambiguous).
$reset = t_seam_walk( array( 'A' => 'src(site);key(a)', 'B' => 'key(b)' ), 'join' );
check( 'P13.8 empty chain at slot 2 resets to current, not inherit', '' === ( $reset[2]['src'] ?? 'X' ), json_encode( $reset[2] ?? null ) );

// ── P14 SELECTING CONTAINER (`try_*`) ───────────────────────────────────────
//
// The same migration-equivalence property as P13.1, for the container whose ABSENCE
// rules are the mirror image: an absent read INHERITS instead of skipping the slot,
// and slot 1 with every axis unset is still an attempt. Three READ SHAPES exist and
// each resolves differently, so each is walked separately:
//   psu  — per-slot `use` enum + key   (text / content / image)
//   psk  — per-slot key, no `use` enum (email / phone)
//   none — no per-slot read at all     (title / permalink / datetime_*)

/**
 * The flat option set the shipped try_ resolver built for one slot, transcribed from
 * class-tag-template-registry.php before the flip. Models the loop's own variables —
 * NOT the `$eval_opts` inheritance around them, which additionally leaked slot 1's
 * bare `srcTermIn` into every later slot's core call (P14.5 covers that fix).
 */
function t_shipped_try_walk( $options, $psk, $psu, $nku = array(), $default_use = '', $max = 5 ) {
	$last_src = 'current';
	$last_ref = '';
	$last_key = '';
	$last_use = '';
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
		$out[ $n ] = array(
			'src'       => $last_src,
			'ref'       => $last_ref,
			'srcTermIn' => $stm,
			'use'       => $psu ? $last_use : null,
			'key'       => '' !== $last_key ? $last_key : null,
		);
	}
	return $out;
}

/** The same walk through the seam, projected onto the shipped shape. */
function t_seam_try_walk( $options, $psk, $psu, $nku = array(), $default_use = '', $max = 5 ) {
	$carry = array( 'src' => '', 'ref' => '', 'use' => $psu ? $default_use : '', 'key' => '' );
	$out   = array();
	for ( $n = 1; $n <= $max; $n++ ) {
		$slot = bws_fold_slot_struct( $n, $options, 'try', $psu );
		if ( null === $slot ) {
			continue;
		}
		$flat = bws_fold_slot_flat_options( $slot, $carry, false );
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
			'src'       => '' === $flat['src'] ? 'current' : $flat['src'],
			'ref'       => $flat['ref'],
			'srcTermIn' => $flat['srcTermIn'],
			'use'       => $psu ? $flat['use'] : null,
			'key'       => '' !== $flat['key'] ? $flat['key'] : null,
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
	'ref hop then inherit'   => array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', '2-src' => 'current', '2-use' => 'key', '2-key' => 'phone' ),
	'term hop at slot 1'     => array( 'srcTermIn' => 'category', 'use' => 'title', '2-use' => 'key', '2-key' => 'b' ),
	'site slot'              => array( 'key' => 'a', '2-src' => 'site', '2-use' => 'key', '2-key' => 'org_phone' ),
	'slot 2 inherits read'   => array( 'key' => 'a', '2-src' => 'site' ),
	'key-only slot 2 drops'  => array( 'key' => 'a', '2-key' => 'b' ),
	'bare tag'               => array(),
	'unset key mode slot 2'  => array( 'key' => 'a', '2-src' => 'site', '2-use' => 'key' ),
);
foreach ( $try_psu_cases as $name => $legacy ) {
	$shipped = t_shipped_try_walk( $legacy, true, true, array( 'title' ), 'key' );
	$folded  = t_seam_try_walk( t_migrate_try( $legacy, true, true ), true, true, array( 'title' ), 'key' );
	check( "P14.1 [psu: $name] migrated folded wire resolves identically", $shipped === $folded, 'legacy:  ' . json_encode( $shipped ) . "\n      folded: " . json_encode( $folded ) );
	$dual = t_seam_try_walk( $legacy, true, true, array( 'title' ), 'key' );
	check( "P14.1 [psu: $name] dual-read of unmigrated wire resolves identically", $shipped === $dual, 'legacy: ' . json_encode( $shipped ) . "\n      dual:   " . json_encode( $dual ) );
}

// The ONE shape where the walks legitimately differ: a slot that inherits on BOTH
// axes. The flat resolver skipped it (nothing new), the seam resolves it as a
// DUPLICATE of its predecessor — output-identical either way, since a duplicate
// returns what the previous slot already returned (or is empty like it). Reachable
// only from hand-written `same` sentinels, because the shipped UI strips them as the
// slot ≥2 default; the fold's own control seeds exactly this shape for a new slot,
// which is why the seam must resolve it rather than treat it as absent.
$all_inherit = t_seam_try_walk( array( 'key' => 'a', '2-src' => 'same', '2-use' => 'same' ), true, true, array( 'title' ), 'key' );
check(
	'P14.1 all-inherit slot 2 resolves as a duplicate of slot 1',
	isset( $all_inherit[2] ) && $all_inherit[1] === $all_inherit[2],
	json_encode( $all_inherit )
);
check(
	'P14.1 …and the flat resolver skipped it, so output is unchanged either way',
	array() === array_diff_key( t_shipped_try_walk( array( 'key' => 'a', '2-src' => 'same', '2-use' => 'same' ), true, true, array( 'title' ), 'key' ), array( 1 => null ) ),
	''
);

// P14.2 — psu with a NO-KEY default read (content: default `content`), where a bare
// tag resolves instead of being dropped by the key gate.
$content_cases = array(
	'bare tag reads content' => array(),
	'excerpt then key'       => array( 'use' => 'excerpt', '2-use' => 'key', '2-key' => 'body' ),
	'key then inherit'       => array( 'use' => 'key', 'key' => 'body', '2-src' => 'ref', '2-ref' => 'office' ),
);
foreach ( $content_cases as $name => $legacy ) {
	$shipped = t_shipped_try_walk( $legacy, true, true, array( 'content', 'excerpt' ), 'content' );
	$folded  = t_seam_try_walk( t_migrate_try( $legacy, true, true ), true, true, array( 'content', 'excerpt' ), 'content' );
	check( "P14.2 [content: $name] migrated folded wire resolves identically", $shipped === $folded, 'legacy:  ' . json_encode( $shipped ) . "\n      folded: " . json_encode( $folded ) );
}
$bare_content = t_seam_try_walk( array(), true, true, array( 'content', 'excerpt' ), 'content' );
check( 'P14.2 a bare selecting tag still ATTEMPTS slot 1', isset( $bare_content[1] ) && 'current' === $bare_content[1]['src'] && 'content' === $bare_content[1]['use'], json_encode( $bare_content ) );

// P14.3 — psk shape (email/phone: per-slot key, NO `use` enum). An empty key at slot
// ≥2 inherits, and no `use(same)` is written for an axis the tag does not have.
$psk_cases = array(
	'two keyed slots'      => array( 'key' => 'a', '2-key' => 'b' ),
	'inherit the key'      => array( 'key' => 'a', '2-src' => 'site' ),
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

// P14.5 — `srcTermIn` does NOT carry forward in a selecting container (it names this
// slot's entity hop). The flat resolver's own variable never carried it; what leaked
// was the bare key riding along in $eval_opts, which the flipped loop now overwrites
// with '' on every slot.
$stm_walk = t_seam_try_walk( array( 'srcTermIn' => 'category', 'use' => 'title', '2-use' => 'key', '2-key' => 'b' ), true, true, array( 'title' ), 'key' );
check( 'P14.5 term hop stays on its own slot', 'category' === ( $stm_walk[1]['srcTermIn'] ?? null ) && '' === ( $stm_walk[2]['srcTermIn'] ?? null ), json_encode( $stm_walk ) );

// P14.6 — slot 1 is never ABSENT in a selecting container, and a combining container
// needs no such exception (its unconfigured read skips the slot one step later).
check( 'P14.6 selecting slot 1 with no keys at all is still a slot', null !== bws_fold_slot_struct( 1, array(), 'try' ), 'null' );
check( 'P14.6 selecting slot 2 with no keys is absent', null === bws_fold_slot_struct( 2, array(), 'try' ), 'not null' );
$empty_join = t_seam_walk( array(), 'join' );
check( 'P14.6 combining with no keys resolves nothing', array() === $empty_join, json_encode( $empty_join ) );

// P14.7 — what the FOLD adds over the flat wire: an explicit per-slot read at slot ≥2
// with no source of its own. Legacy `2-key` alone was DROPPED (FW-51) because a bare
// key could not say whether it meant "override" or "left blank"; `2:key(b)` says it.
$explicit = t_seam_try_walk( array( 'A' => 'key(a)', 'B' => 'key(b)' ), true, true, array( 'title' ), 'key' );
check( 'P14.7 folded key-only slot 2 resolves (the FW-51 ambiguity is gone)', 'b' === ( $explicit[2]['key'] ?? null ) && 'current' === ( $explicit[2]['src'] ?? null ), json_encode( $explicit ) );

// ── P15 THE INHERITED LIMIT (#61) ───────────────────────────────────────────
//
// `src(same)` means the SAME SOURCE, and a limit is one of that source's parameters.
// A slot that inherits its source therefore inherits its bound — which is what lets
// the try_ tag-level `limit` be retired without moving output: the number it used to
// supply to every attempt now reaches an inheriting attempt through the carry.
//
// CONTAINER-SENSITIVE, and the gate is the one the file already draws twice: a
// COMBINING container registered `limit` per slot (`{N}-limit`), so an absent one
// there genuinely means "this slot states none" and must take the default. A
// SELECTING container never had a per-slot limit at all, so absence there can only
// mean inherit. Getting this uniform breaks {{join}} — P13.1's `term hop with limit`
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
		$flat          = bws_fold_slot_flat_options( $slot, $carry, 'try' !== $container, $skip, $limit_default );
		if ( null === $flat ) {
			continue;
		}
		$out[ $n ] = bws_clamp_limit( $flat['limit'] ?? null, $limit_default );
	}
	return $out;
}

$inherit_try = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(same);use(name)' ) );
check( 'P15.1 a selecting slot that inherits its SOURCE inherits its LIMIT', array( 1 => 3, 2 => 3 ) === $inherit_try, json_encode( $inherit_try ) );

// An argless fanning step inherits the same way: it re-states the step but takes its
// relationship field from the carried source, so the bound it fans under is that
// source's. bws_fold_chain_fanning_steps() already treats the two as one shape.
$inherit_argless = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(refs);use(name)' ) );
check( 'P15.2 …and so does an ARGLESS fanning step', array( 1 => 3, 2 => 3 ) === $inherit_argless, json_encode( $inherit_argless ) );

// A slot that states its own source states its own bound — inheriting there would
// silently bound a list the earlier slot knows nothing about.
$own_src = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(terms,category);use(name)' ) );
check( 'P15.3 a slot that states its OWN fanning source takes the chain default, not the carry', array( 1 => 3, 2 => 0 ) === $own_src, json_encode( $own_src ) );

// What is carried is the QUANTITY the earlier slot resolved, including where that slot
// stated nothing — an attempt inheriting `src(refs,office)` reads every office because
// that is what the slot it inherits from reads. Falling back to a default chosen for the
// FLAT wire this slot does not have is the #60 defect one level down.
$no_carry = t_limit_walk( array( 'A' => 'src(refs,office);use(title)', 'B' => 'src(same);use(name)' ) );
check( 'P15.4 an inheriting slot takes the resolved quantity, not its own default', array( 1 => 0, 2 => 0 ) === $no_carry, json_encode( $no_carry ) );

// The same, one era down: the flat spelling's implied 1 is what an inheriting slot gets,
// which is what the shipped legacy walk resolved (P14) and must keep resolving.
$flat_carry = t_limit_walk( array( 'src' => 'ref', 'ref' => 'office', 'use' => 'title', '2-src' => 'same', '2-use' => 'key', '2-key' => 'name' ) );
check( 'P15.4b …and a FLAT-spelled slot carries its implied 1', array( 1 => 1, 2 => 1 ) === $flat_carry, json_encode( $flat_carry ) );

// The guard is literally "my chain does not fan", which is WIDER than inheritance: a slot
// stating its own NON-FANNING root takes the carried number too. Pinned rather than left
// to the comment, because the reason it is harmless is a property rather than an accident
// — a non-fanning source resolves one entity, so any limit over it is inert.
$own_static = t_limit_walk( array( 'A' => 'src(refs,office,limit[3]);use(title)', 'B' => 'src(site);key(org_name)' ) );
check( 'P15.7 a slot stating its own NON-fanning root also takes the carry (inert: one source)', array( 1 => 3, 2 => 3 ) === $own_static, json_encode( $own_static ) );

// COMBINING is the deliberate contrast — and P13.1 `term hop with limit` is the
// shipped-legacy case that would otherwise move.
$inherit_join = t_limit_walk( array( 'A' => 'src(terms,category,limit[3]);use(title)', 'B' => 'src(same);key(blurb)' ), 'join' );
check( 'P15.5 a COMBINING slot does NOT inherit the limit (it owns one per slot)', array( 1 => 3, 2 => 1 ) === $inherit_join, json_encode( $inherit_join ) );

// #61's own equivalence, end to end: the legacy wire the tag-level key served, and the
// migrated wire that no longer has it, must resolve the same quantity at every slot.
$legacy_tag_level = array( 'src' => 'ref', 'ref' => 'office', 'use' => 'title', 'limit' => '3', '2-src' => 'same', '2-use' => 'key', '2-key' => 'name' );
check(
	'P15.6 retiring the tag-level limit does not move what any attempt resolves',
	t_limit_walk( $legacy_tag_level ) === t_limit_walk( t_migrate_try( $legacy_tag_level, true, true ) ),
	'legacy: ' . json_encode( t_limit_walk( $legacy_tag_level ) ) . "\n      migrated: " . json_encode( t_limit_walk( t_migrate_try( $legacy_tag_level, true, true ) ) )
);

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
