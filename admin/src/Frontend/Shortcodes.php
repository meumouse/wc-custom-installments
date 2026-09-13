<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;

defined('ABSPATH') || exit;

/**
 * Every element of the price group, as a shortcode.
 *
 * The 5.x plugin gated all fifteen behind a licence. They are free here: a
 * shortcode is how a store owner puts an element somewhere the automatic
 * placement cannot reach, and charging for that is charging for the plugin
 * working in their theme.
 *
 * The 5.x tags are registered unchanged. A shortcode lives inside post content,
 * where nothing can migrate it, so renaming one would blank it on every page
 * that uses it.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Shortcodes {

	/**
	 * Shortcode tag suffix to the method that renders it.
	 *
	 * @since 6.0.0
	 * @var array<string,string>
	 */
	const TAGS = array(
		'_group' => 'render_group',
		'_modal' => 'render_payment_methods',
		'_card_info' => 'render_best_installments',
		'_table_installments' => 'render_installments_table',
		'_pix_container' => 'render_pix_flag',
		'_ticket_container' => 'render_ticket_flag',
		'_credit_card_container' => 'render_credit_card_flags',
		'_debit_card_container' => 'render_debit_card_flags',
		'_pix_info' => 'render_discount_main_price',
		'_ticket_discount_badge' => 'render_discount_ticket_badge',
		'_economy_pix_badge' => 'render_economy_pix_badge',
		'_sale_badge' => 'render_sale_badge',
		'_get_price_on_pix' => 'render_price_on_pix',
		'_get_price_on_ticket' => 'render_price_on_ticket',
		'_get_economy_pix_price' => 'render_economy_pix_price',
	);


	/**
	 * Register every shortcode, under both prefixes.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		foreach ( self::TAGS as $suffix => $method ) {
			// The 5.x tag, which is what sits in existing post content.
			add_shortcode( 'woo_custom_installments' . $suffix, array( $this, $method ) );

			// The current tag, for content written from now on.
			add_shortcode( 'wc_custom_installments' . $suffix, array( $this, $method ) );
		}
	}


	/**
	 * The whole price group.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_group( $atts = array() ) {
		$product = $this->product( $atts );

		if ( ! $product ) {
			return $this->no_product();
		}

		$renderer = new Render_Elements();

		return $renderer->display_price_group( $product->get_price_html(), $product );
	}


	/**
	 * The payment methods block.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_payment_methods( $atts = array() ) {
		$product = $this->product( $atts );

		if ( ! $product ) {
			return $this->no_product();
		}

		ob_start();

		Render_Elements::display_payment_methods( $product );

		return ob_get_clean();
	}


	/**
	 * The highlighted instalment.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_best_installments( $atts = array() ) {
		return $this->component( 'display_best_installments', $atts );
	}


	/**
	 * The full instalment table.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_installments_table( $atts = array() ) {
		return $this->component( 'render_installments_table', $atts );
	}


	/**
	 * The Pix row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_pix_flag( $atts = array() ) {
		return $this->component( 'render_pix_flag', $atts );
	}


	/**
	 * The bank slip row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_ticket_flag( $atts = array() ) {
		return $this->component( 'render_ticket_flag', $atts );
	}


	/**
	 * The credit card row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function render_credit_card_flags() {
		$components = new Components();

		return $components->render_credit_card_flags();
	}


	/**
	 * The debit card row of the payment methods block.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public function render_debit_card_flags() {
		$components = new Components();

		return $components->render_debit_card_flags();
	}


	/**
	 * The discounted price badge.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_discount_main_price( $atts = array() ) {
		return $this->component( 'discount_main_price', $atts );
	}


	/**
	 * The bank slip discount badge.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_discount_ticket_badge( $atts = array() ) {
		return $this->component( 'discount_ticket_badge', $atts );
	}


	/**
	 * The savings badge.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_economy_pix_badge( $atts = array() ) {
		return $this->component( 'economy_pix_badge', $atts );
	}


	/**
	 * The sale badge.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_sale_badge( $atts = array() ) {
		return $this->component( 'sale_badge', $atts );
	}


	/**
	 * The discounted price, on its own.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_price_on_pix( $atts = array() ) {
		$product = $this->product( $atts );

		if ( ! $product ) {
			return $this->no_product();
		}

		return wc_price( Calculate_Values::get_discounted_price( $product, 'main' ) );
	}


	/**
	 * The bank slip price, on its own.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_price_on_ticket( $atts = array() ) {
		$product = $this->product( $atts );

		if ( ! $product ) {
			return $this->no_product();
		}

		return wc_price( Calculate_Values::get_discounted_price( $product, 'ticket' ) );
	}


	/**
	 * The savings amount, on its own.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	public function render_economy_pix_price( $atts = array() ) {
		$product = $this->product( $atts );

		if ( ! $product ) {
			return $this->no_product();
		}

		return wc_price( Calculate_Values::get_pix_economy( $product ) );
	}


	/**
	 * Render one component for the resolved product.
	 *
	 * @since 6.0.0
	 * @param string $method | Method on the component builder
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return string
	 */
	private function component( $method, $atts ) {
		$product = $this->product( $atts );

		if ( ! $product ) {
			return $this->no_product();
		}

		$components = new Components();

		return (string) $components->$method( $product );
	}


	/**
	 * Resolve the product a shortcode is about.
	 *
	 * An explicit `product` attribute wins, which is what makes these usable on
	 * a landing page rather than only inside a product template.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $atts | Shortcode attributes
	 * @return \WC_Product|false
	 */
	private function product( $atts ) {
		$atts = shortcode_atts( array( 'product' => 0 ), (array) $atts );
		$product_id = absint( $atts['product'] );

		if ( $product_id ) {
			$product = wc_get_product( $product_id );

			if ( $product instanceof \WC_Product ) {
				return $product;
			}
		}

		return Helpers::get_product_from_request();
	}


	/**
	 * What to show where no product could be resolved.
	 *
	 * Visible to an editor, silent to a shopper: a customer should never read
	 * our diagnostics, and an editor needs to know why the box is empty.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	private function no_product() {
		if ( ! current_user_can('edit_posts') ) {
			return '';
		}

		return sprintf(
			'<span class="wci-shortcode-notice">%s</span>',
			esc_html__( 'Não há produto para exibir o preço aqui. Use este shortcode em um modelo de produto ou informe um: [wc_custom_installments_group product="123"].', 'wc-custom-installments' )
		);
	}
}
