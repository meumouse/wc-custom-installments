<?php

namespace MeuMouse\WC_Custom_Installments\Admin;

use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Core\Onboarding;

defined('ABSPATH') || exit;

/**
 * Register the plugin's admin screens.
 *
 * The plugin has a menu of its own: the settings screen is its first entry,
 * and the screens an add-on registers through `WC_Custom_Installments/Admin/Pages`
 * follow it. The setup wizard is reachable by URL only: it is a one-off flow,
 * not a destination that belongs in a menu.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin
 * @author MeuMouse.com
 */
class Menu {

	/**
	 * Admin page slug of the settings screen, which is also the menu's own.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const SETTINGS_PAGE = 'wc-custom-installments';

	/**
	 * Admin page slug the 5.x settings screen lived at, under WooCommerce.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const LEGACY_PAGE = 'woo-custom-installments';


	/**
	 * Register the admin menu hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_filter( 'plugin_action_links_' . WC_CUSTOM_INSTALLMENTS_BASENAME, array( $this, 'add_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta_links' ), 10, 2 );
	}


	/**
	 * Add the menu, its screens and the hidden ones.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			esc_html__( 'Parcelas Customizadas', 'wc-custom-installments' ),
			esc_html__( 'Parcelas Customizadas', 'wc-custom-installments' ),
			Helpers::capability(),
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' ),
			self::menu_icon(),
			self::menu_position()
		);

		// Repeating the parent slug turns the menu's first entry into
		// "Configurações" instead of a second copy of the plugin name.
		add_submenu_page(
			self::SETTINGS_PAGE,
			esc_html__( 'Configurações', 'wc-custom-installments' ),
			esc_html__( 'Configurações', 'wc-custom-installments' ),
			Helpers::capability(),
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' )
		);

		foreach ( self::get_pages() as $slug => $page ) {
			add_submenu_page(
				self::SETTINGS_PAGE,
				esc_html( $page['title'] ),
				esc_html( $page['menu_title'] ),
				Helpers::capability(),
				$slug,
				array( $this, 'render_addon_page' )
			);
		}

		add_submenu_page(
			'',
			esc_html__( 'Configuração do Parcelas Customizadas', 'wc-custom-installments' ),
			esc_html__( 'Configuração do Parcelas Customizadas', 'wc-custom-installments' ),
			Helpers::capability(),
			Onboarding::PAGE,
			array( $this, 'render_onboarding_page' )
		);

		// A submenu registered under an empty parent never lands in the global
		// $submenu array, so core cannot resolve a title and admin-header.php
		// ends up passing null to strip_tags(). Seed it before the header runs.
		if ( Onboarding::PAGE === Helpers::current_admin_page() ) {
			$GLOBALS['title'] = esc_html__( 'Configuração do Parcelas Customizadas', 'wc-custom-installments' );
		}

		$this->register_legacy_redirect();
	}


	/**
	 * The screens add-ons place in the plugin's menu, keyed by page slug.
	 *
	 * Each one is drawn by the host (Views/addon-page.php): the page header, a
	 * panel, and a mount point the add-on fills with its own app, registered in
	 * `window.WCCustomInstallmentsSections` under `component` — the same contract
	 * as a settings tab an add-on renders.
	 *
	 * @since 6.0.0
	 * @return array<string,array{title:string,menu_title:string,description:string,component:string,position:int}>
	 */
	public static function get_pages() {
		/**
		 * Filter the screens add-ons place in the plugin's menu.
		 *
		 * Keyed by page slug. Each entry takes a `title`, a `component` (the name
		 * the add-on registers its renderer under), and optionally a `menu_title`,
		 * a `description` and a `position` ordering it among the other add-on
		 * screens, lowest first.
		 *
		 * @since 6.0.0
		 * @param array<string,array<string,mixed>> $pages | Add-on screens
		 * @return array<string,array<string,mixed>>
		 */
		$pages = (array) apply_filters( 'WC_Custom_Installments/Admin/Pages', array() );
		$clean = array();

		foreach ( $pages as $slug => $page ) {
			$slug = sanitize_key( (string) $slug );

			if ( '' === $slug || self::SETTINGS_PAGE === $slug || Onboarding::PAGE === $slug || ! is_array( $page ) ) {
				continue;
			}

			$title = (string) ( $page['title'] ?? '' );
			$component = (string) ( $page['component'] ?? '' );

			if ( '' === $title || '' === $component ) {
				continue;
			}

			$clean[ $slug ] = array(
				'title' => $title,
				'menu_title' => (string) ( $page['menu_title'] ?? $title ),
				'description' => (string) ( $page['description'] ?? '' ),
				'component' => $component,
				'position' => (int) ( $page['position'] ?? 10 ),
			);
		}

		uasort( $clean, static function( $a, $b ) {
			return $a['position'] <=> $b['position'];
		} );

		return $clean;
	}


	/**
	 * The address of one of the plugin's screens.
	 *
	 * @since 6.0.0
	 * @param string $page | Page slug; the settings screen when empty
	 * @param array<string,string> $args | Extra query arguments
	 * @return string
	 */
	public static function page_url( $page = '', $args = array() ) {
		$args = array_merge( array( 'page' => '' !== $page ? $page : self::SETTINGS_PAGE ), $args );

		return add_query_arg( $args, admin_url('admin.php') );
	}


	/**
	 * The address of the licence screen, when an add-on provides one.
	 *
	 * The free plugin has no licence; the Pro add-on answers with its screen.
	 *
	 * @since 6.0.0
	 * @return string Empty when no add-on provides one.
	 */
	public static function license_url() {
		/**
		 * Filter the address of the licence screen.
		 *
		 * @since 6.0.0
		 * @param string $url | Licence screen address; empty when there is none
		 * @return string
		 */
		return (string) apply_filters( 'WC_Custom_Installments/Admin/License_Url', '' );
	}


	/**
	 * The menu icon: the logo, in one colour, as a data URI.
	 *
	 * WordPress repaints a base64 SVG icon in the admin colour scheme (the
	 * svg-painter script rewrites its fills), so it follows the menu's idle,
	 * hover and current colours like a dashicon does.
	 *
	 * @since 6.0.0
	 * @return string
	 */
	private static function menu_icon() {
		$file = WC_CUSTOM_INSTALLMENTS_DIR . 'assets/brand/menu-icon.svg';
		$svg = is_readable( $file ) ? (string) file_get_contents( $file ) : '';

		if ( '' === $svg ) {
			return 'dashicons-money-alt';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress expects a base64 data URI for SVG menu icons.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}


	/**
	 * Where the menu sits: below WooCommerce, Analytics and Marketing.
	 *
	 * @since 6.0.0
	 * @return float
	 */
	private static function menu_position() {
		/**
		 * Filter the position of the plugin's menu in the admin menu.
		 *
		 * @since 6.0.0
		 * @param float $position | Menu position
		 * @return float
		 */
		return (float) apply_filters( 'WC_Custom_Installments/Admin/Menu_Position', 58.5 );
	}


	/**
	 * Keep the 5.x settings address working.
	 *
	 * 5.x lived at WooCommerce › Parcelas Customizadas, under another slug. A
	 * bookmark to it would otherwise answer "not allowed", so a hidden page by
	 * that slug sends it to the settings screen. Skipped while 5.x itself is
	 * active: the slug is still its own then.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	private function register_legacy_redirect() {
		if ( Helpers::is_legacy_plugin_active() ) {
			return;
		}

		$hook = add_submenu_page( '', '', '', Helpers::capability(), self::LEGACY_PAGE, '__return_null' );

		if ( $hook ) {
			add_action( 'load-' . $hook, array( $this, 'redirect_legacy_page' ) );
		}
	}


	/**
	 * Send the 5.x address to the settings screen.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function redirect_legacy_page() {
		wp_safe_redirect( self::page_url() );
		exit;
	}


	/**
	 * Add a Settings link to the plugins list row.
	 *
	 * @since 6.0.0
	 * @param array<int,string> $links | Existing action links
	 * @return array<int,string>
	 */
	public function add_action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::page_url() ),
			esc_html__( 'Configurações', 'wc-custom-installments' )
		);

		array_unshift( $links, $settings );

		return $links;
	}


	/**
	 * Add a Documentation link beside the version and author in the plugins list.
	 *
	 * Where 5.x put it, so a store owner looking for help finds it in the same
	 * place.
	 *
	 * @since 6.0.0
	 * @param array<int|string,string> $meta | Existing row meta
	 * @param string $plugin_file | Plugin path relative to the plugins directory
	 * @return array<int|string,string>
	 */
	public function add_row_meta_links( $meta, $plugin_file ) {
		if ( WC_CUSTOM_INSTALLMENTS_BASENAME !== $plugin_file ) {
			return $meta;
		}

		$meta['docs'] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( WC_CUSTOM_INSTALLMENTS_DOCS_URL ),
			esc_html__( 'Documentação', 'wc-custom-installments' )
		);

		return $meta;
	}


	/**
	 * Render the settings screen shell.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_settings_page() {
		require WC_CUSTOM_INSTALLMENTS_SRC . 'Views/settings.php';
	}


	/**
	 * Render the shell of a screen an add-on fills.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_addon_page() {
		require WC_CUSTOM_INSTALLMENTS_SRC . 'Views/addon-page.php';
	}


	/**
	 * Render the setup wizard shell.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_onboarding_page() {
		require WC_CUSTOM_INSTALLMENTS_SRC . 'Views/onboarding.php';
	}
}
