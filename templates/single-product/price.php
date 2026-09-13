<?php

/**
 * Single product price.
 *
 * This template can be overridden by copying it to
 * yourtheme/woocommerce/single-product/price.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package MeuMouse.com
 * @since 6.0.0
 * @version 6.0.0
 */

use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;

defined('ABSPATH') || exit;

if ( ! isset( $product ) ) {
	/**
	 * Filter the product this price is rendered for.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return object
	 */
	$product = Hook_Aliases::apply_filters( 'WC_Custom_Installments/Product/Set_Product', wc_get_product( get_the_ID() ) );
}

// Nothing to price. Rendering an error here would put it on the storefront, so
// the template simply steps aside.
if ( ! $product instanceof WC_Product ) {
	return;
}

/**
 * Fires before the price.
 *
 * @since 6.0.0
 * @param object $product | Product object
 */
Hook_Aliases::do_action( 'WC_Custom_Installments/Product/Before_Price', $product );

/**
 * Filter the classes of the price group wrapper.
 *
 * @since 6.0.0
 * @param string $classes | Space-separated classes
 * @param object $product | Product object
 * @return string
 */
$group_classes = Hook_Aliases::apply_filters( 'WC_Custom_Installments/Price/Group_Classes', '', $product );

?>

<p id="woo-custom-installments-product-price" class="woo-custom-installments-price-container <?php echo esc_attr( $group_classes ); ?> <?php echo esc_attr( apply_filters( 'woocommerce_product_price_class', 'price' ) ); ?>">
	<?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce builds and escapes this. ?>
</p>

<?php
/**
 * Fires after the price.
 *
 * @since 6.0.0
 * @param object $product | Product object
 */
Hook_Aliases::do_action( 'WC_Custom_Installments/Product/After_Price', $product );
