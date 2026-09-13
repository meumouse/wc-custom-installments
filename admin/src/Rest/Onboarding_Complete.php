<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Core\Onboarding;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Mark the setup wizard as finished.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Onboarding_Complete extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/onboarding/complete';

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Handle the request.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		return $this->success_response( array(
			'message' => __( 'Setup complete.', 'wc-custom-installments' ),
			'state' => Onboarding::complete(),
		) );
	}
}
