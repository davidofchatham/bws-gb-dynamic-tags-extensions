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
	'version'   => 3, // 3: datetime matrix fields — Event Schedule group (page+staff), dept event_date term field, org_party_datetime option, plain_meta_date. {CURRENT_YEAR} value token resolved at seed time.

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
	),

	'terms' => array(
		'department-support'   => array(
			'taxonomy' => 'department',
			'name'     => 'Support',
			'slug'     => 'support',
		),
		'department-sales'     => array(
			'taxonomy' => 'department',
			'name'     => 'Sales',
			'slug'     => 'sales',
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
		),
		'department-sales'     => array(
			'phone'      => '(987) 333-4444',   // R3.2 valid
			'email'      => 'sales@example.test',
			'event_date' => '20301112',         // datetime D4 valid
		),
		'department-warehouse' => array(
			'phone'      => 'abc',              // R3.3 junk — skipped in list mode
			'email'      => 'warehouse@example.test',
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

		// Editor-side discovery post: Event Details + repeater values live here.
		// ALSO the date-archive context fixture (context-test-matrix C-rows):
		// seed keeps it categoryless + portal-visible so /2026/07/ has results
		// under the portal-system anonymous query filter (see seed.php).
		'post-sample-event' => array(
			'post_type'  => 'post',
			'post_name'  => 'sample-event',
			'post_title' => 'Sample Event',
		),
	),

	// Post → department term assignment (fixture slugs).
	'post_terms' => array(
		'page-matrix-post-meta'   => array( 'department-support', 'department-sales' ),
		'page-matrix-terms-valid' => array( 'department-support', 'department-sales' ),
		'page-matrix-terms-mixed' => array( 'department-support', 'department-sales', 'department-warehouse' ),
		'page-matrix-terms-junk'  => array( 'department-warehouse' ),
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

		'organization_social'        => array( 'facebook' => 'https://facebook.example.test/org' ),
	),
);
