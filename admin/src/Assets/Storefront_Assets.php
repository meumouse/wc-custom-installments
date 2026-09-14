<?php

namespace MeuMouse\WC_Custom_Installments\Assets;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Core\Scripts;

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
	 * Style handle of the bundled Font Awesome Free.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const FONT_AWESOME_HANDLE = 'wc-custom-installments-font-awesome';


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

		$assets = Scripts::get_entry_assets('src/entries/font-awesome.js');

		if ( empty( $assets['styles'] ) ) {
			return;
		}

		$version = ! empty( $assets['version'] ) ? $assets['version'] : $this->version;

		foreach ( array_values( (array) $assets['styles'] ) as $index => $style_url ) {
			$handle = 0 === $index ? self::FONT_AWESOME_HANDLE : self::FONT_AWESOME_HANDLE . '-' . $index;

			wp_enqueue_style( $handle, $style_url, array(), $version );
		}
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

		$max_installments = (int) Repository::get('max_qtd_installments');
		$fees = array();

		for ( $i = 1; $i <= $max_installments; $i++ ) {
			$fees[ $i ] = Helpers::get_fee( $product, $i );
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
			'fee_per_installment' => Repository::is_active('set_fee_per_installment'),
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
			'discounts' => $this->discount_params( $product_id ),
			'installments' => array(
				'max_installments' => $max_installments,
				'max_installments_no_fee' => (int) Repository::get('max_qtd_installments_without_fee'),
				'min_installment' => (float) Repository::get('min_value_installments'),
				'fee' => Helpers::get_fee( $product ),
				'fees' => $fees,
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
	 * @param int $product_id | Product ID, or 0
	 * @return array<string,mixed>
	 */
	private function discount_params( $product_id ) {
		$discounts = array(
			'pix_discount' => Repository::is_active('enable_all_discount_options') ? (float) Repository::get('discount_main_price') : 0,
			'pix_discount_method' => Repository::get('product_price_discount_method'),
			'slip_bank_discount' => Repository::is_active('enable_ticket_discount_main_price') ? (float) Repository::get('discount_ticket') : 0,
			'slip_bank_method' => Repository::get('discount_method_ticket'),
			'enable_discount_per_unit' => 'no',
			'discount_per_unit_method' => '',
			'unit_discount_amount' => '',
		);

		if ( $product_id && Features::is_available('product_overrides') ) {
			$discounts['enable_discount_per_unit'] = get_post_meta( $product_id, 'enable_discount_per_unit', true );
			$discounts['discount_per_unit_method'] = get_post_meta( $product_id, 'discount_per_unit_method', true );
			$discounts['unit_discount_amount'] = get_post_meta( $product_id, 'unit_discount_amount', true );
		}

		return $discounts;
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
			return array(
				'id' => $product->get_id(),
				'type' => $product->get_type(),
				'regular_price' => (float) $product->get_variation_regular_price( 'min', true ),
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
