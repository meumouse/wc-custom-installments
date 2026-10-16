<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;

defined('ABSPATH') || exit;

/**
 * Put the price group on the storefront.
 *
 * WooCommerce gives one filter for the price, `woocommerce_get_price_html`, and
 * everything the plugin shows around the price is assembled into that one
 * return value. The priority is filterable because themes and other plugins
 * hook the same filter, and which one wins is site-specific.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Render_Elements {

	/**
	 * Register the storefront hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! Repository::is_active('enable_installments_all_products') ) {
			return;
		}

		/**
		 * Filter the priority of the price group.
		 *
		 * @since 6.0.0
		 * @param int $priority | Filter priority
		 * @return int
		 */
		$priority = (int) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Priority', 999 );

		add_filter( 'woocommerce_get_price_html', array( $this, 'display_price_group' ), $priority, 2 );
		add_filter( 'WC_Custom_Installments/Price/Group_Classes', array( $this, 'add_group_classes' ), 10, 2 );
		add_action( 'woocommerce_cart_totals_before_order_total', array( __CLASS__, 'display_discount_on_cart' ) );

		$this->register_payment_methods_hook();

		if ( Repository::is_active('remove_price_range') ) {
			add_action( 'WC_Custom_Installments/Product/After_Price', array( $this, 'variation_prices_group' ), 10, 1 );
		}

		if ( Repository::is_active('custom_text_after_price') ) {
			add_filter( 'woocommerce_get_price_html', array( $this, 'custom_product_price' ), 10, 2 );
		}

		if (
			Repository::is_active('enable_functions_discount_per_quantity')
			&& Repository::is_active('message_discount_per_quantity')
			&& 'hook' === Repository::get('discount_per_qtd_message_method')
		) {
			add_action( 'woocommerce_single_product_summary', array( $this, 'display_message_discount_per_quantity' ) );
			add_action( 'woocommerce_after_shop_loop_item_title', array( $this, 'display_message_discount_per_quantity' ) );
		}

		if ( Repository::is_active('center_group_elements_loop') ) {
			add_filter( 'WC_Custom_Installments/Widgets/Align_Price_Group', array( __CLASS__, 'align_center_group_prices' ) );
		}
	}


	/**
	 * Attach the payment methods block wherever the store asked for it.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	private function register_payment_methods_hook() {
		$placement = Repository::get('hook_payment_form_single_product');

		if ( 'before_cart' === $placement ) {
			add_action( 'woocommerce_before_add_to_cart_form', array( __CLASS__, 'display_payment_methods' ) );

			return;
		}

		if ( 'after_cart' === $placement ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( __CLASS__, 'display_payment_methods' ) );

			return;
		}

		// A custom hook is only honoured once it names something. An empty
		// setting used to register an action on the empty string, which fires
		// on every do_action() call in WordPress.
		if ( 'custom' === $placement || 'custom_hook' === $placement ) {
			$hook = trim( (string) Repository::get('set_custom_hook_payment_form') );

			if ( '' !== $hook ) {
				add_action( $hook, array( __CLASS__, 'display_payment_methods' ) );
			}
		}
	}


	/**
	 * Replace the WooCommerce price with the full price group.
	 *
	 * @since 6.0.0
	 * @param string $price | Price HTML WooCommerce built
	 * @param object $product | Product object
	 * @return string
	 */
	public function display_price_group( $price, $product ) {
		if ( empty( $price ) || ! $product instanceof \WC_Product ) {
			return $price;
		}

		// A shortcode reaches this method with price HTML it built itself, so
		// a store hiding its prices has to be checked here and not only
		// through the empty `$price` a hiding plugin leaves behind.
		if ( ! Helpers::is_price_visible( $product ) ) {
			return $price;
		}

		ob_start();

		/**
		 * Fires before the price group.
		 *
		 * @since 6.0.0
		 * @param object $product | Product object
		 */
		Hook_Aliases::do_action( 'WC_Custom_Installments/Price/Before_Group', $product );

		/**
		 * Filter the classes of the price group wrapper.
		 *
		 * @since 6.0.0
		 * @param string $classes | Space-separated classes
		 * @param object $product | Product object
		 * @return string
		 */
		$group_classes = Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Group_Classes', '', $product );

		$components = new Components();

		echo '<div class="woo-custom-installments-group ' . esc_attr( $group_classes ) . '">';

		/**
		 * Fires at the top of the price group.
		 *
		 * @since 6.0.0
		 * @param object $product | Product object
		 */
		Hook_Aliases::do_action( 'WC_Custom_Installments/Price/Prepend_Group', $product );

		echo $this->render_main_price( $product, $components ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.

		/**
		 * Fires after the price itself, inside the group.
		 *
		 * @since 6.0.0
		 * @param object $product | Product object
		 */
		Hook_Aliases::do_action( 'WC_Custom_Installments/Price/Append_Group', $product );

		echo $components->display_best_installments( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo $components->discount_main_price( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo $components->economy_pix_badge( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		echo $components->discount_ticket_badge( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.

		echo '</div>';

		/**
		 * Fires after the price group.
		 *
		 * @since 6.0.0
		 * @param object $product | Product object
		 */
		Hook_Aliases::do_action( 'WC_Custom_Installments/Price/After_Group', $product );

		// Every group after the first one on a product page is a loop item, and
		// the copy differs between the two.
		Calculate_Installments::$count++;

		return ob_get_clean();
	}


	/**
	 * The price itself, in whichever shape the product calls for.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @param Components $components | Component builder
	 * @return string
	 */
	private function render_main_price( $product, $components ) {
		$remove_range = Repository::is_active('remove_price_range');
		$same_price = Helpers::variations_has_same_price( $product );
		$show_sale_badge = Repository::is_active('enable_sale_badge') && Features::is_available('sale_badge');

		$wrapper_class = ( ! $same_price && ! $remove_range ) ? 'has-range-price' : '';

		$html = '<div class="woo-custom-installments-group-main-price ' . esc_attr( $wrapper_class ) . '">';
		$html .= Helpers::render_icon( 'price', 'wci-icon-price' );

		if ( $product->is_type('variable') ) {
			$min_current = $product->get_variation_price( 'min', true );
			$min_regular = $product->get_variation_regular_price( 'min', true );

			if ( ! $same_price ) {
				if ( $remove_range ) {
					$prefix = Repository::get('text_initial_variables');

					if ( '' !== trim( (string) $prefix ) ) {
						$html .= '<span class="woo-custom-installments-starting-from">' . wp_kses_post( $prefix ) . '</span>';
					}

					// The price the cheapest variation had before its sale, so
					// "starting from" reads as a discount the way a simple
					// product on sale does.
					$cheapest_regular = Helpers::cheapest_variation_regular_price( $product );

					if ( $product->is_on_sale() && $cheapest_regular > $min_current ) {
						$html .= '<span class="woo-custom-installments-price original-price has-discount">' . wc_price( $cheapest_regular ) . '</span>';
					}

					$html .= '<span class="woo-custom-installments-price sale-price">' . wc_price( $min_current );
					$html .= $show_sale_badge && $product->is_on_sale() ? $components->sale_badge( $product ) : '';
					$html .= '</span>';
				} else {
					$html .= '<span class="woo-custom-installments-price">' . wc_price( $min_current ) . '</span>';
					$html .= '<span class="woo-custom-installments-range-price-dash">-</span>';
					$html .= '<span class="woo-custom-installments-price">' . wc_price( $product->get_variation_price( 'max', true ) ) . '</span>';
				}
			} else {
				if ( $product->is_on_sale() && $min_current < $min_regular ) {
					$html .= '<span class="woo-custom-installments-price original-price has-discount">' . wc_price( $min_regular ) . '</span>';
				}

				$html .= '<span class="woo-custom-installments-price sale-price">' . wc_price( $min_current ) . '</span>';
			}
		} elseif ( $product->is_on_sale() ) {
			$html .= '<span class="woo-custom-installments-price original-price has-discount">' . wc_price( $product->get_regular_price() ) . '</span>';
			$html .= '<span class="woo-custom-installments-price sale-price">' . wc_price( $product->get_sale_price() );
			$html .= $show_sale_badge ? $components->sale_badge( $product ) : '';
			$html .= '</span>';
		} else {
			$html .= '<span class="woo-custom-installments-price">' . wc_price( $product->get_price() ) . '</span>';
		}

		$html .= '</div>';

		return $html;
	}


	/**
	 * Flag a variable product whose variations differ in price.
	 *
	 * @since 6.0.0
	 * @param string $classes | Current classes
	 * @param object $product | Product object
	 * @return string
	 */
	public function add_group_classes( $classes, $product ) {
		if ( $product instanceof \WC_Product && $product->is_type('variable') && ! Helpers::variations_has_same_price( $product ) ) {
			$classes .= ' variable-range-price';
		}

		return $classes;
	}


	/**
	 * Print the payment methods block.
	 *
	 * @since 6.0.0
	 * @param object|null $product | Product object
	 * @return void
	 */
	public static function display_payment_methods( $product = null ) {
		$display_method = Repository::get('display_installment_type');

		if ( ! in_array( $display_method, array( 'accordion', 'popup', 'table' ), true ) ) {
			return;
		}

		/**
		 * Filter the product the payment methods block is built for.
		 *
		 * @since 6.0.0
		 * @param object|null $product | Product object
		 * @return object|null
		 */
		$product = Hook_Aliases::apply_filters( 'WC_Custom_Installments/Payment_Methods/Set_Product', $product );
		$product = Helpers::get_product_from_request( $product );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		if ( ! Helpers::is_price_visible( $product ) ) {
			return;
		}

		$product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

		if ( 'yes' === get_post_meta( $product_id, '__disable_installments', true ) || ! $product->is_purchasable() ) {
			return;
		}

		/**
		 * Fires before the payment methods block.
		 *
		 * @since 6.0.0
		 * @param object $product | Product object
		 */
		Hook_Aliases::do_action( 'WC_Custom_Installments/Elements/Before_Installments_Container', $product );

		$components = new Components();

		if ( 'accordion' === $display_method ) {
			echo $components->payment_methods_accordion( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		} elseif ( 'popup' === $display_method ) {
			echo $components->payment_methods_modal( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		} else {
			echo $components->render_installments_table( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		}

		/**
		 * Fires after the payment methods block.
		 *
		 * @since 6.0.0
		 * @param object $product | Product object
		 */
		Hook_Aliases::do_action( 'WC_Custom_Installments/Elements/After_Installments_Container', $product );
	}


	/**
	 * Print each variation's price, for the script to swap between.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return void
	 */
	public function variation_prices_group( $product ) {
		if ( ! $product instanceof \WC_Product || ! in_array( $product->get_type(), array( 'variable', 'variable-subscription' ), true ) ) {
			return;
		}

		if ( Helpers::variations_has_same_price( $product ) ) {
			return;
		}

		echo '<ul id="wci-variation-prices">';

		foreach ( $product->get_available_variations() as $variation ) {
			$variation_product = wc_get_product( $variation['variation_id'] );

			if ( ! $variation_product ) {
				continue;
			}

			printf(
				'<li class="wci-variation-item d-none" data-variation-id="%s">%s</li>',
				esc_attr( $variation['variation_id'] ),
				$variation_product->get_price_html() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce builds this.
			);
		}

		echo '</ul>';
	}


	/**
	 * Add a row to the cart totals with what the upfront price would be.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function display_discount_on_cart() {
		if ( ! Repository::is_active('enable_all_discount_options') || ! Repository::is_active('display_installments_cart') ) {
			return;
		}

		if ( ! function_exists('WC') || ! WC()->cart ) {
			return;
		}

		$total = (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_shipping_total();
		$discount = (float) Calculate_Values::calculate_total_discount(
			WC()->cart,
			Repository::is_active('include_shipping_value_in_discounts')
		);

		$row_title = sprintf(
			/* translators: %s: the copy shown after the discounted price, e.g. "with Pix" */
			esc_html__( 'Total %s', 'wc-custom-installments' ),
			Repository::get('text_after_price')
		);

		/**
		 * Filter the title of the cart total row.
		 *
		 * @since 6.0.0
		 * @param string $row_title | Row title
		 * @return string
		 */
		$title = Hook_Aliases::apply_filters( 'WC_Custom_Installments/Cart/Total_Title', $row_title );

		printf(
			'<tr><th>%s</th><td data-title="%s">%s</td></tr>',
			esc_html( $title ),
			esc_attr( $title ),
			wc_price( $total - $discount ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes this.
		);
	}


	/**
	 * Rewrite the price with a fixed discount and a trailing note.
	 *
	 * @since 6.0.0
	 * @param string $price | Price HTML
	 * @param object $product | Product object
	 * @return string
	 */
	public function custom_product_price( $price, $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return $price;
		}

		if ( $product->is_type('simple') ) {
			$base = (float) $product->get_regular_price();
		} elseif ( $product->is_type('variable') ) {
			$base = (float) $product->get_variation_price( 'min', true );
		} else {
			return $price;
		}

		if ( Repository::is_active('add_discount_custom_product_price') ) {
			$discount = (float) Repository::get('discount_value_custom_product_price') / 100;
			$base -= $base * $discount;
		}

		$after = Repository::get('custom_text_after_price_front');
		$suffix = '' !== trim( (string) $after )
			? ' <span class="woo-custom-installments-text-after-price">' . wp_kses_post( $after ) . '</span>'
			: '';

		return wc_price( $base ) . $suffix;
	}


	/**
	 * Centre the price group inside page-builder widgets.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public static function align_center_group_prices() {
		return 'center';
	}


	/**
	 * Print the quantity discount message.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product ID, when the hook supplies one
	 * @return void
	 */
	public function display_message_discount_per_quantity( $product_id = 0 ) {
		$product = $product_id ? wc_get_product( $product_id ) : Helpers::get_product_from_request();

		if ( ! $product instanceof \WC_Product || ! Helpers::is_price_visible( $product ) ) {
			return;
		}

		$components = new Components();

		echo $components->message_for_discount_per_quantity( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}
}
