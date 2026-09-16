<?php

namespace MeuMouse\WC_Custom_Installments\Campaigns;

use MeuMouse\WC_Custom_Installments\Core\Features;

defined('ABSPATH') || exit;

/**
 * Apply running campaigns to the products they target.
 *
 * Campaigns join the engine through Product_Config's two filters, so every
 * price the plugin produces - the rendered one, the Store API's, the
 * storefront runtime's and the Pro add-on's checkout fees - sees them at once.
 *
 * Per effect, the first matching campaign in the index's order wins: a
 * discount campaign and an instalment campaign can both apply to a product,
 * two discounts never add up. A product's own discount always wins over a
 * campaign's: it was set on purpose, for that product.
 *
 * Whether a campaign is running is decided when the price is built, not by a
 * scheduled job, so an uncached page is right the second a campaign starts or
 * ends. Scheduler only tells caches.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Campaigns
 * @author MeuMouse.com
 */
class Resolver {

	/**
	 * Campaigns matched per product in this request.
	 *
	 * @since 6.0.0
	 * @var array<string,array<int,array<string,mixed>>>
	 */
	private static $matches = array();

	/**
	 * The index for this request.
	 *
	 * @since 6.0.0
	 * @var array<int,array<string,mixed>>|null
	 */
	private static $index = null;


	/**
	 * Join the engine.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'WC_Custom_Installments/Product/Discount_Rule', array( __CLASS__, 'discount_rule' ), 10, 3 );
		add_filter( 'WC_Custom_Installments/Product/Installments_Config', array( __CLASS__, 'installments_config' ), 10, 2 );
	}


	/**
	 * Give a product the discount of the campaign it falls under.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed>|null $rule | The product's own rule, or null
	 * @param int $product_id | Product or variation ID
	 * @param int $parent_id | Parent product ID, or the product's own
	 * @return array<string,mixed>|null
	 */
	public static function discount_rule( $rule, $product_id, $parent_id ) {
		if ( null !== $rule || ! Features::is_available('campaigns') ) {
			return $rule;
		}

		$campaign = self::first( $product_id, $parent_id, 'discount' );

		if ( null === $campaign ) {
			return $rule;
		}

		return array(
			'method' => $campaign['discount']['method'],
			'amount' => $campaign['discount']['amount'],
			// An index compiled before a discount could name several methods holds one.
			'gateways' => $campaign['discount']['gateways'] ?? (array) ( $campaign['discount']['gateway'] ?? array() ),
			'source' => 'campaign',
			'source_id' => $campaign['id'],
		);
	}


	/**
	 * Give a product the instalment plan of the campaign it falls under.
	 *
	 * What the campaign leaves blank stays the store's. A per-instalment table
	 * needs its own feature, as it does in the store's settings.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $config | Plan settings
	 * @param object|null $product | Product object, when there is one
	 * @return array<string,mixed>
	 */
	public static function installments_config( $config, $product ) {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) || ! Features::is_available('campaigns') ) {
			return $config;
		}

		$campaign = self::first( $product->get_id(), $product->get_parent_id(), 'installments' );

		if ( null === $campaign ) {
			return $config;
		}

		$plan = $campaign['installments'];

		foreach ( array( 'max', 'without_fee' ) as $key ) {
			if ( '' !== (string) $plan[ $key ] ) {
				$config[ $key ] = (int) $plan[ $key ];
			}
		}

		if ( '' !== (string) $plan['min_value'] ) {
			$config['min_value'] = (float) $plan['min_value'];
		}

		if ( 'single' === $plan['interest'] ) {
			$config['fee_per_installment'] = false;
			$config['fee'] = (float) $plan['fee'];
			$config['fees'] = array();
		} elseif ( 'table' === $plan['interest'] && Features::is_available('fee_per_installment') ) {
			$config['fee_per_installment'] = true;
			$config['fees'] = (array) $plan['fees'];
		}

		return $config;
	}


	/**
	 * The campaign whose effect applies to a product, if any.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product or variation ID
	 * @param int $parent_id | Parent product ID, 0 or the product's own
	 * @param string $effect | Either `discount` or `installments`
	 * @return array<string,mixed>|null Compiled campaign
	 */
	public static function first( $product_id, $parent_id, $effect ) {
		foreach ( self::matching( $product_id, $parent_id ) as $campaign ) {
			if ( ! empty( $campaign[ $effect ] ) ) {
				return $campaign;
			}
		}

		return null;
	}


	/**
	 * Every running campaign that targets a product, in the order they apply.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product or variation ID
	 * @param int $parent_id | Parent product ID, 0 or the product's own
	 * @return array<int,array<string,mixed>>
	 */
	public static function matching( $product_id, $parent_id = 0 ) {
		$product_id = (int) $product_id;
		$parent_id = (int) $parent_id ?: $product_id;
		$key = $product_id . ':' . $parent_id;

		if ( isset( self::$matches[ $key ] ) ) {
			return self::$matches[ $key ];
		}

		if ( null === self::$index ) {
			self::$index = Repository::index();
		}

		$now = self::now();
		$found = array();

		foreach ( self::$index as $campaign ) {
			if ( 'running' === Rules::phase( $campaign, $now ) && self::targets( $campaign['target'], $product_id, $parent_id ) ) {
				$found[] = $campaign;
			}
		}

		self::$matches[ $key ] = $found;

		return $found;
	}


	/**
	 * Whether a target reaches a product.
	 *
	 * A variation is judged by its product: categories, tags and brands are
	 * filed on the product, and choosing a product chooses its variations.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $target | Compiled target
	 * @param int $product_id | Product or variation ID
	 * @param int $parent_id | Parent product ID, or the product's own
	 * @return bool
	 */
	public static function targets( $target, $product_id, $parent_id ) {
		$ids = array_unique( array( $product_id, $parent_id ) );

		if ( array_intersect( $ids, (array) $target['exclude_products'] ) ) {
			return false;
		}

		if ( ! empty( $target['exclude_categories'] ) && array_intersect( self::term_ids( $parent_id, 'product_cat' ), $target['exclude_categories'] ) ) {
			return false;
		}

		switch ( $target['scope'] ) {
			case 'all':
				$in_scope = true;
				break;

			case 'products':
				$in_scope = (bool) array_intersect( $ids, (array) $target['ids'] );
				break;

			default:
				$in_scope = '' !== $target['taxonomy'] && (bool) array_intersect( self::term_ids( $parent_id, $target['taxonomy'] ), (array) $target['ids'] );
		}

		if ( ! $in_scope || 'any' === $target['on_sale'] ) {
			return $in_scope;
		}

		$product = wc_get_product( $product_id );
		$on_sale = $product && $product->is_on_sale();

		return 'only' === $target['on_sale'] ? $on_sale : ! $on_sale;
	}


	/**
	 * The moment campaigns are judged against.
	 *
	 * @since 6.0.0
	 * @return int Unix timestamp
	 */
	public static function now() {
		/**
		 * Filter the moment campaigns are judged against.
		 *
		 * Previewing a store as it will look when a campaign starts is one use.
		 *
		 * @since 6.0.0
		 * @param int $now | Unix timestamp
		 * @return int
		 */
		return (int) apply_filters( 'WC_Custom_Installments/Campaigns/Now', time() );
	}


	/**
	 * Forget what this request matched, after the index changes.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public static function flush() {
		self::$matches = array();
		self::$index = null;
	}


	/**
	 * The terms a product is filed under in a taxonomy.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product ID
	 * @param string $taxonomy | Taxonomy
	 * @return array<int,int>
	 */
	private static function term_ids( $product_id, $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		return array_map( 'intval', (array) wc_get_product_term_ids( $product_id, $taxonomy ) );
	}
}
