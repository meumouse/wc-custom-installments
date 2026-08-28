<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Transfer;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Read an exported configuration back into this store.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Settings_Import extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $route = '/admin/settings/import';

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
		$payload = $request->get_json_params();
		$document = $payload['document'] ?? null;

		// The browser may send the file contents as a raw string.
		if ( is_string( $document ) ) {
			$document = json_decode( $document, true );
		}

		$result = Transfer::import( $document );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message() );
		}

		return $this->success_response( array(
			'message' => __( 'Settings imported.', 'wc-custom-installments' ),
			'settings' => $result,
			'schema' => Registry::get_schema(),
		) );
	}
}
