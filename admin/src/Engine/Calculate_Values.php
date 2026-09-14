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
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Engine
 * @author MeuMouse.com
 */
class Calculate_Values {

	/**
	 * Value of a single instalment, with interest.
	 *
	 * Two formulas, and which one applies is a business decision rather than a
	 * detail: with a per-instalment table the rate is a flat surcharge on the
	 * whole price, because that is how the acquirer quotes it, and the table may
	 * add a fixed amount to the total as well ("R$ 2,99 + 3%"). With one global
	 * rate it is compound interest over the instalment count.
	 *
	 * @since 6.0.0
	 * @param float $value | Base amount
	 * @param float $fee | Interest rate, as a percentage
	 * @param int $installments | Number of instalments
	 * @param bool|null $fee_per_installment | Whether the rate comes from a per-instalment table; the store's setting when null
	 * @param float $fixed | Amount a per-instalment table adds once to the total
	 * @return float
	 */
	public static function calculate_installment_with_fee( $value, $fee, $installments, $fee_per_installment = null, $fixed = 0.0 ) {
		$percentage = floatval( wc_format_decimal( $fee ) ) / 100.00;

		if ( $installments <= 0 ) {
			return 0;
		}

		if ( null === $fee_per_installment ) {
			$fee_per_installment = Repository::is_active('set_fee_per_installment');
		}

		// A fixed amount belongs to the table; the compounding rate has none.
		$fixed = $fee_per_installment ? max( 0, (float) $fixed ) : 0.0;

		if ( $percentage <= 0 && $fixed <= 0 ) {
			$installment_price = $value / $installments;
		} elseif ( $fee_per_installment ) {
			$installment_price = ( $value * $percentage + $value + $fixed ) / $installments;
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
		 * @since 6.0.0
		 * @param float $installment_price | Calculated instalment price
		 * @param float $value | Base amount
		 * @param float $fee | Interest rate
		 * @param int $installments | Number of instalments
		 * @param float $fixed | Fixed amount added to the total (6.0.0)
		 * @return float
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Installments/With_Fees', $installment_price, $value, $fee, $installments, $fixed );
	}


	/**
	 * Value of a single instalment, with no interest.
	 *
	 * @since 6.0.0
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
		 * @since 6.0.0
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
	 * @since 6.0.0
	 * @param float $value | Value of one instalment
	 * @param int $installments_total | Number of instalments
	 * @return float
	 */
	public static function calculate_final_price( $value, $installments_total ) {
		/**
		 * Filter the total paid in instalments.
		 *
		 * @since 6.0.0
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
	 * @since 6.0.0
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
		 * @since 6.0.0
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
	 * @since 6.0.0
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
	 * A variable product shows the lowest upfront price any of its variations
	 * reaches, each with its own discount, since that is the one a customer
	 * could actually pay. See get_lowest_variation_discount().
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @param string $discount_type | Either `main` or `ticket`
	 * @return float
	 */
	public static function get_discounted_price( $product, $discount_type = 'main' ) {
		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
			return 0;
		}

		if ( $product->is_type('variable') ) {
			$lowest = self::get_lowest_variation_discount( $product, $discount_type );

			if ( null !== $lowest ) {
				return $lowest['discounted'];
			}

			$price = $product->get_variation_price( 'min', true );
		} else {
			$price = $product->get_price();
		}

		$product_id = $product->get_id();

		return self::discount_price( $price, $product_id, $product->get_parent_id() ?: $product_id, $discount_type );
	}


	/**
	 * The variation of a variable product with the lowest upfront price.
	 *
	 * Once a variation can carry a discount of its own, the cheapest variation
	 * is not necessarily the one with the lowest upfront price: a pricier one
	 * with a bigger discount can undercut it. Each variation's price is
	 * discounted by the rule it answers to, and the lowest one wins.
	 *
	 * Null when the rules cannot differ between variations: without per-product
	 * discounts every variation takes the store's discount, and with the parent's
	 * discount hidden none takes any. The cheapest price then gives the same
	 * answer, without reading every variation.
	 *
	 * @since 6.0.0
	 * @param object $product | Variable product
	 * @param string $discount_type | Either `main` or `ticket`
	 * @return array{id:int,price:float,discounted:float}|null
	 */
	public static function get_lowest_variation_discount( $product, $discount_type = 'main' ) {
		if ( ! Features::is_available('product_overrides') || ! method_exists( $product, 'get_variation_prices' ) ) {
			return null;
		}

		if ( 'yes' === get_post_meta( $product->get_id(), '__disable_discount_main_price', true ) ) {
			return null;
		}

		$prices = (array) ( $product->get_variation_prices( true )['price'] ?? array() );

		if ( empty( $prices ) ) {
			return null;
		}

		// One query for every variation's meta, rather than one each.
		if ( function_exists('update_meta_cache') ) {
			update_meta_cache( 'post', array_map( 'intval', array_keys( $prices ) ) );
		}

		$lowest = null;

		foreach ( $prices as $variation_id => $price ) {
			$discounted = self::discount_price( (float) $price, (int) $variation_id, $product->get_id(), $discount_type );

			if ( null === $lowest || $discounted < $lowest['discounted'] ) {
				$lowest = array(
					'id' => (int) $variation_id,
					'price' => (float) $price,
					'discounted' => (float) $discounted,
				);
			}
		}

		return $lowest;
	}


	/**
	 * Apply the discount a product answers to on a price.
	 *
	 * @since 6.0.0
	 * @param float $price | Price to discount
	 * @param int $product_id | Product or variation ID
	 * @param int $parent_product_id | Parent product ID, or the product's own
	 * @param string $discount_type | Either `main` or `ticket`
	 * @return float
	 */
	private static function discount_price( $price, $product_id, $parent_product_id, $discount_type ) {
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

			// A discount of the product's own overrides the global one. Without
			// one the store falls back to the single global discount rather than
			// to no discount at all.
			$rule = Product_Config::discount_rule( $product_id, $parent_product_id );

			if ( null !== $rule ) {
				$discount_method = $rule['method'];
				$discount_value = $rule['amount'];
			}
		}

		return self::calculate_price_with_discount( $price, $discount_method, $discount_value );
	}


	/**
	 * What the customer saves by paying upfront.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return float
	 */
	public static function get_pix_economy( $product ) {
		if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'is_type' ) ) {
			return 0;
		}

		// A variable product saves what its lowest upfront price saves, measured
		// against that same variation's price rather than the cheapest one.
		$lowest = $product->is_type('variable') ? self::get_lowest_variation_discount( $product, 'main' ) : null;

		if ( null !== $lowest ) {
			$price = $lowest['price'];
			$custom_price = $lowest['discounted'];
		} else {
			$price = $product->is_type('variable') ? $product->get_variation_price( 'min', true ) : $product->get_price();
			$custom_price = self::get_discounted_price( $product, 'main' );
		}

		$economy = max( 0, (float) $price - (float) $custom_price );

		/**
		 * Filter the savings amount.
		 *
		 * @since 6.0.0
		 * @param float $economy | Amount saved
		 * @param object $product | Product object
		 * @return float
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Economy_Pix_Price', $economy, $product );
	}


	/**
	 * The discounted line total of one cart item.
	 *
	 * @since 6.0.0
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
		$rule = Product_Config::discount_rule( $product_id, $parent_id );

		if ( null !== $rule ) {
			return self::calculate_price_with_discount( $cart_item_total, $rule['method'], $rule['amount'] );
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
	 * @since 6.0.0
	 * @param object $cart | Cart object
	 * @param bool $include_shipping | Whether shipping is part of the base
	 * @return float
	 */
	public static function calculate_total_discount( $cart, $include_shipping = false ) {
		$total_discount = 0;
		$total_cart_value = 0;
		$has_individual_discount = false;

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			$quantity = $cart_item['quantity'];
			$parent_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();

			if ( 'yes' === get_post_meta( $parent_id, '__disable_discount_main_price', true ) ) {
				continue;
			}

			$product_price = wc_get_price_excluding_tax( $product );
			$product_discount = 0;
			$rule = Product_Config::discount_rule( $product->get_id(), $parent_id );

			if ( null !== $rule ) {
				$discount_method = $rule['method'];
				$discount_value = $rule['amount'];
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
		 * @since 6.0.0
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
	 * The product's meta as stored, whatever the licence says. What the engine
	 * applies is Product_Config::discount_rule(), which gates it and lets other
	 * rules join in.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product ID
	 * @param int $parent_product_id | Parent product ID
	 * @return array{0:string,1:string,2:string}
	 */
	public static function get_product_discount( $product_id, $parent_product_id ) {
		$source = self::get_discount_source_id( $product_id, $parent_product_id );

		return array(
			get_post_meta( $source, 'enable_discount_per_unit', true ),
			get_post_meta( $source, 'discount_per_unit_method', true ),
			get_post_meta( $source, 'unit_discount_amount', true ),
		);
	}


	/**
	 * The product whose meta holds the per-product discount that applies.
	 *
	 * A variation with a setting of its own decides for itself: `yes` for a
	 * discount of its own, `no` for the store's. One with no setting follows its
	 * parent, which is what every variation did before a variation could carry
	 * a discount, so a 5.x store sees no change. The discount's details and the
	 * payment method it is tied to are read from the same product, never mixed.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product or variation ID
	 * @param int $parent_product_id | Parent product ID, 0 or the product's own
	 * @return int
	 */
	public static function get_discount_source_id( $product_id, $parent_product_id ) {
		$product_id = (int) $product_id;
		$parent_product_id = (int) $parent_product_id;

		if ( $parent_product_id && $parent_product_id !== $product_id && '' === (string) get_post_meta( $product_id, 'enable_discount_per_unit', true ) ) {
			return $parent_product_id;
		}

		return $product_id;
	}
}
