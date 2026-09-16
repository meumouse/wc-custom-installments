<?php

namespace MeuMouse\WC_Custom_Installments\Engine;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;

defined('ABSPATH') || exit;

/**
 * What a product answers to: its discount and its instalment plan.
 *
 * Every consumer asks here — the server-rendered price, the Store API, the
 * storefront runtime's parameters and the Pro add-on's checkout fees — so a
 * rule decided in one place reaches all of them. Before this, several of them
 * read the product's meta on their own, and a rule added to one path was
 * silently missing from the others.
 *
 * Both answers are filterable, and that is how a rule from outside the product
 * (a campaign) joins in.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Engine
 * @author MeuMouse.com
 */
class Product_Config {

	/**
	 * The discount of its own a product answers to, above the store's.
	 *
	 * The product's own discount comes first: a variation with a setting of its
	 * own decides for itself, one without follows its parent
	 * (Calculate_Values::get_discount_source_id()), and it needs the
	 * `product_overrides` feature. Null means the store's discount applies.
	 *
	 * A rule with an amount of zero is still a rule: the product asked for no
	 * discount of its own, which is not the store's.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product or variation ID
	 * @param int $parent_id | Parent product ID, 0 or the product's own
	 * @return array{method:string,amount:string,gateways:array<int,string>,source:string,source_id:int}|null
	 */
	public static function discount_rule( $product_id, $parent_id = 0 ) {
		$product_id = (int) $product_id;
		$parent_id = (int) $parent_id ?: $product_id;
		$rule = null;

		if ( $product_id && Features::is_available('product_overrides') ) {
			$source = Calculate_Values::get_discount_source_id( $product_id, $parent_id );

			if ( 'yes' === get_post_meta( $source, 'enable_discount_per_unit', true ) ) {
				$gateway = (string) get_post_meta( $source, 'discount_gateway', true );

				$rule = array(
					'method' => (string) get_post_meta( $source, 'discount_per_unit_method', true ),
					'amount' => (string) get_post_meta( $source, 'unit_discount_amount', true ),
					'gateways' => '' !== $gateway ? array( $gateway ) : array(),
					'source' => 'product',
					'source_id' => $source,
				);
			}
		}

		/**
		 * Filter the discount of its own a product answers to.
		 *
		 * Null means the store's discount applies. A rule carries `method`
		 * (`percentage` or `fixed`), `amount`, `gateways` (the payment methods
		 * it is charged for at checkout, or none), `source` and `source_id`.
		 * Leave a rule from the product alone: the product's own setting wins.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed>|null $rule | Discount rule, or null
		 * @param int $product_id | Product or variation ID
		 * @param int $parent_id | Parent product ID, or the product's own
		 * @return array<string,mixed>|null
		 */
		$rule = apply_filters( 'WC_Custom_Installments/Product/Discount_Rule', $rule, $product_id, $parent_id );

		if ( ! is_array( $rule ) ) {
			return null;
		}

		return array(
			'method' => (string) ( $rule['method'] ?? '' ),
			'amount' => (string) ( $rule['amount'] ?? '' ),
			'gateways' => array_values( array_filter( array_map( 'strval', (array) ( $rule['gateways'] ?? array() ) ) ) ),
			'source' => (string) ( $rule['source'] ?? '' ),
			'source_id' => (int) ( $rule['source_id'] ?? 0 ),
		);
	}


	/**
	 * The instalment plan a product answers to.
	 *
	 * The store's settings, unless something narrower says otherwise for this
	 * product. `fees` is the per-instalment table, keyed by instalment count, and
	 * is only read when `fee_per_installment` is on; otherwise `fee` compounds
	 * over every instalment past the interest-free ones.
	 *
	 * @since 6.0.0
	 * @param object|null $product | Product object, when there is one
	 * @return array{max:int,without_fee:int,min_value:float,fee:float,fee_per_installment:bool,fees:array<int,array<string,string>>}
	 */
	public static function installments( $product = null ) {
		$fee_per_installment = Repository::is_active('set_fee_per_installment');

		$config = array(
			'max' => (int) Repository::get('max_qtd_installments'),
			'without_fee' => (int) Repository::get('max_qtd_installments_without_fee'),
			'min_value' => (float) Repository::get('min_value_installments'),
			'fee' => (float) Repository::get('fee_installments_global'),
			'fee_per_installment' => $fee_per_installment,
			'fees' => $fee_per_installment ? (array) Repository::get( 'custom_fee_installments', array() ) : array(),
		);

		/**
		 * Filter the instalment plan a product answers to.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $config | Plan settings
		 * @param object|null $product | Product object, when there is one
		 * @return array<string,mixed>
		 */
		$config = (array) apply_filters( 'WC_Custom_Installments/Product/Installments_Config', $config, $product );

		return array(
			'max' => max( 0, (int) ( $config['max'] ?? 0 ) ),
			'without_fee' => max( 0, (int) ( $config['without_fee'] ?? 0 ) ),
			'min_value' => max( 0, (float) ( $config['min_value'] ?? 0 ) ),
			'fee' => max( 0, (float) ( $config['fee'] ?? 0 ) ),
			'fee_per_installment' => (bool) ( $config['fee_per_installment'] ?? false ),
			'fees' => (array) ( $config['fees'] ?? array() ),
		);
	}


	/**
	 * The interest rate of one instalment count, under a plan.
	 *
	 * With a per-instalment table, a count with no row charges nothing — the
	 * 5.x behaviour, which a store's totals are priced around.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $config | Plan from installments()
	 * @param int $installments | Number of instalments
	 * @return float
	 */
	public static function fee_rate( $config, $installments ) {
		if ( ! empty( $config['fee_per_installment'] ) ) {
			return max( 0, floatval( $config['fees'][ $installments ]['amount'] ?? 0 ) );
		}

		return (float) ( $config['fee'] ?? 0 );
	}


	/**
	 * The fixed amount one instalment count adds to the total, under a plan.
	 *
	 * Only a per-instalment table carries one ("R$ 2,99 + 3%", as some
	 * acquirers quote it); the single compounding rate has none.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $config | Plan from installments()
	 * @param int $installments | Number of instalments
	 * @return float
	 */
	public static function fee_fixed( $config, $installments ) {
		if ( empty( $config['fee_per_installment'] ) ) {
			return 0.0;
		}

		return max( 0, floatval( $config['fees'][ $installments ]['fixed'] ?? 0 ) );
	}
}
