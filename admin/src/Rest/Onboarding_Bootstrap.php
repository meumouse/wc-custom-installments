<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Menu;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Onboarding;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Hydrate the setup wizard.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Onboarding_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $route = '/admin/onboarding';

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle the request.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		/**
		 * Filter the setup wizard bootstrap payload.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $payload | Bootstrap payload
		 * @return array<string,mixed>
		 */
		$payload = (array) apply_filters( 'WC_Custom_Installments/Admin/Onboarding_Bootstrap_Data', array(
			'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
			'page' => 'onboarding',
			'state' => Onboarding::get_state(),
			'settings' => Repository::get_settings(),
			'links' => array(
				'settings_url' => esc_url_raw( admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE ) ),
				'docs_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_DOCS_URL ),
				'store_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_STORE_URL ),
			),
			'rest' => array(
				'root' => esc_url_raw( rest_url( WC_CUSTOM_INSTALLMENTS_REST ) ),
				'nonce' => wp_create_nonce('wp_rest'),
			),
		) );

		return rest_ensure_response( $payload );
	}
}
