<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Campaigns\Repository;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Switch campaigns on or off, trash, restore or delete them.
 *
 * One route for one campaign or many, so the row switch and the bulk actions
 * say the same thing. Without the Pro licence only switching on is refused:
 * a store must always be able to stop or remove a campaign.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Campaigns_Action extends Abstract_Campaign_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/campaigns/action';

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
		$payload = $request->get_json_params();
		$action = sanitize_key( (string) ( $payload['action'] ?? '' ) );
		$ids = array_filter( array_map( 'intval', (array) ( $payload['ids'] ?? array() ) ) );
		$messages = self::messages();

		if ( ! isset( $messages[ $action ] ) || empty( $ids ) ) {
			return $this->error_response( __( 'Escolha uma ação e ao menos uma campanha.', 'wc-custom-installments' ) );
		}

		if ( 'activate' === $action && $this->is_locked() ) {
			return $this->locked_error();
		}

		$count = Repository::apply_action( $ids, $action );

		if ( 0 === $count ) {
			return $this->error_response( __( 'Nenhuma campanha foi alterada. Recarregue a página e tente novamente.', 'wc-custom-installments' ) );
		}

		return $this->success_response( array(
			'message' => sprintf( $messages[ $action ], $count ),
			'campaigns' => Repository::all(),
		) );
	}


	/**
	 * What each action reports, with the number of campaigns it changed.
	 *
	 * @since 6.0.0
	 * @return array<string,string>
	 */
	private static function messages() {
		return array(
			/* translators: %d: number of campaigns */
			'activate' => __( 'Campanhas ativadas: %d.', 'wc-custom-installments' ),
			/* translators: %d: number of campaigns */
			'deactivate' => __( 'Campanhas desativadas: %d.', 'wc-custom-installments' ),
			/* translators: %d: number of campaigns */
			'trash' => __( 'Campanhas movidas para a lixeira: %d.', 'wc-custom-installments' ),
			/* translators: %d: number of campaigns */
			'restore' => __( 'Campanhas restauradas como inativas: %d.', 'wc-custom-installments' ),
			/* translators: %d: number of campaigns */
			'delete' => __( 'Campanhas excluídas permanentemente: %d.', 'wc-custom-installments' ),
		);
	}
}
