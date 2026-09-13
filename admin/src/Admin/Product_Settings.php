<?php

namespace MeuMouse\WC_Custom_Installments\Admin;

use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Per-product overrides, in the product data panel.
 *
 * These stay native WooCommerce fields rather than a Vue app. The product edit
 * screen is WooCommerce's screen, not ours: its fields have to take part in the
 * post save, and quick edit and bulk edit only work with markup WooCommerce
 * recognises. A panel of our own would have to reimplement all three to end up
 * somewhere worse.
 *
 * The fields are a registry, so the Pro add-on adds its own through a filter
 * instead of the core knowing about them.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin
 * @author MeuMouse.com
 */
class Product_Settings {

	/**
	 * Register the product screen hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );

		add_action( 'woocommerce_product_quick_edit_end', array( $this, 'render_quick_edit_fields' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'render_bulk_edit_fields' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( $this, 'save_quick_edit' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'save_bulk_edit' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'print_quick_edit_values' ) );
	}


	/**
	 * Every per-product field the plugin offers.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_fields() {
		$fields = array(
			array(
				'key' => '__disable_installments',
				'type' => 'checkbox',
				'label' => __( 'Hide installments', 'wc-custom-installments' ),
				'description' => __( 'Do not show the installment plan for this product.', 'wc-custom-installments' ),
				'feature' => '',
				'quick_edit' => true,
			),
			array(
				'key' => '__disable_discount_main_price',
				'type' => 'checkbox',
				'label' => __( 'Hide the discount', 'wc-custom-installments' ),
				'description' => __( 'Do not apply or show the upfront-payment discount for this product.', 'wc-custom-installments' ),
				'feature' => '',
				'quick_edit' => true,
			),
		);

		/**
		 * Filter the per-product fields.
		 *
		 * This is how the Pro add-on adds the per-product discount overrides
		 * without the free plugin knowing they exist.
		 *
		 * @since 6.0.0
		 * @param array<int,array<string,mixed>> $fields | Registered fields
		 * @return array<int,array<string,mixed>>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Admin/Product_Fields', $fields );
	}


	/**
	 * Print the fields inside the product data panel.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_fields() {
		global $post;

		if ( ! $post ) {
			return;
		}

		echo '<div class="options_group wc-custom-installments-options">';

		printf(
			'<p class="form-field"><strong>%s</strong></p>',
			esc_html__( 'Custom Installments', 'wc-custom-installments' )
		);

		foreach ( self::get_fields() as $field ) {
			$this->render_field( $field, $post->ID );
		}

		echo '</div>';
	}


	/**
	 * Print one field.
	 *
	 * A gated field renders disabled and labelled, rather than not at all: a
	 * store owner should be able to see what the add-on would give them from
	 * the screen where they would use it.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $field | Field definition
	 * @param int $post_id | Product ID
	 * @return void
	 */
	private function render_field( $field, $post_id ) {
		$locked = ! empty( $field['feature'] ) && ! Features::is_available( $field['feature'] );
		$label = $locked
			/* translators: %s: field label */
			? sprintf( __( '%s (Pro)', 'wc-custom-installments' ), $field['label'] )
			: $field['label'];

		$args = array(
			'id' => $field['key'],
			'label' => $label,
			'description' => $field['description'] ?? '',
			'desc_tip' => true,
			'value' => get_post_meta( $post_id, $field['key'], true ),
		);

		if ( $locked ) {
			$args['custom_attributes'] = array( 'disabled' => 'disabled' );
		}

		switch ( $field['type'] ) {
			case 'select':
				$args['options'] = $field['options'] ?? array();
				woocommerce_wp_select( $args );
				break;

			case 'number':
				$args['type'] = 'number';
				$args['custom_attributes'] = array_merge(
					$args['custom_attributes'] ?? array(),
					array( 'step' => $field['step'] ?? '0.01', 'min' => $field['min'] ?? '0' )
				);
				woocommerce_wp_text_input( $args );
				break;

			case 'text':
				woocommerce_wp_text_input( $args );
				break;

			case 'checkbox':
			default:
				woocommerce_wp_checkbox( $args );
				break;
		}
	}


	/**
	 * Persist the fields when the product is saved.
	 *
	 * @since 6.0.0
	 * @param int $post_id | Product ID
	 * @return void
	 */
	public function save_fields( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// WooCommerce has already verified its own nonce by the time
		// woocommerce_process_product_meta runs.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		foreach ( self::get_fields() as $field ) {
			// A gated field is never written, the same rule the settings
			// repository follows. The disabled input submits nothing anyway;
			// this is what makes that true rather than merely likely.
			if ( ! empty( $field['feature'] ) && ! Features::is_available( $field['feature'] ) ) {
				continue;
			}

			$key = $field['key'];

			if ( 'checkbox' === $field['type'] ) {
				update_post_meta( $post_id, $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' );

				continue;
			}

			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}


	/**
	 * Print the quick edit controls.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_quick_edit_fields() {
		$this->render_list_fields( 'quick' );
	}


	/**
	 * Print the bulk edit controls.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_bulk_edit_fields() {
		$this->render_list_fields( 'bulk' );
	}


	/**
	 * Print the controls for the products list.
	 *
	 * Only checkboxes appear here. Quick edit is for the one or two decisions
	 * worth making without opening the product, and a discount table is not one
	 * of them.
	 *
	 * @since 6.0.0
	 * @param string $context | Either `quick` or `bulk`
	 * @return void
	 */
	private function render_list_fields( $context ) {
		$fields = array_filter( self::get_fields(), static function( $field ) {
			return ! empty( $field['quick_edit'] ) && 'checkbox' === $field['type'];
		} );

		if ( empty( $fields ) ) {
			return;
		}

		echo '<div class="inline-edit-group wc-custom-installments-inline">';

		foreach ( $fields as $field ) {
			if ( ! empty( $field['feature'] ) && ! Features::is_available( $field['feature'] ) ) {
				continue;
			}

			if ( 'bulk' === $context ) {
				printf(
					'<label class="alignleft"><span class="title">%s</span><span class="input-text-wrap"><select name="%s"><option value="">%s</option><option value="yes">%s</option><option value="no">%s</option></select></span></label>',
					esc_html( $field['label'] ),
					esc_attr( $field['key'] ),
					esc_html__( '— No change —', 'wc-custom-installments' ),
					esc_html__( 'Yes', 'wc-custom-installments' ),
					esc_html__( 'No', 'wc-custom-installments' )
				);

				continue;
			}

			printf(
				'<label class="alignleft"><input type="checkbox" name="%s" value="yes" /><span class="checkbox-title">%s</span></label>',
				esc_attr( $field['key'] ),
				esc_html( $field['label'] )
			);
		}

		echo '</div>';
	}


	/**
	 * Save the quick edit controls.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return void
	 */
	public function save_quick_edit( $product ) {
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified its own before firing this.
		foreach ( $this->list_field_keys() as $key ) {
			update_post_meta( $product->get_id(), $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}


	/**
	 * Save the bulk edit controls.
	 *
	 * An unset dropdown means "no change", which is why bulk edit cannot use
	 * the checkbox convention: an unchecked box and an untouched one look the
	 * same, and bulk-clearing every product's setting by accident is not a
	 * mistake anyone recovers from quickly.
	 *
	 * @since 6.0.0
	 * @param object $product | Product object
	 * @return void
	 */
	public function save_bulk_edit( $product ) {
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verified its own before firing this.
		foreach ( $this->list_field_keys() as $key ) {
			if ( empty( $_POST[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

			if ( in_array( $value, array( 'yes', 'no' ), true ) ) {
				update_post_meta( $product->get_id(), $key, $value );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}


	/**
	 * Print the stored values for the quick edit form to read.
	 *
	 * WordPress builds the quick edit form from the row's markup, so a value
	 * that is not printed into the row cannot be pre-filled.
	 *
	 * @since 6.0.0
	 * @param string $column | Column being rendered
	 * @return void
	 */
	public function print_quick_edit_values( $column ) {
		if ( 'name' !== $column ) {
			return;
		}

		global $post;

		if ( ! $post ) {
			return;
		}

		echo '<div class="hidden wc-custom-installments-inline-data">';

		foreach ( $this->list_field_keys() as $key ) {
			printf(
				'<div class="%s">%s</div>',
				esc_attr( $key ),
				esc_html( Helpers::to_toggle( get_post_meta( $post->ID, $key, true ) ) )
			);
		}

		echo '</div>';
	}


	/**
	 * The field keys that appear in the products list.
	 *
	 * @since 6.0.0
	 * @return array<int,string>
	 */
	private function list_field_keys() {
		$keys = array();

		foreach ( self::get_fields() as $field ) {
			if ( empty( $field['quick_edit'] ) || 'checkbox' !== $field['type'] ) {
				continue;
			}

			if ( ! empty( $field['feature'] ) && ! Features::is_available( $field['feature'] ) ) {
				continue;
			}

			$keys[] = $field['key'];
		}

		return $keys;
	}
}
