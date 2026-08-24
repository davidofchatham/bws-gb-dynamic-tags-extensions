<?php
/**
 * core-structures blueprint — manifest (the data contract).
 *
 * Pure data: what the seeded site contains, keyed by stable fixture slugs.
 * Consumers (matrices, future Playwright, composing blueprints) pin against
 * these slugs + `version`. Bump `version` on any breaking key change.
 *
 * Manifest owns DATA; matrices own ASSERTIONS. Row refs in comments are
 * provenance pointers, not expectations.
 *
 * Schema (field groups / CPT / registered meta) lives in schema.php;
 * the applier lives in seed.php.
 */

return array(
	'blueprint' => 'core-structures',
	'version'   => 15, // 15: ZERO-GUARD loop sub-field — `qty` on the matrix-post-meta `team_members` rows ('0' then '4'), plus its schema sub-field. Makes {{loop_item key:qty}} expressible inside the existing repeater loop, which is the SECOND GB Pro tag reaching the '0' guard (text matrix T5.5). Additive: no {{table}} column and no F9c row names the key, so every existing row on that repeater is unmoved. 14: SOURCE GATE corpus, second pass (1.18.0) — the three shapes v13 could not reach, each one a case where the gate's answer is NOT "is the status in a list". TRASHED (`staff-gate-trashed`, reached through the plain-meta `trash_ref`): the only status where EXISTS passes and VISIBLE fails for EVERY viewer, administrators included — WP maps `read_post` on trash toward `edit_post`, so a capability-only gate renders it to editors and to nobody else, and WP's own front end renders it to no one at all. ATTACHMENT (`feature_image` on the gate page, hopped onto as a SOURCE): an attachment stores `inherit`, an INTERNAL status, so a gate testing the raw column drops every attachment for logged-out visitors while showing it to logged-in ones; invisible to every other row because plain {{image}} reads never enter the traversal pipeline. OWN DRAFT (`post_author` on `staff-gate-draft`, plus the `author-other` user): seed.php runs as an administrator, so a draft it authors itself can only be read by someone who can read every draft — the owner arm and the different-author arm are what separate viewer-relative from logged-in-relative, and the existing editor cannot serve as the negative because `edit_others_posts` reads the draft. Additive; `trash` joins the seed's explicit status lookup list, or a trashed fixture is re-created on every reseed. 13: SOURCE GATE corpus (1.18.0, ADR 0007) — three staff singles that differ ONLY in readability (`staff-gate-draft` draft, `staff-gate-private` private, `staff-gate-public` published) plus the `matrix-gate` page that references them, in that order, through `gate_staff`. The gate is viewer-RELATIVE, so the same tag on one page is two assertions: anonymous reads the published target, an administrator reads the draft (fold matrix §F17.1/§F17.2). `via_draft` points at the draft alone, so a chain THROUGH it is cut at the hop (§F17.4) while the draft's own `reports_to` proves the hop target is reachable when the viewer may read it (§F17.5). The third shape is EXISTENCE, not visibility: `stale_ref` is PLAIN meta (no ACF field — ACF's formatter drops a deleted post before the gate could see it) holding a genuinely deleted id ahead of a live one, seeded by creating a post and force-deleting it (§F17.3). Additive; post_status is a new per-post manifest key, absent = publish. 12: FIRST-USABLE corpus (slice A, ADR 0007) — `charter` term field seeded on department-warehouse ALONE (the last term alphabetically, so the §F15 term walk meets two empty reads first) + `feature_image` on staff-tom-associate (Jane, the first related_staff target, deliberately has none — the post-route {{image}} first-usable case). Additive. 11: 11: SCOPE-BOUND fixture root (1.17.0, #112) — `fixture_scoped`, a filter-route root that resolves on `/matrix-fixture-roots/` and answers FALSE everywhere else. Schema-only; no seeded state of its own (it reuses the class route's target). It is the #76 category-2 shape, and it is the only one of the three fixture roots that ever refuses: a registered source, correctly written, off its scope. That is the shape the census found in real wire — the other two always resolve, so before this the rendered refusal could only be reached by an UNREGISTERED token, which is a different decline. It must resolve on its own page too, or "refuses off its scope" reads identically to "is broken". 10: BLOCK PATTERN fixture (1.17.0, #99) — the `patterns` section plus seed.php's ADMINISTRATOR context, which is what makes it non-empty: GB Pro's cache listener is capability-gated, and a capability-less seed produces a wp_block with no `generateblocks_patterns_tree` row at all. The pattern carries a pre-1.6 tag name (so the converter always rewrites it, creating the divergence the reconcile repairs) and literal backslashes in both the block-comment JSON and the rendered code block (so the meta layer's recursive unslash has something to damage — a corpus without one passes the escaping assertion while testing nothing). Browsable with no blocks.php row by construction: a wp_block is in the pattern inserter and editable at Appearance → Patterns. 9: FIELD CONFIGURATION NOTE corpus (1.17.0, #96) — `partner_staff` on the page+staff group: a bidirectional relationship field with a configured limit of 3, self-targeting (a legitimate symmetric relationship, and one field instead of a pair). LABELLED "Partner Staff", as a real field would be — the picker row is the wrong place to state a configuration, since the note IS that statement. SCHEMA-ONLY BY CONSTRUCTION — the note is derived from field DEFINITIONS, never a value read, so there is nothing to seed and nothing for verify.php to assert; it exists to be picked in a `refs` step's field picker and read (fold matrix F14.16). The other cases already have fixtures: `lead_staff_obj` is a single-entry post object with no bidirectional setting (case 6), and `related_staff` must stay silent. 8: EXTERNAL-SOURCE contract corpus (1.17.0, #85) — the `fixture-root` staff post (the class-route root's target: its own main_line/role, `related_staff` leading with TOM where matrix-post-meta's leads with Jane, and the SALES department term alone where the matrix pages carry Support first) plus the `matrix-fixture-roots` page carrying the base-root rows, the folded-slot rows and the `fixture_*` MODIFIER corpus in the six shapes #84's transform maps. Every value on the target is deliberately distinct from the ambient page's: a root whose reads matched the current post would pass whether it resolved or not. The SOURCE and the modifier family are registered in schema.php; the filter-route root (`fixture_alt`) resolves the existing `sample-event` post and needs no new state. 7: SECOND-DEGREE relationship (1.17.0, #55) — `reports_to` (staff→staff) on staff-tom-associate, the only staff→staff link in the blueprint. Makes `src:refs,related_staff;refs,reports_to` expressible, which is the spec's OWN headline case ("the office of the staff member this event references") and was unexercised by every harness and matrix: every relationship value sat on matrix-post-meta, so no second hop had anywhere to land. Distinct field per step on purpose — hopping one field twice cannot distinguish composition from repetition. Jane deliberately has none, so the second step is sparse (F8.7/F8.8). 6: ref-hop RETURN-FORMAT coverage (1.17.0) — `related_staff_obj` (relationship, return_format object) + `lead_staff_obj` (post_object, object, singular) on matrix-post-meta, both carrying the SAME targets as `related_staff` so the hop is an equivalence assertion (fold matrix RF1/RF2). 5: FW-52 serialization-order editor fixtures — a seeded image attachment (`attachments`) + `feature_image` ACF image field on matrix-post-meta (backs the standalone {{image}} editor rows); the fw52-order section is editor-eyeball only. 4: author-archive context fixture (#19 author kind) — users section (author-fixture: display_name + bio, authors sample-event), department-sales term description, sample-event categoryless + portal-visible for the date archive. 3: datetime matrix fields — Event Schedule group (page+staff), dept event_date term field, org_party_datetime option, plain_meta_date. {CURRENT_YEAR} value token resolved at seed time.

	// Keys this blueprint DEFINES (collision rule: later blueprints must not
	// redefine these — compose + reuse instead).
	'defines'   => array(
		'post_types' => array( 'staff' ),
		'taxonomies' => array( 'department' ),
		'acf_groups' => array(
			'group_bwsfx_staff_contact',
			'group_bwsfx_site_settings',
			'group_bwsfx_event_details',
			'group_bwsfx_event_schedule',
			'group_bwsfx_team',
			'group_bwsfx_features',
			'group_bwsfx_page_builder',
			'group_bwsfx_department',
		),
		'registered_meta' => array( 'bws_global_note', 'bws_page_only', 'subtitle', 'bws_cat_note' ),
		'users'           => array( 'fixture-author' ),
	),

	// Fixture users. author-fixture is the author-archive context fixture
	// (context-test-matrix C3/C13): display_name + description (bio) meta give
	// the {{title}}/{{content}} author analogs a real payload, and it authors a
	// visible post so /author/fixture-author/ is non-empty under the
	// portal-system anonymous query filter.
	'users' => array(
		'author-fixture' => array(
			'user_login'   => 'fixture-author',
			'user_nicename' => 'fixture-author',
			'display_name' => 'Fixture Author',
			'user_email'   => 'author@example.test',
			'role'         => 'author',
			'description'  => 'Fixture Author writes the sample posts that back the author-archive context rows. This bio is the user description meta the {{content}} author analog reads.',
		),
		// The gate corpus's NEGATIVE viewer (v14, §F17.7). It owns nothing and
		// appears on no archive; its whole job is to be a logged-in user who still
		// cannot read another author's draft. The role is what makes it work: the
		// existing bwsut-editor has `edit_others_posts` and therefore READS the
		// draft, so using an editor here would measure the opposite fact and pass.
		'author-other'   => array(
			'user_login'    => 'fixture-other-author',
			'user_nicename' => 'fixture-other-author',
			'display_name'  => 'Other Author',
			'user_email'    => 'other-author@example.test',
			'role'          => 'author',
		),
	),

	// Seeded media attachments, keyed by stable fixture slug. seed.php generates a
	// tiny solid-color PNG in the uploads dir and registers it as an attachment
	// (idempotent — re-uses an existing attachment with the same _bws_fixture_slug
	// meta). Used as the target for image-field reads (FW-52 {{image}} editor rows).
	'attachments' => array(
		'fixture-photo' => array(
			'title'    => 'Fixture Photo',
			'alt'      => 'Fixture photo alt text',
			'caption'  => 'Fixture photo caption',
			'color'    => '4a90d9', // solid fill (hex, no #) so the generated PNG is deterministic.
			'filename' => 'fixture-photo.png',
		),
	),

	'terms' => array(
		'department-support'   => array(
			'taxonomy' => 'department',
			'name'     => 'Support',
			'slug'     => 'support',
		),
		'department-sales'     => array(
			'taxonomy'    => 'department',
			'name'        => 'Sales',
			'slug'        => 'sales',
			// Term description — makes the shipped term-kind {{content}} analog
			// (context-test-matrix C17) assert a value instead of vacuous-pass.
			'description' => 'The Sales department term. This description is the term-kind {{content}} analog on a /department/sales/ archive.',
		),
		'department-warehouse' => array(
			'taxonomy' => 'department',
			'name'     => 'Warehouse',
			'slug'     => 'warehouse',
		),
	),

	// Keyed by term fixture slug above.
	'term_fields' => array(
		'department-support'   => array(
			'phone'      => '(987) 111-2222',   // R3.2 valid
			'email'      => 'support@example.test',
			'event_date' => '20301005',         // datetime D4 valid
			// blurb deliberately ABSENT. It was seeded that way to make a term walk
			// visibly SKIP an empty term; the 2026-08-21 determinism reversal (ADR
			// 0007) removed the skip, and the absence now pins ORDER instead: WP
			// hands terms back by NAME, so Sales leads and carries the blurb, and a
			// read in ASSIGNMENT order (Support first) would print nothing (CT5,
			// fold F9a.4). Support and Sales must keep their present blurb state or
			// both rows pass whatever the order is.
		),
		'department-sales'     => array(
			'phone'      => '(987) 333-4444',   // R3.2 valid
			'email'      => 'sales@example.test',
			'blurb'      => 'Sales handles quotes, renewals and the annual customer roadshow.',
			'event_date' => '20301112',         // datetime D4 valid
		),
		'department-warehouse' => array(
			'phone'      => 'abc',              // R3.3 junk — skipped in list mode
			'email'      => 'warehouse@example.test',
			// v12 first-usable (§F15): warehouse is the LAST department term
			// alphabetically, and it is the ONLY term carrying `charter` — so on
			// /matrix-terms-mixed/ (Sales, Support, Warehouse) a collapsing tag
			// meets two empty reads before this value. Support and Sales must NOT
			// gain one, or the walk stops early and the rows pass vacuously.
			'charter'    => 'Warehouse charter: same-day dispatch for stocked items.',
			// datetime D4 "junk" = EMPTY, not a junk string: ACF formats a junk
			// stored value in a date field to TODAY's date before the tag sees it
			// (upstream of the parse chain), so a junk string is untestable as a
			// skip case. Empty is the real-world skippable state.
			'event_date' => '',
		),
	),

	'posts' => array(
		// Relationship target for src:ref rows (R4.4).
		'staff-jane-partner' => array(
			'post_type'       => 'staff',
			'post_name'       => 'jane-partner',
			'post_title'      => 'Jane Partner',
			'content_builder' => 'staff_join', // join NAME rows (sparse data → collapsed output) — join-test-matrix.md
		),

		// Second relationship target — src:ref LIST mode rows need >1 related
		// post to distinguish all-results from first-only (text matrix T7).
		'staff-tom-associate' => array(
			'post_type'       => 'staff',
			'post_name'       => 'tom-associate',
			'post_title'      => 'Tom Associate',
			'content_builder' => 'staff_join', // same rows, dense data → full-name output (J21 stress)
		),

		// Matrix pages split BY SOURCE-STATE, not by tag family — every tag
		// family adds its rows to the page whose carried state it needs.

		// Explicit reads off the current post: full field value set, src:site,
		// src:ref relationship. Also carries VALID department terms (R3.2).
		'page-matrix-post-meta' => array(
			'post_type'       => 'page',
			'post_name'       => 'matrix-post-meta',
			'post_title'      => 'Matrix: Post Meta',
			'content_builder' => 'matrix_post_meta',
		),

		// Content-family page (content-test-matrix.md). Split off because a hopped
		// {{content}} renders another entity's whole block set inline: on a shared
		// page two entities' values land on one screen and neither is legible.
		'page-matrix-content' => array(
			'post_type'       => 'page',
			'post_name'       => 'matrix-content',
			'post_title'      => 'Matrix: Content',
			'content_builder' => 'matrix_content',
		),

		// Term-hop, all assigned terms valid.
		'page-matrix-terms-valid' => array(
			'post_type'       => 'page',
			'post_name'       => 'matrix-terms-valid',
			'post_title'      => 'Matrix: Terms (all valid)',
			'content_builder' => 'matrix_term_hop',
		),

		// Term-hop, one junk term among valid (R3.3 junk-skip).
		'page-matrix-terms-mixed' => array(
			'post_type'       => 'page',
			'post_name'       => 'matrix-terms-mixed',
			'post_title'      => 'Matrix: Terms (mixed junk)',
			'content_builder' => 'matrix_term_hop',
		),

		// Term-hop, ONLY junk terms → fallback fires (R3.4).
		'page-matrix-terms-junk' => array(
			'post_type'       => 'page',
			'post_name'       => 'matrix-terms-junk',
			'post_title'      => 'Matrix: Terms (all junk)',
			'content_builder' => 'matrix_term_hop',
		),

		// The CLASS-ROUTE root's target (#85). `BWS_Fixture_Root_Source::resolve_id()`
		// looks this up by slug, so the root answers the same entity on every request —
		// which is what makes a fixture row assertable where the real external source
		// (request-state dependent) cannot be.
		'staff-fixture-root' => array(
			'post_type'  => 'staff',
			'post_name'  => 'fixture-root',
			'post_title' => 'Fixture Root Entity',
		),

		// The root's RELATIONSHIP target (#85). It exists because the "both sidecars"
		// migration shape needs a hop target that CARRIES A TERM, and neither existing
		// staff single does — giving one a department would have changed fixture state
		// every other matrix reads. Its term (Warehouse) is the third distinct value in
		// the page → root → hop-target chain, so a taxonomy step landing on the wrong
		// entity names the wrong department instead of the right one.
		'staff-fixture-ref' => array(
			'post_type'  => 'staff',
			'post_name'  => 'fixture-ref',
			'post_title' => 'Fixture Ref Target',
		),

		// The external-source corpus page (#85): base-tag root rows, folded-slot rows,
		// and the fixture_* modifier tags in the six shapes the migration maps. Its own
		// ambient values are matrix-post-meta's, so every row here contrasts a rooted
		// read against the unrooted one beside it.
		'page-matrix-fixture-roots' => array(
			'post_type'       => 'page',
			'post_name'       => 'matrix-fixture-roots',
			'post_title'      => 'Matrix: Fixture Roots',
			'content_builder' => 'matrix_fixture_roots',
		),

		// --- SOURCE GATE corpus (v13, ADR 0007) ------------------------------
		// Three staff singles that differ in ONE property — whether an anonymous
		// visitor may read them. Everything else about them is deliberately alike,
		// because the rows assert WHICH entity was read: same field key, same
		// position in one relationship value, distinct first names.
		//
		// A DRAFT and a PRIVATE post rather than one of each kind of unreadable:
		// they fail the gate through different halves of `is_post_status_viewable`
		// and a fixture with only one of them passes with the other's arm deleted.
		// AUTHORED BY A NON-ADMIN (v14): the viewer-relative arm is "who is asking",
		// and seed.php runs as an administrator, so a draft it authors itself can
		// only ever be read by users who can read EVERY draft. Owned by
		// author-fixture, §F17.6 measures the author previewing their own draft and
		// §F17.7 measures a different author failing to.
		'staff-gate-draft'   => array(
			'post_type'   => 'staff',
			'post_name'   => 'gate-draft',
			'post_title'  => 'Dana Draft',
			'post_status' => 'draft',
			'post_author' => 'author-fixture',
		),
		'staff-gate-private' => array(
			'post_type'   => 'staff',
			'post_name'   => 'gate-private',
			'post_title'  => 'Paul Private',
			'post_status' => 'private',
		),
		'staff-gate-public'  => array(
			'post_type'  => 'staff',
			'post_name'  => 'gate-public',
			'post_title' => 'Grace Published',
		),

		// TRASHED (v14, §F17.8). The one status where EXISTS passes and VISIBLE
		// fails for every viewer including an administrator, whose `read_post` WP
		// maps toward `edit_post` and answers true — so a gate that honoured the
		// capability alone would render it to editors and to nobody else. Distinct
		// from the {DELETED_POST_ID} throwaway, which is force-deleted precisely
		// BECAUSE a trashed post still exists; this row needs the surviving one.
		'staff-gate-trashed' => array(
			'post_type'   => 'staff',
			'post_name'   => 'gate-trashed',
			'post_title'  => 'Trish Trashed',
			'post_status' => 'trash',
		),

		// The gate corpus page (§F17). Its own values are NOT read by any gate row —
		// every row here hops — so an ambient leak prints nothing rather than
		// something plausible, and the rows that expect empty stay meaningful.
		'page-matrix-gate' => array(
			'post_type'       => 'page',
			'post_name'       => 'matrix-gate',
			'post_title'      => 'Matrix: Source Gate',
			'content_builder' => 'matrix_gate',
		),

		// Editor-side discovery post: Event Details + repeater values live here.
		// ALSO the date-archive context fixture (context-test-matrix C-rows):
		// seed keeps it categoryless + portal-visible so /2026/07/ has results
		// under the portal-system anonymous query filter (see seed.php).
		'post-sample-event' => array(
			'post_type'   => 'post',
			'post_name'   => 'sample-event',
			'post_title'  => 'Sample Event',
			// Authored by the fixture user so /author/fixture-author/ has a
			// visible post (author-archive context fixture, C3/C13).
			'post_author' => 'author-fixture',
		),
	),

	// Block patterns (wp_block posts). SEPARATE from 'posts' because the seeding is
	// different in kind, not just in post_type: a pattern is only useful here once
	// GenerateBlocks Pro's after_save listener has built its cache entry, and that listener
	// is capability-gated — so seed.php's administrator context (step 0) is what makes this
	// section produce anything at all. Measured: with no current user a seeded wp_block gets
	// NO generateblocks_patterns_tree row whatever.
	//
	// The blueprint's own division still holds: markup lives in blocks.php, this is data.
	'patterns' => array(
		// #99's fixture. It exists to be DIVERGED: the wire is a pre-1.6 tag name, so the
		// converter rewrites it, and GB Pro's cached copy — written once at save and never
		// rebuilt on read — then holds the pre-migration string while post_content holds the
		// new one. That divergence is the whole defect, and this is where it is reachable.
		//
		// BROWSABLE BY CONSTRUCTION, which is why it needs no blocks.php page row: a
		// wp_block shows up in the block editor's pattern inserter and is editable at
		// Appearance → Patterns. Inserting it before a reconcile is the defect demo (it
		// seeds pre-migration wire into fresh content); inserting it after is the fix.
		'pattern-legacy-wire' => array(
			'post_name'       => 'bws-fixture-legacy-wire',
			'post_title'      => 'BWS Fixture: Legacy Wire',
			'content_builder' => 'pattern_legacy_wire',
		),
	),

	// Post → department term assignment (fixture slugs).
	'post_terms' => array(
		'page-matrix-post-meta'   => array( 'department-support', 'department-sales' ),
		'page-matrix-content'     => array( 'department-support', 'department-sales' ),
		'page-matrix-terms-valid' => array( 'department-support', 'department-sales' ),
		'page-matrix-terms-mixed' => array( 'department-support', 'department-sales', 'department-warehouse' ),
		'page-matrix-terms-junk'  => array( 'department-warehouse' ),
		// #85 contrast pair: the corpus page carries SUPPORT, the root target carries
		// SALES alone. A taxonomy-step row through the root must therefore name Sales,
		// and the ambient row beside it Support — identical term sets would make both
		// rows pass with the step resolving the wrong entity.
		'page-matrix-fixture-roots' => array( 'department-support' ),
		'staff-fixture-root'        => array( 'department-sales' ),
		'staff-fixture-ref'         => array( 'department-warehouse' ),
	),

	// ACF field values per post fixture slug (applied via update_field).
	'post_fields' => array(
		'staff-jane-partner' => array(
			'main_line'     => '(555) 200-3000',
			'contact_email' => 'jane@example.test',
			// join matrix — SPARSE person (first+last only; honorific / middle /
			// generation / credential / service stay unseeded → '' reads, J22).
			// A generation suffix is implausible on this name, so the DENSE
			// full-name stress fixture lives on Tom (male) instead; Jane is the
			// sparse-collapse case. related_staff/main_line/contact_email rows
			// (J16b, R4.4) are untouched — they read phone/email, not name_*.
			'name_first' => 'Jane',
			'name_last'  => 'Johnson',
			// datetime matrix — src:ref list rows (D4/D5): distinct per-staff
			// datetime pair; jane is the FIRST related_staff target (limit:1 pins her).
			'event_datetime'     => '2030-05-01 10:00:00',
			'event_end_datetime' => '2030-05-03 15:00:00',
		),

		'staff-tom-associate' => array(
			'main_line'     => '(555) 200-4000',
			'contact_email' => 'tom@example.test',
			// SECOND-DEGREE relationship (#55): the only staff→staff link in the
			// blueprint, and the whole point of it is that `src:refs,related_staff;
			// refs,reports_to` from matrix-post-meta has somewhere to land. An
			// associate reporting to a partner is the plausible direction; Jane is
			// deliberately left with NO `reports_to`, so the second step is sparse
			// and the chain also pins that an empty second degree DROPS rather than
			// erroring (fold matrix F8.7).
			'reports_to'    => array( 'staff-jane-partner' ),
			// v12 first-usable (§F15): Tom carries the ONLY staff feature_image, and
			// Jane (the FIRST related_staff target) deliberately none — the post-route
			// {{image}} first-usable case. The slug resolves to the seeded attachment.
			'feature_image' => 'fixture-photo',
			// join matrix — DENSE full personal name (J21 stress fixture). Male
			// name carries a plausible generation suffix.
			'name_honorific'      => 'Dr.',
			'name_first'          => 'Tom',
			'name_middle_initial' => 'M',
			'name_last'           => 'Smith',
			'name_generation'     => 'Jr.',
			'name_credential'     => 'PhD',
			'name_service'        => 'USN (Ret.)',
			// datetime matrix — second src:ref target (list rows show both dates).
			'event_datetime'     => '2030-06-01 11:00:00',
			'event_end_datetime' => '2030-06-05 12:00:00',
		),

		'page-matrix-post-meta' => array(
			// R0 — href rebuild, global CC 1, strip OFF
			'main_line'        => '(987) 654-3210',            // R0.1
			'booking_line'     => '987.654.3210',              // R0.2
			'after_hours_line' => '(987)654-3210',             // R0.3
			'sms_number'       => '9876543210',                // R0.4, R1.5
			'intl_desk'        => '987 654 3210',              // R0.5
			// R1 — CC 2-tier + trunk-0
			'us_toll_free'     => '+1 987 654 3210',           // R1.1
			'intl_exchange'    => '0011 22 3333',              // R1.2
			'uk_mobile'        => '07911 123456',              // R1.3, R1.4, R3.1
			// R2 — separated-CC dedupe
			'support_tollfree' => '1-800-555-1212',            // R2.1, R2.2, R2.6
			'sales_tollfree'   => '1 (800) 555-1212',          // R2.3
			'fax_tollfree'     => '1.800.555.1212',            // R2.4
			'intl_support'     => '12-800-5551',               // R2.5
			// R2b — flat leading CC
			'flat_tollfree'    => '18005551212',               // R2b.1, R2b.2, R2b.4
			'flat_local'       => '8005551212',                // R2b.3
			// R3/R4/R6 — edge values
			'front_desk_ext'   => '555-867-5309 x99',          // R4.1
			'unused_line'      => '',                          // R3.5 empty
			'short_code'       => '12345',                     // R3.6 length gate
			'hacked_line'      => '+1-987"><script>654-3210',  // R6.1
			'related_staff'    => array( 'staff-jane-partner', 'staff-tom-associate' ), // R4.4 + text T7 list mode (slugs resolved to IDs at seed; jane FIRST — limit:1 rows pin her)
			// SAME targets, SAME order, ACF's other return_format (RF1) — the hop's
			// output must be byte-identical to related_staff's, which is the whole
			// assertion. lead_staff_obj (RF2) is post_object, so it is singular and
			// pins jane alone.
			'related_staff_obj' => array( 'staff-jane-partner', 'staff-tom-associate' ),
			'lead_staff_obj'    => 'staff-jane-partner',
			// join matrix — post-arm context rows. name_first is a deliberate
			// slot-1 value for the ref/site-hop rows (J17/J18) — distinct entity
			// from the staff name_first; the OTHER name_* parts stay unseeded on
			// this page (J13's name_generation slot reads empty here).
			'role'            => 'Captain',                    // J4, J15
			'name_first'      => 'Jane',                       // J17, J18
			'height_ft'       => '5',                          // J11-J14
			'height_in'       => '11',                         // J11
			'height_in_blank' => '',                           // J12/J13 dangling-quote drop
			'height_in_zero'  => '0',                          // J14 absorbed-'0' renders
			// datetime matrix (D-rows) — Event Schedule group. Fixed 2030 values;
			// {CURRENT_YEAR} resolves to the seed-time year (showCurrentYear rows).
			'event_datetime'     => '2030-08-12 09:00:00',     // D0/D1/D2/D3
			'event_end_datetime' => '2030-08-12 17:00:00',     // D2/D3 (cross-meridiem pair)
			'event_time'         => '09:00:00',                // D0.8, D2.6/D3.3 (same-meridiem pair)
			'event_end_time'     => '11:30:00',                // D2.6/D3.3
			'event_start_date'   => '20300801',                // D0.1, D2.1 (same-month range)
			'event_end_date'     => '20300809',                // D2.1
			'event_midnight'     => '2030-08-12 00:00:00',     // D0.6/D0.7, D2.4/D3.4
			'event_thisyear'     => '{CURRENT_YEAR}0410',      // D0.4/D0.5
			'event_date_dmy'     => '20300815',                // D0.11 non-default return_format
			// FW-52 image editor rows — attachment ID target for {{image}} (as:url /
			// as:alt / as:id / as:caption). Seed resolves the fixture slug → the
			// seeded attachment's ID (like related_staff slug→ID resolution).
			'feature_image'      => 'fixture-photo',
			// {{table}} matrix (1.17.0) — team_members repeater on the PAGE so a bare
			// {{table}} reads its own current-post. name/description/role feed TB1/TB2
			// scalar columns; lead_ref is a relationship sub-field (staff slug → ID at
			// seed) feeding TB3's use:title ref-hop column. Row 2 leaves lead_ref blank
			// to prove the ref-title column degrades to '' on a missing relationship.
			// `qty` is the zero-guard sub-field (text matrix T5.5): row 1 holds the string
			// '0', row 2 a non-zero, so one loop shows the guard acting per VALUE. No
			// {{table}} column and no F9c row names it.
			'team_members'       => array(
				array( 'name' => 'Alice Adams', 'description' => 'Founding partner', 'role' => 'Engineering', 'lead_ref' => 'staff-jane-partner', 'qty' => '0' ),
				array( 'name' => 'Bob Brown',   'description' => 'Support lead',     'role' => 'Operations',  'lead_ref' => '', 'qty' => '4' ),
			),
		),

		// content matrix (CT rows). Deliberately DISTINCT values from the staff
		// singles': the property under test is which entity a hopped {{content}}
		// lands on, and identical values on both sides would make a wrong entity
		// unreadable. name_first/name_last feed jane's join rows when they render
		// from HER content — if those rows show these values the hop leaked (#58).
		'page-matrix-content' => array(
			'main_line'      => '(321) 555-0100',   // CT6 ambient contrast
			'related_staff'  => array( 'staff-jane-partner', 'staff-tom-associate' ), // CT1-CT4 hop target (jane FIRST)
			'name_first'     => 'Pagefirst',
			'name_last'      => 'Pagelast',
		),

		// #85 — the class-route root's target. Every value here is deliberately UNLIKE
		// the corpus page's below, and `related_staff` leads with TOM where every other
		// fixture's leads with Jane: a limit:1 hop through this root that came back
		// "Jane Partner" resolved the ambient post, not the root.
		'staff-fixture-root' => array(
			'main_line'     => '(555) 700-1000',
			'role'          => 'Fixture Root Role',
			'contact_email' => 'root@example.test',
			// Leads with the fixture ref target — the only hop target carrying a term,
			// so the "relationship then taxonomy" shape resolves without a limit.
			'related_staff' => array( 'staff-fixture-ref', 'staff-tom-associate' ),
		),

		'staff-fixture-ref' => array(
			'main_line' => '(555) 700-2000',
			'role'      => 'Fixture Ref Role',
		),

		// #85 — the corpus page's OWN values: the ambient contrast every rooted row on
		// the page is read against.
		'page-matrix-fixture-roots' => array(
			'main_line'     => '(444) 000-1111',
			'role'          => 'Ambient Page Role',
			'related_staff' => array( 'staff-jane-partner' ),
		),

		// --- SOURCE GATE corpus (v13) ----------------------------------------
		// Distinct first names, one field key. A row's expected value NAMES the
		// entity that was read, so a gate that let the wrong one through prints the
		// wrong word instead of an empty string.
		'staff-gate-draft'   => array(
			'name_first' => 'Dana',
			// The draft's own hop target — published, and reachable ONLY through the
			// draft. §F17.4's empty is therefore about the stepping stone, not about
			// the destination, and §F17.5 (same tag, administrator) proves it.
			'reports_to' => array( 'staff-gate-public' ),
		),
		'staff-gate-private' => array(
			'name_first' => 'Paul',
		),
		'staff-gate-public'  => array(
			'name_first' => 'Grace',
		),
		'staff-gate-trashed' => array(
			'name_first' => 'Trish',
		),

		'page-matrix-gate' => array(
			// Draft first, private second, published third — the ORDER is the
			// fixture. A gate that ignored status reads Dana for everyone; one
			// that dropped the viewer-relative arm reads Grace for everyone.
			'gate_staff' => array( 'staff-gate-draft', 'staff-gate-private', 'staff-gate-public' ),
			'via_draft'  => array( 'staff-gate-draft' ),
			// The ATTACHMENT arm (v14, §F17.9). An attachment stores post_status
			// `inherit`, which is an INTERNAL status: a gate that tests the raw
			// column reads "not publicly viewable", falls to the capability, and
			// drops every attachment for logged-out visitors. Shipped nowhere, but
			// live for a whole branch, and invisible to every other row because
			// plain {{image}} reads never enter the traversal pipeline. This row
			// hops onto the attachment AS A SOURCE, which is the only way to see it.
			'feature_image' => 'fixture-photo',
		),

		'post-sample-event' => array(
			'event_date'    => '20260901',
			'Event_Date'    => 'September 2026',
			'venue_city'    => 'Chatham',
			'subtitle'      => 'A fixture event',
			'contact_email' => 'events@example.test',
			'escape_probe'  => 'escape probe value',
			'team_members'  => array(
				array( 'name' => 'Alice Adams', 'description' => 'Lead', 'role' => 'Engineering' ),
				array( 'name' => 'Bob Brown', 'description' => 'Support', 'role' => 'Operations' ),
			),
			'feature_list'  => array(
				array( 'name' => 'Fast setup', 'description' => 'Lead' ),
			),
		),
	),

	// Plain post meta (update_post_meta, NOT ACF) — registered-meta rows M9.
	'post_meta' => array(
		'post-sample-event' => array(
			'bws_global_note' => 'global note value',
			'subtitle'        => 'registered subtitle value', // overwritten by ACF value above where both apply
		),
		// The EXISTENCE half of the gate corpus (v13, §F17.3). PLAIN meta on purpose:
		// this is the one gate shape ACF cannot carry, because ACF's own relationship
		// formatter drops an id whose post is gone — so an ACF-backed fixture would
		// pass with the engine gate deleted. Plain meta reaches the reader's raw
		// get_post_meta fallback with the dead id intact.
		//
		// {DELETED_POST_ID} resolves at seed time by creating a post and force-deleting
		// it, so the id is genuinely dead rather than merely high (a hardcoded number
		// starts failing the day the site grows past it, and passes vacuously until then).
		'page-matrix-gate' => array(
			'stale_ref' => array( '{DELETED_POST_ID}', 'staff-gate-public' ),
			// The TRASHED arm (v14, §F17.8), plain meta for the same reason as
			// stale_ref: ACF's relationship formatter runs its own query, and what
			// it drops before the gate sees it is exactly what the row measures.
			// Trashed first, published second: the assertion is that the trashed
			// source is dropped for BOTH viewers and spends no limit budget, so a
			// `limit:1` still reaches Grace.
			'trash_ref' => array( 'staff-gate-trashed', 'staff-gate-public' ),
		),
		'page-matrix-post-meta' => array(
			'bws_page_only'  => 'page-only note value',
			'bws_zero_probe' => '0', // text matrix T5 — '0' is a REAL value, must render
			'plain_meta_date' => '2030-06-15', // datetime D0.10 — plain-meta (non-ACF) read path
		),
	),

	// Plain wp_options, MERGED recursively into any existing value (plugin
	// settings baseline — matrix default state: global CC 1, strip OFF; rows
	// that need other states toggle in the UI per the matrix).
	'wp_options' => array(
		'bws_dynamic_tags_settings' => array(
			'phone' => array(
				'country_code'     => '1',
				'strip_leading_cc' => false,
			),
		),
	),

	// Options-page ACF fields (update_field with 'option').
	'option_fields' => array(
		'org_phone'                  => '(987) 555-0000',      // R4.2
		'organization_phone_display' => '(800) 555-9999',
		'organization_email'         => 'info@example.test',
		'organization_address'       => "123 Fixture Lane\nChatham, NC 27517",
		'organization_founded'       => '20200115',
		'org_party_datetime'         => '2030-09-20 18:00:00', // datetime D5 src:site
		'org_conference_start'       => '2030-09-20 09:00:00', // R8 site datetime_range pair
		'org_conference_end'         => '2030-09-22 17:00:00', // R8 site datetime_range pair

		'organization_social'        => array( 'facebook' => 'https://facebook.example.test/org' ),
	),
);
