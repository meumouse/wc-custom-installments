<?php

namespace MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets;

use Elementor\Widget_Base;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Integrations\Elementor;

defined('ABSPATH') || exit;

/**
 * Shared behaviour of the plugin's Elementor widgets.
 *
 * Every one of them renders something for the product currently in the loop,
 * and in the editor there is no loop - so resolving the product, and saying so
 * when there is none, is the same job each time.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets
 * @author MeuMouse.com
 */
abstract class Abstract_Widget extends Widget_Base {

	/**
	 * The categories this widget appears under.
	 *
	 * @since 1.0.0
	 * @return array<int,string>
	 */
	public function get_categories() {
		return array( Elementor::CATEGORY, 'woocommerce-elements-single' );
	}

	/**
	 * Keywords that find this widget in the panel.
	 *
	 * @since 1.0.0
	 * @return array<int,string>
	 */
	public function get_keywords() {
		return array( 'installments', 'price', 'discount', 'pix', 'woocommerce', 'parcelas' );
	}

	/**
	 * Resolve the product this widget renders for.
	 *
	 * @since 1.0.0
	 * @return \WC_Product|false
	 */
	protected function resolve_product() {
		$product = Helpers::get_product_from_request();

		if ( $product ) {
			return $product;
		}

		// In the editor there is no product in the loop, and an empty box reads
		// as a broken plugin rather than as an empty context.
		return Helpers::is_builder_preview() ? Helpers::get_first_product() : false;
	}

	/**
	 * What to show when there is no product to render for.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function render_empty_state() {
		if ( ! Helpers::is_builder_preview() ) {
			return;
		}

		printf(
			'<div class="wci-widget-placeholder">%s</div>',
			esc_html__( 'This widget renders the product it is placed on. Add it to a product template, or create a product to preview it.', 'wc-custom-installments' )
		);
	}
}
