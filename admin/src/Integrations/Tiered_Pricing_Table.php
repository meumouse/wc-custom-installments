<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Frontend\Headless_Mode;

defined('ABSPATH') || exit;

/**
 * Tiered Pricing Table, which owns the price once a quantity rule applies.
 *
 * When that plugin is deciding the price, the instalment plan has to be built
 * from its number rather than the product's: otherwise a customer buying ten
 * sees the tier price and instalments calculated from the single-unit price.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Tiered_Pricing_Table {

	/**
	 * Register the hooks, when the plugin is here.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! self::is_active() ) {
			return;
		}

		add_filter( 'WC_Custom_Installments/Price/Set_Values_Price', array( $this, 'set_tier_price' ), 10, 2 );
		add_filter( 'WC_Custom_Installments/Assets/Frontend_Params', array( $this, 'announce_plugin' ) );

		// The markup only goes to make room for the price group. A headless
		// store has no price group, and the tier plugin's wrapper is then the
		// only price markup there is.
		if ( Headless_Mode::is_enabled() ) {
			return;
		}

		// Late enough for the plugin to have registered its own price filters.
		add_action( 'init', array( $this, 'remove_price_markup' ), 20 );
		add_action( 'wp', array( $this, 'remove_price_markup' ) );
	}


	/**
	 * Whether the plugin is active.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_active() {
		return class_exists('TierPricingTable\\PriceManager');
	}


	/**
	 * Describe the integration for the settings screen.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public static function describe() {
		return array(
			'id' => 'tiered_pricing_table',
			'title' => 'Tiered Pricing Table',
			'description' => __( 'Preços por faixa de quantidade.', 'wc-custom-installments' ),
			'url' => 'https://wordpress.org/plugins/tier-pricing-table/',
			'active' => self::is_active(),
			'fields' => array(
				array(
					'type' => 'toggle',
					'key' => 'integration_tiered_pricing_use_tier_price',
					'label' => __( 'Calcular as parcelas pelo preço da faixa', 'wc-custom-installments' ),
					'description' => __( 'Monta o plano a partir do preço da faixa na quantidade mínima da regra, em vez do preço unitário do produto. Sem isso, um cliente que precisa comprar dez vê o preço da faixa e parcelas calculadas sobre o preço de uma unidade.', 'wc-custom-installments' ),
				),
			),
		);
	}


	/**
	 * Build the plan from the tier price when one applies.
	 *
	 * The price is the tier's at the rule's minimum quantity, as in 5.x: a
	 * product that cannot be bought below that quantity has no single-unit
	 * price for the plan to start from.
	 *
	 * @since 6.0.0
	 * @param float $price | Price resolved so far
	 * @param object $product | Product object
	 * @return float
	 */
	public function set_tier_price( $price, $product ) {
		if ( ! $product instanceof \WC_Product || ! self::is_active() ) {
			return $price;
		}

		if ( ! Repository::is_active('integration_tiered_pricing_use_tier_price') ) {
			return $price;
		}

		$rule = \TierPricingTable\PriceManager::getPricingRule( $product->get_id() );

		if ( ! $rule ) {
			return $price;
		}

		$tier_price = null;

		if ( method_exists( $rule, 'getMinimum' ) && method_exists( $rule, 'getTierPrice' ) ) {
			$minimum = (int) $rule->getMinimum();
			$tier_price = $rule->getTierPrice( max( 1, $minimum ) );
		} elseif ( method_exists( $rule, 'getPrice' ) ) {
			$tier_price = $rule->getPrice( 1 );
		}

		return ( is_numeric( $tier_price ) && $tier_price > 0 ) ? (float) $tier_price : $price;
	}


	/**
	 * Tell the storefront runtime the other plugin is in charge.
	 *
	 * Without this the runtime would recalculate the price from the quantity
	 * itself and overwrite what the tier plugin just wrote. The product's rules
	 * go along under their 5.x name, `tiered_get_rules`, with `check_tiered_plugin`
	 * beside the 6.0 flag.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $params | Runtime parameters
	 * @return array<string,mixed>
	 */
	public function announce_plugin( $params ) {
		$params = (array) $params;
		$params['tiered_pricing_active'] = true;
		$params['check_tiered_plugin'] = true;
		$params['tiered_get_rules'] = array();

		$product_id = isset( $params['product']['id'] ) ? (int) $params['product']['id'] : 0;

		if ( ! $product_id ) {
			return $params;
		}

		$rule = \TierPricingTable\PriceManager::getPricingRule( $product_id );

		if ( $rule && method_exists( $rule, 'getRules' ) ) {
			$params['tiered_get_rules'] = (array) $rule->getRules();
		}

		return $params;
	}


	/**
	 * Remove the plugin's own price wrapper and tooltip.
	 *
	 * Both are printed around `woocommerce_get_price_html`, where the price
	 * group also lives, and wrapping the group in the plugin's markup breaks
	 * its layout. 5.x removed them for the same reason.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function remove_price_markup() {
		if ( ! class_exists('TierPricingTable\\Core\\ServiceContainer') || ! class_exists('TierPricingTable\\Services\\ProductPageService') ) {
			return;
		}

		try {
			$container = \TierPricingTable\Core\ServiceContainer::getInstance();
			$service = ( $container && method_exists( $container, 'get' ) )
				? $container->get( \TierPricingTable\Services\ProductPageService::class )
				: null;
		} catch ( \Throwable $e ) {
			// The service is the other plugin's internals; a version that
			// reorganised them leaves its markup in place rather than breaking.
			return;
		}

		if ( ! $service ) {
			return;
		}

		remove_filter( 'woocommerce_get_price_html', array( $service, 'wrapPrice' ), 101 );
		remove_filter( 'woocommerce_get_price_html', array( $service, 'renderTooltip' ), 999 );
	}
}
