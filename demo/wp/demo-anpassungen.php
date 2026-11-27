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
		} elseif ( 'anwender' === $als && ( $u = get_user_by( 'login', 'jan-wagner' ) ) ) {
			// Sample CoP member with a company licence.
			wp_set_current_user( $u->ID );
			wp_set_auth_cookie( $u->ID, true );
		} else {
			wp_clear_auth_cookie();
			wp_set_current_user( 0 );
		}

		$forms  = get_option( 'um_core_forms', array() );
		$named  = array(
			'formular' => admin_url( 'post.php?post=' . (int) ( $forms['register'] ?? 0 ) . '&action=edit' ),
			'rollen'   => admin_url( 'admin.php?page=um_roles' ),
			'benutzer' => admin_url( 'users.php' ),
			// Accounts that wait for approval (today: after the invoice is paid).
			'freischaltungen' => admin_url( 'users.php?um_user_status=awaiting_admin_review' ),
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
 * Company licence by e-mail domain (Community of Practice).
 *
 * A company buys one licence; all its employees may use it. Ultimate Member
 * only knows single users, so the licence is matched by the e-mail domain:
 * whoever registers with an address of a licensed company (demo-daten.json,
 * "firmenlizenzen", still valid) gets the company role at once and is
 * approved. Everyone else keeps the role without licence and waits for
 * approval by an admin (today's invoice process).
 *
 * Runs at priority 20: Ultimate Member decides the status at priority 100,
 * based on the role, so the role must be final before that.
 */
add_action(
	'um_registration_complete',
	function ( $user_id, $args, $form_data = null ) {
		$daten   = demo_daten();
		$form_id = isset( $form_data['form_id'] ) ? (int) $form_data['form_id'] : 0;
		if ( empty( $daten['anwender'] ) || $form_id !== (int) get_option( 'demo_anwender_form' ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		$name = trim( get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'last_name', true ) );
		if ( $name ) {
			wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
		}
		$domain = strtolower( substr( strrchr( $user->user_email, '@' ), 1 ) );
		foreach ( isset( $daten['firmenlizenzen'] ) ? $daten['firmenlizenzen'] : array() as $lizenz ) {
			$gueltig = empty( $lizenz['gueltig_bis'] ) || gmdate( 'Y-m-d' ) <= $lizenz['gueltig_bis'];
			if ( $gueltig && strtolower( $lizenz['domain'] ) === $domain ) {
				$user->set_role( 'um_' . sanitize_key( $daten['anwender']['rolle_mit_firmenlizenz'] ) );
				update_user_meta( $user_id, 'unternehmen', $lizenz['firma'] );
				update_user_meta( $user_id, 'demo_firmenlizenz', $lizenz['firma'] );
				UM()->user()->remove_cache( $user_id );
				um_fetch_user( $user_id ); // UM reads the status from the (new) role at priority 100.
				break;
			}
		}
	},
	20,
	3
);

/**
 * "Premium" badge next to the name of Premium members in the member directory.
 * The directory loads its cards by AJAX; this filter changes the card data.
 */
add_filter(
	'um_ajax_get_members_data',
	function ( $data, $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'um_premium', (array) $user->roles, true ) ) {
			// UM's slot right after the name block. The name itself is a flex row that
			// never wraps, so the badge must not go inside display_name_html.
			$badge = '<div class="demo-premium-row" style="text-align:center;margin:0 0 6px">'
				. '<span class="demo-premium" style="display:inline-block;padding:2px 10px;border-radius:999px;background:#c9771a;color:#fff;font-size:12px;line-height:1.5;font-weight:600;white-space:nowrap">Premium</span>'
				. '</div>';
			$data['hook_just_after_name'] = ( isset( $data['hook_just_after_name'] ) ? $data['hook_just_after_name'] : '' ) . $badge;
		}
		return $data;
	},
	10,
	2
);

/**
 * Small badge with the current demo version, bottom right on every page.
 * It proves in the call that the newest version from GitHub is running.
 * Only the number: the change texts in aenderungen.json stay internal.
 */
add_action(
	'wp_footer',
	function () {
		$stand = demo_stand();
		if ( ! $stand ) {
			return;
		}
		printf(
			'<div style="position:fixed;right:12px;bottom:12px;z-index:9999;padding:6px 12px;border-radius:999px;background:#1e73be;color:#fff;font:600 13px/1.4 system-ui,sans-serif;box-shadow:0 2px 8px rgba(0,0,0,.2)">Demo-Stand %s</div>',
			esc_html( $stand['version'] )
		);
	}
);
