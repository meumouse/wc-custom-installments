<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * The places of the store the price group may appear in.
 *
 * The display settings each element carries answer a coarse question — the
 * product page, the listing, both or neither — because that is the question the
 * 5.x settings asked and their values are written into every migrated store.
 * Everything that is not the product page falls into "the listing", which is
 * also where the related products carousel, the search results, the cart and a
 * product block on the home page all end up.
 *
 * This registry is the finer answer: the store picks the places by name, and an
 * element renders where its own setting and this list agree. It is one setting
 * for the whole group, so the parcels and the discounts travel together.
 *
 * Nothing here is gated by itself. With the feature unavailable every place is
 * allowed, so a lapsed licence returns the storefront to what the free plugin
 * shows rather than emptying it.
 *
 * @since 6.1.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Display_Locations {

	/**
	 * Setting holding the places the store picked.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const SETTING = 'display_locations';

	/**
	 * Feature gating the choice.
	 *
	 * @since 6.1.0
	 * @var string
	 */
	const FEATURE = 'display_locations';

	/**
	 * The loop names WooCommerce gives a related, up-sell or cross-sell block.
	 *
	 * @since 6.1.0
	 * @var array<int,string>
	 */
	const SECONDARY_LOOPS = array( 'related', 'up-sells', 'cross-sells' );

	/**
	 * Cached registry for the current request.
	 *
	 * @since 6.1.0
	 * @var array<string,array<string,string>>|null
	 */
	private static $locations = null;


	/**
	 * Get every place the group may appear in.
	 *
	 * @since 6.1.0
	 * @return array<string,array<string,string>>
	 */
	public static function all() {
		if ( null !== self::$locations ) {
			return self::$locations;
		}

		$locations = array(
			'single_product' => array(
				'label' => __( 'Página do produto', 'wc-custom-installments' ),
				'description' => __( 'A página individual de cada produto.', 'wc-custom-installments' ),
			),
			'shop_loop' => array(
				'label' => __( 'Listagem de produtos', 'wc-custom-installments' ),
				'description' => __( 'A loja e as páginas de categoria, tag e marca.', 'wc-custom-installments' ),
			),
			'related_products' => array(
				'label' => __( 'Produtos relacionados', 'wc-custom-installments' ),
				'description' => __( 'Os blocos de relacionados, upsell e cross-sell da página do produto.', 'wc-custom-installments' ),
			),
			'search_results' => array(
				'label' => __( 'Resultados de busca', 'wc-custom-installments' ),
				'description' => __( 'A página que lista o que o cliente procurou.', 'wc-custom-installments' ),
			),
			'cart' => array(
				'label' => __( 'Carrinho', 'wc-custom-installments' ),
				'description' => __( 'A página do carrinho, com a linha do total à vista.', 'wc-custom-installments' ),
			),
			'checkout' => array(
				'label' => __( 'Finalização de compra', 'wc-custom-installments' ),
				'description' => __( 'A página de finalização de compra.', 'wc-custom-installments' ),
			),
			'other' => array(
				'label' => __( 'Outros locais', 'wc-custom-installments' ),
				'description' => __( 'Widgets, blocos, shortcodes e qualquer outra página da loja.', 'wc-custom-installments' ),
			),
		);

		/**
		 * Filter the places the price group may appear in.
		 *
		 * A place added here is offered in the settings and answered by
		 * `Current_Display_Location`, which is what decides whether a request
		 * is in it.
		 *
		 * @since 6.1.0
		 * @param array<string,array<string,string>> $locations | Places, keyed by id
		 * @return array<string,array<string,string>>
		 */
		self::$locations = (array) apply_filters( 'WC_Custom_Installments/Frontend/Display_Locations', $locations );

		return self::$locations;
	}


	/**
	 * The id of every registered place.
	 *
	 * @since 6.1.0
	 * @return array<int,string>
	 */
	public static function ids() {
		return array_keys( self::all() );
	}


	/**
	 * Forget the cached registry.
	 *
	 * @since 6.1.0
	 * @return void
	 */
	public static function flush() {
		self::$locations = null;
	}


	/**
	 * The places the store picked.
	 *
	 * A stored value that is not a list is read as "every place": that is what
	 * a fresh install, a 5.x import and a store that never opened the card all
	 * look like, and none of them asked for a narrower storefront.
	 *
	 * @since 6.1.0
	 * @return array<int,string>
	 */
	public static function enabled() {
		$stored = Repository::get( self::SETTING );

		if ( ! is_array( $stored ) ) {
			return self::ids();
		}

		return array_values( array_intersect( self::ids(), array_map( 'strval', $stored ) ) );
	}


	/**
	 * Which place the current request is in.
	 *
	 * The page the customer is on answers first: on the cart and the checkout,
	 * a cross-sell block follows that page's choice rather than the carousels'.
	 * An empty string means the question has no answer here — an editor, an
	 * AJAX call, the Store API — and nothing is narrowed on a guess.
	 *
	 * @since 6.1.0
	 * @return string
	 */
	public static function current() {
		$location = '';

		if ( ! is_admin() && ! Helpers::is_builder_preview() ) {
			$location = self::detect();
		}

		/**
		 * Filter the place the current request is in.
		 *
		 * @since 6.1.0
		 * @param string $location | Place id, or an empty string when unknown
		 * @return string
		 */
		return (string) apply_filters( 'WC_Custom_Installments/Frontend/Current_Display_Location', $location );
	}


	/**
	 * Whether the group may render in a place.
	 *
	 * @since 6.1.0
	 * @param string|null $location | Place id, or null for the current request
	 * @return bool
	 */
	public static function is_enabled( $location = null ) {
		if ( ! Features::is_available( self::FEATURE ) ) {
			return true;
		}

		$location = ( null === $location ) ? self::current() : (string) $location;

		if ( '' === $location ) {
			return true;
		}

		return in_array( $location, self::enabled(), true );
	}


	/**
	 * Read the place off the WooCommerce conditionals.
	 *
	 * @since 6.1.0
	 * @return string
	 */
	private static function detect() {
		if ( function_exists('is_cart') && is_cart() ) {
			return 'cart';
		}

		if ( function_exists('is_checkout') && is_checkout() ) {
			return 'checkout';
		}

		if ( self::in_secondary_loop() ) {
			return 'related_products';
		}

		if ( is_search() ) {
			return 'search_results';
		}

		if ( function_exists('is_product') && is_product() ) {
			return 'single_product';
		}

		if ( function_exists('is_shop') && ( is_shop() || is_product_taxonomy() ) ) {
			return 'shop_loop';
		}

		// Anything else the customer is looking at: a widget in a sidebar, a
		// product block on the home page, a shortcode in a landing page. Only
		// a page that actually reached the template counts, so an AJAX call or
		// a REST request — the way a block-built listing fetches its products —
		// is left unanswered instead of being read as somewhere else.
		return did_action('template_redirect') ? 'other' : '';
	}


	/**
	 * Whether a related, up-sell or cross-sell block is being built.
	 *
	 * @since 6.1.0
	 * @return bool
	 */
	private static function in_secondary_loop() {
		if ( ! function_exists('wc_get_loop_prop') ) {
			return false;
		}

		return in_array( (string) wc_get_loop_prop('name'), self::SECONDARY_LOOPS, true );
	}
}
