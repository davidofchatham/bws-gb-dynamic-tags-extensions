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
	'version'   => 1,

	// Keys this blueprint DEFINES (collision rule: later blueprints must not
	// redefine these — compose + reuse instead).
	'defines'   => array(
		'post_types' => array( 'staff' ),
		'taxonomies' => array( 'department' ),
		'acf_groups' => array(
			'group_bwsfx_staff_contact',
			'group_bwsfx_site_settings',
			'group_bwsfx_event_details',
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
			'phone' => '(987) 111-2222',   // R3.2 valid
			'email' => 'support@example.test',
		),
		'department-sales'     => array(
			'phone' => '(987) 333-4444',   // R3.2 valid
			'email' => 'sales@example.test',
		),
		'department-warehouse' => array(
			'phone' => 'abc',              // R3.3 junk — skipped in list mode
			'email' => 'warehouse@example.test',
		),
	),

	'posts' => array(
		// Relationship target for src:ref rows (R4.4).
		'staff-jane-partner' => array(
			'post_type'  => 'staff',
			'post_name'  => 'jane-partner',
			'post_title' => 'Jane Partner',
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
			'related_staff'    => array( 'staff-jane-partner' ), // R4.4 (slug resolved to ID at seed)
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
			'bws_page_only' => 'page-only note value',
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
		'organization_social'        => array( 'facebook' => 'https://facebook.example.test/org' ),
	),
);
