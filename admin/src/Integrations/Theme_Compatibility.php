<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * The small CSS corrections some themes need.
 *
 * Each of these was a class of its own in the 5.x plugin: a guard, a heredoc of
 * CSS, and a `wp_head` hook, repeated five times. They are data here, so
 * supporting another theme is one array entry and a store can add its own
 * through a filter without waiting for a release.
 *
 * Every rule is scoped to the theme that needs it. A correction that leaked
 * onto a theme it was not written for would be a bug reported as "the plugin
 * broke my layout".
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Theme_Compatibility {

	/**
	 * Register the hook that prints the corrections.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'render_styles' ), 30 );
		add_action( 'init', array( $this, 'remove_theme_actions' ), 99 );
	}


	/**
	 * The themes with a known correction.
	 *
	 * @since 6.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_rules() {
		$rules = array(
			'astra' => array(
				'detect' => static function() {
					return class_exists('Astra_Theme_Options') || defined('CHILD_THEME_ASTRA_CHILD_VERSION');
				},
				// The sticky add-to-cart bar has no room for a dialog trigger,
				// and the dialog it opens sits behind the bar anyway.
				'css' => '.ast-sticky-add-to-cart-action-wrap button.wci-open-popup{display:none;}',
			),
			'ricky' => array(
				'detect' => static function() {
					return function_exists('ideapark_setup_woocommerce');
				},
				// The grid card is too small for the details trigger.
				'css' => '.c-product-grid__item .wci-open-popup,.c-product-grid__item .wci-accordion-header{display:none;}',
			),
			'machic' => array(
				'detect' => static function() {
					return function_exists('machic_get_option');
				},
				// The theme centres the price block; the price group is
				// left-aligned everywhere else and should stay that way.
				'css' => '.theme-machic .single-product-container .product-price .woo-custom-installments-group{justify-items:flex-start;}'
					. '.theme-machic .single-product-container .product-price .price{display:block;}',
			),
		);

		/**
		 * Filter the theme corrections.
		 *
		 * @since 6.0.0
		 * @param array<string,array<string,mixed>> $rules | Theme key => detection and CSS
		 * @return array<string,array<string,mixed>>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Integrations/Theme_Rules', $rules );
	}


	/**
	 * Print the corrections that apply to this site.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_styles() {
		if ( is_admin() ) {
			return;
		}

		$css = '';

		foreach ( self::get_rules() as $rule ) {
			$detect = $rule['detect'] ?? null;

			if ( ! is_callable( $detect ) || ! $detect() ) {
				continue;
			}

			$css .= $rule['css'] ?? '';
		}

		if ( '' === $css ) {
			return;
		}

		printf( "<style id=\"wc-custom-installments-theme-compat\">%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS declared above.
	}


	/**
	 * Stand down theme code that fights the price group.
	 *
	 * Shoptimizer rewrites the sale price HTML on the same hooks the price
	 * group renders on, so both run and the customer sees two prices.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function remove_theme_actions() {
		if ( ! defined('SHOPTIMIZER_VERSION') ) {
			return;
		}

		remove_action( 'woocommerce_before_shop_loop_item_title', 'shoptimizer_change_displayed_sale_price_html', 7 );
		remove_action( 'woocommerce_single_product_summary', 'shoptimizer_change_displayed_sale_price_html', 10 );
	}


	/**
	 * Whether a named theme is active.
	 *
	 * Kept as a helper for the rules a filter may add.
	 *
	 * @since 6.0.0
	 * @param string $name | Theme name
	 * @return bool
	 */
	public static function is_theme( $name ) {
		return Helpers::check_theme_active( $name );
	}
}
