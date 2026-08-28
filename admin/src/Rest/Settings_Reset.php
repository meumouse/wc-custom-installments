<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Restore every setting to its default.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Settings_Reset extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $route = '/admin/settings/reset';

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
	 * @since 1.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$defaults = Repository::reset_settings();

		return $this->success_response( array(
			'message' => __( 'Settings restored to their defaults.', 'wc-custom-installments' ),
			'settings' => $defaults,
			'schema' => Registry::get_schema(),
		) );
	}
}
