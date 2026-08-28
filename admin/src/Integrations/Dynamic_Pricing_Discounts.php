<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

defined('ABSPATH') || exit;

/**
 * Dynamic Pricing & Discounts, which rewrites prices at display time.
 *
 * Its adjusted price is the one the customer will be charged, so it is the one
 * the instalment plan has to be built from.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Dynamic_Pricing_Discounts {

	/**
	 * Register the hook, when the plugin is here.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_active() {
		return class_exists('RP_WCDPD');
	}


	/**
	 * Use the plugin's adjusted price.
	 *
	 * @since 1.0.0
	 * @param float $price | Price resolved so far
	 * @param object $product | Product object
	 * @return float
	 */
	public function set_adjusted_price( $price, $product ) {
		if ( ! $product instanceof \WC_Product || ! self::is_active() ) {
			return $price;
		}

		// A variable product has no single price to adjust; the variation the
		// customer picks carries its own, and the runtime follows that.
		if ( $product->is_type('variable') ) {
			return $price;
		}

		if ( ! method_exists( 'RP_WCDPD_Product_Pricing', 'get_product_price' ) ) {
			return $price;
		}

		$adjusted = \RP_WCDPD_Product_Pricing::get_product_price( $product, 1 );

		return ( is_numeric( $adjusted ) && $adjusted > 0 ) ? (float) $adjusted : $price;
	}
}
