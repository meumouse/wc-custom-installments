<?php

namespace MeuMouse\WC_Custom_Installments\Admin\Settings;

use MeuMouse\WC_Custom_Installments\Admin\Default_Options;
use MeuMouse\WC_Custom_Installments\Core\Features;

defined('ABSPATH') || exit;

/**
 * Build the settings schema the Vue app renders.
 *
 * The schema is the contract between the two halves of the plugin: PHP decides
 * which sections, cards and fields exist, what type each one is and which
 * feature gates it; the browser renders that and never invents a field of its
 * own. Every label and description is translated here, so the payload reaches
 * the browser already in the site's language.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin\Settings
 * @author MeuMouse.com
 */
class Registry {

	/**
	 * Build the full schema.
	 *
	 * @since 1.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_schema() {
		$schema = array(
			self::section_installments(),
			self::section_discounts(),
			self::section_interests(),
			self::section_payment_methods(),
			self::section_texts(),
			self::section_styles(),
			self::section_advanced(),
		);

		/**
		 * Filter the settings schema.
		 *
		 * This is how an add-on appends its own sections, cards and fields.
		 *
		 * @since 1.0.0
		 * @param array<int,array<string,mixed>> $schema | Settings schema
		 * @return array<int,array<string,mixed>>
		 */
		$schema = (array) apply_filters( 'WC_Custom_Installments/Admin/Settings_Schema', $schema );

		return self::resolve_locks( $schema );
	}


	/**
	 * Cached field definitions for the current request.
	 *
	 * @since 1.0.0
	 * @var array<string,array<string,mixed>>|null
	 */
	private static $definitions = null;


	/**
	 * Flatten the schema into a field definition map, keyed by setting name.
	 *
	 * The repository sanitizes against this, so a field absent from the schema
	 * cannot be written through the settings endpoint.
	 *
	 * @since 1.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_field_definitions() {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$definitions = array();

		foreach ( self::get_schema() as $section ) {
			foreach ( (array) ( $section['cards'] ?? array() ) as $card ) {
				foreach ( (array) ( $card['fields'] ?? array() ) as $field ) {
					if ( empty( $field['key'] ) ) {
						continue;
					}

					$definitions[ $field['key'] ] = $field;

					// A measurement field carries two settings: the amount and
					// the unit it is expressed in. Without its own definition
					// the unit would never be written, because the repository
					// only writes what the schema declares.
					if ( ! empty( $field['unit_key'] ) ) {
						$definitions[ $field['unit_key'] ] = array(
							'type' => 'select',
							'key' => $field['unit_key'],
							'label' => $field['label'],
							'description' => '',
							'default' => Default_Options::get_defaults()[ $field['unit_key'] ] ?? 'rem',
							'feature' => $field['feature'] ?? '',
							'options' => array_map(
								static function( $unit ) {
									return array( 'value' => $unit, 'label' => $unit );
								},
								(array) ( $field['units'] ?? array() )
							),
						);
					}
				}
			}
		}

		self::$definitions = $definitions;

		return self::$definitions;
	}


	/**
	 * Forget the cached field definitions.
	 *
	 * Needed after a licence change, which flips the locked state of every
	 * gated field, and by the tests.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function flush() {
		self::$definitions = null;
	}


	/**
	 * Installments section.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	private static function section_installments() {
		return array(
			'id' => 'installments',
			'title' => __( 'Installments', 'wc-custom-installments' ),
			'description' => __( 'How many installments the store offers, what they cost, and where they appear.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'installments-plan',
					'title' => __( 'Installment plan', 'wc-custom-installments' ),
					'description' => __( 'The rules used to build every installment shown on the storefront.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_installments_all_products',
							__( 'Show installments on products', 'wc-custom-installments' ),
							__( 'Display the installment plan on the shop loop and the product page.', 'wc-custom-installments' )
						),
						self::number(
							'max_qtd_installments',
							__( 'Maximum installments', 'wc-custom-installments' ),
							__( 'The highest number of installments the store accepts.', 'wc-custom-installments' ),
							array( 'min' => 1, 'max' => 48, 'placeholder' => '12' )
						),
						self::number(
							'max_qtd_installments_without_fee',
							__( 'Installments without interest', 'wc-custom-installments' ),
							__( 'How many of them are offered with no interest added.', 'wc-custom-installments' ),
							array( 'min' => 0, 'max' => 48, 'placeholder' => '3' )
						),
						self::number(
							'fee_installments_global',
							__( 'Monthly interest rate (%)', 'wc-custom-installments' ),
							__( 'Applied to every installment beyond the interest-free ones.', 'wc-custom-installments' ),
							array( 'step' => '0.01', 'min' => 0, 'placeholder' => '2.0' )
						),
						self::number(
							'min_value_installments',
							__( 'Minimum installment amount', 'wc-custom-installments' ),
							__( 'Installments smaller than this are not offered.', 'wc-custom-installments' ),
							array( 'step' => '0.01', 'min' => 0, 'placeholder' => '20' )
						),
					),
				),
				array(
					'id' => 'installments-fees',
					'title' => __( 'Interest per installment', 'wc-custom-installments' ),
					'description' => __( 'Replace the single monthly rate with one rate per installment count, matching what your acquirer bills you.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'set_fee_per_installment',
							__( 'Set the interest of each installment', 'wc-custom-installments' ),
							__( 'Charge a different rate for each installment count instead of one monthly rate.', 'wc-custom-installments' ),
							array( 'feature' => 'fee_per_installment' )
						),
						self::field(
							'installment-fees',
							'custom_fee_installments',
							__( 'Interest table', 'wc-custom-installments' ),
							__( 'One rate per installment count. Leave a row empty to fall back to the global rate.', 'wc-custom-installments' ),
							array(
								'feature' => 'fee_per_installment',
								'depends_on' => array( 'set_fee_per_installment' => 'yes' ),
							)
						),
					),
				),
				array(
					'id' => 'installments-display',
					'title' => __( 'Where installments appear', 'wc-custom-installments' ),
					'description' => __( 'Placement and presentation of the installment information.', 'wc-custom-installments' ),
					'fields' => array(
						self::select(
							'get_type_best_installments',
							__( 'Highlighted installment', 'wc-custom-installments' ),
							__( 'Which installment is shown under the price as the headline.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'best_installment_without_fee', 'label' => __( 'The largest interest-free installment', 'wc-custom-installments' ) ),
								array( 'value' => 'best_installment_with_fee', 'label' => __( 'The largest installment, interest included', 'wc-custom-installments' ) ),
								array( 'value' => 'min_value_installment', 'label' => __( 'The smallest installment allowed', 'wc-custom-installments' ) ),
							)
						),
						self::select(
							'hook_display_best_installments',
							__( 'Show the highlighted installment on', 'wc-custom-installments' ),
							__( 'Which storefront pages carry it.', 'wc-custom-installments' ),
							self::display_hook_options()
						),
						self::select(
							'display_installment_type',
							__( 'Installment details', 'wc-custom-installments' ),
							__( 'How the full installment table is presented to the customer.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'popup', 'label' => __( 'In a popup', 'wc-custom-installments' ) ),
								array( 'value' => 'table', 'label' => __( 'As a table on the page', 'wc-custom-installments' ) ),
								array( 'value' => 'none', 'label' => __( 'Do not show the details', 'wc-custom-installments' ) ),
							)
						),
						self::toggle(
							'display_installments_cart',
							__( 'Show installments in the cart', 'wc-custom-installments' ),
							__( 'Display the installment plan for the cart total.', 'wc-custom-installments' )
						),
						self::select(
							'hook_payment_form_single_product',
							__( 'Payment methods block position', 'wc-custom-installments' ),
							__( 'Where the accepted payment methods appear on the product page.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'before_cart', 'label' => __( 'Before the add-to-cart form', 'wc-custom-installments' ) ),
								array( 'value' => 'after_cart', 'label' => __( 'After the add-to-cart form', 'wc-custom-installments' ) ),
								array( 'value' => 'custom', 'label' => __( 'On a custom hook', 'wc-custom-installments' ) ),
							)
						),
						self::text(
							'set_custom_hook_payment_form',
							__( 'Custom hook', 'wc-custom-installments' ),
							__( 'The action hook the payment methods block is attached to.', 'wc-custom-installments' ),
							array(
								'placeholder' => 'woocommerce_single_product_summary',
								'depends_on' => array( 'hook_payment_form_single_product' => 'custom' ),
							)
						),
					),
				),
				array(
					'id' => 'installments-variable',
					'title' => __( 'Variable products', 'wc-custom-installments' ),
					'description' => __( 'How prices behave when the customer picks a variation or changes the quantity.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'remove_price_range',
							__( 'Replace the price range', 'wc-custom-installments' ),
							__( 'Show a single starting price instead of the "from — to" range on variable products.', 'wc-custom-installments' ),
							array( 'feature' => 'variable_price_range' )
						),
						self::toggle(
							'update_price_with_quantity',
							__( 'Update the price with the quantity', 'wc-custom-installments' ),
							__( 'Recalculate the displayed price and installments as the customer changes the quantity.', 'wc-custom-installments' ),
							array( 'feature' => 'variable_price_range' )
						),
						self::text(
							'update_range_price_triggers',
							__( 'Extra variation triggers', 'wc-custom-installments' ),
							__( 'CSS selectors, comma separated, for themes that switch variations their own way instead of through the WooCommerce form.', 'wc-custom-installments' ),
							array(
								'placeholder' => '.my-theme-swatch, .variation-button',
								'feature' => 'variable_price_range',
							)
						),
					),
				),
			),
		);
	}


	/**
	 * Discounts section.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	private static function section_discounts() {
		return array(
			'id' => 'discounts',
			'title' => __( 'Discounts', 'wc-custom-installments' ),
			'description' => __( 'Reward customers who pay upfront.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'discounts-main',
					'title' => __( 'Discount on the product price', 'wc-custom-installments' ),
					'description' => __( 'A single discount applied to the displayed price, for upfront payments such as Pix.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_all_discount_options',
							__( 'Enable discounts', 'wc-custom-installments' ),
							__( 'Turn the discount features on for the whole store.', 'wc-custom-installments' )
						),
						self::select(
							'product_price_discount_method',
							__( 'Discount type', 'wc-custom-installments' ),
							__( 'Whether the discount is a percentage of the price or a fixed amount.', 'wc-custom-installments' ),
							self::discount_method_options()
						),
						self::number(
							'discount_main_price',
							__( 'Discount amount', 'wc-custom-installments' ),
							__( 'The percentage or the amount taken off the price.', 'wc-custom-installments' ),
							array( 'step' => '0.01', 'min' => 0, 'placeholder' => '10' )
						),
						self::select(
							'display_discount_price_hook',
							__( 'Show the discounted price on', 'wc-custom-installments' ),
							__( 'Which storefront pages carry it.', 'wc-custom-installments' ),
							self::display_hook_options()
						),
						self::toggle(
							'include_shipping_value_in_discounts',
							__( 'Include shipping in the discount', 'wc-custom-installments' ),
							__( 'Apply the discount to the shipping cost as well as the products.', 'wc-custom-installments' )
						),
						self::toggle(
							'display_tag_discount_price_checkout',
							__( 'Show the discount at checkout', 'wc-custom-installments' ),
							__( 'Display a line in the order totals stating how much was taken off.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'discounts-gateways',
					'title' => __( 'Discounts per payment method', 'wc-custom-installments' ),
					'description' => __( 'A different discount for each gateway, applied to the cart and checkout totals.', 'wc-custom-installments' ),
					'fields' => array(
						self::field(
							'gateway-rates',
							'gateway_discounts',
							__( 'Discount per gateway', 'wc-custom-installments' ),
							__( 'Set an amount for each payment method your store accepts.', 'wc-custom-installments' ),
							array(
								'feature' => 'gateway_discounts',
								'gateways' => self::get_payment_gateways(),
								'methods' => self::discount_method_options(),
							)
						),
					),
				),
				array(
					'id' => 'discounts-quantity',
					'title' => __( 'Discounts per quantity', 'wc-custom-installments' ),
					'description' => __( 'Reward customers who buy more of the same product.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_functions_discount_per_quantity',
							__( 'Enable quantity discounts', 'wc-custom-installments' ),
							__( 'Apply a discount once the customer reaches a minimum quantity.', 'wc-custom-installments' ),
							array( 'feature' => 'quantity_discounts' )
						),
						self::select(
							'enable_discount_per_quantity_method',
							__( 'Applies to', 'wc-custom-installments' ),
							__( 'Whether the rule is set once for the store or per product.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'global', 'label' => __( 'Every product', 'wc-custom-installments' ) ),
								array( 'value' => 'per_product', 'label' => __( 'Each product individually', 'wc-custom-installments' ) ),
							),
							array(
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_functions_discount_per_quantity' => 'yes' ),
							)
						),
						self::number(
							'set_quantity_enable_discount',
							__( 'Minimum quantity', 'wc-custom-installments' ),
							__( 'How many units the customer has to buy for the discount to apply.', 'wc-custom-installments' ),
							array(
								'min' => 1,
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_discount_per_quantity_method' => 'global' ),
							)
						),
						self::select(
							'discount_per_quantity_method',
							__( 'Discount type', 'wc-custom-installments' ),
							__( 'Whether the quantity discount is a percentage or a fixed amount.', 'wc-custom-installments' ),
							self::discount_method_options(),
							array(
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_discount_per_quantity_method' => 'global' ),
							)
						),
						self::number(
							'value_for_discount_per_quantity',
							__( 'Discount amount', 'wc-custom-installments' ),
							__( 'The percentage or the amount taken off once the minimum is reached.', 'wc-custom-installments' ),
							array(
								'step' => '0.01',
								'min' => 0,
								'feature' => 'quantity_discounts',
								'depends_on' => array( 'enable_discount_per_quantity_method' => 'global' ),
							)
						),
						self::toggle(
							'message_discount_per_quantity',
							__( 'Announce the quantity discount', 'wc-custom-installments' ),
							__( 'Show a message telling the customer how to earn it.', 'wc-custom-installments' ),
							array( 'feature' => 'quantity_discounts' )
						),
					),
				),
				array(
					'id' => 'discounts-ticket',
					'title' => __( 'Bank slip discount', 'wc-custom-installments' ),
					'description' => __( 'A separate discount for customers paying with a bank slip.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_ticket_discount_main_price',
							__( 'Enable the bank slip discount', 'wc-custom-installments' ),
							__( 'Show a second discounted price for bank slip payments.', 'wc-custom-installments' ),
							array( 'feature' => 'ticket_discount' )
						),
						self::select(
							'discount_method_ticket',
							__( 'Discount type', 'wc-custom-installments' ),
							__( 'Whether the bank slip discount is a percentage or a fixed amount.', 'wc-custom-installments' ),
							self::discount_method_options(),
							array(
								'feature' => 'ticket_discount',
								'depends_on' => array( 'enable_ticket_discount_main_price' => 'yes' ),
							)
						),
						self::number(
							'discount_ticket',
							__( 'Discount amount', 'wc-custom-installments' ),
							__( 'The percentage or the amount taken off for bank slip payments.', 'wc-custom-installments' ),
							array(
								'step' => '0.01',
								'min' => 0,
								'feature' => 'ticket_discount',
								'depends_on' => array( 'enable_ticket_discount_main_price' => 'yes' ),
							)
						),
						self::select(
							'display_discount_ticket_hook',
							__( 'Show the bank slip price on', 'wc-custom-installments' ),
							__( 'Which storefront pages carry it.', 'wc-custom-installments' ),
							self::display_hook_options(),
							array(
								'feature' => 'ticket_discount',
								'depends_on' => array( 'enable_ticket_discount_main_price' => 'yes' ),
							)
						),
					),
				),
				array(
					'id' => 'discounts-badges',
					'title' => __( 'Savings badges', 'wc-custom-installments' ),
					'description' => __( 'Highlight how much the customer saves.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_economy_pix_badge',
							__( 'Show the Pix savings badge', 'wc-custom-installments' ),
							__( 'Display how much the customer saves by paying with Pix.', 'wc-custom-installments' ),
							array( 'feature' => 'pix_economy_badge' )
						),
						self::select(
							'display_economy_pix_hook',
							__( 'Show the savings badge on', 'wc-custom-installments' ),
							__( 'Which storefront pages carry it.', 'wc-custom-installments' ),
							self::display_hook_options(),
							array(
								'feature' => 'pix_economy_badge',
								'depends_on' => array( 'enable_economy_pix_badge' => 'yes' ),
							)
						),
						self::toggle(
							'enable_sale_badge',
							__( 'Show the sale badge', 'wc-custom-installments' ),
							__( 'Display the discount percentage over products on sale.', 'wc-custom-installments' ),
							array( 'feature' => 'sale_badge' )
						),
					),
				),
			),
		);
	}


	/**
	 * Interests section.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	private static function section_interests() {
		return array(
			'id' => 'interests',
			'title' => __( 'Interest', 'wc-custom-installments' ),
			'description' => __( 'Charge the processing cost of a payment method back to the order.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'interests-general',
					'title' => __( 'Interest on the order total', 'wc-custom-installments' ),
					'description' => __( 'Adds a fee to the cart and checkout totals for the chosen payment method.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_all_interest_options',
							__( 'Enable interest', 'wc-custom-installments' ),
							__( 'Turn the interest features on for the whole store.', 'wc-custom-installments' )
						),
						self::toggle(
							'display_tag_interest_checkout',
							__( 'Show the interest at checkout', 'wc-custom-installments' ),
							__( 'Display a line in the order totals stating how much was added.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'interests-gateways',
					'title' => __( 'Interest per payment method', 'wc-custom-installments' ),
					'description' => __( 'A different rate for each gateway, applied to the cart and checkout totals.', 'wc-custom-installments' ),
					'fields' => array(
						self::field(
							'gateway-rates',
							'gateway_interests',
							__( 'Interest per gateway', 'wc-custom-installments' ),
							__( 'Set an amount for each payment method your store accepts.', 'wc-custom-installments' ),
							array(
								'feature' => 'gateway_interests',
								'gateways' => self::get_payment_gateways(),
								'methods' => self::discount_method_options(),
							)
						),
					),
				),
			),
		);
	}


	/**
	 * Payment methods section.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	private static function section_payment_methods() {
		return array(
			'id' => 'payment-methods',
			'title' => __( 'Payment methods', 'wc-custom-installments' ),
			'description' => __( 'The block of accepted payment methods shown on the product page.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'payment-methods-containers',
					'title' => __( 'Which methods to show', 'wc-custom-installments' ),
					'description' => __( 'Each one becomes a row in the payment methods block.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_pix_method_payment_form',
							__( 'Pix', 'wc-custom-installments' ),
							__( 'Show the Pix row, with its badge and instructions.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_instant_approval_badge',
							__( 'Instant approval badge', 'wc-custom-installments' ),
							__( 'Add an "approved instantly" note beside the Pix badge.', 'wc-custom-installments' ),
							array( 'depends_on' => array( 'enable_pix_method_payment_form' => 'yes' ) )
						),
						self::toggle(
							'enable_ticket_method_payment_form',
							__( 'Bank slip', 'wc-custom-installments' ),
							__( 'Show the bank slip row, with its badge and instructions.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_credit_card_method_payment_form',
							__( 'Credit card', 'wc-custom-installments' ),
							__( 'Show the credit card row and the brands you accept.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_debit_card_method_payment_form',
							__( 'Debit card', 'wc-custom-installments' ),
							__( 'Show the debit card row and the brands you accept.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'payment-methods-credit-brands',
					'title' => __( 'Credit card brands', 'wc-custom-installments' ),
					'description' => __( 'The badges shown in the credit card row.', 'wc-custom-installments' ),
					'depends_on' => array( 'enable_credit_card_method_payment_form' => 'yes' ),
					'fields' => self::brand_fields('credit'),
				),
				array(
					'id' => 'payment-methods-debit-brands',
					'title' => __( 'Debit card brands', 'wc-custom-installments' ),
					'description' => __( 'The badges shown in the debit card row.', 'wc-custom-installments' ),
					'depends_on' => array( 'enable_debit_card_method_payment_form' => 'yes' ),
					'fields' => self::brand_fields('debit'),
				),
			),
		);
	}


	/**
	 * Texts section.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	private static function section_texts() {
		return array(
			'id' => 'texts',
			'title' => __( 'Texts', 'wc-custom-installments' ),
			'description' => __( 'Every piece of copy the plugin puts on the storefront.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'texts-price',
					'title' => __( 'Around the price', 'wc-custom-installments' ),
					'description' => __( 'The words framing the discounted price and the installment line.', 'wc-custom-installments' ),
					'fields' => array(
						self::text( 'text_before_price', __( 'Before the discounted price', 'wc-custom-installments' ), __( 'For example: "Cash price".', 'wc-custom-installments' ) ),
						self::text( 'text_after_price', __( 'After the discounted price', 'wc-custom-installments' ), __( 'For example: "with Pix".', 'wc-custom-installments' ) ),
						self::text( 'text_initial_variables', __( 'Before a variable product price', 'wc-custom-installments' ), __( 'For example: "From".', 'wc-custom-installments' ) ),
						self::text( 'text_with_fee_installments', __( 'Interest suffix', 'wc-custom-installments' ), __( 'Appended to an installment that carries interest.', 'wc-custom-installments' ) ),
						self::text( 'text_without_fee_installments', __( 'Interest-free suffix', 'wc-custom-installments' ), __( 'Appended to an installment with no interest.', 'wc-custom-installments' ) ),
					),
				),
				array(
					'id' => 'texts-templates',
					'title' => __( 'Installment templates', 'wc-custom-installments' ),
					'description' => __( 'Rewrite the installment line entirely. Available tokens: {{ parcelas }}, {{ valor }}, {{ juros }}.', 'wc-custom-installments' ),
					'fields' => array(
						self::placeholder_text( 'text_display_installments_loop', __( 'On the shop loop', 'wc-custom-installments' ), '', array( 'feature' => 'custom_placeholders' ) ),
						self::placeholder_text( 'text_display_installments_single_product', __( 'On the product page', 'wc-custom-installments' ), '', array( 'feature' => 'custom_placeholders' ) ),
						self::placeholder_text( 'text_display_installments_payment_forms', __( 'In the installment table', 'wc-custom-installments' ), '', array( 'feature' => 'custom_placeholders' ) ),
					),
				),
				array(
					'id' => 'texts-payment-methods',
					'title' => __( 'Payment methods block', 'wc-custom-installments' ),
					'description' => __( 'Headings inside the accepted payment methods block.', 'wc-custom-installments' ),
					'fields' => array(
						self::text( 'text_container_payment_forms', __( 'Block heading', 'wc-custom-installments' ), '' ),
						self::text( 'text_pix_container', __( 'Pix heading', 'wc-custom-installments' ), '' ),
						self::text( 'text_ticket_container', __( 'Bank slip heading', 'wc-custom-installments' ), '' ),
						self::textarea( 'text_instructions_ticket_container', __( 'Bank slip instructions', 'wc-custom-installments' ), __( 'Shown under the bank slip badge.', 'wc-custom-installments' ) ),
						self::text( 'text_credit_card_container', __( 'Credit card heading', 'wc-custom-installments' ), '' ),
						self::text( 'text_debit_card_container', __( 'Debit card heading', 'wc-custom-installments' ), '' ),
						self::text( 'text_table_installments', __( 'Installment table heading', 'wc-custom-installments' ), '' ),
						self::text( 'text_button_installments', __( 'Installment details link', 'wc-custom-installments' ), __( 'The link that opens the full installment table.', 'wc-custom-installments' ) ),
					),
				),
				array(
					'id' => 'texts-badges',
					'title' => __( 'Badges and messages', 'wc-custom-installments' ),
					'description' => __( 'Copy for the savings badge, the bank slip price and the quantity discount message.', 'wc-custom-installments' ),
					'fields' => array(
						self::text(
							'text_economy_pix_badge',
							__( 'Pix savings badge', 'wc-custom-installments' ),
							__( 'Use %s where the saved amount should appear.', 'wc-custom-installments' ),
							array( 'feature' => 'pix_economy_badge' )
						),
						self::text( 'text_before_discount_ticket', __( 'Before the bank slip price', 'wc-custom-installments' ), '', array( 'feature' => 'ticket_discount' ) ),
						self::text( 'text_after_discount_ticket', __( 'After the bank slip price', 'wc-custom-installments' ), '', array( 'feature' => 'ticket_discount' ) ),
						self::text(
							'text_discount_per_quantity_message',
							__( 'Quantity discount message', 'wc-custom-installments' ),
							__( 'Use %d for the quantity and %s for the discount.', 'wc-custom-installments' ),
							array( 'feature' => 'quantity_discounts' )
						),
					),
				),
			),
		);
	}


	/**
	 * Styles section.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	private static function section_styles() {
		return array(
			'id' => 'styles',
			'title' => __( 'Appearance', 'wc-custom-installments' ),
			'description' => __( 'How the plugin looks inside your theme.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'styles-popup',
					'title' => __( 'Installment popup', 'wc-custom-installments' ),
					'description' => __( 'The link that opens the installment table, and the spacing around it.', 'wc-custom-installments' ),
					'fields' => array(
						self::color( 'button_popup_color', __( 'Link colour', 'wc-custom-installments' ), __( 'Colour of the installment details link.', 'wc-custom-installments' ) ),
						self::select(
							'button_popup_size',
							__( 'Link size', 'wc-custom-installments' ),
							'',
							array(
								array( 'value' => 'small', 'label' => __( 'Small', 'wc-custom-installments' ) ),
								array( 'value' => 'normal', 'label' => __( 'Normal', 'wc-custom-installments' ) ),
								array( 'value' => 'large', 'label' => __( 'Large', 'wc-custom-installments' ) ),
							)
						),
						self::unit( 'margin_top_popup_installments', 'unit_margin_top_popup_installments', __( 'Space above', 'wc-custom-installments' ), '' ),
						self::unit( 'margin_bottom_popup_installments', 'unit_margin_bottom_popup_installments', __( 'Space below', 'wc-custom-installments' ), '' ),
						self::unit( 'border_radius_popup_installments', 'unit_border_radius_popup_installments', __( 'Corner radius', 'wc-custom-installments' ), '' ),
					),
				),
				array(
					'id' => 'styles-loop',
					'title' => __( 'Shop loop', 'wc-custom-installments' ),
					'description' => __( 'Alignment of the price group inside product cards.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'center_group_elements_loop',
							__( 'Centre the price group', 'wc-custom-installments' ),
							__( 'Useful for themes whose product cards are centre-aligned.', 'wc-custom-installments' )
						),
						self::textarea(
							'selectors_group_for_center_elements',
							__( 'CSS selectors', 'wc-custom-installments' ),
							__( 'The selectors the centring rule is applied to. Only change this if your theme needs it.', 'wc-custom-installments' ),
							array( 'depends_on' => array( 'center_group_elements_loop' => 'yes' ) )
						),
					),
				),
				array(
					'id' => 'styles-icons',
					'title' => __( 'Icons', 'wc-custom-installments' ),
					'description' => __( 'How the icons beside each element are rendered.', 'wc-custom-installments' ),
					'fields' => array(
						self::select(
							'icon_format_elements',
							__( 'Icon format', 'wc-custom-installments' ),
							__( 'Inline SVG works everywhere; icon fonts need Font Awesome loaded by your theme.', 'wc-custom-installments' ),
							array(
								array( 'value' => 'svg', 'label' => __( 'Inline SVG', 'wc-custom-installments' ) ),
								array( 'value' => 'class', 'label' => __( 'Font Awesome class', 'wc-custom-installments' ) ),
							)
						),
					),
				),
				array(
					'id' => 'styles-elements',
					'title' => __( 'Element design', 'wc-custom-installments' ),
					'description' => __( 'Typography, spacing and colours for each element of the price group, per breakpoint.', 'wc-custom-installments' ),
					'fields' => array(
						self::field(
							'element-design',
							'elements_design',
							__( 'Design controls', 'wc-custom-installments' ),
							__( 'Order the elements and style each one for mobile and desktop.', 'wc-custom-installments' ),
							array( 'feature' => 'advanced_design' )
						),
					),
				),
			),
		);
	}


	/**
	 * Advanced section.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	private static function section_advanced() {
		return array(
			'id' => 'advanced',
			'title' => __( 'Advanced', 'wc-custom-installments' ),
			'description' => __( 'Integrations, structured data and maintenance.', 'wc-custom-installments' ),
			'cards' => array(
				array(
					'id' => 'advanced-integrations',
					'title' => __( 'Integrations', 'wc-custom-installments' ),
					'description' => __( 'Page builders and SEO plugins.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_elementor_widgets',
							__( 'Elementor widgets', 'wc-custom-installments' ),
							__( 'Register the plugin widgets in the Elementor editor.', 'wc-custom-installments' )
						),
						self::toggle(
							'enable_price_grid_in_widgets',
							__( 'Price group inside widgets', 'wc-custom-installments' ),
							__( 'Render the full price group where a widget shows a product price.', 'wc-custom-installments' )
						),
						self::toggle(
							'display_discount_price_schema',
							__( 'Discounted price in structured data', 'wc-custom-installments' ),
							__( 'Report the discounted price to search engines instead of the list price.', 'wc-custom-installments' ),
							array( 'feature' => 'seo_schema' )
						),
						self::toggle(
							'enable_post_meta_feed_xml_price',
							__( 'Discounted price in product feeds', 'wc-custom-installments' ),
							__( 'Store the discounted price in a meta field for shopping feed plugins to read.', 'wc-custom-installments' ),
							array( 'feature' => 'seo_schema' )
						),
					),
				),
				array(
					'id' => 'advanced-maintenance',
					'title' => __( 'Maintenance', 'wc-custom-installments' ),
					'description' => __( 'Diagnostics and data.', 'wc-custom-installments' ),
					'fields' => array(
						self::toggle(
							'enable_debug_mode',
							__( 'Debug mode', 'wc-custom-installments' ),
							__( 'Log additional detail to WooCommerce > Status > Logs.', 'wc-custom-installments' )
						),
					),
				),
				array(
					'id' => 'advanced-transfer',
					'title' => __( 'Import and export', 'wc-custom-installments' ),
					'description' => __( 'Move this configuration to another store, or restore the defaults.', 'wc-custom-installments' ),
					'component' => 'settings-transfer',
				),
			),
		);
	}


	/**
	 * Build the badge toggles of one card row.
	 *
	 * @since 1.0.0
	 * @param string $type | Either `credit` or `debit`
	 * @return array<int,array<string,mixed>>
	 */
	private static function brand_fields( $type ) {
		$fields = array();

		foreach ( Default_Options::get_card_brands() as $brand => $label ) {
			$fields[] = self::toggle( 'enable_' . $brand . '_flag_' . $type, $label, '' );
		}

		return $fields;
	}


	/**
	 * The storefront placements a display setting can take.
	 *
	 * @since 1.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function display_hook_options() {
		return array(
			array( 'value' => 'display_loop_and_single_product', 'label' => __( 'The shop loop and the product page', 'wc-custom-installments' ) ),
			array( 'value' => 'only_single_product', 'label' => __( 'The product page only', 'wc-custom-installments' ) ),
			array( 'value' => 'only_loop', 'label' => __( 'The shop loop only', 'wc-custom-installments' ) ),
			array( 'value' => 'none', 'label' => __( 'Nowhere', 'wc-custom-installments' ) ),
		);
	}


	/**
	 * Percentage or fixed amount.
	 *
	 * @since 1.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function discount_method_options() {
		return array(
			array( 'value' => 'percentage', 'label' => __( 'Percentage', 'wc-custom-installments' ) ),
			array( 'value' => 'fixed', 'label' => __( 'Fixed amount', 'wc-custom-installments' ) ),
		);
	}


	/**
	 * List the store's enabled payment gateways.
	 *
	 * WooCommerce is not always loaded when the schema is built — during a CLI
	 * run, for instance — so an empty list is a valid answer rather than a fatal.
	 *
	 * @since 1.0.0
	 * @return array<int,array<string,string>>
	 */
	private static function get_payment_gateways() {
		if ( ! function_exists('WC') || ! WC()->payment_gateways() ) {
			return array();
		}

		$gateways = array();

		foreach ( WC()->payment_gateways()->get_available_payment_gateways() as $gateway ) {
			$gateways[] = array(
				'id' => $gateway->id,
				'label' => $gateway->get_title(),
			);
		}

		return $gateways;
	}


	/**
	 * Mark every field whose feature is unavailable as locked.
	 *
	 * The value still travels to the browser, so the store owner sees what the
	 * feature does; the repository is what refuses to write it.
	 *
	 * @since 1.0.0
	 * @param array<int,array<string,mixed>> $schema | Settings schema
	 * @return array<int,array<string,mixed>>
	 */
	private static function resolve_locks( $schema ) {
		foreach ( $schema as $section_index => $section ) {
			foreach ( (array) ( $section['cards'] ?? array() ) as $card_index => $card ) {
				$card_locked = true;
				$has_fields = false;

				foreach ( (array) ( $card['fields'] ?? array() ) as $field_index => $field ) {
					$has_fields = true;
					$feature = $field['feature'] ?? '';
					$locked = ( '' !== $feature ) && ! Features::is_available( $feature );

					if ( ! $locked ) {
						$card_locked = false;
					}

					$schema[ $section_index ]['cards'][ $card_index ]['fields'][ $field_index ]['locked'] = $locked;
					$schema[ $section_index ]['cards'][ $card_index ]['fields'][ $field_index ]['tier'] =
						( '' !== $feature && Features::is_pro( $feature ) ) ? Features::TIER_PRO : Features::TIER_FREE;
				}

				// A card whose every field is locked is announced as a whole,
				// so a Pro-only card does not become a wall of badges.
				$schema[ $section_index ]['cards'][ $card_index ]['locked'] = $has_fields && $card_locked;
			}
		}

		return $schema;
	}


	/**
	 * Build a toggle field definition.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function toggle( $key, $label, $description, $extra = array() ) {
		return self::field( 'toggle', $key, $label, $description, $extra );
	}


	/**
	 * Build a text field definition.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function text( $key, $label, $description, $extra = array() ) {
		return self::field( 'text', $key, $label, $description, $extra );
	}


	/**
	 * Build a select field definition.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<int,array<string,string>> $options | Selectable options
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function select( $key, $label, $description, $options, $extra = array() ) {
		return self::field( 'select', $key, $label, $description, array_merge( array( 'options' => $options ), $extra ) );
	}


	/**
	 * Build a numeric field definition.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function number( $key, $label, $description, $extra = array() ) {
		return self::field( 'number', $key, $label, $description, $extra );
	}


	/**
	 * Build a textarea field definition.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function textarea( $key, $label, $description, $extra = array() ) {
		return self::field( 'textarea', $key, $label, $description, $extra );
	}


	/**
	 * Build a colour field definition.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function color( $key, $label, $description, $extra = array() ) {
		return self::field( 'color', $key, $label, $description, $extra );
	}


	/**
	 * Build a text field that offers the installment tokens.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function placeholder_text( $key, $label, $description, $extra = array() ) {
		return self::field( 'placeholder-text', $key, $label, $description, array_merge( array(
			'tokens' => array( '{{ parcelas }}', '{{ valor }}', '{{ juros }}' ),
		), $extra ) );
	}


	/**
	 * Build a measurement field: a number plus the unit it is expressed in.
	 *
	 * @since 1.0.0
	 * @param string $key | Setting name holding the amount
	 * @param string $unit_key | Setting name holding the unit
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function unit( $key, $unit_key, $label, $description, $extra = array() ) {
		return self::field( 'unit', $key, $label, $description, array_merge( array(
			'unit_key' => $unit_key,
			'units' => array( 'px', 'rem', 'em', '%' ),
			'step' => '0.05',
		), $extra ) );
	}


	/**
	 * Build a field definition of any type.
	 *
	 * @since 1.0.0
	 * @param string $type | Field type
	 * @param string $key | Setting name
	 * @param string $label | Field label
	 * @param string $description | Field description
	 * @param array<string,mixed> $extra | Additional field keys
	 * @return array<string,mixed>
	 */
	private static function field( $type, $key, $label, $description, $extra = array() ) {
		$defaults = Default_Options::get_defaults();

		return array_merge( array(
			'type' => $type,
			'key' => $key,
			'label' => $label,
			'description' => $description,
			'default' => $defaults[ $key ] ?? '',
			'feature' => '',
		), $extra );
	}
}
