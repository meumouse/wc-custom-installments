<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Persist the settings payload.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Settings_Save extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/settings';

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
	 * The screen rehydrates from this answer, so it returns what was actually
	 * stored rather than echoing the submitted payload — a locked field the
	 * client tried to change must come back with its real value.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		$settings = ( isset( $payload['settings'] ) && is_array( $payload['settings'] ) ) ? $payload['settings'] : array();

		$saved = Repository::save_settings( $settings );

		return $this->success_response( array(
			'message' => __( 'Configurações salvas.', 'wc-custom-installments' ),
			'settings' => $saved,
		) );
	}
}
