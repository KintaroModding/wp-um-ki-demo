<?php
/**
 * Demo setup for WordPress Playground.
 *
 * Builds the "Who Is Who" scenario in Ultimate Member from demo-daten.json:
 * site basics, roles, registration and profile form fields, member directory,
 * sample members, start page and menu.
 *
 * Runs once per Playground start (blueprint step "runPHP"). Every start builds
 * the site fresh from the files in the repository, so a change in
 * demo-daten.json is live after the next start.
 */

require_once '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$daten = json_decode( file_get_contents( '/wordpress/demo/demo-daten.json' ), true );
if ( ! is_array( $daten ) ) {
	error_log( 'demo-setup: demo-daten.json is missing or not valid JSON: ' . json_last_error_msg() );
	return;
}
if ( ! function_exists( 'UM' ) ) {
	error_log( 'demo-setup: Ultimate Member is not active.' );
	return;
}

// UM only installs its forms and pages for a user with manage_options.
wp_set_current_user( 1 );

// ---------------------------------------------------------------------------
// 1. Ultimate Member base setup: tables, default forms, settings, core pages.
// ---------------------------------------------------------------------------
UM()->setup()->run_setup();
UM()->setup()->install_default_pages();

$core_forms = get_option( 'um_core_forms', array() );
$core_dirs  = get_option( 'um_core_directories', array() );

// ---------------------------------------------------------------------------
// 2. Site basics.
// ---------------------------------------------------------------------------
update_option( 'blogname', $daten['seite']['titel'] );
update_option( 'blogdescription', $daten['seite']['untertitel'] );
update_option( 'users_can_register', 1 );
update_option( 'permalink_structure', '/%postname%/' );

// Friendly German slugs for the UM pages (UM finds its pages by ID, not slug).
$slugs = array(
	'register' => 'registrierung',
	'login'    => 'anmelden',
	'members'  => 'who-is-who',
	'account'  => 'konto',
);
foreach ( $slugs as $core => $slug ) {
	// Find the page by UM's marker; UM()->options() still holds a cached copy here.
	$page_id = UM()->query()->find_post_id( 'page', '_um_core', $core );
	if ( $page_id ) {
		$update = array( 'ID' => (int) $page_id, 'post_name' => $slug );
		if ( 'members' === $core ) {
			$update['post_title'] = $daten['verzeichnis']['titel'];
		}
		wp_update_post( $update );
	}
}

// ---------------------------------------------------------------------------
// 3. Roles.
// ---------------------------------------------------------------------------
$role_keys = array();
foreach ( $daten['rollen'] as $rolle ) {
	$key         = sanitize_key( $rolle['key'] );
	$role_keys[] = $key;
	update_option(
		"um_role_{$key}_meta",
		array(
			'name'                           => $rolle['name'],
			'wp_capabilities'                => array( 'read' => true ),
			'_um_can_access_wpadmin'         => 0,
			'_um_can_not_see_adminbar'       => 1,
			'_um_can_edit_everyone'          => 0,
			'_um_can_delete_everyone'        => 0,
			'_um_can_edit_profile'           => 1,
			'_um_can_delete_profile'         => 1,
			'_um_after_login'                => 'redirect_profile',
			'_um_after_logout'               => 'redirect_home',
			'_um_default_homepage'           => 1,
			'_um_can_view_all'               => 1,
			'_um_can_make_private_profile'   => 0,
			'_um_can_access_private_profile' => 0,
			'_um_status'                     => 'approved',
			// After registration: own profile, or a page given as "nach_registrierung": "/path/".
			'_um_auto_approve_act'           => empty( $rolle['nach_registrierung'] ) ? 'redirect_profile' : 'redirect_url',
			'_um_auto_approve_url'           => empty( $rolle['nach_registrierung'] ) ? '' : home_url( $rolle['nach_registrierung'] ),
		)
	);
}
update_option( 'um_roles', $role_keys );
wp_roles()->for_site(); // Reload roles so the new UM roles exist in this request.

// ---------------------------------------------------------------------------
// 4. Fields: registration form, profile form, and UM's global field list.
// ---------------------------------------------------------------------------

/**
 * Convert one field from demo-daten.json into Ultimate Member's field format.
 */
function demo_um_feld( $key, $f, $position ) {
	$typ = $f['typ'];
	$um  = array(
		'title'      => $f['label'],
		'metakey'    => $key,
		'type'       => $typ,
		'label'      => $f['label'],
		'required'   => empty( $f['pflicht'] ) ? 0 : 1,
		'public'     => 1,
		'editable'   => true,
		'position'   => (string) $position,
		'in_row'     => '_um_row_1',
		'in_sub_row' => '0',
		'in_column'  => '1',
		'in_group'   => '',
	);
	if ( ! empty( $f['optionen'] ) ) {
		$um['options'] = array_values( $f['optionen'] );
	}
	if ( ! empty( $f['hilfe'] ) ) {
		$um['help'] = $f['hilfe'];
	}
	if ( ! empty( $f['platzhalter'] ) ) {
		$um['placeholder'] = $f['platzhalter'];
	}

	if ( 'email' === $typ ) {
		$um['type']     = 'text';
		$um['validate'] = ( 'user_email' === $key ) ? 'unique_email' : 'email';
	} elseif ( 'tel' === $typ ) {
		$um['type']     = 'text';
		$um['validate'] = 'phone_number';
	} elseif ( 'password' === $typ ) {
		$um['min_chars']          = 8;
		$um['max_chars']          = 30;
		$um['force_good_pass']    = 0;
		$um['force_confirm_pass'] = 1;
	}
	return $um;
}

/**
 * Build the _um_custom_fields array for a form from a list of field keys.
 */
function demo_um_formular( $keys, $felder ) {
	$fields = array();
	$pos    = 1;
	foreach ( $keys as $key ) {
		if ( empty( $felder[ $key ] ) ) {
			error_log( "demo-setup: field '$key' is listed but not defined in 'felder'." );
			continue;
		}
		$fields[ $key ] = demo_um_feld( $key, $felder[ $key ], $pos++ );
	}
	$fields['_um_row_1'] = array(
		'type'     => 'row',
		'id'       => '_um_row_1',
		'sub_rows' => '1',
		'cols'     => '1',
		'origin'   => '_um_row_1',
	);
	return $fields;
}

$felder = $daten['felder'];

if ( ! empty( $core_forms['register'] ) ) {
	update_post_meta( $core_forms['register'], '_um_custom_fields', demo_um_formular( $daten['registrierung'], $felder ) );
	wp_update_post( array( 'ID' => $core_forms['register'], 'post_title' => 'Registrierung Who Is Who' ) );
}
if ( ! empty( $core_forms['profile'] ) ) {
	update_post_meta( $core_forms['profile'], '_um_custom_fields', demo_um_formular( $daten['profil'], $felder ) );
	wp_update_post( array( 'ID' => $core_forms['profile'], 'post_title' => 'Profil Who Is Who' ) );
}

// UM's global list of custom fields. The member directory needs it for filters.
$um_fields = get_option( 'um_fields', array() );
$core_keys = array( 'user_email', 'user_password', 'user_login', 'first_name', 'last_name' );
foreach ( $felder as $key => $f ) {
	if ( in_array( $key, $core_keys, true ) ) {
		continue;
	}
	$feld = demo_um_feld( $key, $f, 0 );
	unset( $feld['position'], $feld['in_row'], $feld['in_sub_row'], $feld['in_column'], $feld['in_group'] );
	$um_fields[ $key ] = $feld;
}
update_option( 'um_fields', $um_fields );

// ---------------------------------------------------------------------------
// 5. Member directory "Who Is Who".
// ---------------------------------------------------------------------------
if ( ! empty( $core_dirs['members'] ) ) {
	$dir = (int) $core_dirs['members'];
	$v   = $daten['verzeichnis'];
	// Who may see the directory: "alle" (anyone) or "mitglieder" (logged-in members only).
	$privacy = array( 'alle' => 0, 'gaeste' => 1, 'mitglieder' => 2 );
	$meta = array(
		'_um_privacy'                 => $privacy[ $v['sichtbar_fuer'] ?? 'alle' ] ?? 0,
		'_um_roles'                   => array_map( function ( $k ) { return 'um_' . $k; }, $role_keys ),
		'_um_search'                  => 1,
		'_um_filters'                 => 1,
		'_um_search_fields'           => $v['filter'],
		'_um_filters_expanded'        => 1,
		'_um_filters_is_collapsible'  => 0,
		'_um_show_tagline'            => 1,
		'_um_tagline_fields'          => $v['unter_dem_namen'],
		'_um_show_userinfo'           => 1,
		'_um_reveal_fields'           => $v['details'],
		'_um_userinfo_animate'        => 0,
		'_um_profile_photo'           => '1',
		'_um_cover_photos'            => '0',
		'_um_profiles_per_page'       => (int) $v['pro_seite'],
		'_um_sortby'                  => 'display_name',
		'_um_directory_header'        => '{total_users} Einträge',
		'_um_directory_header_single' => '{total_users} Eintrag',
		'_um_directory_no_users'      => 'Leider passt kein Eintrag zu Ihrer Suche.',
	);
	foreach ( $meta as $k => $val ) {
		update_post_meta( $dir, $k, $val );
	}
	wp_update_post( array( 'ID' => $dir, 'post_title' => $v['titel'] ) );
}

// ---------------------------------------------------------------------------
// 6. Sample members.
// ---------------------------------------------------------------------------
foreach ( $daten['mitglieder'] as $m ) {
	if ( username_exists( $m['login'] ) ) {
		continue;
	}
	$user_id = wp_insert_user(
		array(
			'user_login'   => $m['login'],
			'user_email'   => $m['login'] . '@example.com',
			'user_pass'    => wp_generate_password( 20 ),
			'display_name' => $m['daten']['unternehmen'],
			'role'         => 'um_' . sanitize_key( $m['rolle'] ),
		)
	);
	if ( is_wp_error( $user_id ) ) {
		error_log( 'demo-setup: ' . $user_id->get_error_message() );
		continue;
	}
	foreach ( $m['daten'] as $k => $val ) {
		update_user_meta( $user_id, $k, $val );
	}
	update_user_meta( $user_id, 'account_status', 'approved' );
	// UM cached the user on insert, before the status existed.
	UM()->user()->remove_cache( $user_id );
}

// ---------------------------------------------------------------------------
// 7. Start page and menu.
// ---------------------------------------------------------------------------
foreach ( array( 'sample-page' => 'page', 'hello-world' => 'post' ) as $slug => $type ) {
	$old = get_page_by_path( $slug, OBJECT, $type );
	if ( $old ) {
		wp_delete_post( $old->ID, true );
	}
}

$members_url  = home_url( '/who-is-who/' );
$register_url = home_url( '/registrierung/' );
$start        = <<<HTML
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">{$daten['seite']['titel']}</h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Diese Seite ist eine Demo. Sie läuft komplett im Browser – mit echtem WordPress und echtem Ultimate Member. Registrierung, Rollen und Verzeichnis sind aus einer einzigen Datei aufgebaut, die ein KI-Agent ändert.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="{$members_url}">Zum Who Is Who</a></div>
<!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="{$register_url}">Profil anlegen</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
HTML;

$start_id = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Start',
		'post_name'    => 'start',
		'post_content' => $start,
	)
);
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $start_id );

// Block themes use the newest wp_navigation post when the header menu has no ref.
$links = array(
	array( 'Start', home_url( '/' ) ),
	array( $daten['verzeichnis']['titel'], $members_url ),
	array( 'Profil anlegen', $register_url ),
	array( 'Anmelden', home_url( '/anmelden/' ) ),
);
$nav = '';
foreach ( $links as $l ) {
	$nav .= '<!-- wp:navigation-link ' . wp_json_encode( array( 'label' => $l[0], 'url' => $l[1], 'kind' => 'custom', 'isTopLevelLink' => true ) ) . ' /-->';
}
wp_insert_post(
	array(
		'post_type'    => 'wp_navigation',
		'post_status'  => 'publish',
		'post_title'   => 'Demo-Menü',
		'post_content' => $nav,
	)
);

// Replace the theme footer (it has sample links like Blog, FAQs, Shop).
$footer_id = wp_insert_post(
	array(
		'post_type'    => 'wp_template_part',
		'post_status'  => 'publish',
		'post_name'    => 'footer',
		'post_title'   => 'Footer',
		'post_content' => '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->'
			. '<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--50)">'
			. '<!-- wp:paragraph {"fontSize":"small"} --><p class="has-small-font-size">' . esc_html( $daten['seite']['titel'] ) . ' · Demo mit WordPress und Ultimate Member · Alle Einträge sind erfunden.</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:group -->',
	)
);
if ( $footer_id && ! is_wp_error( $footer_id ) ) {
	wp_set_object_terms( $footer_id, get_stylesheet(), 'wp_theme' );
	wp_set_object_terms( $footer_id, 'footer', 'wp_template_part_area' );
}

flush_rewrite_rules();
UM()->rewrite()->reset_rules();
wp_set_current_user( 0 );
