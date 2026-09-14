<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Menu;
use MeuMouse\WC_Custom_Installments\Admin\Product_Settings;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository as Settings;
use MeuMouse\WC_Custom_Installments\Campaigns\Repository;
use MeuMouse\WC_Custom_Installments\Campaigns\Rules;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Hydrate the campaigns screen.
 *
 * Every campaign comes at once: a store runs a handful, not thousands, and the
 * screen filters and searches them in the browser as Joinotify's lists do.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Campaigns_Bootstrap extends Abstract_Campaign_Route {

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
	protected $methods = 'GET';


	/**
	 * Handle the request.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		/**
		 * Filter the campaigns screen's bootstrap payload.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $payload | Bootstrap payload
		 * @return array<string,mixed>
		 */
		$payload = (array) apply_filters( 'WC_Custom_Installments/Admin/Campaigns_Data', array(
			'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
			'page' => 'campaigns',
			'locked' => $this->is_locked(),
			'campaigns' => Repository::all(),
			'defaults' => Rules::defaults(),
			'now' => wp_date('Y-m-d\TH:i'),
			'timezone' => wp_timezone_string(),
			'store' => self::store_settings(),
			'features' => array(
				'fee_per_installment' => Features::is_available('fee_per_installment'),
				'gateway_discounts' => Features::is_available('gateway_discounts'),
			),
			'taxonomies' => array(
				'categories' => taxonomy_exists('product_cat'),
				'tags' => taxonomy_exists('product_tag'),
				'brands' => taxonomy_exists('product_brand'),
			),
			'gateways' => self::gateways(),
			'pro' => array(
				'active' => Helpers::is_pro_active(),
				'store_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_STORE_URL ),
				'docs_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_DOCS_URL ),
			),
			'links' => array(
				'settings' => esc_url_raw( Menu::page_url() ),
				'license' => esc_url_raw( Menu::license_url() ),
			),
			'environment' => array(
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


	/**
	 * The store's own discount and plan, which a campaign's blank fields keep.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function store_settings() {
		return array(
			'discount_amount' => (string) Settings::get('discount_main_price'),
			'discount_method' => (string) Settings::get('product_price_discount_method'),
			'max' => (int) Settings::get('max_qtd_installments'),
			'without_fee' => (int) Settings::get('max_qtd_installments_without_fee'),
			'min_value' => (float) Settings::get('min_value_installments'),
			'fee' => (float) Settings::get('fee_installments_global'),
			'fee_per_installment' => Settings::is_active('set_fee_per_installment'),
		);
	}


	/**
	 * The payment methods a campaign's discount can be tied to, as options.
	 *
	 * @since 6.0.0
	 * @return array<int,array{value:string,label:string}>
	 */
	private static function gateways() {
		$options = array();

		foreach ( Product_Settings::get_gateway_options() as $value => $label ) {
			$options[] = array(
				'value' => (string) $value,
				'label' => html_entity_decode( (string) $label, ENT_QUOTES, 'UTF-8' ),
			);
		}

		return $options;
	}
}
