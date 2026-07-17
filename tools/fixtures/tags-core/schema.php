<?php
/**
 * tags-core blueprint — schema.
 *
 * Fixture-defining code for the tags-core blueprint: CPT + taxonomy registration,
 * ACF field groups (incl. the collision repeaters and flex fields the
 * field-selector matrix needs), the options page, and registered meta.
 *
 * Loaded two ways:
 *  - at runtime by the mu-plugin loader stub seed.php installs (hooks init/acf)
 *  - directly by seed.php during seeding (functions called immediately)
 *
 * Data lives in manifest.php; this file is code only.
 *
 * Consumed by: tools/test/phone-test-matrix.md, tools/test/field-selector-test-matrix.md.
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
function bws_fixture_tags_core_register_types() {
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
function bws_fixture_tags_core_register_meta() {
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
function bws_fixture_tags_core_register_acf() {
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
			),
			'location' => array(
				array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'department' ) ),
			),
		)
	);
}

// Runtime path (mu-plugin loader stub): hook normally.
if ( function_exists( 'add_action' ) && ! defined( 'BWS_FIXTURE_SEEDING' ) ) {
	add_action( 'init', 'bws_fixture_tags_core_register_types', 5 );
	add_action( 'init', 'bws_fixture_tags_core_register_meta', 5 );
	add_action( 'acf/init', 'bws_fixture_tags_core_register_acf', 5 );
}
