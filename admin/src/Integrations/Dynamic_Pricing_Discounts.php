<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;

defined('ABSPATH') || exit;

/**
 * Dynamic Pricing & Discounts, which rewrites prices at display time.
 *
 * Its adjusted price is the one the customer will be charged, so it is the one
 * the instalment plan has to be built from.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Dynamic_Pricing_Discounts {

	/**
	 * Register the hook, when the plugin is here.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! self::is_active() ) {
			return;
		}

		add_filter( 'WC_Custom_Installments/Price/Set_Values_Price', array( $this, 'set_adjusted_price' ), 10, 2 );
	}


	/**
	 * Whether the plugin is active.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_active() {
		return class_exists('RP_WCDPD');
	}


	/**
	 * Describe the integration for the settings screen.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public static function describe() {
		return array(
			'id' => 'dynamic_pricing_discounts',
			'title' => 'Dynamic Pricing & Discounts',
			'description' => __( 'Regras de preço aplicadas na exibição.', 'wc-custom-installments' ),
			'url' => 'https://wordpress.org/plugins/rp-woocommerce-dynamic-pricing-discounts/',
			'active' => self::is_active(),
			'fields' => array(
				array(
					'type' => 'toggle',
					'key' => 'integration_dynamic_pricing_use_adjusted_price',
					'label' => __( 'Calcular as parcelas pelo preço ajustado', 'wc-custom-installments' ),
					'description' => __( 'Monta o plano a partir do preço que aquele plugin ajustou, que é o preço que o cliente vai pagar. Em um produto variável, parte do preço da variação mais barata.', 'wc-custom-installments' ),
				),
			),
		);
	}


	/**
	 * Use the plugin's adjusted price.
	 *
	 * A variable product is priced from its cheapest variation, as in 5.x: the
	 * plan under a "from" price has to start from the lowest price the customer
	 * can actually pay. The variation the customer then picks carries its own
	 * price, and the runtime follows that.
	 *
	 * @since 6.0.0
	 * @param float $price | Price resolved so far
	 * @param object $product | Product object
	 * @return float
	 */
	public function set_adjusted_price( $price, $product ) {
		if ( ! $product instanceof \WC_Product || ! self::is_active() ) {
			return $price;
		}

		if ( ! Repository::is_active('integration_dynamic_pricing_use_adjusted_price') ) {
			return $price;
		}

		if ( $product->is_type('variable') ) {
			return $this->lowest_variation_price( $product, $price );
		}

		$adjusted = self::adjusted_price( $product );

		return null !== $adjusted ? $adjusted : $price;
	}


	/**
	 * The lowest price among a variable product's visible variations.
	 *
	 * @since 6.0.0
	 * @param object $product | Variable product
	 * @param float $price | Returned when no variation has a price
	 * @return float
	 */
	private function lowest_variation_price( $product, $price ) {
		$prices = array();

		foreach ( $product->get_visible_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation instanceof \WC_Product ) {
				continue;
			}

			$adjusted = self::adjusted_price( $variation );
			$variation_price = null !== $adjusted ? $adjusted : $variation->get_price();

			if ( is_numeric( $variation_price ) ) {
				$prices[] = (float) $variation_price;
			}
		}

		return $prices ? min( $prices ) : $price;
	}


	/**
	 * The price the plugin adjusted a product to, for a single unit.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return float|null Null when the plugin offers no adjusted price.
	 */
	private static function adjusted_price( $product ) {
		if ( ! method_exists( 'RP_WCDPD_Product_Pricing', 'get_product_price' ) ) {
			return null;
		}

		$adjusted = \RP_WCDPD_Product_Pricing::get_product_price( $product, 1 );

		return ( is_numeric( $adjusted ) && $adjusted > 0 ) ? (float) $adjusted : null;
	}
}
