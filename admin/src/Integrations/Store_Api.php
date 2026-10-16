<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema;
use Automattic\WooCommerce\StoreApi\Schemas\V1\ProductSchema;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;
use MeuMouse\WC_Custom_Installments\Frontend\Components;

defined('ABSPATH') || exit;

/**
 * The instalment plan and the discounts, in the WooCommerce Store API.
 *
 * A headless frontend reads products and the cart from `wc/store/v1`, so that
 * is where the numbers go: under `extensions.wc-custom-installments` of every
 * product and of the cart, next to the prices WooCommerce already returns and
 * in the same shape - amounts as strings in the currency's minor unit, with
 * the currency described alongside.
 *
 * The values are the ones the storefront would print, decided by the same
 * settings and the same per-product opt-outs. Where each one is shown is left
 * to the frontend: the placement settings describe a WordPress theme.
 *
 * Registered whether or not the store runs headless. Nothing reads it on a
 * regular store, and a store halfway through moving to its own frontend
 * needs the data before it switches the storefront off.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Store_Api {

	/**
	 * The key the data is published under, in `extensions`.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const EXTENSION_KEY = 'wc-custom-installments';


	/**
	 * Extend the product and cart responses.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! function_exists('woocommerce_store_api_register_endpoint_data') ) {
			return;
		}

		/**
		 * Filter whether the Store API carries the plugin's data.
		 *
		 * @since 6.0.0
		 * @param bool $enabled | Whether to extend the responses
		 * @return bool
		 */
		if ( ! apply_filters( 'WC_Custom_Installments/Store_Api/Enabled', true ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data( array(
			'endpoint' => ProductSchema::IDENTIFIER,
			'namespace' => self::EXTENSION_KEY,
			'data_callback' => array( $this, 'product_data' ),
			'schema_callback' => array( $this, 'product_schema' ),
			'schema_type' => ARRAY_A,
		) );

		woocommerce_store_api_register_endpoint_data( array(
			'endpoint' => CartSchema::IDENTIFIER,
			'namespace' => self::EXTENSION_KEY,
			'data_callback' => array( $this, 'cart_data' ),
			'schema_callback' => array( $this, 'cart_schema' ),
			'schema_type' => ARRAY_A,
		) );
	}


	/**
	 * The data of one product response.
	 *
	 * @since 6.0.0
	 * @param \WC_Product $product | Product being returned
	 * @return array<string,mixed>
	 */
	public function product_data( $product ) {
		return self::with_currency( self::build_product_data( $product, self::money_formatter() ) );
	}


	/**
	 * The data of the cart response.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public function cart_data() {
		$cart = function_exists('WC') && WC()->cart ? WC()->cart : null;

		return self::with_currency( self::build_cart_data( $cart, self::money_formatter() ) );
	}


	/**
	 * Describe a product, with amounts passed through a formatter.
	 *
	 * Separate from the response callback so it can be exercised without the
	 * Store API: the formatter is the only part of it WooCommerce supplies.
	 *
	 * @since 6.0.0
	 * @param \WC_Product $product | Product object
	 * @param callable $money | Turns an amount into its response value
	 * @return array<string,mixed>
	 */
	public static function build_product_data( $product, $money ) {
		$data = array(
			'installments' => array(),
			'best_installments' => array(
				'without_interest' => null,
				'with_interest' => null,
			),
			'discounts' => array(
				'main' => null,
				'ticket' => null,
			),
			'labels' => array(
				'without_interest' => self::text('text_without_fee_installments'),
				'with_interest' => self::text('text_with_fee_installments'),
			),
		);

		if ( ! $product instanceof \WC_Product || ! Helpers::is_price_visible( $product ) ) {
			return $data;
		}

		if ( self::offers_installments( $product ) ) {
			$format = static function( $installment ) use ( $money ) {
				return self::format_installment( $installment, $money );
			};

			$data['installments'] = array_map( $format, Calculate_Installments::installments_list( array(), null, $product ) );

			// The highlight is built from its own price, as on the storefront:
			// for a variable product whose variations differ, the table starts
			// from the highest price and the highlight from the lowest.
			$highlight = Calculate_Installments::installments_list( array(), Components::highlight_price( $product ), $product );
			$type = Repository::get('get_type_best_installments');

			if ( in_array( $type, array( 'best_installment_without_fee', 'both' ), true ) ) {
				$data['best_installments']['without_interest'] = self::format_installment( Calculate_Installments::pick_best_without_interest( $highlight ), $money );
			}

			if ( in_array( $type, array( 'best_installment_with_fee', 'both' ), true ) ) {
				$data['best_installments']['with_interest'] = self::format_installment( Calculate_Installments::pick_best_with_interest( $highlight, $product ), $money );
			}
		}

		if ( self::offers_main_discount( $product ) ) {
			$data['discounts']['main'] = array(
				'price' => $money( Calculate_Values::get_discounted_price( $product, 'main' ) ),
				'savings' => $money( Calculate_Values::get_pix_economy( $product ) ),
				'text_before' => self::text('text_before_price'),
				'text_after' => self::text('text_after_price'),
			);
		}

		if ( self::offers_ticket_discount( $product ) ) {
			$data['discounts']['ticket'] = array(
				'price' => $money( Calculate_Values::get_discounted_price( $product, 'ticket' ) ),
				'text_before' => self::text('text_before_discount_ticket'),
				'text_after' => self::text('text_after_discount_ticket'),
			);
		}

		/**
		 * Filter the plugin's data in a Store API product response.
		 *
		 * Anything added here should also be added to the schema, through the
		 * `woocommerce_store_api_register_endpoint_data()` of your own
		 * namespace, or clients validating the response will reject it.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $data | Product data
		 * @param \WC_Product $product | Product object
		 * @return array<string,mixed>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Store_Api/Product_Data', $data, $product );
	}


	/**
	 * Describe the cart, with amounts passed through a formatter.
	 *
	 * The upfront total is the one the cart totals row shows on the storefront.
	 *
	 * @since 6.0.0
	 * @param \WC_Cart|null $cart | Cart object
	 * @param callable $money | Turns an amount into its response value
	 * @return array<string,mixed>
	 */
	public static function build_cart_data( $cart, $money ) {
		$data = array(
			'discounts' => array(
				'main' => null,
			),
		);

		if ( $cart && Helpers::is_price_visible() && Repository::is_active('enable_all_discount_options') ) {
			$total = (float) $cart->get_cart_contents_total() + (float) $cart->get_shipping_total();
			$discount = (float) Calculate_Values::calculate_total_discount( $cart, Repository::is_active('include_shipping_value_in_discounts') );

			$data['discounts']['main'] = array(
				'total' => $money( $total - $discount ),
				'discount' => $money( $discount ),
				'text_before' => self::text('text_before_price'),
				'text_after' => self::text('text_after_price'),
			);
		}

		/**
		 * Filter the plugin's data in the Store API cart response.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $data | Cart data
		 * @param \WC_Cart|null $cart | Cart object
		 * @return array<string,mixed>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Store_Api/Cart_Data', $data, $cart );
	}


	/**
	 * Whether a product is sold in instalments.
	 *
	 * @since 6.0.0
	 * @param \WC_Product $product | Product object
	 * @return bool
	 */
	private static function offers_installments( $product ) {
		return Repository::is_active('enable_installments_all_products')
			&& ! Components::is_opted_out( $product, '__disable_installments' );
	}


	/**
	 * Whether a product has a price for paying upfront.
	 *
	 * @since 6.0.0
	 * @param \WC_Product $product | Product object
	 * @return bool
	 */
	private static function offers_main_discount( $product ) {
		return Repository::is_active('enable_all_discount_options')
			&& ! Components::is_opted_out( $product, '__disable_discount_main_price' )
			&& $product->get_price() > 0;
	}


	/**
	 * Whether a product has a price for paying by bank slip.
	 *
	 * @since 6.0.0
	 * @param \WC_Product $product | Product object
	 * @return bool
	 */
	private static function offers_ticket_discount( $product ) {
		return Features::is_available('ticket_discount')
			&& Repository::is_active('enable_ticket_method_payment_form')
			&& Repository::is_active('enable_ticket_discount_main_price')
			&& ! Components::is_opted_out( $product, '__disable_discount_main_price' )
			&& $product->get_price() > 0;
	}


	/**
	 * One instalment, as the response carries it.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed>|null $installment | Instalment from the engine
	 * @param callable $money | Turns an amount into its response value
	 * @return array<string,mixed>|null
	 */
	private static function format_installment( $installment, $money ) {
		if ( ! is_array( $installment ) ) {
			return null;
		}

		return array(
			'count' => (int) $installment['installments_total'],
			'amount' => $money( $installment['installment_price'] ),
			'total' => $money( $installment['final_price'] ),
			'has_interest' => (bool) $installment['interest_fee'],
		);
	}


	/**
	 * A piece of the store's copy.
	 *
	 * Filtered the way the storefront filters it before printing it, since the
	 * frontend is likely to print it as markup too.
	 *
	 * @since 6.0.0
	 * @param string $key | Setting name
	 * @return string
	 */
	private static function text( $key ) {
		return wp_kses_post( trim( (string) Repository::get( $key ) ) );
	}


	/**
	 * The Store API's own money formatter.
	 *
	 * @since 6.0.0
	 * @return callable
	 */
	private static function money_formatter() {
		$formatter = woocommerce_store_api_get_formatter('money');

		return static function( $value ) use ( $formatter ) {
			return $formatter->format( $value );
		};
	}


	/**
	 * Add the currency properties the Store API puts beside every amount.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $data | Response data
	 * @return array<string,mixed>
	 */
	private static function with_currency( $data ) {
		return woocommerce_store_api_get_formatter('currency')->format( $data );
	}


	/**
	 * Describe the product data.
	 *
	 * The descriptions are for developers reading the schema, in English like
	 * the rest of the Store API's, and are not translated.
	 *
	 * @since 6.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public function product_schema() {
		return array_merge( array(
			'installments' => array(
				'description' => 'Every instalment the store offers for the product, from one payment up. Empty when the product is not sold in instalments.',
				'type' => 'array',
				'context' => array( 'view', 'edit' ),
				'readonly' => true,
				'items' => self::installment_schema('An instalment option.'),
			),
			'best_installments' => self::object_schema( 'The instalments the store highlights under the price, as chosen in its settings. Null when that one is not highlighted.', array(
				'without_interest' => self::installment_schema('The largest instalment count with no interest.'),
				'with_interest' => self::installment_schema('The largest instalment count, with interest when there is any.'),
			) ),
			'discounts' => self::object_schema( 'The prices for paying upfront. Null when the store or the product does not offer that discount.', array(
				'main' => self::object_schema( 'The upfront price, usually paid by Pix.', array(
					'price' => self::money_schema('The discounted price.'),
					'savings' => self::money_schema('How much paying upfront saves.'),
					'text_before' => self::text_schema('The store\'s copy before the price. May contain inline HTML.'),
					'text_after' => self::text_schema('The store\'s copy after the price. May contain inline HTML.'),
				), true ),
				'ticket' => self::object_schema( 'The price for paying by bank slip.', array(
					'price' => self::money_schema('The discounted price.'),
					'text_before' => self::text_schema('The store\'s copy before the price. May contain inline HTML.'),
					'text_after' => self::text_schema('The store\'s copy after the price. May contain inline HTML.'),
				), true ),
			) ),
			'labels' => self::object_schema( 'The store\'s copy for an instalment.', array(
				'without_interest' => self::text_schema('Shown after an instalment with no interest.'),
				'with_interest' => self::text_schema('Shown after an instalment with interest.'),
			) ),
		), self::currency_schema() );
	}


	/**
	 * Describe the cart data.
	 *
	 * @since 6.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public function cart_schema() {
		return array_merge( array(
			'discounts' => self::object_schema( 'The cart totals for paying upfront. Null when the store does not offer that discount.', array(
				'main' => self::object_schema( 'The order paid upfront, usually by Pix.', array(
					'total' => self::money_schema('The order total with the discount.'),
					'discount' => self::money_schema('The discount.'),
					'text_before' => self::text_schema('The store\'s copy before the price. May contain inline HTML.'),
					'text_after' => self::text_schema('The store\'s copy after the price. May contain inline HTML.'),
				), true ),
			) ),
		), self::currency_schema() );
	}


	/**
	 * Schema of one instalment.
	 *
	 * @since 6.0.0
	 * @param string $description | What it is
	 * @return array<string,mixed>
	 */
	private static function installment_schema( $description ) {
		return self::object_schema( $description, array(
			'count' => array(
				'description' => 'The number of instalments.',
				'type' => 'integer',
				'context' => array( 'view', 'edit' ),
				'readonly' => true,
			),
			'amount' => self::money_schema('The value of each instalment.'),
			'total' => self::money_schema('The total paid across every instalment.'),
			'has_interest' => array(
				'description' => 'Whether the instalments carry interest.',
				'type' => 'boolean',
				'context' => array( 'view', 'edit' ),
				'readonly' => true,
			),
		), true );
	}


	/**
	 * Schema of an object.
	 *
	 * @since 6.0.0
	 * @param string $description | What it is
	 * @param array<string,array<string,mixed>> $properties | Its properties
	 * @param bool $nullable | Whether it can be null
	 * @return array<string,mixed>
	 */
	private static function object_schema( $description, $properties, $nullable = false ) {
		return array(
			'description' => $description,
			'type' => $nullable ? array( 'object', 'null' ) : 'object',
			'context' => array( 'view', 'edit' ),
			'readonly' => true,
			'properties' => $properties,
		);
	}


	/**
	 * Schema of an amount.
	 *
	 * @since 6.0.0
	 * @param string $description | What it is
	 * @return array<string,mixed>
	 */
	private static function money_schema( $description ) {
		return array(
			'description' => $description . ' In the currency\'s minor unit, like every Store API price.',
			'type' => 'string',
			'context' => array( 'view', 'edit' ),
			'readonly' => true,
		);
	}


	/**
	 * Schema of a piece of copy.
	 *
	 * @since 6.0.0
	 * @param string $description | What it is
	 * @return array<string,mixed>
	 */
	private static function text_schema( $description ) {
		return array(
			'description' => $description,
			'type' => 'string',
			'context' => array( 'view', 'edit' ),
			'readonly' => true,
		);
	}


	/**
	 * Schema of the currency properties.
	 *
	 * @since 6.0.0
	 * @return array<string,array<string,mixed>>
	 */
	private static function currency_schema() {
		$properties = array(
			'currency_code' => array( 'string', 'Currency code, in ISO format.' ),
			'currency_symbol' => array( 'string', 'Currency symbol.' ),
			'currency_minor_unit' => array( 'integer', 'Number of decimal places in the minor unit.' ),
			'currency_decimal_separator' => array( 'string', 'Decimal separator.' ),
			'currency_thousand_separator' => array( 'string', 'Thousand separator.' ),
			'currency_prefix' => array( 'string', 'Price prefix for the currency, to be used when formatting.' ),
			'currency_suffix' => array( 'string', 'Price suffix for the currency, to be used when formatting.' ),
		);

		$schema = array();

		foreach ( $properties as $key => list( $type, $description ) ) {
			$schema[ $key ] = array(
				'description' => $description,
				'type' => $type,
				'context' => array( 'view', 'edit' ),
				'readonly' => true,
			);
		}

		return $schema;
	}
}
