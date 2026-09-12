<?php
/**
 * The converter's OWNERSHIP GUARD — may we rewrite the strings bearing this tag name?
 *
 * ONE JOB: never rewrite on ambiguity. The converter edits live post content in place, so
 * a wrong answer here is not a wrong report, it is somebody's page rewritten to read from
 * a source that was never theirs. Everything in this file exists to make the default
 * answer "no" and to make every "yes" say which fact bought it.
 *
 * THE GUARD FAILS OPEN BY CONSTRUCTION, which is why it is a pure predicate with its own
 * harness rather than a few conditions inside the converter. A guard that quietly starts
 * answering "ours" to everything looks exactly like a guard that is working: content gets
 * migrated, the run reports success, and nothing anywhere is different until an author
 * opens a page. tools/test/converter-ownership-test.php drives all four states and
 * censuses the reason enum, so a fifth reason added without a case fails the suite.
 *
 * @package BWS_Dynamic_Tags
 * @since 1.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The closed set of answers the guard can give — two that authorize a rewrite, two that refuse.
 *
 *   'ours'            — nothing says otherwise: no other plugin answers for this name on
 *                       this site, and every option key on the tag is one the target
 *                       template accepts. Rewrite.
 *   'opted_in'        — something DID say otherwise, and the site owner overrode it for
 *                       this tag name. Rewrite. Never inferred; only an explicit per-name
 *                       entry in the opt-in set produces this.
 *   'name_not_ours'   — another plugin's code answers for this name on this site, so we
 *                       cannot prove the strings in content were authored against our tag.
 *                       Refuse. The AUTHOR ACTION is real: rename or remove the other
 *                       plugin's tag, or opt in.
 *   'unknown_options' — the name is ours today, but the tag carries option keys the target
 *                       template does not accept, which is what content authored against
 *                       somebody else's same-named tag looks like after we took the name
 *                       over. Refuse. No author action beyond opting in.
 *
 * THE TWO REFUSALS ARE NOT ONE REASON, and a report merging them tells a site owner to go
 * looking for a plugin conflict that does not exist (see D38; the surface is ticket 12's).
 *
 * CLOSED AND CENSUSED. The report has one line of wording per member, and a reason
 * reaching it with no wording prints nothing at all — which reads exactly like a tag that
 * converted. tools/test/converter-ownership-test.php holds both directions: every member
 * is reachable, and nothing unlisted is returned.
 *
 * @since 1.20.0
 */
const BWS_CONVERTER_OWNERSHIP_REASONS = array(
	'ours',
	'opted_in',
	'name_not_ours',
	'unknown_options',
);

/**
 * One report line per ownership reason — the DECLINE channel's whole vocabulary (FW-39).
 *
 * Keyed by reason so the census is `array_keys()` against the enum above: a fifth reason
 * added without a line fails `converter-ownership-test.php` rather than reaching a report
 * that silently prints nothing for it. The two AUTHORIZING reasons carry an empty line on
 * purpose — a tag that converted has no decline to report, and spelling that as '' keeps
 * both halves of the enum in one list the census can read.
 *
 * `action` IS WHAT SEPARATES THE TWO REFUSALS, and it is the reason they are not one
 * reason (D38). A contested NAME has something a site owner can go and do — rename or
 * remove the other plugin's tag — so the report offers it. Unknown option vocabulary does
 * not: there is no other plugin to go and find, and telling an owner to look for one sends
 * them after a conflict that does not exist. Opting in is available for both and is not
 * this flag; it is the channel's own control.
 *
 * `%1$s` is the tag name, `%2$d` the number of stored strings, `%3$s` the other registrar's
 * phrase (bws_gb_other_registrar_phrase(), already escaped) where one is known. A line that
 * does not use a placeholder simply omits it.
 *
 * NOT A SECOND GATE. The skip channel has its own list beside its own enum
 * (bws_modifier_skip_report_lines()), and the two are never merged — see that function and
 * BWS_MODIFIER_SKIP_REASONS for why.
 *
 * @since 1.20.0
 * @return array<string, array{line:string, action:string}> Reason → wording.
 */
function bws_converter_ownership_report_lines(): array {
	return array(
		'ours'            => array( 'line' => '', 'action' => '' ),
		'opted_in'        => array( 'line' => '', 'action' => '' ),
		'name_not_ours'   => array(
			/* translators: 1: tag name, 2: number of stored tag strings, 3: the other plugin that registers the name. */
			'line'   => __( '%1$s is also registered by %3$s on this site, so we cannot tell which plugin the %2$d stored tags were written for. They are left exactly as they are.', 'generateblocks' ),
			'action' => __( 'Rename or remove the other plugin\'s tag, or claim these tags as yours below.', 'generateblocks' ),
		),
		'unknown_options' => array(
			/* translators: 1: tag name, 2: number of stored tag strings. */
			'line'   => __( '%1$s carries option names we do not recognize, which is what content written for a different plugin\'s tag of the same name looks like. The %2$d stored tags are left exactly as they are.', 'generateblocks' ),
			'action' => '',
		),
	);
}

/**
 * Option holding the tag names the site owner has claimed as theirs.
 *
 * A flat list of tag names. WRITTEN BY THE SCAN REPORT'S OPT-IN CONTROL (ticket 12), which
 * is also where the count and the preview that justify the click live — a claim of
 * ownership is only meaningful next to the strings being claimed. Read here.
 *
 * PER TAG NAME, NEVER A SITE-WIDE FLAG. The question the owner is answering is "are the
 * {{term_title}} strings on this site mine?", and the answer for one contested name says
 * nothing about the next.
 *
 * @since 1.20.0
 */
const BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION = 'bws_dynamic_tags_ownership_optin';

/**
 * THE GUARD. Decide whether the strings bearing $tag may be rewritten, and say why.
 *
 * INVARIANT — THE CONVERTER NEVER REWRITES A TAG NAME WE CANNOT PROVE WE OWN WITHOUT AN
 * EXPLICIT PER-NAME OPT-IN. Both refusals below are absolute against every other fact: a
 * yielded name stays unrewritten whatever its options look like, and unknown vocabulary is
 * never lifted by the name looking clean. Only a name in $opted_in overrides either.
 *
 * PURE, AND THAT IS THE POINT. Every fact arrives as an argument — no WordPress, no GB
 * registry, no option read — so a harness can drive all four states with nothing installed,
 * on every change, at the one place in this ship that damages content on a wrong answer.
 * bws_converter_rewrite_allowed() below is the thin WordPress-side half that gathers them.
 *
 * AXIS — A NAME IS NOT OURS IFF THE COLLISION RECORD SAYS ANOTHER PLUGIN'S CODE ANSWERS FOR
 * IT. That is 'lost' and 'yielded' together, and 'kept' deliberately excluded. The record
 * keeps the three apart because the DEVELOPER remedies differ (see
 * bws_gb_tag_name_collisions()), and the settings page already collapses them on the same
 * line this does — but the question here is neither of theirs. It is "could these strings
 * have been authored against somebody else's tag", and 'lost' and 'yielded' both mean yes.
 *
 * 'kept' MEANS WE REGISTERED OVER A STRANGER, so our tag is the one rendering now — and the
 * content may still predate us. That case has no record to catch it, which is exactly what
 * the vocabulary net below is for.
 *
 * THE YIELD RECORD MEANS "WE CANNOT PROVE THESE STRINGS ARE OURS", NOT "THEY ARE NOT OURS".
 * The site this guard was built for is exactly that case: the strings are ours, the other
 * plugin arrived later, and only the owner knows it. Which is why refusing is not the end
 * of the interaction — declining outright would leave that owner a report and no button.
 *
 * UNKNOWN VOCABULARY IS THE RESIDUAL NET, NEVER SUFFICIENT ALONE. It only ever REFUSES; no
 * path here reads "all keys known" as evidence of anything. On the measured site every key
 * on the contested tags is legal on both plugins' tags, so the net catches nothing there —
 * it is for the other direction, a name we won carrying wire from a vocabulary that is not
 * ours at all.
 *
 * NO KNOWN VOCABULARY MEANS EVERY KEY IS UNKNOWN, so an empty set refuses rather than waves
 * through. That is the fail-closed direction, and it is one `if ( $known_options )` away
 * from being the fail-open one.
 *
 * @since 1.20.0
 * @param string   $tag           The tag name as it appears in post content.
 * @param array    $collisions    The request's collision record, bws_gb_tag_name_collisions() shape.
 * @param string[] $option_keys   Option keys on the tag AFTER the migration entry's own renames.
 * @param string[] $known_options Keys this plugin recognizes. Empty = nothing recognized.
 * @param string[] $opted_in      Tag names the site owner has claimed.
 * @return array{rewrite:bool,reason:string,unknown_options:string[]} A member of
 *         BWS_CONVERTER_OWNERSHIP_REASONS, and the offending keys when there are any.
 */
function bws_converter_tag_ownership(
	string $tag,
	array $collisions,
	array $option_keys,
	array $known_options,
	array $opted_in
): array {
	$outcome = (string) ( $collisions[ $tag ]['outcome'] ?? '' );
	$unknown = array_values( array_diff( $option_keys, $known_options ) );

	$refusal = '';
	if ( 'lost' === $outcome || 'yielded' === $outcome ) {
		$refusal = 'name_not_ours';
	} elseif ( $unknown ) {
		$refusal = 'unknown_options';
	}

	if ( '' === $refusal ) {
		return array( 'rewrite' => true, 'reason' => 'ours', 'unknown_options' => array() );
	}

	if ( in_array( $tag, $opted_in, true ) ) {
		return array( 'rewrite' => true, 'reason' => 'opted_in', 'unknown_options' => $unknown );
	}

	return array( 'rewrite' => false, 'reason' => $refusal, 'unknown_options' => $unknown );
}

/**
 * Gather this site's facts and ask the guard.
 *
 * The WordPress-side half, kept as thin as it can be: it reads three things and calls the
 * predicate. Anything that decides belongs above, where a harness can reach it.
 *
 * THE KEYS COME OFF THE MIGRATED STRING, not the stored one, because the keys the guard
 * weighs are the keys the entry's own renames produced. Passing the transform's output
 * means no second copy of the rename map lives here.
 *
 * THE NAME CHECKED AGAINST THE COLLISION RECORD IS THE STORED ONE. Ownership is a claim
 * about strings already sitting in post content, and those bear the old name; whether the
 * target name is contested is a different question and not this one.
 *
 * KNOWN VOCABULARY IS THE CANONICAL KEY MAP PLUS THE KEYS GB'S OWN OUTPUT PIPELINE
 * CONSUMES, AND IT IS DELIBERATELY NOT THE TARGET TAG'S REGISTERED OPTIONS. A registered
 * option set answers "which controls does the editor paint", and that is a strictly
 * narrower question than "is this key one of ours" — ADR 0004 is why: removing a control
 * never removes an option, so `limit` has been legal, read and unregistered on the base
 * tags since 1.17.0 (#62), and `{{term_text key:phone|limit:2}}` converts to wire that a
 * registration-derived set calls foreign. Measured 2026-09-11 against the real
 * registration pass: four of nine ordinary conversions refused that way.
 * bws_serialization_order_key_map() already owns the enumeration this needs and says so in
 * its own words ("a key absent from this map is UNKNOWN"), which is the second reason not
 * to build a rival — the two would drift and only this one would be silent about it.
 *
 * TAG-AGNOSTIC ON PURPOSE. A key legal on `{{join}}` and not on `{{text}}` is still OURS,
 * and the question here is whether the wire came from somebody else's vocabulary. Per-tag
 * precision would buy nothing against that and cost refusals on wire we wrote.
 *
 * THE GB HALF IS LOAD-BEARING TOO: `link`, `trunc` and the rest are legal on every tag we
 * register, appear in no option array of ours and are not in the key map either, because GB
 * supplies those controls itself. bws_gb_tag_output() owns that set.
 *
 * THE SLOT PREFIX IS STRIPPED FIRST. `2-key` is `key` at slot 2, and a folded slot key
 * (`A`, `B`, …) parses to the empty bare name, which the map carries an entry for.
 *
 * @since 1.20.0
 * @param string $old_tag         Tag name as stored in post content.
 * @param string $transformed_tag The tag string the migration entry produced.
 * @return array{rewrite:bool,reason:string,unknown_options:string[]}
 */
function bws_converter_rewrite_allowed( string $old_tag, string $transformed_tag ): array {
	[ , $new_options ] = \BWS\DynamicTags\MigrationRegistry::parse_tag_string( $transformed_tag );

	$bare = array();
	foreach ( array_keys( $new_options ) as $key ) {
		[ , $bare[] ] = bws_serialization_order_parse_slot( (string) $key );
	}

	return bws_converter_tag_ownership(
		$old_tag,
		bws_gb_tag_name_collisions(),
		$bare,
		array_merge( array_keys( bws_serialization_order_key_map() ), BWS_GB_TAG_OUTPUT_OPTIONS ),
		(array) get_option( BWS_CONVERTER_OWNERSHIP_OPTIN_OPTION, array() )
	);
}
