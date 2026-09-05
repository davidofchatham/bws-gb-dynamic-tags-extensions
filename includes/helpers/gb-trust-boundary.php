<?php
/**
 * The boundary between this plugin and GenerateBlocks' dynamic-data TRUST model.
 *
 * GB 2.4 made one predicate the trust boundary for dynamic data, and GB Pro 2.7 added a
 * second, key-scoped question on top of it. Both are GB's to answer. This file owns HOW WE
 * ASK them — the guarded call, the fallback when a GB old enough not to have them is
 * installed, and nothing else. What each caller DOES with the answer is the caller's, and
 * is stated at the caller.
 *
 * NOTHING HERE IS RE-INCLUDE GUARDED, matching gb-output-boundary.php, slot-fold.php and
 * try-slot-arms.php — the plugin loads it once with `require_once`, and wrapping only the
 * functions would leave the const unprotected while reading as though the file were
 * idempotent.
 *
 * WHY A SEAM RATHER THAN FOUR GUARDED CALLS. Four sites need the predicate (the two
 * `src:site` option-read paths, the field-discovery REST route, and the editor enqueue that
 * inlines the field envelope) and two of those additionally need the key question. Written
 * inline, that is one axis spelled four times, each free to drift into a slightly different
 * probe. `tools/test/gb-trust-boundary-test.php` censuses the tree so it stays one.
 *
 * THE OTHER HALF OF THE MODEL NEEDS NO SEAM, and it is worth saying so here so nobody adds
 * one. GB's render-time taint suppression and its save gate both sit ABOVE the per-tag
 * callback layer and enumerate the whole tag registry, so our tags are covered without
 * asking for anything — see docs/gb-constraints.md §Dynamic data is suppressed by POST
 * SOURCE, and `verify.php`'s GB-trust-model section, which measures that coverage rather
 * than asserting it. This file exists only for the surfaces that suppression cannot reach,
 * because they are ours and are not a rendered tag.
 *
 * CALLERS FAIL OPEN WHEN THIS FILE IS ABSENT, AND THAT IS ONE POLICY STATED ONCE HERE.
 * Every consumer guards its call with function_exists() and, on a miss, behaves as it did
 * before this file existed — the site read resolves, the REST route answers on edit_posts
 * alone, the editor gets its envelope. The guards are the house pattern over an
 * unconditionally-required file (a partial deploy or a .distignore'd path failing to land is
 * a real failure mode, and a guard turns a fatal into a degraded page), so their miss means a
 * BROKEN INSTALL, never an untrusted user. Failing closed there would blank an
 * administrator's editor because a file did not copy, which protects nobody and looks exactly
 * like data loss. Note this is a different question from the last-resort branch INSIDE
 * bws_gb_user_can_author_dynamic_data(), which is about GB being absent rather than us.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.19.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The GenerateBlocks Pro version the FALLBACK derive below was read from.
 *
 * Recorded, not enforced, and it describes the fallback ONLY. The primary path calls Pro's
 * own public method, so it cannot go stale by construction — there is nothing of Pro's rule
 * copied here to drift. The fallback is a copy, so it can, and this is what a reader
 * re-reads it against. Pinned against the fixture environment's recorded Pro version by
 * tools/test/gb-trust-boundary-test.php.
 */
const BWS_GB_TRUST_FALLBACK_READ_FROM = '2.7.1';

/**
 * Whether the current user may author dynamic data, per GenerateBlocks.
 *
 * THE ONE PLACE THIS PLUGIN ASKS. GB's own docblock is blunt about what a `true` here
 * grants: disclosure of protected meta, other users' meta, options and cross-post data.
 *
 * NOT MEMOIZED, and that is a decision rather than an oversight. GB does not cache it
 * either, but the binding reason is local: `verify.php`'s P2 pin works by filtering
 * `generateblocks_user_can_author_dynamic_data` false and then asking within the same
 * request. A per-request cache would hand that pin an answer taken before the filter
 * attached, and the pin would pass while measuring nothing — a tripwire failing open, which
 * is the failure mode the pins exist over. Four `current_user_can()` calls do not buy it.
 *
 * THE THREE PROBES ARE ORDERED BY DIRECTNESS, not preference: GB's global function wrapper,
 * then the class it delegates to, then GB's own default re-applied through GB's own filter.
 * The last is unreachable in practice — the plugin header declares
 * `Requires Plugins: generateblocks-pro` — but a guard has to return something, and
 * returning GB's documented default is the only answer that is neither invented nor a
 * silent policy of our own. Failing closed would blank an admin's editor the moment an
 * upstream rename made every probe miss; failing open would hard-code "no gate" into
 * exactly that branch.
 *
 * @since 1.19.2
 * @return bool True when the current user may author dynamic data.
 */
function bws_gb_user_can_author_dynamic_data(): bool {
	if ( function_exists( 'generateblocks_user_can_author_dynamic_data' ) ) {
		return (bool) generateblocks_user_can_author_dynamic_data();
	}

	if ( class_exists( 'GenerateBlocks_Dynamic_Tag_Security' )
		&& method_exists( 'GenerateBlocks_Dynamic_Tag_Security', 'user_can_author_dynamic_data' )
	) {
		return (bool) GenerateBlocks_Dynamic_Tag_Security::user_can_author_dynamic_data();
	}

	$default = current_user_can( 'unfiltered_html' ) || current_user_can( 'manage_options' );

	return (bool) apply_filters( 'generateblocks_user_can_author_dynamic_data', $default );
}

/**
 * Whether the current user may be shown the value of one wp_options key.
 *
 * A SECOND, INDEPENDENT AXIS from the ADR 0001 allowlist, which asks WHICH KEYS may ever be
 * read and is silent on WHICH USER. Both gates apply; neither substitutes for the other.
 * A key can be allowlisted and still be a cross-boundary read in an untrusted user's editor
 * preview, which is the divergence from GB Pro 2.7 this seam exists to close.
 *
 * THE PRIMARY PATH CALLS PRO, DELIBERATELY. `is_option_allowed_for_current_user()` is public
 * static on Pro's register class and already resolves a dot-notated key to its root segment,
 * so calling it keeps us in lockstep on the rule AND on the key grammar. ADR 0001's
 * "re-derive rather than read Pro's private state" is not in tension with that: it was about
 * PRIVATE state, and re-deriving a public rule is the thing that drifts the moment Pro
 * changes what it subtracts. Its list-returning sibling
 * `get_allowed_options_for_current_user()` is not used, because taking the key-shaped
 * question leaves the root-segment split at Pro too.
 *
 * THE SEED BELOW IS A THIRD COPY OF SIX KEYS, AND IT STAYS ONE ON PURPOSE. ADR 0001's
 * bws_site_allowlist_ok() holds the same six today, and sharing them would look like the
 * de-duplication this file exists to do. It is not: the two answer different questions that
 * agree only by coincidence. Ours is ADR 0001's parity seed, governing which keys this plugin
 * will ever read; this one is imitating GB PRO's get_allowed_options() so the fallback answers
 * the way Pro would. Coupling them would let a future Pro change silently drag our allowlist
 * with it, or the reverse. What the copy owes instead is a DATE, which is what
 * BWS_GB_TRUST_FALLBACK_READ_FROM above is — re-read both against Pro when that version moves.
 *
 * THE FALLBACK MIRRORS PRO'S RULE for a Pro too old to have the method: the shared allowlist
 * minus every registered ACF options-page key, then Pro's own per-user filter. Registration
 * of an ACF options-page field is the opt-in that makes it readable at all (ADR 0001), and
 * an untrusted user is precisely who that opt-in was not made for; core options like
 * `blogname` stay readable, which is Pro's own carve-out and not a softening of it.
 * BWS_GB_TRUST_FALLBACK_READ_FROM records what this copy was read from.
 *
 * A TRUSTED USER SHORT-CIRCUITS. Asking the key question of a trusted user can only ever
 * return true, and skipping it keeps the expensive derive off the path every ordinary
 * editor takes.
 *
 * @since 1.19.2
 * @param string $key wp_options key, optionally dot-notated.
 * @return bool True when the current user may be shown this key's value.
 */
function bws_gb_option_allowed_for_current_user( string $key ): bool {
	if ( '' === trim( $key ) ) {
		return false;
	}

	if ( bws_gb_user_can_author_dynamic_data() ) {
		return true;
	}

	if ( class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_Register' )
		&& method_exists( 'GenerateBlocks_Pro_Dynamic_Tags_Register', 'is_option_allowed_for_current_user' )
	) {
		return (bool) GenerateBlocks_Pro_Dynamic_Tags_Register::is_option_allowed_for_current_user( $key );
	}

	$allowed  = array( 'siteurl', 'blogname', 'blogdescription', 'home', 'time_format', 'user_count' );
	$acf_keys = array();

	if ( class_exists( 'GenerateBlocks_Pro_Dynamic_Tags_ACF' )
		&& method_exists( 'GenerateBlocks_Pro_Dynamic_Tags_ACF', 'get_instance' )
	) {
		$acf = GenerateBlocks_Pro_Dynamic_Tags_ACF::get_instance();

		if ( $acf && method_exists( $acf, 'get_acf_option_fields' ) ) {
			$acf_keys = array_keys( (array) $acf->get_acf_option_fields() );
		}
	}

	// The full allowlist first, so a site filter that adds a key is subject to the same
	// ACF subtraction Pro applies — then the per-user filter, which is Pro's seam for
	// putting a specific key back for non-admins.
	$allowed = apply_filters( 'generateblocks_dynamic_tags_allowed_options', array_merge( $allowed, $acf_keys ) );
	$allowed = array_values( array_diff( (array) $allowed, $acf_keys ) );

	/** This filter is documented in GB Pro's class-register.php. */
	$allowed = (array) apply_filters(
		'generateblocks_dynamic_tags_allowed_options_for_current_user',
		$allowed,
		$acf_keys
	);

	$parent = trim( explode( '.', $key )[0] );

	return '' !== $parent && in_array( $parent, $allowed, true );
}
