<?php
/**
 * Standalone harness for CONTROL order — the order a tag's option controls stack
 * top-to-bottom in the editor panel.
 *
 * CONTROL order IS REGISTRATION order. GB renders `options` as declared and nothing
 * reorders it; the FW-52 normalizer moves the SERIALIZED key order only, inside
 * `setState`. So the registration arrays in base-tags.php / TagTemplateRegistry ARE
 * the panel, and this harness is the only thing that reads them as one. The two axes
 * stay independent (that is FW-52's whole point) — do not "fix" one by touching the
 * other.
 *
 * WHY it exists (1.17.0): option grouping draws a box around the controls that describe
 * one decision (assets/js/option-group.js), and a group draws as ONE box only where its
 * members register CONTIGUOUSLY — the CSS joins adjacent siblings and can see nothing
 * else. That turned control order from a matter of taste into a correctness property,
 * and immediately surfaced a divergence nobody had had a reason to notice: the try_
 * constructor registered its format cluster FIRST (i.e. in serialization order, on the
 * one family that renders one), put `fallback` ahead of `link`, and appended the
 * chain-level `limit`/`sep` dead last — where, being source-group options, they drew
 * their own captionless box at the foot of the panel.
 *
 * The property asserted is CONTIGUITY, not a fixed sequence: the three constructors
 * legitimately differ in where a group sits (term_ leads with format; base and try_ do
 * not), but no constructor may SPLIT a group. Contiguity is exactly what the boxes need
 * and it generalizes to a family this file does not yet enumerate.
 *
 * Contiguity is read over the FULL option sequence, ungrouped controls included (#65) —
 * the CSS run ends at any sibling, so what splits a group is a POSITION, not a group
 * name. Asking the question of the stamped options alone answered a narrower one.
 *
 * Per-tag expected sequences are asserted on top of that for the shapes whose order was
 * wrong, so a regression names itself instead of showing up as a vague box complaint.
 *
 * Run:  php tools/test/control-order-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__, 2 );
define( 'BWS_DYNAMIC_TAGS_PATH', $root . '/' );

// ---------------------------------------------------------------------------
// WP + GB stubs. Only what registration touches — this file never renders a tag.
// ---------------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = null ) { return $s; }
}
foreach ( array( 'add_action', 'add_filter', 'do_action', 'register_rest_route' ) as $fn ) {
	if ( ! function_exists( $fn ) ) {
		eval( "function {$fn}() { return null; }" );
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { return $value; }
}
if ( ! function_exists( 'get_option' ) ) {
	// Settings reads decide which modifier families generate. Empty = every default on.
	function get_option( $name, $default = false ) { return $default; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $k ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $k ) ); }
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
}

/**
 * Capturing stand-in for GB's registrar. Registration is the whole subject here, so the
 * stub keeps every tag's option array in declaration order and answers get_tags() from
 * the same store (the try_ constructor reads it for its own dup-check).
 */
class GenerateBlocks_Register_Dynamic_Tag {
	public static array $tags = array();

	public function __construct( $args ) {
		self::$tags[ $args['tag'] ] = $args;
	}

	public static function get_tags() {
		return self::$tags;
	}
}

class GenerateBlocks_Dynamic_Tags {}
class GenerateBlocks_Dynamic_Tag_Callbacks {
	public static function output( $v, $o = array(), $i = null ) { return $v; }
}

// ---------------------------------------------------------------------------
// Load in the plugin's own order (see bws-gb-dynamic-tags-extensions.php).
// ---------------------------------------------------------------------------

require $root . '/autoload.php';

foreach ( array(
	'includes/helpers/image-helpers.php',
	'includes/helpers/traversal-pipeline.php',
	'includes/helpers/field-helpers.php',
	'includes/helpers/link-helpers.php',
	'includes/helpers/preview-helpers.php',
	'includes/helpers/content-helpers.php',
	'includes/helpers/datetime-helpers.php',
	'includes/helpers/taxonomy-helpers.php',
	'includes/helpers/join-helpers.php',
	'includes/helpers/registration-helpers.php',
	'includes/helpers/serialization-order.php',
	'includes/helpers/slot-fold.php',
	'includes/helpers/slot-fold-compile.php',
	'includes/helpers/slot-fold-migrate.php',
	'includes/tags/base-shared.php',
	'includes/tags/base-tags.php',
	'includes/tags/content-tags.php',
	'includes/tags/image-tags.php',
	'includes/tags/datetime-tags.php',
	'includes/tags/email-tags.php',
	'includes/tags/phone-tags.php',
	'includes/tags/table-tags.php',
	'includes/tags/taxonomy-tags.php',
) as $rel ) {
	require_once $root . '/' . $rel;
}

bws_register_base_tags();
// {{email}} and {{phone}} register from the plugin bootstrap, not from
// bws_register_base_tags() — their MODIFIER TEMPLATES are what base registration pulls in.
// Called here so the two GB tags' own panels are read as well; without them every
// assertion naming `email`/`phone` passes vacuously against an unregistered tag.
bws_register_email_tag();
bws_register_phone_tag();
\BWS\DynamicTags\TagTemplateRegistry::generate_base_try_tags();

$registered = GenerateBlocks_Register_Dynamic_Tag::get_tags();

// ---------------------------------------------------------------------------

$failures = 0;
$count    = 0;

function ok( string $label ): void {
	global $count;
	$count++;
	echo "  ok   {$label}\n";
}

function fail( string $label, $expected, $actual ): void {
	global $failures, $count;
	$count++;
	$failures++;
	echo "  FAIL {$label}\n";
	echo "       expected: " . json_encode( $expected ) . "\n";
	echo "       actual:   " . json_encode( $actual ) . "\n";
}

function assert_same( string $label, $expected, $actual ): void {
	if ( $expected === $actual ) {
		ok( $label );
		return;
	}
	fail( $label, $expected, $actual );
}

/**
 * The group each registered option landed in, in registration order.
 *
 * Reads the `_group` the registration pass STAMPED rather than re-consulting the map:
 * an option that never went through bws_prepare_registration_options() carries no stamp,
 * and that is itself worth catching — an unstamped option renders unboxed no matter
 * what the map says about its name. §1 asserts the stamp directly, since a walk over
 * stamped options cannot notice one that is missing.
 *
 * GROUPED ONLY, so it answers "what order do the boxes come in" (§2) and "which groups
 * have one member" (§4). CONTIGUITY is a question about the FULL sequence and is asked
 * through group_spans() instead.
 *
 * @param array $options Registered options, in declaration order.
 * @return array<int,array{0:string,1:string}> [ option name, group ] for grouped options only.
 */
function grouped_sequence( array $options ): array {
	$seq = array();
	foreach ( $options as $name => $def ) {
		if ( ! empty( $def['_group'] ) ) {
			$seq[] = array( (string) $name, (string) $def['_group'] );
		}
	}
	return $seq;
}

/**
 * Where each group's members sit in the FULL registration sequence.
 *
 * Positions are indexes into the tag's whole option list — ungrouped options included —
 * because that list IS the sibling order the CSS reads. A group is one box iff its
 * members occupy an unbroken span of it; anything else in between, grouped or not,
 * ends the run.
 *
 * @param array $options Registered options, in declaration order.
 * @return array<string,array{first:int,last:int,count:int,names:string[]}> Group => span.
 */
function group_spans( array $options ): array {
	$spans = array();
	$i     = 0;
	foreach ( $options as $name => $def ) {
		$group = ! empty( $def['_group'] ) ? (string) $def['_group'] : '';
		if ( '' !== $group ) {
			if ( ! isset( $spans[ $group ] ) ) {
				$spans[ $group ] = array( 'first' => $i, 'last' => $i, 'count' => 0, 'names' => array() );
			}
			$spans[ $group ]['last']    = $i;
			$spans[ $group ]['names'][] = (string) $name;
			$spans[ $group ]['count']++;
		}
		$i++;
	}
	return $spans;
}

// ===========================================================================
echo "\n§1 Every group's members register CONTIGUOUSLY\n";
// ===========================================================================
// The invariant the boxes stand on. A group that appears, stops, and appears again
// draws TWO boxes for one decision — which is what the author reads as a stray
// control, and is undiagnosable from the CSS end.
//
// A SPAN, not a run of stamps (#65). The break the CSS sees is any sibling between two
// members, and an UNGROUPED option is the most direct way to put one there — a splice
// between `srcTermIn` and `sep` splits the source box on every term_ tag. The earlier
// walk filtered to stamped options first, so the intruder was gone before the check and
// the survivors still read as contiguous; the mutation passed 122/122. Stating the
// property as "the members occupy one unbroken span of the FULL option list" covers both
// intruders at once and needs no exception for an ungrouped option OUTSIDE a group's
// span (a `try_` template's bare `sep`, a lone `linkTo`), which is nobody's break.

foreach ( $registered as $tag => $args ) {
	$options = $args['options'] ?? array();
	$names   = array_keys( $options );
	$broken  = array();

	foreach ( group_spans( $options ) as $group => $span ) {
		if ( $span['last'] - $span['first'] + 1 === $span['count'] ) {
			continue;
		}
		// Name what intervened rather than where the group resumes: the intruder is the
		// thing to move, and it is the half a failure cannot infer from the group name.
		$intruders = array();
		for ( $i = $span['first']; $i <= $span['last']; $i++ ) {
			if ( ! in_array( $names[ $i ], $span['names'], true ) ) {
				$intruders[] = $names[ $i ];
			}
		}
		$broken[] = $group . ' split by ' . implode( ', ', $intruders );
	}

	assert_same( "{{{$tag}}} — no group is split", array(), $broken );
}

// A mapped option that never went through bws_prepare_registration_options() carries no
// stamp. It renders UNBOXED whatever the map says about its name, and it is invisible to
// every group walk in this file — so it splits a run without being counted as a member of
// anything. The span check above catches it only where it happens to land inside another
// group, which is why the stamp is asserted on its own.
$mapped = array_keys( bws_option_visual_groups() );

foreach ( $registered as $tag => $args ) {
	$unstamped = array();
	foreach ( $args['options'] ?? array() as $name => $def ) {
		if ( in_array( (string) $name, $mapped, true ) && empty( $def['_group'] ) ) {
			$unstamped[] = (string) $name;
		}
	}
	assert_same( "{{{$tag}}} — every mapped option carries its stamp", array(), $unstamped );
}

// ===========================================================================
echo "\n§2 try_ registers in canonical control order\n";
// ===========================================================================
// source → format → link → fallback, with the attempts standing in for the source and
// the tag-level field reads inside it (same as every base tag: limit/sep and the field
// keys precede format). `fallback` is ungrouped, so it is asserted by position below
// rather than by group.

$try_groups = array();
foreach ( $registered as $tag => $args ) {
	if ( 0 !== strpos( $tag, 'try_' ) ) {
		continue;
	}
	$order = array();
	foreach ( grouped_sequence( $args['options'] ?? array() ) as $pair ) {
		if ( end( $order ) !== $pair[1] ) {
			$order[] = $pair[1];
		}
	}
	$try_groups[ $tag ] = $order;
}

assert_same(
	'try_ tags were generated',
	true,
	count( $try_groups ) > 0
);

foreach ( $try_groups as $tag => $order ) {
	// The canonical sequence with the groups this tag does not carry removed — a
	// subsequence check, so `try_permalink` (no link, no format) passes on the same rule
	// as `try_datetime_range` (all four).
	$canonical = array_values( array_intersect( array( 'source', 'field', 'format', 'link' ), $order ) );
	assert_same( "{{{$tag}}} — group sequence", $canonical, $order );
}

// The two that were actually wrong, pinned by exact option list so a regression names
// the option rather than the group.
assert_same(
	'{{try_text}} — full option order',
	array( 'A', 'B', 'C', 'D', 'E', 'sep', 'linkTo', 'linkKey', 'newTab', 'fallback' ),
	array_keys( $registered['try_text']['options'] ?? array() )
);

assert_same(
	'{{try_datetime_range}} — full option order',
	array(
		'A', 'B', 'C', 'D', 'E',
		'startKey', 'startTimeKey', 'endKey', 'endTimeKey',
		// The two checkboxes register showCurrentYear FIRST. tag-reference.md §datetime
		// listed them the other way round from 1.16.0 until this harness read the
		// registration.
		//
		// The doc was corrected to the CODE here, which INVERTS the standing rule —
		// tag-reference.md is authoritative and drift normally resolves by changing the
		// code to match it. Allowed as a one-off (user, 2026-08-06) because the doc row
		// stated no preference and neither order is better, so the shipped one is what
		// authors already see. Not precedent: the next drift moves the code.
		'as', 'rangeSep', 'format', 'timeSep', 'showCurrentYear', 'showMidnight',
		'linkTo', 'linkKey', 'newTab',
		'fallback',
	),
	array_keys( $registered['try_datetime_range']['options'] ?? array() )
);

// ===========================================================================
echo "\n§3 fallback is last wherever it is registered\n";
// ===========================================================================
// Ungrouped and global, on every constructor. It sat AHEAD of the link cluster on try_
// until 1.17.0 because the trailing merge carried it, which is the same edit that put
// limit/sep at the bottom — one cause, two symptoms.

foreach ( $registered as $tag => $args ) {
	$keys = array_keys( $args['options'] ?? array() );
	if ( ! in_array( 'fallback', $keys, true ) ) {
		continue;
	}
	assert_same( "{{{$tag}}} — fallback registers last", 'fallback', end( $keys ) );
}

// ===========================================================================
echo "\n§4 A group's LEAD is present wherever the group is\n";
// ===========================================================================
// A lone non-lead member renders BARE (the opt-out in option-group.js), so a group that
// registers without its lead and without a second member shows no box at all. `link` is
// the deliberate exception: it has no lead, by design — the box appears only once a link
// is configured, which is the one group that is genuinely optional.

$leads = array();
foreach ( bws_option_visual_groups() as $name => $spec ) {
	if ( $spec['lead'] ) {
		$leads[ $spec['group'] ][] = $name;
	}
}

// DELIBERATE bare controls: tag + group pairs where the lone member is meant to render
// with no box. The four `try_` list templates are the whole list, and they became bare
// in 1.17.0 when the tag-level `limit` retired (#62) and left `sep` alone in the source
// group. There is no box to want: a try_ tag's SOURCE is its attempts, and a folded slot
// key is ungrouped on purpose (it draws its own boxes inside one value), so a border
// around the separator would caption nothing. Contrast the base tags, where `sep` shares
// the source box with the chain control that leads it.
//
// Asserted as EXERCISED below, so a stale entry fails instead of quietly widening the
// rule the rest of this section enforces.
$bare_by_design = array(
	'try_text'  => array( 'source' ),
	'try_title' => array( 'source' ),
	'try_email' => array( 'source' ),
	'try_phone' => array( 'source' ),
);
$exercised      = array();

foreach ( $registered as $tag => $args ) {
	$options = $args['options'] ?? array();
	$members = array();
	foreach ( grouped_sequence( $options ) as $pair ) {
		$members[ $pair[1] ][] = $pair[0];
	}
	foreach ( $members as $group => $names ) {
		if ( 'link' === $group || count( $names ) > 1 ) {
			continue;
		}
		if ( in_array( $group, $bare_by_design[ $tag ] ?? array(), true ) ) {
			$exercised[ $tag ][] = $group;
			continue;
		}
		$has_lead = (bool) array_intersect( $names, $leads[ $group ] ?? array() );
		assert_same( "{{{$tag}}} — lone `{$group}` member is a lead", true, $has_lead );
	}
}

// Compared SORTED, both levels: the exception list is a set, and registration order is a
// different property with its own sections above. An `===` on the raw arrays passes only
// while the try_ constructor happens to emit these four in the literal's order, so a
// Catalog reorder would fail this on a question it is not asking.
ksort( $bare_by_design );
ksort( $exercised );
foreach ( $exercised as &$exercised_groups ) {
	sort( $exercised_groups );
}
unset( $exercised_groups );
foreach ( $bare_by_design as &$expected_groups ) {
	sort( $expected_groups );
}
unset( $expected_groups );

assert_same( 'every bare-by-design exception is exercised', $bare_by_design, $exercised );

// ===========================================================================
echo "\n§5 The tag-level `limit` is UNREGISTERED wherever the source is a chain\n";
// ===========================================================================
// This section is also where the file owns which options a family registers AT ALL,
// not just their order. That matters because an absent control is invisible in
// rendered output — no matrix row can catch a regression here, only this harness can.
//
// A LIMIT IS STATED WHERE THE SOURCE IS STATED (#62). A chain-authoring tag states its
// source as steps, so its limits live on the steps and the tag-level control retires —
// with one fanning step it is the same knob as that step's own limit, and with two it
// slices the flattened walk at a position set by fan-out widths the author cannot see.
// `try_` retires it for the same reason: its attempts author chains too.
//
// UNREGISTERED, not gated: the mount migrator rewrites flat wire to a chain before the
// panel paints, so a predicate naming the flat tokens would be effectively unreachable.
//
// Removing the OPTION does not remove the VALUE — GB seeds `extraTagParams` from the
// parsed tag string, not from the registry, so a stored `limit` still round-trips and
// still renders (bws_clamp_limit reads it untouched). That is what keeps unmigrated flat
// wire and hand-edited wire meaning what they say (ADR 0004).
//
// `sep` STAYS on every one of them: it joins the final printed list, so it has no "which
// step" question to answer and is genuinely tag-level.

// SWEPT, not listed. The set is decided by the CONTROL a tag authors its source with —
// a chain (`bws-src-chain`) or folded attempts (`bws-slot-fold`) — which is the property
// the rule is actually about, so a NEW tag joins the sweep by registering a chain source
// and a tag family that keeps the flat select (`term_*`, `{{call}}`) stays out of it
// without being named. A hand list would have passed a `limit` re-added to `{{content}}`,
// `{{permalink}}` or `{{image}}` while the docs claim no tag registers one.
$chain_authoring = array();
foreach ( $registered as $tag => $args ) {
	$options = $args['options'] ?? array();
	$folded  = false;
	foreach ( $options as $opt ) {
		if ( 'bws-slot-fold' === ( $opt['type'] ?? '' ) ) {
			$folded = true;
			break;
		}
	}
	if ( 'bws-src-chain' === ( $options['src']['type'] ?? '' ) || $folded ) {
		$chain_authoring[] = $tag;
	}
}

// The sweep is only worth its name if it actually reaches the families the ticket names,
// so the floor is asserted rather than assumed: an empty or half-loaded registry would
// otherwise sail through every "does not register" check below.
foreach ( array( 'text', 'title', 'email', 'phone', 'datetime_single', 'datetime_range', 'try_text' ) as $named ) {
	assert_same( "{{{$named}}} — is in the swept set", true, in_array( $named, $chain_authoring, true ) );
}

foreach ( $chain_authoring as $tag ) {
	$keys = array_keys( $registered[ $tag ]['options'] ?? array() );
	assert_same( "{{{$tag}}} — registers no tag-level `limit`", false, in_array( 'limit', $keys, true ) );
}

// `sep` was SPLIT from the pair, not dropped with it — asserted separately because the two
// halves fail differently and a copy-paste removal of both should name itself. The base
// six are the ticket's own list; the `try_` half is DERIVED from the template flag that
// decides it (`try_list_options`), so this file holds no third copy of that set.
$sep_expected = array( 'text', 'title', 'email', 'phone', 'datetime_single', 'datetime_range' );
foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
	if ( ! empty( $tpl['supports_try'] ) && ! empty( $tpl['try_list_options'] ) ) {
		$sep_expected[] = 'try_' . $tpl['key'];
	}
}

foreach ( $chain_authoring as $tag ) {
	$keys = array_keys( $registered[ $tag ]['options'] ?? array() );
	assert_same(
		"{{{$tag}}} — `sep` registration unchanged",
		in_array( $tag, $sep_expected, true ),
		in_array( 'sep', $keys, true )
	);
}

// ===========================================================================
echo "\n§6 The always-serialized `as` still carries the default that serializes it\n";
// ===========================================================================
// Not an ordering property, but this harness is the only one that sees all three
// constructors, and all three register a `bws-as-size` `as` (base {{image}}, the image
// modifier template's leading + trailing sets, which term_image and try_image are built
// from). GB writes an untouched option only if it SEEDED it, and it seeds from non-empty
// `default`s at tag-select time — so on this option the default is not a convenience, it
// is the whole always-serialize mechanism (docs/tag-reference.md §`as` serialization
// opt-out). v1.16.0's fold dropped it believing the composite wrote on mount; it writes
// on change, so every {{image}} authored under 1.16.0 has no `as` at all. Nothing failed:
// the read seam defaults to url/full, so the tags render right and only the wire is
// silent — which is exactly why it wants an assertion rather than an eyeball.

$seen_as_size = 0;
foreach ( $registered as $tag => $args ) {
	foreach ( $args['options'] ?? array() as $name => $opt ) {
		if ( 'bws-as-size' !== ( $opt['type'] ?? '' ) ) {
			continue;
		}
		++$seen_as_size;
		assert_same( "{{{$tag}}} — `{$name}` seeds its default", 'url,full', $opt['default'] ?? null );
	}
}
assert_same( 'every image family member registered an as+size control', true, $seen_as_size >= 3 );

echo "\n§7 A slot's STEP OFFER is the base tag's (#104)\n";

// A slot's SOURCE *is* a base tag's source (CONTEXT.md I16), so what a slot may STEP with
// is what a base tag may step with. This is a REGISTRATION fact and no other harness sees
// it: bws_build_fold_slot_options() is asserted directly elsewhere against whatever
// `steps` it is handed, so a container that kept the narrow list would pass there while
// shipping an editor that cannot author a chain the renderer now resolves.
//
// It read `['terms']` on both containers while the flatten stood — a slot re-spelled as a
// flat triple held ONE relationship step, which was already a SOURCE row, so a second one
// had no spelling and offering it would have authored wire that skipped. The seam hands
// the whole chain on now (#104) and the arms dispatch on what it resolves to (#103).
//
// `rows` stays OFF every offer, base and slot alike, and that is not an oversight: no
// arm assembles a repeater row, so offering it would author a chain that renders nothing.
// {{table}} is where that gap closes, with its own arm.
$base_offer = null;
foreach ( $registered as $tag => $args ) {
	foreach ( $args['options'] ?? array() as $name => $opt ) {
		if ( 'src' === $name && isset( $opt['fold']['offer'] ) ) {
			$base_offer = $opt['fold']['offer'];
			break 2;
		}
	}
}
assert_same( 'a base tag offers refs then terms', array( 'refs', 'terms' ), $base_offer );

$slot_offers = array();
foreach ( $registered as $tag => $args ) {
	foreach ( $args['options'] ?? array() as $name => $opt ) {
		if ( 'bws-slot-fold' !== ( $opt['type'] ?? '' ) ) {
			continue;
		}
		$slot_offers[ $tag ] = $opt['fold']['offer'] ?? null;
		break;
	}
}
assert_same( 'every multislot container registers a slot offer', true, count( $slot_offers ) >= 10 );
foreach ( $slot_offers as $tag => $offer ) {
	assert_same( "{{{$tag}}} — slot offer equals the base tag's", $base_offer, $offer );
}

echo "\n§8 takes_first_usable agrees across all three constructors (ADR 0007)\n";

// The failure this pins: a Limit results control suppressed on a tag whose render
// still applies the limit, or the reverse. The capability has ONE source — the
// template record — and two editor surfaces derive from it (the base chain option's
// fold config; every try_ slot's fold config). This is the one harness that sees all
// three registration constructors at once, so the agreement is asserted here.

$tags = GenerateBlocks_Register_Dynamic_Tag::get_tags();

// The record itself: exactly the three collapsing templates declare it.
$declared = array();
foreach ( \BWS\DynamicTags\TagTemplateRegistry::get_modifier_templates() as $tpl ) {
	if ( ! empty( $tpl['takes_first_usable'] ) ) {
		$declared[] = $tpl['key'];
	}
}
sort( $declared );
assert_same( 'the record: exactly content/image/permalink declare the capability', array( 'content', 'image', 'permalink' ), $declared );

// Base surface: the chain option's fold flag ⇔ the record, on every base tag that
// registers the chain control.
foreach ( $tags as $tag => $args ) {
	$src = $args['options']['src'] ?? null;
	if ( ! is_array( $src ) || 'bws-src-chain' !== ( $src['type'] ?? '' ) ) {
		continue;
	}
	$expect = in_array( $tag, array( 'content', 'permalink', 'image' ), true );
	assert_same(
		"{{{$tag}}} — chain option fold takesFirstUsable matches the record",
		$expect,
		! empty( $src['fold']['takesFirstUsable'] )
	);
}

// try_ surface: every slot option's fold flag ⇔ the record, per template.
foreach ( $tags as $tag => $args ) {
	if ( 0 !== strpos( $tag, 'try_' ) ) {
		continue;
	}
	$expect = in_array( $tag, array( 'try_content', 'try_permalink', 'try_image' ), true );
	foreach ( $args['options'] as $key => $opt ) {
		if ( 'bws-slot-fold' !== ( $opt['type'] ?? '' ) ) {
			continue;
		}
		assert_same(
			"{{{$tag}}} slot {$key} — fold takesFirstUsable matches the record",
			$expect,
			! empty( $opt['fold']['takesFirstUsable'] )
		);
	}
}

// The third constructor (register_modifier / term_) authors no chain and no folded
// slot, so it has no surface to suppress — asserted so a future term_ chain control
// cannot pick the capability up by accident without a row here changing.
foreach ( $tags as $tag => $args ) {
	if ( 0 !== strpos( $tag, 'term_' ) ) {
		continue;
	}
	foreach ( $args['options'] as $key => $opt ) {
		assert_same(
			"{{{$tag}}} {$key} — no fold carries takesFirstUsable on the flat-select family",
			false,
			! empty( $opt['fold']['takesFirstUsable'] )
		);
	}
}

// ---------------------------------------------------------------------------

echo "\n";
if ( $failures ) {
	echo "FAILED: {$failures} of {$count}\n";
	exit( 1 );
}
echo "PASSED: {$count}/{$count}\n";
exit( 0 );
