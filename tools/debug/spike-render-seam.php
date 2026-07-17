<?php
/**
 * THROWAWAY SPIKE — render-seam feasibility (fixture-testbed.md §Render seam).
 *
 * Run:
 *   bin/wp.sh portals eval-file /plugins/bws-gb-dynamic-tags-extensions/tools/debug/spike-render-seam.php \
 *     --url=https://portals.test/benefit-type/health/
 *
 * Asserts:
 *   1. Before wp(): is_tax() false, queried object null (the --url trap).
 *   2. After wp(): is_tax() true, queried object = the real term.
 *   3. bws_capture_ambient_signals() sees queried_kind=term.
 *   4. bws_resolve_base_source() resolves the term ambiently.
 *   5. replace_tags() with a fake stdClass instance renders without fataling.
 */

$fail = 0;
$check = function ( $label, $ok, $detail = '' ) use ( &$fail ) {
	printf( "[%s] %s%s\n", $ok ? 'PASS' : 'FAIL', $label, $detail !== '' ? " — {$detail}" : '' );
	if ( ! $ok ) { $fail++; }
};

// 1. The trap: bare --url does NOT run the main query.
$check( 'pre-wp() is_tax() is false (trap confirmed)', ! is_tax() );
$check( 'pre-wp() queried object is null (trap confirmed)', null === get_queried_object() );

// 2. The fix: run the main query off the $_SERVER keys --url set.
wp();

$qo = get_queried_object();
$check( 'post-wp() is_tax() true', is_tax(), 'is_tax=' . var_export( is_tax(), true ) );
$check(
	'post-wp() queried object is the term',
	$qo instanceof WP_Term,
	$qo instanceof WP_Term ? "{$qo->taxonomy}:{$qo->slug} (id {$qo->term_id})" : var_export( $qo, true )
);

// 3. Plugin's ambient capture sees it.
$instance          = new stdClass();
$instance->context = [];
$signals = bws_capture_ambient_signals( $instance );
$check(
	'ambient signals queried_kind=term',
	( $signals['queried_kind'] ?? null ) === 'term',
	json_encode( $signals )
);

// 4. Base source resolves the term ambiently.
$resolved = bws_resolve_base_source( [], $instance );
$check(
	'bws_resolve_base_source resolves ambient term',
	is_array( $resolved ) && ! empty( $resolved ),
	var_export( $resolved, true )
);

// 5. Render through GB's replace_tags with the fake-instance shape.
//    Reads term meta spike_probe_key off the AMBIENT term — the full pipeline.
$tag = '{{text key:spike_probe_key}}';
$out = GenerateBlocks_Register_Dynamic_Tag::replace_tags( $tag, [], $instance );
$check(
	'replace_tags reads term meta off ambient term',
	'SPIKE-VALUE-OK' === $out,
	'output=' . var_export( $out, true )
);

echo $fail ? "\nSPIKE FAILED ({$fail})\n" : "\nSPIKE PASSED\n";
exit( $fail ? 1 : 0 );
