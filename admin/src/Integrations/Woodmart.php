<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Woodmart, which renders prices in places WooCommerce does not.
 *
 * The theme builds its own product cards and its own Elementor layouts, so the
 * price group has to be told which product it is looking at and which widgets
 * carry a price worth decorating.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Woodmart {

	/**
	 * Register the theme hooks, when the theme is here.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'wp_head', array( $this, 'render_styles' ), 30 );
		add_filter( 'WC_Custom_Installments/Elementor/Editing_Single_Product', array( $this, 'check_layout_type' ) );
		add_filter( 'WC_Custom_Installments/Elementor/Inject_Controllers', array( $this, 'inject_controllers' ) );
		add_filter( 'WC_Custom_Installments/Product/Set_Product', array( $this, 'set_product_object' ) );
		add_filter( 'WC_Custom_Installments/Assets/Set_Product_Id', array( $this, 'set_product_preview' ) );
	}


	/**
	 * Whether Woodmart is the active theme.
	 *
	 * Four checks because the theme has been distributed under several names
	 * and a child theme may define only one of them.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_active() {
		return defined('WOODMART_VERSION')
			|| function_exists('woodmart_theme_setup')
			|| function_exists('woodmart_child_enqueue_styles')
			|| Helpers::check_theme_active('Woodmart')
			|| Helpers::check_theme_active('Woodmart Child');
	}


	/**
	 * Correct the theme's price layout.
	 *
	 * The theme repeats the product price in places with no room for the whole
	 * price group: the sticky add-to-cart bar, the grouped-product list, the
	 * previous/next product navigation and the quick-shop panel. There the group
	 * is hidden, or cut down to the price, as 5.x did.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_styles() {
		if ( is_admin() ) {
			return;
		}

		$css = '.woodmart-hover-base .wd-product-header .price .woo-custom-installments-group{display:block;}'
			. '.wd-sticky-btn-content button.wci-open-popup{display:none;}'
			. '.wd-product .price .woo-custom-installments-group-main-price{flex-wrap:wrap;}'
			. '.wd-sticky-btn-cart button.wci-open-popup{display:none;}'
			. '.wd-sticky-btn .woo-custom-installments-group{display:none;}'
			. '.wd-sticky-btn-cart .woo-custom-installments-group{display:flex;align-items:center;}'
			. '.wd-sticky-btn-cart .original-price{font-size:1.125rem;}'
			. '.theme-woodmart .woocommerce-grouped-product-list .woo-custom-installments-group{display:none;}'
			. '.wd-product-nav-desc .woo-custom-installments-group{display:none;}'
			. '.theme-woodmart .quick-shop-wrapper button.wci-open-popup{display:none;}';

		printf( "<style id=\"wc-custom-installments-woodmart\">%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS declared above.
	}


	/**
	 * Report a Woodmart single-product layout as a single-product edit.
	 *
	 * Answers the `Elementor/Editing_Single_Product` filter (5.x name
	 * `Woo_Custom_Installments/Elementor/Editing_Single_Product`), because the
	 * theme builds product pages from layouts of its own that Elementor does
	 * not recognise as a product template.
	 *
	 * @since 6.0.0
	 * @param bool $is_editing | Whether a single product is being edited
	 * @return bool
	 */
	public function check_layout_type( $is_editing = false ) {
		$layouts = 'XTS\\Modules\\Layouts\\Main';

		if ( class_exists( $layouts ) && method_exists( $layouts, 'is_layout_type' ) && $layouts::is_layout_type('single_product') ) {
			return true;
		}

		return (bool) $is_editing;
	}


	/**
	 * Tell the Elementor integration about the theme's own widgets.
	 *
	 * The section ids are the ones 5.x decorated, which are the ones the
	 * theme's widgets actually declare; the controls land after each.
	 *
	 * @since 6.0.0
	 * @param array<string,string> $widgets | Widget name => style section
	 * @return array<string,string>
	 */
	public function inject_controllers( $widgets ) {
		return array_merge( (array) $widgets, array(
			'wd_products' => 'layout_style_section',
			'wd_single_product_price' => 'general_style_section',
			'wd_products_tabs' => 'heading_style_section',
			'wd_products_widget' => 'general_content_section',
			'wd_archive_products' => 'general_style_section',
			'wd_single_product_fbt_products' => 'general_style_section',
		) );
	}


	/**
	 * Supply the product when the theme is previewing a single-product layout.
	 *
	 * @since 6.0.0
	 * @param object|false $product | Product resolved so far
	 * @return object|false
	 */
	public function set_product_object( $product ) {
		if ( $product ) {
			return $product;
		}

		if ( ! Helpers::is_builder_preview() ) {
			return $product;
		}

		return Helpers::get_first_product();
	}


	/**
	 * Supply the product id the storefront runtime should configure for.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product ID resolved so far
	 * @return int
	 */
	public function set_product_preview( $product_id ) {
		if ( $product_id || ! Helpers::is_builder_preview() ) {
			return $product_id;
		}

		$product = Helpers::get_first_product();

		return $product ? $product->get_id() : $product_id;
	}
}
