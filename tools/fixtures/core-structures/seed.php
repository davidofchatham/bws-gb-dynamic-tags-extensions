<?php
/**
 * core-structures blueprint — seed applier.
 *
 * Idempotent: reads manifest.php, upserts by fixture slug. Safe to re-run;
 * page content is regenerated from blocks.php every run.
 *
 * Run (from the wp-litespeed env; path shown is the container mount):
 *   bin/wp.sh <site> eval-file <mounted-repo>/tools/fixtures/core-structures/seed.php
 *
 * First job: installs a mu-plugin loader stub whose include path is computed
 * from THIS file's location at seed time (nothing environment-specific is
 * committed) — so schema survives snapshot restore and stays git-editable.
 *
 * Requires ACF (Pro) active for field groups + values; degrades to plain
 * post meta writes for scalar fields if ACF is absent.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run via wp-cli eval-file.\n";
	exit( 1 );
}

define( 'BWS_FIXTURE_SEEDING', true );

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/blocks.php';

$manifest = require __DIR__ . '/manifest.php';
$log      = function ( $msg ) {
	WP_CLI::log( '[core-structures] ' . $msg );
};

// ---------------------------------------------------------------------------
// 1. Mu-plugin loader stub (path computed at seed time, not committed).
// ---------------------------------------------------------------------------
$mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
if ( ! is_dir( $mu_dir ) ) {
	mkdir( $mu_dir, 0755, true );
}
$schema_path = __DIR__ . '/schema.php';
$stub        = "<?php\n// Auto-installed by core-structures seed.php — includes the blueprint schema off the plugin mount.\n"
	. "if ( file_exists( '" . addslashes( $schema_path ) . "' ) ) {\n"
	. "\trequire_once '" . addslashes( $schema_path ) . "';\n"
	. "}\n";
file_put_contents( $mu_dir . '/bws-fixture-core-structures.php', $stub );
$log( 'mu-plugin loader stub installed → ' . $mu_dir . '/bws-fixture-core-structures.php' );

// ---------------------------------------------------------------------------
// 2. Register schema NOW (init/acf hooks already fired in this CLI run).
// ---------------------------------------------------------------------------
bws_fixture_core_structures_register_types();
bws_fixture_core_structures_register_meta();
bws_fixture_core_structures_register_acf();
$have_acf = function_exists( 'update_field' );
$log( 'schema registered (ACF ' . ( $have_acf ? 'present' : 'ABSENT — scalar fallback' ) . ')' );

// Field name → ACF field key (per write context; see schema.php).
$field_keys = array(
	'post'   => array(
		'main_line'        => 'field_bwsfx_main_line',
		'booking_line'     => 'field_bwsfx_booking_line',
		'after_hours_line' => 'field_bwsfx_after_hours_line',
		'sms_number'       => 'field_bwsfx_sms_number',
		'intl_desk'        => 'field_bwsfx_intl_desk',
		'us_toll_free'     => 'field_bwsfx_us_toll_free',
		'intl_exchange'    => 'field_bwsfx_intl_exchange',
		'uk_mobile'        => 'field_bwsfx_uk_mobile',
		'support_tollfree' => 'field_bwsfx_support_tollfree',
		'sales_tollfree'   => 'field_bwsfx_sales_tollfree',
		'fax_tollfree'     => 'field_bwsfx_fax_tollfree',
		'intl_support'     => 'field_bwsfx_intl_support',
		'flat_tollfree'    => 'field_bwsfx_flat_tollfree',
		'flat_local'       => 'field_bwsfx_flat_local',
		'front_desk_ext'   => 'field_bwsfx_front_desk_ext',
		'unused_line'      => 'field_bwsfx_unused_line',
		'short_code'       => 'field_bwsfx_short_code',
		'hacked_line'      => 'field_bwsfx_hacked_line',
		'related_staff'    => 'field_bwsfx_related_staff',
		// join matrix (manifest v2) — person-name / role / height fields.
		'name_honorific'      => 'field_bwsfx_name_honorific',
		'name_first'          => 'field_bwsfx_name_first',
		'name_middle_initial' => 'field_bwsfx_name_middle_initial',
		'name_last'           => 'field_bwsfx_name_last',
		'name_generation'     => 'field_bwsfx_name_generation',
		'name_credential'     => 'field_bwsfx_name_credential',
		'name_service'        => 'field_bwsfx_name_service',
		'role'                => 'field_bwsfx_role',
		'height_ft'           => 'field_bwsfx_height_ft',
		'height_in'           => 'field_bwsfx_height_in',
		'height_in_blank'     => 'field_bwsfx_height_in_blank',
		'height_in_zero'      => 'field_bwsfx_height_in_zero',
		'event_date'       => 'field_bwsfx_event_date',
		'Event_Date'       => 'field_bwsfx_event_date_cased',
		'venue_city'       => 'field_bwsfx_venue_city',
		'subtitle'         => 'field_bwsfx_subtitle',
		'escape_probe'     => 'field_bwsfx_escape_probe',
		'team_members'     => 'field_bwsfx_team_members',
		'feature_list'     => 'field_bwsfx_feature_list',
	),
	'option' => array(
		'org_phone'                  => 'field_bwsfx_org_phone',
		'organization_phone_display' => 'field_bwsfx_organization_phone_display',
		'organization_email'         => 'field_bwsfx_organization_email',
		'organization_address'       => 'field_bwsfx_organization_address',
		'organization_founded'       => 'field_bwsfx_organization_founded',
		'organization_social'        => 'field_bwsfx_organization_social',
	),
	'term'   => array(
		'phone' => 'field_bwsfx_phone',
		'email' => 'field_bwsfx_department_email',
	),
);
// contact_email has two homes (M3.3) — pick by post type at write time.
$contact_email_keys = array(
	'staff' => 'field_bwsfx_staff_contact_email',
	'page'  => 'field_bwsfx_staff_contact_email',
	'post'  => 'field_bwsfx_event_contact_email',
);

// ---------------------------------------------------------------------------
// 3. Terms.
// ---------------------------------------------------------------------------
$term_ids = array();
foreach ( $manifest['terms'] as $slug => $def ) {
	$existing = get_term_by( 'slug', $def['slug'], $def['taxonomy'] );
	if ( $existing ) {
		$term_ids[ $slug ] = (int) $existing->term_id;
	} else {
		$res = wp_insert_term( $def['name'], $def['taxonomy'], array( 'slug' => $def['slug'] ) );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( "term {$slug}: " . $res->get_error_message() );
			continue;
		}
		$term_ids[ $slug ] = (int) $res['term_id'];
	}
}
$log( 'terms: ' . count( $term_ids ) . ' upserted' );

foreach ( $manifest['term_fields'] as $slug => $fields ) {
	if ( ! isset( $term_ids[ $slug ] ) ) {
		continue;
	}
	$tid = $term_ids[ $slug ];
	foreach ( $fields as $name => $value ) {
		if ( $have_acf && isset( $field_keys['term'][ $name ] ) ) {
			update_field( $field_keys['term'][ $name ], $value, 'term_' . $tid );
		} else {
			update_term_meta( $tid, $name, $value );
		}
	}
}
$log( 'term fields applied' );

// ---------------------------------------------------------------------------
// 4. Posts (content regenerated from blocks.php each run).
// ---------------------------------------------------------------------------
$post_ids = array();
foreach ( $manifest['posts'] as $slug => $def ) {
	$content  = isset( $def['content_builder'] ) ? bws_fixture_build_page_content( $def['content_builder'] ) : '';
	$existing = get_posts(
		array(
			'name'        => $def['post_name'],
			'post_type'   => $def['post_type'],
			'post_status' => 'any',
			'numberposts' => 1,
		)
	);
	$args = array(
		'post_type'    => $def['post_type'],
		'post_name'    => $def['post_name'],
		'post_title'   => $def['post_title'],
		'post_status'  => 'publish',
		'post_content' => $content,
	);
	if ( $existing ) {
		$args['ID']         = $existing[0]->ID;
		$post_ids[ $slug ]  = (int) wp_update_post( $args );
	} else {
		$post_ids[ $slug ] = (int) wp_insert_post( $args );
	}
}
$log( 'posts: ' . count( $post_ids ) . ' upserted' );

foreach ( $manifest['post_terms'] as $slug => $terms ) {
	if ( ! isset( $post_ids[ $slug ] ) ) {
		continue;
	}
	$ids = array();
	foreach ( $terms as $t ) {
		if ( isset( $term_ids[ $t ] ) ) {
			$ids[] = $term_ids[ $t ];
		}
	}
	wp_set_object_terms( $post_ids[ $slug ], $ids, 'department' );
}
$log( 'post→term assignments applied' );

// ---------------------------------------------------------------------------
// 5. Post fields (ACF) + plain post meta.
// ---------------------------------------------------------------------------
foreach ( $manifest['post_fields'] as $slug => $fields ) {
	if ( ! isset( $post_ids[ $slug ] ) ) {
		continue;
	}
	$pid   = $post_ids[ $slug ];
	$ptype = get_post_type( $pid );
	foreach ( $fields as $name => $value ) {
		// Resolve fixture-slug references (relationship fields) to post IDs.
		if ( 'related_staff' === $name && is_array( $value ) ) {
			$value = array_values( array_filter( array_map( function ( $ref ) use ( $post_ids ) {
				return isset( $post_ids[ $ref ] ) ? $post_ids[ $ref ] : 0;
			}, $value ) ) );
		}
		if ( 'contact_email' === $name ) {
			$key = isset( $contact_email_keys[ $ptype ] ) ? $contact_email_keys[ $ptype ] : null;
		} else {
			$key = isset( $field_keys['post'][ $name ] ) ? $field_keys['post'][ $name ] : null;
		}
		if ( $have_acf && $key ) {
			update_field( $key, $value, $pid );
		} elseif ( ! is_array( $value ) ) {
			update_post_meta( $pid, $name, $value );
		}
	}
}
foreach ( $manifest['post_meta'] as $slug => $meta ) {
	if ( ! isset( $post_ids[ $slug ] ) ) {
		continue;
	}
	foreach ( $meta as $name => $value ) {
		update_post_meta( $post_ids[ $slug ], $name, $value );
	}
}
$log( 'post fields + plain meta applied' );

// ---------------------------------------------------------------------------
// 6. Plain wp_options (recursive merge — only the manifest's keys change).
// ---------------------------------------------------------------------------
if ( ! empty( $manifest['wp_options'] ) ) {
	foreach ( $manifest['wp_options'] as $opt => $value ) {
		$existing = get_option( $opt, array() );
		if ( is_array( $value ) && is_array( $existing ) ) {
			$value = array_replace_recursive( $existing, $value );
		}
		update_option( $opt, $value );
	}
	$log( 'wp_options merged' );
}

// ---------------------------------------------------------------------------
// 7. Options-page fields.
// ---------------------------------------------------------------------------
foreach ( $manifest['option_fields'] as $name => $value ) {
	if ( $have_acf && isset( $field_keys['option'][ $name ] ) ) {
		update_field( $field_keys['option'][ $name ], $value, 'option' );
	} elseif ( ! is_array( $value ) ) {
		update_option( 'options_' . $name, $value );
	}
}
$log( 'options applied' );

// ---------------------------------------------------------------------------
// 8. Scratch ACF group in the DB (free experimentation target).
// ---------------------------------------------------------------------------
if ( function_exists( 'acf_import_field_group' ) && function_exists( 'acf_get_field_group' ) ) {
	if ( ! acf_get_field_group( 'group_bws_scratch' ) ) {
		acf_import_field_group(
			array(
				'key'      => 'group_bws_scratch',
				'title'    => 'Scratch',
				'fields'   => array(),
				'location' => array(
					array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'post' ) ),
				),
				'active'   => true,
			)
		);
		$log( 'Scratch ACF group created in DB' );
	} else {
		$log( 'Scratch ACF group already present' );
	}
}

// ---------------------------------------------------------------------------
// 9. Rewrites (new CPT/tax need fresh rules for archive URLs).
// ---------------------------------------------------------------------------
flush_rewrite_rules();
$log( 'rewrite rules flushed' );

$log( 'DONE — blueprint ' . $manifest['blueprint'] . ' v' . $manifest['version'] );
