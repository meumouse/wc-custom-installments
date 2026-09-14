<?php

namespace MeuMouse\WC_Custom_Installments\Core;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Engine\Calculate_Installments;
use MeuMouse\WC_Custom_Installments\Frontend\Element_Registry;

defined('ABSPATH') || exit;

/**
 * Shared helpers used across the plugin.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Core
 * @author MeuMouse.com
 */
class Helpers {

	/**
	 * Whether the current request is one of the plugin's admin screens.
	 *
	 * @since 6.0.0
	 * @param string $page | Optional exact page slug to match
	 * @return bool
	 */
	public static function is_plugin_screen( $page = '' ) {
		$current = self::current_admin_page();

		if ( '' === $current ) {
			return false;
		}

		if ( '' !== $page ) {
			return $page === $current;
		}

		return 0 === strpos( $current, WC_CUSTOM_INSTALLMENTS_SLUG );
	}


	/**
	 * Read the `page` query argument of the admin screen being rendered.
	 *
	 * No nonce check applies: this only reports which screen WordPress already
	 * decided to render, and nothing is acted upon.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public static function current_admin_page() {
		if ( ! is_admin() ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; see the docblock.
		if ( ! isset( $_GET['page'] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; see the docblock.
		return sanitize_text_field( wp_unslash( $_GET['page'] ) );
	}


	/**
	 * The capability required to manage the plugin.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filter the capability required to read and change plugin settings.
		 *
		 * @since 6.0.0
		 * @param string $capability | Capability name
		 * @return string
		 */
		return (string) apply_filters( 'WC_Custom_Installments/Admin/Capability', 'manage_woocommerce' );
	}


	/**
	 * Whether the Pro add-on is installed and running.
	 *
	 * Says nothing about the licence — ask Features for that.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_pro_active() {
		return defined('WC_CUSTOM_INSTALLMENTS_PRO_VERSION');
	}


	/**
	 * Whether the 5.x plugin is active alongside this one.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_legacy_plugin_active() {
		return defined('WOO_CUSTOM_INSTALLMENTS_VERSION') || class_exists('MeuMouse\\Woo_Custom_Installments\\Core\\Init');
	}


	/**
	 * Normalise a stored toggle into a boolean.
	 *
	 * Toggles persist as the strings `yes` and `no`, which the 5.x option also
	 * used, so migrated data needs no conversion.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Stored value
	 * @return bool
	 */
	public static function is_enabled( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		return in_array( strtolower( (string) $value ), array( 'yes', '1', 'true', 'on' ), true );
	}


	/**
	 * Convert a boolean into the stored toggle representation.
	 *
	 * @since 6.0.0
	 * @param mixed $value | Value to normalise
	 * @return string
	 */
	public static function to_toggle( $value ) {
		return self::is_enabled( $value ) ? 'yes' : 'no';
	}


	/**
	 * Whether a product is one the plugin should price.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return bool
	 */
	public static function is_product_available( $product ) {
		$is_available = true;

		if ( ! $product instanceof \WC_Product ) {
			$is_available = false;
		} else {
			$price = wc_get_price_to_display( $product );

			if ( ( is_admin() && ! wp_doing_ajax() ) || '' === $price || 0 == $price ) {
				$is_available = false;
			}
		}

		/**
		 * Filter whether a product is available for pricing.
		 *
		 * @since 6.0.0
		 * @param bool $is_available | Whether the product is available
		 * @param object $product | Product object
		 * @return bool
		 */
		return (bool) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Product/Is_Available', $is_available, $product );
	}


	/**
	 * Whether every variation of a product costs the same.
	 *
	 * A variable product whose variations all cost the same has no range to
	 * show, and should render like a simple product.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return bool
	 */
	public static function variations_has_same_price( $product ) {
		if ( ! $product instanceof \WC_Product || ! in_array( $product->get_type(), array( 'variable', 'variable-subscription' ), true ) ) {
			return true;
		}

		$variation_prices = $product->get_variation_prices( true );

		if ( empty( $variation_prices['price'] ) ) {
			return true;
		}

		$first_price = null;

		foreach ( $variation_prices['price'] as $price ) {
			if ( '' === $price || is_null( $price ) ) {
				continue;
			}

			$price = (float) $price;

			if ( null === $first_price ) {
				$first_price = $price;
			} elseif ( $price !== $first_price ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * The regular price of a variable product's cheapest variation.
	 *
	 * Not the lowest regular price: that may belong to another variation, and
	 * striking it out beside the lowest current price would pair two numbers
	 * no single variation has.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return float Zero when the product has no priced variation.
	 */
	public static function cheapest_variation_regular_price( $product ) {
		if ( ! $product instanceof \WC_Product || ! $product->is_type('variable') ) {
			return 0.0;
		}

		$variation_prices = $product->get_variation_prices( true );
		$cheapest = null;
		$regular = 0.0;

		foreach ( (array) ( $variation_prices['price'] ?? array() ) as $variation_id => $price ) {
			if ( '' === $price || is_null( $price ) ) {
				continue;
			}

			if ( null === $cheapest || (float) $price < $cheapest ) {
				$cheapest = (float) $price;
				$regular = (float) ( $variation_prices['regular_price'][ $variation_id ] ?? 0 );
			}
		}

		return $regular;
	}


	/**
	 * Resolve the product the current request is about.
	 *
	 * @since 6.0.0
	 * @param object|bool $product | A product to use instead of resolving one
	 * @return \WC_Product|false
	 */
	public static function get_product_from_request( $product = false ) {
		if ( $product instanceof \WC_Product ) {
			return $product;
		}

		if ( ! function_exists('wc_get_product') ) {
			return false;
		}

		$resolved = wc_get_product();

		if ( $resolved instanceof \WC_Product ) {
			return $resolved;
		}

		/**
		 * Filter the product a price group is rendered for.
		 *
		 * Page builders render outside the loop, where WooCommerce cannot
		 * resolve a product on its own; this is where they supply one.
		 *
		 * @since 6.0.0
		 * @param \WC_Product|false $resolved | The resolved product
		 * @return \WC_Product|false
		 */
		return Hook_Aliases::apply_filters( 'WC_Custom_Installments/Product/Set_Product', false );
	}


	/**
	 * The interest rate applying to one instalment count.
	 *
	 * @since 6.0.0
	 * @param object|bool $product | Product object, when there is one
	 * @param int $installments | Number of instalments
	 * @return float
	 */
	public static function get_fee( $product = false, $installments = 1 ) {
		if ( Repository::is_active('set_fee_per_installment') ) {
			$custom_fee = (array) Repository::get( 'custom_fee_installments', array() );
			$fee = isset( $custom_fee[ $installments ]['amount'] ) ? floatval( $custom_fee[ $installments ]['amount'] ) : 0;
		} else {
			$fee = floatval( Repository::get('fee_installments_global') );
		}

		/**
		 * Filter the interest rate of an instalment count.
		 *
		 * @since 6.0.0
		 * @param float $fee | Interest rate
		 * @param object|bool $product | Product object
		 * @param int $installments | Number of instalments
		 * @return float
		 */
		return (float) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Installments/Get_Fee', $fee, $product, $installments );
	}


	/**
	 * The tokens a copy template can carry, and what they stand for.
	 *
	 * The token names are Portuguese because they are in the wild: store owners
	 * have `{{ parcelas }}` written into their settings, and translating the
	 * tokens would silently blank every customised string.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $values | One instalment
	 * @return array<string,string>
	 */
	public static function strings_to_replace( $values ) {
		/**
		 * Filter the token replacements.
		 *
		 * @since 6.0.0
		 * @param array<string,string> $replacements | Token => replacement
		 * @return array<string,string>
		 */
		return (array) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Strings_To_Replace', array(
			'{{ parcelas }}' => $values['installments_total'],
			'{{ valor }}' => wc_price( $values['installment_price'] ),
			'{{ total }}' => wc_price( $values['final_price'] ),
			'{{ juros }}' => Calculate_Installments::get_fee_info( $values ),
		) );
	}


	/**
	 * Render the icon of one price-group element.
	 *
	 * @since 6.0.0
	 * @param string $element | Element key in the registry
	 * @param string $class | CSS class identifying the icon
	 * @return string
	 */
	public static function render_icon( $element, $class ) {
		$stored = (array) Repository::get( 'elements_design', array() );
		$icon = Element_Registry::icon( $element, $stored );
		$format = Repository::get('icon_format_elements');

		if ( 'class' === $format && '' !== $icon['class'] ) {
			return sprintf( '<i class="%s icon-class %s"></i>', esc_attr( $class ), esc_attr( $icon['class'] ) );
		}

		if ( 'class' !== $format && '' !== $icon['image'] ) {
			return sprintf( '<img class="%s icon-image" src="%s"/>', esc_attr( $class ), esc_url( $icon['image'] ) );
		}

		return '';
	}


	/**
	 * Whether a named theme is the active one.
	 *
	 * @since 6.0.0
	 * @param string $theme_name | Theme name to match
	 * @return bool
	 */
	public static function check_theme_active( $theme_name ) {
		return strtolower( (string) wp_get_theme()->get('Name') ) === strtolower( (string) $theme_name );
	}


	/**
	 * Whether the cart page still uses the `[woocommerce_cart]` shortcode.
	 *
	 * A block-based cart renders nothing the shortcode hooks reach, so this is
	 * what tells the cart integration which world it is in.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function has_shortcode_cart() {
		if ( ! function_exists('wc_get_page_id') ) {
			return false;
		}

		$cart_page = get_post( wc_get_page_id('cart') );

		return $cart_page && has_shortcode( $cart_page->post_content, 'woocommerce_cart' );
	}


	/**
	 * Any published product, for a builder preview to render against.
	 *
	 * An editor has no product in the loop, and a widget with nothing to price
	 * shows the designer an empty box they will read as a broken plugin.
	 *
	 * @since 6.0.0
	 * @return \WC_Product|false
	 */
	public static function get_first_product() {
		if ( ! class_exists('WC_Product_Query') ) {
			return false;
		}

		$query = new \WC_Product_Query( array(
			'limit' => 1,
			'orderby' => 'date',
			'order' => 'DESC',
			'status' => 'publish',
			'return' => 'objects',
		) );

		$products = $query->get_products();

		return ! empty( $products ) ? $products[0] : false;
	}


	/**
	 * Whether a page builder is rendering its editor right now.
	 *
	 * In an editor there is no real product and no real request, so the price
	 * group has to render regardless of the display rules — otherwise the
	 * designer sees an empty box and assumes the plugin is broken.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_builder_preview() {
		if ( class_exists('\Elementor\Plugin') ) {
			$elementor = \Elementor\Plugin::instance();

			if ( isset( $elementor->editor ) && $elementor->editor->is_edit_mode() ) {
				return true;
			}
		}

		/**
		 * Filter whether the current request is a page-builder preview.
		 *
		 * @since 6.0.0
		 * @param bool $is_preview | Whether a builder is previewing
		 * @return bool
		 */
		return (bool) apply_filters( 'WC_Custom_Installments/Frontend/Is_Builder_Preview', false );
	}
}
