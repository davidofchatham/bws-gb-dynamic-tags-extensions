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
// 0. Administrator context (#99).
// ---------------------------------------------------------------------------
// Capability-gated listeners (GB Pro's pattern cache among them) do not fire without one,
// so a capability-less seed produces fixture state that silently differs from the shipped
// path. Rationale, measurements and the deliberate exclusion of replay-tags.php all live in
// the helper, beside the rule, rather than in three copies here.
require_once __DIR__ . '/lib-admin-context.php';
bws_fixture_assume_administrator( $log );

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
		// Same targets in ACF's OTHER return format — the ref hop's object arms
		// (matrix RF1/RF2). lead_staff_obj is post_object, hence singular.
		'related_staff_obj' => 'field_bwsfx_related_staff_obj',
		'lead_staff_obj'    => 'field_bwsfx_lead_staff_obj',
		// The SECOND-DEGREE link (#55) — staff→staff, so a chain can hop twice.
		'reports_to'        => 'field_bwsfx_reports_to',
		// SOURCE GATE corpus (v13, ADR 0007) — see schema.php for why both return ids.
		'gate_staff'        => 'field_bwsfx_gate_staff',
		'via_draft'         => 'field_bwsfx_via_draft',
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
		// datetime matrix (manifest v3) — Event Schedule group (page + staff).
		'event_datetime'     => 'field_bwsfx_event_datetime',
		'event_end_datetime' => 'field_bwsfx_event_end_datetime',
		'event_time'         => 'field_bwsfx_event_time',
		'event_end_time'     => 'field_bwsfx_event_end_time',
		'event_start_date'   => 'field_bwsfx_event_start_date',
		'event_end_date'     => 'field_bwsfx_event_end_date',
		'event_midnight'     => 'field_bwsfx_event_midnight',
		'event_thisyear'     => 'field_bwsfx_event_thisyear',
		'event_date_dmy'     => 'field_bwsfx_event_date_dmy',
		'venue_city'       => 'field_bwsfx_venue_city',
		'subtitle'         => 'field_bwsfx_subtitle',
		'escape_probe'     => 'field_bwsfx_escape_probe',
		'team_members'     => 'field_bwsfx_team_members',
		'feature_list'     => 'field_bwsfx_feature_list',
		// FW-52 image editor rows.
		'feature_image'    => 'field_bwsfx_feature_image',
	),
	'option' => array(
		'org_phone'                  => 'field_bwsfx_org_phone',
		'organization_phone_display' => 'field_bwsfx_organization_phone_display',
		'organization_email'         => 'field_bwsfx_organization_email',
		'organization_address'       => 'field_bwsfx_organization_address',
		'organization_founded'       => 'field_bwsfx_organization_founded',
		'organization_social'        => 'field_bwsfx_organization_social',
		'org_party_datetime'         => 'field_bwsfx_org_party_datetime',
	),
	'term'   => array(
		'phone'      => 'field_bwsfx_phone',
		'email'      => 'field_bwsfx_department_email',
		'event_date' => 'field_bwsfx_dept_event_date',
		'charter'    => 'field_bwsfx_charter',   // v12 first-usable corpus (§F15).
	),
);

// Manifest value tokens. {CURRENT_YEAR} → the seed-time year (keeps the
// showCurrentYear fixture in the current year on every reseed; manifest stays
// pure data). Applied to every string field value below.
$resolve_tokens = function ( $value ) {
	if ( is_string( $value ) && false !== strpos( $value, '{CURRENT_YEAR}' ) ) {
		return str_replace( '{CURRENT_YEAR}', wp_date( 'Y' ), $value );
	}
	return $value;
};
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
	$term_args = array( 'slug' => $def['slug'] );
	if ( isset( $def['description'] ) ) {
		$term_args['description'] = $def['description'];
	}
	$existing = get_term_by( 'slug', $def['slug'], $def['taxonomy'] );
	if ( $existing ) {
		$term_ids[ $slug ] = (int) $existing->term_id;
		// Upsert description on re-seed (wp_insert_term only sets it on create).
		if ( isset( $def['description'] ) ) {
			wp_update_term( (int) $existing->term_id, $def['taxonomy'], array( 'description' => $def['description'] ) );
		}
	} else {
		$res = wp_insert_term( $def['name'], $def['taxonomy'], $term_args );
		if ( is_wp_error( $res ) ) {
			WP_CLI::warning( "term {$slug}: " . $res->get_error_message() );
			continue;
		}
		$term_ids[ $slug ] = (int) $res['term_id'];
	}
}
$log( 'terms: ' . count( $term_ids ) . ' upserted' );

// ---------------------------------------------------------------------------
// 3b. Users (author-archive context fixture — C3/C13).
// ---------------------------------------------------------------------------
$user_ids = array();
foreach ( ( $manifest['users'] ?? array() ) as $slug => $def ) {
	$existing = get_user_by( 'login', $def['user_login'] );
	if ( $existing ) {
		$uid = (int) $existing->ID;
		wp_update_user(
			array(
				'ID'           => $uid,
				'display_name' => $def['display_name'],
				'user_email'   => $def['user_email'],
				'description'  => $def['description'],
				'role'         => $def['role'],
			)
		);
	} else {
		$uid = wp_insert_user(
			array(
				'user_login'    => $def['user_login'],
				'user_nicename' => $def['user_nicename'],
				'user_pass'     => wp_generate_password( 24 ),
				'display_name'  => $def['display_name'],
				'user_email'    => $def['user_email'],
				'description'   => $def['description'],
				'role'          => $def['role'],
			)
		);
	}
	if ( ! is_wp_error( $uid ) ) {
		$user_ids[ $slug ] = (int) $uid;
	}
}
$log( 'users: ' . count( $user_ids ) . ' upserted' );

foreach ( $manifest['term_fields'] as $slug => $fields ) {
	if ( ! isset( $term_ids[ $slug ] ) ) {
		continue;
	}
	$tid = $term_ids[ $slug ];
	foreach ( $fields as $name => $value ) {
		$value = $resolve_tokens( $value );
		if ( $have_acf && isset( $field_keys['term'][ $name ] ) ) {
			update_field( $field_keys['term'][ $name ], $value, 'term_' . $tid );
		} else {
			update_term_meta( $tid, $name, $value );
		}
	}
}
$log( 'term fields applied' );

// ---------------------------------------------------------------------------
// 3b. Media attachments (FW-52 image editor rows).
//
// Generates a small deterministic PNG in the uploads dir and registers it as an
// attachment. Idempotent: an existing attachment carrying the same
// `_bws_fixture_slug` meta is reused (its metadata refreshed), so reseeding never
// piles up duplicates. Needs wp_generate_attachment_metadata (admin media stack).
// ---------------------------------------------------------------------------
$attachment_ids = array();
if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
}
foreach ( ( $manifest['attachments'] ?? array() ) as $slug => $def ) {
	// Reuse by fixture-slug meta.
	$existing = get_posts( array(
		'post_type'   => 'attachment',
		'post_status' => 'any',
		'numberposts' => 1,
		'meta_key'    => '_bws_fixture_slug',
		'meta_value'  => $slug,
		'fields'      => 'ids',
	) );

	// Build a deterministic solid-color PNG (GD) or fall back to a 1x1 PNG.
	$hex   = ltrim( (string) ( $def['color'] ?? '4a90d9' ), '#' );
	$r     = hexdec( substr( $hex, 0, 2 ) );
	$g     = hexdec( substr( $hex, 2, 2 ) );
	$b     = hexdec( substr( $hex, 4, 2 ) );
	$png   = '';
	if ( function_exists( 'imagecreatetruecolor' ) ) {
		$img = imagecreatetruecolor( 320, 200 );
		imagefill( $img, 0, 0, imagecolorallocate( $img, $r, $g, $b ) );
		ob_start();
		imagepng( $img );
		$png = ob_get_clean();
		imagedestroy( $img );
	} else {
		// Minimal 1x1 opaque PNG (base64). Size variants won't generate, but the
		// attachment + as:url/alt/id/caption reads still work.
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M8AAAMBAQDJ/pLvAAAAAElFTkSuQmCC' );
	}

	$uploads  = wp_upload_dir();
	$filename = sanitize_file_name( $def['filename'] ?? ( $slug . '.png' ) );
	$filepath = trailingslashit( $uploads['path'] ) . $filename;
	file_put_contents( $filepath, $png );

	$attach_args = array(
		'post_mime_type' => 'image/png',
		'post_title'     => $def['title'] ?? $slug,
		'post_excerpt'   => $def['caption'] ?? '', // caption.
		'post_status'    => 'inherit',
	);
	if ( $existing ) {
		$attach_args['ID'] = $existing[0];
		$att_id            = (int) wp_update_post( $attach_args );
	} else {
		$att_id = (int) wp_insert_attachment( $attach_args, $filepath );
	}

	update_post_meta( $att_id, '_bws_fixture_slug', $slug );
	update_post_meta( $att_id, '_wp_attachment_image_alt', $def['alt'] ?? '' );
	wp_update_attachment_metadata( $att_id, wp_generate_attachment_metadata( $att_id, $filepath ) );

	$attachment_ids[ $slug ] = $att_id;
}
$log( 'attachments: ' . count( $attachment_ids ) . ' upserted' );

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
			// EXPLICIT status list, not 'any': WP_Query's 'any' subtracts every status
			// flagged exclude_from_search, which is where the gate corpus's draft lives.
			// A miss here is not an error — it silently seeds a SECOND post each run,
			// and the duplicate takes the slug the rows read by.
			// `trash` is in the list for the same reason (v14, §F17.8): a trashed
			// fixture the lookup cannot see is re-created on every reseed, and the
			// duplicate takes the slug while the original keeps the row's id.
			'post_status' => array( 'publish', 'draft', 'private', 'pending', 'future', 'trash' ),
			'numberposts' => 1,
		)
	);
	$args = array(
		'post_type'    => $def['post_type'],
		'post_name'    => $def['post_name'],
		'post_title'   => $def['post_title'],
		// Absent = publish, which every fixture but the gate corpus (v13) wants.
		// The gate rows need posts differing ONLY in readability, so a post's status
		// is fixture DATA there rather than a constant here.
		'post_status'  => isset( $def['post_status'] ) ? $def['post_status'] : 'publish',
		'post_content' => $content,
	);
	if ( isset( $def['post_author'], $user_ids[ $def['post_author'] ] ) ) {
		$args['post_author'] = $user_ids[ $def['post_author'] ];
	}
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

// sample-event doubles as the date-archive context fixture (context-test-matrix
// C-rows): the portal-system front-end query filter drops anonymous-invisible
// posts (must carry an all-users/no-portal portal_visibility term AND no
// category), so a default 'uncategorized' assignment 404s /2026/07/. Keep it
// categoryless + all-users-visible. portal_visibility belongs to
// bws-portal-system — guard on taxonomy existence so this blueprint stays
// loadable without it.
if ( isset( $post_ids['post-sample-event'] ) ) {
	wp_set_object_terms( $post_ids['post-sample-event'], array(), 'category' );
	if ( taxonomy_exists( 'portal_visibility' ) ) {
		wp_set_object_terms( $post_ids['post-sample-event'], array( 'all-users' ), 'portal_visibility' );
	}
	$log( 'sample-event date-archive visibility ensured (no category, all-users)' );
}

// ---------------------------------------------------------------------------
// 4b. Block patterns (wp_block) — the GB Pro pattern-cache fixture (#99).
// ---------------------------------------------------------------------------
// Seeded through wp_insert_post/wp_update_post ON PURPOSE, so GenerateBlocks Pro's
// after_save listener fires and builds a REAL cache entry. A hand-written entry was
// considered and rejected: it would encode our belief about GB Pro's array shape (add a
// field on their side and the fixture never carries it, the read-modify-write mangles or
// drops it, and nothing notices), and it would make the integration seam's headline
// assertion near-vacuous — "every non-content field byte-identical" only bites when those
// fields hold real bytes. A stub preview survives a slash-stripping bug that a real
// multi-kilobyte escaped preview exposes instantly.
//
// CONTENT MUST BE SLASHED GOING IN. wp_insert_post() expects slashed data and unslashes it
// internally, so the literal backslashes this fixture carries deliberately (see
// bws_fixture_pattern_content_legacy_wire()) would be eaten on the way to the database.
// The assertion below catches that directly rather than trusting the convention.
//
// Skips cleanly when the pattern post type is unavailable.
$pattern_ids = array();
if ( ! empty( $manifest['patterns'] ) && post_type_exists( 'wp_block' ) ) {
	foreach ( $manifest['patterns'] as $slug => $def ) {
		$content  = bws_fixture_build_page_content( $def['content_builder'] );
		$existing = get_posts(
			array(
				'name'        => $def['post_name'],
				'post_type'   => 'wp_block',
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);
		$args = array(
			'post_type'    => 'wp_block',
			'post_name'    => $def['post_name'],
			'post_title'   => $def['post_title'],
			'post_status'  => 'publish',
			'post_content' => wp_slash( $content ),
		);
		if ( $existing ) {
			$args['ID']            = $existing[0]->ID;
			$pattern_ids[ $slug ]  = (int) wp_update_post( $args );
		} else {
			$pattern_ids[ $slug ] = (int) wp_insert_post( $args );
		}

		$stored = get_post( $pattern_ids[ $slug ] );
		if ( ! $stored || $stored->post_content !== $content ) {
			$log( 'WARNING: pattern ' . $slug . ' did not round-trip byte-identically (slashing?)' );
		}

		$tree = get_post_meta( $pattern_ids[ $slug ], 'generateblocks_patterns_tree', true );
		if ( empty( $tree ) ) {
			// Either GB Pro is inactive (fine — the fixture is inert) or the administrator
			// context above did not land (not fine, and silent otherwise).
			$log( 'note: pattern ' . $slug . ' has no GB Pro cache entry (GB Pro inactive, or no edit_post capability)' );
		}
	}
	$log( 'patterns: ' . count( $pattern_ids ) . ' upserted' );
} elseif ( ! empty( $manifest['patterns'] ) ) {
	$log( 'patterns: SKIPPED — wp_block post type not registered' );
}

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
		$value = $resolve_tokens( $value );
		// Resolve fixture-slug references (relationship / post_object fields) to
		// post IDs. The field NAMES are a list rather than a chain of `if`s: ACF
		// stores the id whatever the field's return_format is, so every such field
		// resolves identically here and the format only shows up on the READ.
		// A single-slug value (post_object) resolves to a scalar id, not a list.
		if ( in_array( $name, array( 'related_staff', 'related_staff_obj', 'lead_staff_obj', 'reports_to', 'gate_staff', 'via_draft' ), true ) ) {
			$slug_to_id = function ( $ref ) use ( $post_ids ) {
				return isset( $post_ids[ $ref ] ) ? $post_ids[ $ref ] : 0;
			};
			$value      = is_array( $value )
				? array_values( array_filter( array_map( $slug_to_id, $value ) ) )
				: $slug_to_id( $value );
		}
		// Resolve an image-field fixture slug → the seeded attachment ID.
		if ( 'feature_image' === $name && is_string( $value ) && isset( $attachment_ids[ $value ] ) ) {
			$value = $attachment_ids[ $value ];
		}
		// Resolve the {{table}} repeater's per-row relationship sub-field (lead_ref)
		// fixture slug → post ID. The generic related_staff resolver above is
		// top-level-only; the repeater's rows are one level down, so map each row's
		// lead_ref slug here. Empty/unknown slugs → '' (proves the empty-cell path).
		if ( 'team_members' === $name && is_array( $value ) ) {
			$value = array_map( function ( $row ) use ( $post_ids ) {
				if ( is_array( $row ) && isset( $row['lead_ref'] ) && is_string( $row['lead_ref'] ) ) {
					$row['lead_ref'] = isset( $post_ids[ $row['lead_ref'] ] ) ? $post_ids[ $row['lead_ref'] ] : '';
				}
				return $row;
			}, $value );
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
// The gate corpus's EXISTENCE fixture (v13, §F17.3): a genuinely deleted post id.
// Created and force-deleted here rather than hardcoded — a made-up high number passes
// vacuously today and starts failing the day the site's auto-increment reaches it.
// Resolved into plain meta below as the {DELETED_POST_ID} token.
$deleted_post_id = 0;
if ( ! empty( $manifest['post_meta'] ) ) {
	$throwaway = wp_insert_post(
		array(
			'post_type'   => 'staff',
			'post_title'  => 'BWS Fixture Throwaway (deleted at seed time)',
			'post_status' => 'draft',
		)
	);
	if ( $throwaway && ! is_wp_error( $throwaway ) ) {
		$deleted_post_id = (int) $throwaway;
		wp_delete_post( $deleted_post_id, true ); // Force — a trashed post still EXISTS.
		if ( get_post( $deleted_post_id ) ) {
			$log( 'WARNING: throwaway post ' . $deleted_post_id . ' survived deletion — §F17.3 would assert nothing' );
		}
	}
}

foreach ( $manifest['post_meta'] as $slug => $meta ) {
	if ( ! isset( $post_ids[ $slug ] ) ) {
		continue;
	}
	foreach ( $meta as $name => $value ) {
		// Plain meta carries the gate corpus's raw ref list, so it needs the same
		// slug→id resolution the ACF loop above does, plus the deleted-id token.
		if ( is_array( $value ) ) {
			$value = array_values(
				array_filter(
					array_map(
						function ( $entry ) use ( $post_ids, $deleted_post_id ) {
							if ( '{DELETED_POST_ID}' === $entry ) {
								return $deleted_post_id;
							}
							return isset( $post_ids[ $entry ] ) ? $post_ids[ $entry ] : $entry;
						},
						$value
					)
				)
			);
		}
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
	$value = $resolve_tokens( $value );
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
