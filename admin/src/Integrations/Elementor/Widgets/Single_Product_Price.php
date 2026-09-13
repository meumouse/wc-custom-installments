<?php

namespace MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets;

use MeuMouse\WC_Custom_Installments\Frontend\Render_Elements;

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
	 * Render the widget.
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

		$renderer = new Render_Elements();

		echo $renderer->display_price_group( $product->get_price_html(), $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}
}
