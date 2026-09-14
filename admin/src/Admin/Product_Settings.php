<?php

namespace MeuMouse\WC_Custom_Installments\Admin;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
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
 * instead of the core knowing about them. Besides the keys every field has, a
 * definition may carry:
 *
 * - `depends_on` — `array( other_key => value )`: shown only while those
 *   fields hold those values (a checkbox holds `yes` or `no`).
 * - `visible` — false keeps the field out of sight. It is still printed, so
 *   saving the product keeps what it holds.
 * - `discount` — a checkbox turning on a discount of this product's own, which
 *   the "hide the discount" option overrides; the panel says so beside it.
 *
 * The small script in `assets/admin/product-settings.js` applies the first
 * three as the store owner edits, and fills quick edit from the row.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin
 * @author MeuMouse.com
 */
class Product_Settings {

	/**
	 * Quick edit field telling the save that the boxes hold the row's values.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const QUICK_EDIT_LOADED = 'wc_custom_installments_quick_edit';


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
				'label' => __( 'Ocultar parcelas', 'wc-custom-installments' ),
				'description' => __( 'Não exibir o parcelamento para este produto.', 'wc-custom-installments' ),
				'feature' => '',
				'quick_edit' => true,
			),
			array(
				'key' => '__disable_discount_main_price',
				'type' => 'checkbox',
				'label' => __( 'Ocultar o desconto', 'wc-custom-installments' ),
				'description' => __( 'Não aplicar nem exibir o desconto à vista para este produto.', 'wc-custom-installments' ),
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

		$fields = self::get_fields();

		printf(
			'<div class="options_group wc-custom-installments-options" data-wcci-fields="%s">',
			esc_attr( (string) wp_json_encode( $this->get_field_rules( $fields ) ) )
		);

		printf(
			'<p class="form-field"><strong>%s</strong></p>',
			esc_html__( 'Parcelas Customizadas', 'wc-custom-installments' )
		);

		$this->render_discounts_off_notice();

		$discount_disabled = 'yes' === get_post_meta( $post->ID, '__disable_discount_main_price', true );

		foreach ( $fields as $field ) {
			$shown = $this->is_field_shown( $field, $fields, $post->ID );

			$this->render_field( $field, $post->ID, $shown );

			if ( ! empty( $field['discount'] ) && 'checkbox' === ( $field['type'] ?? '' ) ) {
				$this->render_discount_overridden_notice(
					$field['key'],
					$shown && $discount_disabled && 'yes' === get_post_meta( $post->ID, $field['key'], true )
				);
			}
		}

		echo '</div>';
	}


	/**
	 * The display rules the panel script applies, keyed by field.
	 *
	 * Only fields that have a rule are listed.
	 *
	 * @since 6.0.0
	 * @param array<int,array<string,mixed>> $fields | Registered fields
	 * @return array<string,array<string,mixed>>
	 */
	private function get_field_rules( $fields ) {
		$rules = array();

		foreach ( $fields as $field ) {
			if ( empty( $field['key'] ) ) {
				continue;
			}

			$rule = array();

			if ( ! empty( $field['depends_on'] ) && is_array( $field['depends_on'] ) ) {
				$rule['depends_on'] = $field['depends_on'];
			}

			if ( isset( $field['visible'] ) && ! $field['visible'] ) {
				$rule['visible'] = false;
			}

			if ( ! empty( $field['discount'] ) ) {
				$rule['discount'] = true;
			}

			if ( ! empty( $rule ) ) {
				$rules[ $field['key'] ] = $rule;
			}
		}

		return $rules;
	}


	/**
	 * Whether a field starts out visible.
	 *
	 * The same rules the panel script applies, worked out from the stored
	 * values, so the panel does not open with every field showing and then
	 * collapse.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $field | Field definition
	 * @param array<int,array<string,mixed>> $fields | Registered fields
	 * @param int $post_id | Product ID
	 * @param int $depth | Guard against rules that point at each other
	 * @return bool
	 */
	private function is_field_shown( $field, $fields, $post_id, $depth = 0 ) {
		if ( isset( $field['visible'] ) && ! $field['visible'] ) {
			return false;
		}

		if ( empty( $field['depends_on'] ) || ! is_array( $field['depends_on'] ) || $depth > 5 ) {
			return true;
		}

		$by_key = array_column( $fields, null, 'key' );

		foreach ( $field['depends_on'] as $key => $expected ) {
			$value = (string) get_post_meta( $post_id, $key, true );

			if ( isset( $by_key[ $key ] ) && 'checkbox' === ( $by_key[ $key ]['type'] ?? '' ) ) {
				$value = Helpers::to_toggle( $value );
			}

			if ( ! in_array( $value, array_map( 'strval', (array) $expected ), true ) ) {
				return false;
			}

			if ( isset( $by_key[ $key ] ) && ! $this->is_field_shown( $by_key[ $key ], $fields, $post_id, $depth + 1 ) ) {
				return false;
			}
		}

		return true;
	}


	/**
	 * Warn that nothing here takes effect while discounts are off store-wide.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	private function render_discounts_off_notice() {
		if ( Repository::is_active('enable_all_discount_options') ) {
			return;
		}

		$message = esc_html__( 'Os descontos estão desativados nas configurações do plugin, então nenhum desconto é aplicado a este produto.', 'wc-custom-installments' );

		if ( current_user_can( Helpers::capability() ) ) {
			$message .= sprintf(
				' <a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE . '&section=discounts' ) ),
				esc_html__( 'Ativar descontos', 'wc-custom-installments' )
			);
		}

		wp_admin_notice( $message, array(
			'type' => 'warning',
			'additional_classes' => array( 'inline', 'wc-custom-installments-discounts-off' ),
		) );
	}


	/**
	 * Warn, beside a discount of the product's own, that "hide the discount" wins.
	 *
	 * Printed for every such field and shown or hidden by the panel script, so
	 * the warning follows the boxes as they are ticked.
	 *
	 * @since 6.0.0
	 * @param string $key | Key of the discount field
	 * @param bool $shown | Whether it starts out visible
	 * @return void
	 */
	private function render_discount_overridden_notice( $key, $shown ) {
		$classes = array( 'inline', 'wc-custom-installments-discount-warning' );

		if ( ! $shown ) {
			$classes[] = 'hidden';
		}

		wp_admin_notice(
			esc_html__( 'A opção "Ocultar o desconto" está ativada, então este desconto não é aplicado.', 'wc-custom-installments' ),
			array(
				'type' => 'warning',
				'additional_classes' => $classes,
				'attributes' => array( 'data-field' => $key ),
			)
		);
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
	 * @param bool $shown | Whether it starts out visible
	 * @return void
	 */
	private function render_field( $field, $post_id, $shown = true ) {
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
			'wrapper_class' => $shown ? '' : 'hidden',
		);

		if ( $locked ) {
			$args['custom_attributes'] = array( 'disabled' => 'disabled' );
		}

		switch ( $field['type'] ) {
			case 'select':
				$args['options'] = $field['options'] ?? array();

				// A stored value the list no longer offers — a gateway since
				// removed, say — is kept as a choice of its own. Otherwise the
				// select would show the first option, and saving the product
				// would write that over the value without anyone choosing it.
				if ( '' !== (string) $args['value'] && ! array_key_exists( (string) $args['value'], $args['options'] ) ) {
					$args['options'][ (string) $args['value'] ] = (string) $args['value'];
				}

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

		// Set by the product screen script once it has copied the row's values
		// into the boxes. Until then an unticked box means "not loaded", not
		// "switched off", and the save leaves the product alone.
		if ( 'quick' === $context ) {
			printf( '<input type="hidden" name="%s" value="" />', esc_attr( self::QUICK_EDIT_LOADED ) );
		}

		foreach ( $fields as $field ) {
			if ( ! empty( $field['feature'] ) && ! Features::is_available( $field['feature'] ) ) {
				continue;
			}

			if ( 'bulk' === $context ) {
				printf(
					'<label class="alignleft"><span class="title">%s</span><span class="input-text-wrap"><select name="%s"><option value="">%s</option><option value="yes">%s</option><option value="no">%s</option></select></span></label>',
					esc_html( $field['label'] ),
					esc_attr( $field['key'] ),
					esc_html__( '— Sem alteração —', 'wc-custom-installments' ),
					esc_html__( 'Sim', 'wc-custom-installments' ),
					esc_html__( 'Não', 'wc-custom-installments' )
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
		if ( empty( $_POST[ self::QUICK_EDIT_LOADED ] ) ) {
			return;
		}

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
	 * WordPress builds the quick edit form once for the whole list, so a value
	 * that is not printed into the row cannot be pre-filled. The product screen
	 * script copies these into the form when a row's quick edit opens; without
	 * that, every box would open unticked and saving would switch the option off
	 * on a product that only had its price changed.
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
				'<div class="%1$s" data-key="%1$s">%2$s</div>',
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
