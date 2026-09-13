<?php

namespace MeuMouse\WC_Custom_Installments\Integrations;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Compatibility\Hook_Aliases;
use MeuMouse\WC_Custom_Installments\Core\Helpers;

defined('ABSPATH') || exit;

/**
 * Register the plugin's Elementor widgets.
 *
 * Widget names are the contract here: they are stored inside every page that
 * uses one, so a rename deletes the widget from those pages. The 5.x names are
 * kept exactly, and the Pro add-on registers the rest of them under theirs.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations
 * @author MeuMouse.com
 */
class Elementor {

	/**
	 * The widget category the plugin's widgets appear under.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	const CATEGORY = 'wc-custom-installments';


	/**
	 * Register the Elementor hooks.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! defined('ELEMENTOR_VERSION') || ! Repository::is_active('enable_elementor_widgets') ) {
			return;
		}

		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
		add_filter( 'WC_Custom_Installments/Assets/Set_Product_Id', array( $this, 'set_preview_product' ) );
	}


	/**
	 * Add the plugin's own category to the widget panel.
	 *
	 * @since 6.0.0
	 * @param object $manager | Elementor elements manager
	 * @return void
	 */
	public function register_category( $manager ) {
		$manager->add_category( self::CATEGORY, array(
			'title' => esc_html__( 'Custom Installments', 'wc-custom-installments' ),
			'icon' => 'eicon-product-price',
		) );
	}


	/**
	 * Register every widget the site is entitled to.
	 *
	 * @since 6.0.0
	 * @param object $manager | Elementor widgets manager
	 * @return void
	 */
	public function register_widgets( $manager ) {
		/**
		 * Filter the Elementor widget classes to register.
		 *
		 * The Pro add-on appends its own here. Each entry is a fully qualified
		 * class name extending Elementor's widget base.
		 *
		 * @since 6.0.0
		 * @param array<int,string> $widgets | Widget class names
		 * @return array<int,string>
		 */
		$widgets = (array) apply_filters( 'WC_Custom_Installments/Elementor/Widgets', array(
			Elementor\Widgets\Single_Product_Price::class,
			Elementor\Widgets\Installments_Table::class,
		) );

		foreach ( $widgets as $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}

			$manager->register( new $class() );
		}
	}


	/**
	 * Give the editor a product to render against.
	 *
	 * @since 6.0.0
	 * @param int $product_id | Product ID resolved so far
	 * @return int
	 */
	public function set_preview_product( $product_id ) {
		if ( $product_id || ! Helpers::is_builder_preview() ) {
			return $product_id;
		}

		$product = Helpers::get_first_product();

		return $product ? $product->get_id() : $product_id;
	}


	/**
	 * The widgets other plugins asked us to decorate.
	 *
	 * Themes and page builders register their own product widgets, and each one
	 * names the controls section its price styling belongs in.
	 *
	 * @since 6.0.0
	 * @return array<string,string>
	 */
	public static function get_injected_controllers() {
		/**
		 * Filter the third-party widgets the plugin adds controls to.
		 *
		 * @since 6.0.0
		 * @param array<string,string> $widgets | Widget name => style section id
		 * @return array<string,string>
		 */
		return (array) Hook_Aliases::apply_filters( 'WC_Custom_Installments/Elementor/Inject_Controllers', array() );
	}
}
