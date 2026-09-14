<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Campaigns\Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Create or update a campaign.
 *
 * Rules that cannot work come back as messages keyed by the part of the form
 * they concern, and nothing is written; the screen shows each beside its card.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Campaigns_Save extends Abstract_Campaign_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/campaigns';

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
		$campaign = ( isset( $payload['campaign'] ) && is_array( $payload['campaign'] ) ) ? $payload['campaign'] : array();
		$result = Repository::save( $campaign );

		if ( ! empty( $result['errors'] ) ) {
			return $this->error_response(
				__( 'Revise os campos destacados antes de salvar.', 'wc-custom-installments' ),
				array( 'errors' => $result['errors'] )
			);
		}

		return $this->success_response( array(
			'message' => __( 'Campanha salva.', 'wc-custom-installments' ),
			'campaign' => Repository::get( $result['id'] ),
		) );
	}
}
