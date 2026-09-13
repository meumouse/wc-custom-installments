<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

defined('ABSPATH') || exit;

/**
 * Let the plugin supply WooCommerce templates.
 *
 * The plugin's copy is offered only where the theme has none: `locate_template`
 * has already looked in the theme by the time this filter runs, so a theme
 * override at `yourtheme/woocommerce/single-product/price.php` keeps winning
 * exactly as it does without the plugin.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Templates {

	/**
	 * Register the template filter.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 10, 3 );
	}


	/**
	 * Point WooCommerce at the plugin's copy of a template.
	 *
	 * @since 6.0.0
	 * @param string $template | Path WooCommerce resolved
	 * @param string $template_name | Template being looked up
	 * @param string $template_path | Theme template directory
	 * @return string
	 */
	public function locate_template( $template, $template_name, $template_path ) {
		$theme_override = locate_template( array(
			trailingslashit( $template_path ) . $template_name,
			$template_name,
		) );

		// The theme said something about this template. It wins.
		if ( $theme_override ) {
			return $template;
		}

		$plugin_template = WC_CUSTOM_INSTALLMENTS_TEMPLATES . $template_name;

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}
}
