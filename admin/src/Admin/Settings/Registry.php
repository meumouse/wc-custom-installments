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
			array(
				'id' => 'installments',
				'title' => __( 'Installments', 'wc-custom-installments' ),
				'description' => __( 'How many installments the store offers, and what they cost.', 'wc-custom-installments' ),
				'layout' => 'cards',
				'cards' => array(
					array(
						'id' => 'installments-general',
						'title' => __( 'Installment plan', 'wc-custom-installments' ),
						'description' => __( 'The rules used to build every installment shown on the storefront.', 'wc-custom-installments' ),
						'fields' => array(
							self::toggle(
								'enable_installments_all_products',
								__( 'Show installments on products', 'wc-custom-installments' ),
								__( 'Display the installment plan on the shop loop and the product page.', 'wc-custom-installments' )
							),
							self::text(
								'max_qtd_installments',
								__( 'Maximum installments', 'wc-custom-installments' ),
								__( 'The highest number of installments the store accepts.', 'wc-custom-installments' ),
								array( 'inputmode' => 'numeric', 'placeholder' => '12' )
							),
							self::text(
								'max_qtd_installments_without_fee',
								__( 'Installments without interest', 'wc-custom-installments' ),
								__( 'How many of them are offered with no interest added.', 'wc-custom-installments' ),
								array( 'inputmode' => 'numeric', 'placeholder' => '3' )
							),
							self::text(
								'fee_installments_global',
								__( 'Monthly interest rate (%)', 'wc-custom-installments' ),
								__( 'Applied to every installment beyond the interest-free ones.', 'wc-custom-installments' ),
								array( 'inputmode' => 'decimal', 'placeholder' => '2.0' )
							),
							self::text(
								'min_value_installments',
								__( 'Minimum installment amount', 'wc-custom-installments' ),
								__( 'Installments smaller than this are not offered.', 'wc-custom-installments' ),
								array( 'inputmode' => 'decimal', 'placeholder' => '20' )
							),
							self::select(
								'display_installment_type',
								__( 'Installment details', 'wc-custom-installments' ),
								__( 'How the full installment table is presented to the customer.', 'wc-custom-installments' ),
								array(
									array( 'value' => 'popup', 'label' => __( 'In a popup', 'wc-custom-installments' ) ),
									array( 'value' => 'table', 'label' => __( 'As a table on the page', 'wc-custom-installments' ) ),
									array( 'value' => 'none', 'label' => __( "Don't show the details", 'wc-custom-installments' ) ),
								)
							),
						),
					),
					array(
						'id' => 'installments-fees',
						'title' => __( 'Interest per installment', 'wc-custom-installments' ),
						'description' => __( 'Replace the single monthly rate with one rate per installment number.', 'wc-custom-installments' ),
						'fields' => array(
							self::toggle(
								'set_fee_per_installment',
								__( 'Set the interest of each installment', 'wc-custom-installments' ),
								__( 'Charge a different rate for each installment count, matching what your acquirer bills you.', 'wc-custom-installments' ),
								array( 'feature' => 'fee_per_installment' )
							),
						),
					),
				),
			),
			array(
				'id' => 'discounts',
				'title' => __( 'Discounts', 'wc-custom-installments' ),
				'description' => __( 'Reward customers who pay upfront.', 'wc-custom-installments' ),
				'layout' => 'cards',
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
								array(
									array( 'value' => 'percentage', 'label' => __( 'Percentage', 'wc-custom-installments' ) ),
									array( 'value' => 'fixed', 'label' => __( 'Fixed amount', 'wc-custom-installments' ) ),
								)
							),
							self::text(
								'discount_main_price',
								__( 'Discount amount', 'wc-custom-installments' ),
								__( 'The percentage or the amount taken off the price.', 'wc-custom-installments' ),
								array( 'inputmode' => 'decimal', 'placeholder' => '10' )
							),
							self::toggle(
								'include_shipping_value_in_discounts',
								__( 'Include shipping in the discount', 'wc-custom-installments' ),
								__( 'Apply the discount to the shipping cost as well as the products.', 'wc-custom-installments' )
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
							self::toggle(
								'enable_sale_badge',
								__( 'Show the sale badge', 'wc-custom-installments' ),
								__( 'Display the discount percentage over products on sale.', 'wc-custom-installments' ),
								array( 'feature' => 'sale_badge' )
							),
						),
					),
				),
			),
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
	 * Flatten the schema into a field definition map, keyed by setting name.
	 *
	 * The repository sanitizes against this, so a field that is not in the
	 * schema cannot be written through the settings endpoint.
	 *
	 * @since 1.0.0
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_field_definitions() {
		$definitions = array();

		foreach ( self::get_schema() as $section ) {
			foreach ( (array) ( $section['cards'] ?? array() ) as $card ) {
				foreach ( (array) ( $card['fields'] ?? array() ) as $field ) {
					if ( ! empty( $field['key'] ) ) {
						$definitions[ $field['key'] ] = $field;
					}
				}
			}
		}

		return $definitions;
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
				foreach ( (array) ( $card['fields'] ?? array() ) as $field_index => $field ) {
					$feature = $field['feature'] ?? '';
					$locked = ( '' !== $feature ) && ! Features::is_available( $feature );

					$schema[ $section_index ]['cards'][ $card_index ]['fields'][ $field_index ]['locked'] = $locked;
					$schema[ $section_index ]['cards'][ $card_index ]['fields'][ $field_index ]['tier'] =
						( '' !== $feature && Features::is_pro( $feature ) ) ? Features::TIER_PRO : Features::TIER_FREE;
				}
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
