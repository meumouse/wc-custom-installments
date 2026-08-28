<?php

namespace MeuMouse\WC_Custom_Installments\Core;

defined('ABSPATH') || exit;

/**
 * Own the setup wizard's state and decide when to show it.
 *
 * The wizard is where a fresh install picks its installment rules, so it has to
 * actually be seen: once on activation, then as a dismissible reminder. Being
 * seen is not the same as taking over the admin — the redirect fires at most
 * once per user, and the wizard stays reachable by URL afterwards.
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
	 * Read the stored wizard state.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function get_state() {
		$state = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $state ) ? $state : array(), array(
			'completed'    => false,
			'completed_at' => 0,
			'step'         => '',
			'dismissed'    => false,
			'version'      => 1,
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
	 * Mark the wizard as completed.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function complete() {
		self::update_state( array(
			'completed'    => true,
			'completed_at' => time(),
		) );

		delete_transient( self::ACTIVATION_KEY );
	}
}
