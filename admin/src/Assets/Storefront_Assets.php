<?php

namespace MeuMouse\WC_Custom_Installments\Assets;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Core\Scripts;
use MeuMouse\WC_Custom_Installments\Engine\Product_Config;

defined('ABSPATH') || exit;

/**
 * Enqueue the storefront runtime and hand it what it needs.
 *
 * The runtime carries no framework: it is the price group's behaviour and
 * nothing else, so a product page pays a few kilobytes rather than a bundle.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Assets
 * @author MeuMouse.com
 */
class Storefront_Assets extends Abstract_Assets {

	/**
	 * Script handle of the storefront runtime.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const HANDLE = 'wc-custom-installments-storefront';


	/**
	 * Register the storefront hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_font_awesome' ), 20 );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 2 );
		add_filter( 'woocommerce_available_variation', array( $this, 'add_variation_discount' ), 10, 3 );
	}


	/**
	 * Hand each variation's own discount to the runtime.
	 *
	 * WooCommerce passes this data to the page when the customer picks a
	 * variation. The runtime recalculates the upfront price as the quantity
	 * changes and when it replaces the range, and a variation may carry a
	 * discount of its own; without this it would apply the product's. The rule
	 * is resolved here, the variation's or its parent's, so the script does no
	 * inheritance of its own. A gated discount is left out, and the runtime keeps
	 * the page's.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $data | Variation data
	 * @param object $product | Variable product
	 * @param object $variation | Variation
	 * @return array<string,mixed>
	 */
	public function add_variation_discount( $data, $product, $variation ) {
		if ( ! is_array( $data ) || ! $variation instanceof \WC_Product ) {
			return $data;
		}

		$rule = Product_Config::discount_rule( $variation->get_id(), $variation->get_parent_id() );

		// Without per-product discounts, a variation with no rule of its own
		// answers to what the page already carries.
		if ( null === $rule && ! Features::is_available('product_overrides') ) {
			return $data;
		}

		$data['wcci_discount'] = self::discount_rule_params( $rule );

		return $data;
	}


	/**
	 * A discount rule, in the shape the runtime reads it.
	 *
	 * The 5.x key names, because the runtime and customer snippets read them.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed>|null $rule | Rule from Product_Config::discount_rule()
	 * @return array{enable_discount_per_unit:string,discount_per_unit_method:string,unit_discount_amount:string}
	 */
	private static function discount_rule_params( $rule ) {
		return array(
			'enable_discount_per_unit' => null !== $rule ? 'yes' : 'no',
			'discount_per_unit_method' => null !== $rule ? (string) $rule['method'] : '',
			'unit_discount_amount' => null !== $rule ? (string) $rule['amount'] : '',
		);
	}


	/**
	 * Enqueue the runtime where the price group can appear.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! Repository::is_active('enable_installments_all_products') ) {
			return;
		}

		/**
		 * Filter whether the storefront runtime is loaded on this request.
		 *
		 * The default is generous, because the price group appears anywhere a
		 * theme prints a price: shortcodes, builders, related products. A store
		 * that knows better can narrow it.
		 *
		 * @since 6.0.0
		 * @param bool $should_load | Whether to enqueue
		 * @return bool
		 */
		if ( ! apply_filters( 'WC_Custom_Installments/Assets/Load_Storefront', ! is_admin() ) ) {
			return;
		}

		$assets = Scripts::get_entry_assets('src/entries/storefront.js');

		if ( empty( $assets['script'] ) ) {
			return;
		}

		$version = ! empty( $assets['version'] ) ? $assets['version'] : $this->version;

		foreach ( (array) ( $assets['styles'] ?? array() ) as $index => $style_url ) {
			wp_enqueue_style( self::HANDLE . '-' . $index, $style_url, array(), $version );
		}

		wp_enqueue_script( self::HANDLE, $assets['script'], array(), $version, true );

		$params = $this->frontend_params();

		wp_localize_script( self::HANDLE, 'wcCustomInstallmentsParams', $params );

		// The 5.x name, kept because customer snippets read it directly.
		wp_localize_script( self::HANDLE, 'wci_front_params', $params );
	}


	/**
	 * Enqueue the bundled Font Awesome Free when icons are printed as its classes.
	 *
	 * Font Awesome classes are the default icon format. 5.x loaded them through
	 * a Font Awesome Kit, which fetched them from a CDN; the icons now ship with
	 * the plugin and are served from the site itself, so the storefront makes no
	 * request to an external server.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function enqueue_font_awesome() {
		if ( is_admin() || ! Repository::is_active('enable_installments_all_products') ) {
			return;
		}

		if ( 'class' !== Repository::get('icon_format_elements') ) {
			return;
		}

		/**
		 * Filter whether the plugin loads its bundled Font Awesome Free.
		 *
		 * A theme or plugin that already loads Font Awesome 6 can turn this off
		 * so the icon font is not downloaded twice.
		 *
		 * @since 6.0.0
		 * @param bool $should_load | Whether to enqueue Font Awesome
		 * @return bool
		 */
		if ( ! apply_filters( 'WC_Custom_Installments/Assets/Load_Font_Awesome', true ) ) {
			return;
		}

		$this->enqueue_font_awesome_styles();
	}


	/**
	 * Build what the runtime needs to recalculate prices in the browser.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public function frontend_params() {
		$product = Helpers::get_product_from_request();

		/**
		 * Filter the product the storefront runtime is configured for.
		 *
		 * @since 6.0.0
		 * @param int $product_id | Product ID, or 0
		 * @return int
		 */
		$product_id = (int) Hook_Aliases::apply_filters(
			'WC_Custom_Installments/Assets/Set_Product_Id',
			$product instanceof \WC_Product ? $product->get_id() : 0
		);

		if ( $product_id && ( ! $product instanceof \WC_Product || $product->get_id() !== $product_id ) ) {
			$product = wc_get_product( $product_id );
		}

		// The runtime parameters are printed in the page source, so a store
		// hiding its prices would hand them to the visitor as plain numbers.
		// Dropping the product leaves the runtime with the same parameters it
		// gets on a page that has no product at all.
		if ( $product instanceof \WC_Product && ! Helpers::is_price_visible( $product ) ) {
			$product = null;
			$product_id = 0;
		}

		$plan = Product_Config::installments( $product instanceof \WC_Product ? $product : null );
		$max_installments = $plan['max'];
		$fees = array();
		$fees_fixed = array();

		for ( $i = 1; $i <= $max_installments; $i++ ) {
			$fees[ $i ] = Helpers::get_fee( $product, $i );
			$fees_fixed[ $i ] = Helpers::get_fee_fixed( $product, $i );
		}

		$tiered_pricing_active = class_exists('TierPricingTable\\Plugin') || class_exists('TierPricingTable\\PriceManager');
		$best_installments = Repository::get('text_display_installments_single_product');
		$decimals = wc_get_price_decimals();
		$decimal_separator = wc_get_price_decimal_separator();
		$thousand_separator = wc_get_price_thousand_separator();

		// Keys under their 5.x names sit beside the 6.0 ones - `ajax_url`,
		// `license_valid`, `check_tiered_plugin`, `tiered_get_rules`,
		// `i18n.best_installments_sp` and `currency.format_*` - because customer
		// snippets read `wci_front_params` by those names.
		$params = array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'debug_mode' => $this->debug_mode,
			'dev_mode' => $this->dev_mode,
			'license_valid' => Features::is_available('variable_price_range'),
			'element_triggers' => Repository::get('update_range_price_triggers'),
			'active_price_range' => Helpers::to_toggle( Repository::is_active('remove_price_range') ),
			'update_price_with_quantity' => Helpers::to_toggle( Repository::is_active('update_price_with_quantity') ),
			'fee_per_installment' => $plan['fee_per_installment'],
			'product_variation_with_range' => $product instanceof \WC_Product && ! Helpers::variations_has_same_price( $product ),
			'tiered_pricing_active' => $tiered_pricing_active,
			'check_tiered_plugin' => $tiered_pricing_active,
			'tiered_get_rules' => array(),
			'i18n' => array(
				'without_fee_label' => Repository::get('text_without_fee_installments'),
				'with_fee_label' => Repository::get('text_with_fee_installments'),
				'best_installments' => $best_installments,
				'best_installments_sp' => $best_installments,
			),
			'currency' => array(
				'symbol' => get_woocommerce_currency_symbol(),
				'decimals' => $decimals,
				'decimal_separator' => $decimal_separator,
				'thousand_separator' => $thousand_separator,
				'format' => str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ),
				'format_num_decimals' => $decimals,
				'format_decimal_sep' => $decimal_separator,
				'format_thousand_sep' => $thousand_separator,
			),
			'discounts' => $this->discount_params( $product_id ? $product : false ),
			'installments' => array(
				'max_installments' => $max_installments,
				'max_installments_no_fee' => $plan['without_fee'],
				'min_installment' => $plan['min_value'],
				'fee' => Helpers::get_fee( $product ),
				'fees' => $fees,
				'fees_fixed' => $fees_fixed,
			),
			'product' => $this->product_params( $product ),
		);

		/**
		 * Filter the storefront runtime parameters.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $params | What the runtime receives
		 * @return array<string,mixed>
		 */
		return (array) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Assets/Frontend_Params', $params );
	}


	/**
	 * The discounts the runtime may apply as the price changes.
	 *
	 * A gated discount is reported as absent rather than as zero: zero would
	 * have the runtime recalculate a badge the server never rendered.
	 *
	 * @since 6.0.0
	 * @param object|false $product | Product the page is configured for
	 * @return array<string,mixed>
	 */
	private function discount_params( $product ) {
		$discounts = array(
			'pix_discount' => Repository::is_active('enable_all_discount_options') ? (float) Repository::get('discount_main_price') : 0,
			'pix_discount_method' => Repository::get('product_price_discount_method'),
			'slip_bank_discount' => Repository::is_active('enable_ticket_discount_main_price') ? (float) Repository::get('discount_ticket') : 0,
			'slip_bank_method' => Repository::get('discount_method_ticket'),
		);

		$rule = $product instanceof \WC_Product
			? Product_Config::discount_rule( $product->get_id(), $product->get_parent_id() ?: $product->get_id() )
			: null;

		return array_merge( $discounts, self::discount_rule_params( $rule ) );
	}


	/**
	 * The prices the runtime starts from.
	 *
	 * @since 6.0.0
	 * @param object|false $product | Product object
	 * @return array<string,mixed>
	 */
	private function product_params( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return array(
				'id' => 0,
				'type' => 'simple',
				'regular_price' => 0,
				'sale_price' => 0,
				'current_price' => 0,
			);
		}

		if ( $product->is_type('variable') ) {
			// The same regular price the server struck out beside the lowest
			// one, so a quantity change repaints the pair it printed.
			$regular = Helpers::cheapest_variation_regular_price( $product );

			return array(
				'id' => $product->get_id(),
				'type' => $product->get_type(),
				'regular_price' => $regular > 0 ? $regular : (float) $product->get_variation_regular_price( 'min', true ),
				'sale_price' => (float) $product->get_variation_sale_price( 'min', true ),
				'current_price' => (float) $product->get_variation_price( 'min', true ),
			);
		}

		return array(
			'id' => $product->get_id(),
			'type' => $product->get_type(),
			'regular_price' => (float) $product->get_regular_price(),
			'sale_price' => (float) $product->get_sale_price(),
			'current_price' => (float) $product->get_price(),
		);
	}


	/**
	 * Mark the runtime as an ES module.
	 *
	 * Injected into this handle's own tag rather than rebuilt, so the inline
	 * scripts WordPress prepends - the localized parameters among them - survive.
	 *
	 * @since 6.0.0
	 * @param string $tag | Printed script markup
	 * @param string $handle | Script handle
	 * @return string
	 */
	public function add_module_type_attribute( $tag, $handle ) {
		if ( self::HANDLE !== $handle ) {
			return $tag;
		}

		$tag = is_scalar( $tag ) ? (string) $tag : '';
		$pattern = '#<script\b(?![^>]*\stype=)([^>]*\sid=(["\'])' . preg_quote( $handle, '#' ) . '-js\2[^>]*)>#';
		$result = preg_replace( $pattern, '<script type="module"$1>', $tag, 1 );

		return null === $result ? $tag : $result;
	}
}
