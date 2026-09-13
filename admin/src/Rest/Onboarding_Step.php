<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Onboarding\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Onboarding;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Persist one step of the setup wizard.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Onboarding_Step extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/onboarding/step';

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
	 * The payload is narrowed to what the wizard is allowed to write before it
	 * reaches the repository, so a malformed step can never touch a setting the
	 * wizard does not show.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$step = isset( $payload['step'] ) ? sanitize_key( $payload['step'] ) : '';
		$incoming = ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) ? $payload['settings'] : array();

		$allowed = array_intersect_key( $incoming, array_flip( Registry::allowed_keys() ) );
		$saved = Repository::save_settings( $allowed );

		if ( '' !== $step ) {
			Onboarding::update_state( array( 'step' => $step ) );
		}

		return $this->success_response( array(
			'settings' => array_intersect_key( $saved, array_flip( Registry::allowed_keys() ) ),
			'state' => Onboarding::get_state(),
		) );
	}
}
