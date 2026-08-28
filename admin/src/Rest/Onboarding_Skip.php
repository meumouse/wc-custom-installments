<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Core\Onboarding;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Leave the setup wizard without finishing it.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Onboarding_Skip extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $route = '/admin/onboarding/skip';

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle the request.
	 *
	 * Skipping dismisses the reminder but does not mark the wizard complete:
	 * the store owner may well come back, and the screen should not pretend
	 * they answered questions they did not.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		return $this->success_response( array(
			'state' => Onboarding::update_state( array( 'dismissed' => true ) ),
		) );
	}
}
