<?php

namespace MeuMouse\WC_Custom_Installments\Admin;

defined('ABSPATH') || exit;

/**
 * Default value of every plugin setting.
 *
 * The keys deliberately match the 5.x option names, so a migrated store keeps
 * its configuration without a translation table. New settings are additive.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin
 * @author MeuMouse.com
 */
class Default_Options {

	/**
	 * Get every default, keyed by setting name.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		$defaults = array(
			// Installments.
			'enable_installments_all_products'  => 'yes',
			'max_qtd_installments'              => '12',
			'max_qtd_installments_without_fee'  => '3',
			'min_value_installments'            => '20',
			'fee_installments_global'           => '2.0',
			'get_type_best_installments'        => 'best_installment_without_fee',
			'hook_display_best_installments'    => 'display_loop_and_single_product',
			'display_installment_type'          => 'popup',
			'display_installments_cart'         => 'yes',

			// Discounts.
			'enable_all_discount_options'         => 'yes',
			'product_price_discount_method'       => 'percentage',
			'discount_main_price'                 => '10',
			'display_discount_price_hook'         => 'display_loop_and_single_product',
			'display_tag_discount_price_checkout' => 'yes',
			'include_shipping_value_in_discounts' => 'yes',

			// Pro-gated settings. They are stored so the screen can show what the
			// feature looks like, and the gate keeps them from taking effect.
			'set_fee_per_installment'         => 'no',
			'custom_fee_installments'         => array(),
			'remove_price_range'              => 'no',
			'update_price_with_quantity'      => 'yes',
			'enable_sale_badge'               => 'no',
			'enable_economy_pix_badge'        => 'no',
			'display_discount_price_schema'   => 'no',
		);

		/**
		 * Filter the plugin's default settings.
		 *
		 * An add-on registering new settings appends its defaults here.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $defaults | Default settings
		 * @return array<string,mixed>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Admin/Default_Options', $defaults );
	}
}
