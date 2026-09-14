<?php

namespace MeuMouse\WC_Custom_Installments\Integrations\Elementor;

use MeuMouse\WC_Custom_Installments\Admin\Settings\Repository;
use MeuMouse\WC_Custom_Installments\Integrations\Elementor;
use MeuMouse\WC_Custom_Installments\Integrations\Elementor\Widgets\Style_Controls;

defined('ABSPATH') || exit;

/**
 * Add the price group's style controls to other plugins' product widgets.
 *
 * Elementor Pro, Woodmart and XStore render product prices in widgets of their
 * own, and the price group WooCommerce hands them is ours. This adds the same
 * style sections the plugin's price widget has, right after a named section of
 * each of those widgets, under the `wci_` IDs 5.x stored in saved pages.
 *
 * Built on `elementor/init`, so it never exists without Elementor.
 *
 * @since 6.0.0
 * @package MeuMouse\WC_Custom_Installments\Integrations\Elementor
 * @author MeuMouse.com
 */
class Inject_Controllers {

	/**
	 * Widget name => section IDs, once resolved for this request.
	 *
	 * Elementor calls the section hook for every section of every widget, and
	 * running the filter each time is what it would otherwise cost.
	 *
	 * @since 6.0.0
	 * @var array<string,array<int,string>>|null
	 */
	private $widgets = null;

	/**
	 * Widgets that already received the controls.
	 *
	 * A widget can name more than one section, and a control declared twice
	 * is an Elementor error.
	 *
	 * @since 6.0.0
	 * @var array<string,bool>
	 */
	private $injected = array();


	/**
	 * Register the hooks, when the Elementor integration is on.
	 *
	 * @since 6.0.0
	 * @return void
	 */
	public function __construct() {
		if ( ! defined('ELEMENTOR_VERSION') || ! Repository::is_active('enable_elementor_widgets') ) {
			return;
		}

		add_filter( 'WC_Custom_Installments/Elementor/Inject_Controllers', array( $this, 'add_xstore_widgets' ), 5 );
		add_action( 'elementor/element/after_section_end', array( $this, 'inject_controllers' ), 10, 3 );
	}


	/**
	 * Add the XStore widgets 5.x decorated.
	 *
	 * @since 6.0.0
	 * @param array<string,string|array<int,string>> $widgets | Widget name => style section
	 * @return array<string,string|array<int,string>>
	 */
	public function add_xstore_widgets( $widgets ) {
		if ( ! self::is_xstore() ) {
			return $widgets;
		}

		return array_merge( (array) $widgets, array(
			'etheme_product_carousel' => 'section_product_style',
			'woocommerce-product-etheme_cross_sells' => 'section_general_style_section',
			'etheme_product_list' => 'section_product_style',
			'et-custom-products-masonry' => 'style_section',
			'et-advanced-tabs' => 'et_section_tabs_style_settings',
			'etheme_ajax_search' => 'section_style_general',
			'woocommerce-product-etheme_related' => 'section_general_style_section',
		) );
	}


	/**
	 * Add the controls once a widget's named section has closed.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @param string $section_id | ID of the section that just closed
	 * @param array<string,mixed> $args | Section arguments
	 * @return void
	 */
	public function inject_controllers( $element, $section_id, $args ) {
		$name = $element->get_name();

		if ( isset( $this->injected[ $name ] ) ) {
			return;
		}

		$widgets = $this->get_widgets();

		if ( ! isset( $widgets[ $name ] ) || ! in_array( $section_id, $widgets[ $name ], true ) ) {
			return;
		}

		$this->injected[ $name ] = true;

		self::add_controllers( $element );
	}


	/**
	 * Add the price group's style controls to a widget.
	 *
	 * Public and static, as in 5.x, so a theme can decorate a widget of its own
	 * from its own hook.
	 *
	 * @since 6.0.0
	 * @param object $element | Elementor controls stack
	 * @return void
	 */
	public static function add_controllers( $element ) {
		Style_Controls::price_group( $element, true );
	}


	/**
	 * The widgets to decorate, with every section each one names.
	 *
	 * @since 6.0.0
	 * @return array<string,array<int,string>>
	 */
	private function get_widgets() {
		if ( null !== $this->widgets ) {
			return $this->widgets;
		}

		$this->widgets = array();

		foreach ( Elementor::get_injected_controllers() as $widget => $sections ) {
			$sections = array_values( array_filter( array_map( 'strval', (array) $sections ) ) );

			if ( ! empty( $sections ) ) {
				$this->widgets[ (string) $widget ] = $sections;
			}
		}

		return $this->widgets;
	}


	/**
	 * Whether XStore is the active theme.
	 *
	 * @since 6.0.0
	 * @return bool
	 */
	public static function is_xstore() {
		return defined('ETHEME_THEME_NAME') && 'XStore' === constant('ETHEME_THEME_NAME');
	}
}
