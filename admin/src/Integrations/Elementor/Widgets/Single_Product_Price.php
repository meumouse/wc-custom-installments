<?php

namespace MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets;

defined('ABSPATH') || exit;

/**
 * The full price group: price, instalment, discount and badges.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets
 * @author MeuMouse.com
 */
class Single_Product_Price extends Abstract_Widget {

	/**
	 * The widget name.
	 *
	 * Unchanged from 5.x: this string is stored inside every page using the
	 * widget, and renaming it would delete the widget from those pages.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_name() {
		return 'wci_single_product_price';
	}


	/**
	 * The widget title shown in the panel.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Preço do produto com parcelas', 'wc-custom-installments' );
	}


	/**
	 * The widget icon.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-product-price';
	}


	/**
	 * Register the widget controls.
	 *
	 * The same sections the plugin adds to other product widgets, under the
	 * unprefixed IDs this widget stored in 5.x.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	protected function register_controls() {
		Style_Controls::price_group( $this );
	}


	/**
	 * Render the widget.
	 *
	 * The whole `single-product/price.php` template, as in 5.x: the price
	 * container and, after it, the list of each variation's price that the
	 * storefront script swaps in when a variation is chosen. Rendering the
	 * price group alone would leave that script nothing to swap.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	protected function render() {
		$product = $this->resolve_product();

		if ( ! $product ) {
			$this->render_empty_state();

			return;
		}

		// A theme's copy of the template may read the global instead of the
		// argument, as WooCommerce's own does, and in the editor that global
		// is empty.
		$previous = $GLOBALS['product'] ?? null;
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored right below.

		wc_get_template( 'single-product/price.php', array(
			'product' => $product,
		) );

		$GLOBALS['product'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the value saved above.
	}
}
