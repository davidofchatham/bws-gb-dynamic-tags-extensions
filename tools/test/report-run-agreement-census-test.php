<?php
/**
 * Census harness for the Migration Tool's report-equals-run rule (#119), on the REAL registrations.
 *
 * migration-report-test.php pins the rule on synthetic entries. This drives it over the entries
 * the plugin ships: for every tag string, TagConverter::classify_tag() says `convert` exactly
 * when TagConverter::migrate_string() changes it, and its `string` is what the run writes.
 * It also counts the strings the OLD report would have listed and the run leaves alone (a deprecated
 * name whose transform changes nothing, or an option entry whose key matches and whose transform
 * moves nothing), which is the number this change exists to take to zero.
 *
 * TWO INPUTS. The synthetic half always runs: a bare `{{name}}` for every registered deprecated
 * tag name, and it must contain at least one conversion or it would pass by comparing nothing.
 * A census (`census.jsonl` from bin/harvest-tags.sh, one `tag_string` per line) runs when its path
 * is the first argument or in BWS_CENSUS; it holds client wire and is never committed, so without
 * one the harness prints a visible skip notice for that half.
 *
 * NOT COVERED. The `term_` modifier entries registered by the bootstrap need WordPress; the modifier
 * templates here are stand-ins, as in migration-order-test.php. The ownership guard sees no
 * foreign plugin, so no `declined` verdict arises; that is converter-ownership-test.php's.
 *
 * Run:  php tools/test/report-run-agreement-census-test.php [census.jsonl]
 * Exit 0 = pass, 1 = fail.
 *
 * @package BWS_Dynamic_Tags
 */

error_reporting( E_ALL & ~E_DEPRECATED );

define( 'ABSPATH', __DIR__ );

if ( ! function_exists( '__' ) ) {
	function __( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $s ) { return $s; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $s, $d = null ) { return $s; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $s ) { return is_string( $s ) ? trim( $s ) : $s; }
}
if ( ! function_exists( '_x' ) ) {
	function _x( $s, $c = '', $d = null ) { return $s; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $s ) ); }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) { return $default; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'get_intermediate_image_sizes' ) ) {
	function get_intermediate_image_sizes() { return array( 'thumbnail', 'medium', 'large' ); }
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}

// Nothing is held by another plugin: ownership is converter-ownership-test.php's business.
function bws_gb_tag_name_collisions(): array { return array(); }
function bws_gb_registered_tags_now(): array { return array(); }
function bws_gb_tags_we_registered(): array { return array(); }
function bws_gb_tag_held_by_other( string $tag, array $registry, array $ours ): ?array { return null; }

$root = dirname( __DIR__, 2 );
require $root . '/includes/helpers/image-helpers.php';
require $root . '/includes/helpers/serialization-order.php';
require $root . '/includes/helpers/slot-fold.php';
require $root . '/includes/helpers/slot-fold-compile.php';
require $root . '/includes/helpers/slot-fold-migrate.php';
require $root . '/includes/helpers/join-helpers.php';
require $root . '/includes/helpers/gb-output-boundary.php';
require $root . '/includes/helpers/converter-ownership.php';
require $root . '/includes/classes/class-migration-registry.php';
require $root . '/includes/classes/class-deprecated-tag-registry.php';
require $root . '/includes/classes/class-tag-template-registry.php';
require $root . '/includes/tags/deprecated-tags.php';
require $root . '/includes/helpers/registration-helpers.php';
require $root . '/includes/tags/datetime-tags.php'; // The rewrite rule the four old datetime entries run.
require $root . '/includes/classes/admin/class-tag-converter.php';

use BWS\DynamicTags\Admin\TagConverter;
use BWS\DynamicTags\TagTemplateRegistry;

foreach ( array( 'text', 'content', 'image' ) as $key ) {
	TagTemplateRegistry::register_modifier_template( array( 'key' => $key, 'supports_try' => true ) );
}
// The old try_ tags' templates, as the plugin registers them: their option cascade folds flat slot keys.
foreach ( array( 'datetime_single', 'datetime_range' ) as $key ) {
	TagTemplateRegistry::register_modifier_template( array( 'key' => $key, 'supports_try' => true ) );
}
bws_register_nxm_tag_migrations();
bws_register_early_deprecated_tag_migrations();
bws_register_option_migrations();
bws_register_deprecated_tags(); // The four old datetime tags (FW-81).

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $detail = '' ): void {
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

use BWS\DynamicTags\MigrationRegistry;

$deprecated = array_flip( MigrationRegistry::get_deprecated_tag_names() );
$by_tag     = MigrationRegistry::get_option_migrations_by_tag();
$stats      = array( 'strings' => 0, 'convert' => 0, 'old_listed_noop' => 0, 'skipped' => 0, 'declined' => 0 );

/**
 * Compare the verdict with the run for one stored string.
 */
function compare_verdict( string $stored, string $where ): void {
	global $deprecated, $by_tag, $stats;
	[ $name ] = MigrationRegistry::parse_tag_string( $stored );
	if ( ! isset( $deprecated[ $name ] ) && ! isset( $by_tag[ $name ] ) ) {
		return;
	}

	$verdict = TagConverter::classify_tag( $name, $stored );
	$ran     = TagConverter::migrate_string( $stored )['content'];
	++$stats['strings'];

	if ( 'skipped' === $verdict['status'] ) {
		++$stats['skipped'];
		return; // The run has no skip channel: it rewrites what the entry produces; skipped is the entry declining the SHAPE, asserted elsewhere.
	}
	if ( 'declined' === $verdict['status'] ) {
		++$stats['declined'];
	}

	$converts = 'convert' === $verdict['status'];
	$stats['convert'] += (int) $converts;
	check( "$where: convert iff the run changes it ($stored)", $converts === ( $ran !== $stored ), json_encode( array( 'status' => $verdict['status'], 'ran' => $ran ) ) );
	if ( $converts ) {
		check( "$where: the verdict's string is the run's ($stored)", $verdict['string'] === $ran, json_encode( array( 'verdict' => $verdict['string'], 'ran' => $ran ) ) );
	}

	// What the OLD report listed: a deprecated name always, an option entry whenever its key matched.
	$old_listed = isset( $deprecated[ $name ] );
	if ( ! $old_listed ) {
		[ , $options ] = MigrationRegistry::parse_tag_string( $stored );
		foreach ( $by_tag[ $name ] as $entry ) {
			$old_listed = $old_listed || MigrationRegistry::entry_matches( $entry, array_keys( $options ), $options );
		}
	}
	if ( $old_listed && $ran === $stored && 'unchanged' === $verdict['status'] ) {
		++$stats['old_listed_noop'];
	}
}

echo "§C1 synthetic: every registered deprecated name, bare\n";
foreach ( array_keys( $deprecated ) as $name ) {
	compare_verdict( '{{' . $name . '}}', 'C1' );
}
check( 'C1.1 at least one bare deprecated name converts', $stats['convert'] > 0 );

// ===========================================================================
echo "\n§C3 the four old datetime tags (FW-81): the shared mount corpus, through every name\n";
// ===========================================================================
// The corpus is the editor mount step's (datetime-migration-test.php §D8 holds the rewrite rule on it); here it is
// run through the SHIPPED entries and both readers, so the report's verdict and the run agree on every
// input, and the end-only range is skipped and reported rather than renamed.
$corpus = json_decode( (string) file_get_contents( __DIR__ . '/datetime-mount-corpus.json' ), true );
check( 'C3.0 the shared corpus parses', ! empty( $corpus['cases'] ) );

$families = array(
	'single' => array( 'datetime_single', 'try_datetime_single' ),
	'range'  => array( 'datetime_range', 'try_datetime_range' ),
);
// What the run writes where the rewrite rule itself changes nothing (`expect` null in the corpus): a
// rename alone, or the tag left whole. Literals, written from the rule that a rename must move no output.
$no_rewrite = array(
	'single: key alone is already the new form'          => 'key:a',
	'single: a + in the key is not a clean entry'        => null,
	'single: a ; in the time key is not a clean entry'   => null,
	'range: all four empty is left alone'                => null,
	'range: key alone is already the new form'           => 'key:s;e',
	'range: a ; in an operand is not a clean entry'      => 'startKey:a;b|endKey:e',
);
// The same, for the two `try_` names, whose read goes into slot A (FW-193): a rename alone would
// empty the tag, so a plain read moves into A, and what cannot move keeps the old name whole.
$no_rewrite_try = array(
	'single: key alone is already the new form'          => 'A:key(a)',
	'single: a + in the key is not a clean entry'        => null,
	'single: a ; in the time key is not a clean entry'   => null,
	'range: all four empty is left alone'                => null,
	'range: key alone is already the new form'           => 'A:key(s;e)',
	'range: a ; in an operand is not a clean entry'      => null,
	'try slot A: an unparsable A is left alone'          => null,
);
$new_names = array(
	'datetime_single'     => 'datetime',
	'datetime_range'      => 'datetime',
	'try_datetime_single' => 'try_datetime',
	'try_datetime_range'  => 'try_datetime',
);

$end_only_seen = 0;
foreach ( (array) ( $corpus['cases'] ?? array() ) as $case ) {
	foreach ( $families[ $case['family'] ] as $old ) {
		$stored  = MigrationRegistry::format_tag_string( $old, $case['options'] );
		$verdict = TagConverter::classify_tag( $old, $stored );
		$ran     = TagConverter::migrate_string( $stored )['content'];

		$is_try = 0 === strpos( $old, 'try_' );
		$expect = $is_try ? $case['expectTry'] : $case['expect'];
		$stated = $is_try ? $no_rewrite_try : $no_rewrite;
		if ( null !== $expect ) {
			check( "C3.9 the run writes the rewrite ({$stored})", MigrationRegistry::format_tag_string( $new_names[ $old ], $expect ) === $ran, $ran );
		} elseif ( empty( $case['endOnly'] ) ) {
			check( "C3.10 every no-rewrite case has a stated outcome ({$case['label']})", array_key_exists( $case['label'], $stated ) );
			$want = ( $stated[ $case['label'] ] ?? null );
			check(
				"C3.11 {$case['label']}: {$old}",
				null === $want ? $stored === $ran : '{{' . $new_names[ $old ] . ' ' . $want . '}}' === $ran,
				$ran
			);
		}

		compare_verdict( $stored, 'C3' );
		check( "C3.1 never declined by the ownership guard ({$stored})", 'declined' !== $verdict['status'], json_encode( $verdict ) );

		if ( ! empty( $case['endOnly'] ) ) {
			++$end_only_seen;
			check(
				"C3.2 an end-only range is skipped with the datetime reason ({$stored})",
				'skipped' === $verdict['status'] && 'range_without_start' === $verdict['reason'],
				json_encode( $verdict )
			);
			check( "C3.3 and the run leaves it whole ({$stored})", $stored === $ran, $ran );
		} else {
			check( "C3.4 nothing else is skipped ({$stored})", 'skipped' !== $verdict['status'], json_encode( $verdict ) );
		}
	}
}
check( 'C3.5 every end-only case met both of its names', 8 === $end_only_seen, (string) $end_only_seen );

// The old try_ tags keep slots the old option cascade folds: flat slot keys fold first, then the read goes
// into slot A beside them, and slots 2+ (no read of their own) are left to reuse it.
foreach ( array(
	'C3.14 flat slot 1 and 2 fold, then A takes the read' => array(
		'{{try_datetime_single src:post,3|key:a|timeKey:t|2-src:parent}}',
		'{{try_datetime A:src(post,3);key(a+t)|B:src(parent)}}',
	),
	'C3.15 no slot at all makes one slot A' => array(
		'{{try_datetime_range startKey:s|endKey:e}}',
		'{{try_datetime A:key(s;e)}}',
	),
	'C3.16 a read-less old try tag is renamed and gains no slot' => array(
		'{{try_datetime_single src:post,3}}',
		'{{try_datetime A:src(post,3)}}',
	),
	'C3.17 a tag-level use is left whole' => array(
		'{{try_datetime_single key:a|use:published}}',
		'{{try_datetime_single key:a|use:published}}',
	),
) as $label => list( $in, $want ) ) {
	check( "{$label}: {$in}", $want === TagConverter::migrate_string( $in )['content'], TagConverter::migrate_string( $in )['content'] );
}

// A single that names a range key would turn into a range under the new name (the merged tag derives its
// mode from the options), so it is left whole, unreported.
check(
	'C3.12 a single stating a range key is left whole',
	'{{datetime_single key:a|startKey:s}}' === TagConverter::migrate_string( '{{datetime_single key:a|startKey:s}}' )['content']
		&& 'unchanged' === TagConverter::classify_tag( 'datetime_single', '{{datetime_single key:a|startKey:s}}' )['status']
);

// The old tags ignore `use`, which the merged tag reads, so a tag stating one is left whole too.
check(
	'C3.13 a tag stating `use` is left whole',
	'{{datetime_range use:published;key|key:a;b}}' === TagConverter::migrate_string( '{{datetime_range use:published;key|key:a;b}}' )['content']
);

// A chained rename from an older name rewrites the options ONCE, on the last hop.
$chained = TagConverter::resolve_full_chain( 'post_custom_datetime_range', '{{post_custom_datetime_range start_field:s|end_field:e}}' );
check(
	'C3.6 an older range name chains on to `datetime` with the one `key`',
	1 === preg_match( '/^\{\{datetime [^}]*\bkey:s;e\b[^}]*\}\}$/', $chained ) && false === strpos( $chained, 'startKey' ),
	$chained
);

// The option cascade the OLD name owns still runs: `source` is GB's eaten key, repaired to `src` by an
// entry registered under the old name, which the rename would otherwise strand.
check(
	'C3.7 the old name\'s option repair runs before the rename',
	'{{datetime src:post,3|key:a+t}}' === TagConverter::migrate_string( '{{datetime_single source:post,3|key:a|timeKey:t}}' )['content'],
	TagConverter::migrate_string( '{{datetime_single source:post,3|key:a|timeKey:t}}' )['content']
);

// A skip keeps the OLD name's repairs for the option pass, as before: the cascade is not lost.
check(
	'C3.8 a skipped end-only range still gets its old name\'s option repair',
	'{{datetime_range src:post,3|endKey:e}}' === TagConverter::migrate_string( '{{datetime_range source:post,3|endKey:e}}' )['content'],
	TagConverter::migrate_string( '{{datetime_range source:post,3|endKey:e}}' )['content']
);

echo "\n§C2 census\n";
$census = $argv[1] ?? ( getenv( 'BWS_CENSUS' ) ?: '' );
if ( '' === $census ) {
	echo "SKIP  §C2: no census given (argument or BWS_CENSUS); only the synthetic cases ran.\n";
} elseif ( ! is_readable( $census ) ) {
	check( "C2.0 census is readable: $census", false );
} else {
	$seen = array();
	foreach ( file( $census, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$row = json_decode( $line, true );
		if ( is_array( $row ) && is_string( $row['tag_string'] ?? null ) ) {
			$seen[ $row['tag_string'] ] = true;
		}
	}
	check( 'C2.1 the census held tag strings at all', count( $seen ) > 0 );
	$before = $stats;
	foreach ( array_keys( $seen ) as $stored ) {
		compare_verdict( $stored, 'C2' );
	}
	printf( "census: %d distinct strings; %d reach a registered name; %d convert; %d the old report listed and the run leaves alone; %d skipped; %d declined\n",
		count( $seen ), $stats['strings'] - $before['strings'], $stats['convert'] - $before['convert'], $stats['old_listed_noop'] - $before['old_listed_noop'], $stats['skipped'] - $before['skipped'], $stats['declined'] - $before['declined'] );
}

echo "\n";
if ( $fail ) {
	echo "FAILED: {$fail} (passed {$pass})\n";
	exit( 1 );
}
echo "PASSED: {$pass}\n";
exit( 0 );
