<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;


defined('ABSPATH') || exit;

/**
 * Tiered Pricing Table, which owns the price once a quantity rule applies.
 *
 * When that plugin is deciding the price, the instalment plan has to be built
 * from its number rather than the product's: otherwise a customer buying ten
 * sees the tier price and instalments calculated from the single-unit price.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Tiered_Pricing_Table {

	/**
	 * Register the hooks, when the plugin is here.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! self::is_active() ) {
			return;
		}

		add_filter( 'WC_Custom_Installments/Price/Set_Values_Price', array( $this, 'set_tier_price' ), 10, 2 );
		add_filter( 'WC_Custom_Installments/Assets/Frontend_Params', array( $this, 'announce_plugin' ) );
	}


	/**
	 * Whether the plugin is active.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_active() {
		return class_exists('TierPricingTable\\PriceManager');
	}


	/**
	 * Build the plan from the tier price when one applies.
	 *
	 * @since 1.0.0
	 * @param float $price | Price resolved so far
	 * @param object $product | Product object
	 * @return float
	 */
	public function set_tier_price( $price, $product ) {
		if ( ! $product instanceof \WC_Product || ! self::is_active() ) {
			return $price;
		}

		$rule = \TierPricingTable\PriceManager::getPricingRule( $product->get_id() );

		if ( ! $rule ) {
			return $price;
		}

		$tier_price = $rule->getPrice( 1 );

		return $tier_price ? (float) $tier_price : $price;
	}


	/**
	 * Tell the storefront runtime the other plugin is in charge.
	 *
	 * Without this the runtime would recalculate the price from the quantity
	 * itself and overwrite what the tier plugin just wrote.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $params | Runtime parameters
	 * @return array<string,mixed>
	 */
	public function announce_plugin( $params ) {
		$params['tiered_pricing_active'] = true;

		return $params;
	}
}
