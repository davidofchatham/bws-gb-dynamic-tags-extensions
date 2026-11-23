<?php
/**
 * Measures which gate answers on each arm of the L2 read seam, against the REAL
 * GenerateBlocks on the testbed. NOT shipped.
 *
 * Run (the plugin is mounted at /plugins/ in the wpcli container):
 *   wsl.exe -d Ubuntu -- bash -lc 'cd ~/wp-litespeed && bin/wp.sh testbed eval-file /plugins/bws-gb-dynamic-tags-extensions/tools/debug/seam-gate-probe.php'
 *
 * Two questions, each asked with a POSITIVE CONTROL so a refusal cannot be an empty read
 * (the dotted-path checks use `bws_probe_dot.x`, an allowed parent that must read):
 *
 *   A. KEY BLOCKLIST. For site, term, user and post: a readable key returns its value,
 *      a GenerateBlocks-disallowed key (`user_pass`) returns '' through the seam, and
 *      GB's Meta_Handler ALONE returns '' for it too (the layer the site arm leans on).
 *      The site arm is reached by allowlisting the key first, since the allowlist would
 *      otherwise refuse before GB is asked and the probe would measure the wrong gate.
 *
 *   B. SOURCE VISIBILITY. The seam reads a private post's meta for an anonymous viewer
 *      (it has no visibility gate); bws_is_source_usable() refuses the same source and
 *      bws_run_traversal() drops it. Where the visibility gate lives is the traversal.
 *
 * NOT COVERED: GB's REST-only restrictions (user-meta access, `read_post`, protected meta)
 * are gated on REST_REQUEST and never fire under wp-cli, and the `meta_row` arm has no key
 * check to measure (its row was read, and gated, before it reached the seam).
 *
 * Writes temp rows (options, one post, term/user meta) and removes them in `finally`. A
 * missing fixture throws into the catch below instead of exiting, so cleanup still runs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$fails = 0;
$check = static function ( string $label, $expected, $actual ) use ( &$fails ): void {
	if ( $expected === $actual ) {
		echo "  ok   {$label}\n";
		return;
	}
	$fails++;
	echo "  FAIL {$label}\n         expected: " . var_export( $expected, true ) . "\n         actual:   " . var_export( $actual, true ) . "\n";
};

if ( ! class_exists( 'GenerateBlocks_Meta_Handler' )
	|| ! class_exists( 'GenerateBlocks_Dynamic_Tag_Security' )
	|| ! function_exists( 'bws_read_resolved_source_value' ) ) {
	echo "FAIL  GB Meta_Handler, GB's dynamic-tag security class or the plugin is not loaded\n";
	exit( 1 );
}

$OK  = 'bws_probe_ok';
$DOT = 'bws_probe_dot'; // Stored as an array, so `bws_probe_dot.x` is a dotted read under an ALLOWED parent.
$BAD = 'user_pass'; // On GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS.
$check( 'precondition: the probe key is on GB\'s blocklist', true, in_array( $BAD, GenerateBlocks_Dynamic_Tag_Security::DISALLOWED_KEYS, true ) );

// Let the site arm past the allowlist for both keys, so GB's layer is what answers.
$allow = static function ( $a ) use ( $OK, $DOT, $BAD ) {
	return array_merge( (array) $a, array( $OK, $DOT, $BAD ) );
};
add_filter( 'generateblocks_dynamic_tags_allowed_options', $allow );

$post_id = 0;
$term    = null;
$user_id = 0;

try {
	// ── Fixtures ────────────────────────────────────────────────────────────────
	$post_id = wp_insert_post( array( 'post_title' => 'bws probe', 'post_status' => 'private', 'post_type' => 'post' ) );
	$terms   = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 1 ) );
	$term    = is_array( $terms ) && $terms ? $terms[0] : null;
	$users   = get_users( array( 'number' => 1, 'fields' => 'ID' ) );
	$user_id = $users ? (int) $users[0] : 0;
	if ( ! $post_id || ! $term || ! $user_id ) {
		throw new RuntimeException( "fixtures missing (post={$post_id}, term=" . ( $term ? $term->term_id : 'none' ) . ", user={$user_id})" );
	}

	update_option( $OK, 'SITE_OK' );
	update_option( $BAD, 'SITE_LEAK' );
	update_post_meta( $post_id, $OK, 'POST_OK' );
	update_post_meta( $post_id, $BAD, 'POST_LEAK' );
	update_term_meta( $term->term_id, $OK, 'TERM_OK' );
	update_term_meta( $term->term_id, $BAD, 'TERM_LEAK' );
	update_user_meta( $user_id, $OK, 'USER_OK' );
	update_user_meta( $user_id, $BAD, 'USER_LEAK' );

	$sources = array(
		'site' => array( 'kind' => 'site' ),
		'term' => array( 'kind' => 'term', 'id' => (int) $term->term_id ),
		'user' => array( 'kind' => 'user', 'id' => $user_id ),
		'post' => array( 'kind' => 'post', 'id' => $post_id ),
	);
	$value   = array( 'site' => 'SITE_OK', 'term' => 'TERM_OK', 'user' => 'USER_OK', 'post' => 'POST_OK' );

	echo "\n== A. key blocklist, per arm ==\n";
	foreach ( $sources as $kind => $src ) {
		$check( "{$kind}: control key reads through the seam", $value[ $kind ], bws_read_resolved_source( $src, $OK, null ) );
		$check( "{$kind}: disallowed key is refused through the seam", '', bws_read_resolved_source( $src, $BAD, null ) );
	}

	// GB's layer alone: what the site arm depends on, and what the other three double up.
	$check( 'GB alone, option: control reads', 'SITE_OK', GenerateBlocks_Meta_Handler::get_option( $OK ) );
	$check( 'GB alone, option: disallowed key refused', '', GenerateBlocks_Meta_Handler::get_option( $BAD ) );
	// Dotted paths: the control first, so the refusals below cannot be an empty dotted read.
	update_option( $DOT, array( 'x' => 'DOT_OK' ) );
	update_option( $BAD, array( 'x' => 'SITE_LEAK' ) );
	$check( 'GB alone, option: a dotted path under an ALLOWED parent reads (control)', 'DOT_OK', GenerateBlocks_Meta_Handler::get_option( $DOT . '.x' ) );
	$check( 'seam site arm: the same dotted control reads', 'DOT_OK', bws_read_resolved_source( $sources['site'], $DOT . '.x', null ) );
	$check( 'GB alone, option: a dotted path under a disallowed parent is refused', '', GenerateBlocks_Meta_Handler::get_option( $BAD . '.x' ) );
	$check( 'seam site arm: the dotted path is refused too', '', bws_read_resolved_source( $sources['site'], $BAD . '.x', null ) );
	$check( 'GB alone, term meta: disallowed key refused', '', GenerateBlocks_Meta_Handler::get_meta( $term->term_id, $BAD, true, 'get_term_meta' ) );
	$check( 'GB alone, user meta: disallowed key refused', '', GenerateBlocks_Meta_Handler::get_meta( $user_id, $BAD, true, 'get_user_meta' ) );
	$check( 'GB alone, post meta: disallowed key refused', '', GenerateBlocks_Meta_Handler::get_meta( $post_id, $BAD, true, 'get_post_meta' ) );

	echo "\n== B. source visibility lives in the traversal, not the seam ==\n";
	wp_set_current_user( 0 );
	$check( 'viewer is anonymous', 0, get_current_user_id() );
	$check( 'seam reads a PRIVATE post\'s meta for an anonymous viewer', 'POST_OK', bws_read_resolved_source( $sources['post'], $OK, null ) );
	$check( 'bws_is_source_usable() refuses the same source', false, bws_is_source_usable( $sources['post'] ) );
	$check( 'bws_run_traversal() drops it before any read', array(), bws_run_traversal( array( $sources['post'] ), array() ) );
	$check( 'a public source survives the same traversal (control)', array( $sources['term'] ), bws_run_traversal( array( $sources['term'] ), array() ) );
} catch ( RuntimeException $e ) {
	$fails++;
	echo 'FAIL  ' . $e->getMessage() . "\n";
} finally {
	remove_filter( 'generateblocks_dynamic_tags_allowed_options', $allow );
	delete_option( $OK );
	delete_option( $DOT );
	delete_option( $BAD );
	if ( $post_id ) {
		wp_delete_post( $post_id, true );
	}
	if ( $term ) {
		delete_term_meta( $term->term_id, $OK );
		delete_term_meta( $term->term_id, $BAD );
	}
	if ( $user_id ) {
		delete_user_meta( $user_id, $OK );
		delete_user_meta( $user_id, $BAD );
	}
}

echo $fails ? "\nFAILED - {$fails} check(s)\n" : "\nPASSED\n";
exit( $fails ? 1 : 0 );
