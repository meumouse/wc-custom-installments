<?php

namespace MeuMouse\WC_Custom_Installments\Admin;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Per-product overrides, in a "Parcelas Customizadas" tab of the product data panel.
 *
 * These stay native WooCommerce fields rather than a Vue app. The product edit
 * screen is WooCommerce's screen, not ours: its fields have to take part in the
 * post save, and quick edit and bulk edit only work with markup WooCommerce
 * recognises. A panel of our own would have to reimplement all three to end up
 * somewhere worse.
 *
 * The fields are a registry an add-on extends through a filter. The Pro fields
 * are declared here, gated, like the Pro fields of the settings schema: a store
 * without the add-on sees them disabled and marked, on the screen where it would
 * use them, and the add-on only has to open the gate. Besides the keys every
 * field has, a definition may carry:
 *
 * - `group` — the group it is listed under (see get_groups()); a field naming
 *   none, or one that does not exist, goes to `other`.
 * - `depends_on` — `array( other_key => value )`: shown only while those
 *   fields hold those values (a checkbox holds `yes` or `no`).
 * - `visible` — false keeps the field out of sight. It is still printed, so
 *   saving the product keeps what it holds.
 * - `discount` — a checkbox turning on a discount of this product's own, which
 *   the "hide the discount" option overrides; the panel says so beside it.
 *
 * The small script in `assets/admin/product-settings.js` applies the rules as
 * the store owner edits, and fills quick edit from the row.
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
	 * Key of the tab in the product data panel.
	 *
	 * WooCommerce derives the tab's classes from it (`<key>_options`), which is
	 * what the stylesheet gives the tab its icon by.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const TAB = 'wc_custom_installments';

	/**
	 * ID of the panel the tab opens.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const PANEL = 'wc_custom_installments_product_data';

	/**
	 * Request key holding the variation fields, by variation index.
	 *
	 * The variation fields share their meta keys with the product's, so they
	 * post under one name of their own: a variation's
	 * `enable_discount_per_unit` must never read as the product's checkbox
	 * when both reach the same request.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const VARIATION_FIELDS = 'wc_custom_installments_variation';


	/**
	 * Register the product screen hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'register_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_fields' ) );

		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

		add_action( 'woocommerce_product_quick_edit_end', array( $this, 'render_quick_edit_fields' ) );
		add_action( 'woocommerce_product_bulk_edit_end', array( $this, 'render_bulk_edit_fields' ) );
		add_action( 'woocommerce_product_quick_edit_save', array( $this, 'save_quick_edit' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'save_bulk_edit' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'print_quick_edit_values' ) );
	}


	/**
	 * Every per-product field the plugin offers.
	 *
	 * The meta keys are the 5.x ones. They live on thousands of products, and a
	 * rename would quietly drop every override a store had configured.
	 *
	 * The discount details only show while their own toggle is on, as in 5.x.
	 * The quantity rule only shows when the store sets that rule per product:
	 * with a single store-wide rule there is nothing to fill in here. Hidden
	 * fields are still printed, so saving the product keeps whatever they hold.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_fields() {
		$methods = self::get_discount_methods();

		$per_unit = array( 'enable_discount_per_unit' => 'yes' );
		$per_quantity = array( 'enable_discount_per_quantity' => 'yes' );
		$quantity_shown = self::is_quantity_rule_shown();

		$fields = array(
			array(
				'key' => '__disable_installments',
				'type' => 'checkbox',
				'label' => __( 'Ocultar parcelas', 'wc-custom-installments' ),
				'description' => __( 'Não exibir o parcelamento para este produto.', 'wc-custom-installments' ),
				'feature' => '',
				'quick_edit' => true,
				'group' => 'installments',
			),
			array(
				'key' => '__disable_discount_main_price',
				'type' => 'checkbox',
				'label' => __( 'Ocultar o desconto', 'wc-custom-installments' ),
				'description' => __( 'Não aplicar nem exibir o desconto à vista para este produto.', 'wc-custom-installments' ),
				'feature' => '',
				'quick_edit' => true,
				'group' => 'discounts',
			),
			array(
				'key' => 'enable_discount_per_unit',
				'type' => 'checkbox',
				'label' => __( 'Desconto personalizado para este produto', 'wc-custom-installments' ),
				'description' => __( 'Usa um desconto próprio em vez do desconto geral da loja.', 'wc-custom-installments' ),
				'feature' => 'product_overrides',
				'quick_edit' => false,
				'discount' => true,
				'group' => 'discounts',
			),
			array(
				'key' => 'discount_per_unit_method',
				'type' => 'select',
				'label' => __( 'Tipo de desconto', 'wc-custom-installments' ),
				'description' => '',
				'options' => $methods,
				'feature' => 'product_overrides',
				'quick_edit' => false,
				'depends_on' => $per_unit,
				'group' => 'discounts',
			),
			array(
				'key' => 'unit_discount_amount',
				'type' => 'number',
				'label' => __( 'Valor do desconto', 'wc-custom-installments' ),
				'description' => '',
				'feature' => 'product_overrides',
				'quick_edit' => false,
				'depends_on' => $per_unit,
				'group' => 'discounts',
			),
			array(
				'key' => 'discount_gateway',
				'type' => 'select',
				'label' => __( 'Somente para esta forma de pagamento', 'wc-custom-installments' ),
				'description' => __( 'Deixe vazio para aplicar o desconto em qualquer forma de pagamento.', 'wc-custom-installments' ),
				'options' => self::get_gateway_options(),
				'feature' => 'gateway_discounts',
				'quick_edit' => false,
				'depends_on' => $per_unit,
				'group' => 'discounts',
			),
			array(
				'key' => 'enable_discount_per_quantity',
				'type' => 'checkbox',
				'label' => __( 'Desconto por quantidade para este produto', 'wc-custom-installments' ),
				'description' => __( 'Usa uma regra de quantidade própria em vez da regra geral da loja.', 'wc-custom-installments' ),
				'feature' => 'quantity_discounts',
				'quick_edit' => false,
				'discount' => true,
				'visible' => $quantity_shown,
				'group' => 'quantity_discounts',
			),
			array(
				'key' => 'minimum_quantity_discount',
				'type' => 'number',
				'label' => __( 'Quantidade mínima', 'wc-custom-installments' ),
				'description' => '',
				'min' => '1',
				'step' => '1',
				'feature' => 'quantity_discounts',
				'quick_edit' => false,
				'depends_on' => $per_quantity,
				'visible' => $quantity_shown,
				'group' => 'quantity_discounts',
			),
			array(
				'key' => 'discount_per_quantity_method',
				'type' => 'select',
				'label' => __( 'Tipo de desconto por quantidade', 'wc-custom-installments' ),
				'description' => '',
				'options' => $methods,
				'feature' => 'quantity_discounts',
				'quick_edit' => false,
				'depends_on' => $per_quantity,
				'visible' => $quantity_shown,
				'group' => 'quantity_discounts',
			),
			array(
				'key' => 'quantity_discount_amount',
				'type' => 'number',
				'label' => __( 'Valor do desconto por quantidade', 'wc-custom-installments' ),
				'description' => '',
				'feature' => 'quantity_discounts',
				'quick_edit' => false,
				'depends_on' => $per_quantity,
				'visible' => $quantity_shown,
				'group' => 'quantity_discounts',
			),
		);

		/**
		 * Filter the per-product fields.
		 *
		 * This is how an add-on lists a field of its own in the plugin's tab of
		 * the product data panel, saved and gated like the built-in ones.
		 *
		 * @since 6.0.0
		 * @param array<int,array<string,mixed>> $fields | Registered fields
		 * @return array<int,array<string,mixed>>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Admin/Product_Fields', $fields );
	}


	/**
	 * The groups the tab lists its fields under, in order.
	 *
	 * @since 6.0.0
	 * @return array<string,string> Group key => title
	 */
	public static function get_groups() {
		return array(
			'installments' => __( 'Parcelamento', 'wc-custom-installments' ),
			'discounts' => __( 'Descontos', 'wc-custom-installments' ),
			'quantity_discounts' => __( 'Desconto por quantidade', 'wc-custom-installments' ),
			'other' => __( 'Outras opções', 'wc-custom-installments' ),
		);
	}


	/**
	 * Sort the fields into their groups.
	 *
	 * Groups come out in the order get_groups() lists them, fields in the order
	 * they were registered, and groups with no fields are left out.
	 *
	 * @since 6.0.0
	 * @param array<int,array<string,mixed>> $fields | Registered fields
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	public static function group_fields( $fields ) {
		$groups = array_fill_keys( array_keys( self::get_groups() ), array() );

		foreach ( $fields as $field ) {
			if ( empty( $field['key'] ) ) {
				continue;
			}

			$group = (string) ( $field['group'] ?? '' );

			if ( ! isset( $groups[ $group ] ) ) {
				$group = 'other';
			}

			$groups[ $group ][] = $field;
		}

		return array_filter( $groups );
	}


	/**
	 * The ways a per-product discount can be expressed.
	 *
	 * @since 6.0.0
	 * @return array<string,string>
	 */
	private static function get_discount_methods() {
		return array(
			'percentage' => __( 'Porcentagem', 'wc-custom-installments' ),
			'fixed' => __( 'Valor fixo', 'wc-custom-installments' ),
		);
	}


	/**
	 * The fields each variation of a variable product offers.
	 *
	 * The product's own discount, set per variation, under the same meta keys
	 * the product uses. The engine reads a variation's before its parent's
	 * (Calculate_Values::get_discount_source_id()), so the toggle is a choice of
	 * three rather than a checkbox: "same as the product" stores nothing and
	 * follows the parent, which is how every existing variation behaves, while
	 * a checkbox would write `no` on the first save and cut that link.
	 *
	 * Besides the keys a product field has, a variation field may carry `width`
	 * (`first`, `last` or `full`, WooCommerce's variation columns) and `inherit`
	 * (an empty value deletes the meta instead of storing it).
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_variation_fields() {
		$own = array( 'enable_discount_per_unit' => 'yes' );

		return array(
			array(
				'key' => 'enable_discount_per_unit',
				'type' => 'select',
				'label' => __( 'Desconto personalizado', 'wc-custom-installments' ),
				'description' => __( 'Por padrão, a variação usa o desconto definido no produto.', 'wc-custom-installments' ),
				'options' => array(
					'' => __( 'Igual ao produto', 'wc-custom-installments' ),
					'yes' => __( 'Desconto próprio desta variação', 'wc-custom-installments' ),
					'no' => __( 'Desconto geral da loja', 'wc-custom-installments' ),
				),
				'feature' => 'product_overrides',
				'discount' => true,
				'inherit' => true,
				'width' => 'full',
			),
			array(
				'key' => 'discount_per_unit_method',
				'type' => 'select',
				'label' => __( 'Tipo de desconto', 'wc-custom-installments' ),
				'description' => '',
				'options' => self::get_discount_methods(),
				'feature' => 'product_overrides',
				'depends_on' => $own,
				'width' => 'first',
			),
			array(
				'key' => 'unit_discount_amount',
				'type' => 'number',
				'label' => __( 'Valor do desconto', 'wc-custom-installments' ),
				'description' => '',
				'feature' => 'product_overrides',
				'depends_on' => $own,
				'width' => 'last',
			),
			array(
				'key' => 'discount_gateway',
				'type' => 'select',
				'label' => __( 'Somente para esta forma de pagamento', 'wc-custom-installments' ),
				'description' => __( 'Deixe vazio para aplicar o desconto em qualquer forma de pagamento.', 'wc-custom-installments' ),
				'options' => self::get_gateway_options(),
				'feature' => 'gateway_discounts',
				'depends_on' => $own,
				'width' => 'full',
			),
		);
	}


	/**
	 * Print the plugin's fields inside one variation.
	 *
	 * WooCommerce loads variations over AJAX, a page at a time, and names each
	 * control by its position on the page, so the IDs and names carry that
	 * index. The panel script reads it from the wrapper.
	 *
	 * @since 6.0.0
	 * @param int $loop | Position of the variation on the page
	 * @param array<string,mixed> $variation_data | Variation data
	 * @param object $variation | Variation post
	 * @return void
	 */
	public function render_variation_fields( $loop, $variation_data, $variation ) {
		if ( ! is_object( $variation ) || empty( $variation->ID ) ) {
			return;
		}

		$loop = (int) $loop;
		$fields = self::get_variation_fields();
		$prefix = 'wcci_variation_';
		$suffix = '_' . $loop;

		printf(
			'<div class="wc-custom-installments-options wc-custom-installments-variation" data-wcci-fields="%1$s" data-wcci-prefix="%2$s" data-wcci-suffix="%3$s"><h4 class="wc-custom-installments-group-title">%4$s</h4>',
			esc_attr( (string) wp_json_encode( $this->get_field_rules( $fields ) ) ),
			esc_attr( $prefix ),
			esc_attr( $suffix ),
			esc_html__( 'Parcelas Customizadas', 'wc-custom-installments' )
		);

		$discount_disabled = 'yes' === get_post_meta( (int) $variation->post_parent, '__disable_discount_main_price', true );

		foreach ( $fields as $field ) {
			$shown = $this->is_field_shown( $field, $fields, $variation->ID );

			$this->render_field( $field, $variation->ID, $shown, array(
				'id' => $prefix . $field['key'] . $suffix,
				'name' => sprintf( '%s[%d][%s]', self::VARIATION_FIELDS, $loop, $field['key'] ),
				'wrapper_class' => 'form-row form-row-' . ( $field['width'] ?? 'full' ),
			) );

			if ( ! empty( $field['discount'] ) ) {
				$this->render_discount_overridden_notice(
					$field['key'],
					$shown && $discount_disabled && 'yes' === get_post_meta( $variation->ID, $field['key'], true )
				);
			}
		}

		echo '</div>';
	}


	/**
	 * Persist a variation's fields.
	 *
	 * Only what was posted for this variation is written: WooCommerce saves the
	 * variations that changed, and a variation absent from the request keeps
	 * what it has. A gated field is never written, as on the product.
	 *
	 * @since 6.0.0
	 * @param int $variation_id | Variation ID
	 * @param int $index | Position of the variation in the request
	 * @return void
	 */
	public function save_variation_fields( $variation_id, $index ) {
		if ( ! current_user_can( 'edit_post', $variation_id ) ) {
			return;
		}

		// WooCommerce has verified its own nonce before saving variations.
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ self::VARIATION_FIELDS ][ $index ] ) || ! is_array( $_POST[ self::VARIATION_FIELDS ][ $index ] ) ) {
			return;
		}

		$submitted = wp_unslash( $_POST[ self::VARIATION_FIELDS ][ $index ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		foreach ( self::get_variation_fields() as $field ) {
			$key = $field['key'];

			if ( self::is_locked( $field ) || ! array_key_exists( $key, $submitted ) || ! is_scalar( $submitted[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( (string) $submitted[ $key ] );

			// A choice the list does not offer is not written. That covers a
			// value the variation already holds and the list no longer offers,
			// such as a payment method since removed: the select shows it as a
			// choice of its own, and skipping it here keeps it.
			if ( 'select' === $field['type'] && ! array_key_exists( $value, $field['options'] ?? array() ) ) {
				continue;
			}

			if ( '' === $value && ! empty( $field['inherit'] ) ) {
				delete_post_meta( $variation_id, $key );

				continue;
			}

			update_post_meta( $variation_id, $key, $value );
		}
	}


	/**
	 * Add the plugin's tab to the product data panel.
	 *
	 * After "Advanced", and out of grouped products, which have no price of
	 * their own for an installment plan or a discount to apply to. That is also
	 * where the General tab, which held these fields before, is hidden.
	 *
	 * @since 6.0.0
	 * @param array<string,array<string,mixed>> $tabs | Product data tabs
	 * @return array<string,array<string,mixed>>
	 */
	public function register_tab( $tabs ) {
		$tabs[ self::TAB ] = array(
			'label' => __( 'Parcelas Customizadas', 'wc-custom-installments' ),
			'target' => self::PANEL,
			'class' => array( 'hide_if_grouped' ),
			'priority' => 75,
		);

		return $tabs;
	}


	/**
	 * Print the panel the plugin's tab opens.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_panel() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$fields = self::get_fields();
		$titles = self::get_groups();

		printf(
			'<div id="%1$s" class="panel woocommerce_options_panel hidden wc-custom-installments-options" data-wcci-fields="%2$s">',
			esc_attr( self::PANEL ),
			esc_attr( (string) wp_json_encode( $this->get_field_rules( $fields ) ) )
		);

		$this->render_pro_notice( $fields );
		$this->render_discounts_off_notice();

		$discount_disabled = 'yes' === get_post_meta( $post->ID, '__disable_discount_main_price', true );

		foreach ( self::group_fields( $fields ) as $group => $group_fields ) {
			// A group whose every field is out of sight for good is hidden with
			// them, so its title does not sit above nothing. Fields hidden by
			// `depends_on` do not count: their toggle is still there.
			$in_sight = array_filter( $group_fields, static function( $field ) {
				return ! isset( $field['visible'] ) || $field['visible'];
			} );

			printf(
				'<div class="options_group wc-custom-installments-group%1$s" data-group="%2$s"><h4 class="wc-custom-installments-group-title">%3$s</h4>',
				empty( $in_sight ) ? ' hidden' : '',
				esc_attr( $group ),
				esc_html( $titles[ $group ] )
			);

			foreach ( $group_fields as $field ) {
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

		echo '</div>';
	}


	/**
	 * Whether a field is gated and the gate is closed.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $field | Field definition
	 * @return bool
	 */
	private static function is_locked( $field ) {
		return ! empty( $field['feature'] ) && ! Features::is_available( $field['feature'] );
	}


	/**
	 * Say what the fields marked Pro need, above them.
	 *
	 * Without the add-on, what it is and where to get it; with the add-on but no
	 * valid licence, where to activate it. Printed only when a field in sight is
	 * locked, so a licensed store never sees it.
	 *
	 * @since 6.0.0
	 * @param array<int,array<string,mixed>> $fields | Registered fields
	 * @return void
	 */
	private function render_pro_notice( $fields ) {
		$locked = array_filter( $fields, static function( $field ) {
			return self::is_locked( $field ) && ( ! isset( $field['visible'] ) || $field['visible'] );
		} );

		if ( empty( $locked ) ) {
			return;
		}

		if ( Helpers::is_pro_active() ) {
			$message = esc_html__( 'Os campos marcados como Pro precisam de uma licença ativa do Parcelas Customizadas Pro.', 'wc-custom-installments' );

			// The licence tab is the add-on's (`?section=license`); the
			// settings screen opens its first tab if the name ever changes.
			if ( current_user_can( Helpers::capability() ) ) {
				$message .= sprintf(
					' <a href="%s">%s</a>',
					esc_url( admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE . '&section=license' ) ),
					esc_html__( 'Ativar licença', 'wc-custom-installments' )
				);
			}
		} else {
			$message = esc_html__( 'Os campos marcados como Pro fazem parte do Parcelas Customizadas Pro e ficam disponíveis assim que ele é instalado e ativado.', 'wc-custom-installments' );

			// A new tab: the product being edited may have unsaved changes.
			$message .= sprintf(
				' <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( WC_CUSTOM_INSTALLMENTS_STORE_URL ),
				esc_html__( 'Conhecer o Pro', 'wc-custom-installments' )
			);
		}

		wp_admin_notice( $message, array(
			'type' => 'info',
			'additional_classes' => array( 'inline', 'wc-custom-installments-pro-notice' ),
		) );
	}


	/**
	 * Whether the quantity rule fields show.
	 *
	 * Only when the store sets that rule per product, read raw rather than
	 * through the gate: this decides what the panel shows, and a locked field
	 * still shows, disabled. The 6.0 development spelling of the value is
	 * accepted beside the 5.x one the schema stores.
	 *
	 * While the feature is locked the store cannot choose that mode, so the
	 * fields show regardless, disabled: they are there to say what the add-on
	 * would give the product, not to be filled in.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	private static function is_quantity_rule_shown() {
		if ( ! Features::is_available('quantity_discounts') ) {
			return true;
		}

		return in_array( (string) Repository::get('enable_discount_per_quantity_method'), array( 'product', 'per_product' ), true );
	}


	/**
	 * The payment methods a per-product discount can be tied to.
	 *
	 * Every gateway WooCommerce knows, enabled or not, as 5.x listed them. The
	 * available ones depend on the cart and the customer, and on the product
	 * screen there is neither: a gateway that happened to be unavailable here
	 * would drop out of the list, and saving the product would then quietly
	 * clear the choice.
	 *
	 * @since 6.0.0
	 * @return array<string,string>
	 */
	private static function get_gateway_options() {
		$options = array( '' => __( 'Qualquer forma de pagamento', 'wc-custom-installments' ) );

		if ( ! function_exists('WC') || ! WC()->payment_gateways ) {
			return $options;
		}

		foreach ( WC()->payment_gateways->payment_gateways() as $gateway_id => $gateway ) {
			$id = ! empty( $gateway->id ) ? (string) $gateway->id : (string) $gateway_id;
			$title = wp_strip_all_tags( (string) $gateway->get_title() );

			if ( '' === $title && ! empty( $gateway->method_title ) ) {
				$title = wp_strip_all_tags( (string) $gateway->method_title );
			}

			$options[ $id ] = '' !== $title ? $title : $id;
		}

		return $options;
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
	 * A gated field renders disabled and marked Pro, rather than not at all: a
	 * store owner should be able to see what the add-on would give them from
	 * the screen where they would use it.
	 *
	 * @since 6.0.0
	 * @param array<string,mixed> $field | Field definition
	 * @param int $post_id | Product or variation ID
	 * @param bool $shown | Whether it starts out visible
	 * @param array<string,string> $overrides | `id`, `name` and `wrapper_class` for a control that is not the product's own
	 * @return void
	 */
	private function render_field( $field, $post_id, $shown = true, $overrides = array() ) {
		$locked = self::is_locked( $field );
		$label = $field['label'];
		$wrapper_classes = array_filter( array( $overrides['wrapper_class'] ?? '' ) );

		// WooCommerce passes the label through wp_kses_post(), so the badge
		// survives and the label itself is escaped here.
		if ( $locked ) {
			$label = sprintf(
				'%1$s <span class="wc-custom-installments-pro-badge">%2$s</span>',
				esc_html( $label ),
				esc_html__( 'Pro', 'wc-custom-installments' )
			);

			$wrapper_classes[] = 'wc-custom-installments-locked';
		}

		if ( ! $shown ) {
			$wrapper_classes[] = 'hidden';
		}

		$args = array(
			'id' => $overrides['id'] ?? $field['key'],
			'label' => $label,
			'description' => $field['description'] ?? '',
			'desc_tip' => true,
			'value' => get_post_meta( $post_id, $field['key'], true ),
			'wrapper_class' => implode( ' ', $wrapper_classes ),
		);

		if ( ! empty( $overrides['name'] ) ) {
			$args['name'] = $overrides['name'];
		}

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
			if ( self::is_locked( $field ) ) {
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
			if ( self::is_locked( $field ) ) {
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

			if ( self::is_locked( $field ) ) {
				continue;
			}

			$keys[] = $field['key'];
		}

		return $keys;
	}
}
