<?php

namespace MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets;

use MeuMouse\WC_Custom_Installments\Frontend\Components;

defined('ABSPATH') || exit;

/**
 * The full instalment table, without the panel or dialog around it.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets
 * @author MeuMouse.com
 */
class Installments_Table extends Abstract_Widget {

	/**
	 * The widget name.
	 *
	 * Unchanged from 5.x, for the reason given on the price widget.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_name() {
		return 'wci_installments_table';
	}

	/**
	 * The widget title shown in the panel.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_title() {
		return esc_html__( 'Tabela de parcelas', 'wc-custom-installments' );
	}

	/**
	 * The widget icon.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-table';
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

		$components = new Components();

		echo $components->render_installments_table( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}
}
