<?php

namespace MeuMouse\WC_Custom_Installments\Engine;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Features;

defined('ABSPATH') || exit;

/**
 * The money. Every price the plugin shows comes out of here.
 *
 * Ported from the 5.x calculation with its behaviour intact, including the
 * rounding: `calculate_final_price()` rounds the instalment before multiplying,
 * which is what an acquirer actually charges and what the previous plugin
 * displayed. Changing it would silently move every total on every store.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Engine
 * @author MeuMouse.com
 */
class Calculate_Values {

	/**
	 * Value of a single instalment, with interest.
	 *
	 * Two formulas, and which one applies is a business decision rather than a
	 * detail: with a per-instalment table the rate is a flat surcharge on the
	 * whole price, because that is how the acquirer quotes it. With one global
	 * rate it is compound interest over the instalment count.
	 *
	 * @since 1.0.0
	 * @param float $value | Base amount
	 * @param float $fee | Interest rate, as a percentage
	 * @param int $installments | Number of instalments
	 * @return float
	 */
	public static function calculate_installment_with_fee( $value, $fee, $installments ) {
		$percentage = floatval( wc_format_decimal( $fee ) ) / 100.00;

		if ( $installments <= 0 ) {
			return 0;
		}

		if ( $percentage <= 0 ) {
			$installment_price = $value / $installments;
		} elseif ( Repository::is_active('set_fee_per_installment') ) {
			$installment_price = ( $value * $percentage + $value ) / $installments;
		} else {
			$denominator = ( ( 1 + $percentage ) ** $installments ) - 1;

			if ( 0.0 === (float) $denominator ) {
				return 0;
			}

			$installment_price = $value * $percentage * ( ( 1 + $percentage ) ** $installments ) / $denominator;
		}

		/**
		 * Filter the instalment price with interest.
		 *
		 * @since 1.0.0
		 * @param float $installment_price | Calculated instalment price
		 * @param float $value | Base amount
		 * @param float $fee | Interest rate
		 * @param int $installments | Number of instalments
		 * @return float
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Installments/With_Fees', $installment_price, $value, $fee, $installments );
	}


	/**
	 * Value of a single instalment, with no interest.
	 *
	 * @since 1.0.0
	 * @param float $value | Base amount
	 * @param int $installments | Number of instalments
	 * @return float
	 */
	public static function calculate_installment_no_fee( $value, $installments ) {
		if ( $installments <= 0 ) {
			return 0;
		}

		$installment_price = $value / $installments;

		/**
		 * Filter the instalment price without interest.
		 *
		 * @since 1.0.0
		 * @param float $installment_price | Calculated instalment price
		 * @param float $value | Base amount
		 * @param int $installments | Number of instalments
		 * @return float
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Installments/Without_Fee', $installment_price, $value, $installments );
	}


	/**
	 * Total paid across every instalment.
	 *
	 * @since 1.0.0
	 * @param float $value | Value of one instalment
	 * @param int $installments_total | Number of instalments
	 * @return float
	 */
	public static function calculate_final_price( $value, $installments_total ) {
		/**
		 * Filter the total paid in instalments.
		 *
		 * @since 1.0.0
		 * @param float $final_price | Total paid
		 * @param float $value | Value of one instalment
		 * @param int $installments_total | Number of instalments
		 * @return float
		 */
		return Hook_Aliases::apply_filters(
			'WC_Custom_Installments/Installments/Final_Price',
			round( $value, 2 ) * $installments_total,
			$value,
			$installments_total
		);
	}


	/**
	 * Take a percentage off an amount.
	 *
	 * @since 1.0.0
	 * @param float $value | Original amount
	 * @param float $discount | Discount, as a percentage
	 * @param object|bool $product | Product object, when there is one
	 * @return float
	 */
	public static function calculate_discounted_price( $value, $discount, $product = false ) {
		$discount = (float) $discount;
		$value = (float) $value;
		$price = $discount ? $value * ( ( 100 - $discount ) / 100 ) : $value;

		/**
		 * Filter the discounted price.
		 *
		 * @since 1.0.0
		 * @param float $price | Discounted price
		 * @param float $value | Original amount
		 * @param float $discount | Discount value
		 * @param object|bool $product | Product object
		 * @return float
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Discounted_Price', $price, $value, $discount, $product );
	}


	/**
	 * Apply a discount of either kind to an amount.
	 *
	 * @since 1.0.0
	 * @param float $price | Original amount
	 * @param string $discount_method | Either `percentage` or `fixed`
	 * @param float $discount_value | Discount value
	 * @return float
	 */
	public static function calculate_price_with_discount( $price, $discount_method, $discount_value ) {
		$discount_value = floatval( $discount_value );

		if ( 'percentage' === $discount_method ) {
			return self::calculate_discounted_price( (float) $price, $discount_value );
		}

		return (float) $price - $discount_value;
	}


	/**
	 * The discounted price of a product, for display.
	 *
	 * @since 1.0.0
	 * @param object $product | Product object
	 * @param string $discount_type | Either `main` or `ticket`
	 * @return float
	 */
	public static function get_discounted_price( $product, $discount_type = 'main' ) {
		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
			return 0;
		}

		if ( $product->is_type('variation') ) {
			$price = $product->get_price();
		} elseif ( $product->is_type('variable') ) {
			$price = $product->get_variation_price( 'min', true );
		} else {
			$price = $product->get_price();
		}

		$product_id = $product->get_id();
		$parent_product_id = $product->get_parent_id() ?: $product_id;

		if ( 'ticket' === $discount_type ) {
			$discount_value = (float) Repository::get('discount_ticket');
			$discount_method = Repository::get('discount_method_ticket');
		} else {
			$discount_value = (float) Repository::get('discount_main_price');
			$discount_method = Repository::get('product_price_discount_method');
		}

		if ( $product_id && $parent_product_id ) {
			if ( 'yes' === get_post_meta( $product_id, '__disable_discount_main_price', true ) ) {
				return $price;
			}

			// A per-product discount overrides the global one, and is a Pro
			// capability: without it the store falls back to the single global
			// discount rather than to no discount at all.
			if ( Features::is_available('product_overrides') ) {
				list( $per_product, $per_product_method, $per_product_value ) = self::get_product_discount( $product_id, $parent_product_id );

				if ( 'yes' === $per_product ) {
					$discount_method = $per_product_method;
					$discount_value = $per_product_value;
				}
			}
		}

		return self::calculate_price_with_discount( $price, $discount_method, $discount_value );
	}


	/**
	 * What the customer saves by paying upfront.
	 *
	 * @since 1.0.0
	 * @param object $product | Product object
	 * @return float
	 */
	public static function get_pix_economy( $product ) {
		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
			return 0;
		}

		if ( $product->is_type('variation') ) {
			$price = $product->get_price();
		} elseif ( $product->is_type('variable') ) {
			$price = $product->get_variation_price( 'min', true );
		} else {
			$price = $product->get_price();
		}

		$custom_price = self::get_discounted_price( $product, 'main' );
		$economy = max( 0, (float) $price - (float) $custom_price );

		/**
		 * Filter the savings amount.
		 *
		 * @since 1.0.0
		 * @param float $economy | Amount saved
		 * @param object $product | Product object
		 * @return float
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Economy_Pix_Price', $economy, $product );
	}


	/**
	 * The discounted line total of one cart item.
	 *
	 * @since 1.0.0
	 * @param object $product | Product object
	 * @param float $quantity | Quantity in the cart
	 * @return float
	 */
	public static function get_discounted_cart_item_price( $product, $quantity ) {
		$product_id = $product->get_id();
		$parent_id = $product->get_parent_id();

		$disabled = 'yes' === get_post_meta( $product_id, '__disable_discount_main_price', true );
		$disabled_in_parent = 'yes' === get_post_meta( $parent_id, '__disable_discount_main_price', true );

		if ( $disabled || $disabled_in_parent ) {
			return $product->get_price() * $quantity;
		}

		$cart_item_total = $product->get_price() * $quantity;

		if ( Features::is_available('product_overrides') ) {
			list( $per_product, $per_product_method, $per_product_value ) = self::get_product_discount( $product_id, $parent_id );

			if ( 'yes' === $per_product ) {
				return self::calculate_price_with_discount( $cart_item_total, $per_product_method, $per_product_value );
			}
		}

		return self::calculate_price_with_discount(
			$cart_item_total,
			Repository::get('product_price_discount_method'),
			Repository::get('discount_main_price')
		);
	}


	/**
	 * The total discount the cart would earn by paying upfront.
	 *
	 * Per-unit product discounts and the global discount are mutually
	 * exclusive: a cart carrying any individual discount does not also receive
	 * the global one, which is the 5.x behaviour and the one store owners have
	 * priced around.
	 *
	 * @since 1.0.0
	 * @param object $cart | Cart object
	 * @param bool $include_shipping | Whether shipping is part of the base
	 * @return float
	 */
	public static function calculate_total_discount( $cart, $include_shipping = false ) {
		$total_discount = 0;
		$total_cart_value = 0;
		$has_individual_discount = false;
		$per_product_available = Features::is_available('product_overrides');

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$quantity = $cart_item['quantity'];
			$parent_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

			if ( 'yes' === get_post_meta( $parent_id, '__disable_discount_main_price', true ) ) {
				continue;
			}

			$product_price = wc_get_price_excluding_tax( $product );
			$product_discount = 0;

			if ( $per_product_available && 'yes' === get_post_meta( $parent_id, 'enable_discount_per_unit', true ) ) {
				$discount_value = get_post_meta( $parent_id, 'unit_discount_amount', true );
				$discount_method = get_post_meta( $parent_id, 'discount_per_unit_method', true );

				if ( ! empty( $discount_value ) && (float) $discount_value > 0 ) {
					$has_individual_discount = true;

					if ( 'percentage' === $discount_method ) {
						$product_discount = ( $product_price * $discount_value / 100 ) * $quantity;
					} elseif ( 'fixed' === $discount_method ) {
						$product_discount = $discount_value * $quantity;
					}

					$total_discount += $product_discount;
				}
			}

			if ( 0 == $product_discount ) {
				$total_cart_value += $product_price * $quantity;
			}
		}

		if ( $include_shipping ) {
			$total_cart_value += $cart->get_shipping_total();
		}

		if ( ! $has_individual_discount ) {
			$global_discount_value = (float) Repository::get('discount_main_price');

			if ( $global_discount_value > 0 ) {
				if ( 'percentage' === Repository::get('product_price_discount_method') ) {
					$total_discount += ( $total_cart_value * $global_discount_value ) / 100;
				} else {
					$total_discount += min( $global_discount_value, $total_cart_value );
				}
			}
		}

		/**
		 * Filter the total discount applied to the cart.
		 *
		 * @since 1.0.0
		 * @param float $total_discount | Total discount
		 * @param object $cart | Cart object
		 * @param bool $include_shipping | Whether shipping was included
		 * @return float
		 */
		return Hook_Aliases::apply_filters(
			'WC_Custom_Installments/Price/Calculate_Total_Discount',
			round( $total_discount, 2 ),
			$cart,
			$include_shipping
		);
	}


	/**
	 * Read the per-product discount, falling back to the parent.
	 *
	 * @since 1.0.0
	 * @param int $product_id | Product ID
	 * @param int $parent_product_id | Parent product ID
	 * @return array{0:string,1:string,2:string}
	 */
	public static function get_product_discount( $product_id, $parent_product_id ) {
		$enabled = get_post_meta( $product_id, 'enable_discount_per_unit', true );
		$method = get_post_meta( $product_id, 'discount_per_unit_method', true );
		$amount = get_post_meta( $product_id, 'unit_discount_amount', true );

		if ( empty( $enabled ) ) {
			$enabled = get_post_meta( $parent_product_id, 'enable_discount_per_unit', true );
			$method = get_post_meta( $parent_product_id, 'discount_per_unit_method', true );
			$amount = get_post_meta( $parent_product_id, 'unit_discount_amount', true );
		}

		return array( $enabled, $method, $amount );
	}
}
