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
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets
 * @author MeuMouse.com
 */
abstract class Abstract_Widget extends Widget_Base {

	/**
	 * The categories this widget appears under.
	 *
	 * @since 6.0.0
	 * @return array<int,string>
	 */
	public function get_categories() {
		return array( Elementor::CATEGORY, 'woocommerce-elements-single' );
	}


	/**
	 * Keywords that find this widget in the panel.
	 *
	 * @since 6.0.0
	 * @return array<int,string>
	 */
	public function get_keywords() {
		return array( 'installments', 'price', 'discount', 'pix', 'woocommerce', 'parcelas' );
	}


	/**
	 * Resolve the product this widget renders for.
	 *
	 * @since 6.0.0
	 * @return \WC_Product|false
	 */
	protected function resolve_product() {
		$product = Helpers::get_product_from_request();

		if ( $product ) {
			return $product;
		}

		// In the editor there is no product in the loop, and an empty box reads
		// as a broken plugin rather than as an empty context. The template's
		// own preview product comes first, so the widgets and the storefront
		// runtime price the same product.
		if ( ! Elementor::is_preview() ) {
			return false;
		}

		$preview_id = Elementor::get_preview_product_id();
		$preview = $preview_id ? wc_get_product( $preview_id ) : false;

		return $preview instanceof \WC_Product ? $preview : Helpers::get_first_product();
	}


	/**
	 * Explain, in the editor only, why the storefront will not show a widget.
	 *
	 * Some widgets render only when a setting hands them the element. The
	 * editor still draws them, so this is what keeps a designer from placing
	 * one and wondering why the product page stays empty.
	 *
	 * @since 6.0.0
	 * @param string $message | What the designer should know
	 * @return void
	 */
	protected function render_editor_notice( $message ) {
		if ( ! Elementor::is_preview() ) {
			return;
		}

		printf( '<div class="wci-widget-placeholder">%s</div>', esc_html( $message ) );
	}

	/**
	 * What to show for a widget the licence does not cover.
	 *
	 * Nothing on the storefront: a customer should never read our licensing
	 * state. In the editor it says why the box is empty, because a designer
	 * who sees nothing assumes the widget is broken.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	protected function render_locked_state() {
		if ( ! Helpers::is_builder_preview() ) {
			return;
		}

		printf(
			'<div class="wci-widget-placeholder">%s</div>',
			esc_html__( 'Este widget precisa de um recurso que sua licença não cobre no momento.', 'wc-custom-installments' )
		);
	}


	/**
	 * What to show when there is no product to render for.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	protected function render_empty_state() {
		if ( ! Helpers::is_builder_preview() ) {
			return;
		}

		printf(
			'<div class="wci-widget-placeholder">%s</div>',
			esc_html__( 'Este widget exibe o produto em que é colocado. Adicione-o a um modelo de produto ou crie um produto para visualizá-lo.', 'wc-custom-installments' )
		);
	}
}
