<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;

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
 * What needs handling is what the store may want withheld. That plugin can hide
 * prices from visitors who are not signed in, and it hides them by emptying
 * `woocommerce_get_price_html` alone: the price group disappears with it, but
 * the payment methods block, the quantity message, the fifteen shortcodes, the
 * Elementor widgets, the Store API payload and the numbers the storefront
 * runtime prints into the page source would all keep handing back the price the
 * store meant to hide. And a store pricing per customer often does not want a
 * plan or a discount advertised on top of a negotiated price at all.
 *
 * Both are answered through gates the plugin already has, with a setting each.
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
	 * Products already looked up for a rule, per request.
	 *
	 * A variable product asks about every visible variation, and every price
	 * group on an archive asks again about the same products.
	 *
	 * @since 6.0.0
	 * @var array<int,bool>
	 */
	private static $has_rule = array();


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

		add_filter( 'WC_Custom_Installments/Product/Price_Visible', array( $this, 'hide_from_visitors' ), 10, 2 );
		add_filter( 'WC_Custom_Installments/Product/Opted_Out', array( $this, 'opt_out_priced_product' ), 10, 3 );
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
	 * Describe the integration for the settings screen.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public static function describe() {
		return array(
			'id' => 'role_based_pricing',
			'title' => 'Role Based Pricing for Woo',
			'description' => __( 'Preços por função ou por cliente. As parcelas e o desconto à vista já saem do preço que o cliente logado vai pagar; aqui você decide o que o plugin deixa de exibir.', 'wc-custom-installments' ),
			'url' => 'https://wordpress.org/plugins/role-and-customer-based-pricing-for-woocommerce/',
			'active' => self::is_active(),
			'fields' => array(
				array(
					'type' => 'toggle',
					'key' => 'integration_role_pricing_respect_hidden_prices',
					'label' => __( 'Respeitar preços escondidos de visitantes', 'wc-custom-installments' ),
					'description' => __( 'Enquanto o outro plugin estiver escondendo os preços de quem não está logado, nenhum elemento deste plugin devolve esse preço: nem parcelas, nem desconto, nem formas de pagamento, nem os shortcodes, nem a Store API.', 'wc-custom-installments' ),
				),
				array(
					'type' => 'toggle',
					'key' => 'integration_role_pricing_hide_installments',
					'label' => __( 'Não exibir parcelas em produtos com regra', 'wc-custom-installments' ),
					'description' => __( 'Nos produtos que o outro plugin precifica por função ou por cliente, trata o produto como se tivesse as parcelas desativadas no painel do produto.', 'wc-custom-installments' ),
				),
				array(
					'type' => 'toggle',
					'key' => 'integration_role_pricing_hide_discount',
					'label' => __( 'Não exibir o desconto à vista em produtos com regra', 'wc-custom-installments' ),
					'description' => __( 'Mesma coisa para o desconto à vista, útil quando o preço por cliente já é um preço negociado.', 'wc-custom-installments' ),
				),
			),
		);
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

		if ( ! Repository::is_active('integration_role_pricing_respect_hidden_prices') ) {
			return $visible;
		}

		return ! self::hides_prices_from_visitors();
	}


	/**
	 * Treat a product the other plugin prices as opted out.
	 *
	 * The two opt-outs are the ones a store owner sets on a product, so an
	 * element already silent for an opted-out product stays silent here, and
	 * the Store API follows along without a rule of its own.
	 *
	 * @since 6.0.0
	 * @param bool $opted_out | Whether the product is already opted out
	 * @param object $product | Product object
	 * @param string $meta_key | The opt-out being asked about
	 * @return bool
	 */
	public function opt_out_priced_product( $opted_out, $product, $meta_key ) {
		if ( $opted_out || ! $product instanceof \WC_Product || ! self::is_active() ) {
			return $opted_out;
		}

		$settings = array(
			'__disable_installments' => 'integration_role_pricing_hide_installments',
			'__disable_discount_main_price' => 'integration_role_pricing_hide_discount',
		);

		if ( ! isset( $settings[ $meta_key ] ) || ! Repository::is_active( $settings[ $meta_key ] ) ) {
			return $opted_out;
		}

		return self::prices_product( $product );
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
	 * Whether a rule of the other plugin prices this product for this customer.
	 *
	 * That plugin gives a rule to simple products and to variations, never to
	 * the variable parent, so a variable product is priced by a rule as soon as
	 * one of its visible variations is.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return bool
	 */
	public static function prices_product( $product ) {
		if ( ! $product instanceof \WC_Product || ! class_exists('MeowCrew\\RoleAndCustomerBasedPricing\\PricingRulesDispatcher') ) {
			return false;
		}

		$product_id = $product->get_id();

		if ( isset( self::$has_rule[ $product_id ] ) ) {
			return self::$has_rule[ $product_id ];
		}

		$has_rule = self::dispatches_rule( $product_id );

		if ( ! $has_rule && $product->is_type( array( 'variable', 'variable-subscription' ) ) ) {
			foreach ( $product->get_visible_children() as $variation_id ) {
				if ( self::dispatches_rule( $variation_id ) ) {
					$has_rule = true;

					break;
				}
			}
		}

		self::$has_rule[ $product_id ] = $has_rule;

		return $has_rule;
	}


	/**
	 * Whether the other plugin resolves a rule for one product ID.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product or variation ID
	 * @return bool
	 */
	private static function dispatches_rule( $product_id ) {
		try {
			return (bool) \MeowCrew\RoleAndCustomerBasedPricing\PricingRulesDispatcher::dispatchRule( (int) $product_id );
		} catch ( \Throwable $e ) {
			// The dispatcher is the other plugin's internals; a version that
			// reorganised them leaves the product showing its plan rather than
			// breaking the storefront.
			return false;
		}
	}


	/**
	 * Forget which products carry a rule.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function flush() {
		self::$has_rule = array();
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
