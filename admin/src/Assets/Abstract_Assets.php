<?php

namespace MeuMouse\WC_Custom_Installments\Assets;

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
}
