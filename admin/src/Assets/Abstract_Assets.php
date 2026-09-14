<?php

namespace MeuMouse\WC_Custom_Installments\Assets;

use MeuMouse\WC_Custom_Installments\Core\Scripts;

defined('ABSPATH') || exit;

/**
 * Shared runtime context for the asset loaders.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Assets
 * @author MeuMouse.com
 */
abstract class Abstract_Assets {

	/**
	 * Style handle of the bundled Font Awesome Free.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const FONT_AWESOME_HANDLE = 'wc-custom-installments-font-awesome';

	/**
	 * Base URL of the static assets directory.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $assets_url = '';

	/**
	 * Current plugin version.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	protected $version = '';

	/**
	 * Whether debug mode is enabled.
	 *
	 * @since 6.0.0
	 * @var bool
	 */
	protected $debug_mode = false;

	/**
	 * Whether developer mode is enabled.
	 *
	 * @since 6.0.0
	 * @var bool
	 */
	protected $dev_mode = false;


	/**
	 * Read the shared context from the plugin constants.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		$this->assets_url = defined('WC_CUSTOM_INSTALLMENTS_ASSETS') ? WC_CUSTOM_INSTALLMENTS_ASSETS : '';
		$this->version = defined('WC_CUSTOM_INSTALLMENTS_VERSION') ? WC_CUSTOM_INSTALLMENTS_VERSION : '';
		$this->debug_mode = defined('WC_CUSTOM_INSTALLMENTS_DEBUG_MODE') ? (bool) WC_CUSTOM_INSTALLMENTS_DEBUG_MODE : false;
		$this->dev_mode = defined('WC_CUSTOM_INSTALLMENTS_DEV_MODE') ? (bool) WC_CUSTOM_INSTALLMENTS_DEV_MODE : false;
	}


	/**
	 * Enqueue the stylesheet of the bundled Font Awesome Free.
	 *
	 * Served from the plugin itself, so loading it reaches no external server.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	protected function enqueue_font_awesome_styles() {
		$assets = Scripts::get_entry_assets('src/entries/font-awesome.js');

		if ( empty( $assets['styles'] ) ) {
			return;
		}

		$version = ! empty( $assets['version'] ) ? $assets['version'] : $this->version;

		foreach ( array_values( (array) $assets['styles'] ) as $index => $style_url ) {
			$handle = 0 === $index ? self::FONT_AWESOME_HANDLE : self::FONT_AWESOME_HANDLE . '-' . $index;

			wp_enqueue_style( $handle, $style_url, array(), $version );
		}
	}
}
