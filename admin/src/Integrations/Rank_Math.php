<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Conflict_Guard;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Values;

defined('ABSPATH') || exit;

/**
 * Rank Math, which prints the product's structured data itself.
 *
 * With Rank Math active, WooCommerce's own JSON-LD is replaced by Rank Math's,
 * so the discounted price has to be written into Rank Math's graph for search
 * results to quote the price the storefront shows. This follows the
 * "discounted price in structured data" setting, which is a Pro capability;
 * `Repository::is_active()` answers false while that feature is locked.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Rank_Math {

	/**
	 * Register the hook, when Rank Math is here.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! self::is_active() ) {
			return;
		}

		add_filter( 'rank_math/json_ld', array( $this, 'filter_json_ld' ), 99, 2 );
	}


	/**
	 * Whether Rank Math is active.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_active() {
		return class_exists('RankMath');
	}


	/**
	 * Put the discounted price into the product's offer.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $data | Rank Math's JSON-LD entities
	 * @param object $jsonld | Rank Math's JSON-LD builder
	 * @return array<string,mixed>
	 */
	public function filter_json_ld( $data, $jsonld = null ) {
		if ( ! is_array( $data ) || ! $this->should_filter() ) {
			return $data;
		}

		$product = $this->get_product( $jsonld );

		if ( ! $product instanceof \WC_Product ) {
			return $data;
		}

		$price = (float) Calculate_Values::get_discounted_price( $product, 'main' );

		if ( $price <= 0 ) {
			return $data;
		}

		$price = wc_format_decimal( $price, wc_get_price_decimals() );

		foreach ( $data as $key => $entity ) {
			if ( ! is_array( $entity ) || ! self::is_product_entity( $entity ) || empty( $entity['offers'] ) || ! is_array( $entity['offers'] ) ) {
				continue;
			}

			$data[ $key ]['offers'] = self::apply_price( $entity['offers'], $price );
		}

		return $data;
	}


	/**
	 * Whether the store asked for the discounted price in structured data.
	 *
	 * The 5.x plugin filters the same graph, so while it is still active the
	 * price is left to it rather than discounted twice.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	private function should_filter() {
		if ( ! Repository::is_active('display_discount_price_schema') || ! Repository::is_active('enable_all_discount_options') ) {
			return false;
		}

		return ! Conflict_Guard::has_conflict();
	}


	/**
	 * The product the graph describes.
	 *
	 * @since 6.0.0
	 * @param object|null $jsonld | Rank Math's JSON-LD builder
	 * @return \WC_Product|false
	 */
	private function get_product( $jsonld ) {
		$post_id = ( is_object( $jsonld ) && ! empty( $jsonld->post_id ) ) ? (int) $jsonld->post_id : 0;

		if ( ! $post_id && is_singular('product') ) {
			$post_id = (int) get_queried_object_id();
		}

		if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
			return false;
		}

		return wc_get_product( $post_id );
	}


	/**
	 * Whether an entity of the graph is a product.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $entity | One entity
	 * @return bool
	 */
	private static function is_product_entity( $entity ) {
		$types = isset( $entity['@type'] ) ? (array) $entity['@type'] : array();

		return in_array( 'Product', $types, true );
	}


	/**
	 * Write the price into an offer, or the lowest price of an aggregate offer.
	 *
	 * A list of per-variation offers is left alone: each would need its own
	 * variation's discount, which the list does not identify reliably.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $offers | The entity's offers
	 * @param string $price | Discounted price, formatted
	 * @return array<string,mixed>
	 */
	private static function apply_price( $offers, $price ) {
		if ( isset( $offers['lowPrice'] ) ) {
			$offers['lowPrice'] = $price;
		}

		if ( isset( $offers['price'] ) ) {
			$offers['price'] = $price;
		}

		// The tax-inclusive price specification repeats the price; leaving the
		// old one there would contradict the offer it belongs to.
		if ( isset( $offers['priceSpecification'] ) && is_array( $offers['priceSpecification'] ) ) {
			if ( isset( $offers['priceSpecification']['price'] ) ) {
				$offers['priceSpecification']['price'] = $price;
			} else {
				foreach ( $offers['priceSpecification'] as $index => $specification ) {
					if ( is_array( $specification ) && isset( $specification['price'] ) ) {
						$offers['priceSpecification'][ $index ]['price'] = $price;
					}
				}
			}
		}

		return $offers;
	}
}
