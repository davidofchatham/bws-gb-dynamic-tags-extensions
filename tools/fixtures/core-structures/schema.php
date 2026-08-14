<?php
/**
 * core-structures blueprint — schema.
 *
 * Fixture-defining code for the core-structures blueprint: CPT + taxonomy registration,
 * ACF field groups (incl. the collision repeaters and flex fields the
 * field-selector matrix needs), the options page, and registered meta.
 *
 * Loaded two ways:
 *  - at runtime by the mu-plugin loader stub seed.php installs (hooks init/acf)
 *  - directly by seed.php during seeding (functions called immediately)
 *
 * Data lives in manifest.php; this file is code only.
 *
 * Consumed by: tools/test/phone-test-matrix.md, tools/test/field-selector-test-matrix.md,
 * tools/test/text-test-matrix.md, tools/test/join-test-matrix.md.
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_CLI' ) ) {
	exit;
}

/**
 * CPT + taxonomy.
 *
 * staff      — relationship-field target (src:ref rows).
 * department — term-hop taxonomy (srcTermIn rows + ambient term archive).
 */
function bws_fixture_core_structures_register_types() {
	register_post_type(
		'staff',
		array(
			'label'        => 'Staff',
			'public'       => true,
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
			'has_archive'  => true,
		)
	);

	register_taxonomy(
		'department',
		array( 'post', 'page', 'staff' ),
		array(
			'label'        => 'Departments',
			'public'       => true,
			'show_in_rest' => true,
			'hierarchical' => true,
		)
	);
}

/**
 * Registered (non-ACF) meta — field-selector matrix M9.
 *
 * bws_global_note — global registered post meta (M9.1).
 * bws_page_only   — subtype-registered, page only (M9.2 / B8).
 * subtitle        — global registered key COLLIDING with the ACF `subtitle`
 *                   field on post (M9.3 / B7: differing reach keeps both).
 * bws_cat_note    — subtype-registered term meta on category (M9.5).
 */
function bws_fixture_core_structures_register_meta() {
	register_post_meta( '', 'bws_global_note', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	register_post_meta( 'page', 'bws_page_only', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	register_post_meta( '', 'subtitle', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
	register_term_meta( 'category', 'bws_cat_note', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true ) );
}

/**
 * ACF field groups.
 *
 * Group inventory (matrix rows each serves):
 *  Staff Contact         — phone-format value fields (phone matrix R0–R6) + contact_email
 *                          (second home, M3.3) + related_staff relationship (R4.4).
 *  Site Settings         — options page fields (R4.2, src:site rows, corpus organization_* keys).
 *  Event Details         — type variety (M2.3), label-falls-back-to-key (M3.4 event_date),
 *                          case-variant key (M1.6 Event_Date), substring label (M1.5 venue_city),
 *                          registered-meta collision (M9.3 subtitle), </script> label (M8.2).
 *  Team / Product Features — the two collision repeaters (M3.1 name, M3.2 description).
 *  Page Builder          — two flex fields, each with a Hero layout + headline (M2.6).
 *  Department Details    — term meta fields on the department taxonomy (R3.2–R3.4, M9.5 area).
 */
function bws_fixture_core_structures_register_acf() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page(
			array(
				'page_title' => 'Site Settings',
				'menu_title' => 'Site Settings',
				'menu_slug'  => 'site-settings',
				'capability' => 'manage_options',
			)
		);
	}

	$text = function ( $name, $label, $extra = array() ) {
		return array_merge(
			array(
				'key'   => 'field_bwsfx_' . strtolower( $name ),
				'name'  => $name,
				'label' => $label,
				'type'  => 'text',
			),
			$extra
		);
	};

	// --- Staff Contact (page + staff) — phone matrix value set. ---
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_staff_contact',
			'title'    => 'Staff Contact',
			'fields'   => array(
				$text( 'main_line', 'Main Line' ),
				$text( 'booking_line', 'Booking Line' ),
				$text( 'after_hours_line', 'After Hours Line' ),
				$text( 'sms_number', 'SMS Number' ),
				$text( 'intl_desk', 'International Desk' ),
				$text( 'us_toll_free', 'US Toll-Free' ),
				$text( 'intl_exchange', 'International Exchange' ),
				$text( 'uk_mobile', 'UK Mobile' ),
				$text( 'support_tollfree', 'Support Toll-Free' ),
				$text( 'sales_tollfree', 'Sales Toll-Free' ),
				$text( 'fax_tollfree', 'Fax Toll-Free' ),
				$text( 'intl_support', 'International Support' ),
				$text( 'flat_tollfree', 'Flat Toll-Free' ),
				$text( 'flat_local', 'Flat Local' ),
				$text( 'front_desk_ext', 'Front Desk (ext)' ),
				$text( 'unused_line', 'Unused Line' ),
				$text( 'short_code', 'Short Code' ),
				$text( 'hacked_line', 'Hacked Line' ),
				// --- join matrix (J-rows) — person-name parts (dense on tom,
				// sparse on jane), role, and height unit-suffix fields. ---
				$text( 'name_honorific', 'Honorific' ),
				$text( 'name_first', 'First Name' ),
				$text( 'name_middle_initial', 'Middle Initial' ),
				$text( 'name_last', 'Last Name' ),
				$text( 'name_generation', 'Generation' ),
				$text( 'name_credential', 'Credential' ),
				$text( 'name_service', 'Service/Status' ),
				$text( 'role', 'Role' ),
				$text( 'height_ft', 'Height (ft)' ),
				$text( 'height_in', 'Height (in)' ),
				$text( 'height_in_blank', 'Height (in, blank)' ),
				$text( 'height_in_zero', 'Height (in, zero)' ),
				array(
					'key'   => 'field_bwsfx_staff_contact_email',
					'name'  => 'contact_email',
					'label' => 'Contact Email',
					'type'  => 'email',
				),
				array(
					'key'           => 'field_bwsfx_related_staff',
					'name'          => 'related_staff',
					'label'         => 'Related Staff',
					'type'          => 'relationship',
					'post_type'     => array( 'staff' ),
					'return_format' => 'id',
				),
				// --- ref-hop RETURN-FORMAT coverage (1.17.0) ---------------------
				// bws_get_related_posts_data type-guards relationship|post_object and
				// bws_pipeline_ref_to_posts / bws_extract_post_id handle WP_Post as
				// well as ids, but every fixture field above returns an ID — so the
				// object arms were pure-harness-only, asserted against a test shim's
				// GUESS at what ACF hands back. These two fields carry the same
				// targets in the other format, which makes the ref hop's output an
				// EQUIVALENCE assertion (matrix rows RF1/RF2) with no new expected
				// values to maintain.
				array(
					'key'           => 'field_bwsfx_related_staff_obj',
					'name'          => 'related_staff_obj',
					'label'         => 'Related Staff (object format)',
					'type'          => 'relationship',
					'post_type'     => array( 'staff' ),
					'return_format' => 'object',
				),
				// post_object + object is the ONLY fixture shape that (a) exercises
				// the post_object half of the type guard and (b) returns ONE WP_Post
				// rather than a list, which is what reaches the non-array wrap in
				// bws_get_related_posts_data. Singular by construction, so it is also
				// the cheap probe for a fan-out gate keyed on cardinality.
				array(
					'key'           => 'field_bwsfx_lead_staff_obj',
					'name'          => 'lead_staff_obj',
					'label'         => 'Lead Staff (post object, object format)',
					'type'          => 'post_object',
					'post_type'     => array( 'staff' ),
					'return_format' => 'object',
				),
				// --- SECOND-DEGREE relationship (1.17.0, #55) --------------------
				// The field that makes a TWO-RELATIONSHIP chain expressible in the
				// fixture: `src:refs,related_staff;refs,reports_to`. Distinct from
				// the first step's field ON PURPOSE — a chain that hops the same
				// field twice passes even if the second step re-reads the first
				// step's, so it cannot tell composition from repetition.
				//
				// This group is located on page AND staff, so `related_staff` was
				// already reachable on a staff post; what was missing was any
				// SECOND relationship to hop to. Every relationship value in the
				// blueprint sat on `matrix-post-meta`, which left the spec's own
				// headline case ("the office of the staff member this event
				// references") unexercised by every harness and matrix.
				array(
					'key'           => 'field_bwsfx_reports_to',
					'name'          => 'reports_to',
					'label'         => 'Reports To',
					'type'          => 'relationship',
					'post_type'     => array( 'staff' ),
					'return_format' => 'id',
				),
				// --- FIELD CONFIGURATION NOTE corpus (1.17.0, #96) ---------------
				// The one shape the blueprint could not otherwise show: a
				// BIDIRECTIONAL relationship field with a CONFIGURED LIMIT, which is
				// note case 1 — the only case naming both a limit and a reciprocal
				// writer. The note is derived from DEFINITIONS ONLY, so this field
				// needs no seeded value; it exists to be picked in a `refs` step's
				// field picker and read (fold matrix F14.16).
				//
				// SELF-TARGETING is a legitimate ACF configuration (a symmetric
				// relationship) and is what keeps this to one field instead of a pair
				// whose only purpose is to be pointed at.
				//
				// The other cases already have fixtures: `lead_staff_obj` above is a
				// single-entry post object with no bidirectional setting (case 6, the
				// emphasised one), and `related_staff` is a plain relationship with
				// neither setting, which must stay SILENT.
				array(
					'key'                  => 'field_bwsfx_partner_staff',
					'name'                 => 'partner_staff',
					'label'                => 'Partner Staff (bidirectional, limit 3)',
					'type'                 => 'relationship',
					'post_type'            => array( 'staff' ),
					'return_format'        => 'id',
					'max'                  => 3,
					'bidirectional'        => 1,
					'bidirectional_target' => array( 'field_bwsfx_partner_staff' ),
				),
				// FW-52 image editor rows — an ACF image field returning the
				// attachment ID, so {{image use:key|key:feature_image}} resolves a
				// real attachment for the as:url/alt/id/caption editor eyeball.
				array(
					'key'           => 'field_bwsfx_feature_image',
					'name'          => 'feature_image',
					'label'         => 'Feature Image',
					'type'          => 'image',
					'return_format' => 'id',
				),
			),
			'location' => array(
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'staff' ) ),
			),
		)
	);

	// --- Site Settings (options page) — organization_* keys match the block corpus. ---
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_site_settings',
			'title'    => 'Organization',
			'fields'   => array(
				$text( 'org_phone', 'Phone' ),
				$text( 'organization_phone_display', 'Phone (display)' ),
				array(
					'key'   => 'field_bwsfx_organization_email',
					'name'  => 'organization_email',
					'label' => 'Email',
					'type'  => 'email',
				),
				array(
					'key'   => 'field_bwsfx_organization_address',
					'name'  => 'organization_address',
					'label' => 'Address',
					'type'  => 'textarea',
				),
				array(
					'key'            => 'field_bwsfx_organization_founded',
					'name'           => 'organization_founded',
					'label'          => 'Founded',
					'type'           => 'date_picker',
					'return_format'  => 'Ymd',
					'display_format' => 'F j, Y',
				),
				array(
					// datetime matrix D5 — src:site datetime read.
					'key'            => 'field_bwsfx_org_party_datetime',
					'name'           => 'org_party_datetime',
					'label'          => 'Company Party',
					'type'           => 'date_time_picker',
					'return_format'  => 'F j, Y g:i A',
					'display_format' => 'F j, Y g:i A',
				),
				array(
					'key'        => 'field_bwsfx_organization_social',
					'name'       => 'organization_social',
					'label'      => 'Social Links',
					'type'       => 'group',
					'sub_fields' => array(
						array(
							'key'   => 'field_bwsfx_org_social_facebook',
							'name'  => 'facebook',
							'label' => 'Facebook',
							'type'  => 'url',
						),
					),
				),
			),
			'location' => array(
				array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'site-settings' ) ),
			),
		)
	);

	// --- Event Details (post) — discovery edge cases. ---
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_event_details',
			'title'    => 'Event Details',
			'fields'   => array(
				array(
					// M3.4: label falls back to key — label IS the key.
					'key'            => 'field_bwsfx_event_date',
					'name'           => 'event_date',
					'label'          => 'event_date',
					'type'           => 'date_picker',
					'return_format'  => 'Ymd',
				),
				array(
					// M1.6: case-variant key.
					'key'   => 'field_bwsfx_event_date_cased',
					'name'  => 'Event_Date',
					'label' => 'Event Date',
					'type'  => 'text',
				),
				// M1.5: custom key `city` is a substring of this label's rendered row.
				$text( 'venue_city', 'City' ),
				// M9.3: collides with the globally registered `subtitle` meta key.
				$text( 'subtitle', 'Subtitle' ),
				array(
					'key'   => 'field_bwsfx_event_contact_email',
					'name'  => 'contact_email',
					'label' => 'Contact Email',
					'type'  => 'email',
				),
				// M8.2: label must not be able to close the inline <script> (B5 JSON_HEX_TAG).
				$text( 'escape_probe', 'Break </script><b>x</b>' ),
			),
			'location' => array(
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ),
			),
		)
	);

	// --- Team (post + page) — collision repeater #1. ---
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_team',
			'title'    => 'Team',
			'fields'   => array(
				array(
					'key'        => 'field_bwsfx_team_members',
					'name'       => 'team_members',
					'label'      => 'Team Members',
					'type'       => 'repeater',
					'sub_fields' => array(
						array(
							'key'   => 'field_bwsfx_team_name',
							'name'  => 'name',
							'label' => 'Name',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_bwsfx_team_description',
							'name'  => 'description',
							'label' => 'Description',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_bwsfx_team_role',
							'name'  => 'role',
							'label' => 'Role',
							'type'  => 'text',
						),
						// {{table}} use:title column target — a relationship sub-field per
						// row. Stored as a post ID (seed resolves the manifest slug → ID);
						// the table's ref-title column hops it row→post and reads the title.
						array(
							'key'           => 'field_bwsfx_team_lead_ref',
							'name'          => 'lead_ref',
							'label'         => 'Lead (related post)',
							'type'          => 'relationship',
							'return_format' => 'id',
						),
					),
				),
			),
			'location' => array(
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ),
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
			),
		)
	);

	// --- Product Features (post + page) — collision repeater #2.
	// M3.1: `name` here is "Feature Name" (different label → two rows).
	// M3.2: `description` here is "Description" again (same label → one merged row).
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_features',
			'title'    => 'Product Features',
			'fields'   => array(
				array(
					'key'        => 'field_bwsfx_feature_list',
					'name'       => 'feature_list',
					'label'      => 'Features',
					'type'       => 'repeater',
					'sub_fields' => array(
						array(
							'key'   => 'field_bwsfx_feature_name',
							'name'  => 'name',
							'label' => 'Feature Name',
							'type'  => 'text',
						),
						array(
							'key'   => 'field_bwsfx_feature_description',
							'name'  => 'description',
							'label' => 'Description',
							'type'  => 'text',
						),
					),
				),
			),
			'location' => array(
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ),
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
			),
		)
	);

	// --- Page Builder (page) — M2.6 flex breadcrumb: two flex fields, each with
	// a Hero layout containing `headline`; paths must stay distinct
	// (… › Blocks › Hero vs … › Sidebar › Hero).
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_page_builder',
			'title'    => 'Page Builder',
			'fields'   => array(
				array(
					'key'     => 'field_bwsfx_blocks',
					'name'    => 'blocks',
					'label'   => 'Blocks',
					'type'    => 'flexible_content',
					'layouts' => array(
						'layout_bwsfx_blocks_hero' => array(
							'key'        => 'layout_bwsfx_blocks_hero',
							'name'       => 'hero',
							'label'      => 'Hero',
							'sub_fields' => array(
								array(
									'key'   => 'field_bwsfx_blocks_hero_headline',
									'name'  => 'headline',
									'label' => 'Headline',
									'type'  => 'text',
								),
								array(
									'key'   => 'field_bwsfx_blocks_hero_subheading',
									'name'  => 'subheading',
									'label' => 'Subheading',
									'type'  => 'text',
								),
							),
						),
						'layout_bwsfx_blocks_cta'  => array(
							'key'        => 'layout_bwsfx_blocks_cta',
							'name'       => 'cta',
							'label'      => 'Call to Action',
							'sub_fields' => array(
								array(
									'key'   => 'field_bwsfx_blocks_cta_button',
									'name'  => 'button_text',
									'label' => 'Button Text',
									'type'  => 'text',
								),
							),
						),
					),
				),
				array(
					'key'     => 'field_bwsfx_sidebar',
					'name'    => 'sidebar',
					'label'   => 'Sidebar',
					'type'    => 'flexible_content',
					'layouts' => array(
						'layout_bwsfx_sidebar_hero' => array(
							'key'        => 'layout_bwsfx_sidebar_hero',
							'name'       => 'hero',
							'label'      => 'Hero',
							'sub_fields' => array(
								array(
									'key'   => 'field_bwsfx_sidebar_hero_headline',
									'name'  => 'headline',
									'label' => 'Headline',
									'type'  => 'text',
								),
							),
						),
					),
				),
			),
			'location' => array(
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
			),
		)
	);

	// --- Event Schedule (page + staff) — datetime matrix value fields (D-rows,
	// tools/test/datetime-test-matrix.md). Fixed 2030 values keep expectations
	// stable; the current-year case uses the {CURRENT_YEAR} seed token. ---
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_event_schedule',
			'title'    => 'Event Schedule',
			'fields'   => array(
				array(
					'key'            => 'field_bwsfx_event_datetime',
					'name'           => 'event_datetime',
					'label'          => 'Event Date-Time',
					'type'           => 'date_time_picker',
					'return_format'  => 'F j, Y g:i A',
					'display_format' => 'F j, Y g:i A',
				),
				array(
					'key'            => 'field_bwsfx_event_end_datetime',
					'name'           => 'event_end_datetime',
					'label'          => 'Event End Date-Time',
					'type'           => 'date_time_picker',
					'return_format'  => 'F j, Y g:i A',
					'display_format' => 'F j, Y g:i A',
				),
				array(
					// Lowercase-meridiem return format — case probe for the auto chain.
					'key'            => 'field_bwsfx_event_time',
					'name'           => 'event_time',
					'label'          => 'Event Time',
					'type'           => 'time_picker',
					'return_format'  => 'g:i a',
					'display_format' => 'g:i a',
				),
				array(
					'key'            => 'field_bwsfx_event_end_time',
					'name'           => 'event_end_time',
					'label'          => 'Event End Time',
					'type'           => 'time_picker',
					'return_format'  => 'g:i a',
					'display_format' => 'g:i a',
				),
				array(
					'key'            => 'field_bwsfx_event_start_date',
					'name'           => 'event_start_date',
					'label'          => 'Event Start Date',
					'type'           => 'date_picker',
					'return_format'  => 'F j, Y',
					'display_format' => 'F j, Y',
				),
				array(
					'key'            => 'field_bwsfx_event_end_date',
					'name'           => 'event_end_date',
					'label'          => 'Event End Date',
					'type'           => 'date_picker',
					'return_format'  => 'F j, Y',
					'display_format' => 'F j, Y',
				),
				array(
					// Value seeded at midnight — showMidnight / smart-time rows.
					'key'            => 'field_bwsfx_event_midnight',
					'name'           => 'event_midnight',
					'label'          => 'Event (midnight)',
					'type'           => 'date_time_picker',
					'return_format'  => 'F j, Y g:i A',
					'display_format' => 'F j, Y g:i A',
				),
				array(
					// Value seeded in the CURRENT year — showCurrentYear rows.
					'key'            => 'field_bwsfx_event_thisyear',
					'name'           => 'event_thisyear',
					'label'          => 'Event (this year)',
					'type'           => 'date_picker',
					'return_format'  => 'F j, Y',
					'display_format' => 'F j, Y',
				),
				array(
					// Non-default return_format — the parse chain dispatches on it (D0.11).
					'key'            => 'field_bwsfx_event_date_dmy',
					'name'           => 'event_date_dmy',
					'label'          => 'Event Date (d/m/Y)',
					'type'           => 'date_picker',
					'return_format'  => 'd/m/Y',
					'display_format' => 'd/m/Y',
				),
			),
			'location' => array(
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
				array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'staff' ) ),
			),
		)
	);

	// --- Department Details (term meta on department) — term-hop value fields. ---
	acf_add_local_field_group(
		array(
			'key'      => 'group_bwsfx_department',
			'title'    => 'Department Details',
			'fields'   => array(
				$text( 'phone', 'Phone' ),
				array(
					'key'   => 'field_bwsfx_department_email',
					'name'  => 'email',
					'label' => 'Email',
					'type'  => 'email',
				),
				// content matrix CT4 / fold F9a.4 — the term-hop {{content use:key}}
				// read. Sparse on warehouse so the first-non-empty walk is visible.
				$text( 'blurb', 'Department Blurb' ),
				array(
					// datetime matrix D4 — srcTermIn list rows (valid on support/sales,
					// junk on warehouse per the manifest).
					'key'            => 'field_bwsfx_dept_event_date',
					'name'           => 'event_date',
					'label'          => 'Department Event Date',
					'type'           => 'date_picker',
					'return_format'  => 'F j, Y',
					'display_format' => 'F j, Y',
				),
			),
			'location' => array(
				array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'department' ) ),
			),
		)
	);
}

/**
 * A seeded post's id, looked up by slug — the resolution BOTH fixture roots share (#85).
 *
 * MEMOISES ONLY A HIT. A miss is deliberately re-looked-up on the next call, because the
 * one process where a miss is expected is the seeding run itself: schema.php loads from
 * the mu-plugin before seed.php creates the posts, so a cached `false` would leave the
 * root permanently unresolvable for the rest of that process and present as a silent
 * fall-through to the ambient entity — indistinguishable, in a rendered row, from the
 * root resolving wrongly.
 *
 * @param string $slug      Post slug.
 * @param string $post_type Post type.
 * @return int|false
 */
function bws_fixture_seeded_post_id( $slug, $post_type ) {
	static $cache = array();
	$ck = $post_type . ':' . $slug;
	if ( isset( $cache[ $ck ] ) ) {
		return $cache[ $ck ];
	}
	$post = get_page_by_path( $slug, OBJECT, $post_type );
	if ( ! $post instanceof WP_Post ) {
		return false;
	}
	$cache[ $ck ] = (int) $post->ID;
	return $cache[ $ck ];
}

/**
 * The fixture chain-root SOURCE (class route) — registration callback (#85).
 *
 * Lazy require: BWS_Fixture_Root_Source extends a plugin class, so declaring it at file
 * load would fatal a site with the plugin deactivated. Inside this callback the parent is
 * guaranteed — the action only fires from SourceRegistry::init().
 */
function bws_fixture_core_structures_register_source() {
	if ( ! class_exists( 'BWS\DynamicTags\SourceRegistry' ) || ! class_exists( 'BWS\DynamicTags\AbstractSource' ) ) {
		return;
	}
	require_once __DIR__ . '/fixture-source.php';
	\BWS\DynamicTags\SourceRegistry::register_source( new BWS_Fixture_Root_Source() );
}

/**
 * The fixture chain-root declared through the FILTER route (#85).
 *
 * The cheap case the filter exists for: an entity and nothing else, no source class. Its
 * target is the seeded `sample-event` post — a DIFFERENT entity from the class route's,
 * so a row reading through the wrong one shows the wrong value rather than the same one.
 *
 * Resolves by slug like its sibling: deterministic, never request state.
 *
 * @param array $roots Declared root specs, keyed by source key.
 * @return array
 */
function bws_fixture_core_structures_chain_roots( $roots ) {
	$roots['fixture_alt'] = array(
		'label'   => 'Fixture Root (filter)',
		'context' => 'post',
		'resolve' => static function ( $options, $instance ) {
			return bws_fixture_seeded_post_id( 'sample-event', 'post' );
		},
	);
	return $roots;
}

/**
 * The fixture MODIFIER family — `fixture_text`, `fixture_image`, … (#85).
 *
 * Backed by the class-route source, so the family's prefix and its root key are the SAME
 * string, exactly as the real external family's are. That is what makes the seeded corpus
 * a faithful rehearsal of the migration: `{{fixture_text key:role}}` must become
 * `{{text src:fixture|key:role}}`, and both must render the same bytes.
 *
 * Registers at init:21 — after bws_register_base_tags() populates the modifier templates
 * at init:20, which is where the family's nine tags come from.
 */
function bws_fixture_core_structures_register_modifier() {
	if ( ! class_exists( 'BWS\DynamicTags\TagTemplateRegistry' ) ) {
		return;
	}
	\BWS\DynamicTags\TagTemplateRegistry::register_modifier( array(
		'prefix'            => 'fixture',
		'gb_type'           => 'fixture-based',
		'modifier_label'    => 'Fixture-based',
		'base_source_key'   => 'fixture',
		// No traversal_source_key: accepted-but-ignored since 1.14.0 — the src:ref hop is
		// a generic `ref` step off base_source_key.
		'excluded_supports' => array( 'source' ),
	) );

	// The family's MIGRATION entries (#86), generated from the same template list the
	// registration above iterates — one call, no list of tag names. This is the seam an
	// external plugin owning a retired prefix uses verbatim, which is the whole reason the
	// fixture family exists: the path runs end to end in-repo, against seeded wire, with no
	// second plugin installed.
	//
	// The family stays REGISTERED after migrating. Retiring it is the owner's decision on
	// the owner's schedule (`prefix_removed`), and the FR3 corpus needs the tags rendering
	// to be a migration corpus at all — a reseed puts the pre-conversion wire back.
	if ( function_exists( 'bws_register_modifier_root_migrations' ) ) {
		bws_register_modifier_root_migrations( 'fixture', 'fixture', array( 'since' => '1.17.0' ) );
	}
}

/**
 * The editor preview's prefix label for the fixture family (#85) — named rather than
 * inline, like every other registration in this file, so it can be unhooked.
 *
 * @param array $map Prefix => label.
 * @return array
 */
function bws_fixture_core_structures_preview_map( $map ) {
	$map['fixture_'] = 'Fixture';
	return $map;
}

// Runtime path (mu-plugin loader stub): hook normally.
if ( function_exists( 'add_action' ) && ! defined( 'BWS_FIXTURE_SEEDING' ) ) {
	add_action( 'init', 'bws_fixture_core_structures_register_types', 5 );
	add_action( 'init', 'bws_fixture_core_structures_register_meta', 5 );
	add_action( 'acf/init', 'bws_fixture_core_structures_register_acf', 5 );

	// The external-source contract's in-repo corpus (#85). Both routes plus the modifier
	// family the migration rehearses against; the seeded rows live on
	// /matrix-fixture-roots/ (blocks.php) and the entities they read in manifest.php.
	//
	// Registered from the mu-plugin at FILE LOAD, which is what puts the listener in
	// place before the source registry fires at plugins_loaded:20.
	add_action( 'bws_dynamic_tags_register_sources', 'bws_fixture_core_structures_register_source' );
	add_filter( 'bws_dynamic_tags_chain_roots', 'bws_fixture_core_structures_chain_roots' );
	add_action( 'init', 'bws_fixture_core_structures_register_modifier', 21 );

	// The preview's prefix map, so an unresolvable fixture_* tag previews as "Fixture …"
	// rather than as its raw tag name — the same registration the real family makes.
	add_filter( 'bws_dynamic_tags_preview_modifier_map', 'bws_fixture_core_structures_preview_map' );

	// {{table}} is a PROTOTYPE and registers off by default from 1.17.0, so the plugin
	// does not ship a half-built tag. The testbed is where it is built and where its
	// matrix rows and fixture blocks live, so the fixture turns it back on. This is a
	// filter rather than a constant because it must be set before the plugin registers
	// at init:20, and an mu-plugin loads well ahead of that. Delete this when the tag
	// ships v1 and registers unconditionally again (FW-53).
	add_filter( 'bws_dynamic_tags_register_table_tag', '__return_true' );
}
