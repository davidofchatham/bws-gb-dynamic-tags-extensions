<?php
/**
 * Standalone unit harness for the FW-56/57 fold MIGRATOR and the option-migration
 * CASCADE engine it exposed.
 *
 * Loads the real files (no transcribed copies): includes/helpers/slot-fold-migrate.php,
 * includes/classes/class-migration-registry.php and the template registry whose
 * try_slot_axes() decides which keys are slot-level. What the sibling harnesses do NOT
 * cover and this one does:
 *
 *   M1 CASCADE   — a matching entry that produces NO CHANGE must not end the cascade.
 *                  This is an ENGINE fix, and the trigger is real: the as+size fold
 *                  entry matches on `as`, survives its own transform, and used to stop
 *                  every entry registered after it (the fold entry among them).
 *   M2 READ SHAPE — try_slot_axes() per shape: which axes are per-slot, which stay at
 *                  tag level. `limit` is tag-level on EVERY try_ template and per-slot
 *                  on join, which is why the exclusion list is per-container data.
 *   M3 CONTAINER  — the config is DERIVED from the registered templates, so a template
 *                  whose read shape changes cannot leave a stale copy behind.
 *   M4 SURFACE    — the trigger/strip key set: tag-level axes excluded at EVERY slot
 *                  position, not just slot 1 (a dead `3-key` on a chain-only template
 *                  must stay dead, not become live wire).
 *   M5 REWRITE    — legacy keys leave, folded values arrive, tag-level options survive,
 *                  an already-folded slot wins over its legacy siblings, and the emitted
 *                  key order is canonical (so a migrated tag shows no spurious diff the
 *                  first time it is opened).
 *   M6 END TO END — the registered entry through apply_option_migration(), i.e. what the
 *                  converter actually runs.
 *
 * Migration EQUIVALENCE (does migrated wire resolve like the legacy wire it replaced?)
 * lives in tools/test/slot-fold-test.php §P13.1/§P14, which now drives the shipped
 * migrator rather than a local transcription of it.
 *
 * Run:  php tools/test/fold-migration-test.php
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

// The WP surface the loaded files touch when CALLED (nothing runs at load time).
if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
// The depth-0 base migrator reads the chain through the COMPILER, so it comes along.
require __DIR__ . '/../../includes/helpers/slot-fold-compile.php';
require __DIR__ . '/../../includes/helpers/slot-fold-migrate.php';
require __DIR__ . '/../../includes/classes/class-migration-registry.php';
require __DIR__ . '/../../includes/classes/class-tag-template-registry.php';

use BWS\DynamicTags\MigrationRegistry;
use BWS\DynamicTags\TagTemplateRegistry;

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

// ── M1 — the CASCADE engine: a no-op entry must not halt it ─────────────────

// Two entries on one tag. The first matches and changes nothing (the as+size shape);
// the second must still fire. Registration order is the application order.
MigrationRegistry::register(
	array(
		'type'               => 'option',
		'match_tag'          => 'cascade_tag',
		'match_any_options'  => array( 'as' ),
		'new_tag'            => 'cascade_tag',
		'transform_callback' => static fn( string $s ): string => $s,
		'label'              => 'no-op',
	)
);
MigrationRegistry::register(
	array(
		'type'              => 'option',
		'match_tag'         => 'cascade_tag',
		'match_any_options' => array( 'old' ),
		'new_tag'           => 'cascade_tag',
		'option_renames'    => array( 'old' => 'new' ),
		'label'             => 'old → new',
	)
);

check(
	'M1.1 a no-op entry does not stop a later entry from firing',
	'{{cascade_tag as:url|new:1}}' === MigrationRegistry::apply_option_migration( 'cascade_tag', '{{cascade_tag as:url|old:1}}' ),
	MigrationRegistry::apply_option_migration( 'cascade_tag', '{{cascade_tag as:url|old:1}}' )
);
check(
	'M1.2 nothing left to do → unchanged, and no runaway loop',
	'{{cascade_tag as:url|new:1}}' === MigrationRegistry::apply_option_migration( 'cascade_tag', '{{cascade_tag as:url|new:1}}' ),
	MigrationRegistry::apply_option_migration( 'cascade_tag', '{{cascade_tag as:url|new:1}}' )
);
check(
	'M1.3 an entry matching nothing leaves the string alone',
	'{{cascade_tag key:x}}' === MigrationRegistry::apply_option_migration( 'cascade_tag', '{{cascade_tag key:x}}' ),
	MigrationRegistry::apply_option_migration( 'cascade_tag', '{{cascade_tag key:x}}' )
);

// A THREE-entry chain where the third only becomes reachable after the second runs —
// the transform changes which entries match, so matches are re-derived each iteration.
MigrationRegistry::register(
	array(
		'type'              => 'option',
		'match_tag'         => 'chain_tag',
		'match_any_options' => array( 'a' ),
		'new_tag'           => 'chain_tag',
		'option_renames'    => array( 'a' => 'b' ),
		'label'             => 'a → b',
	)
);
MigrationRegistry::register(
	array(
		'type'              => 'option',
		'match_tag'         => 'chain_tag',
		'match_any_options' => array( 'b' ),
		'new_tag'           => 'chain_tag',
		'option_renames'    => array( 'b' => 'c' ),
		'label'             => 'b → c',
	)
);
check(
	'M1.4 cascade re-derives matches, so a chain completes in one call',
	'{{chain_tag c:1}}' === MigrationRegistry::apply_option_migration( 'chain_tag', '{{chain_tag a:1}}' ),
	MigrationRegistry::apply_option_migration( 'chain_tag', '{{chain_tag a:1}}' )
);

// The finder returns EVERY match in registration order, which is what lets the cascade
// step past a no-op entry. (The singular first-match sibling was deleted in 1.17.0: it had
// no caller once this one existed, and this case was the only thing keeping it alive.)
$all = MigrationRegistry::find_option_migrations( 'cascade_tag', array( 'as', 'old' ) );
check( 'M1.5 finder returns every match in registration order', array( 'no-op', 'old → new' ) === array_column( $all, 'label' ), json_encode( array_column( $all, 'label' ) ) );

// ── M2 — read shapes: which axes a try_ template owns per slot ──────────────

$shape_psu  = TagTemplateRegistry::try_slot_axes( array( 'try_per_slot_use' => true, 'try_per_slot_key' => true ) );
$shape_psk  = TagTemplateRegistry::try_slot_axes( array( 'try_per_slot_key' => true ) );
$shape_none = TagTemplateRegistry::try_slot_axes( array() );
$shape_list = TagTemplateRegistry::try_slot_axes( array( 'try_per_slot_use' => true, 'try_list_options' => true ) );

check( 'M2.1 enum+picker shape owns use and key per slot', array( 'use', 'key' ) === $shape_psu['slot_read'], json_encode( $shape_psu ) );
check( 'M2.2 picker-alone shape owns key only; use stays tag-level', array( 'key' ) === $shape_psk['slot_read'] && in_array( 'use', $shape_psk['tag_level'], true ), json_encode( $shape_psk ) );
check( 'M2.3 chain-only shape owns no read axis; both stay tag-level', array() === $shape_none['slot_read'] && array( 'use', 'key', 'limit' ) === $shape_none['tag_level'], json_encode( $shape_none ) );
check(
	'M2.4 limit is tag-level on EVERY try_ template, list options or not',
	in_array( 'limit', $shape_psu['tag_level'], true ) && in_array( 'limit', $shape_list['tag_level'], true ),
	json_encode( array( $shape_psu['tag_level'], $shape_list['tag_level'] ) )
);

// ── M3 — container config, DERIVED from the registered templates ────────────

TagTemplateRegistry::register_modifier_template(
	array(
		'key'                   => 'text',
		'supports_try'          => true,
		'try_per_slot_use'      => true,
		'try_per_slot_key'      => true,
		'try_list_options'      => true,
	)
);
TagTemplateRegistry::register_modifier_template(
	array(
		'key'              => 'phone',
		'supports_try'     => true,
		'try_per_slot_key' => true,
	)
);
TagTemplateRegistry::register_modifier_template(
	array(
		'key'          => 'datetime_single',
		'supports_try' => true,
	)
);
// Not try-capable: must not appear in the multislot list.
TagTemplateRegistry::register_modifier_template( array( 'key' => 'content_only' ) );

$join_cfg = bws_fold_migration_container( 'join' );
$text_cfg = bws_fold_migration_container( 'try_text' );
$dts_cfg  = bws_fold_migration_container( 'try_datetime_single' );

check( 'M3.1 join: combining, per-slot limit kept, ten slots', array() === $join_cfg['tag_level'] && true === $join_cfg['combining'] && 10 === $join_cfg['max'], json_encode( $join_cfg ) );
check( 'M3.2 try_text: selecting, per_slot_use derived, limit excluded', false === $text_cfg['combining'] && true === $text_cfg['per_slot_use'] && array( 'limit' ) === $text_cfg['tag_level'], json_encode( $text_cfg ) );
check( 'M3.3 try_datetime_single: read axes stay tag-level', array( 'use', 'key', 'limit' ) === $dts_cfg['tag_level'] && false === $dts_cfg['per_slot_use'], json_encode( $dts_cfg ) );
check( 'M3.4 a non-container tag has no config (callback no-ops)', null === bws_fold_migration_container( 'text' ), 'not null' );
check( 'M3.5 an unknown try_ tag has no config', null === bws_fold_migration_container( 'try_nonesuch' ), 'not null' );

$multislot = bws_fold_migration_multislot_tags();
check( 'M3.6 multislot list = join + every try-capable template', array( 'join', 'try_text', 'try_phone', 'try_datetime_single' ) === $multislot, json_encode( $multislot ) );
check( 'M3.7 {{table}} is never listed (it ships folded, so it has no legacy wire)', ! in_array( 'table', $multislot, true ), json_encode( $multislot ) );

// ── M4 — the trigger/strip key surface ─────────────────────────────────────

$dts_keys = bws_fold_migration_slot_keys( $dts_cfg );
check(
	'M4.1 tag-level axes are excluded at EVERY slot position, not just slot 1',
	! in_array( 'key', $dts_keys, true ) && ! in_array( '3-key', $dts_keys, true ) && ! in_array( '2-use', $dts_keys, true ),
	json_encode( $dts_keys )
);
check( 'M4.2 …while the source axes stay, bare and prefixed', in_array( 'src', $dts_keys, true ) && in_array( '5-srcTermIn', $dts_keys, true ), json_encode( $dts_keys ) );
check( 'M4.3 join keeps limit as a slot key at every position', in_array( 'limit', bws_fold_migration_slot_keys( $join_cfg ), true ) && in_array( '10-limit', bws_fold_migration_slot_keys( $join_cfg ), true ), json_encode( bws_fold_migration_slot_keys( $join_cfg ) ) );
check( 'M4.4 the key set is exactly axes × slots for the axes owned', count( bws_fold_migration_slot_keys( $text_cfg ) ) === 5 * ( count( BWS_FOLD_FLAT_AXES ) - 1 ), count( bws_fold_migration_slot_keys( $text_cfg ) ) );

// The MATCH surface is a different question from the MAPPER surface, and the one key
// where they diverge is the one this entry now retires. A tag whose slots are already
// folded has no legacy slot key left, so without this the shape #61 names never matches.
check( 'M4.5 a selecting container MATCHES on the tag-level limit it retires', in_array( 'limit', bws_fold_migration_match_keys( $text_cfg ), true ), json_encode( bws_fold_migration_match_keys( $text_cfg ) ) );
check( 'M4.5 …while the MAPPER surface still excludes it (folding it deletes slot 1\'s read)', ! in_array( 'limit', bws_fold_migration_slot_keys( $text_cfg ), true ), json_encode( bws_fold_migration_slot_keys( $text_cfg ) ) );
check( 'M4.6 combining adds nothing — `limit` is already a slot key there', bws_fold_migration_slot_keys( $join_cfg ) === bws_fold_migration_match_keys( $join_cfg ), '' );

// ── M5 — the rewrite ───────────────────────────────────────────────────────

// A try_text tag with a tag-level limit and two configured slots.
$t5 = bws_fold_migrate_slots(
	array(
		'src'    => 'ref',
		'ref'    => 'office',
		'key'    => 'name',
		'limit'  => '3',
		'2-use'  => 'key',
		'2-key'  => 'role',
		'sep'    => ', ',
	),
	$text_cfg
);
check( 'M5.1 legacy slot keys are gone', array() === array_intersect( array_keys( $t5 ), array( 'src', 'ref', 'key', '2-use', '2-key' ) ), json_encode( $t5 ) );
// The TAG-LEVEL `limit` reaches a FANNING slot (#60). It is not a bound across attempts —
// it was each attempt's own default — so it has to land where that attempt states its
// source, or the flat era's materialized 1 overwrites the author's number. Slot B gets
// nothing: it fans only by inheriting A's source, so it has no list of its own to bound —
// the seam carries A's bound to it along with A's source (slot-fold-test.php §P15), which
// is what lets the tag-level key itself be retired here (#61, §M10).
check( 'M5.2 folded values arrive, carrying the tag-level limit into the FANNING attempt', 'src(refs,office,limit[3]);key(name)' === ( $t5['A'] ?? null ) && 'src(same);key(role)' === ( $t5['B'] ?? null ), json_encode( $t5 ) );
// …and with NO tag-level limit it is the flat era's implied 1 that lands, on the fanning
// step only — a slot that fans by inheritance states no bound of its own.
$t5b = bws_fold_migrate_slots(
	array(
		'src'   => 'ref',
		'ref'   => 'office',
		'key'   => 'name',
		'2-use' => 'key',
		'2-key' => 'role',
	),
	$text_cfg
);
// Slot 1 reads the tag-level key DIRECTLY (its prefix is ''), so the selecting-container
// fallback is reachable only from a fanning slot ≥2 — which is where dropping it goes
// unnoticed. Without the fallback slot C takes the materialized 1 and the author's 4 is
// silently overwritten, on the one shape that can show it.
$t5c = bws_fold_migrate_slots(
	array(
		'key'     => 'a',
		'limit'   => '4',
		'3-src'   => 'ref',
		'3-ref'   => 'office',
		'3-use'   => 'title',
	),
	$text_cfg
);
check( 'M5.2c a FANNING slot ≥2 takes the tag-level limit, not the materialized 1', 'src(refs,office,limit[4]);use(title)' === ( $t5c['C'] ?? null ), json_encode( $t5c ) );
check( 'M5.2c …and a NON-fanning slot beside it still takes nothing', 'key(a)' === ( $t5c['A'] ?? null ), json_encode( $t5c ) );

check( 'M5.2b no tag-level limit → the flat era default lands on the fanning step alone', 'src(refs,office,limit[1]);key(name)' === ( $t5b['A'] ?? null ) && 'src(same);key(role)' === ( $t5b['B'] ?? null ), json_encode( $t5b ) );
check( 'M5.3 the tag-level limit does NOT survive — it was pushed down (#61, §M10)', ! array_key_exists( 'limit', $t5 ), json_encode( $t5 ) );
check( 'M5.4 unrelated tag-level options survive', ', ' === ( $t5['sep'] ?? null ), json_encode( $t5 ) );
// Canonical order: a FOLDED slot ranks as its slot's source, so tag-level (slot 0) keys
// lead and the slots follow in ordinal order. Matching what the editor's normalizer writes
// is what keeps a migrated tag from showing a spurious diff the first time it is opened
// and saved — and the M7 twin block below is what holds the two sides to one answer.
// (Until 2026-08-04 the slots LED, because all-digit keys are JS array-index properties
// that GB's `Object.entries(extraTagParams)` emits first whatever the normalizer builds.
// Capitals gave the ordering back to the sort; see includes/helpers/slot-fold.php.)
check( 'M5.5 emitted keys are canonically ordered (tag-level source, then folded slots)', array( 'sep', 'A', 'B' ) === array_map( 'strval', array_keys( $t5 ) ), json_encode( array_keys( $t5 ) ) );

// Nothing to migrate → null, which is the callback's no-op contract.
check( 'M5.6 an already-folded tag migrates to nothing', null === bws_fold_migrate_slots( array( 'A' => 'key(a)', 'B' => 'key(b)' ), $text_cfg ), 'not null' );
check( 'M5.7 a tag whose only source-group key is TAG-level migrates to nothing', null === bws_fold_migrate_slots( array( 'key' => 'event_date', 'use' => 'key' ), $dts_cfg ), json_encode( bws_fold_migrate_slots( array( 'key' => 'event_date', 'use' => 'key' ), $dts_cfg ) ) );

// Half-applied migration / hand-edit: a folded value beside legacy siblings. The folded
// value is the author's later intent (the render dual-read agrees), so the legacy keys
// are DROPPED, never merged into it.
$mixed = bws_fold_migrate_slots( array( 'A' => 'key(new)', 'src' => 'ref', 'ref' => 'office', 'key' => 'old' ), $text_cfg );
check( 'M5.8 an already-folded slot wins over its legacy siblings', array( 'A' => 'key(new)' ) === array_map( 'strval', $mixed ), json_encode( $mixed ) );

// The FW-51 shape: a selecting slot ≥2 whose only content was a key renders nothing
// today, so it maps to nothing — but its dead keys still leave the wire.
$fw51 = bws_fold_migrate_slots( array( 'key' => 'a', '2-key' => 'b' ), $text_cfg );
check( 'M5.9 a slot that maps to nothing still has its legacy keys stripped', array( 'A' ) === array_map( 'strval', array_keys( $fw51 ) ), json_encode( $fw51 ) );

// Join: `limit` IS a slot axis here, and slot 10 is reachable.
$j5 = bws_fold_migrate_slots( array( 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3', '10-key' => 'last', 'mode' => 'template' ), $join_cfg );
check( 'M5.10 join folds its per-slot limit onto the fanning step', 'src(terms,category,limit[3]);use(title)' === ( $j5['A'] ?? null ), json_encode( $j5 ) );
check( 'M5.11 join slot 10 folds (BWS_JOIN_MAX_SLOTS, not five)', 'src(same);key(last)' === ( $j5['J'] ?? null ), json_encode( $j5 ) );
check( 'M5.12 join tag-level assembly options lead the folded slots', array( 'mode', 'A', 'J' ) === array_map( 'strval', array_keys( $j5 ) ), json_encode( array_keys( $j5 ) ) );

// A slot naming a RETIRED source token (#56) is declined WHOLE — the one case where "not
// folded" and "not stripped" come apart. The fold cannot rewrite the token faithfully: the
// relationship field lives in `rel`, which is not a fold axis, or in a `key` that may be
// tag-level and already filtered out. The converter's own entry does it beforehand from
// the undifferentiated option array; the MOUNT path has no entry chain, so without this it
// would fold `related_post` into a chain root that resolves to nothing and store the tag
// differently from the converter. Leaving the legacy keys in place is what lets the
// converter still fix it afterwards.
$retired = bws_fold_migrate_slots( array( 'key' => 'name', '2-src' => 'related_post', '2-use' => 'key', '2-key' => 'role' ), $text_cfg );
check( 'M5.13 a retired-token slot is not folded', ! isset( $retired['B'] ), json_encode( $retired ) );
check(
	'M5.14 and its legacy keys SURVIVE, so the converter can still reach it',
	isset( $retired['2-src'], $retired['2-use'], $retired['2-key'] ),
	json_encode( $retired )
);
check( 'M5.15 the other slots still fold normally', 'key(name)' === ( $retired['A'] ?? null ), json_encode( $retired ) );
check(
	'M5.16 a tag whose ONLY slot is retired migrates to nothing (no-op, not an empty rewrite)',
	null === bws_fold_migrate_slots( array( 'src' => 'related_post', 'key' => 'name' ), $text_cfg ),
	json_encode( bws_fold_migrate_slots( array( 'src' => 'related_post', 'key' => 'name' ), $text_cfg ) )
);

// ── M6 — end to end, through the registered entry ──────────────────────────

MigrationRegistry::register(
	array(
		'type'               => 'option',
		'match_tag'          => 'try_text',
		'match_any_options'  => bws_fold_migration_match_keys( $text_cfg ),
		'new_tag'            => 'try_text',
		'transform_callback' => 'bws_migrate_src_chain_slots',
		'label'              => 'fold',
	)
);

check(
	'M6.1 the registered entry folds a stored tag string',
	'{{try_text A:src(refs,office,limit[1]);key(name)|B:src(same);key(role)}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text src:ref|ref:office|key:name|2-use:key|2-key:role}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text src:ref|ref:office|key:name|2-use:key|2-key:role}}' )
);
check(
	'M6.2 running it twice is a fixpoint (the no-op contract)',
	'{{try_text A:src(refs,office,limit[1]);key(name)|B:src(same);key(role)}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text A:src(refs,office,limit[1]);key(name)|B:src(same);key(role)}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text A:src(refs,office,limit[1]);key(name)|B:src(same);key(role)}}' )
);
check(
	'M6.3 a tag the entry does not match is untouched',
	'{{try_phone 2-key:mobile}}' === MigrationRegistry::apply_option_migration( 'try_phone', '{{try_phone 2-key:mobile}}' ),
	MigrationRegistry::apply_option_migration( 'try_phone', '{{try_phone 2-key:mobile}}' )
);
// A free-form value carrying the grammar's own separators must survive the round trip
// through GB's `|`/`:` layer — the fallback text rides along tag-level here, but a
// migrated slot's `label`/`format` would take the escaping path.
// GB does not trim option values (parse_options splits and stores the remainder
// verbatim), so a trailing space inside the braces belongs to the last value. An
// authored `sep:, ` must survive the fold — this entry matches nearly every join/try_
// tag, so an rtrim here would rewrite separators site-wide.
check(
	'M6.5 a trailing-space value on the last option survives the rewrite',
	'{{try_text sep:, |A:key(name)}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|sep:, }}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|sep:, }}' )
);
check(
	'M6.4 tag-level free-form values are not disturbed by the fold',
	'{{try_text A:key(name)|fallback:Name: TBA}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|fallback:Name: TBA}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|fallback:Name: TBA}}' )
);
// #61's own shape, end to end: slots already folded, only the retiring key left. It has
// no legacy slot key at all, so it reaches the transform only because the MATCH surface
// names `limit` (§M4.5) — and the acceptance criterion is written against exactly this
// tag string.
check(
	'M6.6 an already-folded tag with a tag-level limit still reaches the entry',
	'{{try_text A:src(refs,office,limit[3]);use(title)|B:key(role)}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text A:src(refs,office);use(title)|B:key(role)|limit:3}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text A:src(refs,office);use(title)|B:key(role)|limit:3}}' )
);
check(
	'M6.7 …and it is a fixpoint too',
	'{{try_text A:src(refs,office,limit[3]);use(title)|B:key(role)}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text A:src(refs,office,limit[3]);use(title)|B:key(role)}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text A:src(refs,office,limit[3]);use(title)|B:key(role)}}' )
);

// ── M7 — the JS TWIN: the editor's mount migrator must rewrite identically ──
//
// The converter (this file's subject) and the editor's mount migrator are ONE migration
// in two languages, and they are COMPLEMENTARY rather than redundant: the scanner reads
// post_content only, so a block widget is reachable only on mount, while a draft nobody
// opens is reachable only by the scanner. A divergence between them therefore does not
// show up as one path being wrong — it shows up as the same tag stored two ways
// depending on which path found it first. A PHP-only harness structurally cannot see
// that, so one shared corpus goes through both.
//
// KEY ORDER IS HALF THE PROPERTY, which is why the comparison is ordered pairs rather
// than an associative map: an all-digit key is a JS array-index property, enumerated
// ahead of every string key no matter how the object was built, so the folded slots
// LEAD — and PHP's canonical order had to adopt that or every mount would rewrite what
// the converter had just written.
//
// A missing `node` FAILS rather than skips: a silent pass here would hide exactly the
// drift the twin exists to catch (house rule, see CLAUDE.md §Development).
$corpus_file = __DIR__ . '/fold-migration-corpus.json';
$corpus      = json_decode( (string) file_get_contents( $corpus_file ), true );
check( 'M7.0 the shared corpus parses', is_array( $corpus ) && ! empty( $corpus['cases'] ), $corpus_file );
check( 'M7.0b the corpus covers DEPTH-0 base source too', ! empty( $corpus['baseSrc'] ), count( (array) ( $corpus['baseSrc'] ?? array() ) ) . ' base cases' );

/** Ordered key/value pairs, or null — key ORDER is half the property. */
$pairs_of = static function ( $out ) {
	if ( null === $out ) {
		return null;
	}
	$pairs = array();
	foreach ( $out as $key => $value ) {
		$pairs[] = array( (string) $key, (string) $value );
	}
	return $pairs;
};

// DEPTH-0 — the base tag's own source triple. Built here rather than folded into the
// slot loop because the two migrators take different inputs: a container config versus
// none at all.
$php_base = array();
foreach ( (array) ( $corpus['baseSrc'] ?? array() ) as $case ) {
	$php_base[] = $pairs_of( bws_fold_migrate_base_src( (array) $case['options'] ) );
}

$php_doc = array();
foreach ( (array) ( $corpus['cases'] ?? array() ) as $case ) {
	// The corpus speaks the EDITOR's vocabulary (`flatAxes` — what registration ships
	// to the control). The migrator wants the complement, which is the direction the
	// shipped registration already goes.
	$cfg = array(
		'container'    => ! empty( $case['conf']['combining'] ) ? 'join' : 'try',
		'combining'    => ! empty( $case['conf']['combining'] ),
		'per_slot_use' => ! empty( $case['conf']['perSlotUse'] ),
		'max'          => (int) $case['conf']['max'],
		'tag_level'    => array_values( array_diff( BWS_FOLD_FLAT_AXES, (array) $case['conf']['flatAxes'] ) ),
	);
	$out = bws_fold_migrate_slots( (array) $case['options'], $cfg );
	if ( null === $out ) {
		$php_doc[] = null;
		continue;
	}
	$pairs = array();
	foreach ( $out as $key => $value ) {
		$pairs[] = array( (string) $key, (string) $value );
	}
	$php_doc[] = $pairs;
}

$raw    = (string) shell_exec( 'node ' . escapeshellarg( __DIR__ . '/fold-migration-driver.js' ) . ' 2>&1' );
$js_doc = json_decode( $raw, true );
if ( ! is_array( $js_doc ) ) {
	check( 'M7.1 the JS driver ran (node on PATH, migrate layer exported)', false, substr( trim( $raw ), 0, 400 ) );
} else {
	check( 'M7.1 the JS driver ran (node on PATH, migrate layer exported)', true );
	$js_slots = (array) ( $js_doc['cases'] ?? array() );
	$js_base  = (array) ( $js_doc['baseSrc'] ?? array() );
	check( 'M7.2 both sides answered every corpus case', count( $php_doc ) === count( $js_slots ), count( $php_doc ) . ' php vs ' . count( $js_slots ) . ' js' );
	check( 'M7.2b both sides answered every BASE case', count( $php_base ) === count( $js_base ), count( $php_base ) . ' php vs ' . count( $js_base ) . ' js' );

	foreach ( $php_base as $i => $php_case ) {
		$label   = $corpus['baseSrc'][ $i ]['label'] ?? "base case {$i}";
		$js_case = array_key_exists( $i, $js_base ) ? $js_base[ $i ] : '(missing)';
		check(
			'M7.4 base twin agrees — ' . $label,
			$php_case === $js_case,
			'php: ' . json_encode( $php_case ) . "
      js:  " . json_encode( $js_case )
		);
	}

	$js_doc = $js_slots;
	foreach ( $php_doc as $i => $php_case ) {
		$label = $corpus['cases'][ $i ]['label'] ?? "case {$i}";
		// array_key_exists, not `??`: a no-op case is legitimately NULL on both sides, and
		// `?? false` would report agreement as a divergence.
		$js_case = array_key_exists( $i, $js_doc ) ? $js_doc[ $i ] : '(missing)';
		check(
			'M7.3 twin agrees — ' . $label,
			$php_case === $js_case,
			'php: ' . json_encode( $php_case ) . "\n      js:  " . json_encode( $js_case )
		);
	}
}

// ── M8 — {{join}} format-token escape (the letters' latent regression) ─────
//
// NOT a token re-spell: `%1` and `%A` resolve to the same internal token, forever. What
// migrates is the LITERAL. Before 1.17.0 a `%` not followed by a slot DIGIT passed
// through bws_join_wire_format() untouched, so `10%APR` was legal stored wire; with the
// letters tokenizing it becomes slot 1. join-template-test.php pins that the tokenizer
// now does this; these pin that the migration is what keeps stored text intact.
//
// THE PROPERTY THAT MATTERS IS THE ERA GATE, and the first draft of this block is what
// found it missing: `%A` is literal-or-token by ERA, not by inspection, so a
// content-based test would escape an INTENDED token and break working output. A folded
// slot key cannot predate the letters, so its presence is the decidable discriminator.

require __DIR__ . '/../../includes/helpers/join-helpers.php';

MigrationRegistry::register(
	array(
		'type'               => 'option',
		'match_tag'          => 'join',
		'match_any_options'  => array( 'format' ),
		'new_tag'            => 'join',
		'transform_callback' => 'bws_migrate_join_format_escape',
		'label'              => 'join format escape',
	)
);

$m8 = static fn( string $tag ): string => MigrationRegistry::apply_option_migration( 'join', $tag );

// PRE-LETTERS wire: flat slot keys, digit tokens, and a literal `%APR` that 1.15.0
// rendered verbatim. Every `%`+letter here is a literal, so every one is escaped.
check(
	'M8.1 a literal % before a slot letter is escaped on pre-letters wire',
	'{{join mode:template|format:10%%APR from %1|key:rate}}' === $m8( '{{join mode:template|format:10%APR from %1|key:rate}}' ),
	$m8( '{{join mode:template|format:10%APR from %1|key:rate}}' )
);
// THE ERA GATE. Same format text, but the tag carries a folded slot key — so it was
// written after the letters went live and `%A` is a TOKEN. Escaping it here would turn a
// working tag into literal text, which is why no content test can be used.
check(
	'M8.2 an intended letter TOKEN on folded wire is left alone',
	'{{join mode:template|format:10%APR from %A|A:key(rate)}}' === $m8( '{{join mode:template|format:10%APR from %A|A:key(rate)}}' ),
	$m8( '{{join mode:template|format:10%APR from %A|A:key(rate)}}' )
);
// FIXPOINT, from the lookbehind alone (this tag has no folded key, so the gate lets it
// through and only the escape state stops a second pass).
check(
	'M8.3 running it twice changes nothing',
	'{{join mode:template|format:10%%APR from %1|key:rate}}' === $m8( '{{join mode:template|format:10%%APR from %1|key:rate}}' ),
	$m8( '{{join mode:template|format:10%%APR from %1|key:rate}}' )
);
// The token set is bounded by the CONTAINER, not by A–Z: `%K` is literal in a 10-slot
// join in BOTH eras, so escaping it would rewrite text the renderer never looks at.
check(
	'M8.4 a letter past the container maximum is left alone',
	'{{join mode:template|format:%K of %%A|key:rate}}' === $m8( '{{join mode:template|format:%K of %A|key:rate}}' ),
	$m8( '{{join mode:template|format:%K of %A|key:rate}}' )
);
check(
	'M8.5 a digit-token format is untouched (both alphabets resolve alike)',
	'{{join mode:template|format:%1 (%2)|key:a|2-key:b}}' === $m8( '{{join mode:template|format:%1 (%2)|key:a|2-key:b}}' ),
	$m8( '{{join mode:template|format:%1 (%2)|key:a|2-key:b}}' )
);
check(
	'M8.6 a trailing lone % is not a token and is not escaped',
	'{{join mode:template|format:up 10%|key:a}}' === $m8( '{{join mode:template|format:up 10%|key:a}}' ),
	$m8( '{{join mode:template|format:up 10%|key:a}}' )
);
// A separator-mode join has no `format`, so the entry must not match at all.
check(
	'M8.7 separator-mode join is untouched',
	'{{join A:key(a)|valueSep: / }}' === $m8( '{{join A:key(a)|valueSep: / }}' ),
	$m8( '{{join A:key(a)|valueSep: / }}' )
);

// ── M9 — the legacy limit lands on the STEPS, at depth 0 ─────────────────────
//
// The M7 twin proves the two languages AGREE; it cannot prove either is right, and it
// carries no expected values by design. These are the expectations.
//
// The rule under test (bws_fold_chain_apply_legacy_limit): a base tag's flat source
// implied a limit of 1, chain wire defaults to unlimited, so the respelling must carry
// the old default across — onto the steps, never as a tag-level `limit`. THE EARLIER
// FANNING STEPS MATTER: per-step limits are per-input and multiply, so `N` on the last
// one alone yields N per parent rather than the N total the flat spelling meant.

$base = static function ( array $options ) {
	$out = bws_fold_migrate_base_src( $options );
	return ( null === $out ) ? null : $out;
};

$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name' ) );
check(
	'M9.1 an implied limit lands on the one fanning step, not on the tag',
	'refs,office,limit(1)' === ( $m9['src'] ?? null ) && ! isset( $m9['limit'] ),
	json_encode( $m9 )
);

$m9 = $base( array( 'srcTermIn' => 'department', 'use' => 'title' ) );
check(
	'M9.2 a terms-only source is limited the same way',
	'terms,department,limit(1)' === ( $m9['src'] ?? null ) && ! isset( $m9['limit'] ),
	json_encode( $m9 )
);

$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'department' ) );
check(
	'M9.3 TWO fanning steps are BOTH limited (the product is the ceiling)',
	'refs,office,limit(1);terms,department,limit(1)' === ( $m9['src'] ?? null ) && ! isset( $m9['limit'] ),
	json_encode( $m9 )
);

$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'limit' => '3' ) );
check(
	'M9.4 an author-stated limit MOVES onto the step and leaves no tag-level key',
	'refs,office,limit(3)' === ( $m9['src'] ?? null ) && ! isset( $m9['limit'] ),
	json_encode( $m9 )
);

// The shape with no faithful per-input mapping, pinned to the CHOSEN one: 1 on the
// earlier step bounds the total at N, where leaving it unlimited would give N per parent.
// It picks the same element as the flat walk unless parent 1 has fewer than N children —
// the documented residual hole, zero instances in either surveyed database.
$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'srcTermIn' => 'department', 'limit' => '3' ) );
check(
	'M9.5 an author-stated limit over two fanning steps holds the EARLIER one at 1',
	'refs,office,limit(1);terms,department,limit(3)' === ( $m9['src'] ?? null ) && ! isset( $m9['limit'] ),
	json_encode( $m9 )
);

// 0 and -1 both already mean unlimited, which is what chain wire defaults to. Nothing to
// carry, and the author's own token stays as written.
foreach ( array( '0', '-1' ) as $unlimited ) {
	$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'limit' => $unlimited ) );
	check(
		"M9.6 an explicit limit:{$unlimited} writes no step limit and stays where it is",
		'refs,office' === ( $m9['src'] ?? null ) && $unlimited === ( $m9['limit'] ?? null ),
		json_encode( $m9 )
	);
}

// bws_clamp_limit's is_numeric guard already gives a non-numeric value the default, so
// it MEANS absent — but deleting an author's text on that basis is a bigger move than
// this rewrite is entitled to, and leaving it renders identically.
$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'limit' => 'lots' ) );
check(
	'M9.7 a non-numeric limit is treated as absent but is NOT consumed',
	'refs,office,limit(1)' === ( $m9['src'] ?? null ) && 'lots' === ( $m9['limit'] ?? null ),
	json_encode( $m9 )
);

// An EMPTY value is absent by the same reading, and is not deleted for the same reason:
// the rewrite consumes a key only when it has moved the author's number onto a step.
$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'limit' => '' ) );
check(
	'M9.7b an empty limit: is treated as absent and is NOT consumed',
	'refs,office,limit(1)' === ( $m9['src'] ?? null ) && '' === ( $m9['limit'] ?? null ),
	json_encode( $m9 )
);

// A magnitude the two languages cannot hold identically is the unlimited case, never a
// materialized step limit: PHP's (int) saturates at PHP_INT_MAX where JS reaches
// Infinity, so materializing it would write a different number in each language. The
// twin corpus carries the same case, which is what proves they agree rather than that
// PHP alone is right.
$m9 = $base( array( 'src' => 'ref', 'ref' => 'office', 'limit' => '99999999999999999999' ) );
check(
	'M9.7c an unrepresentable magnitude writes no step limit and stays where it is',
	'refs,office' === ( $m9['src'] ?? null ) && '99999999999999999999' === ( $m9['limit'] ?? null ),
	json_encode( $m9 )
);

// An argless fanning step is DROPPED by the compiler, so the chain does not fan and a
// limit on it would be inert noise. The old tag rendered one value because its source
// resolved one entity, not because anything bounded it.
$m9 = $base( array( 'src' => 'ref', 'key' => 'name' ) );
check(
	'M9.8 an orphan src:ref respells with no limit at all',
	'refs' === ( $m9['src'] ?? null ) && ! isset( $m9['limit'] ),
	json_encode( $m9 )
);

// The author-conversion path can limit a step in the same commit that converts. That is a
// stated per-step intent, and materializing a default on top of it would overwrite a
// decision with a guess — so the whole mapping stands down.
$pre = array(
	array( 'slug' => 'refs', 'arg' => 'office', 'limit' => '2', 'extra' => array() ),
	array( 'slug' => 'terms', 'arg' => 'department', 'limit' => null, 'extra' => array() ),
);
$m9 = bws_fold_chain_apply_legacy_limit( $pre, null );
check(
	'M9.9 a chain that already carries a step limit is left entirely alone',
	$pre === $m9['chain'] && false === $m9['consumed'],
	json_encode( $m9 )
);

// The N×M families ride this same mapping rather than a second copy of it. Their wire
// expectations live with the entries themselves, in
// tools/test/related-post-src-migration-test.php §R6 — one owner per assertion.

// ── M10 — a SELECTING container's TAG-LEVEL limit is retired (#61) ───────────
//
// `try_`'s tag-level `limit` was never a bound ACROSS attempts — it was each attempt's
// own default. Once a slot's source is a chain, nothing says which step such a number
// aims at and there is no per-step lever to aim it with, so it stops existing: the
// value is pushed into the slots that consumed it and the key is deleted.
//
// #60 already pushed the value into LEGACY slots (the mapper's view). What is new here
// is the other two halves — a slot that is ALREADY FOLDED takes it too, and the key
// goes. {{join}} is untouched throughout: its `limit` has always been a SLOT axis.

check(
	'M10.1 the tag-level key is GONE once its number has been pushed down',
	! array_key_exists( 'limit', (array) bws_fold_migrate_slots( array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '3' ), $text_cfg ) ),
	json_encode( bws_fold_migrate_slots( array( 'src' => 'ref', 'ref' => 'office', 'key' => 'name', 'limit' => '3' ), $text_cfg ) )
);

// The shape the acceptance criterion names: slots ALREADY folded, the key still there.
// Reachable by hand-edit (ADR 0004) and from any tag the pre-#61 rule already migrated.
$m10 = bws_fold_migrate_slots(
	array(
		'A'     => 'src(refs,office);use(title)',
		'B'     => 'src(terms,department);key(name)',
		'limit' => '3',
	),
	$text_cfg
);
check(
	'M10.2 an already-folded FANNING slot takes the number onto its own last fanning step',
	'src(refs,office,limit[3]);use(title)' === ( $m10['A'] ?? null ) && 'src(terms,department,limit[3]);key(name)' === ( $m10['B'] ?? null ),
	json_encode( $m10 )
);
check( 'M10.2 …and the tag-level key goes with it', ! array_key_exists( 'limit', (array) $m10 ), json_encode( $m10 ) );

// A slot with NO fanning step had nothing to bound, so it takes nothing — and a slot
// that fans only by INHERITING gets the bound through the seam's carry instead
// (slot-fold-test.php §P15), which is what keeps this rewrite output-neutral.
$m10 = bws_fold_migrate_slots( array( 'A' => 'key(a)', 'B' => 'src(same);key(b)', 'limit' => '3' ), $text_cfg );
check(
	'M10.3 a non-fanning and an INHERITING slot are both left as written',
	'key(a)' === ( $m10['A'] ?? null ) && 'src(same);key(b)' === ( $m10['B'] ?? null ) && ! array_key_exists( 'limit', (array) $m10 ),
	json_encode( $m10 )
);

// The author's own step limit wins, exactly as it does at depth 0 (M9.9): the tag-level
// number is a DEFAULT, and a default never overwrites a stated value.
$m10 = bws_fold_migrate_slots( array( 'A' => 'src(refs,office,limit[2]);use(title)', 'limit' => '3' ), $text_cfg );
check(
	'M10.4 a slot that already pins its own limit is untouched (the key still goes)',
	'src(refs,office,limit[2]);use(title)' === ( $m10['A'] ?? null ) && ! array_key_exists( 'limit', (array) $m10 ),
	json_encode( $m10 )
);

// `0`/`-1` are unlimited, which is what chain wire already defaults to — nothing to push
// down. The key is still retired: leaving it would leave the container arm falling back
// to a key this release says does not exist.
$m10 = bws_fold_migrate_slots( array( 'A' => 'src(refs,office);use(title)', 'limit' => '0' ), $text_cfg );
check(
	'M10.5 an explicit unlimited pushes nothing down and is still retired',
	'src(refs,office);use(title)' === ( $m10['A'] ?? null ) && ! array_key_exists( 'limit', (array) $m10 ),
	json_encode( $m10 )
);

// A non-numeric value is not a number to push anywhere. Deleting the author's text on
// that basis is a bigger move than this rewrite is entitled to (M9.7's reasoning), and
// bws_clamp_limit's is_numeric guard already renders it as the default either way.
$m10 = bws_fold_migrate_slots( array( 'A' => 'src(refs,office);use(title)', 'limit' => 'lots' ), $text_cfg );
check( 'M10.6 a non-numeric tag-level limit is NOT consumed', null === $m10, json_encode( $m10 ) );

// Nothing to migrate is still nothing: a tag with no slot at all has nowhere to push a
// number to, so the rewrite declines rather than emitting an empty diff.
check( 'M10.7 a slotless tag is a no-op', null === bws_fold_migrate_slots( array( 'limit' => '3' ), $text_cfg ), 'not null' );

// A slot naming a RETIRED source token is declined WHOLE, so it is not a slot to push
// into either. Consuming the key on its account would leave the tag half-treated — its
// legacy keys still there for the converter to fix later, but the bound they need
// already deleted — and the fix would then land a `limit[1]` where the author wrote 3.
// That is the one way this rewrite can move output on a tag it deliberately did not
// touch, and both twins share the ordering, so §M7 structurally cannot catch it.
check(
	'M10.9 a tag whose ONLY slot is DECLINED keeps its tag-level limit',
	null === bws_fold_migrate_slots( array( 'src' => 'second_related_post', 'key' => 'name', 'limit' => '3' ), $text_cfg ),
	json_encode( bws_fold_migrate_slots( array( 'src' => 'second_related_post', 'key' => 'name', 'limit' => '3' ), $text_cfg ) )
);
// …but a declined slot does not block a real one: the number still reaches the slot that
// can hold it, and the key goes because it was delivered.
$m10 = bws_fold_migrate_slots( array( 'src' => 'second_related_post', 'key' => 'name', '2-src' => 'ref', '2-ref' => 'office', '2-use' => 'title', 'limit' => '3' ), $text_cfg );
check(
	'M10.9b …while a declined slot beside a real one still lets the number land',
	'src(refs,office,limit[3]);use(title)' === ( $m10['B'] ?? null ) && ! array_key_exists( 'limit', (array) $m10 ) && isset( $m10['src'], $m10['key'] ),
	json_encode( $m10 )
);

// {{join}} owns `limit` PER SLOT, so its bare `limit` IS slot 1's own legacy axis. It is
// therefore never pushed anywhere: beside an already-folded slot 1 it is a legacy sibling
// the folded value outranks (M5.8's rule), and slot 1's chain keeps whatever it states.
$m10 = bws_fold_migrate_slots( array( 'A' => 'src(terms,category);use(title)', 'limit' => '3' ), $join_cfg );
check(
	'M10.8 a COMBINING container pushes nothing down — its bare limit is slot 1\'s own axis',
	array( 'A' => 'src(terms,category);use(title)' ) === array_map( 'strval', (array) $m10 ),
	json_encode( $m10 )
);
// …and where slot 1 is legacy it folds as that slot's own limit, unchanged by #61.
check(
	'M10.8b …and a legacy combining slot still folds its own limit onto its own step',
	'src(terms,category,limit[3]);use(title)' === ( bws_fold_migrate_slots( array( 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3', '2-key' => 'b' ), $join_cfg )['A'] ?? null ),
	json_encode( bws_fold_migrate_slots( array( 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3', '2-key' => 'b' ), $join_cfg ) )
);

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
