<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Campaigns\Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Copy a campaign, as an inactive one.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Campaigns_Duplicate extends Abstract_Campaign_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/campaigns/duplicate';

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
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		if ( $this->is_locked() ) {
			return $this->locked_error();
		}

		$payload = $request->get_json_params();
		$id = Repository::duplicate( (int) ( $payload['id'] ?? 0 ) );

		if ( ! $id ) {
			return $this->error_response( __( 'Não foi possível duplicar a campanha.', 'wc-custom-installments' ) );
		}

		return $this->success_response( array(
			'message' => __( 'Campanha duplicada. A cópia está inativa.', 'wc-custom-installments' ),
			'campaign' => Repository::get( $id ),
			'campaigns' => Repository::all(),
		) );
	}
}
