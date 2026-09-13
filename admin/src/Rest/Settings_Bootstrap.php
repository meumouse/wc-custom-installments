<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Hydrate the settings screen.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Settings_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $route = '/admin/settings';

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
		 * Filter the settings bootstrap payload.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $payload | Bootstrap payload
		 * @return array<string,mixed>
		 */
		$payload = (array) apply_filters( 'WC_Custom_Installments/Admin/Bootstrap_Data', array(
			'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
			'page' => 'settings',
			'schema' => Registry::get_schema(),
			'settings' => Repository::get_settings(),
			'features' => Features::all(),
			'pro' => array(
				'active' => Helpers::is_pro_active(),
				'store_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_STORE_URL ),
				'docs_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_DOCS_URL ),
			),
			'environment' => array(
				// Decoded: WooCommerce returns entities, and the app renders text.
				'currency_symbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'price_format' => html_entity_decode( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ), ENT_QUOTES, 'UTF-8' ),
				'decimal_separator' => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
			),
			'rest' => array(
				'root' => esc_url_raw( rest_url( WC_CUSTOM_INSTALLMENTS_REST ) ),
				'nonce' => wp_create_nonce('wp_rest'),
			),
		) );

		return rest_ensure_response( $payload );
	}
}
