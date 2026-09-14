<?php

namespace MeuMouse\WC_Custom_Installments\Frontend;

use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;

defined('ABSPATH') || exit;

/**
 * Let the plugin supply WooCommerce templates.
 *
 * The plugin's copy wins over the theme's, as it did in 5.x: many themes ship
 * their own `single-product/price.php`, and without the plugin's container and
 * the per-variation price list inside it, the price stops changing when a
 * shopper picks a variation. A theme that wants to restyle it still can, in
 * the plugin's own override folder: `yourtheme/wc-custom-installments/…`.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Frontend
 * @author MeuMouse.com
 */
class Templates {

	/**
	 * Folder inside a theme that overrides the plugin's templates.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const THEME_FOLDER = 'wc-custom-installments/';


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
		$plugin_template = WC_CUSTOM_INSTALLMENTS_TEMPLATES . $template_name;

		if ( ! file_exists( $plugin_template ) ) {
			return $template;
		}

		// A copy made for this plugin is a deliberate choice, and it wins.
		$plugin_override = locate_template( array( self::THEME_FOLDER . $template_name ) );

		if ( $plugin_override ) {
			return $plugin_override;
		}

		$theme_override = locate_template( array(
			trailingslashit( (string) $template_path ) . $template_name,
			$template_name,
		) );

		/**
		 * Filter whether the theme's WooCommerce copy of a template wins over the plugin's.
		 *
		 * @since 6.0.0
		 * @param bool $theme_wins | Whether the theme's copy is used
		 * @param string $template_name | Template being looked up
		 * @param string $theme_override | Path of the theme's copy, or an empty string
		 * @return bool
		 */
		$theme_wins = (bool) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Templates/Theme_Override_Wins', false, $template_name, (string) $theme_override );

		if ( $theme_override && $theme_wins ) {
			return $template;
		}

		return $plugin_template;
	}
}
