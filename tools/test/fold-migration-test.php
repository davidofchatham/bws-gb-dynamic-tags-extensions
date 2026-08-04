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

require __DIR__ . '/../../includes/helpers/serialization-order.php';
require __DIR__ . '/../../includes/helpers/slot-fold.php';
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

// find_option_migration() (singular) must keep returning the FIRST match — the scanner
// and the admin preview read it for the entry LABEL, not to apply anything.
$first = MigrationRegistry::find_option_migration( 'cascade_tag', array( 'as', 'old' ) );
$all   = MigrationRegistry::find_option_migrations( 'cascade_tag', array( 'as', 'old' ) );
check( 'M1.5 singular finder still returns the first match', 'no-op' === ( $first['label'] ?? '' ), json_encode( $first['label'] ?? null ) );
check( 'M1.6 plural finder returns every match in registration order', array( 'no-op', 'old → new' ) === array_column( $all, 'label' ), json_encode( array_column( $all, 'label' ) ) );

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
check( 'M4.4 the key set is exactly axes × slots for the axes owned', count( bws_fold_migration_slot_keys( $text_cfg ) ) === 5 * ( count( BWS_FOLD_LEGACY_AXES ) - 1 ), count( bws_fold_migration_slot_keys( $text_cfg ) ) );

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
check( 'M5.2 folded values arrive', 'src(refs,office);key(name)' === ( $t5['1'] ?? null ) && 'src(same);key(role)' === ( $t5['2'] ?? null ), json_encode( $t5 ) );
check( 'M5.3 the tag-level limit survives, unfolded', '3' === ( $t5['limit'] ?? null ), json_encode( $t5 ) );
check( 'M5.4 unrelated tag-level options survive', ', ' === ( $t5['sep'] ?? null ), json_encode( $t5 ) );
// Canonical order leads with the FOLDED slots, then the tag-level keys in group order.
// The lead is forced, not chosen: an all-digit key is a JS array-index property, so GB's
// `Object.entries(extraTagParams)` emits it first no matter what the editor's normalizer
// builds — and matching it is what keeps a migrated tag from showing a spurious diff the
// first time it is opened and saved.
check( 'M5.5 emitted keys are canonically ordered (folded slots, then tag-level)', array( '1', '2', 'limit', 'sep' ) === array_map( 'strval', array_keys( $t5 ) ), json_encode( array_keys( $t5 ) ) );

// Nothing to migrate → null, which is the callback's no-op contract.
check( 'M5.6 an already-folded tag migrates to nothing', null === bws_fold_migrate_slots( array( '1' => 'key(a)', '2' => 'key(b)', 'limit' => '2' ), $text_cfg ), 'not null' );
check( 'M5.7 a tag whose only source-group key is TAG-level migrates to nothing', null === bws_fold_migrate_slots( array( 'key' => 'event_date', 'use' => 'key' ), $dts_cfg ), json_encode( bws_fold_migrate_slots( array( 'key' => 'event_date', 'use' => 'key' ), $dts_cfg ) ) );

// Half-applied migration / hand-edit: a folded value beside legacy siblings. The folded
// value is the author's later intent (the render dual-read agrees), so the legacy keys
// are DROPPED, never merged into it.
$mixed = bws_fold_migrate_slots( array( '1' => 'key(new)', 'src' => 'ref', 'ref' => 'office', 'key' => 'old' ), $text_cfg );
check( 'M5.8 an already-folded slot wins over its legacy siblings', array( '1' => 'key(new)' ) === array_map( 'strval', $mixed ), json_encode( $mixed ) );

// The FW-51 shape: a selecting slot ≥2 whose only content was a key renders nothing
// today, so it maps to nothing — but its dead keys still leave the wire.
$fw51 = bws_fold_migrate_slots( array( 'key' => 'a', '2-key' => 'b' ), $text_cfg );
check( 'M5.9 a slot that maps to nothing still has its legacy keys stripped', array( '1' ) === array_map( 'strval', array_keys( $fw51 ) ), json_encode( $fw51 ) );

// Join: `limit` IS a slot axis here, and slot 10 is reachable.
$j5 = bws_fold_migrate_slots( array( 'srcTermIn' => 'category', 'use' => 'title', 'limit' => '3', '10-key' => 'last', 'mode' => 'template' ), $join_cfg );
check( 'M5.10 join folds its per-slot limit onto the fanning step', 'src(terms,category,limit[3]);use(title)' === ( $j5['1'] ?? null ), json_encode( $j5 ) );
check( 'M5.11 join slot 10 folds (BWS_JOIN_MAX_SLOTS, not five)', 'src(same);key(last)' === ( $j5['10'] ?? null ), json_encode( $j5 ) );
check( 'M5.12 join tag-level assembly options survive, after the folded slots', array( '1', '10', 'mode' ) === array_map( 'strval', array_keys( $j5 ) ), json_encode( array_keys( $j5 ) ) );

// ── M6 — end to end, through the registered entry ──────────────────────────

MigrationRegistry::register(
	array(
		'type'               => 'option',
		'match_tag'          => 'try_text',
		'match_any_options'  => bws_fold_migration_slot_keys( $text_cfg ),
		'new_tag'            => 'try_text',
		'transform_callback' => 'bws_migrate_src_chain_slots',
		'label'              => 'fold',
	)
);

check(
	'M6.1 the registered entry folds a stored tag string',
	'{{try_text 1:src(refs,office);key(name)|2:src(same);key(role)}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text src:ref|ref:office|key:name|2-use:key|2-key:role}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text src:ref|ref:office|key:name|2-use:key|2-key:role}}' )
);
check(
	'M6.2 running it twice is a fixpoint (the no-op contract)',
	'{{try_text 1:src(refs,office);key(name)|2:src(same);key(role)}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text 1:src(refs,office);key(name)|2:src(same);key(role)}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text 1:src(refs,office);key(name)|2:src(same);key(role)}}' )
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
	'{{try_text 1:key(name)|sep:, }}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|sep:, }}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|sep:, }}' )
);
check(
	'M6.4 tag-level free-form values are not disturbed by the fold',
	'{{try_text 1:key(name)|fallback:Name: TBA}}' === MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|fallback:Name: TBA}}' ),
	MigrationRegistry::apply_option_migration( 'try_text', '{{try_text key:name|fallback:Name: TBA}}' )
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

$php_doc = array();
foreach ( (array) ( $corpus['cases'] ?? array() ) as $case ) {
	// The corpus speaks the EDITOR's vocabulary (`legacyAxes` — what registration ships
	// to the control). The migrator wants the complement, which is the direction the
	// shipped registration already goes.
	$cfg = array(
		'container'    => ! empty( $case['conf']['combining'] ) ? 'join' : 'try',
		'combining'    => ! empty( $case['conf']['combining'] ),
		'per_slot_use' => ! empty( $case['conf']['perSlotUse'] ),
		'max'          => (int) $case['conf']['max'],
		'tag_level'    => array_values( array_diff( BWS_FOLD_LEGACY_AXES, (array) $case['conf']['legacyAxes'] ) ),
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
	check( 'M7.2 both sides answered every corpus case', count( $php_doc ) === count( $js_doc ), count( $php_doc ) . ' php vs ' . count( $js_doc ) . ' js' );
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

echo "\n$pass passed, $fail failed\n";
exit( $fail > 0 ? 1 : 0 );
