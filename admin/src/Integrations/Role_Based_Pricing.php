<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

defined('ABSPATH') || exit;

/**
 * Role Based Pricing for Woo, which prices a product per role and per customer.
 *
 * Its prices arrive through WooCommerce: it filters `woocommerce_product_get_price`,
 * the two sale and regular variants, their six variation equivalents and the
 * variation price hash, all at priority 99. The plugin's engine reads prices
 * only through those getters, so the instalment plan and the upfront discount
 * are already built from the price the signed-in customer will be charged -
 * nothing to adjust there.
 *
 * What does need handling is the opposite case: that plugin can hide prices
 * from visitors who are not signed in, and it hides them by emptying
 * `woocommerce_get_price_html` alone. The price group disappears with it, but
 * the payment methods block, the quantity message, the fifteen shortcodes, the
 * Elementor widgets, the Store API payload and the numbers the storefront
 * runtime prints into the page source would all keep handing back the price the
 * store meant to hide. So this integration closes the one gate instead.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Role_Based_Pricing {

	/**
	 * The option holding the store's choice, under the plugin's own prefix.
	 *
	 * Read directly when the plugin's service container cannot answer, so a
	 * release that reorganises its internals still keeps prices hidden.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const HIDE_PRICES_OPTION = 'role_and_customer_based_pricing_hide_prices_for_non_logged_in_users';


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

		add_filter( 'WC_Custom_Installments/Product/Price_Visible', array( $this, 'hide_from_visitors' ), 10, 2 );
	}


	/**
	 * Whether the plugin is active.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_active() {
		return class_exists('MeowCrew\\RoleAndCustomerBasedPricing\\RoleAndCustomerBasedPricingPlugin');
	}


	/**
	 * Show nothing price-derived while the store hides its prices.
	 *
	 * @since 6.0.0
	 * @param bool $visible | Whether prices may be shown
	 * @param object|null $product | Product object, or null
	 * @return bool
	 */
	public function hide_from_visitors( $visible, $product = null ) {
		if ( ! $visible || is_user_logged_in() || ! self::is_active() ) {
			return $visible;
		}

		return ! self::hides_prices_from_visitors();
	}


	/**
	 * Whether the store asked that visitors not see prices.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function hides_prices_from_visitors() {
		$settings = self::settings();

		if ( $settings && method_exists( $settings, 'doHidePricesForNonLoggedInUsers' ) ) {
			return (bool) $settings->doHidePricesForNonLoggedInUsers();
		}

		return 'yes' === get_option( self::HIDE_PRICES_OPTION, 'no' );
	}


	/**
	 * The plugin's settings service.
	 *
	 * @since 6.0.0
	 * @return object|null Null when the container cannot be reached.
	 */
	private static function settings() {
		if ( ! class_exists('MeowCrew\\RoleAndCustomerBasedPricing\\Core\\ServiceContainer') ) {
			return null;
		}

		try {
			$container = \MeowCrew\RoleAndCustomerBasedPricing\Core\ServiceContainer::getInstance();

			return ( $container && method_exists( $container, 'getSettings' ) ) ? $container->getSettings() : null;
		} catch ( \Throwable $e ) {
			// The container is the other plugin's internals; a version that
			// reorganised them falls back to the stored option rather than
			// taking the storefront down.
			return null;
		}
	}
}
