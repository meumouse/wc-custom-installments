<?php

namespace MeuMouse\WC_Custom_Installments\Admin\Onboarding;

use MeuMouse\WC_Custom_Installments\Admin\Menu;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry as Settings_Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Core\Migrator;
use MeuMouse\WC_Custom_Installments\Core\Onboarding;

defined('ABSPATH') || exit;

/**
 * Describe the setup wizard and what it may write.
 *
 * The wizard writes into the same option the settings screen does, through the
 * same repository, so there is one place that decides what a setting means and
 * one place that decides whether it may be written.
 *
 * @since 1.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin\Onboarding
 * @author MeuMouse.com
 */
class Registry {

	/**
	 * Settings the wizard is allowed to write.
	 *
	 * Everything else on the settings screen is out of its reach, so a
	 * malformed step payload can never touch an unrelated setting.
	 *
	 * @since 1.0.0
	 * @return array<int,string>
	 */
	public static function allowed_keys() {
		$keys = array(
			'max_qtd_installments',
			'max_qtd_installments_without_fee',
			'fee_installments_global',
			'min_value_installments',
			'enable_all_discount_options',
			'product_price_discount_method',
			'discount_main_price',
			'enable_pix_method_payment_form',
			'enable_ticket_method_payment_form',
			'enable_credit_card_method_payment_form',
			'enable_debit_card_method_payment_form',
			'hook_display_best_installments',
			'display_discount_price_hook',
			'display_installment_type',
		);

		foreach ( array_keys( \MeuMouse\WC_Custom_Installments\Admin\Default_Options::get_card_brands() ) as $brand ) {
			$keys[] = 'enable_' . $brand . '_flag_credit';
			$keys[] = 'enable_' . $brand . '_flag_debit';
		}

		/**
		 * Filter the settings the setup wizard may write.
		 *
		 * @since 1.0.0
		 * @param array<int,string> $keys | Setting names
		 * @return array<int,string>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Onboarding/Allowed_Keys', $keys );
	}


	/**
	 * The wizard steps, in order.
	 *
	 * Titles live here rather than in the browser so they go through the same
	 * translation pipeline as the rest of the backend copy.
	 *
	 * @since 1.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_steps() {
		$steps = array(
			array(
				'id' => 'welcome',
				'title' => __( 'Welcome', 'wc-custom-installments' ),
				'summary' => __( 'What this plugin puts on your storefront, and what it needs from you.', 'wc-custom-installments' ),
				'optional' => false,
			),
			array(
				'id' => 'installments',
				'title' => __( 'Instalments', 'wc-custom-installments' ),
				'summary' => __( 'How many instalments you accept, and what they cost.', 'wc-custom-installments' ),
				'optional' => false,
			),
			array(
				'id' => 'discount',
				'title' => __( 'Upfront discount', 'wc-custom-installments' ),
				'summary' => __( 'What a customer saves by paying in one go.', 'wc-custom-installments' ),
				'optional' => true,
			),
			array(
				'id' => 'payment-methods',
				'title' => __( 'Payment methods', 'wc-custom-installments' ),
				'summary' => __( 'Which methods and card brands to show.', 'wc-custom-installments' ),
				'optional' => true,
			),
			array(
				'id' => 'display',
				'title' => __( 'Where it appears', 'wc-custom-installments' ),
				'summary' => __( 'Placement on the shop and the product page.', 'wc-custom-installments' ),
				'optional' => false,
			),
			array(
				'id' => 'trial',
				'title' => __( 'Try Pro', 'wc-custom-installments' ),
				'summary' => __( 'Optional. Seven days of the paid features, with no card.', 'wc-custom-installments' ),
				'optional' => true,
			),
			array(
				'id' => 'finish',
				'title' => __( 'Done', 'wc-custom-installments' ),
				'summary' => __( 'Where to go from here.', 'wc-custom-installments' ),
				'optional' => false,
			),
		);

		/**
		 * Filter the setup wizard steps.
		 *
		 * @since 1.0.0
		 * @param array<int,array<string,mixed>> $steps | The steps, in order
		 * @return array<int,array<string,mixed>>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Onboarding/Steps', $steps );
	}


	/**
	 * Build the payload the wizard hydrates itself with.
	 *
	 * @since 1.0.0
	 * @return array<string,mixed>
	 */
	public static function get_bootstrap_data() {
		$settings = Repository::get_settings();
		$schema = Settings_Registry::get_schema();

		/**
		 * Filter the setup wizard bootstrap payload.
		 *
		 * @since 1.0.0
		 * @param array<string,mixed> $payload | Bootstrap payload
		 * @return array<string,mixed>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Admin/Onboarding_Bootstrap_Data', array(
			'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
			'page' => 'onboarding',
			'state' => Onboarding::get_state(),
			'steps' => self::get_steps(),
			'settings' => self::visible_settings( $settings ),
			'fields' => self::step_fields( $schema ),
			'brands' => \MeuMouse\WC_Custom_Installments\Admin\Default_Options::get_card_brands(),

			// A store upgrading from 5.x already answered all of this. Asking
			// again would risk them overwriting years of configuration with
			// whatever the wizard happens to default to.
			'migrated' => Migrator::has_legacy_data(),

			'pro' => array(
				'active' => Helpers::is_pro_active(),
				'trial_available' => Trial::is_available(),
				'trial_days' => Trial::DAYS,
				'store_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_STORE_URL ),
				'api_host' => wp_parse_url( WC_CUSTOM_INSTALLMENTS_API_URL, PHP_URL_HOST ),
			),
			'environment' => array(
				// WooCommerce hands symbols over as HTML entities (R$ is "&#82;&#36;"),
				// and Vue prints text, not markup, so the entities would show as-is.
				'currency_symbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'price_format' => html_entity_decode( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ), ENT_QUOTES, 'UTF-8' ),
				'decimal_separator' => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
			),
			'links' => array(
				'settings_url' => esc_url_raw( admin_url( 'admin.php?page=' . Menu::SETTINGS_PAGE ) ),
				'docs_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_DOCS_URL ),
				'shop_url' => esc_url_raw( function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : home_url() ),
				'privacy_url' => 'https://meumouse.com/politica-de-privacidade/',
				'terms_url' => 'https://meumouse.com/termos-condicionais/',
			),
			'rest' => array(
				'root' => esc_url_raw( rest_url( WC_CUSTOM_INSTALLMENTS_REST ) ),
				'nonce' => wp_create_nonce('wp_rest'),
			),
		) );
	}


	/**
	 * Narrow the settings to what the wizard actually shows.
	 *
	 * @since 1.0.0
	 * @param array<string,mixed> $settings | Full settings
	 * @return array<string,mixed>
	 */
	private static function visible_settings( $settings ) {
		return array_intersect_key( $settings, array_flip( self::allowed_keys() ) );
	}


	/**
	 * Lift the field definitions the wizard reuses out of the settings schema.
	 *
	 * The wizard shows a handful of the same controls the settings screen does.
	 * Copying their labels and options here would be two descriptions of one
	 * field, and they would disagree the first time either changed.
	 *
	 * @since 1.0.0
	 * @param array<int,array<string,mixed>> $schema | Settings schema
	 * @return array<string,array<string,mixed>>
	 */
	private static function step_fields( $schema ) {
		$wanted = array_flip( self::allowed_keys() );
		$fields = array();

		foreach ( $schema as $section ) {
			foreach ( (array) ( $section['cards'] ?? array() ) as $card ) {
				foreach ( (array) ( $card['fields'] ?? array() ) as $field ) {
					if ( isset( $field['key'], $wanted[ $field['key'] ] ) ) {
						$fields[ $field['key'] ] = $field;
					}
				}
			}
		}

		return $fields;
	}
}
