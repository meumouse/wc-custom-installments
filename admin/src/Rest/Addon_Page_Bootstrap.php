<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Menu;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use WP_Error;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Hydrate a screen an add-on fills.
 *
 * The host draws the header from this payload; everything inside the panel is
 * the add-on's, which talks to the REST API with the root and nonce passed on
 * here.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Addon_Page_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/pages/(?P<slug>[a-z0-9_-]+)';

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle the request.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$slug = sanitize_key( (string) $request->get_param('slug') );
		$pages = Menu::get_pages();

		if ( ! isset( $pages[ $slug ] ) ) {
			return new WP_Error(
				'wc_custom_installments_unknown_page',
				__( 'Esta página não existe ou o complemento que a cria não está ativo.', 'wc-custom-installments' ),
				array( 'status' => 404 )
			);
		}

		$page = $pages[ $slug ];

		/**
		 * Filter the payload of a screen an add-on fills.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $payload | Bootstrap payload
		 * @param string $slug | Page slug
		 * @return array<string,mixed>
		 */
		$payload = (array) apply_filters( 'WC_Custom_Installments/Admin/Addon_Page_Data', array(
			'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
			'page' => 'addon',
			'addon' => array(
				'slug' => $slug,
				'title' => $page['title'],
				'description' => $page['description'],
				'component' => $page['component'],
			),
			'pro' => array(
				'active' => Helpers::is_pro_active(),
				'store_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_STORE_URL ),
				'docs_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_DOCS_URL ),
			),
			'rest' => array(
				'root' => esc_url_raw( rest_url( WC_CUSTOM_INSTALLMENTS_REST ) ),
				'nonce' => wp_create_nonce('wp_rest'),
			),
		), $slug );

		return rest_ensure_response( $payload );
	}
}
