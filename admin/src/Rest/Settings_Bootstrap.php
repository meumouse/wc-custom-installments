<?php

namespace MeuMouse\WC_Custom_Installments\Rest;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Registry;
use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Core\Features;
use MeuMouse\WC_Custom_Installments\Core\Helpers;
use WP_REST_Request;

defined('ABSPATH') || exit;

/**
 * Hydrate the settings screen.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Rest
 * @author MeuMouse.com
 */
class Settings_Bootstrap extends Abstract_Route {

	/**
	 * Route path.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $route = '/admin/settings';

	/**
	 * Allowed HTTP methods.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $methods = 'GET';


	/**
	 * Handle the request.
	 *
	 * @since 6.0.0
	 * @param WP_REST_Request $request | REST request instance
	 * @return \WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		/**
		 * Filter the settings bootstrap payload.
		 *
		 * @since 6.0.0
		 * @param array<string,mixed> $payload | Bootstrap payload
		 * @return array<string,mixed>
		 */
		$payload = (array) apply_filters( 'WC_Custom_Installments/Admin/Bootstrap_Data', array(
			'version' => WC_CUSTOM_INSTALLMENTS_VERSION,
			'page' => 'settings',
			'schema' => Registry::get_schema(),
			'settings' => Repository::normalize_settings( Repository::get_settings() ),
			'features' => Features::all(),
			'system_status' => self::system_status(),
			'pro' => array(
				'active' => Helpers::is_pro_active(),
				'store_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_STORE_URL ),
				'docs_url' => esc_url_raw( WC_CUSTOM_INSTALLMENTS_DOCS_URL ),
			),
			'environment' => array(
				// Decoded: WooCommerce returns entities, and the app renders text.
				'currency_symbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'price_format' => html_entity_decode( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ), ENT_QUOTES, 'UTF-8' ),
				'decimal_separator' => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
			),
			'rest' => array(
				'root' => esc_url_raw( rest_url( WC_CUSTOM_INSTALLMENTS_REST ) ),
				'nonce' => wp_create_nonce('wp_rest'),
			),
		) );

		return rest_ensure_response( $payload );
	}


	/**
	 * The environment facts a support request usually needs.
	 *
	 * Each row says whether it looks healthy, so the screen can flag a limit
	 * below what the plugin is known to need without judging anything itself.
	 *
	 * @since 6.0.0
	 * @return array<int,array<string,mixed>>
	 */
	public static function system_status() {
		global $wp_version;

		$yes = __( 'Sim', 'wc-custom-installments' );
		$no = __( 'Não', 'wc-custom-installments' );
		$memory = (string) ini_get('memory_limit');
		$post_max = (string) ini_get('post_max_size');
		$upload_max = (string) ini_get('upload_max_filesize');
		$time_limit = (int) ini_get('max_execution_time');
		$input_vars = (int) ini_get('max_input_vars');

		$rows = array(
			array(
				'group' => 'WordPress',
				'label' => __( 'Versão do WordPress', 'wc-custom-installments' ),
				'value' => (string) $wp_version,
				'ok' => version_compare( (string) $wp_version, '6.5', '>=' ),
				'hint' => __( 'A versão mínima exigida é 6.5.', 'wc-custom-installments' ),
			),
			array(
				'group' => 'WordPress',
				'label' => __( 'WordPress Multisite', 'wc-custom-installments' ),
				'value' => is_multisite() ? $yes : $no,
				'ok' => true,
			),
			array(
				'group' => 'WordPress',
				'label' => __( 'Modo de depuração do WordPress', 'wc-custom-installments' ),
				'value' => ( defined('WP_DEBUG') && WP_DEBUG ) ? $yes : $no,
				'ok' => true,
			),
			array(
				'group' => 'WooCommerce',
				'label' => __( 'Versão do WooCommerce', 'wc-custom-installments' ),
				'value' => defined('WC_VERSION') ? WC_VERSION : '—',
				'ok' => defined('WC_VERSION') && version_compare( WC_VERSION, '8.0', '>=' ),
				'hint' => __( 'A versão mínima exigida é 8.0.', 'wc-custom-installments' ),
			),
			array(
				'group' => 'WooCommerce',
				'label' => __( 'Versão do Parcelas Customizadas', 'wc-custom-installments' ),
				'value' => WC_CUSTOM_INSTALLMENTS_VERSION,
				'ok' => true,
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Versão do PHP', 'wc-custom-installments' ),
				'value' => PHP_VERSION,
				'ok' => version_compare( PHP_VERSION, '8.1', '>=' ),
				'hint' => __( 'A versão mínima exigida é 8.1.', 'wc-custom-installments' ),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Extensão DOMDocument', 'wc-custom-installments' ),
				'value' => class_exists('DOMDocument') ? $yes : $no,
				'ok' => class_exists('DOMDocument'),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Extensão cURL', 'wc-custom-installments' ),
				'value' => function_exists('curl_version') ? $yes : $no,
				'ok' => function_exists('curl_version'),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Extensão OpenSSL', 'wc-custom-installments' ),
				'value' => extension_loaded('openssl') ? $yes : $no,
				'ok' => extension_loaded('openssl'),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Limite de memória do PHP', 'wc-custom-installments' ),
				'value' => $memory,
				'ok' => '-1' === $memory || wp_convert_hr_to_bytes( $memory ) >= 128 * MB_IN_BYTES,
				'hint' => __( 'O valor mínimo recomendado é 128M.', 'wc-custom-installments' ),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Tamanho máximo da postagem do PHP', 'wc-custom-installments' ),
				'value' => $post_max,
				'ok' => wp_convert_hr_to_bytes( $post_max ) >= 64 * MB_IN_BYTES,
				'hint' => __( 'O valor mínimo recomendado é 64M.', 'wc-custom-installments' ),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Tamanho máximo de envio do PHP', 'wc-custom-installments' ),
				'value' => $upload_max,
				'ok' => wp_convert_hr_to_bytes( $upload_max ) >= 64 * MB_IN_BYTES,
				'hint' => __( 'O valor mínimo recomendado é 64M.', 'wc-custom-installments' ),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Limite de tempo do PHP', 'wc-custom-installments' ),
				'value' => (string) $time_limit,
				'ok' => 0 === $time_limit || $time_limit >= 180,
				'hint' => __( 'O valor mínimo recomendado é 180.', 'wc-custom-installments' ),
			),
			array(
				'group' => __( 'Servidor', 'wc-custom-installments' ),
				'label' => __( 'Variáveis máximas de entrada do PHP', 'wc-custom-installments' ),
				'value' => (string) $input_vars,
				'ok' => $input_vars >= 10000,
				'hint' => __( 'O valor mínimo recomendado é 10000.', 'wc-custom-installments' ),
			),
		);

		/**
		 * Filter the rows of the system status card.
		 *
		 * @since 6.0.0
		 * @param array<int,array<string,mixed>> $rows | Status rows
		 * @return array<int,array<string,mixed>>
		 */
		return (array) apply_filters( 'WC_Custom_Installments/Admin/System_Status', $rows );
	}
}
