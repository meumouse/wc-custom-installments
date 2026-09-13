<?php

namespace MeuMouse\WC_Custom_Installments\Assets;

use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Core\Onboarding;
use MeuMouse\WC_Custom_Installments\Core\Scripts;
use MeuMouse\WC_Custom_Installments\Admin\Menu;

defined('ABSPATH') || exit;

/**
 * Enqueue the Vite-built admin apps.
 *
 * Each admin screen is its own Vue application. The browser receives only what
 * it needs to fetch its payload — the REST root, a nonce, the page slug and the
 * endpoint — and requests the rest over REST, so no heavy payload is embedded
 * in the HTML.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Assets
 * @author MeuMouse.com
 */
class Admin_Assets extends Abstract_Assets {

	/**
	 * Admin page slug to Vite entry source.
	 *
	 * @since 6.0.0
	 * @var array<string,string>
	 */
	private $entries = array(
		Menu::SETTINGS_PAGE => 'src/entries/settings.js',
		Onboarding::PAGE    => 'src/entries/onboarding.js',
	);

	/**
	 * Admin page slug to script handle.
	 *
	 * @since 6.0.0
	 * @var array<string,string>
	 */
	private $handles = array(
		Menu::SETTINGS_PAGE => 'wc-custom-installments-settings-app',
		Onboarding::PAGE    => 'wc-custom-installments-onboarding-app',
	);

	/**
	 * Admin page slug to bootstrap page name and REST endpoint.
	 *
	 * @since 6.0.0
	 * @var array<string,array<string,string>>
	 */
	private $bootstrap = array(
		Menu::SETTINGS_PAGE => array( 'page' => 'settings', 'endpoint' => 'admin/settings' ),
		Onboarding::PAGE    => array( 'page' => 'onboarding', 'endpoint' => 'admin/onboarding' ),
	);


	/**
	 * Register the asset hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ), 100 );
		add_filter( 'script_loader_tag', array( $this, 'add_module_type_attribute' ), 10, 2 );
		add_filter( 'load_script_translation_file', array( __CLASS__, 'resolve_script_translation_file' ), 10, 3 );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}


	/**
	 * Enqueue the app built for the current admin screen.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function enqueue_assets() {
		$page = Helpers::current_admin_page();

		if ( '' === $page || ! isset( $this->entries[ $page ] ) ) {
			return;
		}

		$assets = Scripts::get_entry_assets( $this->entries[ $page ] );

		if ( empty( $assets['script'] ) ) {
			add_action( 'admin_notices', array( $this, 'missing_build_notice' ) );

			return;
		}

		$asset_version = ! empty( $assets['version'] ) ? $assets['version'] : $this->version;

		if ( ! empty( $assets['styles'] ) ) {
			foreach ( $assets['styles'] as $index => $style_url ) {
				wp_enqueue_style(
					'wc-custom-installments-' . sanitize_key( $page ) . '-' . $index,
					$style_url,
					array(),
					$asset_version
				);
			}
		}

		$handle = $this->handles[ $page ];

		wp_enqueue_script( $handle, $assets['script'], array( 'wp-i18n' ), $asset_version, true );

		wp_set_script_translations( $handle, 'wc-custom-installments', WC_CUSTOM_INSTALLMENTS_LANGUAGES );

		wp_localize_script( $handle, 'wcCustomInstallmentsBootstrap', array(
			'restUrl'  => esc_url_raw( rest_url( WC_CUSTOM_INSTALLMENTS_REST ) ),
			'nonce'    => wp_create_nonce('wp_rest'),
			'page'     => $this->bootstrap[ $page ]['page'],
			'endpoint' => $this->bootstrap[ $page ]['endpoint'],
		) );
	}


	/**
	 * Mark our entry scripts as ES modules.
	 *
	 * WordPress hands this filter the whole printed block for the handle — the
	 * script tag plus any inline scripts it prepended, notably the
	 * `wp.i18n.setLocaleData` block from wp_set_script_translations(). Rebuilding
	 * the tag would drop those and the app would render untranslated, so the
	 * attribute is injected into this handle's own tag alone, matched by its id.
	 *
	 * @since 6.0.0
	 * @param string $tag | Printed script markup
	 * @param string $handle | Script handle
	 * @return string
	 */
	public function add_module_type_attribute( $tag, $handle ) {
		$tag = is_scalar( $tag ) ? (string) $tag : '';

		if ( ! in_array( $handle, $this->handles, true ) ) {
			return $tag;
		}

		$pattern = '#<script\b(?![^>]*\stype=)([^>]*\sid=(["\'])' . preg_quote( $handle, '#' ) . '-js\2[^>]*)>#';
		$result = preg_replace( $pattern, '<script type="module"$1>', $tag, 1 );

		return null === $result ? $tag : $result;
	}


	/**
	 * Point WordPress at our handle-named JSON translation files.
	 *
	 * Core resolves script translations as "{domain}-{locale}-{md5(src)}.json",
	 * while the languages pipeline emits "{domain}-{locale}-{handle}.json".
	 * Vite hashes nothing in the entry path we can predict ahead of the build,
	 * so a stable md5-based name cannot be generated; remap instead.
	 *
	 * @since 6.0.0
	 * @param string|false $file | Path core resolved
	 * @param string $handle | Script handle
	 * @param string $domain | Text domain
	 * @return string|false
	 */
	public static function resolve_script_translation_file( $file, $handle, $domain ) {
		if ( 'wc-custom-installments' !== $domain ) {
			return $file;
		}

		$locale = determine_locale();
		$candidate = trailingslashit( WC_CUSTOM_INSTALLMENTS_LANGUAGES ) . "wc-custom-installments-{$locale}-{$handle}.json";

		return is_readable( $candidate ) ? $candidate : $file;
	}


	/**
	 * Notice shown when the frontend has not been built.
	 *
	 * Only a developer running from a Git checkout can see this — a released
	 * package always carries app/dist.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function missing_build_notice() {
		wp_admin_notice(
			esc_html__( 'O pacote de administração do Parcelas Customizadas para WooCommerce está ausente. Execute "npm run build" no diretório app.', 'wc-custom-installments' ),
			array( 'type' => 'error' )
		);
	}


	/**
	 * Flag our admin screens on the body.
	 *
	 * The admin stylesheet paints the page background from this class, so the
	 * apps sit on the same ground as Joinotify's instead of wp-admin's grey,
	 * and no other screen is touched.
	 *
	 * @since 6.0.0
	 * @param string $classes | Space-separated body classes
	 * @return string
	 */
	public function add_body_class( $classes ) {
		if ( ! isset( $this->entries[ Helpers::current_admin_page() ] ) ) {
			return $classes;
		}

		return trim( $classes . ' wc-custom-installments-screen' );
	}
}
