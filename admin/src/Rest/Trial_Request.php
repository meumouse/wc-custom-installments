<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Onboarding\Trial;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Ask the licensing service for a trial key, on the store owner's say-so.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Trial_Request extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/trial';

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
	 * Nothing leaves the site until this route is called, and this route is
	 * only called when someone fills in the form and presses the button.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$payload = (array) $request->get_json_params();

		$result = Trial::request( array(
			'first_name' => sanitize_text_field( $payload['first_name'] ?? '' ),
			'last_name' => sanitize_text_field( $payload['last_name'] ?? '' ),
			'email' => sanitize_email( $payload['email'] ?? '' ),
			'phone' => sanitize_text_field( $payload['phone'] ?? '' ),
		) );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message(), array( 'code' => $result->get_error_code() ) );
		}

		return $this->success_response( array(
			'message' => __( 'Seu teste está pronto. A chave está abaixo, e uma cópia está a caminho do seu e-mail.', 'wc-custom-installments' ),
			'trial' => $result,
		) );
	}
}
