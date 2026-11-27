<?php
/**
 * Plugin Name: Demo-Anpassungen
 * Description: Custom behavior for the "Who Is Who" demo. Loaded as a must-use plugin in WordPress Playground.
 *
 * Live changes during the call go here (behavior) or into demo-daten.json (data).
 */

/**
 * Read demo-daten.json once per request.
 */
function demo_daten() {
	static $daten = null;
	if ( null === $daten ) {
		$daten = json_decode( (string) @file_get_contents( '/wordpress/demo/demo-daten.json' ), true );
		if ( ! is_array( $daten ) ) {
			$daten = array();
		}
	}
	return $daten;
}

/**
 * Read the change log and return the newest entry.
 */
function demo_stand() {
	$log = json_decode( (string) @file_get_contents( '/wordpress/demo/aenderungen.json' ), true );
	if ( empty( $log['eintraege'] ) ) {
		return null;
	}
	return end( $log['eintraege'] );
}

/**
 * View switch for the demo page: ?demo_als=besucher|admin&ziel=<path or name>.
 *
 * ONLY FOR THE PLAYGROUND DEMO. This logs anyone in as admin.
 * Never copy this into a real site.
 */
add_action(
	'init',
	function () {
		if ( empty( $_GET['demo_als'] ) ) {
			return;
		}
		$als  = sanitize_key( wp_unslash( $_GET['demo_als'] ) );
		$ziel = isset( $_GET['ziel'] ) ? sanitize_text_field( wp_unslash( $_GET['ziel'] ) ) : '/';

		if ( 'admin' === $als ) {
			wp_set_current_user( 1 );
			wp_set_auth_cookie( 1, true );
		} else {
			wp_clear_auth_cookie();
			wp_set_current_user( 0 );
		}

		$forms  = get_option( 'um_core_forms', array() );
		$named  = array(
			'formular' => admin_url( 'post.php?post=' . (int) ( $forms['register'] ?? 0 ) . '&action=edit' ),
			'rollen'   => admin_url( 'admin.php?page=um_roles' ),
			'benutzer' => admin_url( 'users.php' ),
		);
		$target = isset( $named[ $ziel ] ) ? $named[ $ziel ] : home_url( '/' . ltrim( $ziel, '/' ) );
		wp_safe_redirect( $target );
		exit;
	},
	1
);

/**
 * After registration: give the role that matches the chosen profile type,
 * and show the company name instead of the e-mail address.
 */
add_action(
	'um_registration_complete',
	function ( $user_id ) {
		$daten = demo_daten();
		$typ   = get_user_meta( $user_id, 'profil_typ', true );
		if ( is_array( $typ ) ) { // UM saves radio choices as a one-item list.
			$typ = reset( $typ );
		}
		$map   = isset( $daten['rolle_nach_profil'] ) ? $daten['rolle_nach_profil'] : array();
		if ( is_string( $typ ) && isset( $map[ $typ ] ) ) {
			$user = new WP_User( $user_id );
			$user->set_role( 'um_' . sanitize_key( $map[ $typ ] ) );
		}
		$firma = get_user_meta( $user_id, 'unternehmen', true );
		if ( $firma ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $firma ) );
		}
		if ( function_exists( 'UM' ) ) {
			UM()->user()->remove_cache( $user_id );
		}
	},
	20
);

/**
 * "Premium" badge next to the name of Premium members in the member directory.
 * The directory loads its cards by AJAX; this filter changes the card data.
 */
add_filter(
	'um_ajax_get_members_data',
	function ( $data, $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'um_premium', (array) $user->roles, true ) && ! empty( $data['display_name_html'] ) ) {
			$data['display_name_html'] .= ' <span class="demo-premium" style="display:inline-block;margin-left:6px;padding:1px 8px;border-radius:999px;background:#c9771a;color:#fff;font-size:12px;font-weight:600;vertical-align:middle">Premium</span>';
		}
		return $data;
	},
	10,
	2
);

/**
 * Small badge with the current demo version, bottom right on every page.
 * It proves in the call that the newest version from GitHub is running.
 */
add_action(
	'wp_footer',
	function () {
		$stand = demo_stand();
		if ( ! $stand ) {
			return;
		}
		printf(
			'<div style="position:fixed;right:12px;bottom:12px;z-index:9999;padding:6px 12px;border-radius:999px;background:#1e73be;color:#fff;font:600 13px/1.4 system-ui,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.2)">Demo-Stand %s · %s</div>',
			esc_html( $stand['version'] ),
			esc_html( $stand['titel_de'] )
		);
	}
);
