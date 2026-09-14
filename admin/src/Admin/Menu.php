<?php

namespace MeuMouse\WC_Custom_Installments\Admin;

use MeuMouse\WC_Custom_Installments\Core\Helpers;
use MeuMouse\WC_Custom_Installments\Core\Onboarding;

defined('ABSPATH') || exit;

/**
 * Register the plugin's admin screens.
 *
 * The settings screen sits under WooCommerce, where a store owner looks for it.
 * The setup wizard is reachable by URL only: it is a one-off flow, not a
 * destination that belongs in a menu.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Admin
 * @author MeuMouse.com
 */
class Menu {

	/**
	 * Admin page slug of the settings screen.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const SETTINGS_PAGE = 'wc-custom-installments';


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
	 * Add the settings screen and the hidden wizard screen.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Parcelas Customizadas', 'wc-custom-installments' ),
			esc_html__( 'Parcelas Customizadas', 'wc-custom-installments' ),
			Helpers::capability(),
			self::SETTINGS_PAGE,
			array( $this, 'render_settings_page' )
		);

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
			esc_url( admin_url( 'admin.php?page=' . self::SETTINGS_PAGE ) ),
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
	 * Render the setup wizard shell.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function render_onboarding_page() {
		require WC_CUSTOM_INSTALLMENTS_SRC . 'Views/onboarding.php';
	}
}
