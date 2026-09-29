<?php
/**
 * Pure harness for the fixed read's turn in the six try_email / try_phone per-item
 * dispatchers (FW-141 05): post, term and row, per family.
 *
 * What it pins is the DISPATCH SEAM, not the finisher: a fixed read answers the author's
 * value once when the dispatcher's own source resolved, and nothing when it did not, so the
 * attempt walk moves on; an invalid entry is empty for the same reason; a field read never
 * takes the fixed branch. Source resolution is stubbed (its real rule is pinned by
 * slot-fold-test.php §P19); the finishers run for real, with the settings class stubbed.
 *
 *   php tools/test/try-fixed-dispatch-test.php
 *
 * @package BWS_Dynamic_Tags
 */


namespace BWS\DynamicTags\Admin {
	class SettingsPage {
		public static function is_email_obfuscation_enabled(): bool { return false; }
		public static function get_phone_country_code(): string { return '1'; }
		public static function is_phone_strip_cc_enabled(): bool { return false; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ );
	function __( $s, $d = null ) { return $s; }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function is_email( $s ) { return false !== filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
	function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
	function antispambot( $s ) { return $s; }
	function bws_base_src_resolution( array $o ): array { return array( 'kind' => 'site' === ( $o['src'] ?? '' ) ? 'site' : 'post' ); }
	function bws_read_field() { return ''; }
	function bws_read_term_field() { return ''; }
	function bws_read_resolved_source() { return ''; }

	require __DIR__ . '/../../includes/helpers/registration-helpers.php';
	require __DIR__ . '/../../includes/helpers/field-helpers.php';
	require __DIR__ . '/../../includes/tags/email-tags.php';
	require __DIR__ . '/../../includes/tags/phone-tags.php';

	$fails = 0;
	function eq( string $label, $want, $got ): void {
		global $fails;
		if ( $want === $got ) {
			echo "PASS  {$label}\n";
			return;
		}
		$fails++;
		echo "FAIL  {$label}\n      want: " . json_encode( $want ) . "\n      got : " . json_encode( $got ) . "\n";
	}

	$strip = static fn( array $a ): array => array_map( static fn( $s ) => trim( strip_tags( $s ) ), $a );

	$fam = array(
		'email' => array( 'good' => 'info@example.com', 'bad' => 'not-an-email', 'want' => 'info@example.com' ),
		'phone' => array( 'good' => '555-867-5309',     'bad' => 'abc',          'want' => '555-867-5309' ),
	);

	foreach ( $fam as $t => $f ) {
		$post = "bws_try_{$t}_post_dispatch";
		$term = "bws_try_{$t}_term_dispatch";
		$row  = "bws_try_{$t}_row_dispatch";
		$opts = array( 'use' => 'fixed', 'fixed' => $f['good'], 'noLink' => '1' );

		// A fixed read answers once per dispatcher source, finished by the family.
		eq( "{$t} post: a fixed read with a resolved post", array( $f['want'] ), $strip( $post( 7, $opts, null ) ) );
		eq( "{$t} term: a fixed read with a resolved term", array( $f['want'] ), $strip( $term( 7, $opts, null ) ) );
		eq( "{$t} row: a row is always a source", array( $f['want'] ), $strip( $row( array( 'kind' => 'meta_row' ), $opts, null ) ) );

		// D8 — no source, empty, so the walk moves on.
		eq( "{$t} post: no post id is no source", array(), $post( 0, $opts, null ) );
		eq( "{$t} post: the site is always a source", array( $f['want'] ), $strip( $post( 0, $opts + array( 'src' => 'site' ), null ) ) );
		eq( "{$t} term: no term id is no source", array(), $term( 0, $opts, null ) );

		// D11 — invalid is empty (the finisher's validity, not a second check here).
		$bad = array( 'use' => 'fixed', 'fixed' => $f['bad'] );
		eq( "{$t} post: an invalid entry is empty", array(), $post( 7, $bad, null ) );
		eq( "{$t} row: an invalid entry is empty", array(), $row( array( 'kind' => 'meta_row' ), $bad, null ) );

		// No text entered is empty too, not a crash.
		eq( "{$t} post: use:fixed with nothing typed is empty", array(), $post( 7, array( 'use' => 'fixed' ), null ) );

		// D2 — a `fixed` token alone IS the fixed read (it is implied, after `key`); beside a
		// key the key read wins and the fixed text never prints.
		eq( "{$t} post: a fixed token with no use and no key is the fixed read", array( $f['want'] ), $strip( $post( 7, array( 'fixed' => $f['good'], 'noLink' => '1' ), null ) ) );
		eq( "{$t} post: a stale fixed token beside a key never prints", array(), $post( 7, array( 'key' => 'x', 'fixed' => $f['good'] ), null ) );
		eq( "{$t} the helper reports a field read as null, not empty", null, bws_try_fixed_dispatch( $t, true, array( 'key' => 'x' ) ) );
	}

	echo $fails ? "\n{$fails} FAILURE(S)\n" : "\nALL PASS\n";
	exit( $fails ? 1 : 0 );
}
