<?php

namespace MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
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
	 * Register the widget controls.
	 *
	 * The control IDs are the 5.x ones, which saved pages hold their values
	 * under.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'wci_installments_table_style_section',
			array(
				'label' => esc_html__( 'Estilos da tabela de parcelamento', 'wc-custom-installments' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		Style_Controls::border(
			$this,
			'wci_installments_table_border',
			Style_Controls::scoped( '.woo-custom-installments-table tr', '.woo-custom-installments-table tr th' ),
			esc_html__( 'Borda da tabela', 'wc-custom-installments' )
		);

		Style_Controls::typography(
			$this,
			'wci_installments_table_typography',
			Style_Controls::scoped( '.woo-custom-installments-table th', '.woo-custom-installments-table td' ),
			esc_html__( 'Tipografia da tabela', 'wc-custom-installments' )
		);

		Style_Controls::color( $this, 'wci_installments_table_text_color', array(
			Style_Controls::scoped('.woo-custom-installments-table th') => 'color: {{VALUE}};',
			Style_Controls::scoped('.woo-custom-installments-table td') => 'color: {{VALUE}};',
		) );

		Style_Controls::color(
			$this,
			'wci_installments_table_price_color',
			array( Style_Controls::scoped('.woo-custom-installments-table .amount') => 'color: {{VALUE}};' ),
			esc_html__( 'Cor do preço', 'wc-custom-installments' )
		);

		Style_Controls::padding( $this, 'wci_installments_table_padding', Style_Controls::scoped('.woo-custom-installments-table th'), true );
		Style_Controls::margin( $this, 'wci_installments_table_margin', Style_Controls::scoped('.woo-custom-installments-table') );

		Style_Controls::hide_switch(
			$this,
			'wci_hidden_installments_table',
			esc_html__( 'Ocultar título', 'wc-custom-installments' ),
			Style_Controls::scoped('h4.installments-title')
		);

		$this->end_controls_section();
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
