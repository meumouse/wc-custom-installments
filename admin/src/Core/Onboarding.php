<?php

namespace MeuMouse\WC_Custom_Installments\Core;

use MeuMouse\WC_Custom_Installments\Admin\Menu;

defined('ABSPATH') || exit;

/**
 * Own the setup wizard's state and decide when to show it.
 *
 * The wizard is where a fresh install picks its instalment rules, so it has to
 * actually be seen: once on activation, then as a dismissible reminder.
 *
 * Being seen is not the same as taking over the admin. The redirect fires at
 * most once per user, a bulk activation never redirects at all, and the wizard
 * stays reachable by URL afterwards.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Onboarding {

	/**
	 * Option holding the wizard state.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION = 'wc_custom_installments_onboarding';

	/**
	 * Transient set on activation to trigger the one-time redirect.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const ACTIVATION_KEY = 'wc_custom_installments_activation_redirect';

	/**
	 * User meta recording that this user already landed on the wizard.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SEEN_META = 'wc_custom_installments_onboarding_seen';

	/**
	 * Admin page slug of the wizard.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const PAGE = 'wc-custom-installments-onboarding';


	/**
	 * Register the hooks that surface the wizard.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ), 1 );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_post_wc_custom_installments_dismiss_onboarding', array( $this, 'handle_dismiss' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}


	/**
	 * Flag a fresh activation so the next admin request opens the wizard.
	 *
	 * Bulk activations are filtered out later, where the request that would be
	 * redirected can actually be inspected.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function on_activation() {
		if ( self::is_completed() ) {
			return;
		}

		set_transient( self::ACTIVATION_KEY, 1, MINUTE_IN_SECONDS );
	}


	/**
	 * Send the activating user to the wizard, once.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function maybe_redirect() {
		if ( ! get_transient( self::ACTIVATION_KEY ) ) {
			return;
		}

		delete_transient( self::ACTIVATION_KEY );

		if ( wp_doing_ajax() || wp_doing_cron() || is_network_admin() ) {
			return;
		}

		// Activating several plugins at once should not hijack the request:
		// the other plugins' own redirects would fight over it, and the user
		// asked for the plugins screen.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads only which bulk action WordPress is performing.
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		if ( ! current_user_can( Helpers::capability() ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( get_user_meta( $user_id, self::SEEN_META, true ) ) {
			return;
		}

		update_user_meta( $user_id, self::SEEN_META, time() );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}


	/**
	 * Remind the store owner the wizard is there, on our own screens only.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_notice() {
		if ( self::is_completed() || self::is_dismissed() ) {
			return;
		}

		if ( ! current_user_can( Helpers::capability() ) || ! Helpers::is_plugin_screen() ) {
			return;
		}

		if ( self::PAGE === Helpers::current_admin_page() ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p>%s</p><p><a class="button button-primary" href="%s">%s</a> <a class="button-link" href="%s">%s</a></p></div>',
			esc_html__( 'Finish setting up Custom Installments and your store will be showing instalment plans in about a minute.', 'wc-custom-installments' ),
			esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
			esc_html__( 'Open the setup', 'wc-custom-installments' ),
			esc_url( wp_nonce_url(
				admin_url('admin-post.php?action=wc_custom_installments_dismiss_onboarding'),
				'wc_custom_installments_dismiss_onboarding'
			) ),
			esc_html__( 'Dismiss', 'wc-custom-installments' )
		);
	}


	/**
	 * Stop showing the reminder.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_dismiss() {
		check_admin_referer('wc_custom_installments_dismiss_onboarding');

		if ( ! current_user_can( Helpers::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'wc-custom-installments' ) );
		}

		self::update_state( array( 'dismissed' => true ) );

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE ) );
		exit;
	}


	/**
	 * Flag the wizard screen on the admin body.
	 *
	 * The wizard covers the viewport, and a fixed overlay does not stop the
	 * document underneath from scrolling. Locking it from CSS costs nothing and
	 * works before any JavaScript has run.
	 *
	 * @since 1.0.0
	 * @param string $classes | Space-separated body classes
	 * @return string
	 */
	public function add_body_class( $classes ) {
		if ( self::PAGE !== Helpers::current_admin_page() ) {
			return $classes;
		}

		return trim( $classes . ' wc-custom-installments-onboarding-fullscreen' );
	}


	/**
	 * Read the stored wizard state.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function get_state() {
		$state = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $state ) ? $state : array(), array(
			'completed' => false,
			'completed_at' => 0,
			'step' => '',
			'dismissed' => false,
			'version' => 1,
		) );
	}


	/**
	 * Persist a partial state update.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $changes | Keys to merge into the stored state
	 * @return array<string,mixed> The state as stored.
	 */
	public static function update_state( $changes ) {
		$state = array_merge( self::get_state(), (array) $changes );

		update_option( self::OPTION, $state, false );

		return $state;
	}


	/**
	 * Whether the wizard has been completed.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_completed() {
		$state = self::get_state();

		return ! empty( $state['completed'] );
	}


	/**
	 * Whether the reminder has been dismissed.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_dismissed() {
		$state = self::get_state();

		return ! empty( $state['dismissed'] );
	}


	/**
	 * Mark the wizard as completed.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function complete() {
		delete_transient( self::ACTIVATION_KEY );

		return self::update_state( array(
			'completed' => true,
			'completed_at' => time(),
		) );
	}
}
