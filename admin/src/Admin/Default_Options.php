<?php

namespace MeuMouse\WC_Custom_Installments\Admin;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Frontend\Element_Registry;

defined('ABSPATH') || exit;

/**
 * Default value of every plugin setting.
 *
 * The keys deliberately match the 5.x option names, so a migrated store keeps
 * its configuration with no translation table and no data loss. New settings
 * are additive.
 *
 * Storefront copy defaults are wrapped in `__()`: the source locale is English,
 * and a Brazilian store gets the pt_BR catalogue. A store that already had its
 * own wording keeps it — the migrator copies the stored value over the default.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin
 * @author MeuMouse.com
 */
class Default_Options {

	/**
	 * Prefix of the key of every card brand the store registers.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const CUSTOM_BRAND_PREFIX = 'custom_';


	/**
	 * Get every default, keyed by setting name.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		$defaults = array_merge(
			self::installments(),
			self::discounts(),
			self::interests(),
			self::payment_methods(),
			self::texts(),
			self::styles(),
			self::integrations(),
			self::advanced()
		);

		/**
		 * Filter the plugin's default settings.
		 *
		 * An add-on registering new settings appends its defaults here. Also
		 * fired under its 5.x name, `Woo_Custom_Installments/Admin/Set_Default_Options`.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $defaults | Default settings
		 * @return array<string,mixed>
		 */
		return (array) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Admin/Default_Options', $defaults );
	}


	/**
	 * Installment plan and display defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function installments() {
		return array(
			'enable_installments_all_products' => 'yes',
			'max_qtd_installments' => '12',
			'max_qtd_installments_without_fee' => '3',
			'min_value_installments' => '20',
			'fee_installments_global' => '2.0',
			'get_type_best_installments' => 'best_installment_without_fee',
			'hook_display_best_installments' => 'display_loop_and_single_product',
			'display_installment_type' => 'popup',
			'display_installments_cart' => 'yes',
			'hook_payment_form_single_product' => 'before_cart',
			'set_custom_hook_payment_form' => '',

			// Pro.
			'set_fee_per_installment' => 'no',
			'custom_fee_installments' => array(),
			'remove_price_range' => 'no',
			'update_price_with_quantity' => 'yes',
			'update_range_price_triggers' => '',
		);
	}


	/**
	 * Discount defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function discounts() {
		return array(
			'enable_all_discount_options' => 'yes',
			'product_price_discount_method' => 'percentage',
			'discount_main_price' => '10',
			'display_discount_price_hook' => 'display_loop_and_single_product',
			'display_tag_discount_price_checkout' => 'yes',
			'include_shipping_value_in_discounts' => 'yes',
			'custom_text_after_price' => 'no',

			// Pro — per gateway.
			'gateway_discounts' => array(),

			// Pro — per quantity.
			'enable_functions_discount_per_quantity' => 'no',
			'enable_discount_per_quantity_method' => 'global',
			'set_quantity_enable_discount' => '1',
			'discount_per_quantity_method' => 'percentage',
			'value_for_discount_per_quantity' => '0',
			'enable_discount_per_unit_discount_per_quantity' => 'no',
			'message_discount_per_quantity' => 'no',
			'discount_per_qtd_message_method' => 'hook',

			// Pro — bank slip.
			'enable_ticket_discount_main_price' => 'no',
			'discount_method_ticket' => 'percentage',
			'discount_ticket' => '0',
			'display_discount_ticket_hook' => 'display_loop_and_single_product',

			// Pro — badges.
			'enable_economy_pix_badge' => 'no',
			'display_economy_pix_hook' => 'only_single_product',
			'enable_sale_badge' => 'no',
			'add_discount_custom_product_price' => 'no',
			'discount_value_custom_product_price' => '10',
		);
	}


	/**
	 * Interest defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function interests() {
		return array(
			'enable_all_interest_options' => 'no',
			'display_tag_interest_checkout' => 'no',

			// Pro — per gateway.
			'gateway_interests' => array(),
		);
	}


	/**
	 * Payment method container and badge defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function payment_methods() {
		$defaults = array(
			'enable_pix_method_payment_form' => 'no',
			'enable_instant_approval_badge' => 'no',
			'enable_ticket_method_payment_form' => 'no',
			'enable_credit_card_method_payment_form' => 'no',
			'enable_debit_card_method_payment_form' => 'no',

			// Pro — the brands the store registers itself.
			'custom_card_brands' => array(),
		);

		foreach ( self::get_card_brands() as $brand => $label ) {
			$defaults[ 'enable_' . $brand . '_flag_credit' ] = 'no';
			$defaults[ 'enable_' . $brand . '_flag_debit' ] = 'no';
		}

		return $defaults;
	}


	/**
	 * Storefront copy defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function texts() {
		return array(
			'text_before_price' => __( 'À vista', 'wc-custom-installments' ),
			'text_after_price' => __( 'no Pix', 'wc-custom-installments' ),
			'text_initial_variables' => __( 'A partir de', 'wc-custom-installments' ),
			'text_button_installments' => __( 'Detalhes das parcelas', 'wc-custom-installments' ),
			'text_pix_container' => __( 'Transferências:', 'wc-custom-installments' ),
			'text_ticket_container' => __( 'Boleto bancário:', 'wc-custom-installments' ),
			'text_instructions_ticket_container' => __( 'Você receberá os dados de pagamento ao concluir o pedido.', 'wc-custom-installments' ),
			'text_credit_card_container' => __( 'Cartões de crédito:', 'wc-custom-installments' ),
			'text_debit_card_container' => __( 'Cartões de débito:', 'wc-custom-installments' ),
			'text_table_installments' => __( 'Parcelas:', 'wc-custom-installments' ),
			'text_with_fee_installments' => __( 'com juros', 'wc-custom-installments' ),
			'text_without_fee_installments' => __( 'sem juros', 'wc-custom-installments' ),
			'text_container_payment_forms' => __( 'Formas de pagamento', 'wc-custom-installments' ),
			'custom_text_after_price_front' => __( 'no Pix', 'wc-custom-installments' ),

			// Pro.
			'text_display_installments_payment_forms' => __( '{{ parcelas }}x de {{ valor }} {{ juros }}', 'wc-custom-installments' ),
			'text_display_installments_loop' => __( 'Em até {{ parcelas }}x de {{ valor }} {{ juros }}', 'wc-custom-installments' ),
			'text_display_installments_single_product' => __( 'Em até {{ parcelas }}x de {{ valor }} {{ juros }}', 'wc-custom-installments' ),
			'text_economy_pix_badge' => __( 'Economize %s no Pix', 'wc-custom-installments' ),
			'text_before_discount_ticket' => __( 'À vista', 'wc-custom-installments' ),
			'text_after_discount_ticket' => __( 'no boleto bancário', 'wc-custom-installments' ),
			'text_discount_per_quantity_message' => __( 'Compre %d unidades e ganhe %s de desconto', 'wc-custom-installments' ),
		);
	}


	/**
	 * Presentation defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function styles() {
		return array(
			'button_popup_color' => '#008aff',
			'button_popup_size' => 'normal',
			'margin_top_popup_installments' => '1',
			'unit_margin_top_popup_installments' => 'rem',
			'margin_bottom_popup_installments' => '3',
			'unit_margin_bottom_popup_installments' => 'rem',
			'border_radius_popup_installments' => '0.25',
			'unit_border_radius_popup_installments' => 'rem',
			'center_group_elements_loop' => 'no',
			'selectors_group_for_center_elements' => '.archive .woo-custom-installments-group, .loop .woo-custom-installments-group, li.product .woo-custom-installments-group, li.wc-block-grid__product .woo-custom-installments-group, .product-grid-item .woo-custom-installments-group, .e-loop-item.product .woo-custom-installments-group, .swiper-slide .type-product .woo-custom-installments-group, .shopengine-single-product-item .woo-custom-installments-group, .products-list.grid .item-product .woo-custom-installments-group, .product-item.grid .woo-custom-installments-group, .card-product .woo-custom-installments-group, .owl-item .woo-custom-installments-group, .jet-woo-products__inner-box .woo-custom-installments-group',
			'icon_format_elements' => 'class',

			// Pro — the per-element, per-breakpoint design controls. The default
			// comes from the registry so a new element ships with its styles
			// instead of needing the option every store already wrote to.
			'elements_design' => Element_Registry::defaults(),
		);
	}


	/**
	 * Integration and maintenance defaults.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function advanced() {
		return array(
			'enable_debug_mode' => 'no',
			'headless_mode' => 'no',
		);
	}


	/**
	 * What the plugin does alongside each plugin it integrates with.
	 *
	 * The compatibility behaviours are on by default: a store that installed
	 * both plugins wants them working together, and a behaviour a store owner
	 * had to find and switch on would read as a bug until they did.
	 *
	 * The two suspensions are off, because they withhold something the store is
	 * showing today.
	 *
	 * @since 6.0.0
	 * @return array<string,mixed>
	 */
	private static function integrations() {
		return array(
			// The master switch of each card, which `Integrations\Catalog`
			// names. A store that has the other plugin wants the integration.
			'integration_elementor_enabled' => 'yes',
			'integration_rank_math_enabled' => 'yes',
			'integration_tiered_pricing_table_enabled' => 'yes',
			'integration_dynamic_pricing_discounts_enabled' => 'yes',
			'integration_role_based_pricing_enabled' => 'yes',

			'enable_elementor_widgets' => 'yes',
			'enable_price_grid_in_widgets' => 'yes',
			'integration_tiered_pricing_use_tier_price' => 'yes',
			'integration_dynamic_pricing_use_adjusted_price' => 'yes',
			'integration_role_pricing_respect_hidden_prices' => 'yes',
			'integration_role_pricing_hide_installments' => 'no',
			'integration_role_pricing_hide_discount' => 'no',

			// Pro.
			'display_discount_price_schema' => 'no',
			'enable_post_meta_feed_xml_price' => 'no',
		);
	}


	/**
	 * The card brands and payment providers the plugin offers badges for.
	 *
	 * The keys are the 5.x setting fragments, so `enable_visa_flag_credit` keeps
	 * meaning what it always meant. The brands the store registered itself
	 * (Pro) follow the ones the plugin ships.
	 *
	 * @since 6.0.0
	 * @return array<string,string>
	 */
	public static function get_card_brands() {
		$brands = array(
			'mastercard' => 'Mastercard',
			'visa' => 'Visa',
			'elo' => 'Elo',
			'american_express' => 'American Express',
			'hipercard' => 'Hipercard',
			'diners_club' => 'Diners Club',
			'discover' => 'Discover',
			'paypal' => 'PayPal',
			'stripe' => 'Stripe',
			'mercado_pago' => 'Mercado Pago',
			'pagseguro' => 'PagSeguro',
			'pagarme' => 'Pagar.me',
			'cielo' => 'Cielo',
		);

		// Listed with or without a licence. What the gate locks is their
		// switches, so a lapsed licence shows the store's brands locked rather
		// than making them, and what was switched on, disappear.
		foreach ( self::get_custom_card_brands() as $brand ) {
			if ( ! isset( $brands[ $brand['id'] ] ) ) {
				$brands[ $brand['id'] ] = $brand['label'];
			}
		}

		/**
		 * Filter the payment badges the plugin offers.
		 *
		 * @since 6.0.0
		 * @param array<string,string> $brands | Brand key => display label
		 * @return array<string,string>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Payment_Methods/Brands', $brands );
	}


	/**
	 * The card brands the store registered itself (Pro).
	 *
	 * Read from the stored option, not through the repository: the defaults are
	 * built from the brand list and the repository merges the defaults in, so
	 * asking it here would go round in circles. The list was sanitized when it
	 * was written; this only drops rows an edited option could hold that no
	 * longer name a brand.
	 *
	 * @since 6.0.0
	 * @return array<int,array{id:string,label:string,image:string}>
	 */
	public static function get_custom_card_brands() {
		$stored = get_option( Repository::OPTION, array() );
		$rows = ( is_array( $stored ) && isset( $stored['custom_card_brands'] ) && is_array( $stored['custom_card_brands'] ) ) ? $stored['custom_card_brands'] : array();
		$brands = array();

		foreach ( $rows as $row ) {
			$id = is_array( $row ) ? ( $row['id'] ?? '' ) : '';

			if ( ! self::is_custom_brand_key( $id ) || isset( $brands[ $id ] ) || '' === trim( (string) ( $row['label'] ?? '' ) ) ) {
				continue;
			}

			$brands[ $id ] = array(
				'id' => $id,
				'label' => (string) $row['label'],
				'image' => (string) ( $row['image'] ?? '' ),
			);
		}

		return array_values( $brands );
	}


	/**
	 * Whether a brand key is one of those the store registered.
	 *
	 * Those keys carry a prefix of their own, so none can collide with a brand
	 * the plugin ships, today or in a later version: a brand's key names its
	 * switches (`enable_{key}_flag_credit`), and two brands sharing one would
	 * share those too.
	 *
	 * @since 6.0.0
	 * @param mixed $key | Brand key
	 * @return bool
	 */
	public static function is_custom_brand_key( $key ) {
		return is_string( $key ) && 1 === preg_match( '/^' . self::CUSTOM_BRAND_PREFIX . '[a-z0-9_]+$/', $key );
	}
}
