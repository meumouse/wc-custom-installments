<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Distribution\Update_Check as Checker;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Check for updates now, from this plugin's row in the plugins list.
 *
 * What update-check.js asks, so the answer lands in the row without a reload.
 * The check and its wording are Distribution\Update_Check's, shared with the
 * no-JavaScript path of the same link.
 *
 * Part of the temporary distribution channel; AGENTS.md section 6 has the
 * removal checklist.
 *
 * @since 6.1.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Update_Check extends Abstract_Route {

	/**
	 * Route path, also printed on the row link for the script.
	 *
	 * Not `/updates/check`: the Pro add-on registers that one, in this very
	 * namespace - the REST namespace belongs to the free plugin and the add-on
	 * mounts its own routes into it. Two registrations of one path would leave
	 * whichever plugin booted last in charge of both buttons.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const PATH = '/updates/self-check';

	/**
	 * Route path.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $route = self::PATH;

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	protected $methods = 'POST';


	/**
	 * Only whoever may update plugins may ask for updates.
	 *
	 * @since 6.1.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return bool
	 */
	public function permission( WP_REST_Request $request ) {
		return current_user_can('update_plugins');
	}


	/**
	 * Handle the request.
	 *
	 * @since 6.1.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$result = Checker::run();
		$outcome = Checker::describe_result( $result );
		$data = array(
			'result' => $result,
			'type' => $outcome['type'],
			'action' => $outcome['action'],
		);

		if ( 'error' === $result ) {
			return $this->error_response( $outcome['message'], $data );
		}

		return $this->success_response( array_merge( $data, array( 'message' => $outcome['message'] ) ) );
	}
}
